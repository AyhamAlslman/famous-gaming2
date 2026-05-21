<?php
require_once 'includes/config.php';

unset(
    $_SESSION['site_user_id'],
    $_SESSION['site_user_name'],
    $_SESSION['site_user_role'],
    $_SESSION['site_user_loyalty_points'],
    $_SESSION['customer_booking_token'],
    $_SESSION['customer_name'],
    $_SESSION['customer_phone']
);

header('Location: login.php?logged_out=1');
exit;
?>
