<?php
require_once 'auth_check.php';
require_once '../includes/config.php';

admin_require_csrf();
ensure_admin_notifications_table($conn);

$action = $_POST['action'] ?? '';
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
$redirect_input = $_POST['redirect_url'] ?? 'dashboard.php';
$parsed_redirect = parse_url($redirect_input);
$redirect_path = basename($parsed_redirect['path'] ?? 'dashboard.php');
$redirect_query = isset($parsed_redirect['query']) && $parsed_redirect['query'] !== '' ? '?' . $parsed_redirect['query'] : '';

if (!preg_match('/^[A-Za-z0-9_.-]+\.php$/', $redirect_path)) {
    $redirect_path = 'dashboard.php';
    $redirect_query = '';
}

if ($action === 'mark_read' && $notification_id > 0) {
    mark_admin_notification_read($conn, $notification_id);
} elseif ($action === 'mark_all_read') {
    mark_all_admin_notifications_read($conn);
}

header('Location: ' . $redirect_path . $redirect_query);
exit;
