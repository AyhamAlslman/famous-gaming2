<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ' . site_url('general/login.php?redirect=admin/dashboard.php'));
    exit;
}

if (!isset($_SESSION['admin_role'])) {
    session_destroy();
    header('Location: ' . site_url('general/login.php?redirect=admin/dashboard.php'));
    exit;
}

function isAdmin() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'admin';
}

function isEmployee() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'employee';
}

function admin_csrf_token() {
    return generate_csrf_token();
}

function admin_csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function admin_require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';

        if (!verify_csrf_token($token)) {
            http_response_code(403);
            exit('Invalid request token.');
        }
    }
}
?>
