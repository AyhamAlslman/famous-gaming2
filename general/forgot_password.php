<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);

$page_title = t('auth_forgot_page_title');
$error_msg = '';
$email = '';
$request_submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'request_reset') {
        $email = strtolower(sanitize_input($_POST['email'] ?? ''));

        if ($email === '' || !validate_email($email)) {
            $error_msg = t('auth_email_invalid');
        } else {
            $request = request_site_user_password_reset($conn, $email);
            $request_code = (string)($request['code'] ?? '');

            // Show success regardless (security best practice - don't reveal if email exists)
            $request_submitted = true;
            
            // Log the actual result for monitoring
            if (!empty($request['success'])) {
                error_log('Password reset requested for: ' . $email);
            } else {
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
                    <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <?php if ($request_submitted): ?>
                    <!-- Success message after email submission -->
                    <div class="message success">
                        <?php echo (function_exists('site_language') && site_language() === 'ar') 
                            ? 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني. يرجى التحقق من صندوق الوارد والبريد الغير مرغوب فيه.'
                            : 'A password reset link has been sent to your email. Please check your inbox and spam folder.'; 
                        ?>
                    </div>
                    <p class="auth-help-text">
                        <?php echo (function_exists('site_language') && site_language() === 'ar') 
                            ? 'سيكون الرابط صالحاً لمدة 15 دقيقة.'
                            : 'The link will be valid for 15 minutes.'; 
                        ?>
                    </p>
                    <a class="btn auth-submit-btn" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo t('nav_login'); ?>
                    </a>
                <?php else: ?>
                    <!-- Email request form -->
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

            <aside class="auth-side-panel">
                <span class="auth-side-kicker">FAMOUS GAMING</span>
                <h2><?php echo t('auth_forgot_title'); ?></h2>
                <p><?php echo t('auth_reset_hint'); ?></p>
                <a class="auth-side-action" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_login'); ?></a>
            </aside>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
