<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$success_msg = '';
$error_msg = '';
$confirmed_booking = null;
$preselected_room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$booking_page_path = $booking_page_path ?? 'user/booking.php';
$booking_always_show_flow = $booking_always_show_flow ?? false;
$booking_show_room_showcase = $booking_show_room_showcase ?? true;
ensure_user_auth_schema($conn);
$current_site_user = get_current_site_user($conn);
$loyalty_points_earned = 0;
$loyalty_settings = get_loyalty_settings($conn);
$loyalty_earn_display = rtrim(rtrim(number_format((float)$loyalty_settings['earn_per_jod'], 2), '0'), '.');
$loyalty_redeem_display = rtrim(rtrim(number_format((float)$loyalty_settings['redeem_points_per_jod'], 2), '0'), '.');

if (!$current_site_user) {
    $booking_redirect_target = $booking_page_path . ($preselected_room_id > 0 ? '?room_id=' . $preselected_room_id : '') . '#booking-form';
    $_SESSION['post_login_redirect'] = $booking_redirect_target;
    header('Location: ' . site_url('general/login.php?redirect=' . urlencode($booking_redirect_target)));
    exit;
}

$available_menu_items = [];
$menu_result = mysqli_query($conn, "SELECT id, item_name, item_category, item_price, item_description FROM menu_items WHERE is_available = 1 AND item_category IN ('Drinks', 'Snacks') ORDER BY item_category, item_name");
if ($menu_result) {
    $available_menu_items = mysqli_fetch_all($menu_result, MYSQLI_ASSOC);
}

