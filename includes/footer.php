    <footer class="footer site-footer">
        <div class="container site-footer-grid site-footer-clean-grid">
            <div class="site-footer-brand">
                <strong><?php echo t('brand_name'); ?></strong>
                <p><?php echo t('footer_tagline'); ?></p>
                <span class="site-footer-info"><?php echo t('footer_jabal_amman'); ?></span>
            </div>

            <div class="site-footer-links">
                <h3><?php echo t('footer_quick_links'); ?></h3>
                <div class="site-footer-link-grid">
                    <?php if (!empty($_SESSION['site_user_id'])): ?>
                        <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_store'); ?></a>
                        <a href="<?php echo htmlspecialchars(site_url('user/menu.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('booking_step_menu'); ?></a>
                        <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_my_bookings'); ?></a>
                        <a class="site-footer-support-link" href="<?php echo htmlspecialchars(site_url('user/complaints.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('footer_support'); ?></a>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars(site_url('general/about.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_about'); ?></a>
                        <a href="<?php echo htmlspecialchars(site_url('general/contact.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_contact'); ?></a>
                        <a class="site-footer-support-link" href="<?php echo htmlspecialchars(site_url('user/complaints.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('footer_support'); ?></a>
                        <a href="<?php echo htmlspecialchars(site_url('general/register.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_register'); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="site-footer-follow">
                <h3><?php echo t('footer_follow'); ?></h3>
                <div class="site-footer-socials">
                    <a href="https://wa.me/962798597188" class="footer-social footer-whatsapp" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.14 6.36 2.14 11.72c0 1.82.52 3.55 1.48 5.06L2 22l5.36-1.57a10.2 10.2 0 0 0 4.68 1.16c5.46 0 9.9-4.36 9.9-9.72C21.94 6.36 17.5 2 12.04 2Zm0 17.9c-1.47 0-2.9-.4-4.14-1.16l-.3-.18-3.18.93.95-3.02-.2-.31a8.02 8.02 0 0 1-1.33-4.44c0-4.43 3.68-8.03 8.2-8.03s8.2 3.6 8.2 8.03-3.68 8.18-8.2 8.18Zm4.5-6.02c-.25-.12-1.47-.71-1.7-.79-.23-.08-.4-.12-.57.12-.17.24-.65.79-.8.95-.15.16-.3.18-.55.06-.25-.12-1.06-.38-2.02-1.2-.75-.66-1.25-1.47-1.4-1.72-.15-.24-.02-.38.11-.5.12-.11.25-.28.37-.42.12-.14.17-.24.25-.4.08-.16.04-.3-.02-.42-.06-.12-.57-1.34-.78-1.84-.21-.48-.42-.42-.57-.42h-.49c-.17 0-.44.06-.67.3-.23.24-.88.85-.88 2.07 0 1.22.9 2.4 1.03 2.56.13.16 1.77 2.65 4.29 3.72.6.25 1.07.4 1.44.51.6.19 1.15.16 1.58.1.48-.07 1.47-.59 1.68-1.16.21-.57.21-1.05.15-1.16-.06-.1-.23-.16-.48-.28Z"/></svg>
                    </a>
                    <a href="https://instagram.com/ayham_alslmann" class="footer-social footer-instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2Zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8Zm8.7 2.35a1.15 1.15 0 1 1 0 2.3 1.15 1.15 0 0 1 0-2.3ZM12 7.2a4.8 4.8 0 1 1 0 9.6 4.8 4.8 0 0 1 0-9.6Zm0 2a2.8 2.8 0 1 0 0 5.6 2.8 2.8 0 0 0 0-5.6Z"/></svg>
                    </a>
                    <a href="https://www.facebook.com/search/top/?q=Ayham%20Alslman" class="footer-social footer-facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06C2 17.08 5.66 21.24 10.44 22v-7.03H7.9v-2.91h2.54V9.84c0-2.52 1.5-3.91 3.78-3.91 1.1 0 2.24.2 2.24.2v2.47H15.2c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.44 2.91h-2.34V22C18.34 21.24 22 17.08 22 12.06Z"/></svg>
                    </a>
                </div>
            </div>

            <div class="site-footer-bottom">
                <p><?php echo t('footer_rights'); ?></p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS (Local) -->
    <script src="<?php echo htmlspecialchars(site_url('assets/js/bootstrap.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

    <!-- Custom JS -->
    <script>
        window.siteModalTexts = <?php echo json_encode([
            'messageTitle' => t('modal_message_title'),
            'confirmTitle' => t('modal_confirm_title'),
            'ok' => t('common_close'),
            'yes' => t('common_yes'),
            'no' => t('common_no')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.siteValidationTexts = <?php echo json_encode([
            'phoneInvalid' => t('booking_validation_phone'),
            'datePast' => t('booking_validation_date'),
            'estimatedTotal' => t('booking_total_estimate')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="<?php echo htmlspecialchars(site_url('assets/js/script.js'), ENT_QUOTES, 'UTF-8'); ?>?v=2.8"></script>
</body>
</html>
