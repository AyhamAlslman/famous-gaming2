<?php
require_once dirname(__DIR__) . '/includes/config.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

header('Location: ' . site_url('general/login.php?redirect=admin/dashboard.php'));
exit;
?>
