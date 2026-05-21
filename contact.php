<?php
include 'includes/config.php';
$page_title = t('contact_page_title');
include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1><?php echo t('contact_hero_title'); ?></h1>
        <p><?php echo t('contact_hero_text'); ?></p>
    </div>
</section>

<section class="content">
    <div class="container">
        <div class="contact-main-container">
            <div class="row g-4 contact-info-grid">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="contact-info-card h-100">
                        <div class="contact-icon" aria-hidden="true">&#128222;</div>
                        <h3 class="contact-card-title"><?php echo t('contact_phone'); ?></h3>
                        <a href="tel:+96261234567" class="contact-card-link">+962 6 123 4567</a>
                        <a href="tel:+962791234567" class="contact-card-link">+962 79 123 4567</a>
                        <p class="contact-card-hint"><?php echo t('contact_phone_hours'); ?></p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="contact-info-card h-100">
                        <div class="contact-icon" aria-hidden="true">&#128231;</div>
                        <h3 class="contact-card-title"><?php echo t('contact_email'); ?></h3>
                        <a href="mailto:info@famousgaming.jo" class="contact-card-link">info@famousgaming.jo</a>
                        <a href="mailto:bookings@famousgaming.jo" class="contact-card-link">bookings@famousgaming.jo</a>
                        <p class="contact-card-hint"><?php echo t('contact_email_reply'); ?></p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://www.google.com/maps/search/?api=1&amp;query=Jabal+Amman,+Jordan" class="contact-info-card contact-info-card-link h-100" target="_blank" rel="noopener noreferrer" aria-label="<?php echo htmlspecialchars(t('contact_location_hint'), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="contact-icon" aria-hidden="true">&#128205;</div>
                        <h3 class="contact-card-title"><?php echo t('contact_location'); ?></h3>
                        <p class="contact-card-text">Rainbow Street</p>
                        <p class="contact-card-text">Jabal Amman</p>
                        <p class="contact-card-text">Amman, Jordan</p>
                        <p class="contact-card-hint"><?php echo t('contact_location_hint'); ?></p>
                    </a>
                </div>
            </div>

            <div class="contact-hours-container">
                <h3 class="contact-hours-title"><?php echo t('contact_hours_title'); ?></h3>
                <div class="contact-hours-list">
                    <div class="contact-hours-item">
                        <span class="contact-hours-day"><?php echo t('contact_sun_thu'); ?></span>
                        <span class="contact-hours-time">9:00 AM - 12:00 AM</span>
                    </div>
                    <div class="contact-hours-item">
                        <span class="contact-hours-day"><?php echo t('contact_friday'); ?></span>
                        <span class="contact-hours-time">9:00 AM - 1:00 AM</span>
                    </div>
                    <div class="contact-hours-item">
                        <span class="contact-hours-day"><?php echo t('contact_saturday'); ?></span>
                        <span class="contact-hours-time">9:00 AM - 1:00 AM</span>
                    </div>
                    <div class="contact-hours-item">
                        <span class="contact-hours-highlight"><?php echo t('contact_walkins'); ?></span>
                        <span class="contact-hours-time"><?php echo t('contact_booking_recommended'); ?></span>
                    </div>
                </div>
            </div>

            <div class="contact-social-container">
                <h3 class="contact-social-title"><?php echo t('contact_social_title'); ?></h3>
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
                <h3 class="contact-cta-title"><?php echo t('contact_ready_title'); ?></h3>
                <p class="contact-cta-text"><?php echo t('contact_ready_text'); ?></p>
                <a href="booking.php" class="btn"><?php echo t('nav_book_now'); ?></a>
            </div>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
