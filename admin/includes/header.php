<?php
$admin_notification_unread_count = count_unread_admin_notifications($conn);
$admin_notifications = get_recent_admin_notifications($conn, 6);
$current_admin_path = basename($_SERVER['PHP_SELF']);
$current_admin_query = $_SERVER['QUERY_STRING'] ?? '';
$admin_notification_redirect = $current_admin_path . ($current_admin_query !== '' ? '?' . $current_admin_query : '');
$switch_to_en = site_switch_language_url('en');
$switch_to_ar = site_switch_language_url('ar');
$admin_language_target_url = site_language() === 'ar' ? $switch_to_en : $switch_to_ar;
$admin_language_target_label = site_language() === 'ar' ? t('lang_en') : t('lang_ar');
$admin_display_name = $_SESSION['admin_full_name'] ?? ($_SESSION['admin_username'] ?? t('admin_brand'));
$admin_display_role = $_SESSION['admin_role'] ?? '';

$admin_nav_items = [
    [
        'url' => 'dashboard.php',
        'label' => t('admin_nav_dashboard'),
        'active' => $current_admin_path === 'dashboard.php'
    ],
    [
        'url' => 'bookings_full_crud.php',
        'label' => t('admin_nav_bookings'),
        'active' => in_array($current_admin_path, ['bookings_full_crud.php', 'booking_details.php'], true)
    ],
    [
        'url' => 'customer_tickets.php',
        'label' => t('admin_nav_customer_tickets'),
        'active' => $current_admin_path === 'customer_tickets.php'
    ],
    [
        'url' => 'rooms_full_crud.php',
        'label' => t('admin_nav_rooms'),
        'active' => $current_admin_path === 'rooms_full_crud.php'
    ]
];

if (function_exists('isAdmin') && isAdmin()) {
    $admin_nav_items[] = [
        'url' => 'menu_items.php',
        'label' => t('admin_nav_menu_items'),
        'active' => $current_admin_path === 'menu_items.php'
    ];
    $admin_nav_items[] = [
        'url' => 'store_products.php',
        'label' => t('admin_nav_store_products'),
        'active' => $current_admin_path === 'store_products.php'
    ];
    $admin_nav_items[] = [
        'url' => 'store_orders.php',
        'label' => t('admin_nav_store_orders'),
        'active' => $current_admin_path === 'store_orders.php'
    ];
    $admin_nav_items[] = [
        'url' => 'employees.php',
        'label' => t('admin_nav_employees'),
        'active' => $current_admin_path === 'employees.php'
    ];
}

