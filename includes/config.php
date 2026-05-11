<?php
$db_host = 'localhost:3307';
$db_user = 'root';
$db_pass = '';
$db_name = 'playroom_db';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>
