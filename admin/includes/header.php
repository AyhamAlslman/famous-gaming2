<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>FAMOUS GAMING - Admin Panel</title>
    <link rel="stylesheet" href="css/admin.css?v=1.1">

    <link rel="icon" type="image/x-icon" href="../images/favicon.png">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <h2>FAMOUS GAMING - Admin</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'class="active"' : ''; ?>>Dashboard</a></li>
                <li><a href="bookings_full_crud.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'bookings_full_crud.php' || basename($_SERVER['PHP_SELF']) == 'booking_details.php') ? 'class="active"' : ''; ?>>Bookings</a></li>
                <li><a href="rooms_full_crud.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'rooms_full_crud.php') ? 'class="active"' : ''; ?>>Rooms</a></li>
                <li><a href="menu_items.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'menu_items.php') ? 'class="active"' : ''; ?>>Menu Items</a></li>
                <li><a href="store_products.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'store_products.php') ? 'class="active"' : ''; ?>>Store Products</a></li>
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                <li><a href="employees.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'employees.php') ? 'class="active"' : ''; ?>>Employees</a></li>
                <?php endif; ?>
                <li><a href="complaints_full_crud.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'complaints_full_crud.php') ? 'class="active"' : ''; ?>>Complaints</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>
