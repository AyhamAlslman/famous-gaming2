<?php
session_start();

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = t('admin_login_invalid_token');
    } else {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password before verification

    if (empty($username) || empty($password)) {
        $error_message = t('admin_login_required');
    } else {
        // Use prepared statement to prevent SQL injection
        $stmt = mysqli_prepare($conn, "SELECT id, username, password, full_name, role, status FROM admins WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) == 1) {
            $admin = mysqli_fetch_assoc($result);

            // Check if account is inactive
            if ($admin['status'] == 'Inactive') {
                $error_message = t('admin_login_inactive');
            } else {
                // Verify password using password_verify for hashed passwords
                // Also support plain text for backward compatibility during migration
                $password_valid = false;

                if (password_verify($password, $admin['password'])) {
                    // Hashed password verification
                    $password_valid = true;
                } elseif ($admin['password'] === $password) {
                    // Plain text fallback (for accounts not yet migrated)
                    $password_valid = true;

                    // Auto-update to hashed password
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = mysqli_prepare($conn, "UPDATE admins SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($update_stmt, "si", $hashed, $admin['id']);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }

                if ($password_valid) {
                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_full_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];

                    // Log successful login
                    log_admin_action($conn, $admin['id'], 'LOGIN', 'admins', $admin['id']);

                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error_message = t('admin_login_invalid');
                }
            }
        } else {
            $error_message = t('admin_login_invalid');
        }

        mysqli_stmt_close($stmt);
    }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(site_language(), ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars(site_direction(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('admin_login_page_title'); ?></title>
    <link rel="stylesheet" href="css/admin.css?v=2.0">
</head>
<body class="admin-body admin-login-page <?php echo site_is_rtl() ? 'admin-rtl' : ''; ?>">
    <section class="content admin-login-content">
        <div class="container admin-login-container">
            <div class="admin-login-language-switcher">
                <?php
                $admin_login_language_url = site_language() === 'ar' ? site_switch_language_url('en') : site_switch_language_url('ar');
                $admin_login_language_label = site_language() === 'ar' ? t('lang_en') : t('lang_ar');
                ?>
                <a href="<?php echo htmlspecialchars($admin_login_language_url, ENT_QUOTES, 'UTF-8'); ?>" class="active"><?php echo htmlspecialchars($admin_login_language_label, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
            <h2 class="section-title"><?php echo t('admin_login_heading'); ?></h2>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="form-container admin-login-card">
                <h3 style="text-align: center; margin-bottom: 1.5rem;"><?php echo t('admin_login_title'); ?></h3>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label><?php echo t('admin_login_username'); ?></label>
                        <input type="text" name="username" required autofocus>
                    </div>

                    <div class="form-group">
                        <label><?php echo t('admin_login_password'); ?></label>
                        <input type="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn" style="width: 100%;"><?php echo t('admin_login_submit'); ?></button>
                    </div>
                </form>

                <div class="admin-login-links" style="text-align: center; margin-top: 1.5rem;">
                    <a href="../forgot_password.php" class="admin-login-link"><?php echo t('admin_login_forgot'); ?></a>
                    <a href="../index.php" class="admin-login-link"><?php echo t('admin_login_back'); ?></a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p><?php echo t('admin_footer'); ?></p>
        </div>
    </footer>
</body>
</html>
