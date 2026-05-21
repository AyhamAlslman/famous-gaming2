<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

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
        $success_msg = t('auth_reset_success');
    }
}

include 'includes/header.php';
?>

<section class="content auth-page-content">
    <div class="container">
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

            <form method="POST" action="forgot_password.php" class="auth-form">
                <div class="form-group">
                    <label class="form-label"><?php echo t('auth_email'); ?></label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                </div>

                <button type="submit" class="btn auth-submit-btn"><?php echo t('common_submit'); ?></button>
            </form>

            <div class="auth-links">
                <a href="login.php"><?php echo t('nav_login'); ?></a>
                <a href="index.php"><?php echo t('auth_back_to_site'); ?></a>
            </div>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
