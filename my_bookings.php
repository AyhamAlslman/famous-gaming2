<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = 'My Bookings - FAMOUS GAMING';
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

        $success_msg = $updated > 0 ? 'Booking cancelled successfully.' : 'This booking could not be cancelled.';
    } elseif ($ticket_action === 'cancel') {
        $error_msg = 'Your booking session was not found. Please make a new booking to see tickets here.';
    }

    $booking_lookup = strtoupper(sanitize_input($_POST['booking_lookup'] ?? ''));
    $phone = sanitize_input($_POST['phone'] ?? '');
    $booking_id = ctype_digit($booking_lookup) ? (int)$booking_lookup : 0;

    if ($ticket_action === 'cancel') {
        // Cancel action already handled above.
    } elseif (empty($booking_lookup) || empty($phone)) {
        $error_msg = 'Please enter your booking ID and phone number.';
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
            $success_msg = 'Booking found. You can show the ticket below at the shop.';
        } else {
            $error_msg = 'No booking was found for that booking ID and phone number.';
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
        <h1>My Bookings</h1>
        <p>Show your confirmed booking ticket at the shop</p>
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
                         data-ticket-time="<?php echo htmlspecialchars(format_time($booking['start_time']) . ' for ' . (int)$booking['hours'] . ' hour' . ((int)$booking['hours'] === 1 ? '' : 's')); ?>"
                         data-ticket-status="<?php echo htmlspecialchars($booking['status']); ?>">
                        <div class="booking-ticket-header">
                            <div>
                                <span class="ticket-label">Booking Ticket</span>
                                <h2>Your reservation is ready</h2>
                                <p>Please show this booking ID when you arrive.</p>
                            </div>
                            <span class="ticket-status status-<?php echo strtolower(htmlspecialchars($booking['status'])); ?>"><?php echo htmlspecialchars($booking['status']); ?></span>
                        </div>

                        <div class="booking-ticket-code">
                            <span>Reservation Barcode</span>
                            <div class="ticket-barcode" aria-label="Reservation barcode">
                                <?php echo render_booking_barcode($booking['booking_code'] ?: $booking['id']); ?>
                            </div>
                        </div>

                        <div class="booking-ticket-grid">
                            <div>
                                <span>Customer</span>
                                <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong>
                            </div>
                            <div>
                                <span>Device / Session</span>
                                <strong><?php echo htmlspecialchars($booking['room_name']); ?> - <?php echo htmlspecialchars($booking['room_type']); ?></strong>
                            </div>
                            <div>
                                <span>Date</span>
                                <strong><?php echo format_date($booking['booking_date']); ?></strong>
                            </div>
                            <div>
                                <span>Time</span>
                                <strong><?php echo format_time($booking['start_time']); ?> for <?php echo (int)$booking['hours']; ?> hour<?php echo (int)$booking['hours'] === 1 ? '' : 's'; ?></strong>
                            </div>
                        </div>

                        <div class="booking-ticket-actions">
                            <button type="button" class="btn download-ticket-btn">Save Ticket Image</button>
                            <?php if ($booking['status'] === 'Confirmed'): ?>
                                <form method="POST" action="my_bookings.php" class="ticket-action-form">
                                    <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                                    <input type="hidden" name="ticket_action" value="cancel">
                                    <button type="submit" class="btn ticket-cancel-btn" onclick="return confirm('Cancel this booking?');">Cancel Booking</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-bookings">
                <h2>No bookings in this session yet</h2>
                <p>Book a gaming session to see your confirmed ticket here.</p>
                <a href="booking.php" class="btn">Book Now</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
include 'includes/footer.php';
?>
