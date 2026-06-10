<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);

$page_title = t('auth_forgot_page_title');
$step = 'email';
$error_msg = '';
$success_msg = '';
$reset_link = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'request_reset') {
        $email = strtolower(sanitize_input($_POST['email'] ?? ''));

        if ($email === '' || !validate_email($email)) {
            $error_msg = t('auth_email_invalid');
        } else {
            $request = request_site_user_password_reset($conn, $email);
            $request_code = (string)($request['code'] ?? '');

            if (!empty($request['success']) || in_array($request_code, ['email_not_found', 'inactive_account'], true)) {
                $_SESSION['login_success_message'] = t('auth_reset_success');
                mysqli_close($conn);
                header('Location: ' . site_url('general/login.php'));
                exit;
            }

            $error_msg = $request['message'] ?? t('auth_reset_send_failed');
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="content auth-page-content">
    <div class="container">
        <div class="auth-shell auth-shell-compact">
            <div class="auth-card">
                <span class="auth-eyebrow"><?php echo t('nav_account'); ?></span>
                <h1><?php echo t('auth_forgot_title'); ?></h1>
                <p class="auth-help-text"><?php echo t('auth_reset_hint'); ?></p>
                <?php if ($success_msg): ?><div class="message success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
                <?php if ($error_msg): ?><div class="message error"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>
                <?php if ($reset_link): ?>
                    <div class="form-group">
                        <label class="form-label"><?php echo (function_exists('site_language') && site_language() === 'ar') ? 'رابط إعادة التعيين' : 'Reset link'; ?></label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8'); ?>" readonly onclick="this.select();">
                    </div>
                    <a class="btn auth-submit-btn" href="<?php echo htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_reset_submit'); ?></a>
                <?php endif; ?>
                <?php if ($step === 'complete'): ?>
                    <a class="btn auth-submit-btn" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_login'); ?></a>
                <?php endif; ?>
            </div>

            <aside class="auth-side-panel">
                <span class="auth-side-kicker">FAMOUS GAMING</span>
                <h2><?php echo t('auth_forgot_title'); ?></h2>
                <p><?php echo t('auth_reset_hint'); ?></p>
                <a class="auth-side-action" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_login'); ?></a>
            </aside>
        </div>
    </div>
</section>

<?php if ($step !== 'complete'): ?>
<div class="site-modal otp-modal password-reset-modal" id="passwordResetModal">
    <a class="site-modal-backdrop password-reset-backdrop" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('common_close'), ENT_QUOTES, 'UTF-8'); ?>"></a>
    <div class="site-modal-dialog password-reset-dialog" role="dialog" aria-modal="true" aria-labelledby="passwordResetTitle">
        <a class="site-modal-close password-reset-close" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('common_close'), ENT_QUOTES, 'UTF-8'); ?>">&times;</a>

        <?php if ($step === 'email'): ?>
            <h3 id="passwordResetTitle"><?php echo t('auth_forgot_title'); ?></h3>
            <p><?php echo t('auth_reset_hint'); ?></p>
            <form method="POST" action="<?php echo htmlspecialchars(site_url('general/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>" class="auth-form">
                <input type="hidden" name="action" value="request_reset">
                <div class="form-group">
                    <label class="form-label"><?php echo t('auth_email'); ?></label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" autocomplete="email" required autofocus>
                </div>
                <button type="submit" class="btn auth-submit-btn"><?php echo t('common_submit'); ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
