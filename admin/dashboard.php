<?php
require_once 'auth_check.php';

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
$stats['store_orders'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM store_orders"))['count'] ?? 0;
$stats['pending_store_orders'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM store_orders WHERE status = 'Pending'"))['count'] ?? 0;
$stats['busy_rooms'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rooms WHERE status = 'Busy'"))['count'] ?? 0;
$stats['pending_menu_orders'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT b.id) as count FROM booking_items bi INNER JOIN bookings b ON bi.booking_id = b.id WHERE b.status IN ('Pending', 'Confirmed') AND b.booking_date >= CURDATE()"))['count'] ?? 0;
$stats['pending_orders_total'] = (int)$stats['pending_bookings'] + (int)$stats['pending_store_orders'] + (int)$stats['pending_menu_orders'];
$stats['loyalty_points_balance'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(loyalty_points), 0) as total FROM site_users"))['total'] ?? 0;
$booking_points = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(loyalty_points_earned), 0) as earned, IFNULL(SUM(loyalty_points_redeemed), 0) as redeemed FROM bookings")) ?: ['earned' => 0, 'redeemed' => 0];
$store_points = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(loyalty_points_earned), 0) as earned, IFNULL(SUM(loyalty_points_redeemed), 0) as redeemed FROM store_orders")) ?: ['earned' => 0, 'redeemed' => 0];
$stats['loyalty_points_earned'] = (int)$booking_points['earned'] + (int)$store_points['earned'];
$stats['loyalty_points_redeemed'] = (int)$booking_points['redeemed'] + (int)$store_points['redeemed'];
$booking_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(paid_amount), 0) as total FROM bookings WHERE payment_status = 'Paid'"))['total'] ?? 0;
$store_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(paid_amount), 0) as total FROM store_orders WHERE payment_status = 'Paid'"))['total'] ?? 0;
$stats['paid_revenue'] = (float)$booking_revenue + (float)$store_revenue;
$today_booking_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(paid_amount), 0) as total FROM bookings WHERE payment_status = 'Paid' AND booking_date = CURDATE()"))['total'] ?? 0;
$today_store_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(paid_amount), 0) as total FROM store_orders WHERE payment_status = 'Paid' AND DATE(created_at) = CURDATE()"))['total'] ?? 0;
$stats['today_paid_revenue'] = (float)$today_booking_revenue + (float)$today_store_revenue;
$stats['unread_notifications'] = count_unread_admin_notifications($conn);

$today = date('Y-m-d');
$today_bookings = mysqli_query($conn, "SELECT b.*, r.room_name FROM bookings b LEFT JOIN rooms r ON b.room_id = r.id WHERE DATE(b.booking_date) = '$today' ORDER BY b.start_time DESC");

$room_statuses = [];
$room_status_result = mysqli_query(
    $conn,
    "SELECT
        r.id,
        r.room_name,
        r.room_type,
        r.status,
        active_booking.customer_name,
        active_booking.start_time,
        active_booking.hours
     FROM rooms r
     LEFT JOIN bookings active_booking
        ON active_booking.room_id = r.id
        AND active_booking.status IN ('Pending', 'Confirmed')
        AND active_booking.booking_date = CURDATE()
        AND CURTIME() >= active_booking.start_time
        AND CURTIME() < ADDTIME(active_booking.start_time, SEC_TO_TIME(active_booking.hours * 3600))
     ORDER BY FIELD(r.status, 'Busy', 'Available'), r.room_name ASC"
);
if ($room_status_result) {
    $room_statuses = mysqli_fetch_all($room_status_result, MYSQLI_ASSOC);
}

$management_cards = [
    [
        'url' => 'bookings_full_crud.php',
        'label' => t('admin_nav_bookings'),
        'value' => $stats['pending_bookings'],
        'meta' => t('admin_dashboard_pending_bookings')
    ],
    [
        'url' => 'rooms_full_crud.php',
        'label' => t('admin_nav_rooms'),
        'value' => $stats['available_rooms'] . '/' . $stats['total_rooms'],
        'meta' => t('admin_dashboard_available_rooms')
    ],
    [
        'url' => 'store_orders.php',
        'label' => t('admin_nav_store_orders'),
        'value' => $stats['store_orders'],
        'meta' => t('admin_store_orders_subtitle')
    ],
    [
        'url' => 'complaints_full_crud.php',
        'label' => t('admin_nav_complaints'),
        'value' => $stats['total_complaints'],
        'meta' => t('admin_complaints_management')
    ],
    [
        'url' => 'customer_tickets.php',
        'label' => t('admin_nav_customer_tickets'),
        'value' => $stats['customer_tickets'],
        'meta' => t('admin_dashboard_customer_tickets')
    ]
];

