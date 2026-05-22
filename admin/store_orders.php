<?php
require_once 'auth_check.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

ensure_store_orders_schema($conn);

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';
$selected_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$allowed_order_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
$allowed_payment_statuses = ['Unpaid', 'Partial', 'Paid'];
$allowed_payment_methods = ['Cash', 'Visa', 'CliQ', 'Loyalty'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();

    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'update_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $status = sanitize_input($_POST['status'] ?? 'Pending');
        $payment_status = sanitize_input($_POST['payment_status'] ?? 'Unpaid');
        $payment_method = sanitize_input($_POST['payment_method'] ?? 'Cash');
        $paid_amount = max(0, (float)($_POST['paid_amount'] ?? 0));
        $points_to_redeem = max(0, (int)($_POST['loyalty_points_to_redeem'] ?? 0));

        if ($order_id <= 0) {
            $error_message = t('store_order_missing');
        } elseif (!in_array($status, $allowed_order_statuses, true)) {
            $error_message = t('store_order_status_invalid');
        } elseif (!in_array($payment_status, $allowed_payment_statuses, true)) {
            $error_message = t('payment_method_invalid');
        } elseif (!in_array($payment_method, $allowed_payment_methods, true)) {
            $error_message = t('payment_method_invalid');
        } else {
            $stmt = mysqli_prepare($conn, "SELECT * FROM store_orders WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $order = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$order) {
                $error_message = t('store_order_missing');
            } else {
                $current_total = (float)$order['total_amount'];
                $redeemed = ['points' => 0, 'discount' => 0.0];

                mysqli_begin_transaction($conn);

                if ($points_to_redeem > 0) {
                    $redeemed = redeem_loyalty_points($conn, (int)$order['user_id'], $points_to_redeem, $current_total);
                    $current_total = max(0, $current_total - $redeemed['discount']);
                }
                $redeemed_points = (int)$redeemed['points'];
                $redeemed_discount = (float)$redeemed['discount'];

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE store_orders
                     SET status = ?,
                         payment_status = ?,
                         payment_method = ?,
                         paid_amount = ?,
                         loyalty_points_redeemed = loyalty_points_redeemed + ?,
                         loyalty_discount = loyalty_discount + ?,
                         total_amount = ?
                     WHERE id = ?"
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    "sssdiddi",
                    $status,
                    $payment_status,
                    $payment_method,
                    $paid_amount,
                    $redeemed_points,
                    $redeemed_discount,
                    $current_total,
                    $order_id
                );

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_commit($conn);
                    $success_message = t('store_order_update_success');
                    $selected_order_id = $order_id;
                    create_admin_notification(
                        $conn,
                        'store_order_updated',
                        'Store order updated',
                        'Store order ' . $order['order_code'] . ' was updated.',
                        'store_orders',
                        $order_id,
                        'store_orders.php?order_id=' . $order_id
                    );
                    create_site_notification(
                        $conn,
                        (int)$order['user_id'],
                        'store_order_updated',
                        t('store_order_update_success'),
                        t('store_order_user_update'),
                        'user/my_bookings.php'
                    );
                    log_admin_action($conn, $_SESSION['admin_id'], 'UPDATE_STORE_ORDER', 'store_orders', $order_id);
                } else {
                    mysqli_rollback($conn);
                    $error_message = t('store_order_update_error');
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}

$orders_query = "SELECT so.*, su.full_name, su.email, su.loyalty_points
                 FROM store_orders so
                 LEFT JOIN site_users su ON so.user_id = su.id
                 ORDER BY so.created_at DESC, so.id DESC";
$orders = mysqli_query($conn, $orders_query);

$selected_order = null;
$selected_order_items = [];
if ($selected_order_id > 0) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT so.*, su.full_name, su.email, su.loyalty_points
         FROM store_orders so
         LEFT JOIN site_users su ON so.user_id = su.id
         WHERE so.id = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $selected_order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $selected_order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($selected_order) {
        $selected_order_items = get_store_order_items($conn, $selected_order_id);
    }
}

$page_title = t('admin_store_orders_management');
$active_page = 'store_orders';
include 'includes/header.php';
?>

