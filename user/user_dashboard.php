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

$rooms = [];
$rooms_result = mysqli_query($conn, "SELECT * FROM rooms ORDER BY FIELD(status, 'Available', 'Busy'), room_name ASC");
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
     LIMIT 5"
);
mysqli_stmt_bind_param($stmt, "i", $site_user_id);
mysqli_stmt_execute($stmt);
$recent_result = mysqli_stmt_get_result($stmt);
if ($recent_result) {
    $recent_bookings = mysqli_fetch_all($recent_result, MYSQLI_ASSOC);
}
mysqli_stmt_close($stmt);

$dashboard_stats = [
    'bookings' => 0,
    'paid' => 0,
    'unread' => count_unread_site_notifications($conn, $site_user_id)
];

$stats_stmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS bookings_count,
        SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid_count
     FROM bookings
     WHERE user_id = ?"
);
mysqli_stmt_bind_param($stats_stmt, "i", $site_user_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
if ($stats_result && ($stats_row = mysqli_fetch_assoc($stats_result))) {
    $dashboard_stats['bookings'] = (int)$stats_row['bookings_count'];
    $dashboard_stats['paid'] = (int)$stats_row['paid_count'];
}
mysqli_stmt_close($stats_stmt);

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero user-dashboard-hero">
    <div class="container">
        <h1><?php echo t('user_dashboard_heading', ['name' => htmlspecialchars($current_site_user['full_name'])]); ?></h1>
        <p><?php echo t('user_dashboard_subtitle'); ?></p>
    </div>
</section>

<section class="content user-dashboard-content">
    <div class="container">
        <div class="user-dashboard-grid">
            <a class="user-dashboard-stat" href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <span><?php echo t('user_dashboard_total_bookings'); ?></span>
                <strong><?php echo $dashboard_stats['bookings']; ?></strong>
            </a>
            <a class="user-dashboard-stat" href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <span><?php echo t('user_dashboard_paid_bookings'); ?></span>
                <strong><?php echo $dashboard_stats['paid']; ?></strong>
            </a>
            <a class="user-dashboard-stat loyalty-stat" href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <span><?php echo t('loyalty_points'); ?></span>
                <strong><?php echo (int)$current_site_user['loyalty_points']; ?></strong>
            </a>
            <a class="user-dashboard-stat" href="<?php echo htmlspecialchars(site_url('user/notifications.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <span><?php echo t('nav_notifications'); ?></span>
                <strong><?php echo $dashboard_stats['unread']; ?></strong>
            </a>
        </div>

        <div class="user-dashboard-actions">
            <a href="<?php echo htmlspecialchars(site_url('user/booking.php'), ENT_QUOTES, 'UTF-8'); ?>" class="user-action-card">
                <span><?php echo t('nav_book_now'); ?></span>
                <h2><?php echo t('user_dashboard_choose_booking'); ?></h2>
                <p><?php echo t('user_dashboard_choose_booking_text'); ?></p>
            </a>
            <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="user-action-card">
                <span><?php echo t('nav_my_bookings'); ?></span>
                <h2><?php echo t('user_dashboard_history'); ?></h2>
                <p><?php echo t('user_dashboard_history_text'); ?></p>
            </a>
            <a href="<?php echo htmlspecialchars(site_url('user/complaints.php'), ENT_QUOTES, 'UTF-8'); ?>" class="user-action-card">
                <span><?php echo t('footer_support'); ?></span>
                <h2><?php echo t('user_dashboard_support'); ?></h2>
                <p><?php echo t('user_dashboard_support_text'); ?></p>
            </a>
            <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>" class="user-action-card">
                <span><?php echo t('nav_store'); ?></span>
                <h2><?php echo t('user_dashboard_store'); ?></h2>
                <p><?php echo t('user_dashboard_store_text'); ?></p>
            </a>
        </div>

        <div class="user-dashboard-section">
            <div class="home-section-heading">
                <span class="ticket-label"><?php echo t('home_rooms_title'); ?></span>
                <h2><?php echo t('user_dashboard_pick_room'); ?></h2>
                <p><?php echo t('user_dashboard_pick_room_text'); ?></p>
            </div>

            <div class="booking-room-showcase-grid">
                <?php foreach ($rooms as $room): ?>
                    <?php
                    $room_image = site_asset_url($room['image_path'] ?? '', 'images/home-hero-background.png');
                    $is_available = $room['status'] === 'Available';
                    ?>
                    <article class="booking-room-detail-card user-room-card <?php echo $is_available ? 'is-available' : 'is-busy'; ?>">
                        <div class="booking-room-detail-media">
                            <img src="<?php echo htmlspecialchars($room_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($room['room_name']); ?>">
                            <span class="user-room-status"><?php echo htmlspecialchars(t('status_' . strtolower($room['status']), [], $room['status'])); ?></span>
                        </div>
                        <div class="booking-room-detail-body">
                            <span><?php echo htmlspecialchars($room['room_type']); ?></span>
                            <h3><?php echo htmlspecialchars($room['room_name']); ?></h3>
                            <p><?php echo htmlspecialchars($room['description'] ?: $room['services']); ?></p>
                            <div class="booking-room-detail-footer">
                                <strong><?php echo number_format((float)$room['price_per_hour'], 2); ?> <?php echo t('home_room_price_suffix'); ?></strong>
                                <?php if ($is_available): ?>
                                    <a class="btn btn-small" href="<?php echo htmlspecialchars(site_url('user/booking.php?room_id=' . (int)$room['id'] . '#booking-form'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('home_book_room'); ?></a>
                                <?php else: ?>
                                    <span class="btn btn-small room-book-btn-disabled"><?php echo t('home_booking_unavailable'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="user-dashboard-section">
            <div class="home-section-heading">
                <span class="ticket-label"><?php echo t('my_bookings_history_title'); ?></span>
                <h2><?php echo t('user_dashboard_recent_bookings'); ?></h2>
            </div>

            <?php if (empty($recent_bookings)): ?>
                <div class="empty-bookings user-dashboard-empty">
                    <h2><?php echo t('my_bookings_empty_title'); ?></h2>
                    <p><?php echo t('my_bookings_empty_text'); ?></p>
                    <a href="<?php echo htmlspecialchars(site_url('user/booking.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('nav_book_now'); ?></a>
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
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
