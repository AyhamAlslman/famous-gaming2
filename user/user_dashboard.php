<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);
$current_site_user = get_current_site_user($conn);

if (!$current_site_user) {
    $_SESSION['post_login_redirect'] = 'user/user_dashboard.php';
    header('Location: ' . site_url('general/login.php?redirect=user/user_dashboard.php'));
    exit;
}

$site_user_id = (int)$current_site_user['id'];
$page_title = t('user_dashboard_page_title');
$profile_success_msg = '';
$profile_error_msg = '';
$feedback_success_msg = '';
$feedback_error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'update_profile') {
    $profile_name = sanitize_input($_POST['full_name'] ?? '');
    $profile_email = sanitize_input($_POST['email'] ?? '');
    $profile_phone = sanitize_input($_POST['phone'] ?? '');
    $profile_password = (string)($_POST['password'] ?? '');
    $profile_confirm_password = (string)($_POST['confirm_password'] ?? '');
    $profile_errors = [];

    if ($profile_name === '') {
        $profile_errors[] = t('profile_name_required');
    }

    if (!validate_email($profile_email)) {
        $profile_errors[] = t('profile_email_invalid');
    }

    if ($profile_phone !== '' && !validate_phone($profile_phone)) {
        $profile_errors[] = t('profile_phone_invalid');
    }

    if ($profile_password !== '' || $profile_confirm_password !== '') {
        if (strlen($profile_password) < 6) {
            $profile_errors[] = t('auth_password_short');
        }

        if ($profile_password !== $profile_confirm_password) {
            $profile_errors[] = t('auth_password_mismatch');
        }
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $profile_errors[] = t('payment_session_expired');
    }

    if (empty($profile_errors)) {
        $email_stmt = mysqli_prepare($conn, "SELECT id FROM site_users WHERE email = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($email_stmt, "si", $profile_email, $site_user_id);
        mysqli_stmt_execute($email_stmt);
        $email_result = mysqli_stmt_get_result($email_stmt);
        $email_exists = $email_result && mysqli_fetch_assoc($email_result);
        mysqli_stmt_close($email_stmt);

        if ($email_exists) {
            $profile_errors[] = t('profile_email_exists');
        }
    }

    if (empty($profile_errors)) {
        if ($profile_password !== '') {
            $password_hash = password_hash($profile_password, PASSWORD_DEFAULT);
            $update_stmt = mysqli_prepare($conn, "UPDATE site_users SET full_name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "ssssi", $profile_name, $profile_email, $profile_phone, $password_hash, $site_user_id);
        } else {
            $update_stmt = mysqli_prepare($conn, "UPDATE site_users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "sssi", $profile_name, $profile_email, $profile_phone, $site_user_id);
        }

        if (mysqli_stmt_execute($update_stmt)) {
            $profile_success_msg = t('profile_update_success');
            $current_site_user = get_current_site_user($conn);
            $_SESSION['site_user_name'] = $current_site_user['full_name'];
            $_SESSION['site_user_loyalty_points'] = (int)$current_site_user['loyalty_points'];
        } else {
            $profile_error_msg = t('profile_update_error');
        }

        mysqli_stmt_close($update_stmt);
    }

    if (!empty($profile_errors)) {
        $profile_error_msg = implode('<br>', $profile_errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'dashboard_feedback') {
    $feedback_message = sanitize_input($_POST['feedback_message'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $feedback_error_msg = t('payment_session_expired');
    } elseif ($feedback_message === '') {
        $feedback_error_msg = t('complaints_required');
    } else {
        $feedback_stmt = mysqli_prepare($conn, "INSERT INTO complaints (user_id, customer_name, phone, message) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($feedback_stmt, "isss", $site_user_id, $current_site_user['full_name'], $current_site_user['phone'], $feedback_message);

        if (mysqli_stmt_execute($feedback_stmt)) {
            $complaint_id = mysqli_insert_id($conn);
            create_admin_notification(
                $conn,
                'feedback_created',
                'New feedback submitted',
                $current_site_user['full_name'] . ' submitted new dashboard feedback.',
                'complaints',
                $complaint_id,
                'complaints_full_crud.php'
            );
            create_site_notification(
                $conn,
                $site_user_id,
                'support_sent',
                t('complaints_success'),
                t('user_notification_support_sent'),
                'user/complaints.php'
            );
            $feedback_success_msg = t('complaints_success');
        } else {
            $feedback_error_msg = t('complaints_error');
        }

        mysqli_stmt_close($feedback_stmt);
    }
}

$rooms = [];
$rooms_result = mysqli_query($conn, "SELECT id, room_name, room_type, price_per_hour, status, image_path FROM rooms ORDER BY FIELD(status, 'Available', 'Busy'), room_name ASC");
if ($rooms_result) {
    $rooms = mysqli_fetch_all($rooms_result, MYSQLI_ASSOC);
}

$recent_bookings = [];
$stmt = mysqli_prepare(
    $conn,
    "SELECT b.*, r.room_name, r.room_type
     FROM bookings b
     LEFT JOIN rooms r ON b.room_id = r.id
     WHERE b.user_id = ?
     ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC
     LIMIT 4"
);
mysqli_stmt_bind_param($stmt, "i", $site_user_id);
mysqli_stmt_execute($stmt);
$recent_result = mysqli_stmt_get_result($stmt);
if ($recent_result) {
    $recent_bookings = mysqli_fetch_all($recent_result, MYSQLI_ASSOC);
}
mysqli_stmt_close($stmt);

$next_booking = null;
$next_stmt = mysqli_prepare(
    $conn,
    "SELECT b.*, r.room_name, r.room_type
     FROM bookings b
     LEFT JOIN rooms r ON b.room_id = r.id
     WHERE b.user_id = ?
       AND b.status IN ('Pending', 'Confirmed')
       AND (b.booking_date > CURDATE() OR (b.booking_date = CURDATE() AND b.start_time >= CURTIME()))
     ORDER BY b.booking_date ASC, b.start_time ASC, b.id ASC
     LIMIT 1"
);
mysqli_stmt_bind_param($next_stmt, "i", $site_user_id);
mysqli_stmt_execute($next_stmt);
$next_result = mysqli_stmt_get_result($next_stmt);
if ($next_result) {
    $next_booking = mysqli_fetch_assoc($next_result) ?: null;
}
mysqli_stmt_close($next_stmt);

$loyalty_settings = get_loyalty_settings($conn);
$loyalty_earn_display = rtrim(rtrim(number_format((float)$loyalty_settings['earn_per_jod'], 2), '0'), '.');
$loyalty_redeem_display = rtrim(rtrim(number_format((float)$loyalty_settings['redeem_points_per_jod'], 2), '0'), '.');
$dashboard_initial = function_exists('mb_substr') ? mb_substr($current_site_user['full_name'], 0, 1, 'UTF-8') : substr($current_site_user['full_name'], 0, 1);
$dashboard_room_cards = array_slice($rooms, 0, 4);
$dashboard_recent_activity = array_slice($recent_bookings, 0, 3);

include dirname(__DIR__) . '/includes/header.php';
?>

<main class="user-dashboard-v2" id="dashboard-home">
    <aside class="user-dashboard-rail" aria-label="<?php echo htmlspecialchars(t('nav_account'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="dashboard-rail-profile">
            <span><?php echo htmlspecialchars($dashboard_initial, ENT_QUOTES, 'UTF-8'); ?></span>
            <strong><?php echo htmlspecialchars($current_site_user['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <em><?php echo (int)$current_site_user['loyalty_points']; ?> <?php echo t('loyalty_points_short'); ?></em>
        </div>
        <a class="active" href="#dashboard-home" aria-label="<?php echo htmlspecialchars(t('nav_home'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="dashboard-rail-icon">H</span>
            <span class="dashboard-rail-text"><strong><?php echo t('nav_home'); ?></strong><em><?php echo t('common_view'); ?></em></span>
        </a>
        <a href="<?php echo htmlspecialchars(site_url('user/booking.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('nav_book_now'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="dashboard-rail-icon">B</span>
            <span class="dashboard-rail-text"><strong><?php echo t('nav_book_now'); ?></strong><em><?php echo t('home_rooms_title'); ?></em></span>
        </a>
        <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('nav_store'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="dashboard-rail-icon">S</span>
            <span class="dashboard-rail-text"><strong><?php echo t('nav_store'); ?></strong><em><?php echo t('user_dashboard_store'); ?></em></span>
        </a>
        <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('nav_my_bookings'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="dashboard-rail-icon">M</span>
            <span class="dashboard-rail-text"><strong><?php echo t('nav_my_bookings'); ?></strong><em><?php echo t('common_schedule'); ?></em></span>
        </a>
        <a href="<?php echo htmlspecialchars(site_url('user/complaints.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('nav_feedback'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="dashboard-rail-icon">F</span>
            <span class="dashboard-rail-text"><strong><?php echo t('nav_feedback'); ?></strong><em><?php echo t('dashboard_feedback_title'); ?></em></span>
        </a>
        <a href="#profile-details" aria-label="<?php echo htmlspecialchars(t('profile_title'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="dashboard-rail-icon">P</span>
            <span class="dashboard-rail-text"><strong><?php echo t('profile_title'); ?></strong><em><?php echo t('nav_account'); ?></em></span>
        </a>
    </aside>

    <section class="user-dashboard-stage">
        <div class="user-dashboard-inner">
            <?php if ($profile_success_msg): ?>
                <div class="message success"><?php echo $profile_success_msg; ?></div>
            <?php endif; ?>

            <?php if ($profile_error_msg): ?>
                <div class="message error"><?php echo $profile_error_msg; ?></div>
            <?php endif; ?>

            <?php if ($feedback_success_msg): ?>
                <div class="message success"><?php echo $feedback_success_msg; ?></div>
            <?php endif; ?>

            <?php if ($feedback_error_msg): ?>
                <div class="message error"><?php echo $feedback_error_msg; ?></div>
            <?php endif; ?>

            <section class="user-dashboard-v2-hero">
                <div class="user-dashboard-v2-avatar"><?php echo htmlspecialchars($dashboard_initial, ENT_QUOTES, 'UTF-8'); ?></div>
                <div>
                    <span><?php echo t('nav_account'); ?></span>
                    <h1><?php echo t('user_dashboard_heading', ['name' => htmlspecialchars($current_site_user['full_name'])]); ?></h1>
                    <p><?php echo t('user_dashboard_subtitle'); ?></p>
                </div>
            </section>

            <div class="user-dashboard-v2-layout web-dashboard-container">
                <div class="user-dashboard-v2-main">
                    <section class="dashboard-v2-card dashboard-v2-activity">
                        <div class="dashboard-v2-card-head">
                            <div>
                                <span><?php echo t('common_schedule'); ?></span>
                                <h2><?php echo t('nav_my_bookings'); ?></h2>
                            </div>
                            <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('common_view'); ?></a>
                        </div>

                        <?php if (empty($dashboard_recent_activity)): ?>
                            <div class="dashboard-v2-empty">
                                <strong><?php echo t('my_bookings_empty_title'); ?></strong>
                                <p><?php echo t('my_bookings_empty_text'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-v2-activity-list">
                                <?php foreach ($dashboard_recent_activity as $booking): ?>
                                    <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="dashboard-v2-activity-row">
                                        <strong><?php echo htmlspecialchars($booking['booking_code'] ?: ('FG-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT))); ?></strong>
                                        <span><?php echo htmlspecialchars($booking['room_name']); ?> - <?php echo format_date($booking['booking_date']); ?></span>
                                        <em><?php echo htmlspecialchars(t('status_' . strtolower($booking['status']), [], $booking['status'])); ?></em>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="dashboard-v2-card dashboard-v2-rooms">
                        <div class="dashboard-v2-card-head">
                            <div>
                                <span><?php echo t('home_rooms_title'); ?></span>
                                <h2><?php echo t('user_dashboard_pick_room'); ?></h2>
                            </div>
                            <a href="<?php echo htmlspecialchars(site_url('user/booking.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_book_now'); ?></a>
                        </div>

                        <?php if (empty($dashboard_room_cards)): ?>
                            <div class="dashboard-v2-empty"><?php echo t('booking_addons_empty'); ?></div>
                        <?php else: ?>
                            <div class="dashboard-v2-room-grid">
                                <?php foreach ($dashboard_room_cards as $room): ?>
                                    <?php
                                    $room_status_key = strtolower((string)$room['status']);
                                    $is_available = $room['status'] === 'Available';
                                    $room_image = site_asset_url($room['image_path'] ?? '', 'images/home-hero-background.png');
                                    ?>
                                    <article class="dashboard-v2-room-card">
                                        <div class="dashboard-v2-room-main">
                                            <img src="<?php echo htmlspecialchars($room_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($room['room_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <div>
                                                <span><?php echo htmlspecialchars($room['room_type']); ?></span>
                                                <h3><?php echo htmlspecialchars($room['room_name']); ?></h3>
                                                <em class="<?php echo $is_available ? 'is-live' : 'is-busy'; ?>"><?php echo htmlspecialchars(t('status_' . $room_status_key, [], $room['status'])); ?></em>
                                            </div>
                                        </div>
                                        <?php if ($is_available): ?>
                                            <a href="<?php echo htmlspecialchars(site_url('user/booking.php?room_id=' . (int)$room['id'] . '#booking-form'), ENT_QUOTES, 'UTF-8'); ?>" class="dashboard-v2-book-btn"><?php echo t('home_book_room'); ?></a>
                                        <?php else: ?>
                                            <span class="dashboard-v2-hold"><?php echo number_format((float)$room['price_per_hour'], 2); ?> <?php echo t('home_room_price_suffix'); ?></span>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="dashboard-v2-card dashboard-v2-next">
                        <div class="dashboard-v2-card-head">
                            <div>
                                <span><?php echo t('common_schedule'); ?></span>
                                <h2><?php echo t('my_bookings_hero_title'); ?></h2>
                            </div>
                        </div>
                        <?php if ($next_booking): ?>
                            <a class="dashboard-v2-session" href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>">
                                <time><?php echo date('M d', strtotime($next_booking['booking_date'])); ?></time>
                                <div>
                                    <strong><?php echo htmlspecialchars($next_booking['room_name']); ?> - <?php echo htmlspecialchars($next_booking['room_type']); ?></strong>
                                    <span><?php echo format_time($next_booking['start_time']); ?> - <?php echo translated_hours_label($next_booking['hours']); ?></span>
                                </div>
                            </a>
                        <?php else: ?>
                            <div class="dashboard-v2-empty">
                                <strong><?php echo t('my_bookings_empty_title'); ?></strong>
                                <p><?php echo t('my_bookings_empty_text'); ?></p>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="user-dashboard-v2-side">
                    <section class="dashboard-v2-card dashboard-v2-side-profile">
                        <div class="dashboard-v2-side-avatar"><?php echo htmlspecialchars($dashboard_initial, ENT_QUOTES, 'UTF-8'); ?></div>
                        <strong><?php echo htmlspecialchars($current_site_user['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo htmlspecialchars($current_site_user['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </section>

                    <section class="dashboard-v2-card dashboard-v2-loyalty" id="profile-loyalty">
                        <div class="dashboard-v2-card-head">
                            <div>
                                <span><?php echo t('loyalty_points_short'); ?></span>
                                <h2><?php echo t('loyalty_points'); ?></h2>
                            </div>
                        </div>
                        <div class="dashboard-v2-loyalty-score">
                            <span><?php echo t('loyalty_points_short'); ?></span>
                            <strong><?php echo (int)$current_site_user['loyalty_points']; ?></strong>
                        </div>
                        <details class="dashboard-v2-details">
                            <summary><?php echo t('loyalty_calculation_title'); ?></summary>
                            <p><?php echo t('loyalty_calculation_earn', ['points' => $loyalty_earn_display]); ?></p>
                            <p><?php echo t('loyalty_calculation_redeem', ['points' => $loyalty_redeem_display]); ?></p>
                        </details>
                    </section>

                    <section class="dashboard-v2-card dashboard-v2-actions">
                        <h2><?php echo t('footer_quick_links'); ?></h2>
                        <div>
                            <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>"><span>S</span><?php echo t('nav_store'); ?></a>
                            <a href="<?php echo htmlspecialchars(site_url('user/menu.php'), ENT_QUOTES, 'UTF-8'); ?>"><span>N</span><?php echo t('booking_step_menu'); ?></a>
                            <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>"><span>M</span><?php echo t('nav_my_bookings'); ?></a>
                        </div>
                    </section>

                    <details class="dashboard-v2-card dashboard-v2-collapsible" id="profile-details" <?php echo $profile_error_msg ? 'open' : ''; ?>>
                        <summary>
                            <span><?php echo t('nav_profile'); ?></span>
                            <strong><?php echo t('profile_title'); ?></strong>
                            <em><?php echo htmlspecialchars($current_site_user['email'], ENT_QUOTES, 'UTF-8'); ?></em>
                        </summary>
                        <form method="POST" action="<?php echo htmlspecialchars(site_url('user/user_dashboard.php#profile-details'), ENT_QUOTES, 'UTF-8'); ?>" class="form-container profile-card user-profile-form">
                            <input type="hidden" name="profile_action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group">
                                <label class="form-label"><?php echo t('auth_full_name'); ?></label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($_POST['full_name'] ?? $current_site_user['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo t('auth_email'); ?></label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? $current_site_user['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo t('common_phone'); ?></label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ($current_site_user['phone'] ?? '')); ?>" placeholder="07XXXXXXXX">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo t('profile_new_password'); ?></label>
                                <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="<?php echo htmlspecialchars(t('profile_password_placeholder'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo t('auth_confirm_password'); ?></label>
                                <input type="password" name="confirm_password" class="form-control" autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn w-100"><?php echo t('profile_update_button'); ?></button>
                        </form>
                    </details>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
