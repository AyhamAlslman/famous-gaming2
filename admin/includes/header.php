<?php
ensure_admin_notifications_table($conn);
$admin_notification_unread_count = count_unread_admin_notifications($conn);
$admin_notifications = get_recent_admin_notifications($conn, 6);
$current_admin_path = basename($_SERVER['PHP_SELF']);
$current_admin_query = $_SERVER['QUERY_STRING'] ?? '';
$admin_notification_redirect = $current_admin_path . ($current_admin_query !== '' ? '?' . $current_admin_query : '');
$switch_to_en = site_switch_language_url('en');
$switch_to_ar = site_switch_language_url('ar');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(site_language(), ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars(site_direction(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo t('admin_panel_title'); ?></title>
    <link rel="stylesheet" href="css/admin.css?v=2.1">

    <link rel="icon" type="image/x-icon" href="../images/favicon.png">
</head>
<body class="admin-body <?php echo site_is_rtl() ? 'admin-rtl' : ''; ?>">
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <h2><?php echo t('admin_brand'); ?></h2>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'class="active"' : ''; ?>><?php echo t('admin_nav_dashboard'); ?></a></li>
                <li><a href="bookings_full_crud.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'bookings_full_crud.php' || basename($_SERVER['PHP_SELF']) == 'booking_details.php') ? 'class="active"' : ''; ?>><?php echo t('admin_nav_bookings'); ?></a></li>
                <li><a href="customer_tickets.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'customer_tickets.php') ? 'class="active"' : ''; ?>><?php echo t('admin_nav_customer_tickets'); ?></a></li>
                <li><a href="rooms_full_crud.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'rooms_full_crud.php') ? 'class="active"' : ''; ?>><?php echo t('admin_nav_rooms'); ?></a></li>
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                <li><a href="menu_items.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'menu_items.php') ? 'class="active"' : ''; ?>><?php echo t('admin_nav_menu_items'); ?></a></li>
                <li><a href="store_products.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'store_products.php') ? 'class="active"' : ''; ?>><?php echo t('admin_nav_store_products'); ?></a></li>
                <li><a href="employees.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'employees.php') ? 'class="active"' : ''; ?>><?php echo t('admin_nav_employees'); ?></a></li>
                <?php endif; ?>
                <li><a href="complaints_full_crud.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'complaints_full_crud.php') ? 'class="active"' : ''; ?>><?php echo t('admin_nav_complaints'); ?></a></li>
                <li><a href="logout.php"><?php echo t('admin_nav_logout'); ?></a></li>
            </ul>
            <div class="admin-navbar-tools">
                <div class="admin-notification-nav">
                    <details class="admin-notification-dropdown">
                        <summary class="admin-notification-trigger" aria-label="<?php echo htmlspecialchars(t('admin_notifications'), ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="admin-notification-icon">&#128276;</span>
                            <?php if ($admin_notification_unread_count > 0): ?>
                                <span class="admin-notification-count"><?php echo $admin_notification_unread_count > 99 ? '99+' : $admin_notification_unread_count; ?></span>
                            <?php endif; ?>
                        </summary>
                        <div class="admin-notification-menu">
                            <div class="admin-notification-menu-header">
                                <div>
                                    <strong><?php echo t('admin_notifications'); ?></strong>
                                    <span><?php echo t('admin_unread', ['count' => $admin_notification_unread_count]); ?></span>
                                </div>
                                <?php if ($admin_notification_unread_count > 0): ?>
                                    <form method="POST" action="notifications.php" class="admin-notification-inline-form">
                                        <input type="hidden" name="action" value="mark_all_read">
                                        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($admin_notification_redirect); ?>">
                                        <?php echo admin_csrf_input(); ?>
                                        <button type="submit" class="admin-notification-link-btn"><?php echo t('admin_mark_all_read'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <?php if (empty($admin_notifications)): ?>
                                <div class="admin-notification-empty"><?php echo t('admin_no_notifications'); ?></div>
                            <?php else: ?>
                                <div class="admin-notification-list">
                                    <?php foreach ($admin_notifications as $notification): ?>
                                        <div class="admin-notification-card <?php echo (int)$notification['is_read'] === 0 ? 'is-unread' : 'is-read'; ?>">
                                            <div class="admin-notification-card-body">
                                                <div class="admin-notification-card-head">
                                                    <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                                    <?php if ((int)$notification['is_read'] === 0): ?>
                                                        <span class="admin-notification-dot"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <span class="admin-notification-time"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></span>
                                            </div>
                                            <div class="admin-notification-actions">
                                                <?php if (!empty($notification['action_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" class="admin-notification-link-btn"><?php echo t('common_view'); ?></a>
                                                <?php endif; ?>
                                                <?php if ((int)$notification['is_read'] === 0): ?>
                                                    <form method="POST" action="notifications.php" class="admin-notification-inline-form">
                                                        <input type="hidden" name="action" value="mark_read">
                                                        <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
                                                        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($admin_notification_redirect); ?>">
                                                        <?php echo admin_csrf_input(); ?>
                                                        <button type="submit" class="admin-notification-link-btn"><?php echo t('admin_mark_read'); ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
                <div class="admin-language-switcher">
                    <a class="admin-language-link <?php echo site_language() === 'en' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($switch_to_en, ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('lang_en'); ?></a>
                    <span class="admin-language-divider">/</span>
                    <a class="admin-language-link <?php echo site_language() === 'ar' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($switch_to_ar, ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('lang_ar'); ?></a>
                </div>
            </div>
        </div>
    </nav>
