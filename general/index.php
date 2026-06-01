<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = t('home_page_title');
$business_status = get_current_business_status($conn);
$booking_target = 'user/room_booking.php#booking-form';
$booking_url = site_url($booking_target);
$booking_login_link = site_url('general/login.php?redirect=' . urlencode($booking_target));
$is_logged_in = !empty($_SESSION['site_user_id']);
$home_hero_image = site_url('images/home-hero-2026-optimized.jpg');
$home_booking_image = site_url('images/home-game-collage.jpg');
$home_food_image = site_url('images/service-food-optimized.jpg');
$home_store_image = site_url('images/store.jpg');
$home_rooms = [];
$home_rooms_result = mysqli_query($conn, "SELECT
        r.id,
        r.room_name,
        r.room_type,
        r.price_per_hour,
        r.services,
        r.description,
        r.image_path,
        CASE
            WHEN r.status = 'Busy' THEN 'Busy'
            WHEN EXISTS (
                SELECT 1
                FROM bookings active_booking
                WHERE active_booking.room_id = r.id
                  AND active_booking.status IN ('Pending', 'Confirmed')
                  AND active_booking.booking_date = CURDATE()
                  AND CURTIME() >= active_booking.start_time
                  AND CURTIME() < ADDTIME(active_booking.start_time, SEC_TO_TIME(active_booking.hours * 3600))
            ) THEN 'Busy'
            ELSE 'Available'
        END AS current_status
    FROM rooms r
    ORDER BY FIELD(current_status, 'Available', 'Busy'), r.room_name ASC");
if ($home_rooms_result) {
    $home_rooms = mysqli_fetch_all($home_rooms_result, MYSQLI_ASSOC);
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
                <button type="button" class="btn index-room-reveal-btn" data-home-rooms-toggle aria-expanded="false" aria-controls="rooms">
                    <?php echo t('home_cta'); ?>
                </button>
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
            <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>" class="home-service-card home-service-card-large">
                <img src="<?php echo htmlspecialchars($home_store_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('home_store_card_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="index-service-card-copy">
                    <span><?php echo t('store_eyebrow'); ?></span>
                    <h3><?php echo t('home_store_card_title'); ?></h3>
                    <p><?php echo t('home_store_card_text'); ?></p>
                </div>
            </a>

            <button type="button" class="home-service-card home-service-card-large index-service-room-trigger" data-home-rooms-toggle aria-expanded="false" aria-controls="rooms">
                <img src="<?php echo htmlspecialchars($home_booking_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('home_book_card_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="index-service-card-copy">
                    <span><?php echo t('home_rooms_title'); ?></span>
                    <h3><?php echo t('home_book_card_title'); ?></h3>
                    <p><?php echo t('home_book_card_text'); ?></p>
                </div>
            </button>

            <a href="<?php echo htmlspecialchars(site_url('general/service_hospitality.php'), ENT_QUOTES, 'UTF-8'); ?>" class="home-service-card home-service-card-large">
                <img src="<?php echo htmlspecialchars($home_food_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('home_snacks_card_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="index-service-card-copy">
                    <span><?php echo t('home_menu_title'); ?></span>
                    <h3><?php echo t('home_snacks_card_title'); ?></h3>
                    <p><?php echo t('home_snacks_card_text'); ?></p>
                </div>
            </a>
        </div>
    </div>
</section>

<?php if (!empty($home_rooms)): ?>
    <section class="index-rooms-section" id="rooms" data-home-rooms-section>
        <div class="container">
            <div class="home-section-heading">
                <span class="ticket-label"><?php echo t('home_rooms_title'); ?></span>
                <h2><?php echo t('home_rooms_teaser_title'); ?></h2>
                <p><?php echo t('home_rooms_teaser_text'); ?></p>
                <?php if (!$is_logged_in): ?>
                    <p class="index-login-required-note"><?php echo t('home_login_required_to_book'); ?></p>
                <?php endif; ?>
            </div>

            <div class="rooms-grid index-rooms-grid">
                <?php foreach ($home_rooms as $room): ?>
                    <?php
                    $room_status = $room['current_status'] ?? $room['status'];
                    $room_status_key = strtolower((string)$room_status);
                    $is_available = $room_status === 'Available';
                    $room_image = site_asset_url($room['image_path'] ?? '', 'images/home-hero-background-optimized.jpg');
                    $room_booking_target = 'user/room_booking.php?room_id=' . (int)$room['id'] . '#booking-form';
                    $room_booking_url = $is_logged_in
                        ? site_url($room_booking_target)
                        : site_url('general/login.php?redirect=' . urlencode($room_booking_target));
                    ?>
                    <article class="room-card room-card-status-<?php echo $is_available ? 'available' : 'busy'; ?>">
                        <div class="room-image">
                            <img src="<?php echo htmlspecialchars($room_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($room['room_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <h3><?php echo htmlspecialchars($room['room_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="room-type"><?php echo htmlspecialchars($room['room_type'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="index-room-description"><?php echo htmlspecialchars($room['description'] ?: ($room['services'] ?: t('home_room_devices_default')), ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="room-card-actions">
                            <span class="ticket-status status-<?php echo htmlspecialchars($room_status_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('status_' . $room_status_key, [], $room_status), ENT_QUOTES, 'UTF-8'); ?></span>
                            <strong class="room-price"><?php echo number_format((float)$room['price_per_hour'], 2); ?> <?php echo t('home_room_price_suffix'); ?></strong>
                            <?php if ($is_available): ?>
                                <a href="<?php echo htmlspecialchars($room_booking_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-small room-book-btn"><?php echo t('home_book_room'); ?></a>
                            <?php else: ?>
                                <span class="btn btn-small room-book-btn room-book-btn-disabled"><?php echo htmlspecialchars(t('status_' . $room_status_key, [], $room_status), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roomsSection = document.querySelector('[data-home-rooms-section]');
    const toggles = Array.from(document.querySelectorAll('[data-home-rooms-toggle]'));

    if (!roomsSection) {
        return;
    }

    function showRoomsSection() {
        roomsSection.hidden = false;
        roomsSection.classList.remove('is-collapsed');
        roomsSection.classList.add('is-expanded');
    }

    showRoomsSection();

    if (toggles.length > 0) {
        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                showRoomsSection();
                roomsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    if ('IntersectionObserver' in window && toggles.length > 0) {
        const roomsObserver = new IntersectionObserver(function (entries) {
            const isVisible = entries.some(function (entry) {
                return entry.isIntersecting;
            });

            toggles.forEach(function (item) {
                item.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
            });
        }, {
            threshold: 0.12
        });

        roomsObserver.observe(roomsSection);
    }
});
</script>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
