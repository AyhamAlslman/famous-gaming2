<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);

$page_title = t('auth_reset_page_title');
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error_msg = '';
$success_msg = '';
$password_reset_user = get_site_user_by_password_reset_token($conn, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$password_reset_user) {
        $error_msg = t('auth_reset_invalid');
    } elseif ($new_password === '' || $confirm_password === '') {
        $error_msg = t('auth_required');
    } elseif (strlen($new_password) < 6) {
        $error_msg = t('auth_password_short');
    } elseif ($new_password !== $confirm_password) {
        $error_msg = t('auth_password_mismatch');
    } elseif (reset_site_user_password_by_token($conn, $token, $new_password)) {
        $success_msg = t('auth_reset_complete');
        $password_reset_user = null;
    } else {
        $error_msg = t('booking_submit_error');
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="content auth-page-content">
    <div class="container">
        <div class="auth-shell auth-shell-compact">
            <div class="auth-card">
                <span class="auth-eyebrow"><?php echo t('nav_account'); ?></span>
                <h1><?php echo t('auth_reset_title'); ?></h1>
                <p class="auth-help-text">
                    <?php echo $password_reset_user ? t('auth_reset_form_help') : t('auth_reset_invalid'); ?>
                </p>

                <?php if ($success_msg): ?>
                    <div class="message success"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <?php if ($password_reset_user): ?>
                    <form method="POST" action="<?php echo htmlspecialchars(site_url('general/reset_password.php'), ENT_QUOTES, 'UTF-8'); ?>" class="auth-form">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="form-group">
                            <label class="form-label"><?php echo t('auth_new_password'); ?></label>
                            <div class="auth-password-field">
                                <input id="reset-password" type="password" name="password" class="form-control" required>
                                <button type="button" class="auth-password-toggle" data-password-toggle="#reset-password" data-show-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" data-hide-label="<?php echo htmlspecialchars(t('auth_hide_password'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <svg class="auth-eye-icon auth-eye-open" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M12 5.5c5 0 8.7 4.1 10 6.5-1.3 2.4-5 6.5-10 6.5S3.3 14.4 2 12c1.3-2.4 5-6.5 10-6.5Zm0 10.2A3.7 3.7 0 1 0 12 8.3a3.7 3.7 0 0 0 0 7.4Zm0-2A1.7 1.7 0 1 1 12 10.3a1.7 1.7 0 0 1 0 3.4Z"/>
                                    </svg>
                                    <svg class="auth-eye-icon auth-eye-closed" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1a11.6 11.6 0 0 1-5.2 1.3C7 20.3 3.3 16.4 2 14c.7-1.3 2.2-3.2 4.2-4.6L1.9 4l1.4-1.7Zm6.1 8.5a3.7 3.7 0 0 0 4.8 4.8l-1.7-1.7a1.7 1.7 0 0 1-2.3-2.3L9.4 10.8Zm2.6-5.1c5 0 8.7 3.9 10 6.3a15.9 15.9 0 0 1-2.8 3.6l-2.6-2.6a3.7 3.7 0 0 0-4.5-4.5L9.9 6.3c.7-.4 1.4-.6 2.1-.6Z"/>
                                    </svg>
                                </button>
                            </div>
                            <small class="auth-password-note"><?php echo t('auth_password_tip'); ?></small>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?php echo t('auth_confirm_password'); ?></label>
                            <div class="auth-password-field">
                                <input id="reset-confirm-password" type="password" name="confirm_password" class="form-control" required>
                                <button type="button" class="auth-password-toggle" data-password-toggle="#reset-confirm-password" data-show-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" data-hide-label="<?php echo htmlspecialchars(t('auth_hide_password'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <svg class="auth-eye-icon auth-eye-open" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M12 5.5c5 0 8.7 4.1 10 6.5-1.3 2.4-5 6.5-10 6.5S3.3 14.4 2 12c1.3-2.4 5-6.5 10-6.5Zm0 10.2A3.7 3.7 0 1 0 12 8.3a3.7 3.7 0 0 0 0 7.4Zm0-2A1.7 1.7 0 1 1 12 10.3a1.7 1.7 0 0 1 0 3.4Z"/>
                                    </svg>
                                    <svg class="auth-eye-icon auth-eye-closed" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1a11.6 11.6 0 0 1-5.2 1.3C7 20.3 3.3 16.4 2 14c.7-1.3 2.2-3.2 4.2-4.6L1.9 4l1.4-1.7Zm6.1 8.5a3.7 3.7 0 0 0 4.8 4.8l-1.7-1.7a1.7 1.7 0 0 1-2.3-2.3L9.4 10.8Zm2.6-5.1c5 0 8.7 3.9 10 6.3a15.9 15.9 0 0 1-2.8 3.6l-2.6-2.6a3.7 3.7 0 0 0-4.5-4.5L9.9 6.3c.7-.4 1.4-.6 2.1-.6Z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn auth-submit-btn"><?php echo t('auth_reset_submit'); ?></button>
                    </form>
                <?php endif; ?>

                <div class="auth-links auth-links-stacked">
                    <a href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_login'); ?></a>
                    <a href="<?php echo htmlspecialchars(site_url('general/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_forgot_password'); ?></a>
                    <a href="<?php echo htmlspecialchars(site_url('general/index.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_back_to_site'); ?></a>
                </div>
            </div>

            <aside class="auth-side-panel">
                <span class="auth-side-kicker">FAMOUS GAMING</span>
                <h2><?php echo t('auth_reset_title'); ?></h2>
                <p><?php echo t('auth_reset_form_help'); ?></p>
                <a class="auth-side-action" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_login'); ?></a>
            </aside>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
