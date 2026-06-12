<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);

$page_title = t('auth_forgot_page_title');
$error_msg = '';
$success_msg = '';
$email = '';
$request_submitted = false;
$email_delivery_error_text = site_language() === 'ar'
    ? 'تعذر إرسال رسالة إعادة التعيين الآن. حاول مرة أخرى لاحقًا.'
    : 'The reset email could not be sent right now. Please try again later.';
$reset_link_expires_text = site_language() === 'ar'
    ? 'سيكون الرابط صالحًا لمدة 15 دقيقة.'
    : 'The link will be valid for 15 minutes.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'request_reset') {
        $email = strtolower(sanitize_input($_POST['email'] ?? ''));

        if ($email === '' || !validate_email($email)) {
            $error_msg = t('auth_email_invalid');
        } else {
            $request = request_site_user_password_reset($conn, $email);
            $request_code = (string)($request['code'] ?? '');
            $email_delivery_failed = in_array($request_code, ['send_failed', 'mail_not_configured'], true)
                || (!empty($request['success']) && empty($request['email_sent']));

            if (!empty($request['success']) && !empty($request['email_sent'])) {
                $request_submitted = true;
                $success_msg = (string)($request['message'] ?? t('auth_reset_success'));
                error_log('Password reset requested for: ' . $email);
            } else {
                $error_msg = $email_delivery_failed
                    ? $email_delivery_error_text
                    : (string)($request['message'] ?? t('booking_submit_error'));
                error_log('Password reset failed for ' . $email . ': ' . $request_code);
            }
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

                <?php if ($error_msg): ?>
                    <div class="message error"><?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if ($request_submitted): ?>
                    <div class="message success"><?php echo htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8'); ?></div>
                    <p class="auth-help-text"><?php echo htmlspecialchars($reset_link_expires_text, ENT_QUOTES, 'UTF-8'); ?></p>

                    <a class="btn auth-submit-btn" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo t('nav_login'); ?>
                    </a>
                <?php else: ?>
                    <form method="POST" action="<?php echo htmlspecialchars(site_url('general/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>" class="auth-form">
                        <input type="hidden" name="action" value="request_reset">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('auth_email'); ?></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required autofocus>
                        </div>
                        <button type="submit" class="btn auth-submit-btn"><?php echo t('common_submit'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
