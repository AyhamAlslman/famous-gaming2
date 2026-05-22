<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    header('Location: ' . site_url('user/my_bookings.php'));
    exit;
}

header('Location: ' . site_url('user/payment.php?booking_id=' . $booking_id . '&method=Visa'));
exit;
?>
