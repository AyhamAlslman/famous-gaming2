<?php
require_once 'auth_check.php';

$stats = [];
$stats['total_rooms'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM rooms"))['count'];
$live_room_status_query = "SELECT
        SUM(CASE WHEN current_status = 'Available' THEN 1 ELSE 0 END) AS available_count,
        SUM(CASE WHEN current_status = 'Busy' THEN 1 ELSE 0 END) AS busy_count
    FROM (
        SELECT
            CASE
                WHEN r.status = 'Busy' THEN 'Busy'
                WHEN EXISTS (
                    SELECT 1
                    FROM bookings active_booking
                    WHERE active_booking.room_id = r.id
                      AND active_booking.status IN ('Pending', 'Confirmed')
                      AND active_booking.booking_date = CURDATE()
                      AND CURTIME() >= active_booking.start_time
                      AND CURTIME() < ADDTIME(active_booking.start_time, SEC_TO_TIME(active_booking.hours * 3600))
                ) THEN 'Busy'
                ELSE 'Available'
            END AS current_status
        FROM rooms r
    ) room_statuses";
$live_room_counts = mysqli_fetch_assoc(mysqli_query($conn, $live_room_status_query)) ?: ['available_count' => 0, 'busy_count' => 0];
$stats['available_rooms'] = (int)($live_room_counts['available_count'] ?? 0);
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
$stats['busy_rooms'] = (int)($live_room_counts['busy_count'] ?? 0);
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
        CASE
            WHEN active_booking.id IS NOT NULL OR r.status = 'Busy' THEN 'Busy'
            ELSE 'Available'
        END AS current_status,
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
     ORDER BY FIELD(CASE WHEN active_booking.id IS NOT NULL OR r.status = 'Busy' THEN 'Busy' ELSE 'Available' END, 'Busy', 'Available'), r.room_name ASC"
);
if ($room_status_result) {
    $room_statuses = mysqli_fetch_all($room_status_result, MYSQLI_ASSOC);
}

$daily_revenue_keys = [];
$daily_revenue_labels = [];
$daily_revenue_map = [];
for ($i = 13; $i >= 0; $i--) {
    $timestamp = strtotime('-' . $i . ' days');
    $key = date('Y-m-d', $timestamp);
    $daily_revenue_keys[] = $key;
    $daily_revenue_labels[] = date('M d', $timestamp);
    $daily_revenue_map[$key] = 0.0;
}

$daily_revenue_result = mysqli_query(
    $conn,
    "SELECT revenue_date, SUM(total_paid) AS total_paid
     FROM (
        SELECT booking_date AS revenue_date, SUM(paid_amount) AS total_paid
        FROM bookings
        WHERE payment_status = 'Paid'
          AND paid_amount > 0
          AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY booking_date
        UNION ALL
        SELECT DATE(created_at) AS revenue_date, SUM(paid_amount) AS total_paid
        FROM store_orders
        WHERE payment_status = 'Paid'
          AND paid_amount > 0
          AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY DATE(created_at)
     ) AS paid_revenue
     GROUP BY revenue_date
     ORDER BY revenue_date ASC"
);
if ($daily_revenue_result) {
    while ($row = mysqli_fetch_assoc($daily_revenue_result)) {
        $key = (string)$row['revenue_date'];
        if (array_key_exists($key, $daily_revenue_map)) {
            $daily_revenue_map[$key] = (float)$row['total_paid'];
        }
    }
}

$monthly_revenue_keys = [];
$monthly_revenue_labels = [];
$monthly_revenue_map = [];
for ($i = 5; $i >= 0; $i--) {
    $timestamp = strtotime('first day of -' . $i . ' months');
    $key = date('Y-m-01', $timestamp);
    $monthly_revenue_keys[] = $key;
    $monthly_revenue_labels[] = date('M Y', $timestamp);
    $monthly_revenue_map[$key] = 0.0;
}

