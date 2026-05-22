<?php
require_once dirname(__DIR__) . '/includes/config.php';
$page_title = t('contact_page_title');
$contact_hero_image = site_url('images/home-neon-sign.jpg');
include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero page-hero arena-page-hero contact-page-hero" style="--page-hero-image: url('<?php echo htmlspecialchars($contact_hero_image, ENT_QUOTES, 'UTF-8'); ?>');">
    <img class="page-hero-visual" src="<?php echo htmlspecialchars($contact_hero_image, ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true">
    <div class="container">
        <span class="ticket-label">FAMOUS GAMING 2026</span>
        <h1><?php echo t('contact_hero_title'); ?></h1>
        <p><?php echo t('contact_hero_text'); ?></p>
    </div>
</section>

<section class="content arena-page-content contact-page-content">
    <div class="container">
        <div class="home-section-heading arena-section-heading">
            <span class="ticket-label"><?php echo t('contact_social_title'); ?></span>
            <h2><?php echo t('contact_ready_title'); ?></h2>
            <p><?php echo t('contact_ready_text'); ?></p>
        </div>
        <div class="contact-main-container">
            <div class="row g-4 contact-info-grid">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="contact-info-card h-100">
                        <div class="contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M6.62 10.79a15.3 15.3 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24 11.4 11.4 0 0 0 3.58.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .57 3.58 1 1 0 0 1-.24 1.01l-2.21 2.2Z"/></svg>
                        </div>
                        <h3 class="contact-card-title"><?php echo t('contact_phone'); ?></h3>
                        <a href="tel:+96261234567" class="contact-card-link">+962 6 123 4567</a>
                        <a href="tel:+962791234567" class="contact-card-link">+962 79 123 4567</a>
                        <p class="contact-card-hint"><?php echo t('contact_phone_hours'); ?></p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="contact-info-card h-100">
                        <div class="contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 2v.25l8 5 8-5V7H4Zm16 10V9.6l-7.47 4.67a1 1 0 0 1-1.06 0L4 9.6V17h16Z"/></svg>
                        </div>
                        <h3 class="contact-card-title"><?php echo t('contact_email'); ?></h3>
                        <a href="mailto:info@famousgaming.jo" class="contact-card-link">info@famousgaming.jo</a>
                        <a href="mailto:bookings@famousgaming.jo" class="contact-card-link">bookings@famousgaming.jo</a>
                        <p class="contact-card-hint"><?php echo t('contact_email_reply'); ?></p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://www.google.com/maps/search/?api=1&amp;query=Jabal+Amman,+Jordan" class="contact-info-card contact-info-card-link h-100" target="_blank" rel="noopener noreferrer" aria-label="<?php echo htmlspecialchars(t('contact_location_hint'), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 2a7 7 0 0 1 7 7c0 5.25-7 13-7 13S5 14.25 5 9a7 7 0 0 1 7-7Zm0 9.5A2.5 2.5 0 1 0 12 6a2.5 2.5 0 0 0 0 5.5Z"/></svg>
                        </div>
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
                    <a href="https://wa.me/962791234567" class="contact-social-link social-whatsapp" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
                        <div class="contact-social-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="img"><path d="M12.04 2C6.58 2 2.14 6.36 2.14 11.72c0 1.82.52 3.55 1.48 5.06L2 22l5.36-1.57a10.2 10.2 0 0 0 4.68 1.16c5.46 0 9.9-4.36 9.9-9.72C21.94 6.36 17.5 2 12.04 2Zm0 17.9c-1.47 0-2.9-.4-4.14-1.16l-.3-.18-3.18.93.95-3.02-.2-.31a8.02 8.02 0 0 1-1.33-4.44c0-4.43 3.68-8.03 8.2-8.03s8.2 3.6 8.2 8.03-3.68 8.18-8.2 8.18Zm4.5-6.02c-.25-.12-1.47-.71-1.7-.79-.23-.08-.4-.12-.57.12-.17.24-.65.79-.8.95-.15.16-.3.18-.55.06-.25-.12-1.06-.38-2.02-1.2-.75-.66-1.25-1.47-1.4-1.72-.15-.24-.02-.38.11-.5.12-.11.25-.28.37-.42.12-.14.17-.24.25-.4.08-.16.04-.3-.02-.42-.06-.12-.57-1.34-.78-1.84-.21-.48-.42-.42-.57-.42h-.49c-.17 0-.44.06-.67.3-.23.24-.88.85-.88 2.07 0 1.22.9 2.4 1.03 2.56.13.16 1.77 2.65 4.29 3.72.6.25 1.07.4 1.44.51.6.19 1.15.16 1.58.1.48-.07 1.47-.59 1.68-1.16.21-.57.21-1.05.15-1.16-.06-.1-.23-.16-.48-.28Z"/></svg>
                        </div>
                        <div class="contact-social-name">WhatsApp</div>
                        <div class="contact-social-handle">+962 79 123 4567</div>
                    </a>
                    <a href="https://instagram.com/ayham_alslmann" class="contact-social-link social-instagram" target="_blank" rel="noopener noreferrer" aria-label="Visit our Instagram page">
                        <div class="contact-social-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="img"><path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2Zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8Zm8.7 2.35a1.15 1.15 0 1 1 0 2.3 1.15 1.15 0 0 1 0-2.3ZM12 7.2a4.8 4.8 0 1 1 0 9.6 4.8 4.8 0 0 1 0-9.6Zm0 2a2.8 2.8 0 1 0 0 5.6 2.8 2.8 0 0 0 0-5.6Z"/></svg>
                        </div>
                        <div class="contact-social-name">Instagram</div>
                        <div class="contact-social-handle">@ayham_alslmann</div>
                    </a>
                    <a href="#" class="contact-social-link social-facebook" aria-label="View our Facebook page">
                        <div class="contact-social-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" role="img"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06C2 17.08 5.66 21.24 10.44 22v-7.03H7.9v-2.91h2.54V9.84c0-2.52 1.5-3.91 3.78-3.91 1.1 0 2.24.2 2.24.2v2.47H15.2c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.44 2.91h-2.34V22C18.34 21.24 22 17.08 22 12.06Z"/></svg>
                        </div>
                        <div class="contact-social-name">Facebook</div>
                        <div class="contact-social-handle">FAMOUS GAMING</div>
                    </a>
                </div>
            </div>

            <div class="contact-cta-container">
                <h3 class="contact-cta-title"><?php echo t('contact_ready_title'); ?></h3>
                <p class="contact-cta-text"><?php echo t('contact_ready_text'); ?></p>
                <a href="<?php echo htmlspecialchars(site_url('user/booking.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('nav_book_now'); ?></a>
            </div>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
