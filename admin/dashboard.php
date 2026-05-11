<?php
require_once 'auth_check.php';
include '../includes/config.php';

$stats = [];
$stats['total_rooms'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rooms"))['count'];
$stats['available_rooms'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rooms WHERE status = 'Available'"))['count'];
$stats['total_bookings'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings"))['count'];
$stats['pending_bookings'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending'"))['count'];
$stats['total_complaints'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM complaints"))['count'];
$stats['total_admins'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM admins"))['count'];

$today = date('Y-m-d');
$today_bookings = mysqli_query($conn, "SELECT b.*, r.room_name FROM bookings b LEFT JOIN rooms r ON b.room_id = r.id WHERE DATE(b.booking_date) = '$today' ORDER BY b.start_time DESC");

$success_message = isset($_GET['success']) ? $_GET['success'] : '';

$page_title = 'Dashboard';
$active_page = 'dashboard';
include 'includes/header.php';
?>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    Welcome, <?php echo $_SESSION['admin_full_name']; ?> (<?php echo ucfirst($_SESSION['admin_role']); ?>)
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Total Rooms</h3>
                    <div class="stat-number"><?php echo $stats['total_rooms']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Available Rooms</h3>
                    <div class="stat-number"><?php echo $stats['available_rooms']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Bookings</h3>
                    <div class="stat-number"><?php echo $stats['pending_bookings']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Complaints</h3>
                    <div class="stat-number"><?php echo $stats['total_complaints']; ?></div>
                </div>
                <?php if (isAdmin()): ?>
                <div class="stat-card">
                    <h3>Total Admins</h3>
                    <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                </div>
                <?php endif; ?>
            </div>

            <h2 class="section-title">Today's Bookings (<?php echo date('Y-m-d'); ?>)</h2>

            <div class="table-container">
                <?php if (mysqli_num_rows($today_bookings) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Room</th>
                            <th>Time</th>
                            <th>Duration (hours)</th>
                            <th>Status</th>
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
                                    <?php echo $booking['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">No bookings for today</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
