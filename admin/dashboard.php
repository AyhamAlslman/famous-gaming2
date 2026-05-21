<?php
require_once 'auth_check.php';
include '../includes/config.php';
require_once '../includes/functions.php';

ensure_user_auth_schema($conn);

$stats = [];
$stats['total_rooms'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rooms"))['count'];
$stats['available_rooms'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rooms WHERE status = 'Available'"))['count'];
$stats['total_bookings'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings"))['count'];
$stats['pending_bookings'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending'"))['count'];
$stats['customer_tickets'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE booking_code IS NOT NULL OR customer_session_token IS NOT NULL"))['count'];
$stats['total_complaints'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM complaints"))['count'];
$stats['total_admins'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM admins"))['count'];
$stats['total_users'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM site_users"))['count'] ?? 0;
$stats['menu_items'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM menu_items"))['count'] ?? 0;
$stats['store_products'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM store_products"))['count'] ?? 0;
$stats['paid_revenue'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(paid_amount), 0) as total FROM bookings WHERE payment_status = 'Paid'"))['total'] ?? 0;
$stats['unread_notifications'] = count_unread_admin_notifications($conn);

$today = date('Y-m-d');
$today_bookings = mysqli_query($conn, "SELECT b.*, r.room_name FROM bookings b LEFT JOIN rooms r ON b.room_id = r.id WHERE DATE(b.booking_date) = '$today' ORDER BY b.start_time DESC");

$success_message = isset($_GET['success']) ? $_GET['success'] : '';

$page_title = t('admin_dashboard_page_title');
$active_page = 'dashboard';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1><?php echo t('admin_dashboard_heading'); ?></h1>
                <div class="user-info">
                    <?php echo t('admin_dashboard_welcome', [
                        'name' => $_SESSION['admin_full_name'],
                        'role' => t('admin_role_' . strtolower($_SESSION['admin_role']), [], ucfirst($_SESSION['admin_role']))
                    ]); ?>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo t('admin_dashboard_total_rooms'); ?></h3>
                    <div class="stat-number"><?php echo $stats['total_rooms']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php echo t('admin_dashboard_available_rooms'); ?></h3>
                    <div class="stat-number"><?php echo $stats['available_rooms']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php echo t('admin_dashboard_total_bookings'); ?></h3>
                    <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php echo t('admin_dashboard_pending_bookings'); ?></h3>
                    <div class="stat-number"><?php echo $stats['pending_bookings']; ?></div>
                </div>
                <a href="customer_tickets.php" class="stat-card admin-stat-link">
                    <h3><?php echo t('admin_dashboard_customer_tickets'); ?></h3>
                    <div class="stat-number"><?php echo $stats['customer_tickets']; ?></div>
                </a>
                <div class="stat-card">
                    <h3><?php echo t('admin_dashboard_total_complaints'); ?></h3>
                    <div class="stat-number"><?php echo $stats['total_complaints']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php echo t('admin_dashboard_total_users'); ?></h3>
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                </div>
                <?php if (isAdmin()): ?>
                    <a href="menu_items.php" class="stat-card admin-stat-link">
                        <h3><?php echo t('admin_dashboard_menu_items'); ?></h3>
                        <div class="stat-number"><?php echo $stats['menu_items']; ?></div>
                    </a>
                    <a href="store_products.php" class="stat-card admin-stat-link">
                        <h3><?php echo t('admin_dashboard_store_products'); ?></h3>
                        <div class="stat-number"><?php echo $stats['store_products']; ?></div>
                    </a>
                <?php endif; ?>
                <a href="notifications.php" class="stat-card admin-stat-link">
                    <h3><?php echo t('admin_notifications'); ?></h3>
                    <div class="stat-number"><?php echo $stats['unread_notifications']; ?></div>
                </a>
                <div class="stat-card">
                    <h3><?php echo t('admin_dashboard_paid_revenue'); ?></h3>
                    <div class="stat-number"><?php echo number_format((float)$stats['paid_revenue'], 2); ?></div>
                </div>
                <?php if (isAdmin()): ?>
                <div class="stat-card">
                    <h3><?php echo t('admin_dashboard_total_admins'); ?></h3>
                    <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                </div>
                <?php endif; ?>
            </div>

            <h2 class="section-title"><?php echo t('admin_dashboard_todays_bookings'); ?> (<?php echo date('Y-m-d'); ?>)</h2>

            <div class="table-container">
                <?php if (mysqli_num_rows($today_bookings) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo t('admin_field_id'); ?></th>
                            <th><?php echo t('admin_field_customer_name'); ?></th>
                            <th><?php echo t('admin_field_phone'); ?></th>
                            <th><?php echo t('admin_field_room'); ?></th>
                            <th><?php echo t('admin_field_time'); ?></th>
                            <th><?php echo t('admin_field_duration'); ?></th>
                            <th><?php echo t('admin_field_status'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = mysqli_fetch_assoc($today_bookings)): ?>
                        <tr>
                            <td><?php echo $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                            <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                            <td><?php echo date('h:i A', strtotime($booking['start_time'])); ?></td>
                            <td><?php echo $booking['hours']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo htmlspecialchars(t('status_' . strtolower($booking['status']), [], $booking['status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data"><?php echo t('admin_dashboard_no_bookings_today'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
