<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);

$page_title = t('home_menu_title') . ' - FAMOUS GAMING';
$shared_hero_image = site_url('images/shared-public-hero.jpg');
$menu_items = [];
$selected_category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$allowed_categories = ['Drinks', 'Snacks'];
$menu_category_counts = array_fill_keys($allowed_categories, 0);

if (!in_array($selected_category, $allowed_categories, true)) {
    $selected_category = '';
}

$query = "SELECT id, item_name, item_category, item_price, item_description
          FROM menu_items
          WHERE is_available = 1 AND item_category IN ('Drinks', 'Snacks')";

if ($selected_category !== '') {
    $query .= " AND item_category = ?";
}

$query .= " ORDER BY item_category, item_name";

if ($selected_category !== '') {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $selected_category);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $menu_items = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
} else {
    $result = mysqli_query($conn, $query);
    $menu_items = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

foreach ($menu_items as $menu_count_item) {
    if (isset($menu_category_counts[$menu_count_item['item_category']])) {
        $menu_category_counts[$menu_count_item['item_category']]++;
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero menu-hero" style="--page-hero-image: url('<?php echo htmlspecialchars($shared_hero_image, ENT_QUOTES, 'UTF-8'); ?>');">
    <div class="container">
        <div class="store-hero-shell">
            <div class="store-hero-copy store-hero-copy-centered">
                <span class="store-eyebrow"><?php echo t('booking_step_menu'); ?></span>
                <h1><?php echo t('home_menu_title'); ?></h1>
                <p><?php echo t('service_menu_booking_only'); ?></p>
            </div>
        </div>
    </div>
</section>

<section class="content menu-content">
    <div class="container">
        <div class="store-toolbar menu-toolbar">
            <div>
                <h2 class="section-title store-section-title"><?php echo t('home_menu_preview_title'); ?></h2>
                <p class="store-toolbar-text"><?php echo t('home_menu_preview_text'); ?></p>
                <div class="menu-category-summary">
                    <?php foreach ($allowed_categories as $category): ?>
                        <span>
                            <strong><?php echo (int)($menu_category_counts[$category] ?? 0); ?></strong>
                            <?php echo htmlspecialchars(translated_menu_category_label($category)); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="store-filter-chips">
                <a href="<?php echo htmlspecialchars(site_url('user/menu.php'), ENT_QUOTES, 'UTF-8'); ?>" class="store-filter-chip <?php echo $selected_category === '' ? 'active' : ''; ?>"><?php echo t('store_all_products'); ?></a>
                <?php foreach ($allowed_categories as $category): ?>
                    <a href="<?php echo htmlspecialchars(site_url('user/menu.php?category=' . urlencode($category)), ENT_QUOTES, 'UTF-8'); ?>" class="store-filter-chip <?php echo $selected_category === $category ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars(translated_menu_category_label($category)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($menu_items)): ?>
            <div class="store-empty-state">
                <h3><?php echo t('booking_addons_empty'); ?></h3>
                <p><?php echo t('service_menu_booking_only'); ?></p>
            </div>
        <?php else: ?>
            <div class="menu-preview-grid">
                <?php foreach ($menu_items as $item): ?>
                    <article class="menu-preview-card" data-menu-category="<?php echo htmlspecialchars($item['item_category'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="menu-preview-visual">
                            <img src="<?php echo htmlspecialchars(site_url('images/service-food-optimized.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                            <span class="menu-preview-icon"><?php echo strtoupper(substr($item['item_category'], 0, 1)); ?></span>
                        </div>
                        <div class="menu-preview-copy">
                            <span><?php echo htmlspecialchars(translated_menu_category_label($item['item_category'])); ?></span>
                            <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                            <?php if (!empty($item['item_description'])): ?>
                                <p><?php echo htmlspecialchars($item['item_description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <strong><?php echo number_format((float)$item['item_price'], 2); ?> JOD</strong>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="menu-booking-cta">
                <a href="<?php echo htmlspecialchars(site_url('user/user_dashboard.php#dashboard-rooms'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('nav_book_now'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
