<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = t('home_page_title');
$business_status = get_current_business_status($conn);
$booking_login_link = site_url('general/login.php?redirect=' . urlencode('user/booking.php'));
$is_logged_in = !empty($_SESSION['site_user_id']);
$home_hero_image = site_url('images/home-hero-2026.png');
$home_booking_image = site_url('images/home-game-collage.jpg');
$home_food_image = site_url('images/service-food.png');
$home_store_image = site_url('images/store.jpg');

$menu_items = [];
$menu_result = mysqli_query($conn, "SELECT item_name, item_category, item_price, item_description FROM menu_items WHERE is_available = 1 AND item_category IN ('Drinks', 'Snacks') ORDER BY item_category, item_name");
if ($menu_result) {
    $menu_items = mysqli_fetch_all($menu_result, MYSQLI_ASSOC);
}

include dirname(__DIR__) . '/includes/header.php';
?>

<main class="index-page-shell">
<section class="index-hero-redesign" style="--page-hero-image: url('<?php echo htmlspecialchars($home_hero_image, ENT_QUOTES, 'UTF-8'); ?>');" aria-label="<?php echo htmlspecialchars(t('home_hero_title'), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="container index-hero-layout">
        <div class="index-hero-copy">
            <span class="home-status-pill status-<?php echo htmlspecialchars($business_status['state']); ?>">
                <?php echo htmlspecialchars($business_status['label']); ?>
            </span>
            <span class="index-hero-kicker">PS5 ARENA PLAY</span>
            <h1><?php echo t('home_hero_title'); ?></h1>
            <p><?php echo t('home_hero_line_1'); ?></p>
            <p class="index-hero-support"><?php echo t('home_hero_line_2'); ?></p>
            <div class="index-hero-actions">
                <a href="<?php echo htmlspecialchars($is_logged_in ? site_url('user/booking.php') : $booking_login_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('home_cta'); ?></a>
                <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn index-hero-secondary-btn"><?php echo t('nav_store'); ?></a>
            </div>
            <div class="index-hero-badges" aria-hidden="true">
                <span>PS5</span>
                <span><?php echo t('nav_store'); ?></span>
                <span><?php echo t('services_hospitality_type'); ?></span>
            </div>
        </div>
        <div class="index-hero-media" aria-hidden="true">
            <img src="<?php echo htmlspecialchars($home_hero_image, ENT_QUOTES, 'UTF-8'); ?>" alt="">
        </div>
    </div>
</section>

<section class="index-experience-section">
    <div class="container">
        <div class="home-intro-panel">
            <span class="ticket-label">FAMOUS GAMING 2026</span>
            <h2><?php echo t('home_intro_title'); ?></h2>
            <p><?php echo t('home_intro_text'); ?></p>
        </div>

        <div class="home-section-heading">
            <h2><?php echo t('home_services_title'); ?></h2>
        </div>

        <div class="home-service-grid home-service-grid-three">
            <a href="<?php echo htmlspecialchars($is_logged_in ? site_url('user/booking.php') : $booking_login_link, ENT_QUOTES, 'UTF-8'); ?>" class="home-service-card home-service-card-large">
                <img src="<?php echo htmlspecialchars($home_booking_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('home_book_card_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <span><?php echo t('nav_book_now'); ?></span>
                    <h3><?php echo t('home_book_card_title'); ?></h3>
                    <p><?php echo t('home_book_card_text'); ?></p>
                </div>
            </a>

            <a href="#home-menu-preview" class="home-service-card home-service-card-large" data-home-menu-trigger>
                <img src="<?php echo htmlspecialchars($home_food_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('home_snacks_card_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <span><?php echo t('services_hospitality_type'); ?></span>
                    <h3><?php echo t('home_snacks_card_title'); ?></h3>
                    <p><?php echo t('home_snacks_card_text'); ?></p>
                </div>
            </a>

            <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>" class="home-service-card home-service-card-large">
                <img src="<?php echo htmlspecialchars($home_store_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('home_store_card_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <span><?php echo t('nav_store'); ?></span>
                    <h3><?php echo t('home_store_card_title'); ?></h3>
                    <p><?php echo t('home_store_card_text'); ?></p>
                </div>
            </a>
        </div>

        <div class="home-menu-preview home-menu-preview-panel" id="home-menu-preview" data-home-menu-panel hidden>
            <div class="home-section-heading">
                <span class="ticket-label"><?php echo t('home_menu_title'); ?></span>
                <h2><?php echo t('home_menu_preview_title'); ?></h2>
                <p><?php echo t('home_menu_preview_text'); ?></p>
            </div>

            <?php if (!empty($menu_items)): ?>
                <div class="home-menu-grid">
                    <?php foreach ($menu_items as $item): ?>
                        <div class="home-menu-item">
                            <span><?php echo htmlspecialchars(translated_menu_category_label($item['item_category'])); ?></span>
                            <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                            <p><?php echo htmlspecialchars($item['item_description']); ?></p>
                            <strong><?php echo number_format((float)$item['item_price'], 2); ?> JOD</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="home-no-rooms"><?php echo t('booking_addons_empty'); ?></div>
            <?php endif; ?>

            <?php if (!$is_logged_in): ?>
                <div class="home-menu-login-note">
                    <span><?php echo t('auth_required_to_order'); ?></span>
                    <a href="<?php echo htmlspecialchars($booking_login_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-small"><?php echo t('nav_login'); ?></a>
                    <a href="<?php echo htmlspecialchars(site_url('general/register.php?redirect=' . urlencode('user/booking.php')), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-small home-secondary-btn"><?php echo t('nav_register'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
</main>

<script>
    (function () {
        const menuTrigger = document.querySelector('[data-home-menu-trigger]');
        const menuPanel = document.querySelector('[data-home-menu-panel]');

        if (!menuTrigger || !menuPanel) {
            return;
        }

        function openMenuPanel(scrollIntoView) {
            menuPanel.hidden = false;
            menuPanel.classList.add('is-visible');

            if (scrollIntoView) {
                menuPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        menuTrigger.addEventListener('click', function (event) {
            event.preventDefault();
            openMenuPanel(true);
        });

        if (window.location.hash === '#home-menu-preview') {
            openMenuPanel(false);
        }
    }());
</script>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
