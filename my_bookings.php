<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = t('my_bookings_page_title');
$success_msg = '';
$error_msg = '';

ensure_booking_confirmation_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_session_token = $_SESSION['customer_booking_token'] ?? '';
    $ticket_action = sanitize_input($_POST['ticket_action'] ?? '');
    $ticket_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;

    if ($ticket_action === 'cancel' && $ticket_id > 0 && !empty($customer_session_token)) {
        $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'Cancelled' WHERE id = ? AND customer_session_token = ? AND status = 'Confirmed'");
        mysqli_stmt_bind_param($stmt, "is", $ticket_id, $customer_session_token);
        mysqli_stmt_execute($stmt);
        $updated = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        $success_msg = $updated > 0 ? t('my_bookings_cancel_success') : t('my_bookings_cancel_error');
    } elseif ($ticket_action === 'cancel') {
        $error_msg = t('my_bookings_session_missing');
    }

    $booking_lookup = strtoupper(sanitize_input($_POST['booking_lookup'] ?? ''));
    $phone = sanitize_input($_POST['phone'] ?? '');
    $booking_id = ctype_digit($booking_lookup) ? (int)$booking_lookup : 0;

    if ($ticket_action === 'cancel') {
        // Cancel action already handled above.
    } elseif (empty($booking_lookup) || empty($phone)) {
        $error_msg = t('my_bookings_lookup_required');
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, customer_name, phone, customer_session_token FROM bookings WHERE (id = ? OR booking_code = ?) AND phone = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iss", $booking_id, $booking_lookup, $phone);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $booking = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($booking) {
            $customer_session_token = $booking['customer_session_token'];

            if (empty($customer_session_token)) {
                $customer_session_token = get_customer_session_token();
                $update_stmt = mysqli_prepare($conn, "UPDATE bookings SET customer_session_token = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "si", $customer_session_token, $booking['id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }

            $_SESSION['customer_booking_token'] = $customer_session_token;
            $_SESSION['customer_name'] = $booking['customer_name'];
            $_SESSION['customer_phone'] = $booking['phone'];
            $success_msg = t('my_bookings_found');
        } else {
            $error_msg = t('my_bookings_not_found');
        }
    }
}

$customer_session_token = $_SESSION['customer_booking_token'] ?? '';
$bookings = [];

if (!empty($customer_session_token)) {
    $query = "SELECT b.*, r.room_name, r.room_type
              FROM bookings b
              LEFT JOIN rooms r ON b.room_id = r.id
              WHERE b.customer_session_token = ?
              ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $customer_session_token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1><?php echo t('my_bookings_hero_title'); ?></h1>
        <p><?php echo t('my_bookings_hero_text'); ?></p>
    </div>
</section>

<section class="content">
    <div class="container">
        <?php if ($success_msg): ?>
            <div class="message success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($bookings)): ?>
            <div class="my-bookings-list">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-ticket ticket-<?php echo strtolower(htmlspecialchars($booking['status'])); ?>"
                         data-ticket-code="<?php echo htmlspecialchars($booking['booking_code'] ?: ('FG-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT))); ?>"
                         data-ticket-customer="<?php echo htmlspecialchars($booking['customer_name']); ?>"
                         data-ticket-device="<?php echo htmlspecialchars($booking['room_name'] . ' - ' . $booking['room_type']); ?>"
                         data-ticket-date="<?php echo htmlspecialchars(format_date($booking['booking_date'])); ?>"
                         data-ticket-time="<?php echo htmlspecialchars(format_time($booking['start_time']) . ' - ' . translated_hours_label($booking['hours'])); ?>"
                         data-ticket-status="<?php echo htmlspecialchars(t('status_' . strtolower($booking['status']), [], $booking['status'])); ?>">
                        <div class="booking-ticket-header">
                            <div>
                                <span class="ticket-label"><?php echo t('booking_ticket_label'); ?></span>
                                <h2><?php echo t('booking_ticket_ready'); ?></h2>
                                <p><?php echo t('booking_ticket_arrival'); ?></p>
                            </div>
                            <span class="ticket-status status-<?php echo strtolower(htmlspecialchars($booking['status'])); ?>"><?php echo htmlspecialchars(t('status_' . strtolower($booking['status']), [], $booking['status'])); ?></span>
                        </div>

                        <div class="booking-ticket-code">
                            <span><?php echo t('booking_barcode'); ?></span>
                            <div class="ticket-barcode" aria-label="<?php echo htmlspecialchars(t('booking_barcode'), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo render_booking_barcode($booking['booking_code'] ?: $booking['id']); ?>
                            </div>
                        </div>

                        <div class="booking-ticket-grid">
                            <div>
                                <span><?php echo t('common_customer'); ?></span>
                                <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('booking_device_session'); ?></span>
                                <strong><?php echo htmlspecialchars($booking['room_name']); ?> - <?php echo htmlspecialchars($booking['room_type']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('common_date'); ?></span>
                                <strong><?php echo format_date($booking['booking_date']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('common_time'); ?></span>
                                <strong><?php echo format_time($booking['start_time']); ?> - <?php echo translated_hours_label($booking['hours']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('common_payment'); ?></span>
                                <strong><?php echo htmlspecialchars(t('status_' . strtolower($booking['payment_status'] ?? 'Unpaid'), [], $booking['payment_status'] ?? 'Unpaid')); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('common_total'); ?></span>
                                <strong><?php echo number_format((float)($booking['final_total'] ?? $booking['total_price']), 2); ?> JOD</strong>
                            </div>
                        </div>

                        <div class="booking-ticket-actions">
                            <button type="button" class="btn download-ticket-btn"><?php echo t('booking_save_ticket'); ?></button>
                            <?php if ($booking['status'] !== 'Cancelled' && ($booking['payment_status'] ?? 'Unpaid') !== 'Paid'): ?>
                                <a href="payment.php?booking_id=<?php echo (int)$booking['id']; ?>" class="btn payment-checkout-btn"><?php echo t('my_bookings_simulate_payment'); ?></a>
                            <?php endif; ?>
                            <?php if ($booking['status'] === 'Confirmed'): ?>
                                <form method="POST" action="my_bookings.php" class="ticket-action-form">
                                    <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                                    <input type="hidden" name="ticket_action" value="cancel">
                                    <button type="submit" class="btn ticket-cancel-btn" onclick="return confirm('<?php echo htmlspecialchars(t('my_bookings_cancel_confirm'), ENT_QUOTES, 'UTF-8'); ?>');"><?php echo t('my_bookings_cancel'); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-bookings">
                <h2><?php echo t('my_bookings_empty_title'); ?></h2>
                <p><?php echo t('my_bookings_empty_text'); ?></p>
                <a href="booking.php" class="btn"><?php echo t('nav_book_now'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
include 'includes/footer.php';
?>
