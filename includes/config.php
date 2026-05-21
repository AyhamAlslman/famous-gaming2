<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Amman');

require_once __DIR__ . '/lang.php';

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'playroom_db';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>
