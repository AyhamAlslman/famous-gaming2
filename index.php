<?php
include 'includes/config.php';
$page_title = 'Home - FAMOUS GAMING';
include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Welcome to FAMOUS GAMING</h1>
        <p>Experience Premium Gaming in Luxury</p>
        <p>State-of-the-Art Consoles - VIP Rooms - Professional Service</p>
    </div>
</section>
<h1>sofnsfcpskoncscnsdpo</h1>
<section class="content">
    <div class="container">
        <h2 class="section-title">Our Premium Rooms</h2>

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
                        <div class="room-price"><?= number_format($room['price_per_hour'], 2) ?> JOD/hr</div>
                        <div class="room-card-actions">
                            <?php if ($is_available): ?>
                                <a href="booking.php?room_id=<?= (int)$room['id'] ?>#booking-form" class="btn btn-small room-book-btn">
                                    Book Now
                                </a>
                            <?php else: ?>
                                <span class="btn btn-small room-book-btn room-book-btn-disabled" aria-disabled="true">
                                    Booking Unavailable
                                </span> 
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="index-cta-container">
            <a href="booking.php" class="btn">Book Your Experience</a>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
