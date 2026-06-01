<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/includes/config.php';

$requested_admin_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? 'admin/dashboard.php');
$base_path = defined('SITE_BASE_PATH') ? rtrim(str_replace('\\', '/', SITE_BASE_PATH), '/') : '';
$admin_redirect_target = ltrim($requested_admin_path, '/');

if ($base_path !== '' && str_starts_with($requested_admin_path, $base_path . '/')) {
    $admin_redirect_target = ltrim(substr($requested_admin_path, strlen($base_path)), '/');
}

$admin_redirect_query = $_SERVER['QUERY_STRING'] ?? '';
if ($admin_redirect_query !== '') {
    $admin_redirect_target .= '?' . $admin_redirect_query;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ' . site_url('general/login.php?redirect=' . urlencode($admin_redirect_target)));
    exit;
}

if (!isset($_SESSION['admin_role'])) {
    session_destroy();
    header('Location: ' . site_url('general/login.php?redirect=' . urlencode($admin_redirect_target)));
    exit;
}

ensure_user_auth_schema($conn);

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

admin_require_csrf();
?>