$booking_rooms = [];
$booking_rooms_result = mysqli_query($conn, "SELECT * FROM rooms WHERE status = 'Available' ORDER BY room_name ASC");
if ($booking_rooms_result) {
    $booking_rooms = mysqli_fetch_all($booking_rooms_result, MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $customer_name = sanitize_input($_POST['customer_name']);
    $phone = sanitize_input($_POST['phone']);
    $room_id = intval($_POST['room_id']);
    $preselected_room_id = $room_id;
    $booking_date = sanitize_input($_POST['booking_date']);
    $start_time = sanitize_input($_POST['start_time']);
    $hours = intval($_POST['hours']);
    $notes = sanitize_input($_POST['notes']);
    $selected_payment_method = sanitize_input($_POST['payment_method'] ?? 'Cash');
    $selected_menu_quantities = isset($_POST['menu_items']) && is_array($_POST['menu_items']) ? $_POST['menu_items'] : [];
    $selected_menu_items = [];
    $addons_total = 0.0;

    // Validate inputs
    $validation_errors = [];

    if (empty($customer_name)) {
        $validation_errors[] = t('booking_validation_name');
    }

    if (!validate_phone($phone)) {
        $validation_errors[] = t('booking_validation_phone');
    }

    if (!validate_booking_date($booking_date)) {
        $validation_errors[] = t('booking_validation_date');
    }

    if (!validate_time($start_time)) {
        $validation_errors[] = t('booking_validation_time');
    }

    if (!validate_hour_interval($start_time)) {
        $validation_errors[] = t('booking_validation_hour_interval');
    }

    $min_hours = (int)get_setting($conn, 'min_booking_hours', 1);
    $max_hours = (int)get_setting($conn, 'max_booking_hours', 12);

    if ($hours < $min_hours || $hours > $max_hours) {
        $validation_errors[] = t('booking_validation_hours_range', ['min' => $min_hours, 'max' => $max_hours]);
    }

    if (!in_array($selected_payment_method, ['Cash', 'Visa', 'CliQ'], true)) {
        $validation_errors[] = t('payment_method_invalid');
    }

    if (count($validation_errors) > 0) {
        $error_msg = implode('<br>', $validation_errors);
    } else {
        // Get room details using prepared statement
        $stmt = mysqli_prepare($conn, "SELECT id, room_name, price_per_hour, status FROM rooms WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $room_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $room = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$room) {
            $error_msg = t('booking_room_not_found');
        } elseif ($room['status'] !== 'Available') {
            $error_msg = t('booking_room_unavailable');
        } else {
            // Check if within business hours
            if (!is_within_business_hours($conn, $booking_date, $start_time, $hours)) {
                $error_msg = t('booking_business_hours_error');
            } else {
                // Check for booking conflicts
                $availability = check_room_availability($conn, $room_id, $booking_date, $start_time, $hours);

                if (!$availability['available']) {
                    $conflicts = $availability['conflicts'];
                    $conflict_times = [];

                    foreach ($conflicts as $conflict) {
                        $conflict_start = format_time($conflict['start_time']);
                        $conflict_end = date('g:i A', strtotime($conflict['start_time']) + ($conflict['hours'] * 3600));
                        $conflict_times[] = "$conflict_start - $conflict_end";
                    }

                    $error_msg = t('booking_conflict_intro') . '<br>';
                    $error_msg .= implode('<br>', $conflict_times);
                    $error_msg .= '<br>' . t('booking_conflict_outro');
                } else {
                    $menu_ids = [];
                    foreach ($selected_menu_quantities as $menu_item_id => $quantity) {
                        $menu_item_id = (int)$menu_item_id;
                        $quantity = max(0, min(20, (int)$quantity));

                        if ($menu_item_id > 0 && $quantity > 0) {
                            $menu_ids[$menu_item_id] = $quantity;
                        }
                    }

                    if (!empty($menu_ids)) {
                        $item_id_list = implode(',', array_map('intval', array_keys($menu_ids)));
                        $item_result = mysqli_query($conn, "SELECT id, item_name, item_price FROM menu_items WHERE is_available = 1 AND item_category IN ('Drinks', 'Snacks') AND id IN ($item_id_list)");

                        while ($item_result && ($item = mysqli_fetch_assoc($item_result))) {
                            $quantity = $menu_ids[(int)$item['id']];
                            $line_total = (float)$item['item_price'] * $quantity;
                            $addons_total += $line_total;
                            $selected_menu_items[] = [
                                'id' => (int)$item['id'],
                                'quantity' => $quantity,
                                'price' => (float)$item['item_price']
                            ];
                        }
                    }

                    // Calculate total price
                    $total_price = $room['price_per_hour'] * $hours;
                    $final_total = $total_price + $addons_total;
                    $booking_code = generate_booking_code();
                    $customer_session_token = get_customer_session_token();
                    $site_user_id = $current_site_user ? (int)$current_site_user['id'] : null;
                    $_SESSION['customer_booking_token'] = $customer_session_token;
                    $_SESSION['customer_name'] = $customer_name;
                    $_SESSION['customer_phone'] = $phone;

                    // Insert booking using prepared statement
                    $stmt = mysqli_prepare($conn, "INSERT INTO bookings (booking_code, customer_name, phone, customer_session_token, user_id, room_id, booking_date, start_time, hours, total_price, additional_items_total, status, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed', ?, ?)");

                    mysqli_stmt_bind_param($stmt, "ssssiissiddss", $booking_code, $customer_name, $phone, $customer_session_token, $site_user_id, $room_id, $booking_date, $start_time, $hours, $total_price, $addons_total, $selected_payment_method, $notes);

                    if (mysqli_stmt_execute($stmt)) {
                        $booking_id = mysqli_insert_id($conn);

                        if (!empty($selected_menu_items)) {
                            $booking_item_stmt = mysqli_prepare($conn, "INSERT INTO booking_items (booking_id, menu_item_id, quantity, item_price) VALUES (?, ?, ?, ?)");

                            foreach ($selected_menu_items as $selected_item) {
                                mysqli_stmt_bind_param($booking_item_stmt, "iiid", $booking_id, $selected_item['id'], $selected_item['quantity'], $selected_item['price']);
                                mysqli_stmt_execute($booking_item_stmt);
                            }

                            mysqli_stmt_close($booking_item_stmt);
                        }

                        if ($current_site_user) {
                            $loyalty_points_earned = award_loyalty_points($conn, $site_user_id, $booking_id, $final_total);
                        }

                        create_admin_notification(
                            $conn,
                            'booking_created',
                            'New booking created',
                            $customer_name . ' booked ' . $room['room_name'] . ' on ' . $booking_date . ' at ' . $start_time . '.',
                            'bookings',
                            $booking_id,
                            'booking_details.php?id=' . $booking_id
                        );
                        create_admin_notification(
                            $conn,
                            'payment_pending',
                            'Payment still pending',
                            'Booking #' . $booking_id . ' is confirmed and waiting for payment.',
                            'bookings',
                            $booking_id,
                            'booking_details.php?id=' . $booking_id
                        );
                        create_site_notification(
                            $conn,
                            $site_user_id,
                            'booking_created',
                            t('booking_ticket_ready'),
                            t('booking_success') . ' ' . $room['room_name'] . ' - ' . format_date($booking_date) . ' ' . format_time($start_time),
                            'user/my_bookings.php'
                        );
                        $success_msg = t('booking_success');
                        if ($loyalty_points_earned > 0) {
                            $success_msg .= ' ' . t('loyalty_earned', ['points' => $loyalty_points_earned]);
                        }
                        $confirmed_booking = get_customer_booking_by_id($conn, $booking_id);

                        // Clear form by redirecting
                        $_POST = [];
                    } else {
                        $error_msg = t('booking_submit_error');
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

$show_booking_flow = $booking_always_show_flow || ($_SERVER['REQUEST_METHOD'] === 'POST') || $preselected_room_id > 0;
$page_title = t('booking_page_title');
include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero booking-hero">
    <div class="container">
        <h1><?php echo t('booking_hero_title'); ?></h1>
        <p><?php echo t('booking_hero_text'); ?></p>
    </div>
</section>

<section class="content">
    <div class="container">
        <?php if ($confirmed_booking): ?>
            <div class="booking-ticket-modal" role="dialog" aria-modal="true" aria-labelledby="booking-ticket-title">
                <div class="booking-ticket-modal-backdrop" data-close-ticket-modal></div>
                <div class="booking-ticket-modal-panel">
                    <button type="button" class="booking-ticket-close" data-close-ticket-modal aria-label="<?php echo htmlspecialchars(t('booking_close_ticket'), ENT_QUOTES, 'UTF-8'); ?>">X</button>
                    <div class="message success booking-ticket-modal-message">
                        <?php echo $success_msg; ?>
                    </div>
                    <div class="booking-ticket"
                         data-ticket-code="<?php echo htmlspecialchars($confirmed_booking['booking_code'] ?: ('FG-' . str_pad($confirmed_booking['id'], 6, '0', STR_PAD_LEFT))); ?>"
                         data-ticket-customer="<?php echo htmlspecialchars($confirmed_booking['customer_name']); ?>"
                         data-ticket-device="<?php echo htmlspecialchars($confirmed_booking['room_name'] . ' - ' . $confirmed_booking['room_type']); ?>"
                         data-ticket-date="<?php echo htmlspecialchars(format_date($confirmed_booking['booking_date'])); ?>"
                         data-ticket-time="<?php echo htmlspecialchars(format_time($confirmed_booking['start_time']) . ' - ' . translated_hours_label($confirmed_booking['hours'])); ?>"
                         data-ticket-status="<?php echo htmlspecialchars($confirmed_booking['status']); ?>">
                        <div class="booking-ticket-header">
                            <div>
                                <span class="ticket-label"><?php echo t('booking_ticket_label'); ?></span>
                                <h2 id="booking-ticket-title"><?php echo t('booking_ticket_ready'); ?></h2>
                                <p><?php echo t('booking_ticket_arrival'); ?></p>
                            </div>
                            <span class="ticket-status"><?php echo htmlspecialchars(t('status_' . strtolower($confirmed_booking['status']), [], $confirmed_booking['status'])); ?></span>
                        </div>

                        <div class="booking-ticket-code">
                            <span><?php echo t('booking_barcode'); ?></span>
                            <div class="ticket-barcode" aria-label="<?php echo htmlspecialchars(t('booking_barcode'), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo render_booking_barcode($confirmed_booking['booking_code'] ?: $confirmed_booking['id']); ?>
                            </div>
                        </div>

                        <div class="booking-ticket-grid">
                            <div>
                                <span><?php echo t('common_customer'); ?></span>
                                <strong><?php echo htmlspecialchars($confirmed_booking['customer_name']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('booking_device_session'); ?></span>
                                <strong><?php echo htmlspecialchars($confirmed_booking['room_name']); ?> - <?php echo htmlspecialchars($confirmed_booking['room_type']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('common_date'); ?></span>
                                <strong><?php echo format_date($confirmed_booking['booking_date']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('common_time'); ?></span>
                                <strong><?php echo format_time($confirmed_booking['start_time']); ?> - <?php echo translated_hours_label($confirmed_booking['hours']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('common_total'); ?></span>
                                <strong><?php echo number_format((float)($confirmed_booking['final_total'] ?? $confirmed_booking['total_price']), 2); ?> JOD</strong>
                            </div>
                            <div>
                                <span><?php echo t('loyalty_points'); ?></span>
                                <strong><?php echo (int)($confirmed_booking['loyalty_points_earned'] ?? 0); ?></strong>
                            </div>
                        </div>

                        <div class="booking-ticket-actions">
                            <button type="button" class="btn download-ticket-btn"><?php echo t('booking_save_ticket'); ?></button>
                            <a href="<?php echo htmlspecialchars(site_url('user/payment.php?booking_id=' . (int)$confirmed_booking['id'] . '&method=' . urlencode($confirmed_booking['payment_method'] ?: 'Cash')), ENT_QUOTES, 'UTF-8'); ?>" class="btn payment-checkout-btn"><?php echo t('my_bookings_simulate_payment'); ?></a>
                            <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('booking_view_bookings'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($success_msg): ?>
            <div class="message success">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="message error">
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($booking_show_room_showcase && !empty($booking_rooms)): ?>
            <div class="booking-room-showcase">
                <div class="home-section-heading">
                    <span class="ticket-label"><?php echo t('home_rooms_title'); ?></span>
                    <h2><?php echo t('booking_form_room'); ?></h2>
                </div>
                <div class="booking-room-compact-list">
                    <?php foreach ($booking_rooms as $room): ?>
                        <?php
                        $room_image = site_asset_url($room['image_path'] ?? '', 'images/home-hero-background-optimized.jpg');
                        ?>
                        <article class="live-room-row-item booking-room-row">
                            <div class="booking-room-row-media">
                                <img src="<?php echo htmlspecialchars($room_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($room['room_name']); ?>">
                            </div>
                            <div class="booking-room-row-copy">
                                <span><?php echo htmlspecialchars($room['room_type']); ?></span>
                                <h3><?php echo htmlspecialchars($room['room_name']); ?></h3>
                                <p><?php echo htmlspecialchars($room['description'] ?: $room['services']); ?></p>
                            </div>
                            <div class="booking-room-row-actions">
                                <strong><?php echo number_format((float)$room['price_per_hour'], 2); ?> <?php echo t('home_room_price_suffix'); ?></strong>
                                <button type="button" class="btn btn-small" data-booking-room-select="<?php echo (int)$room['id']; ?>"><?php echo t('home_book_room'); ?></button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-container booking-flow-panel <?php echo $show_booking_flow ? 'is-active' : ''; ?>" id="booking-form" data-booking-flow>
            <form method="POST" action="<?php echo htmlspecialchars(site_url($booking_page_path), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="booking-flow-head">
                    <div>
                        <span class="ticket-label"><?php echo t('booking_flow_label'); ?></span>
                        <h2><?php echo t('booking_flow_title'); ?></h2>
                        <p><?php echo t('booking_flow_text'); ?></p>
                    </div>
                    <ul class="booking-flow-instructions" aria-label="<?php echo htmlspecialchars(t('booking_info_title'), ENT_QUOTES, 'UTF-8'); ?>">
                        <li><?php echo t('booking_info_1'); ?></li>
                        <li><?php echo t('booking_info_2'); ?></li>
                        <li><?php echo t('booking_info_3'); ?></li>
                    </ul>
                </div>
                <div class="booking-flow-steps" aria-label="<?php echo htmlspecialchars(t('booking_flow_label'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="is-active" data-booking-step-indicator="details"><?php echo t('booking_step_details'); ?></span>
                    <span data-booking-step-indicator="addons"><?php echo t('booking_step_menu'); ?></span>
                    <span data-booking-step-indicator="payment"><?php echo t('booking_step_payment'); ?></span>
                </div>
                <div class="booking-flow-step is-active" data-booking-step-panel="details">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_name'); ?></label>
                            <input type="text" name="customer_name" class="form-control" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ($current_site_user['full_name'] ?? '')); ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_phone'); ?></label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ($current_site_user['phone'] ?? '')); ?>" required placeholder="07XXXXXXXX">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_room'); ?></label>
                            <select name="room_id" id="room_id" class="form-select" required>
                                <option value=""><?php echo t('booking_form_choose_room'); ?></option>
                                <?php
                                $rooms_query = "SELECT * FROM rooms WHERE status = 'Available' ORDER BY room_name ASC";
                                $rooms_result = mysqli_query($conn, $rooms_query);
                                while ($room = mysqli_fetch_assoc($rooms_result)) {
                                    $selected = ($preselected_room_id === (int)$room['id']) ? ' selected' : '';
                                    echo '<option value="' . $room['id'] . '" data-price="' . htmlspecialchars($room['price_per_hour']) . '"' . $selected . '>';
                                    echo htmlspecialchars($room['room_name']) . ' - ' . htmlspecialchars($room['room_type']);
                                    echo ' (' . number_format($room['price_per_hour'], 2) . ' ' . t('home_room_price_suffix') . ')';
                                    echo '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_date'); ?></label>
                            <input type="date" name="booking_date" id="booking_date" class="form-control" value="<?php echo htmlspecialchars($_POST['booking_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_hours'); ?></label>
                            <input type="number" name="hours" id="hours" class="form-control" required min="1" max="12" value="<?php echo htmlspecialchars($_POST['hours'] ?? '2'); ?>">
                        </div>
                    </div>
                </div>

                <div id="slot_availability">
                    <div class="slot-availability-container">
                        <strong class="slot-availability-title"><?php echo t('booking_slots_title'); ?></strong>
                        <div id="slot_status">
                            <?php echo t('booking_slots_loading'); ?>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label"><?php echo t('booking_form_start_time'); ?></label>
                    <select name="start_time" class="form-select" required id="start_time_select">
                        <option value=""><?php echo t('booking_form_choose_time'); ?></option>
                        <?php
                        // Fetch global time slots (room_id IS NULL) for initial display
                        $time_query = "SELECT slot_time, slot_label FROM time_slots WHERE is_active = 1 AND room_id IS NULL ORDER BY slot_time";
                        $time_result = mysqli_query($conn, $time_query);
                        if ($time_result && mysqli_num_rows($time_result) > 0) {
                            while ($slot = mysqli_fetch_assoc($time_result)) {
                                echo '<option value="' . htmlspecialchars($slot['slot_time']) . '">';
                                echo htmlspecialchars($slot['slot_label']);
                                echo '</option>';
                            }
                        } else {
                            // Fallback to default slots if none in database
                            for ($hour = 9; $hour <= 23; $hour++) {
                                $time_24 = sprintf('%02d:00:00', $hour);
                                $time_12 = date('g:i A', strtotime($time_24));
                                echo '<option value="' . $time_24 . '">' . $time_12 . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <small class="booking-time-hint form-text">
                        <?php echo t('booking_form_time_hint'); ?>
                    </small>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label"><?php echo t('booking_form_notes'); ?></label>
                    <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div class="booking-step-actions">
                    <button type="button" class="btn" data-booking-go="addons"><?php echo t('booking_choose_addons'); ?></button>
                    <button type="button" class="btn payment-secondary-btn" data-booking-go="payment"><?php echo t('booking_skip_addons'); ?></button>
                </div>
                </div>

                <div class="booking-addons-panel booking-flow-step" data-booking-step-panel="addons" hidden>
                    <div class="booking-addons-head">
                        <div>
                            <h3><?php echo t('booking_addons_title'); ?></h3>
                            <p><?php echo t('booking_addons_text'); ?></p>
                        </div>
                    </div>
                    <p class="booking-addons-note"><?php echo t('booking_addons_note'); ?></p>

                    <?php if (!empty($available_menu_items)): ?>
                        <div class="booking-addons-grid">
                            <?php foreach ($available_menu_items as $menu_item): ?>
                                <?php
                                $item_id = (int)$menu_item['id'];
                                $posted_quantity = isset($_POST['menu_items'][$item_id]) ? max(0, min(20, (int)$_POST['menu_items'][$item_id])) : 0;
                                ?>
                                <label class="booking-addon-card">
                                    <span class="booking-addon-category"><?php echo htmlspecialchars(translated_menu_category_label($menu_item['item_category'])); ?></span>
                                    <strong><?php echo htmlspecialchars($menu_item['item_name']); ?></strong>
                                    <?php if (!empty($menu_item['item_description'])): ?>
                                        <small><?php echo htmlspecialchars($menu_item['item_description']); ?></small>
                                    <?php endif; ?>
                                    <span class="booking-addon-price"><?php echo number_format((float)$menu_item['item_price'], 2); ?> JOD</span>
                                    <span class="booking-addon-qty">
                                        <?php echo t('booking_addons_quantity'); ?>
                                        <input
                                            type="number"
                                            name="menu_items[<?php echo $item_id; ?>]"
                                            class="form-control booking-addon-input"
                                            min="0"
                                            max="20"
                                            value="<?php echo $posted_quantity; ?>"
                                            data-menu-item
                                            data-price="<?php echo htmlspecialchars($menu_item['item_price']); ?>"
                                        >
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="booking-addons-empty"><?php echo t('booking_addons_empty'); ?></p>
                    <?php endif; ?>

                    <div class="booking-total-estimate">
                        <div>
                            <span><?php echo t('booking_room_subtotal'); ?></span>
                            <strong id="booking-room-subtotal">0.00 JOD</strong>
                        </div>
                        <div>
                            <span><?php echo t('booking_addons_subtotal'); ?></span>
                            <strong id="booking-addons-subtotal">0.00 JOD</strong>
                        </div>
                        <div class="booking-total-estimate-final">
                            <span><?php echo t('booking_total_estimate'); ?></span>
                            <strong id="booking-total-estimate">0.00 JOD</strong>
                        </div>
                    </div>

                    <div class="booking-step-actions">
                        <button type="button" class="btn payment-secondary-btn" data-booking-go="details"><?php echo t('booking_back_to_details'); ?></button>
                        <button type="button" class="btn" data-booking-go="payment"><?php echo t('booking_continue_payment'); ?></button>
                    </div>
                </div>

                <div class="booking-addons-panel booking-payment-panel booking-flow-step" data-booking-step-panel="payment" hidden>
                    <div class="booking-addons-head">
                        <div>
                            <h3><?php echo t('payment_method_title'); ?></h3>
                            <p><?php echo t('payment_choose_how'); ?></p>
                        </div>
                    </div>
                    <div class="user-loyalty-rules booking-loyalty-rules">
                        <b><?php echo t('loyalty_calculation_title'); ?></b>
                        <span><?php echo t('loyalty_calculation_earn', ['points' => $loyalty_earn_display]); ?></span>
                        <span><?php echo t('loyalty_calculation_redeem', ['points' => $loyalty_redeem_display]); ?></span>
                    </div>
                    <div class="payment-method-grid">
                        <?php $booking_selected_method = $_POST['payment_method'] ?? 'Cash'; ?>
                        <?php foreach (['Cash', 'Visa', 'CliQ'] as $method): ?>
                            <label class="payment-method-option">
                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="<?php echo $method; ?>"
                                    class="payment-method-input"
                                    <?php echo $booking_selected_method === $method ? 'checked' : ''; ?>
                                >
                                <span class="payment-method-card">
                                    <strong><?php echo t('payment_' . strtolower($method), [], $method); ?></strong>
                                    <small>
                                        <?php
                                        if ($method === 'Cash') {
                                            echo t('payment_cash_text');
                                        } elseif ($method === 'Visa') {
                                            echo t('payment_visa_text');
                                        } else {
                                            echo t('payment_cliq_text');
                                        }
                                        ?>
                                    </small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="booking-step-actions booking-submit-actions">
                        <button type="button" class="btn payment-secondary-btn" data-booking-go="addons"><?php echo t('booking_back_to_menu'); ?></button>
                        <button type="submit" class="btn booking-submit-btn">
                            <?php echo t('booking_submit'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="booking-info-container">
            <h3 class="booking-info-title"><?php echo t('booking_info_title'); ?></h3>
            <ul class="booking-info-list">
                <li><?php echo t('booking_info_1'); ?></li>
                <li><?php echo t('booking_info_2'); ?></li>
                <li><?php echo t('booking_info_3'); ?></li>
                <li><?php echo t('booking_info_4'); ?></li>
                <li><?php echo t('booking_info_5'); ?></li>
            </ul>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookingTexts = <?php echo json_encode([
        'loading' => t('booking_slots_loading'),
        'error' => t('booking_slots_error'),
        'errorGeneric' => t('booking_slots_error_generic'),
        'noneConfigured' => t('booking_slots_none_configured'),
        'available' => t('booking_slots_available'),
        'unavailable' => t('booking_slots_unavailable'),
        'noneAvailableTitle' => t('booking_slots_none_available_title'),
        'noneAvailableText' => t('booking_slots_none_available_text'),
        'chooseTime' => t('booking_form_choose_time'),
        'booked' => t('booking_slot_booked')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const roomSelect = document.getElementById('room_id');
    const dateInput = document.getElementById('booking_date');
    const hoursInput = document.getElementById('hours');
    const timeSelect = document.getElementById('start_time_select');
    const slotAvailability = document.getElementById('slot_availability');
    const slotStatus = document.getElementById('slot_status');
    const bookingForm = document.getElementById('booking-form');
    const bookingStepPanels = Array.from(document.querySelectorAll('[data-booking-step-panel]'));
    const bookingStepIndicators = Array.from(document.querySelectorAll('[data-booking-step-indicator]'));
    const bookingGoButtons = Array.from(document.querySelectorAll('[data-booking-go]'));
    const menuInputs = Array.from(document.querySelectorAll('[data-menu-item]'));
    const roomSubtotalEl = document.getElementById('booking-room-subtotal');
    const addonsSubtotalEl = document.getElementById('booking-addons-subtotal');
    const totalEstimateEl = document.getElementById('booking-total-estimate');
    const params = new URLSearchParams(window.location.search);
    const requestedRoomId = params.get('room_id');
    let currentSlots = [];
    let currentBookingStep = 'details';

    function formatJod(amount) {
        return amount.toFixed(2) + ' JOD';
    }

    function updateBookingEstimate() {
        const selectedRoom = roomSelect.options[roomSelect.selectedIndex];
        const roomPrice = selectedRoom && selectedRoom.dataset.price ? parseFloat(selectedRoom.dataset.price) : 0;
        const hours = Math.max(0, parseInt(hoursInput.value || '0', 10));
        const roomSubtotal = roomPrice * hours;
        let addonsSubtotal = 0;

        menuInputs.forEach(function(input) {
            const qty = Math.max(0, parseInt(input.value || '0', 10));
            const price = parseFloat(input.dataset.price || '0');
            addonsSubtotal += qty * price;
        });

        if (roomSubtotalEl) {
            roomSubtotalEl.textContent = formatJod(roomSubtotal);
        }

        if (addonsSubtotalEl) {
            addonsSubtotalEl.textContent = formatJod(addonsSubtotal);
        }

        if (totalEstimateEl) {
            totalEstimateEl.textContent = formatJod(roomSubtotal + addonsSubtotal);
        }
    }

    function showBookingFlow() {
        if (bookingForm) {
            bookingForm.classList.add('is-active');
        }
    }

    function validateDetailsStep() {
        const detailsPanel = document.querySelector('[data-booking-step-panel="details"]');

        if (!detailsPanel) {
            return true;
        }

        const fields = Array.from(detailsPanel.querySelectorAll('input, select, textarea'));
        const invalidField = fields.find(function(field) {
            return typeof field.checkValidity === 'function' && !field.checkValidity();
        });

        if (invalidField) {
            invalidField.reportValidity();
            return false;
        }

        return true;
    }

    function setBookingStep(step) {
        if (!['details', 'addons', 'payment'].includes(step)) {
            step = 'details';
        }

        bookingStepPanels.forEach(function(panel) {
            const isActive = panel.dataset.bookingStepPanel === step;
            panel.hidden = !isActive;
            panel.classList.toggle('is-active', isActive);
        });

        bookingStepIndicators.forEach(function(indicator) {
            indicator.classList.toggle('is-active', indicator.dataset.bookingStepIndicator === step);
        });

        currentBookingStep = step;
        updateBookingEstimate();

        if (bookingForm) {
            bookingForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function fetchAvailableSlots() {
        const roomId = roomSelect.value;
        const bookingDate = dateInput.value;
        const hours = hoursInput.value;

        // Only fetch if all required fields are filled
        if (!roomId || !bookingDate || !hours) {
            slotAvailability.style.display = 'none';
            return;
        }

        // Show loading state
        slotAvailability.style.display = 'block';
        slotStatus.innerHTML = '<span class="slot-status-loading">' + bookingTexts.loading + '</span>';

        // Make AJAX request
        fetch(`<?php echo site_url('user/get_available_slots.php'); ?>?room_id=${roomId}&booking_date=${bookingDate}&hours=${hours}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    slotStatus.innerHTML = '<span class="slot-status-error">' + bookingTexts.error.replace(':message', data.error) + '</span>';
                    return;
                }

                currentSlots = data.slots;
                updateSlotDisplay(data.slots);
                updateTimeSelectOptions(data.slots);
            })
            .catch(error => {
                console.error('Error fetching slots:', error);
                slotStatus.innerHTML = '<span class="slot-status-error">' + bookingTexts.errorGeneric + '</span>';
            });
    }

    // Function to update slot display
    function updateSlotDisplay(slots) {
        if (!slots || slots.length === 0) {
            slotStatus.innerHTML = '<span class="slot-status-warning">' + bookingTexts.noneConfigured + '</span>';
            return;
        }

        const availableSlots = slots.filter(slot => slot.available);
        const unavailableSlots = slots.filter(slot => !slot.available);

        let html = '<div class="slot-display-container">';

        if (availableSlots.length > 0) {
            html += '<div class="slot-section">';
            html += '<strong class="slot-section-title available">' + bookingTexts.available + '</strong><br>';
            html += '<div class="slot-badges-container">';
            availableSlots.forEach(slot => {
                html += `<span class="slot-badge available">${slot.label}</span>`;
            });
            html += '</div></div>';
        }

        if (unavailableSlots.length > 0) {
            html += '<div class="slot-section">';
            html += '<strong class="slot-section-title unavailable">' + bookingTexts.unavailable + '</strong><br>';
            html += '<div class="slot-badges-container">';
            unavailableSlots.forEach(slot => {
                html += `<span class="slot-badge unavailable">${slot.label}</span>`;
            });
            html += '</div></div>';
        }

        html += '</div>';

        if (availableSlots.length === 0) {
            html += '<div class="slot-no-available">';
            html += '<strong>' + bookingTexts.noneAvailableTitle + '</strong>';
            html += '<small>' + bookingTexts.noneAvailableText + '</small>';
            html += '</div>';
        }

        slotStatus.innerHTML = html;
    }

    // Function to update time select dropdown options
    function updateTimeSelectOptions(slots) {
        // Clear existing options except the first one
        timeSelect.innerHTML = '<option value="">' + bookingTexts.chooseTime + '</option>';

        if (!slots || slots.length === 0) {
            return;
        }

        slots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.time;
            option.textContent = slot.label;

            if (!slot.available) {
                option.disabled = true;
                option.textContent += ' (' + bookingTexts.booked + ')';
                option.style.color = '#999';
                option.style.textDecoration = 'line-through';
            }

            timeSelect.appendChild(option);
        });
    }

    // Attach event listeners
    roomSelect.addEventListener('change', fetchAvailableSlots);
    roomSelect.addEventListener('change', updateBookingEstimate);
    dateInput.addEventListener('change', fetchAvailableSlots);
    hoursInput.addEventListener('change', fetchAvailableSlots);
    hoursInput.addEventListener('input', fetchAvailableSlots);
    hoursInput.addEventListener('input', updateBookingEstimate);
    hoursInput.addEventListener('change', updateBookingEstimate);

    menuInputs.forEach(function(input) {
        input.addEventListener('input', updateBookingEstimate);
        input.addEventListener('change', updateBookingEstimate);
    });

    bookingGoButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const targetStep = button.dataset.bookingGo || 'details';

            if (currentBookingStep === 'details' && targetStep !== 'details' && !validateDetailsStep()) {
                return;
            }

            showBookingFlow();
            setBookingStep(targetStep);
        });
    });

    if (window.location.hash === '#booking-form' && bookingForm) {
        showBookingFlow();
        setBookingStep('details');
    }

    if (requestedRoomId && roomSelect.querySelector(`option[value="${requestedRoomId}"]`)) {
        roomSelect.value = requestedRoomId;
        showBookingFlow();

        if (window.location.hash === '#booking-form' && bookingForm) {
            bookingForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    document.querySelectorAll('[data-booking-room-select]').forEach(function(button) {
        button.addEventListener('click', function() {
            const roomId = button.dataset.bookingRoomSelect;

            if (roomSelect && roomSelect.querySelector(`option[value="${roomId}"]`)) {
                roomSelect.value = roomId;
                roomSelect.dispatchEvent(new Event('change', { bubbles: true }));
                showBookingFlow();
                setBookingStep('details');
                if (bookingForm) {
                    bookingForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });

    updateBookingEstimate();
});
</script>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
