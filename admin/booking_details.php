<?php
require_once 'auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

ensure_user_auth_schema($conn);

$success_message = '';
$error_message = '';
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id === 0) {
    header('Location: bookings_full_crud.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    admin_require_csrf();

    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add_item') {
            $menu_item_id = intval($_POST['menu_item_id']);
            $quantity = intval($_POST['quantity']);

            // Get item price
            $item_query = mysqli_prepare($conn, "SELECT item_price FROM menu_items WHERE id = ? AND is_available = 1");
            mysqli_stmt_bind_param($item_query, "i", $menu_item_id);
            mysqli_stmt_execute($item_query);
            $item_result = mysqli_stmt_get_result($item_query);

            if ($item_row = mysqli_fetch_assoc($item_result)) {
                $item_price = $item_row['item_price'];

                // Insert booking item
                $stmt = mysqli_prepare($conn, "INSERT INTO booking_items (booking_id, menu_item_id, quantity, item_price) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iiid", $booking_id, $menu_item_id, $quantity, $item_price);

                if (mysqli_stmt_execute($stmt)) {
                    // Update additional_items_total
                    $update_stmt = mysqli_prepare($conn, "UPDATE bookings SET additional_items_total = (SELECT IFNULL(SUM(item_total), 0) FROM booking_items WHERE booking_id = ?) WHERE id = ?");
                    mysqli_stmt_bind_param($update_stmt, "ii", $booking_id, $booking_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);

                    $success_message = t('admin_item_added_success');
                    log_admin_action($conn, $_SESSION['admin_id'], 'ADD_ITEM', 'booking_items', mysqli_insert_id($conn));
                } else {
                    $error_message = t('admin_item_add_error');
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($item_query);

        } elseif ($_POST['action'] == 'remove_item') {
            $item_id = intval($_POST['item_id']);

            $stmt = mysqli_prepare($conn, "DELETE FROM booking_items WHERE id = ? AND booking_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $item_id, $booking_id);

            if (mysqli_stmt_execute($stmt)) {
                // Update additional_items_total
                $update_stmt = mysqli_prepare($conn, "UPDATE bookings SET additional_items_total = (SELECT IFNULL(SUM(item_total), 0) FROM booking_items WHERE booking_id = ?) WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "ii", $booking_id, $booking_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);

                $success_message = t('admin_item_removed_success');
                log_admin_action($conn, $_SESSION['admin_id'], 'REMOVE_ITEM', 'booking_items', $item_id);
            } else {
                $error_message = t('admin_item_remove_error');
            }
            mysqli_stmt_close($stmt);

        } elseif ($_POST['action'] == 'extend_hours') {
            $extra_hours = intval($_POST['extra_hours']);

            // Get room price per hour
            $room_query = mysqli_prepare($conn, "SELECT r.price_per_hour FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ?");
            mysqli_stmt_bind_param($room_query, "i", $booking_id);
            mysqli_stmt_execute($room_query);
            $room_result = mysqli_stmt_get_result($room_query);

            if ($room_row = mysqli_fetch_assoc($room_result)) {
                $price_per_hour = $room_row['price_per_hour'];
                $additional_cost = $extra_hours * $price_per_hour;

                // Update booking hours and total_price
                $stmt = mysqli_prepare($conn, "UPDATE bookings SET hours = hours + ?, total_price = total_price + ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "idi", $extra_hours, $additional_cost, $booking_id);

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = t('admin_extend_success', [
                        'hours' => translated_hours_label($extra_hours),
                        'amount' => number_format($additional_cost, 2)
                    ]);
                    log_admin_action($conn, $_SESSION['admin_id'], 'EXTEND_HOURS', 'bookings', $booking_id);
                } else {
                    $error_message = t('admin_extend_error');
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($room_query);

        } elseif ($_POST['action'] == 'update_payment') {
            $payment_status = sanitize_input($_POST['payment_status']);
            $payment_method = sanitize_input($_POST['payment_method']);
            $paid_amount = floatval($_POST['paid_amount']);

            $stmt = mysqli_prepare($conn, "UPDATE bookings SET payment_status = ?, payment_method = ?, paid_amount = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssdi", $payment_status, $payment_method, $paid_amount, $booking_id);

            if (mysqli_stmt_execute($stmt)) {
                $booking_user_id = 0;
                $user_stmt = mysqli_prepare($conn, "SELECT user_id FROM bookings WHERE id = ? LIMIT 1");
                mysqli_stmt_bind_param($user_stmt, "i", $booking_id);
                mysqli_stmt_execute($user_stmt);
                $user_result = mysqli_stmt_get_result($user_stmt);
                if ($user_row = mysqli_fetch_assoc($user_result)) {
                    $booking_user_id = (int)($user_row['user_id'] ?? 0);
                }
                mysqli_stmt_close($user_stmt);

                $notification_title = $payment_status === 'Paid' ? 'Payment updated' : 'Payment still pending';
                $notification_message = $payment_status === 'Paid'
                    ? 'Booking #' . $booking_id . ' was marked paid via ' . $payment_method . '.'
                    : 'Booking #' . $booking_id . ' payment status is ' . $payment_status . '.';
                create_admin_notification(
                    $conn,
                    'payment_updated',
                    $notification_title,
                    $notification_message,
                    'bookings',
                    $booking_id,
                    'booking_details.php?id=' . $booking_id
                );
                if ($booking_user_id > 0) {
                    create_site_notification(
                        $conn,
                        $booking_user_id,
                        'payment_updated',
                        t('payment_success'),
                        t('payment_already_paid_text', ['method' => t('payment_' . strtolower($payment_method), [], $payment_method)]),
                        'my_bookings.php'
                    );
                }
                $success_message = t('admin_payment_update_success');
                log_admin_action($conn, $_SESSION['admin_id'], 'UPDATE_PAYMENT', 'bookings', $booking_id);
            } else {
                $error_message = t('admin_payment_update_error');
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get booking details
$booking_query = "SELECT b.*, r.room_name, r.room_type, r.price_per_hour
                  FROM bookings b
                  JOIN rooms r ON b.room_id = r.id
                  WHERE b.id = ?";
$stmt = mysqli_prepare($conn, $booking_query);
mysqli_stmt_bind_param($stmt, "i", $booking_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$booking) {
    header('Location: bookings_full_crud.php');
    exit;
}

$booking_final_total = isset($booking['final_total'])
    ? (float)$booking['final_total']
    : ((float)$booking['total_price'] + (float)($booking['additional_items_total'] ?? 0));

// Get booking items
$items_query = "SELECT bi.*, mi.item_name, mi.item_category
                FROM booking_items bi
                JOIN menu_items mi ON bi.menu_item_id = mi.id
                WHERE bi.booking_id = ?
                ORDER BY mi.item_category, mi.item_name";
$stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt, "i", $booking_id);
mysqli_stmt_execute($stmt);
$booking_items = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Get available menu items
$menu_query = "SELECT * FROM menu_items WHERE is_available = 1 ORDER BY item_category, item_name";
$menu_items = mysqli_query($conn, $menu_query);

$page_title = t('admin_booking_details', ['id' => $booking['id']]);
$active_page = 'bookings';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1><?php echo t('admin_booking_details', ['id' => $booking['id']]); ?></h1>
                <a href="bookings_full_crud.php" class="btn btn-secondary"><?php echo t('admin_action_back_to_bookings'); ?></a>
            </div>

            <?php if ($success_message): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="grid-2">
                <!-- Booking Information -->
                <div class="card">
                    <h2><?php echo t('admin_booking_information'); ?></h2>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_field_customer_name'); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_field_phone'); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['phone']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_field_room'); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['room_name']); ?> (<?php echo htmlspecialchars($booking['room_type']); ?>)</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_field_date'); ?>:</span>
                        <span class="info-value"><?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_start_time'); ?>:</span>
                        <span class="info-value"><?php echo date('h:i A', strtotime($booking['start_time'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_end_time'); ?>:</span>
                        <span class="info-value"><?php echo date('h:i A', strtotime($booking['end_time'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_field_duration'); ?>:</span>
                        <span class="info-value"><?php echo translated_hours_label($booking['hours']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_price_per_hour'); ?>:</span>
                        <span class="info-value"><?php echo number_format($booking['price_per_hour'], 2); ?> JOD</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_field_status'); ?>:</span>
                        <span class="info-value">
                            <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                <?php echo htmlspecialchars(t('status_' . strtolower($booking['status']), [], $booking['status'])); ?>
                            </span>
                        </span>
                    </div>
                    <?php if ($booking['notes']): ?>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_notes'); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['notes']); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Extend Hours Form -->
                    <form method="POST" style="margin-top: 1.5rem;">
                        <input type="hidden" name="action" value="extend_hours">
                        <?php echo admin_csrf_input(); ?>
                        <h3><?php echo t('admin_extend_booking'); ?></h3>
                        <div class="form-inline">
                            <div class="form-group">
                                <label><?php echo t('admin_extra_hours'); ?></label>
                                <input type="number" name="extra_hours" min="1" max="12" value="1" required>
                            </div>
                            <button type="submit" class="btn btn-success"><?php echo t('admin_extend_booking'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Payment Information -->
                <div class="card">
                    <h2><?php echo t('admin_payment_information'); ?></h2>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_payment_status'); ?>:</span>
                        <span class="info-value">
                            <span class="status-badge payment-<?php echo strtolower($booking['payment_status']); ?>">
                                <?php echo htmlspecialchars(t('status_' . strtolower($booking['payment_status']), [], $booking['payment_status'])); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_payment_method'); ?>:</span>
                        <span class="info-value"><?php echo $booking['payment_method'] ? htmlspecialchars(t('payment_' . strtolower($booking['payment_method']), [], $booking['payment_method'])) : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('admin_paid_amount'); ?>:</span>
                        <span class="info-value"><?php echo number_format($booking['paid_amount'], 2); ?> JOD</span>
                    </div>

                    <div class="total-summary">
                        <div class="total-row">
                            <span><?php echo t('admin_room_booking'); ?>:</span>
                            <span><?php echo number_format($booking['total_price'], 2); ?> JOD</span>
                        </div>
                        <div class="total-row">
                            <span><?php echo t('admin_additional_items'); ?>:</span>
                            <span><?php echo number_format((float)($booking['additional_items_total'] ?? 0), 2); ?> JOD</span>
                        </div>
                        <div class="total-row final">
                            <span><?php echo t('admin_total_label'); ?>:</span>
                            <span><?php echo number_format($booking_final_total, 2); ?> JOD</span>
                        </div>
                        <?php if ($booking_final_total > $booking['paid_amount']): ?>
                        <div class="total-row" style="color: #f44336;">
                            <span><?php echo t('admin_balance_due'); ?>:</span>
                            <span><?php echo number_format($booking_final_total - $booking['paid_amount'], 2); ?> JOD</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Update Payment Form -->
                    <form method="POST" style="margin-top: 1.5rem;">
                        <input type="hidden" name="action" value="update_payment">
                        <?php echo admin_csrf_input(); ?>
                        <h3><?php echo t('admin_update_payment'); ?></h3>
                        <div class="form-group">
                            <label><?php echo t('admin_payment_status'); ?></label>
                            <select name="payment_status" required>
                                <option value="Unpaid" <?php echo $booking['payment_status'] == 'Unpaid' ? 'selected' : ''; ?>><?php echo t('status_unpaid'); ?></option>
                                <option value="Partial" <?php echo $booking['payment_status'] == 'Partial' ? 'selected' : ''; ?>><?php echo t('status_partial'); ?></option>
                                <option value="Paid" <?php echo $booking['payment_status'] == 'Paid' ? 'selected' : ''; ?>><?php echo t('status_paid'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo t('admin_payment_method'); ?></label>
                            <select name="payment_method" required>
                                <option value=""><?php echo t('admin_select_method'); ?></option>
                                <option value="Cash" <?php echo $booking['payment_method'] == 'Cash' ? 'selected' : ''; ?>><?php echo t('payment_cash'); ?></option>
                                <option value="CliQ" <?php echo $booking['payment_method'] == 'CliQ' ? 'selected' : ''; ?>><?php echo t('payment_cliq'); ?></option>
                                <option value="Visa" <?php echo $booking['payment_method'] == 'Visa' ? 'selected' : ''; ?>><?php echo t('payment_visa'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo t('admin_paid_amount'); ?> (JOD)</label>
                            <input type="number" step="0.01" name="paid_amount" value="<?php echo $booking['paid_amount']; ?>" required>
                        </div>
                        <button type="submit" class="btn btn-success" style="width: 100%;"><?php echo t('admin_update_payment'); ?></button>
                    </form>
                </div>
            </div>

            <!-- Additional Items Section -->
            <div class="card">
                <h2><?php echo t('admin_orders_services'); ?></h2>

                <!-- Add Item Form -->
                <form method="POST">
                    <input type="hidden" name="action" value="add_item">
                    <?php echo admin_csrf_input(); ?>
                    <div class="form-inline">
                        <div class="form-group" style="flex: 2;">
                            <label><?php echo t('admin_select_item_service'); ?></label>
                            <select name="menu_item_id" required>
                                <option value=""><?php echo t('admin_choose_item'); ?></option>
                                <?php
                                $current_category = '';
                                while ($item = mysqli_fetch_assoc($menu_items)):
                                    if ($current_category != $item['item_category']):
                                        if ($current_category != '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars(translated_menu_category_label($item['item_category'])) . '">';
                                        $current_category = $item['item_category'];
                                    endif;
                                ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['item_name']); ?> - <?php echo number_format($item['item_price'], 2); ?> JOD
                                    </option>
                                <?php endwhile; ?>
                                <?php if ($current_category != '') echo '</optgroup>'; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo t('admin_quantity'); ?></label>
                            <input type="number" name="quantity" min="1" value="1" required style="max-width: 100px;">
                        </div>
                        <button type="submit" class="btn btn-success"><?php echo t('admin_action_add'); ?></button>
                    </div>
                </form>

                <!-- Ordered Items Table -->
                <?php if (mysqli_num_rows($booking_items) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo t('admin_field_item_name'); ?></th>
                            <th><?php echo t('admin_field_category'); ?></th>
                            <th><?php echo t('admin_field_price'); ?></th>
                            <th><?php echo t('admin_quantity'); ?></th>
                            <th><?php echo t('admin_total_label'); ?></th>
                            <th><?php echo t('admin_field_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        mysqli_data_seek($booking_items, 0);
                        while ($item = mysqli_fetch_assoc($booking_items)):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td>
                                <span class="category-badge category-<?php echo strtolower($item['item_category']); ?>">
                                    <?php echo htmlspecialchars(translated_menu_category_label($item['item_category'])); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($item['item_price'], 2); ?> JOD</td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['item_total'], 2); ?> JOD</td>
                            <td>
                                <form method="POST" style="display: inline;" data-admin-confirm-form data-admin-confirm-message="<?php echo htmlspecialchars(t('admin_delete_confirm'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <?php echo admin_csrf_input(); ?>
                                    <button type="submit" class="btn btn-small btn-danger"><?php echo t('admin_remove'); ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-items"><?php echo t('admin_no_additional_items'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-fade messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
