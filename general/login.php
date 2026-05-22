<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);
$auth_redirect = safe_local_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? ($_SESSION['post_login_redirect'] ?? 'user/user_dashboard.php'), 'user/user_dashboard.php');

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . site_url('admin/dashboard.php'));
    exit;
}

if (!empty($_SESSION['site_user_id'])) {
    header('Location: ' . site_url($auth_redirect));
    exit;
}

$page_title = t('auth_login_page_title');
$error_msg = '';
$login_identifier = '';

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
            if ($admin['status'] !== 'Active') {
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

                    header('Location: ' . site_url('admin/dashboard.php'));
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
                            <button type="button" class="auth-password-toggle" data-password-toggle="#login-password" data-show-label="<?php echo htmlspecialchars(t('auth_show_password'), ENT_QUOTES, 'UTF-8'); ?>" data-hide-label="<?php echo htmlspecialchars(t('auth_hide_password'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_show_password'); ?></button>
                        </div>
                    </div>

                    <button type="submit" class="btn auth-submit-btn"><?php echo t('nav_login'); ?></button>
                </form>

                <div class="auth-links auth-links-stacked">
                    <a class="auth-forgot-link" href="<?php echo htmlspecialchars(site_url('general/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_forgot_password'); ?></a>
                    <a href="<?php echo htmlspecialchars(site_url('general/register.php'), ENT_QUOTES, 'UTF-8'); ?>?redirect=<?php echo urlencode($auth_redirect); ?>"><?php echo t('auth_need_account'); ?></a>
                    <a href="<?php echo htmlspecialchars(site_url('general/index.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_back_to_site'); ?></a>
                </div>
            </div>

            <aside class="auth-side-panel">
                <span class="auth-side-kicker">FAMOUS GAMING</span>
                <h2><?php echo t('auth_forgot_password'); ?></h2>
                <p><?php echo t('auth_password_tip'); ?></p>
                <a class="auth-side-action" href="<?php echo htmlspecialchars(site_url('general/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo t('auth_forgot_password'); ?></a>
            </aside>
            </div>
    </div>
</section>

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