<div class="content">
    <div class="container">
        <div class="page-header">
            <div>
                <h1><?php echo t('admin_store_orders_management'); ?></h1>
                <p class="admin-page-subtitle"><?php echo t('admin_store_orders_subtitle'); ?></p>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($selected_order): ?>
            <div class="grid-2 admin-store-order-detail-grid">
                <div class="card">
                    <h2><?php echo t('store_order_code'); ?> <?php echo htmlspecialchars($selected_order['order_code']); ?></h2>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('common_customer'); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($selected_order['customer_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('common_phone'); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($selected_order['phone']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('auth_email'); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($selected_order['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php echo t('loyalty_points'); ?>:</span>
                        <span class="info-value"><?php echo (int)$selected_order['loyalty_points']; ?></span>
                    </div>

                    <div class="admin-order-items">
                        <?php foreach ($selected_order_items as $item): ?>
                            <div>
                                <span><?php echo htmlspecialchars($item['product_name']); ?> x<?php echo (int)$item['quantity']; ?></span>
                                <strong><?php echo number_format((float)$item['item_total'], 2); ?> JOD</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card">
                    <h2><?php echo t('admin_update_payment'); ?></h2>
                    <div class="total-summary">
                        <div class="total-row">
                            <span><?php echo t('store_basket_summary_subtotal'); ?>:</span>
                            <span><?php echo number_format((float)$selected_order['subtotal'], 2); ?> JOD</span>
                        </div>
                        <div class="total-row">
                            <span><?php echo t('loyalty_discount'); ?>:</span>
                            <span><?php echo number_format((float)$selected_order['loyalty_discount'], 2); ?> JOD</span>
                        </div>
                        <div class="total-row final">
                            <span><?php echo t('admin_total_label'); ?>:</span>
                            <span><?php echo number_format((float)$selected_order['total_amount'], 2); ?> JOD</span>
                        </div>
                    </div>

                    <form method="POST" class="admin-store-order-form">
                        <input type="hidden" name="action" value="update_order">
                        <input type="hidden" name="order_id" value="<?php echo (int)$selected_order['id']; ?>">
                        <?php echo admin_csrf_input(); ?>

                        <div class="form-group">
                            <label><?php echo t('admin_field_status'); ?></label>
                            <select name="status" required>
                                <?php foreach ($allowed_order_statuses as $status_option): ?>
                                    <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo $selected_order['status'] === $status_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(t('status_' . strtolower($status_option), [], $status_option)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?php echo t('admin_payment_status'); ?></label>
                            <select name="payment_status" required>
                                <?php foreach ($allowed_payment_statuses as $payment_status_option): ?>
                                    <option value="<?php echo htmlspecialchars($payment_status_option); ?>" <?php echo $selected_order['payment_status'] === $payment_status_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(t('status_' . strtolower($payment_status_option), [], $payment_status_option)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?php echo t('admin_payment_method'); ?></label>
                            <select name="payment_method" required>
                                <?php foreach ($allowed_payment_methods as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method); ?>" <?php echo $selected_order['payment_method'] === $method ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(t('payment_' . strtolower($method), [], $method)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?php echo t('admin_paid_amount'); ?> (JOD)</label>
                            <input type="number" step="0.01" min="0" name="paid_amount" value="<?php echo htmlspecialchars($selected_order['paid_amount']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label><?php echo t('loyalty_redeem_points'); ?></label>
                            <input type="number" min="0" max="<?php echo (int)$selected_order['loyalty_points']; ?>" name="loyalty_points_to_redeem" value="0">
                            <small><?php echo t('loyalty_redeem_hint'); ?></small>
                        </div>

                        <button type="submit" class="btn btn-success" style="width: 100%;"><?php echo t('admin_action_update'); ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <?php if ($orders && mysqli_num_rows($orders) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo t('store_order_code'); ?></th>
                            <th><?php echo t('common_customer'); ?></th>
                            <th><?php echo t('common_total'); ?></th>
                            <th><?php echo t('admin_payment_status'); ?></th>
                            <th><?php echo t('admin_payment_method'); ?></th>
                            <th><?php echo t('admin_field_status'); ?></th>
                            <th><?php echo t('common_date'); ?></th>
                            <th><?php echo t('admin_field_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_code']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                    <span class="admin-muted"><?php echo htmlspecialchars($order['phone']); ?></span>
                                </td>
                                <td><?php echo number_format((float)$order['total_amount'], 2); ?> JOD</td>
                                <td>
                                    <span class="status-badge payment-<?php echo strtolower(htmlspecialchars($order['payment_status'])); ?>">
                                        <?php echo htmlspecialchars(t('status_' . strtolower($order['payment_status']), [], $order['payment_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(t('payment_' . strtolower($order['payment_method']), [], $order['payment_method'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($order['status'])); ?>">
                                        <?php echo htmlspecialchars(t('status_' . strtolower($order['status']), [], $order['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d h:i A', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <a href="store_orders.php?order_id=<?php echo (int)$order['id']; ?>" class="btn btn-small btn-info"><?php echo t('admin_action_view_details'); ?></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data"><?php echo t('store_order_empty'); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
