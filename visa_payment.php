<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    header('Location: my_bookings.php');
    exit;
}

header('Location: payment.php?booking_id=' . $booking_id . '&method=Visa');
exit;
?>
