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

$page_title = t('payment_page_title');
$success_msg = isset($_GET['payment']) && $_GET['payment'] === 'success' ? t('payment_success') : '';
$error_msg = '';
$customer_session_token = $_SESSION['customer_booking_token'] ?? '';
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0);
$selected_method = sanitize_input($_POST['payment_method'] ?? 'Cash');
$card_number = sanitize_input($_POST['card_number'] ?? '');
$expiry_date = sanitize_input($_POST['expiry_date'] ?? '');
$cvv = sanitize_input($_POST['cvv'] ?? '');
$otp_code = sanitize_input($_POST['otp_code'] ?? '');
$otp_confirmed = sanitize_input($_POST['otp_confirmed'] ?? '0');

$booking = null;
$booking_items = [];
$payable_total = 0.0;

if ($booking_id > 0 && !empty($customer_session_token)) {
    $booking = get_checkout_booking($conn, $booking_id, $customer_session_token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$booking) {
        $error_msg = t('payment_booking_missing');
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = t('payment_session_expired');
    } elseif ($booking['status'] === 'Cancelled') {
        $error_msg = t('payment_cancelled_error');
    } elseif (($booking['payment_status'] ?? 'Unpaid') === 'Paid') {
        $success_msg = t('payment_success');
    } elseif (!in_array($selected_method, ['Cash', 'Visa', 'CliQ'], true)) {
        $error_msg = t('payment_method_invalid');
    } else {
        if ($selected_method === 'Visa') {
            $card_number_digits = preg_replace('/\D+/', '', $card_number);
            $cvv_digits = preg_replace('/\D+/', '', $cvv);

            if (!is_valid_luhn_number($card_number_digits)) {
                $error_msg = t('payment_card_invalid');
            } elseif (!is_valid_future_expiry($expiry_date)) {
                $error_msg = t('payment_expiry_invalid');
            } elseif (strlen($cvv_digits) < 3 || strlen($cvv_digits) > 4) {
                $error_msg = t('payment_cvv_invalid');
            } elseif ($otp_confirmed !== '1' || !preg_match('/^[0-9]{4,6}$/', $otp_code)) {
                $error_msg = t('payment_otp_invalid');
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

            $error_msg = t('payment_submit_error');
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
        <h1><?php echo t('payment_hero_title'); ?></h1>
        <p><?php echo t('payment_hero_text'); ?></p>
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
                <span class="ticket-label"><?php echo t('payment_access'); ?></span>
                <h2><?php echo t('payment_not_available_title'); ?></h2>
                <p><?php echo t('payment_not_available_text'); ?></p>
                <div class="booking-ticket-actions">
                    <a href="my_bookings.php" class="btn"><?php echo t('payment_go_to_bookings'); ?></a>
                    <a href="booking.php" class="btn payment-secondary-btn"><?php echo t('payment_new_booking'); ?></a>
                </div>
            </div>
        <?php else: ?>
            <div class="checkout-shell">
                <div class="checkout-panel checkout-summary-panel">
                    <div class="checkout-panel-header">
                        <div>
                            <span class="ticket-label"><?php echo t('payment_summary'); ?></span>
                            <h2><?php echo t('payment_reservation'); ?><?php echo htmlspecialchars($booking['booking_code'] ?: ('FG-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT))); ?></h2>
                        </div>
                        <span class="checkout-status-pill status-<?php echo strtolower(htmlspecialchars($booking['payment_status'] ?? 'unpaid')); ?>">
                            <?php echo htmlspecialchars(t('status_' . strtolower($booking['payment_status'] ?? 'Unpaid'), [], $booking['payment_status'] ?? 'Unpaid')); ?>
                        </span>
                    </div>

                    <div class="checkout-summary-grid">
                        <div>
                            <span><?php echo t('common_customer'); ?></span>
                            <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong>
                        </div>
                        <div>
                            <span><?php echo t('common_phone'); ?></span>
                            <strong><?php echo htmlspecialchars($booking['phone']); ?></strong>
                        </div>
                        <div>
                            <span><?php echo t('common_room'); ?></span>
                            <strong><?php echo htmlspecialchars($booking['room_name']); ?> - <?php echo htmlspecialchars($booking['room_type']); ?></strong>
                        </div>
                        <div>
                            <span><?php echo t('common_schedule'); ?></span>
                            <strong><?php echo format_date($booking['booking_date']); ?>, <?php echo format_time($booking['start_time']); ?></strong>
                        </div>
                        <div>
                            <span><?php echo t('common_duration'); ?></span>
                            <strong><?php echo translated_hours_label($booking['hours']); ?></strong>
                        </div>
                        <div>
                            <span><?php echo t('common_booking_status'); ?></span>
                            <strong><?php echo htmlspecialchars(t('status_' . strtolower($booking['status']), [], $booking['status'])); ?></strong>
                        </div>
                    </div>

                    <div class="checkout-total-card">
                        <div class="checkout-total-row">
                            <span><?php echo t('payment_room_booking'); ?></span>
                            <strong><?php echo number_format((float)$booking['total_price'], 2); ?> JOD</strong>
                        </div>
                        <div class="checkout-total-row">
                            <span><?php echo t('payment_additional_items'); ?></span>
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
                            <span><?php echo t('payment_total_due'); ?></span>
                            <strong><?php echo number_format($payable_total, 2); ?> JOD</strong>
                        </div>
                    </div>
                </div>

                <div class="checkout-panel checkout-form-panel">
                    <div class="checkout-panel-header">
                        <div>
                            <span class="ticket-label"><?php echo t('payment_method_title'); ?></span>
                            <h2><?php echo t('payment_choose_how'); ?></h2>
                        </div>
                    </div>

                    <p class="simulation-note">
                        <?php echo t('payment_note'); ?>
                    </p>

                    <?php if (($booking['payment_status'] ?? 'Unpaid') === 'Paid'): ?>
                        <div class="payment-success-panel">
                            <h3><?php echo t('payment_already_paid_title'); ?></h3>
                            <p><?php echo t('payment_already_paid_text', ['method' => htmlspecialchars(t('payment_' . strtolower($booking['payment_method'] ?: ''), [], $booking['payment_method'] ?: t('payment_selected_method')))]); ?></p>
                            <div class="booking-ticket-actions">
                                <a href="my_bookings.php" class="btn"><?php echo t('common_back_to_my_bookings'); ?></a>
                            </div>
                        </div>
                    <?php elseif ($booking['status'] === 'Cancelled'): ?>
                        <div class="payment-success-panel payment-blocked-panel">
                            <h3><?php echo t('payment_unavailable_title'); ?></h3>
                            <p><?php echo t('payment_unavailable_text'); ?></p>
                            <div class="booking-ticket-actions">
                                <a href="my_bookings.php" class="btn"><?php echo t('common_back_to_my_bookings'); ?></a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="payment.php?booking_id=<?php echo (int)$booking['id']; ?>" class="checkout-form">
                            <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                            <input type="hidden" name="otp_confirmed" id="otp_confirmed" value="<?php echo htmlspecialchars($otp_confirmed); ?>">
                            <input type="hidden" name="otp_code" id="otp_code" value="<?php echo htmlspecialchars($otp_code); ?>">

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
                                            <strong><?php echo t('payment_' . strtolower($method), [], $method); ?></strong>
                                            <small>
                                                <?php
                                                if ($method === 'Cash') {
                                                    echo t('payment_cash_text');
                                                } elseif ($method === 'Visa') {
                                                    echo t('payment_visa_text');
                                                } else {
                                                    echo t('payment_cliq_text');
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
                                            <label class="form-label"><?php echo t('payment_card_number'); ?></label>
                                            <input type="text" name="card_number" id="card_number" class="form-control" maxlength="23" inputmode="numeric" placeholder="4242 4242 4242 4242" value="<?php echo htmlspecialchars($card_number); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label"><?php echo t('payment_expiry'); ?></label>
                                            <input type="text" name="expiry_date" id="expiry_date" class="form-control" maxlength="5" placeholder="MM/YY" value="<?php echo htmlspecialchars($expiry_date); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label"><?php echo t('payment_cvv'); ?></label>
                                            <input type="password" name="cvv" id="cvv" class="form-control" maxlength="4" inputmode="numeric" placeholder="123" value="<?php echo htmlspecialchars($cvv); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="checkout-submit-row">
                                <div class="checkout-amount-badge">
                                    <?php echo t('payment_total_prefix'); ?> <?php echo number_format($payable_total, 2); ?> JOD
                                </div>
                                <button type="submit" class="btn payment-submit-btn"><?php echo t('payment_confirm'); ?></button>
                            </div>
                        </form>

                        <div class="site-modal otp-modal" id="paymentOtpModal" hidden>
                            <div class="site-modal-backdrop" data-otp-close></div>
                            <div class="site-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="paymentOtpTitle">
                                <button type="button" class="site-modal-close" data-otp-close aria-label="<?php echo htmlspecialchars(t('common_close'), ENT_QUOTES, 'UTF-8'); ?>">X</button>
                                <h3 id="paymentOtpTitle"><?php echo t('payment_otp_title'); ?></h3>
                                <p><?php echo t('payment_otp_text'); ?></p>
                                <div class="form-group">
                                    <label class="form-label"><?php echo t('payment_otp_code'); ?></label>
                                    <input type="text" id="otp_input" class="form-control" maxlength="6" inputmode="numeric" placeholder="123456">
                                    <small class="booking-time-hint form-text"><?php echo t('payment_otp_hint'); ?></small>
                                </div>
                                <div class="site-modal-actions">
                                    <button type="button" class="btn payment-secondary-btn" data-otp-close><?php echo t('common_cancel'); ?></button>
                                    <button type="button" class="btn" id="confirmOtpButton"><?php echo t('common_confirm'); ?></button>
                                </div>
                            </div>
                        </div>
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
    const checkoutForm = document.querySelector('.checkout-form');
    const otpModal = document.getElementById('paymentOtpModal');
    const otpInput = document.getElementById('otp_input');
    const otpHidden = document.getElementById('otp_code');
    const otpConfirmed = document.getElementById('otp_confirmed');
    const confirmOtpButton = document.getElementById('confirmOtpButton');

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

    if (otpInput) {
        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    function openOtpModal() {
        if (!otpModal) {
            return;
        }

        otpModal.hidden = false;
        document.body.classList.add('site-modal-open');
        if (otpInput) {
            otpInput.focus();
        }
    }

    function closeOtpModal() {
        if (!otpModal) {
            return;
        }

        otpModal.hidden = true;
        document.body.classList.remove('site-modal-open');
    }

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            const selected = document.querySelector('.payment-method-input:checked');
            const isVisa = selected && selected.value === 'Visa';

            if (isVisa && otpConfirmed && otpConfirmed.value !== '1') {
                event.preventDefault();
                openOtpModal();
            }
        });
    }

    document.querySelectorAll('[data-otp-close]').forEach(function(button) {
        button.addEventListener('click', closeOtpModal);
    });

    if (confirmOtpButton) {
        confirmOtpButton.addEventListener('click', function() {
            const code = otpInput ? otpInput.value.replace(/\D/g, '') : '';

            if (!/^[0-9]{4,6}$/.test(code)) {
                if (otpInput) {
                    otpInput.classList.add('is-invalid');
                    otpInput.focus();
                }
                return;
            }

            if (otpHidden) {
                otpHidden.value = code;
            }

            if (otpConfirmed) {
                otpConfirmed.value = '1';
            }

            closeOtpModal();
            if (checkoutForm) {
                checkoutForm.submit();
            }
        });
    }

    toggleVisaFields();
});
</script>

<?php include 'includes/footer.php'; ?>
