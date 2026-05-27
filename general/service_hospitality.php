<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = t('services_hospitality_title') . ' - FAMOUS GAMING';
$items = [
    t('services_hospitality_item_1'),
    t('services_hospitality_item_2'),
    t('services_hospitality_item_3'),
    t('services_hospitality_item_4'),
    t('services_hospitality_item_5')
];
$menu_items = [];
$menu_result = mysqli_query($conn, "SELECT item_name, item_category, item_price, item_description FROM menu_items WHERE is_available = 1 AND item_category IN ('Drinks', 'Snacks') ORDER BY item_category, item_name");
if ($menu_result) {
    $menu_items = mysqli_fetch_all($menu_result, MYSQLI_ASSOC);
}

include dirname(__DIR__) . '/includes/header.php';
?>

<main class="service-detail-page">
    <section class="hero arena-page-hero service-detail-hero" style="--page-hero-image: url('<?php echo htmlspecialchars(site_url('images/service-food.png'), ENT_QUOTES, 'UTF-8'); ?>');">
        <div class="container">
            <span class="ticket-label"><?php echo t('services_hospitality_type'); ?></span>
            <h1><?php echo t('services_hospitality_title'); ?></h1>
            <p><?php echo t('services_hospitality_desc'); ?></p>
        </div>
    </section>

    <section class="content service-detail-content">
        <div class="container">
            <div class="service-detail-layout">
                <div class="service-detail-copy">
                    <span class="ticket-label"><?php echo t('services_hospitality_type'); ?></span>
                    <h2><?php echo t('services_hospitality_title'); ?></h2>
                    <p><?php echo t('services_hospitality_desc'); ?></p>
                    <div class="booking-ticket-actions">
                        <a href="<?php echo htmlspecialchars(site_url('user/room_booking.php#booking-form'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('nav_book_now'); ?></a>
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

            <div class="service-menu-section service-detail-menu-preview">
                <div class="home-section-heading">
                    <span class="ticket-label"><?php echo t('services_menu_title'); ?></span>
                    <h2><?php echo t('home_menu_preview_title'); ?></h2>
                    <p><?php echo t('service_menu_booking_only'); ?></p>
                </div>

                <?php if (!empty($menu_items)): ?>
                    <div class="service-menu-grid">
                        <?php foreach ($menu_items as $item): ?>
                            <div class="service-menu-item">
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
            </div>
        </div>
    </section>
</main>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
