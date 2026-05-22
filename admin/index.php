<?php
/**
 * Admin Index - Redirects to Dashboard
 *
 * This file redirects users to the appropriate page:
 * - If logged in: Redirect to dashboard
 * - If not logged in: Redirect to login page
 */

require_once dirname(__DIR__) . '/includes/config.php';

// Check if admin is logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Redirect to dashboard
    header('Location: dashboard.php');
    exit();
} else {
    // Redirect to unified login page
    header('Location: ' . site_url('auth/login.php?redirect=admin/dashboard.php'));
    exit();
}
?>
