<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);

$page_title = t('auth_forgot_page_title');
$success_msg = '';
$error_msg = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(sanitize_input($_POST['email'] ?? ''));

    if ($email === '') {
        $error_msg = t('auth_required');
    } elseif (!validate_email($email)) {
        $error_msg = t('auth_email_invalid');
    } else {
        $reset_request = request_site_user_password_reset($conn, $email);

        if (!empty($reset_request['success']) && !empty($reset_request['email_sent'])) {
            $success_msg = $reset_request['message'] ?? t('auth_reset_success');
        } else {
            $error_msg = $reset_request['message'] ?? t('booking_submit_error');
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

                <?php if ($success_msg): ?>
                    <div class="message success"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars(site_url('general/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>" class="auth-form">
                    <div class="form-group">
                        <label class="form-label"><?php echo t('auth_email'); ?></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                    </div>

                    <button type="submit" class="btn auth-submit-btn"><?php echo t('common_submit'); ?></button>
                </form>

                <div class="auth-links">
                    <a href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('nav_login'); ?></a>
                    <a href="<?php echo htmlspecialchars(site_url('general/register.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_need_account'); ?></a>
                    <a href="<?php echo htmlspecialchars(site_url('general/index.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_back_to_site'); ?></a>
                </div>
            </div>

            <aside class="auth-side-panel">
                <span class="auth-side-kicker">FAMOUS GAMING</span>
                <h2><?php echo t('auth_forgot_password'); ?></h2>
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
