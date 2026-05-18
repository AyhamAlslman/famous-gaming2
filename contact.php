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
                        <div class="contact-icon" aria-hidden="true">&#128222;</div>
                        <h3 class="contact-card-title">Phone</h3>
                        <a href="tel:+96261234567" class="contact-card-link">+962 6 123 4567</a>
                        <a href="tel:+962791234567" class="contact-card-link">+962 79 123 4567</a>
                        <p class="contact-card-hint">Daily: 9:00 AM - 12:00 AM</p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="contact-info-card h-100">
                        <div class="contact-icon" aria-hidden="true">&#128231;</div>
                        <h3 class="contact-card-title">Email</h3>
                        <a href="mailto:info@famousgaming.jo" class="contact-card-link">info@famousgaming.jo</a>
                        <a href="mailto:bookings@famousgaming.jo" class="contact-card-link">bookings@famousgaming.jo</a>
                        <p class="contact-card-hint">We reply within 24 hours</p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://www.google.com/maps/search/?api=1&amp;query=Jabal+Amman,+Jordan" class="contact-info-card contact-info-card-link h-100" target="_blank" rel="noopener noreferrer" aria-label="Open Jabal Amman, Jordan in Google Maps">
                        <div class="contact-icon" aria-hidden="true">&#128205;</div>
                        <h3 class="contact-card-title">Location</h3>
                        <p class="contact-card-text">Rainbow Street</p>
                        <p class="contact-card-text">Jabal Amman</p>
                        <p class="contact-card-text">Amman, Jordan</p>
                        <p class="contact-card-hint">Tap to open Google Maps</p>
                    </a>
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
                    <a href="https://instagram.com/ayham_alslmann" class="contact-social-link" target="_blank" rel="noopener noreferrer" aria-label="Visit our Instagram page">
                        <div class="contact-social-icon" aria-hidden="true">&#128241;</div>
                        <div class="contact-social-name">Instagram</div>
                        <div class="contact-social-handle">@ayham_alslmann</div>
                    </a>
                    <a href="#" class="contact-social-link" aria-label="View our Twitter page">
                        <div class="contact-social-icon" aria-hidden="true">&#128172;</div>
                        <div class="contact-social-name">Twitter</div>
                        <div class="contact-social-handle">@famousgaming</div>
                    </a>
                    <a href="#" class="contact-social-link" aria-label="View our Facebook page">
                        <div class="contact-social-icon" aria-hidden="true">&#128216;</div>
                        <div class="contact-social-name">Facebook</div>
                        <div class="contact-social-handle">FAMOUS GAMING</div>
                    </a>
                    <a href="#" class="contact-social-link" aria-label="View our TikTok page">
                        <div class="contact-social-icon" aria-hidden="true">&#127918;</div>
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
