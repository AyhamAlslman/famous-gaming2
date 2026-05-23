<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = t('services_page_title');
$services = [
    [
        'url' => site_url('general/service_gaming.php'),
        'image' => site_url('images/service-gaming.png'),
        'type' => t('services_gaming_type'),
        'title' => t('services_gaming_title'),
        'description' => t('services_gaming_desc')
    ],
    [
        'url' => site_url('general/service_hospitality.php'),
        'image' => site_url('images/service-food.png'),
        'type' => t('services_hospitality_type'),
        'title' => t('services_hospitality_title'),
        'description' => t('services_hospitality_desc')
    ],
    [
        'url' => site_url('general/service_events.php'),
        'image' => site_url('images/service-events.png'),
        'class' => 'service-hub-card-featured',
        'type' => t('services_events_type'),
        'title' => t('services_events_title'),
        'description' => t('services_events_desc')
    ]
];

include dirname(__DIR__) . '/includes/header.php';
?>

<main class="service-hub-page">
    <section class="hero arena-page-hero service-hub-hero" style="--page-hero-image: url('<?php echo htmlspecialchars(site_url('images/home-neon-sign.jpg'), ENT_QUOTES, 'UTF-8'); ?>');">
        <div class="container">
            <span class="ticket-label"><?php echo t('nav_services'); ?></span>
            <h1><?php echo t('services_hero_title'); ?></h1>
            <p><?php echo t('services_hero_text'); ?></p>
        </div>
    </section>

    <section class="content service-hub-content">
        <div class="container">
            <div class="home-section-heading service-hub-heading">
                <h2><?php echo t('services_section_title'); ?></h2>
                <p><?php echo t('services_menu_text'); ?></p>
            </div>

            <div class="service-hub-grid">
                <?php foreach ($services as $service): ?>
                    <a class="service-hub-card <?php echo htmlspecialchars($service['class'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($service['url'], ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars($service['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div>
                            <span><?php echo $service['type']; ?></span>
                            <h3><?php echo $service['title']; ?></h3>
                            <p><?php echo $service['description']; ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="service-hub-cta">
                <div>
                    <span class="ticket-label">FAMOUS GAMING</span>
                    <h2><?php echo t('services_cta'); ?></h2>
                    <p><?php echo t('home_book_card_text'); ?></p>
                </div>
                <a href="<?php echo htmlspecialchars(site_url('user/booking.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('nav_book_now'); ?></a>
            </div>
        </div>
    </section>
</main>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
