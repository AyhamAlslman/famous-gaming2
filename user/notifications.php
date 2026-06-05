<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);
$current_site_user = get_current_site_user($conn);

if (!$current_site_user) {
    $_SESSION['post_login_redirect'] = 'user/notifications.php';
    header('Location: ' . site_url('general/login.php?redirect=user/notifications.php'));
    exit;
}

$site_user_id = (int)$current_site_user['id'];

function site_user_notification_url($action_url) {
    $action_url = trim((string)$action_url);

    if ($action_url === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $action_url)) {
        return $action_url;
    }

    if (str_contains($action_url, '..')) {
        return '';
    }

    $path = ltrim($action_url, '/');
    $route = preg_replace('/[?#].*$/', '', $path);
    $user_routes = [
        'booking.php',
        'room_booking.php',
        'my_bookings.php',
        'store.php',
        'payment.php',
        'visa_payment.php',
        'complaints.php',
        'notifications.php',
        'profile.php',
        'user_dashboard.php'
    ];

    if (in_array($route, $user_routes, true)) {
        $path = 'user/' . $path;
    }

    return site_url($path);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $action = sanitize_input($_POST['action'] ?? '');
    $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

    if ($action === 'mark_read' && $notification_id > 0) {
        mark_site_notification_read($conn, $site_user_id, $notification_id);
    } elseif ($action === 'mark_all_read') {
        mark_all_site_notifications_read($conn, $site_user_id);
    }

    header('Location: ' . site_url('user/notifications.php'));
    exit;
}

$page_title = t('notifications_page_title');
$notifications = get_site_notifications($conn, $site_user_id, 120);
$unread_count = count_unread_site_notifications($conn, $site_user_id);
$notification_summary = [
    'total' => count($notifications),
    'unread' => $unread_count,
    'booking' => 0,
    'store' => 0,
];

foreach ($notifications as $notification) {
    $meta = get_notification_type_meta($notification['notification_type'] ?? '');
    if (isset($notification_summary[$meta['group']])) {
        $notification_summary[$meta['group']]++;
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero notifications-hero">
    <div class="container">
        <h1><?php echo t('notifications_heading'); ?></h1>
        <p><?php echo t('notifications_subtitle'); ?></p>
    </div>
</section>

<section class="content notifications-content">
    <div class="container">
        <div class="notifications-shell">
            <div class="smart-notification-summary">
                <div class="smart-notification-summary-card">
                    <span><?php echo t('notifications_all'); ?></span>
                    <strong><?php echo (int)$notification_summary['total']; ?></strong>
                </div>
                <div class="smart-notification-summary-card">
                    <span><?php echo t('admin_unread', ['count' => $unread_count]); ?></span>
                    <strong><?php echo (int)$notification_summary['unread']; ?></strong>
                </div>
                <div class="smart-notification-summary-card">
                    <span><?php echo t('admin_nav_bookings'); ?></span>
                    <strong><?php echo (int)$notification_summary['booking']; ?></strong>
                </div>
                <div class="smart-notification-summary-card">
                    <span><?php echo t('nav_store'); ?></span>
                    <strong><?php echo (int)$notification_summary['store']; ?></strong>
                </div>
            </div>

            <div class="notifications-toolbar">
                <div>
                    <span class="ticket-label"><?php echo t('nav_notifications'); ?></span>
                    <h2><?php echo t('notifications_all'); ?></h2>
                    <p><?php echo t('notifications_unread_count', ['count' => $unread_count]); ?></p>
                </div>

                <?php if ($unread_count > 0): ?>
                    <form method="POST" action="<?php echo htmlspecialchars(site_url('user/notifications.php'), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="mark_all_read">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn payment-secondary-btn"><?php echo t('notifications_mark_all'); ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="notifications-empty">
                    <h3><?php echo t('notifications_empty_title'); ?></h3>
                    <p><?php echo t('notifications_empty_text'); ?></p>
                    <a href="<?php echo htmlspecialchars(site_url('user/user_dashboard.php#dashboard-rooms'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('nav_book_now'); ?></a>
                </div>
            <?php else: ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <?php $notification = localize_notification_for_display($notification); ?>
                        <?php $notification_meta = get_notification_type_meta($notification['notification_type'] ?? ''); ?>
                        <article class="notification-card <?php echo (int)$notification['is_read'] === 0 ? 'is-unread' : 'is-read'; ?>">
                            <div class="notification-card-main">
                                <span class="smart-notification-badge <?php echo htmlspecialchars($notification_meta['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($notification_meta['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <div class="notification-card-head">
                                    <h3><?php echo htmlspecialchars($notification['display_title']); ?></h3>
                                    <?php if ((int)$notification['is_read'] === 0): ?>
                                        <span class="notification-dot"></span>
                                    <?php endif; ?>
                                </div>
                                <p><?php echo htmlspecialchars($notification['display_message']); ?></p>
                                <span class="notification-time"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></span>
                            </div>
                            <div class="notification-card-actions">
                                <?php $notification_action_url = site_user_notification_url($notification['action_url'] ?? ''); ?>
                                <?php if ($notification_action_url !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($notification_action_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-small"><?php echo t('common_view'); ?></a>
                                <?php endif; ?>
                                <?php if ((int)$notification['is_read'] === 0): ?>
                                    <form method="POST" action="<?php echo htmlspecialchars(site_url('user/notifications.php'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="btn btn-small payment-secondary-btn"><?php echo t('notifications_mark_read'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
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
