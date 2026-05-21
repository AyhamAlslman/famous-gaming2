<?php
require_once 'auth_check.php';
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    ensure_admin_notifications_table($conn);

    $action = $_POST['action'] ?? '';
    $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    $redirect_input = $_POST['redirect_url'] ?? 'notifications.php';
    $parsed_redirect = parse_url($redirect_input);
    $redirect_path = basename($parsed_redirect['path'] ?? 'notifications.php');
    $redirect_query = isset($parsed_redirect['query']) && $parsed_redirect['query'] !== '' ? '?' . $parsed_redirect['query'] : '';

    if (!preg_match('/^[A-Za-z0-9_.-]+\.php$/', $redirect_path)) {
        $redirect_path = 'notifications.php';
        $redirect_query = '';
    }

    if ($action === 'mark_read' && $notification_id > 0) {
        mark_admin_notification_read($conn, $notification_id);
    } elseif ($action === 'mark_all_read') {
        mark_all_admin_notifications_read($conn);
    }

    header('Location: ' . $redirect_path . $redirect_query);
    exit;
}

ensure_admin_notifications_table($conn);

$page_title = t('admin_notifications');
$active_page = 'notifications';
$notifications = get_all_admin_notifications($conn, 160);
$unread_count = count_unread_admin_notifications($conn);

include 'includes/header.php';
?>

<div class="content">
    <div class="container">
        <div class="page-header">
            <div>
                <h1><?php echo t('admin_notifications'); ?></h1>
                <p class="admin-page-subtitle"><?php echo t('admin_notifications_subtitle'); ?></p>
            </div>
            <?php if ($unread_count > 0): ?>
                <form method="POST" action="notifications.php">
                    <input type="hidden" name="action" value="mark_all_read">
                    <input type="hidden" name="redirect_url" value="notifications.php">
                    <?php echo admin_csrf_input(); ?>
                    <button type="submit" class="btn btn-secondary"><?php echo t('admin_mark_all_read'); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <div class="admin-notifications-page-list">
            <?php if (empty($notifications)): ?>
                <div class="no-data"><?php echo t('admin_no_notifications'); ?></div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <article class="admin-notification-page-card <?php echo (int)$notification['is_read'] === 0 ? 'is-unread' : 'is-read'; ?>">
                        <div class="admin-notification-page-body">
                            <div class="admin-notification-page-head">
                                <h2><?php echo htmlspecialchars($notification['title']); ?></h2>
                                <?php if ((int)$notification['is_read'] === 0): ?>
                                    <span class="admin-notification-dot"></span>
                                <?php endif; ?>
                            </div>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <span class="admin-notification-time"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></span>
                        </div>
                        <div class="admin-notification-page-actions">
                            <?php if (!empty($notification['action_url'])): ?>
                                <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" class="btn btn-small"><?php echo t('common_view'); ?></a>
                            <?php endif; ?>
                            <?php if ((int)$notification['is_read'] === 0): ?>
                                <form method="POST" action="notifications.php">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
                                    <input type="hidden" name="redirect_url" value="notifications.php">
                                    <?php echo admin_csrf_input(); ?>
                                    <button type="submit" class="btn btn-small btn-secondary"><?php echo t('admin_mark_read'); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
