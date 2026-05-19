<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

ensure_booking_confirmation_schema($conn);

function get_checkout_booking($conn, $booking_id, $customer_session_token) {
    $query = "SELECT b.*, r.room_name, r.room_type
              FROM bookings b
              LEFT JOIN rooms r ON b.room_id = r.id
              WHERE b.id = ? AND b.customer_session_token = ?
              LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $booking_id, $customer_session_token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $booking;
}

function get_checkout_booking_items($conn, $booking_id) {
    $query = "SELECT bi.quantity, bi.item_total, COALESCE(mi.item_name, 'Item') AS item_name
              FROM booking_items bi
              LEFT JOIN menu_items mi ON bi.menu_item_id = mi.id
              WHERE bi.booking_id = ?
              ORDER BY bi.id ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $items;
}

$page_title = 'Checkout Simulation - FAMOUS GAMING';
$success_msg = isset($_GET['payment']) && $_GET['payment'] === 'success' ? 'Payment simulation successful' : '';
$error_msg = '';
$customer_session_token = $_SESSION['customer_booking_token'] ?? '';
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0);
$selected_method = sanitize_input($_POST['payment_method'] ?? 'Cash');
$card_number = sanitize_input($_POST['card_number'] ?? '');
$expiry_date = sanitize_input($_POST['expiry_date'] ?? '');
$cvv = sanitize_input($_POST['cvv'] ?? '');

$booking = null;
$booking_items = [];
$payable_total = 0.0;

if ($booking_id > 0 && !empty($customer_session_token)) {
    $booking = get_checkout_booking($conn, $booking_id, $customer_session_token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$booking) {
        $error_msg = 'This booking could not be loaded for checkout. Please open it from My Bookings.';
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Your session expired. Please refresh the page and try again.';
    } elseif ($booking['status'] === 'Cancelled') {
        $error_msg = 'Cancelled bookings cannot be paid.';
    } elseif (($booking['payment_status'] ?? 'Unpaid') === 'Paid') {
        $success_msg = 'Payment simulation successful';
    } elseif (!in_array($selected_method, ['Cash', 'Visa', 'CliQ'], true)) {
        $error_msg = 'Please select a valid payment method.';
    } else {
        if ($selected_method === 'Visa') {
            $card_number_digits = preg_replace('/\D+/', '', $card_number);
            $cvv_digits = preg_replace('/\D+/', '', $cvv);

            if (strlen($card_number_digits) < 13 || strlen($card_number_digits) > 19) {
                $error_msg = 'Please enter a valid simulated card number.';
            } elseif (!preg_match('/^(0[1-9]|1[0-2])\/[0-9]{2}$/', $expiry_date)) {
                $error_msg = 'Please enter the expiry date in MM/YY format.';
            } elseif (strlen($cvv_digits) < 3 || strlen($cvv_digits) > 4) {
                $error_msg = 'Please enter a valid simulated CVV.';
            }
        }

        if (empty($error_msg)) {
            $payable_total = isset($booking['final_total'])
                ? (float)$booking['final_total']
                : ((float)$booking['total_price'] + (float)$booking['additional_items_total']);

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE bookings
                 SET payment_status = 'Paid', payment_method = ?, paid_amount = ?
                 WHERE id = ? AND customer_session_token = ?"
            );
            mysqli_stmt_bind_param($stmt, "sdis", $selected_method, $payable_total, $booking_id, $customer_session_token);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                create_admin_notification(
                    $conn,
                    'payment_updated',
                    'Payment updated',
                    'Booking #' . $booking_id . ' was marked paid via ' . $selected_method . '.',
                    'bookings',
                    $booking_id,
                    'booking_details.php?id=' . $booking_id
                );
                header('Location: payment.php?booking_id=' . $booking_id . '&payment=success');
                exit;
            }

            $error_msg = 'Payment simulation could not be completed. Please try again.';
            mysqli_stmt_close($stmt);
        }
    }
}

