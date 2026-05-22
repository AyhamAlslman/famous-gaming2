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
$home_events_image = site_url('images/home-neon-sign.jpg');

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
                <a href="<?php echo htmlspecialchars(site_url('general/services.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn index-hero-secondary-btn"><?php echo t('nav_services'); ?></a>
            </div>
            <div class="index-hero-badges" aria-hidden="true">
                <span>PS5</span>
                <span><?php echo t('nav_services'); ?></span>
                <span><?php echo t('services_hospitality_type'); ?></span>
            </div>
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
            <a href="<?php echo htmlspecialchars(site_url('general/service_gaming.php'), ENT_QUOTES, 'UTF-8'); ?>" class="home-service-card home-service-card-large">
                <img src="<?php echo htmlspecialchars($home_booking_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('home_book_card_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <span><?php echo t('services_gaming_type'); ?></span>
                    <h3><?php echo t('services_gaming_title'); ?></h3>
                    <p><?php echo t('services_gaming_desc'); ?></p>
                </div>
            </a>

            <a href="<?php echo htmlspecialchars(site_url('general/service_hospitality.php'), ENT_QUOTES, 'UTF-8'); ?>" class="home-service-card home-service-card-large">
                <img src="<?php echo htmlspecialchars($home_food_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('home_snacks_card_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <span><?php echo t('services_hospitality_type'); ?></span>
                    <h3><?php echo t('services_hospitality_title'); ?></h3>
                    <p><?php echo t('services_hospitality_desc'); ?></p>
                </div>
            </a>

            <a href="<?php echo htmlspecialchars(site_url('general/service_events.php'), ENT_QUOTES, 'UTF-8'); ?>" class="home-service-card home-service-card-large">
                <img src="<?php echo htmlspecialchars($home_events_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('services_events_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <span><?php echo t('services_events_type'); ?></span>
                    <h3><?php echo t('services_events_title'); ?></h3>
                    <p><?php echo t('services_events_desc'); ?></p>
                </div>
            </a>
        </div>
    </div>
</section>
</main>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
