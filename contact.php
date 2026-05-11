<?php
include 'includes/config.php';
$page_title = 'Contact Us - FAMOUS GAMING';
include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Contact Us</h1>
        <p>Get in touch with us - we're here to help</p>
    </div>
</section>

<section class="content">
    <div class="container">
        <div class="contact-main-container">
            <div class="row g-4 contact-info-grid">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="contact-info-card h-100">
                        <div class="contact-icon">📞</div>
                        <h3 class="contact-card-title">Phone</h3>
                        <p class="contact-card-text">+962 6 123 4567</p>
                        <p class="contact-card-text">+962 79 123 4567</p>
                        <p class="contact-card-hint">Daily: 9:00 AM - 12:00 AM</p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="contact-info-card h-100">
                        <div class="contact-icon">📧</div>
                        <h3 class="contact-card-title">Email</h3>
                        <p class="contact-card-text">info@famousgaming.jo</p>
                        <p class="contact-card-text">bookings@famousgaming.jo</p>
                        <p class="contact-card-hint">We reply within 24 hours</p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="contact-info-card h-100">
                        <div class="contact-icon">📍</div>
                        <h3 class="contact-card-title">Location</h3>
                        <p class="contact-card-text">Rainbow Street</p>
                        <p class="contact-card-text">Jabal Amman</p>
                        <p class="contact-card-text">Amman, Jordan</p>
                    </div>
                </div>
            </div>

            <div class="contact-hours-container">
                <h3 class="contact-hours-title">Opening Hours</h3>
                <div class="contact-hours-list">
                    <div class="contact-hours-item">
                        <span class="contact-hours-day">Sunday - Thursday</span>
                        <span class="contact-hours-time">9:00 AM - 12:00 AM</span>
                    </div>
                    <div class="contact-hours-item">
                        <span class="contact-hours-day">Friday</span>
                        <span class="contact-hours-time">9:00 AM - 1:00 AM</span>
                    </div>
                    <div class="contact-hours-item">
                        <span class="contact-hours-day">Saturday</span>
                        <span class="contact-hours-time">9:00 AM - 1:00 AM</span>
                    </div>
                    <div class="contact-hours-item">
                        <span class="contact-hours-highlight">Walk-ins Welcome!</span>
                        <span class="contact-hours-time">Booking Recommended</span>
                    </div>
                </div>
            </div>

            <div class="contact-social-container">
                <h3 class="contact-social-title">Social Media</h3>
                <div class="contact-social-grid">
                    <a href="#" class="contact-social-link">
                        <div class="contact-social-icon">📱</div>
                        <div class="contact-social-name">Instagram</div>
                        <div class="contact-social-handle">@famousgaming</div>
                    </a>
                    <a href="#" class="contact-social-link">
                        <div class="contact-social-icon">💬</div>
                        <div class="contact-social-name">Twitter</div>
                        <div class="contact-social-handle">@famousgaming</div>
                    </a>
                    <a href="#" class="contact-social-link">
                        <div class="contact-social-icon">📘</div>
                        <div class="contact-social-name">Facebook</div>
                        <div class="contact-social-handle">FAMOUS GAMING</div>
                    </a>
                    <a href="#" class="contact-social-link">
                        <div class="contact-social-icon">🎮</div>
                        <div class="contact-social-name">TikTok</div>
                        <div class="contact-social-handle">@famousgaming</div>
                    </a>
                </div>
            </div>

            <div class="contact-cta-container">
                <h3 class="contact-cta-title">Ready to Play?</h3>
                <p class="contact-cta-text">Book your gaming session now and experience the best PlayStation gaming!</p>
                <a href="booking.php" class="btn">Book Now</a>
            </div>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