$monthly_revenue_result = mysqli_query(
    $conn,
    "SELECT revenue_month, SUM(total_paid) AS total_paid
     FROM (
        SELECT DATE_FORMAT(booking_date, '%Y-%m-01') AS revenue_month, SUM(paid_amount) AS total_paid
        FROM bookings
        WHERE payment_status = 'Paid'
          AND paid_amount > 0
          AND booking_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
        GROUP BY DATE_FORMAT(booking_date, '%Y-%m-01')
        UNION ALL
        SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS revenue_month, SUM(paid_amount) AS total_paid
        FROM store_orders
        WHERE payment_status = 'Paid'
          AND paid_amount > 0
          AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
        GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
     ) AS paid_revenue
     GROUP BY revenue_month
     ORDER BY revenue_month ASC"
);
if ($monthly_revenue_result) {
    while ($row = mysqli_fetch_assoc($monthly_revenue_result)) {
        $key = (string)$row['revenue_month'];
        if (array_key_exists($key, $monthly_revenue_map)) {
            $monthly_revenue_map[$key] = (float)$row['total_paid'];
        }
    }
}

$room_occupancy_labels = [];
$room_occupancy_values = [];
$room_occupancy_result = mysqli_query(
    $conn,
    "SELECT
        r.id,
        r.room_name,
        COUNT(b.id) AS booking_count
     FROM rooms r
     LEFT JOIN bookings b
        ON b.room_id = r.id
        AND b.status IN ('Pending', 'Confirmed', 'Completed')
     GROUP BY r.id, r.room_name
     ORDER BY booking_count DESC, r.room_name ASC
     LIMIT 6"
);
if ($room_occupancy_result) {
    while ($row = mysqli_fetch_assoc($room_occupancy_result)) {
        $room_occupancy_labels[] = $row['room_name'];
        $room_occupancy_values[] = (int)$row['booking_count'];
    }
}

$top_selling_products = [];
$top_products_result = mysqli_query(
    $conn,
    "SELECT
        COALESCE(soi.product_id, 0) AS product_key,
        soi.product_name,
        COALESCE(soi.category, sp.category, '') AS category,
        SUM(soi.quantity) AS units_sold,
        SUM(soi.quantity * soi.item_price) AS sales_total,
        COUNT(DISTINCT soi.order_id) AS orders_count,
        COALESCE(sp.stock_quantity, 0) AS stock_quantity
     FROM store_order_items soi
     INNER JOIN store_orders so ON so.id = soi.order_id
     LEFT JOIN store_products sp ON sp.id = soi.product_id
     WHERE so.payment_status = 'Paid'
       AND so.status IN ('Confirmed', 'Completed')
     GROUP BY COALESCE(soi.product_id, 0), soi.product_name, COALESCE(soi.category, sp.category, ''), sp.stock_quantity
     ORDER BY units_sold DESC, sales_total DESC, soi.product_name ASC
     LIMIT 5"
);
if ($top_products_result) {
    while ($row = mysqli_fetch_assoc($top_products_result)) {
        $units_sold = (int)$row['units_sold'];
        $stock_quantity = (int)$row['stock_quantity'];
        if ($stock_quantity <= 0) {
            $recommendation_key = 'admin_dashboard_recommend_restock_now';
        } elseif ($stock_quantity <= max(3, $units_sold)) {
            $recommendation_key = 'admin_dashboard_recommend_restock';
        } elseif ($units_sold >= 10) {
            $recommendation_key = 'admin_dashboard_recommend_promote';
        } else {
            $recommendation_key = 'admin_dashboard_recommend_bundle';
        }

        $row['recommendation'] = t($recommendation_key);
        $top_selling_products[] = $row;
    }
}

$smart_insights = get_admin_smart_insights($conn);
$smart_popular_times = get_popular_booking_times($conn, 4);
$smart_room_recommendations = get_smart_room_recommendations($conn, 0, 3);
$smart_store_recommendations = get_smart_store_recommendations($conn, 0, 3);

