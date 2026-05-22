<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);
$auth_redirect = safe_local_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? ($_SESSION['post_login_redirect'] ?? 'user/user_dashboard.php'), 'user/user_dashboard.php');

if (!empty($_SESSION['site_user_id'])) {
    header('Location: ' . site_url($auth_redirect));
    exit;
}

$page_title = t('auth_register_page_title');
$error_msg = '';
$full_name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = strtolower(sanitize_input($_POST['email'] ?? ''));
    $phone = sanitize_input($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        $error_msg = t('auth_required');
    } elseif (!validate_email($email)) {
        $error_msg = t('auth_email_invalid');
    } elseif ($phone !== '' && !validate_phone($phone)) {
        $error_msg = t('booking_validation_phone');
    } elseif (strlen($password) < 6) {
        $error_msg = t('auth_password_short');
    } elseif ($password !== $confirm_password) {
        $error_msg = t('auth_password_mismatch');
    } else {
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM site_users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $exists = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);

        if ($exists) {
            $error_msg = t('auth_email_exists');
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';
            $stmt = mysqli_prepare($conn, "INSERT INTO site_users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssss", $full_name, $email, $phone, $hashed_password, $role);

            if (mysqli_stmt_execute($stmt)) {
                $user_id = mysqli_insert_id($conn);
                $_SESSION['site_user_id'] = (int)$user_id;
                $_SESSION['site_user_name'] = $full_name;
                $_SESSION['site_user_role'] = $role;
                $_SESSION['site_user_loyalty_points'] = 0;
                $_SESSION['customer_name'] = $full_name;
                $_SESSION['customer_phone'] = $phone;
                create_site_notification(
                    $conn,
                    (int)$user_id,
                    'account_created',
                    t('auth_register_success'),
                    t('user_notification_welcome'),
                    'user/user_dashboard.php'
                );

                unset($_SESSION['post_login_redirect']);
                header('Location: ' . site_url($auth_redirect));
                exit;
            }

            $error_msg = t('booking_submit_error');
            mysqli_stmt_close($stmt);
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="content auth-page-content">
    <div class="container">
        <div class="auth-shell">
            <div class="auth-card auth-card-wide">
                <span class="auth-eyebrow"><?php echo t('nav_account'); ?></span>
                <h1><?php echo t('auth_register_title'); ?></h1>
                <p class="auth-help-text"><?php echo t('auth_access_help'); ?></p>

                <?php if ($error_msg): ?>
                    <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars(site_url('general/register.php'), ENT_QUOTES, 'UTF-8'); ?>" class="auth-form auth-form-grid">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($auth_redirect, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label class="form-label"><?php echo t('auth_full_name'); ?></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo t('auth_email'); ?></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo t('common_phone'); ?></label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" placeholder="07XXXXXXXX">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo t('auth_password'); ?></label>
                        <div class="auth-password-field">
                            <input id="register-password" type="password" name="password" class="form-control" required>
                            <button type="button" class="auth-password-toggle" data-password-toggle="#register-password" data-show-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" data-hide-label="<?php echo htmlspecialchars(t('auth_hide_password'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>">
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
                            <input id="register-confirm-password" type="password" name="confirm_password" class="form-control" required>
                            <button type="button" class="auth-password-toggle" data-password-toggle="#register-confirm-password" data-show-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" data-hide-label="<?php echo htmlspecialchars(t('auth_hide_password'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>">
                                <svg class="auth-eye-icon auth-eye-open" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M12 5.5c5 0 8.7 4.1 10 6.5-1.3 2.4-5 6.5-10 6.5S3.3 14.4 2 12c1.3-2.4 5-6.5 10-6.5Zm0 10.2A3.7 3.7 0 1 0 12 8.3a3.7 3.7 0 0 0 0 7.4Zm0-2A1.7 1.7 0 1 1 12 10.3a1.7 1.7 0 0 1 0 3.4Z"/>
                                </svg>
                                <svg class="auth-eye-icon auth-eye-closed" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1a11.6 11.6 0 0 1-5.2 1.3C7 20.3 3.3 16.4 2 14c.7-1.3 2.2-3.2 4.2-4.6L1.9 4l1.4-1.7Zm6.1 8.5a3.7 3.7 0 0 0 4.8 4.8l-1.7-1.7a1.7 1.7 0 0 1-2.3-2.3L9.4 10.8Zm2.6-5.1c5 0 8.7 3.9 10 6.3a15.9 15.9 0 0 1-2.8 3.6l-2.6-2.6a3.7 3.7 0 0 0-4.5-4.5L9.9 6.3c.7-.4 1.4-.6 2.1-.6Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn auth-submit-btn auth-form-full"><?php echo t('nav_register'); ?></button>
                </form>

                <div class="auth-links">
                    <a href="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>?redirect=<?php echo urlencode($auth_redirect); ?>"><?php echo t('auth_have_account'); ?></a>
                </div>
            </div>
            </div>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
