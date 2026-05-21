<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

ensure_user_auth_schema($conn);
$auth_redirect = safe_local_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? ($_SESSION['post_login_redirect'] ?? 'user_dashboard.php'));

if (!empty($_SESSION['site_user_id'])) {
    header('Location: ' . $auth_redirect);
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
                    'user_dashboard.php'
                );

                unset($_SESSION['post_login_redirect']);
                header('Location: ' . $auth_redirect);
                exit;
            }

            $error_msg = t('booking_submit_error');
            mysqli_stmt_close($stmt);
        }
    }
}

include 'includes/header.php';
?>

<section class="content auth-page-content">
    <div class="container">
        <div class="auth-card">
            <span class="auth-eyebrow"><?php echo t('nav_account'); ?></span>
            <h1><?php echo t('auth_register_title'); ?></h1>

            <?php if ($error_msg): ?>
                <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="auth-form">
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
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo t('auth_confirm_password'); ?></label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn auth-submit-btn"><?php echo t('nav_register'); ?></button>
            </form>

            <div class="auth-links">
                <a href="login.php?redirect=<?php echo urlencode($auth_redirect); ?>"><?php echo t('auth_have_account'); ?></a>
                <a href="index.php"><?php echo t('auth_back_to_site'); ?></a>
            </div>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