$dashboard_chart_payload = [
    'dailyRevenue' => [
        'labels' => $daily_revenue_labels,
        'values' => array_values($daily_revenue_map),
    ],
    'monthlyRevenue' => [
        'labels' => $monthly_revenue_labels,
        'values' => array_values($monthly_revenue_map),
    ],
    'roomOccupancy' => [
        'labels' => $room_occupancy_labels,
        'values' => $room_occupancy_values,
    ],
    'texts' => [
        'daily' => t('admin_dashboard_daily'),
        'monthly' => t('admin_dashboard_monthly'),
        'paidRevenue' => t('admin_dashboard_paid_revenue'),
        'bookings' => t('admin_dashboard_bookings_count'),
        'chartUnavailable' => t('admin_dashboard_chart_unavailable'),
    ],
    'currency' => 'JOD',
    'isRtl' => site_is_rtl(),
];

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
                            $room_display_status = $room_status['current_status'] ?? ($active_room_booking ? 'Busy' : $room_status['status']);
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

            <section class="admin-overview-panel admin-analytics-panel">
                <div class="admin-panel-heading admin-analytics-heading">
                    <div>
                        <span><?php echo t('admin_dashboard_analytics_report'); ?></span>
                        <h2><?php echo t('admin_dashboard_revenue_trends'); ?></h2>
                    </div>
                    <div class="admin-chart-toggle" role="group" aria-label="<?php echo htmlspecialchars(t('admin_dashboard_revenue_trends'), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="button" class="active" data-revenue-mode="daily"><?php echo t('admin_dashboard_daily'); ?></button>
                        <button type="button" data-revenue-mode="monthly"><?php echo t('admin_dashboard_monthly'); ?></button>
                    </div>
                </div>
                <div class="admin-chart-frame admin-line-chart-frame">
                    <canvas id="adminRevenueChart" aria-label="<?php echo htmlspecialchars(t('admin_dashboard_revenue_trends'), ENT_QUOTES, 'UTF-8'); ?>"></canvas>
                </div>
            </section>

            <div class="admin-dashboard-analytics">
                <section class="admin-overview-panel admin-chart-panel">
                    <div class="admin-panel-heading">
                        <span><?php echo t('admin_dashboard_analytics_report'); ?></span>
                        <h2><?php echo t('admin_dashboard_room_demand'); ?></h2>
                    </div>
                    <div class="admin-chart-frame admin-pie-chart-frame">
                        <canvas id="adminRoomChart" aria-label="<?php echo htmlspecialchars(t('admin_dashboard_room_demand'), ENT_QUOTES, 'UTF-8'); ?>"></canvas>
                    </div>
                </section>

                <section class="admin-overview-panel admin-top-products-panel">
                    <div class="admin-panel-heading">
                        <span><?php echo t('admin_dashboard_smart_recommendations'); ?></span>
                        <h2><?php echo t('admin_dashboard_top_selling_products'); ?></h2>
                    </div>

                    <?php if (empty($top_selling_products)): ?>
                        <div class="no-data"><?php echo t('admin_dashboard_no_product_sales'); ?></div>
                    <?php else: ?>
                        <div class="admin-top-products-list">
                            <?php foreach ($top_selling_products as $index => $product): ?>
                                <article class="admin-top-product-card">
                                    <div class="admin-top-product-rank">#<?php echo $index + 1; ?></div>
                                    <div class="admin-top-product-main">
                                        <span><?php echo htmlspecialchars($product['category'] ?: t('admin_product'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <h3><?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p><?php echo htmlspecialchars($product['recommendation'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                    <div class="admin-top-product-metrics">
                                        <div>
                                            <span><?php echo t('admin_dashboard_units_sold'); ?></span>
                                            <strong><?php echo (int)$product['units_sold']; ?></strong>
                                        </div>
                                        <div>
                                            <span><?php echo t('admin_dashboard_product_revenue'); ?></span>
                                            <strong><?php echo number_format((float)$product['sales_total'], 2); ?> JOD</strong>
                                        </div>
                                        <div>
                                            <span><?php echo t('admin_field_stock'); ?></span>
                                            <strong><?php echo (int)$product['stock_quantity']; ?></strong>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="admin-overview-panel admin-ai-insights-panel">
                <div class="admin-panel-heading">
                    <span><?php echo t('admin_dashboard_smart_recommendations'); ?></span>
                    <h2><?php echo htmlspecialchars(smart_i18n('insight_reports_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                </div>

                <div class="admin-ai-insights-grid">
                    <?php foreach ($smart_insights as $insight): ?>
                        <article class="admin-ai-insight-card">
                            <span><?php echo htmlspecialchars($insight['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <strong><?php echo htmlspecialchars((string)$insight['value'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?php echo htmlspecialchars($insight['text'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="admin-ai-lists-grid">
                    <div class="admin-ai-list">
                        <h3><?php echo htmlspecialchars(smart_i18n('insight_peak_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <?php if (empty($smart_popular_times)): ?>
                            <p><?php echo htmlspecialchars(smart_i18n('no_data'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php else: ?>
                            <?php foreach ($smart_popular_times as $time): ?>
                                <div><span><?php echo htmlspecialchars($time['time'], ENT_QUOTES, 'UTF-8'); ?></span><strong><?php echo (int)$time['count']; ?></strong></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="admin-ai-list">
                        <h3><?php echo htmlspecialchars(smart_i18n('insight_room_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <?php foreach ($smart_room_recommendations as $room): ?>
                            <div>
                                <span><?php echo htmlspecialchars($room['room_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong><?php echo htmlspecialchars($room['room_type'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="admin-ai-list">
                        <h3><?php echo htmlspecialchars(smart_i18n('insight_store_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <?php foreach ($smart_store_recommendations as $product): ?>
                            <div>
                                <span><?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong><?php echo number_format((float)$product['price'], 2); ?> JOD</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

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

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        const dashboardCharts = <?php echo json_encode($dashboard_chart_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const textColor = '#d6e8f5';
        const mutedColor = 'rgba(214, 232, 245, 0.58)';
        const gridColor = 'rgba(169, 216, 245, 0.14)';
        const neonPalette = ['#4edfff', '#5effcf', '#ffd166', '#ff5f7b', '#9aa9ff', '#ff9f6e'];

        function formatCurrency(value) {
            return Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' ' + dashboardCharts.currency;
        }

        function showChartUnavailable() {
            if (typeof window.showAdminMessage === 'function') {
                window.showAdminMessage(dashboardCharts.texts.chartUnavailable);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                showChartUnavailable();
                return;
            }

            Chart.defaults.color = textColor;
            Chart.defaults.font.family = "'Segoe UI', Tahoma, Arial, sans-serif";

            const revenueCanvas = document.getElementById('adminRevenueChart');
            const roomCanvas = document.getElementById('adminRoomChart');
            let revenueChart = null;

            function makeRevenueConfig(mode) {
                const activeData = mode === 'monthly' ? dashboardCharts.monthlyRevenue : dashboardCharts.dailyRevenue;

                return {
                    type: 'line',
                    data: {
                        labels: activeData.labels,
                        datasets: [{
                            label: dashboardCharts.texts.paidRevenue,
                            data: activeData.values,
                            borderColor: '#4edfff',
                            backgroundColor: 'rgba(78, 223, 255, 0.16)',
                            fill: true,
                            tension: 0.36,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#5effcf',
                            pointBorderColor: '#03111b',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    boxWidth: 12,
                                    color: textColor
                                },
                                rtl: dashboardCharts.isRtl
                            },
                            tooltip: {
                                rtl: dashboardCharts.isRtl,
                                callbacks: {
                                    label: function (context) {
                                        return dashboardCharts.texts.paidRevenue + ': ' + formatCurrency(context.parsed.y);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    color: 'transparent'
                                },
                                ticks: {
                                    color: mutedColor
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: mutedColor,
                                    callback: function (value) {
                                        return Number(value).toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                };
            }

            function renderRevenueChart(mode) {
                if (!revenueCanvas) {
                    return;
                }

                if (revenueChart) {
                    revenueChart.destroy();
                }

                revenueChart = new Chart(revenueCanvas, makeRevenueConfig(mode));
            }

            renderRevenueChart('daily');

            document.querySelectorAll('[data-revenue-mode]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const mode = button.dataset.revenueMode === 'monthly' ? 'monthly' : 'daily';
                    document.querySelectorAll('[data-revenue-mode]').forEach(function (item) {
                        item.classList.toggle('active', item === button);
                    });
                    renderRevenueChart(mode);
                });
            });

            if (roomCanvas) {
                new Chart(roomCanvas, {
                    type: 'pie',
                    data: {
                        labels: dashboardCharts.roomOccupancy.labels,
                        datasets: [{
                            label: dashboardCharts.texts.bookings,
                            data: dashboardCharts.roomOccupancy.values,
                            backgroundColor: neonPalette.map(function (color) {
                                return color + 'cc';
                            }),
                            borderColor: 'rgba(5, 9, 18, 0.94)',
                            borderWidth: 2,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: textColor,
                                    padding: 14,
                                    boxWidth: 12
                                },
                                rtl: dashboardCharts.isRtl
                            },
                            tooltip: {
                                rtl: dashboardCharts.isRtl,
                                callbacks: {
                                    label: function (context) {
                                        return context.label + ': ' + context.parsed + ' ' + dashboardCharts.texts.bookings;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    })();
    </script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
