<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw_body = file_get_contents('php://input');
$payload = json_decode($raw_body ?: '[]', true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$message = sanitize_input($payload['message'] ?? '');
$site_user_id = isset($_SESSION['site_user_id']) ? (int)$_SESSION['site_user_id'] : 0;

if ($message === '') {
    $message = smart_i18n('chat_default');
}

$response = build_support_chatbot_response($conn, $message, $site_user_id);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

mysqli_close($conn);
