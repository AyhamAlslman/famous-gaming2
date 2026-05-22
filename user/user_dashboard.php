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
$rooms_result = mysqli_query($conn, "SELECT id, room_name, room_type, price_per_hour, status FROM rooms ORDER BY FIELD(status, 'Available', 'Busy'), room_name ASC");
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

$dashboard_stats = [
    'bookings' => 0,
    'paid' => 0,
    'upcoming' => 0,
    'unread' => count_unread_site_notifications($conn, $site_user_id)
];

$stats_stmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS bookings_count,
        SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(CASE WHEN status IN ('Pending', 'Confirmed') AND booking_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming_count
     FROM bookings
     WHERE user_id = ?"
);
mysqli_stmt_bind_param($stats_stmt, "i", $site_user_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
if ($stats_result && ($stats_row = mysqli_fetch_assoc($stats_result))) {
    $dashboard_stats['bookings'] = (int)$stats_row['bookings_count'];
    $dashboard_stats['paid'] = (int)$stats_row['paid_count'];
    $dashboard_stats['upcoming'] = (int)$stats_row['upcoming_count'];
}
mysqli_stmt_close($stats_stmt);

$dashboard_links = [
    [
        'url' => site_url('general/services.php'),
        'label' => t('nav_services'),
        'title' => t('home_services_title'),
        'text' => t('services_hero_text')
    ],
    [
        'url' => site_url('user/booking.php'),
        'label' => t('nav_book_now'),
        'title' => t('user_dashboard_choose_booking'),
        'text' => t('user_dashboard_choose_booking_text')
    ],
    [
        'url' => site_url('user/store.php'),
        'label' => t('nav_store'),
        'title' => t('user_dashboard_store'),
        'text' => t('user_dashboard_store_text')
    ],
    [
        'url' => site_url('user/my_bookings.php'),
        'label' => t('nav_my_bookings'),
        'title' => t('user_dashboard_history'),
        'text' => t('user_dashboard_history_text')
    ],
    [
        'url' => site_url('user/complaints.php'),
        'label' => t('nav_feedback'),
        'title' => t('user_dashboard_support'),
        'text' => t('user_dashboard_support_text')
    ]
];

$loyalty_settings = get_loyalty_settings($conn);
$loyalty_earn_display = rtrim(rtrim(number_format((float)$loyalty_settings['earn_per_jod'], 2), '0'), '.');
$loyalty_redeem_display = rtrim(rtrim(number_format((float)$loyalty_settings['redeem_points_per_jod'], 2), '0'), '.');

include dirname(__DIR__) . '/includes/header.php';
?>

<main class="user-overview-page">
    <section class="hero user-dashboard-hero user-overview-hero">
        <div class="container user-overview-hero-grid">
            <div>
                <span class="ticket-label"><?php echo t('nav_account'); ?></span>
                <h1><?php echo t('user_dashboard_heading', ['name' => htmlspecialchars($current_site_user['full_name'])]); ?></h1>
                <p><?php echo t('user_dashboard_subtitle'); ?></p>
            </div>
            <div class="user-overview-hero-actions">
                <a href="<?php echo htmlspecialchars(site_url('user/booking.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('nav_book_now'); ?></a>
                <a href="<?php echo htmlspecialchars(site_url('user/complaints.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn payment-secondary-btn"><?php echo t('nav_feedback'); ?></a>
            </div>
        </div>
    </section>

    <section class="content user-dashboard-content user-overview-content">
        <div class="container">
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

            <div class="user-overview-layout">
                <div class="user-overview-main">
                    <div class="user-stats-grid" aria-label="<?php echo htmlspecialchars(t('user_dashboard_page_title'), ENT_QUOTES, 'UTF-8'); ?>">
                        <a class="user-stat-card" href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>">
                            <span><?php echo t('user_dashboard_total_bookings'); ?></span>
                            <strong><?php echo $dashboard_stats['bookings']; ?></strong>
                        </a>
                        <a class="user-stat-card" href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>">
                            <span><?php echo t('status_confirmed'); ?></span>
                            <strong><?php echo $dashboard_stats['upcoming']; ?></strong>
                        </a>
                        <a class="user-stat-card" href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>">
                            <span><?php echo t('user_dashboard_paid_bookings'); ?></span>
                            <strong><?php echo $dashboard_stats['paid']; ?></strong>
                        </a>
                        <a class="user-stat-card" href="<?php echo htmlspecialchars(site_url('user/notifications.php'), ENT_QUOTES, 'UTF-8'); ?>">
                            <span><?php echo t('nav_notifications'); ?></span>
                            <strong><?php echo $dashboard_stats['unread']; ?></strong>
                        </a>
                    </div>

                    <section class="user-panel user-next-panel">
                        <div class="home-section-heading user-panel-heading">
                            <span class="ticket-label"><?php echo t('common_schedule'); ?></span>
                            <h2><?php echo t('my_bookings_hero_title'); ?></h2>
                        </div>

                        <?php if ($next_booking): ?>
                            <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="user-next-booking-card">
                                <div>
                                    <span><?php echo htmlspecialchars($next_booking['booking_code'] ?: ('FG-' . str_pad($next_booking['id'], 6, '0', STR_PAD_LEFT))); ?></span>
                                    <h3><?php echo htmlspecialchars($next_booking['room_name']); ?> - <?php echo htmlspecialchars($next_booking['room_type']); ?></h3>
                                    <p><?php echo format_date($next_booking['booking_date']); ?>, <?php echo format_time($next_booking['start_time']); ?> - <?php echo translated_hours_label($next_booking['hours']); ?></p>
                                </div>
                                <strong><?php echo htmlspecialchars(t('status_' . strtolower($next_booking['status']), [], $next_booking['status'])); ?></strong>
                            </a>
                        <?php else: ?>
                            <div class="empty-bookings user-dashboard-empty user-compact-empty">
                                <h2><?php echo t('my_bookings_empty_title'); ?></h2>
                                <p><?php echo t('my_bookings_empty_text'); ?></p>
                                <a href="<?php echo htmlspecialchars(site_url('user/booking.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-small"><?php echo t('nav_book_now'); ?></a>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="user-panel">
                        <div class="home-section-heading user-panel-heading">
                            <span class="ticket-label">FAMOUS GAMING</span>
                            <h2><?php echo t('nav_services'); ?></h2>
                            <p><?php echo t('user_dashboard_subtitle'); ?></p>
                        </div>

                        <div class="user-action-grid">
                            <?php foreach ($dashboard_links as $link): ?>
                                <a href="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>" class="user-action-card">
                                    <span><?php echo $link['label']; ?></span>
                                    <h3><?php echo $link['title']; ?></h3>
                                    <p><?php echo $link['text']; ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="user-panel">
                        <div class="home-section-heading user-panel-heading">
                            <span class="ticket-label"><?php echo t('home_rooms_title'); ?></span>
                            <h2><?php echo t('user_dashboard_pick_room'); ?></h2>
                            <p><?php echo t('user_dashboard_pick_room_text'); ?></p>
                        </div>

                        <?php if (empty($rooms)): ?>
                            <div class="home-no-rooms"><?php echo t('booking_addons_empty'); ?></div>
                        <?php else: ?>
                            <div class="user-room-status-grid">
                                <?php foreach ($rooms as $room): ?>
                                    <?php
                                    $room_status_key = strtolower((string)$room['status']);
                                    $room_status_class = preg_replace('/[^a-z0-9_-]+/i', '-', $room_status_key);
                                    $is_available = $room['status'] === 'Available';
                                    ?>
                                    <article class="user-room-status-card status-<?php echo htmlspecialchars($room_status_class, ENT_QUOTES, 'UTF-8'); ?>">
                                        <div>
                                            <span><?php echo htmlspecialchars($room['room_type']); ?></span>
                                            <h3><?php echo htmlspecialchars($room['room_name']); ?></h3>
                                        </div>
                                        <strong><?php echo htmlspecialchars(t('status_' . $room_status_key, [], $room['status'])); ?></strong>
                                        <?php if ($is_available): ?>
                                            <a href="<?php echo htmlspecialchars(site_url('user/booking.php?room_id=' . (int)$room['id'] . '#booking-form'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-small"><?php echo t('home_book_room'); ?></a>
                                        <?php else: ?>
                                            <span class="user-room-hold"><?php echo number_format((float)$room['price_per_hour'], 2); ?> <?php echo t('home_room_price_suffix'); ?></span>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="user-panel dashboard-feedback-section" id="dashboard-feedback">
                        <div class="home-section-heading user-panel-heading">
                            <span class="ticket-label"><?php echo t('nav_feedback'); ?></span>
                            <h2><?php echo t('dashboard_feedback_title'); ?></h2>
                            <p><?php echo t('dashboard_feedback_text'); ?></p>
                        </div>
                        <form method="POST" action="<?php echo htmlspecialchars(site_url('user/user_dashboard.php#dashboard-feedback'), ENT_QUOTES, 'UTF-8'); ?>" class="dashboard-feedback-form">
                            <input type="hidden" name="profile_action" value="dashboard_feedback">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <textarea name="feedback_message" class="form-control" rows="4" required placeholder="<?php echo htmlspecialchars(t('complaints_message_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                            <button type="submit" class="btn"><?php echo t('complaints_submit'); ?></button>
                        </form>
                    </section>

                    <section class="user-panel">
                        <div class="home-section-heading user-panel-heading">
                            <span class="ticket-label"><?php echo t('my_bookings_history_title'); ?></span>
                            <h2><?php echo t('user_dashboard_recent_bookings'); ?></h2>
                        </div>

                        <?php if (empty($recent_bookings)): ?>
                            <div class="empty-bookings user-dashboard-empty user-compact-empty">
                                <h2><?php echo t('my_bookings_empty_title'); ?></h2>
                                <p><?php echo t('my_bookings_empty_text'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="user-bookings-mini-list">
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="user-booking-mini-card">
                                        <div>
                                            <span><?php echo htmlspecialchars($booking['booking_code'] ?: ('FG-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT))); ?></span>
                                            <h3><?php echo htmlspecialchars($booking['room_name']); ?> - <?php echo htmlspecialchars($booking['room_type']); ?></h3>
                                            <p><?php echo format_date($booking['booking_date']); ?>, <?php echo format_time($booking['start_time']); ?> - <?php echo translated_hours_label($booking['hours']); ?></p>
                                        </div>
                                        <strong><?php echo htmlspecialchars(t('status_' . strtolower($booking['status']), [], $booking['status'])); ?></strong>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="user-overview-sidebar">
                    <div class="profile-side-card user-loyalty-card" id="profile-loyalty">
                        <span class="ticket-label"><?php echo t('loyalty_points'); ?></span>
                        <strong><?php echo (int)$current_site_user['loyalty_points']; ?></strong>
                        <p><?php echo t('profile_loyalty_text'); ?></p>
                        <div class="user-loyalty-rules">
                            <b><?php echo t('loyalty_calculation_title'); ?></b>
                            <span><?php echo t('loyalty_calculation_earn', ['points' => $loyalty_earn_display]); ?></span>
                            <span><?php echo t('loyalty_calculation_redeem', ['points' => $loyalty_redeem_display]); ?></span>
                            <em><?php echo t('loyalty_calculation_balance'); ?></em>
                        </div>
                        <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn payment-secondary-btn"><?php echo t('nav_my_bookings'); ?></a>
                    </div>

                    <details class="user-dashboard-profile-panel" id="profile-details">
                        <summary>
                            <span><?php echo t('profile_label'); ?></span>
                            <strong><?php echo t('profile_title'); ?></strong>
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

                    <a class="user-sidebar-logout" href="<?php echo htmlspecialchars(site_url('general/logout.php'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm-message="<?php echo htmlspecialchars(t('logout_confirm'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm-title="<?php echo htmlspecialchars(t('modal_confirm_title'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_logout'); ?></a>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