$admin_nav_items[] = [
    'url' => 'complaints_full_crud.php',
    'label' => t('admin_nav_complaints'),
    'active' => $current_admin_path === 'complaints_full_crud.php'
];
$admin_nav_items[] = [
    'url' => 'notifications.php',
    'label' => t('admin_notifications'),
    'active' => $current_admin_path === 'notifications.php'
];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(site_language(), ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars(site_direction(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo t('admin_panel_title'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('admin/css/admin.css'), ENT_QUOTES, 'UTF-8'); ?>?v=3.7">
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(site_url('images/favicon.png'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="admin-body <?php echo site_is_rtl() ? 'admin-rtl' : 'admin-ltr'; ?>">
    <div class="admin-shell">
        <aside class="admin-sidebar" id="adminSidebar">
            <a class="logo admin-sidebar-brand" href="dashboard.php" aria-label="<?php echo htmlspecialchars(t('admin_brand'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="admin-brand-mark" aria-hidden="true">FG</span>
                <span>
                    <strong><?php echo t('brand_name'); ?></strong>
                    <small><?php echo t('admin_panel_title'); ?></small>
                </span>
            </a>

            <nav class="admin-sidebar-nav" aria-label="<?php echo htmlspecialchars(t('admin_toggle_navigation'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($admin_nav_items as $nav_item): ?>
                    <a href="<?php echo htmlspecialchars($nav_item['url'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $nav_item['active'] ? 'class="active"' : ''; ?>>
                        <?php echo $nav_item['label']; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-user-chip">
                    <span><?php echo htmlspecialchars($admin_display_name, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($admin_display_role !== ''): ?>
                        <strong><?php echo htmlspecialchars(t('admin_role_' . strtolower($admin_display_role), [], ucfirst($admin_display_role)), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php endif; ?>
                </div>
                <a class="admin-logout-button" href="<?php echo htmlspecialchars(site_url('general/logout.php'), ENT_QUOTES, 'UTF-8'); ?>" data-admin-confirm-message="<?php echo htmlspecialchars(t('admin_logout_confirm'), ENT_QUOTES, 'UTF-8'); ?>" data-admin-confirm-title="<?php echo htmlspecialchars(t('modal_confirm_title'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('admin_nav_logout'); ?></a>
            </div>
        </aside>

        <div class="admin-sidebar-backdrop" data-admin-nav-toggle hidden></div>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar-inner">
                    <button type="button" class="admin-sidebar-toggle" data-admin-nav-toggle aria-expanded="false" aria-controls="adminSidebar" aria-label="<?php echo htmlspecialchars(t('admin_toggle_navigation'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>

                    <div class="admin-topbar-title">
                        <strong><?php echo isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : t('admin_panel_title'); ?></strong>
                        <span><?php echo t('admin_brand'); ?></span>
                    </div>

                    <div class="admin-navbar-tools">
                        <div class="admin-notification-nav">
                            <div class="admin-notification-dropdown">
                                <button type="button" class="admin-notification-trigger" data-admin-notification-toggle aria-expanded="false" aria-controls="adminNotificationMenu" aria-label="<?php echo htmlspecialchars(t('admin_notifications'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="admin-notification-icon">&#128276;</span>
                                    <?php if ($admin_notification_unread_count > 0): ?>
                                        <span class="admin-notification-count"><?php echo $admin_notification_unread_count > 99 ? '99+' : $admin_notification_unread_count; ?></span>
                                    <?php endif; ?>
                                </button>
                                <div class="admin-notification-menu" id="adminNotificationMenu" hidden>
                                    <div class="admin-notification-menu-header">
                                        <div>
                                            <strong><?php echo t('admin_notifications'); ?></strong>
                                            <span><?php echo t('admin_unread', ['count' => $admin_notification_unread_count]); ?></span>
                                        </div>
                                        <a href="notifications.php" class="admin-notification-link-btn"><?php echo t('admin_view_all_notifications'); ?></a>
                                        <?php if ($admin_notification_unread_count > 0): ?>
                                            <form method="POST" action="notifications.php" class="admin-notification-inline-form">
                                                <input type="hidden" name="action" value="mark_all_read">
                                                <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($admin_notification_redirect, ENT_QUOTES, 'UTF-8'); ?>">
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
                                                            <strong><?php echo htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                            <?php if ((int)$notification['is_read'] === 0): ?>
                                                                <span class="admin-notification-dot"></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p><?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                        <span class="admin-notification-time"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></span>
                                                    </div>
                                                    <div class="admin-notification-actions">
                                                        <?php if (!empty($notification['action_url'])): ?>
                                                            <a href="<?php echo htmlspecialchars($notification['action_url'], ENT_QUOTES, 'UTF-8'); ?>" class="admin-notification-link-btn"><?php echo t('common_view'); ?></a>
                                                        <?php endif; ?>
                                                        <?php if ((int)$notification['is_read'] === 0): ?>
                                                            <form method="POST" action="notifications.php" class="admin-notification-inline-form">
                                                                <input type="hidden" name="action" value="mark_read">
                                                                <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
                                                                <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($admin_notification_redirect, ENT_QUOTES, 'UTF-8'); ?>">
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
                            </div>
                        </div>

                        <div class="admin-language-switcher">
                            <a class="admin-language-link active" href="<?php echo htmlspecialchars($admin_language_target_url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($admin_language_target_label, ENT_QUOTES, 'UTF-8'); ?></a>
                        </div>
                    </div>
                </div>
            </header>
