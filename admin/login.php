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
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password before verification

    if (empty($username) || empty($password)) {
        $error_message = 'Username and password are required';
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
                $error_message = 'This account is inactive. Please contact administrator';
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
                    $error_message = 'Invalid username or password';
                }
            }
        } else {
            $error_message = 'Invalid username or password';
        }

        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <section class="content">
        <div class="container">
            <h2 class="section-title">Admin Panel</h2>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="form-container">
                <h3 style="text-align: center; margin-bottom: 1.5rem;">Login</h3>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required autofocus>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn" style="width: 100%;">Login</button>
                    </div>
                </form>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="../index.php" style="color: #e94560;">Back to Main Site</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 PlayStation PlayRoom - Admin Panel</p>
        </div>
    </footer>
</body>
</html>