if (isAdmin()) {
    $management_cards[] = [
        'url' => 'menu_items.php',
        'label' => t('admin_nav_menu_items'),
        'value' => $stats['menu_items'],
        'meta' => t('dashboard_menu_title')
    ];
    $management_cards[] = [
        'url' => 'store_products.php',
        'label' => t('admin_nav_store_products'),
        'value' => $stats['store_products'],
        'meta' => t('admin_dashboard_store_products')
    ];
    $management_cards[] = [
        'url' => 'employees.php',
        'label' => t('admin_nav_employees'),
        'value' => $stats['total_admins'],
        'meta' => t('admin_dashboard_total_admins')
    ];
}

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

            <div class="dashboard-stats admin-overview-stats">
                <a href="rooms_full_crud.php" class="stat-card admin-stat-link admin-overview-widget">
                    <h3><?php echo t('status_busy'); ?> / <?php echo t('admin_nav_rooms'); ?></h3>
                    <div class="stat-number"><?php echo $stats['busy_rooms']; ?></div>
                </a>
                <a href="bookings_full_crud.php" class="stat-card admin-stat-link admin-overview-widget">
                    <h3><?php echo t('admin_dashboard_pending_orders'); ?></h3>
                    <div class="stat-number"><?php echo $stats['pending_orders_total']; ?></div>
                </a>
                <a href="notifications.php" class="stat-card admin-stat-link admin-overview-widget">
                    <h3><?php echo t('admin_notifications'); ?></h3>
                    <div class="stat-number"><?php echo $stats['unread_notifications']; ?></div>
                </a>
                <div class="stat-card admin-overview-widget admin-revenue-widget">
                    <h3><?php echo t('admin_dashboard_paid_revenue'); ?> - <?php echo date('Y-m-d'); ?></h3>
                    <div class="stat-number"><?php echo number_format((float)$stats['today_paid_revenue'], 2); ?></div>
                </div>
            </div>

            <div class="admin-dashboard-overview">
                <section class="admin-overview-panel">
                    <div class="admin-panel-heading">
                        <span><?php echo t('admin_nav_rooms'); ?></span>
                        <h2><?php echo t('status_busy_now'); ?></h2>
                    </div>

                    <div class="admin-room-status-grid">
                        <?php foreach ($room_statuses as $room_status): ?>
                            <?php
                            $active_room_booking = !empty($room_status['customer_name']);
                            $room_display_status = $active_room_booking ? 'Busy' : $room_status['status'];
                            $room_status_key = strtolower((string)$room_display_status);
                            $room_status_class = preg_replace('/[^a-z0-9_-]+/i', '-', $room_status_key);
                            $room_time_label = '';
                            if ($active_room_booking) {
                                $room_start_timestamp = strtotime($room_status['start_time']);
                                $room_end_timestamp = $room_start_timestamp + ((int)$room_status['hours'] * 3600);
                                $room_time_label = format_time($room_status['start_time']) . ' - ' . date('g:i A', $room_end_timestamp);
                            }
                            ?>
                            <article class="admin-room-status-card status-<?php echo htmlspecialchars($room_status_class, ENT_QUOTES, 'UTF-8'); ?>">
                                <div>
                                    <span><?php echo htmlspecialchars($room_status['room_type']); ?></span>
                                    <h3><?php echo htmlspecialchars($room_status['room_name']); ?></h3>
                                </div>
                                <strong><?php echo htmlspecialchars(t('status_' . $room_status_key, [], $room_display_status)); ?></strong>
                                <?php if ($active_room_booking): ?>
                                    <p><?php echo htmlspecialchars($room_status['customer_name']); ?> · <?php echo htmlspecialchars($room_time_label); ?></p>
                                <?php else: ?>
                                    <p><?php echo t('admin_dashboard_available_rooms'); ?></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-overview-panel">
                    <div class="admin-panel-heading">
                        <span><?php echo t('admin_dashboard_heading'); ?></span>
                        <h2><?php echo t('admin_nav_dashboard'); ?></h2>
                    </div>

                    <div class="admin-management-grid">
                        <?php foreach ($management_cards as $card): ?>
                            <a href="<?php echo htmlspecialchars($card['url']); ?>" class="admin-management-card">
                                <span><?php echo $card['label']; ?></span>
                                <strong><?php echo htmlspecialchars((string)$card['value']); ?></strong>
                                <p><?php echo $card['meta']; ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <section class="admin-overview-panel admin-loyalty-report">
                <div class="admin-panel-heading">
                    <span><?php echo t('admin_dashboard_secondary_report'); ?></span>
                    <h2><?php echo t('admin_dashboard_loyalty_points'); ?></h2>
                </div>
                <div class="admin-loyalty-report-grid">
                    <div>
                        <span><?php echo t('loyalty_points'); ?></span>
                        <strong><?php echo (int)$stats['loyalty_points_balance']; ?></strong>
                    </div>
                    <div>
                        <span><?php echo t('admin_loyalty_earned'); ?></span>
                        <strong><?php echo (int)$stats['loyalty_points_earned']; ?></strong>
                    </div>
                    <div>
                        <span><?php echo t('admin_loyalty_redeemed'); ?></span>
                        <strong><?php echo (int)$stats['loyalty_points_redeemed']; ?></strong>
                    </div>
                </div>
            </section>

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
