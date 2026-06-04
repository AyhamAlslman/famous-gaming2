<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);

$page_title = t('auth_forgot_page_title');
$flow_key = 'password_reset_otp_flow';
$flow = $_SESSION[$flow_key] ?? [];
$step = !empty($flow['verified']) ? 'password' : (!empty($flow['user_id']) ? 'otp' : 'email');
$error_msg = '';
$success_msg = '';
$email = (string)($flow['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'request_otp') {
        $email = strtolower(sanitize_input($_POST['email'] ?? ''));
        $last_sent_at = (int)($flow['last_sent_at'] ?? 0);

        if ($last_sent_at > 0 && time() - $last_sent_at < 60 && $email === ($flow['email'] ?? '')) {
            $success_msg = t('auth_otp_sent');
            $step = 'otp';
        } else {
            $request = request_site_user_password_reset_otp($conn, $email);
            if (!empty($request['success'])) {
                $_SESSION[$flow_key] = [
                    'user_id' => $request['user_id'],
                    'email' => $request['email'],
                    'attempts' => 0,
                    'last_sent_at' => time(),
                    'verified' => false,
                ];
                $flow = $_SESSION[$flow_key];
                $success_msg = $request['message'];
                $step = 'otp';
            } else {
                $error_msg = $request['message'] ?? t('auth_reset_send_failed');
                $step = 'email';
            }
        }
    } elseif ($action === 'verify_otp') {
        $flow = $_SESSION[$flow_key] ?? [];
        $otp_code = preg_replace('/\D+/', '', (string)($_POST['otp_code'] ?? ''));
        $attempts = (int)($flow['attempts'] ?? 0);

        if (empty($flow['user_id']) || empty($flow['email'])) {
            $error_msg = t('auth_otp_invalid');
            $step = 'email';
        } elseif ($attempts >= 5) {
            clear_site_user_password_reset_token($conn, (int)$flow['user_id']);
            unset($_SESSION[$flow_key]);
            $flow = [];
            $error_msg = t('auth_otp_attempts_exceeded');
            $step = 'email';
        } elseif (verify_site_user_password_reset_otp($conn, $flow['user_id'], $flow['email'], $otp_code)) {
            $_SESSION[$flow_key]['verified'] = true;
            $_SESSION[$flow_key]['verified_at'] = time();
            $flow = $_SESSION[$flow_key];
            $step = 'password';
        } else {
            $_SESSION[$flow_key]['attempts'] = $attempts + 1;
            $error_msg = t('auth_otp_invalid');
            $step = 'otp';
        }
    } elseif ($action === 'reset_password') {
        $flow = $_SESSION[$flow_key] ?? [];
        $new_password = (string)($_POST['password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');
        $verified_at = (int)($flow['verified_at'] ?? 0);

        if (empty($flow['verified']) || $verified_at < time() - 600) {
            unset($_SESSION[$flow_key]);
            $flow = [];
            $error_msg = t('auth_otp_invalid');
            $step = 'email';
        } elseif ($new_password === '' || $confirm_password === '') {
            $error_msg = t('auth_required');
            $step = 'password';
        } elseif (strlen($new_password) < 6) {
            $error_msg = t('auth_password_short');
            $step = 'password';
        } elseif ($new_password !== $confirm_password) {
            $error_msg = t('auth_password_mismatch');
            $step = 'password';
        } elseif (reset_site_user_password_after_otp($conn, $flow['user_id'], $flow['email'], $new_password)) {
            unset($_SESSION[$flow_key]);
            $success_msg = t('auth_reset_complete');
            $step = 'complete';
        } else {
            $error_msg = t('booking_submit_error');
            $step = 'password';
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
                <?php if ($step === 'complete'): ?>
                    <a class="btn auth-submit-btn" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_login'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($step !== 'complete'): ?>
<div class="site-modal otp-modal" id="passwordResetModal">
    <div class="site-modal-backdrop"></div>
    <div class="site-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="passwordResetTitle">
        <a class="site-modal-close" href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('common_close'), ENT_QUOTES, 'UTF-8'); ?>">X</a>

        <?php if ($step === 'email'): ?>
            <h3 id="passwordResetTitle"><?php echo t('auth_forgot_title'); ?></h3>
            <p><?php echo t('auth_reset_hint'); ?></p>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="request_otp">
                <div class="form-group">
                    <label class="form-label"><?php echo t('auth_email'); ?></label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" autocomplete="email" required autofocus>
                </div>
                <button type="submit" class="btn auth-submit-btn"><?php echo t('common_submit'); ?></button>
            </form>
        <?php elseif ($step === 'otp'): ?>
            <h3 id="passwordResetTitle"><?php echo t('auth_otp_title'); ?></h3>
            <p><?php echo t('auth_otp_hint', ['email' => $flow['email'] ?? $email]); ?></p>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="verify_otp">
                <div class="form-group">
                    <label class="form-label"><?php echo t('auth_otp_label'); ?></label>
                    <input type="text" name="otp_code" class="form-control auth-otp-input" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required autofocus>
                </div>
                <button type="submit" class="btn auth-submit-btn"><?php echo t('auth_otp_verify'); ?></button>
            </form>
            <form method="POST" class="auth-modal-secondary-form">
                <input type="hidden" name="action" value="request_otp">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($flow['email'] ?? $email, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn auth-modal-secondary-btn"><?php echo t('auth_otp_resend'); ?></button>
            </form>
        <?php else: ?>
            <h3 id="passwordResetTitle"><?php echo t('auth_reset_title'); ?></h3>
            <p><?php echo t('auth_reset_form_help'); ?></p>
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="reset_password">
                <div class="form-group">
                    <label class="form-label"><?php echo t('auth_new_password'); ?></label>
                    <input type="password" name="password" class="form-control" minlength="6" autocomplete="new-password" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo t('auth_confirm_password'); ?></label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn auth-submit-btn"><?php echo t('auth_reset_submit'); ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
