<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);
$auth_redirect = safe_local_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? ($_SESSION['post_login_redirect'] ?? 'user/user_dashboard.php'), 'user/user_dashboard.php');
$is_admin_redirect = preg_match('#^admin/#', $auth_redirect) === 1;
$admin_post_login_target = $is_admin_redirect ? $auth_redirect : 'admin/dashboard.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . site_url($admin_post_login_target));
    exit;
}

if (!empty($_SESSION['site_user_id']) && !$is_admin_redirect) {
    header('Location: ' . site_url($auth_redirect));
    exit;
}

$page_title = t('auth_login_page_title');
$error_msg = '';
$success_msg = '';
$login_identifier = '';

if (!empty($_SESSION['login_success_message'])) {
    $success_msg = (string)$_SESSION['login_success_message'];
    unset($_SESSION['login_success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = sanitize_input($_POST['login_identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login_identifier === '' || $password === '') {
        $error_msg = t('auth_required');
    } else {
        $admin_stmt = mysqli_prepare($conn, "SELECT id, username, password, full_name, role, status FROM admins WHERE username = ? OR email = ? LIMIT 1");
        mysqli_stmt_bind_param($admin_stmt, "ss", $login_identifier, $login_identifier);
        mysqli_stmt_execute($admin_stmt);
        $admin_result = mysqli_stmt_get_result($admin_stmt);
        $admin = mysqli_fetch_assoc($admin_result);
        mysqli_stmt_close($admin_stmt);

        if ($admin) {
            if (strtolower((string)$admin['role']) === 'employee') {
                $error_msg = t('auth_login_invalid');
            } elseif ($admin['status'] !== 'Active') {
                $error_msg = t('auth_inactive');
            } else {
                $admin_password_valid = password_verify($password, $admin['password']) || $admin['password'] === $password;

                if ($admin_password_valid) {
                    if ($admin['password'] === $password) {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $update_stmt = mysqli_prepare($conn, "UPDATE admins SET password = ? WHERE id = ?");
                        mysqli_stmt_bind_param($update_stmt, "si", $hashed, $admin['id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }

                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_full_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    log_admin_action($conn, $admin['id'], 'LOGIN', 'admins', $admin['id']);

                    header('Location: ' . site_url($admin_post_login_target));
                    exit;
                }
            }
        }

        if ($error_msg === '') {
            $user_stmt = mysqli_prepare($conn, "SELECT id, full_name, email, phone, password, role, loyalty_points, status FROM site_users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($user_stmt, "s", $login_identifier);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
            mysqli_stmt_close($user_stmt);

            if (!$user || !password_verify($password, $user['password'])) {
                $error_msg = t('auth_login_invalid');
            } elseif ($user['status'] !== 'Active') {
                $error_msg = t('auth_inactive');
            } else {
                $_SESSION['site_user_id'] = (int)$user['id'];
                $_SESSION['site_user_name'] = $user['full_name'];
                $_SESSION['site_user_role'] = $user['role'];
                $_SESSION['site_user_loyalty_points'] = (int)$user['loyalty_points'];
                $_SESSION['customer_name'] = $user['full_name'];
                $_SESSION['customer_phone'] = $user['phone'];

                unset($_SESSION['post_login_redirect']);
                header('Location: ' . site_url($auth_redirect));
                exit;
            }
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="content auth-page-content">
    <div class="container">
        <div class="auth-shell">
            <div class="auth-card">
                <span class="auth-eyebrow"><?php echo t('nav_account'); ?></span>
                <h1><?php echo t('auth_login_title'); ?></h1>
                <p class="auth-help-text"><?php echo t('auth_access_help'); ?></p>

                <?php if ($error_msg): ?>
                    <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>
                <?php if ($success_msg): ?>
                    <div class="message success"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars(site_url('general/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="auth-form">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($auth_redirect, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label class="form-label"><?php echo t('auth_login_identifier'); ?></label>
                        <input type="text" name="login_identifier" class="form-control" value="<?php echo htmlspecialchars($login_identifier); ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo t('auth_password'); ?></label>
                        <div class="auth-password-field">
                            <input id="login-password" type="password" name="password" class="form-control" required>
                            <button type="button" class="auth-password-toggle" data-password-toggle="#login-password" data-show-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" data-hide-label="<?php echo htmlspecialchars(t('auth_hide_password'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>">
                                <svg class="auth-eye-icon auth-eye-open" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M12 5.5c5 0 8.7 4.1 10 6.5-1.3 2.4-5 6.5-10 6.5S3.3 14.4 2 12c1.3-2.4 5-6.5 10-6.5Zm0 10.2A3.7 3.7 0 1 0 12 8.3a3.7 3.7 0 0 0 0 7.4Zm0-2A1.7 1.7 0 1 1 12 10.3a1.7 1.7 0 0 1 0 3.4Z"/>
                                </svg>
                                <svg class="auth-eye-icon auth-eye-closed" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1a11.6 11.6 0 0 1-5.2 1.3C7 20.3 3.3 16.4 2 14c.7-1.3 2.2-3.2 4.2-4.6L1.9 4l1.4-1.7Zm6.1 8.5a3.7 3.7 0 0 0 4.8 4.8l-1.7-1.7a1.7 1.7 0 0 1-2.3-2.3L9.4 10.8Zm2.6-5.1c5 0 8.7 3.9 10 6.3a15.9 15.9 0 0 1-2.8 3.6l-2.6-2.6a3.7 3.7 0 0 0-4.5-4.5L9.9 6.3c.7-.4 1.4-.6 2.1-.6Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn auth-submit-btn"><?php echo t('nav_login'); ?></button>
                </form>

                <div class="auth-links auth-links-stacked">
                    <a class="auth-forgot-link" href="<?php echo htmlspecialchars(site_url('general/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_forgot_password'); ?></a>
                    <a href="<?php echo htmlspecialchars(site_url('general/register.php'), ENT_QUOTES, 'UTF-8'); ?>?redirect=<?php echo urlencode($auth_redirect); ?>"><?php echo t('auth_need_account'); ?></a>
                </div>
            </div>

        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
