<?php
$current_page = basename($_SERVER['PHP_SELF']);
$switch_to_en = site_switch_language_url('en');
$switch_to_ar = site_switch_language_url('ar');
$language_toggle_url = site_language() === 'ar' ? $switch_to_en : $switch_to_ar;
$language_toggle_label = site_language() === 'ar' ? t('lang_en') : t('lang_ar');
$site_user_name = $_SESSION['site_user_name'] ?? '';
$site_user_id = isset($_SESSION['site_user_id']) ? (int)$_SESSION['site_user_id'] : 0;
$site_user_points = isset($_SESSION['site_user_loyalty_points']) ? (int)$_SESSION['site_user_loyalty_points'] : 0;
$site_user_logged_in = $site_user_id > 0 && $site_user_name !== '';
if ($site_user_id > 0 && function_exists('get_current_site_user')) {
    $site_header_user = get_current_site_user($conn);

    if ($site_header_user) {
        $site_user_name = $site_header_user['full_name'];
        $site_user_points = (int)$site_header_user['loyalty_points'];
        $site_user_logged_in = true;
    } else {
        $site_user_logged_in = false;
        $site_user_id = 0;
        $site_user_name = '';
        $site_user_points = 0;
    }
}
$site_user_notification_count = $site_user_logged_in ? count_unread_site_notifications($conn, $site_user_id) : 0;
$site_user_initial = $site_user_name !== '' ? (function_exists('mb_substr') ? mb_substr($site_user_name, 0, 1, 'UTF-8') : substr($site_user_name, 0, 1)) : 'F';
$is_auth_page = in_array($current_page, ['login.php', 'register.php', 'forgot_password.php'], true);
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$is_direct_group_page = preg_match('#/(general|user)/#', $script_name) === 1;
$public_header_pages = ['index.php', 'about.php', 'contact.php', 'login.php', 'register.php', 'forgot_password.php'];
$site_header_is_user = $site_user_logged_in && !in_array($current_page, $public_header_pages, true);
$page_body_class = 'page-' . preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($current_page, PATHINFO_FILENAME));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(site_language(), ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars(site_direction(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'FAMOUS GAMING'; ?></title>
    <?php if ($is_direct_group_page): ?>
        <base href="<?php echo htmlspecialchars(site_url(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <!-- Bootstrap CSS (Local) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/bootstrap.css'), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=7.9">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/final-overrides.css'), ENT_QUOTES, 'UTF-8'); ?>?v=3.0">

    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(site_url('images/logo-mark.svg'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(site_url('images/favicon.png'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="<?php echo trim((site_is_rtl() ? 'rtl-layout ' : '') . ($site_header_is_user ? 'site-user-shell ' : 'site-public-shell ') . $page_body_class); ?>">
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand logo" href="<?php echo htmlspecialchars(site_url('general/index.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="logo-mark" aria-hidden="true">
                    <svg class="logo-controller" viewBox="0 0 84 84" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="controllerStroke" x1="17" y1="22" x2="66" y2="56" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="#86B7FF"/>
                                <stop offset="0.55" stop-color="#968FFF"/>
                                <stop offset="1" stop-color="#62D8FF"/>
                            </linearGradient>
                            <linearGradient id="controllerGlow" x1="22" y1="24" x2="61" y2="52" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="#A9C8FF"/>
                                <stop offset="0.5" stop-color="#B59FFF"/>
                                <stop offset="1" stop-color="#7FE4FF"/>
                            </linearGradient>
                        </defs>
                        <path class="controller-core" d="M25.173 24.5H58.827C63.198 24.5 67.134 27.079 68.881 31.085L71.989 38.213C74.87 44.817 70.029 52.25 62.823 52.25H58.998C56.116 52.25 53.441 50.767 51.918 48.328L49.997 45.251C49.149 43.892 47.661 43.065 46.059 43.065H37.941C36.339 43.065 34.851 43.892 34.003 45.251L32.082 48.328C30.559 50.767 27.884 52.25 25.002 52.25H21.177C13.971 52.25 9.13 44.817 12.011 38.213L15.119 31.085C16.866 27.079 20.802 24.5 25.173 24.5Z"/>
                        <path class="controller-highlight" d="M28 28.5H56"/>
                        <path class="controller-detail" d="M27.8 36.3H33.2V30.9H37V36.3H42.4V40.1H37V45.5H33.2V40.1H27.8V36.3Z"/>
                        <path class="controller-detail" d="M43.8 35.7H46.8"/>
                        <circle class="controller-orb" cx="51.8" cy="34.2" r="2.2"/>
                        <circle class="controller-orb" cx="57.8" cy="39.2" r="2.2"/>
                        <circle class="controller-orb" cx="45.8" cy="39.2" r="2.2"/>
                        <circle class="controller-orb" cx="51.8" cy="44.2" r="2.2"/>
                        <circle class="controller-thumb" cx="41.4" cy="39.8" r="3.25"/>
                    </svg>
                </span>
                <span class="logo-copy">
                    <span class="logo-word logo-word-famous">FAMOUS</span>
                    <span class="logo-word logo-word-gaming">GAMING</span>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto nav-menu">
                    <?php if ($site_header_is_user): ?>
                        <li class="nav-item"><a class="nav-link <?php echo $current_page === 'user_dashboard.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('user/user_dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_home'); ?></a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current_page === 'store.php' || $current_page === 'store_checkout.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_store'); ?></a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current_page === 'menu.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('user/menu.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('booking_step_menu'); ?></a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current_page === 'my_bookings.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_my_bookings'); ?></a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('general/index.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_home'); ?></a></li>
                        <li class="nav-item"><a class="nav-link <?php echo in_array($current_page, ['services.php', 'service_gaming.php', 'service_hospitality.php', 'service_events.php'], true) ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('general/services.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_services'); ?></a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current_page === 'contact.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('general/contact.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_contact'); ?></a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $current_page === 'about.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('general/about.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_about'); ?></a></li>
                    <?php endif; ?>
                </ul>
                <div class="nav-actions">
                    <?php if (!$site_header_is_user): ?>
                        <a class="nav-auth-link nav-auth-secondary <?php echo $current_page === 'login.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_login'); ?></a>
                        <a class="nav-auth-link nav-auth-primary <?php echo $current_page === 'register.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('general/register.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_register'); ?></a>
                        <a class="nav-language-link nav-language-toggle" href="<?php echo htmlspecialchars($language_toggle_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('language_label'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($language_toggle_label, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php else: ?>
                        <a class="nav-user-pill <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('user/profile.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('profile_menu_edit'), ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="nav-user-copy">
                                <strong class="nav-user-name"><?php echo htmlspecialchars($site_user_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </span>
                        </a>
                        <a class="nav-notification-link <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('user/notifications.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('nav_notifications'), ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="nav-notification-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M12 22a2.7 2.7 0 0 0 2.55-1.78h-5.1A2.7 2.7 0 0 0 12 22Zm7-5.2-1.5-1.72V10a5.52 5.52 0 0 0-4.16-5.35V3.8a1.34 1.34 0 1 0-2.68 0v.85A5.52 5.52 0 0 0 6.5 10v5.08L5 16.8V18h14v-1.2Z"/>
                                </svg>
                            </span>
                            <?php if ($site_user_notification_count > 0): ?>
                                <span class="nav-notification-count"><?php echo $site_user_notification_count > 99 ? '99+' : $site_user_notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-language-link nav-language-toggle" href="<?php echo htmlspecialchars($language_toggle_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('language_label'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($language_toggle_label, ENT_QUOTES, 'UTF-8'); ?></a>
                        <a class="nav-auth-link nav-auth-secondary nav-logout-link" href="<?php echo htmlspecialchars(site_url('user/logout.php'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm-message="<?php echo htmlspecialchars(t('logout_confirm'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm-title="<?php echo htmlspecialchars(t('modal_confirm_title'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_logout'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
