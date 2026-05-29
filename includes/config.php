<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Amman');
mysqli_report(MYSQLI_REPORT_OFF);

if (!defined('SITE_ROOT_PATH')) {
    define('SITE_ROOT_PATH', dirname(__DIR__));
}

if (!defined('SITE_BASE_PATH')) {
    $document_root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
    $project_root = realpath(SITE_ROOT_PATH);
    $base_path = '';

    if ($document_root && $project_root) {
        $document_root = str_replace('\\', '/', $document_root);
        $project_root = str_replace('\\', '/', $project_root);

        if (strpos($project_root, $document_root) === 0) {
            $base_path = substr($project_root, strlen($document_root));
        }
    }

    $base_path = '/' . trim(str_replace('\\', '/', $base_path), '/');
    define('SITE_BASE_PATH', $base_path === '/' ? '' : $base_path);
}

if (!function_exists('site_url')) {
    function site_url($path = '') {
        $base = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '';
        $path = ltrim((string)$path, '/');

        return rtrim($base, '/') . ($path !== '' ? '/' . $path : '/');
    }
}

require_once __DIR__ . '/lang.php';

$db_hosts = [
    ['host' => '127.0.0.1', 'port' => 3306],
    ['host' => 'localhost', 'port' => 3306],
    ['host' => '127.0.0.1', 'port' => 3307],
    ['host' => 'localhost', 'port' => 3307],
];
$db_user = 'root';
$db_pass = '';
$db_name = 'playroom_db';

mysqli_report(MYSQLI_REPORT_OFF);

$conn = false;
$last_db_error = 'Unknown database connection error.';

foreach ($db_hosts as $db_target) {
    $conn = @mysqli_connect($db_target['host'], $db_user, $db_pass, $db_name, $db_target['port']);

    if ($conn) {
        break;
    }

    $last_db_error = mysqli_connect_error();
}

if (!$conn && mysqli_connect_errno()) {
    $fallback_db_host = $db_hosts[0]['host'];
    $fallback_db_port = $db_hosts[0]['port'];
    $server_conn = @mysqli_connect($fallback_db_host, $db_user, $db_pass, '', $fallback_db_port);

    if ($server_conn) {
        mysqli_query($server_conn, "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        mysqli_close($server_conn);
        $conn = @mysqli_connect($fallback_db_host, $db_user, $db_pass, $db_name, $fallback_db_port);
    }
}

if (!$conn) {
    die("Database connection failed: " . $last_db_error);
}

mysqli_set_charset($conn, "utf8mb4");

require_once __DIR__ . '/functions.php';

ensure_user_auth_schema($conn);
?>
