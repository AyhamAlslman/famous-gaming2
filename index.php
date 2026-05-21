<?php
include 'includes/config.php';
$page_title = t('home_page_title');
include 'includes/header.php';
?>
   Alooooooooooooooooooooooooooo 
<section class="hero" style="background-image: linear-gradient(120deg, rgba(3, 8, 18, 0.94) 0%, rgba(3, 8, 18, 0.72) 45%, rgba(4, 8, 18, 0.94) 100%), url('images/home-hero-background.png');">
    <div class="container">
        <h1><?php echo t('home_hero_title'); ?></h1>
        <p><?php echo t('home_hero_line_1'); ?></p>
        <p><?php echo t('home_hero_line_2'); ?></p>
    </div>
</section>
<section class="content">
    <div class="container">
        <h2 class="section-title"><?php echo t('home_rooms_title'); ?></h2>

        <div class="row g-4 rooms-grid">
            <?php
            $rooms = mysqli_query($conn, "SELECT * FROM rooms ORDER BY id ASC");

            while ($room = mysqli_fetch_assoc($rooms)):
                $status_class = ($room['status'] == 'Available') ? 'status-available' : 'status-busy';
                $is_available = ($room['status'] == 'Available');
                $card_status_class = $is_available ? 'room-card-status-available' : 'room-card-status-busy';
            ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="room-card <?= $card_status_class ?> h-100">
                        <?php if (!empty($room['image_path']) && file_exists($room['image_path'])): ?>
                            <div class="room-image">
                                <img src="<?= htmlspecialchars($room['image_path']) ?>"
                                     alt="<?= htmlspecialchars($room['room_name']) ?>" class="img-fluid">
                            </div>
                        <?php else: ?>
                            <div class="room-image room-image-placeholder">ðŸŽ®</div>
                        <?php endif; ?>

                        <h3><?= htmlspecialchars($room['room_name']) ?></h3>
                        <div class="room-type"><?= htmlspecialchars($room['room_type']) ?></div>
                        <div class="room-price"><?= number_format($room['price_per_hour'], 2) ?> <?php echo t('home_room_price_suffix'); ?></div>
                        <div class="room-card-actions">
                            <?php if ($is_available): ?>
                                <a href="booking.php?room_id=<?= (int)$room['id'] ?>#booking-form" class="btn btn-small room-book-btn">
                                    <?php echo t('home_book_room'); ?>
                                </a>
                            <?php else: ?>
                                <span class="btn btn-small room-book-btn room-book-btn-disabled" aria-disabled="true">
                                    <?php echo t('home_booking_unavailable'); ?>
                                </span> 
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="index-cta-container">
            <a href="booking.php" class="btn"><?php echo t('home_cta'); ?></a>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
