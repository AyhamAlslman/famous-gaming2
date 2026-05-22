<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = t('services_events_title') . ' - FAMOUS GAMING';
$items = [
    t('services_events_item_1'),
    t('services_events_item_2'),
    t('services_events_item_3'),
    t('services_events_item_4'),
    t('services_events_item_5')
];

include dirname(__DIR__) . '/includes/header.php';
?>

<main class="service-detail-page">
    <section class="hero arena-page-hero service-detail-hero" style="--page-hero-image: url('<?php echo htmlspecialchars(site_url('images/home-game-collage.jpg'), ENT_QUOTES, 'UTF-8'); ?>');">
        <div class="container">
            <span class="ticket-label"><?php echo t('services_events_type'); ?></span>
            <h1><?php echo t('services_events_title'); ?></h1>
            <p><?php echo t('services_events_desc'); ?></p>
        </div>
    </section>

    <section class="content service-detail-content">
        <div class="container service-detail-layout">
            <div class="service-detail-copy">
                <span class="ticket-label"><?php echo t('services_events_type'); ?></span>
                <h2><?php echo t('services_events_title'); ?></h2>
                <p><?php echo t('services_events_desc'); ?></p>
                <div class="booking-ticket-actions">
                    <a href="<?php echo htmlspecialchars(site_url('user/booking.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('nav_book_now'); ?></a>
                    <a href="<?php echo htmlspecialchars(site_url('user/complaints.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn payment-secondary-btn"><?php echo t('footer_support'); ?></a>
                </div>
            </div>

            <div class="service-detail-list-card">
                <?php foreach ($items as $item): ?>
                    <div class="service-detail-list-item">
                        <span></span>
                        <strong><?php echo $item; ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