if ($booking) {
    $booking_items = get_checkout_booking_items($conn, $booking_id);
    $payable_total = isset($booking['final_total'])
        ? (float)$booking['final_total']
        : ((float)$booking['total_price'] + (float)$booking['additional_items_total']);
}

include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Checkout Simulation</h1>
        <p>Review your booking and complete a safe demo payment flow</p>
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

        <?php if (!$booking): ?>
            <div class="booking-lookup-panel">
                <span class="ticket-label">Checkout Access</span>
                <h2>Booking not available for checkout</h2>
                <p>Open this page from your booking confirmation or from My Bookings so we can verify your session.</p>
                <div class="booking-ticket-actions">
                    <a href="my_bookings.php" class="btn">Go to My Bookings</a>
                    <a href="booking.php" class="btn payment-secondary-btn">Make a New Booking</a>
                </div>
            </div>
        <?php else: ?>
            <div class="checkout-shell">
                <div class="checkout-panel checkout-summary-panel">
                    <div class="checkout-panel-header">
                        <div>
                            <span class="ticket-label">Booking Summary</span>
                            <h2>Reservation #<?php echo htmlspecialchars($booking['booking_code'] ?: ('FG-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT))); ?></h2>
                        </div>
                        <span class="checkout-status-pill status-<?php echo strtolower(htmlspecialchars($booking['payment_status'] ?? 'unpaid')); ?>">
                            <?php echo htmlspecialchars($booking['payment_status'] ?? 'Unpaid'); ?>
                        </span>
                    </div>

                    <div class="checkout-summary-grid">
                        <div>
                            <span>Customer</span>
                            <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong>
                        </div>
                        <div>
                            <span>Phone</span>
                            <strong><?php echo htmlspecialchars($booking['phone']); ?></strong>
                        </div>
                        <div>
                            <span>Room</span>
                            <strong><?php echo htmlspecialchars($booking['room_name']); ?> - <?php echo htmlspecialchars($booking['room_type']); ?></strong>
                        </div>
                        <div>
                            <span>Schedule</span>
                            <strong><?php echo format_date($booking['booking_date']); ?>, <?php echo format_time($booking['start_time']); ?></strong>
                        </div>
                        <div>
                            <span>Duration</span>
                            <strong><?php echo (int)$booking['hours']; ?> hour<?php echo (int)$booking['hours'] === 1 ? '' : 's'; ?></strong>
                        </div>
                        <div>
                            <span>Booking Status</span>
                            <strong><?php echo htmlspecialchars($booking['status']); ?></strong>
                        </div>
                    </div>

                    <div class="checkout-total-card">
                        <div class="checkout-total-row">
                            <span>Room Booking</span>
                            <strong><?php echo number_format((float)$booking['total_price'], 2); ?> JOD</strong>
                        </div>
                        <div class="checkout-total-row">
                            <span>Additional Items</span>
                            <strong><?php echo number_format((float)$booking['additional_items_total'], 2); ?> JOD</strong>
                        </div>

                        <?php if (!empty($booking_items)): ?>
                            <div class="checkout-items-list">
                                <?php foreach ($booking_items as $item): ?>
                                    <div class="checkout-item-row">
                                        <span><?php echo htmlspecialchars($item['item_name']); ?> x<?php echo (int)$item['quantity']; ?></span>
                                        <strong><?php echo number_format((float)$item['item_total'], 2); ?> JOD</strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="checkout-total-row checkout-total-row-final">
                            <span>Total Due</span>
                            <strong><?php echo number_format($payable_total, 2); ?> JOD</strong>
                        </div>
                    </div>
                </div>

                <div class="checkout-panel checkout-form-panel">
                    <div class="checkout-panel-header">
                        <div>
                            <span class="ticket-label">Payment Method</span>
                            <h2>Choose how to pay</h2>
                        </div>
                    </div>

                    <p class="simulation-note">
                        This is a payment simulation only. Any Visa details entered here stay on this website and are never sent to a real payment gateway.
                    </p>

                    <?php if (($booking['payment_status'] ?? 'Unpaid') === 'Paid'): ?>
                        <div class="payment-success-panel">
                            <h3>Payment already completed</h3>
                            <p>Your booking is already marked as paid with <?php echo htmlspecialchars($booking['payment_method'] ?: 'the selected method'); ?>.</p>
                            <div class="booking-ticket-actions">
                                <a href="my_bookings.php" class="btn">Back to My Bookings</a>
                            </div>
                        </div>
                    <?php elseif ($booking['status'] === 'Cancelled'): ?>
                        <div class="payment-success-panel payment-blocked-panel">
                            <h3>Payment unavailable</h3>
                            <p>This booking has been cancelled, so checkout is no longer available.</p>
                            <div class="booking-ticket-actions">
                                <a href="my_bookings.php" class="btn">Back to My Bookings</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="payment.php?booking_id=<?php echo (int)$booking['id']; ?>" class="checkout-form">
                            <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

                            <div class="payment-method-grid">
                                <?php foreach (['Cash', 'Visa', 'CliQ'] as $method): ?>
                                    <label class="payment-method-option">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="<?php echo $method; ?>"
                                            class="payment-method-input"
                                            <?php echo $selected_method === $method ? 'checked' : ''; ?>
                                        >
                                        <span class="payment-method-card">
                                            <strong><?php echo $method; ?></strong>
                                            <small>
                                                <?php
                                                if ($method === 'Cash') {
                                                    echo 'Simple counter payment simulation';
                                                } elseif ($method === 'Visa') {
                                                    echo 'Demo card form with no real processing';
                                                } else {
                                                    echo 'Fast local wallet-style simulation';
                                                }
                                                ?>
                                            </small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="visa-fields" id="visa-fields" <?php echo $selected_method === 'Visa' ? '' : 'hidden'; ?>>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label">Card Number</label>
                                            <input type="text" name="card_number" id="card_number" class="form-control" maxlength="23" inputmode="numeric" placeholder="4242 4242 4242 4242" value="<?php echo htmlspecialchars($card_number); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Expiry Date</label>
                                            <input type="text" name="expiry_date" id="expiry_date" class="form-control" maxlength="5" placeholder="MM/YY" value="<?php echo htmlspecialchars($expiry_date); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">CVV</label>
                                            <input type="password" name="cvv" id="cvv" class="form-control" maxlength="4" inputmode="numeric" placeholder="123" value="<?php echo htmlspecialchars($cvv); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="checkout-submit-row">
                                <div class="checkout-amount-badge">
                                    Total: <?php echo number_format($payable_total, 2); ?> JOD
                                </div>
                                <button type="submit" class="btn payment-submit-btn">Confirm Simulation</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentInputs = document.querySelectorAll('.payment-method-input');
    const visaFields = document.getElementById('visa-fields');
    const cardNumberInput = document.getElementById('card_number');
    const expiryDateInput = document.getElementById('expiry_date');
    const cvvInput = document.getElementById('cvv');

    function toggleVisaFields() {
        const selected = document.querySelector('.payment-method-input:checked');
        const isVisa = selected && selected.value === 'Visa';

        if (!visaFields) {
            return;
        }

        visaFields.hidden = !isVisa;

        [cardNumberInput, expiryDateInput, cvvInput].forEach(function (field) {
            if (field) {
                field.required = !!isVisa;
            }
        });
    }

    paymentInputs.forEach(function (input) {
        input.addEventListener('change', toggleVisaFields);
    });

    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function () {
            const digits = this.value.replace(/\D/g, '').slice(0, 19);
            this.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
        });
    }

    if (expiryDateInput) {
        expiryDateInput.addEventListener('input', function () {
            const digits = this.value.replace(/\D/g, '').slice(0, 4);
            if (digits.length >= 3) {
                this.value = digits.slice(0, 2) + '/' + digits.slice(2);
            } else {
                this.value = digits;
            }
        });
    }

    if (cvvInput) {
        cvvInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
    }

    toggleVisaFields();
});
</script>

<?php include 'includes/footer.php'; ?>
