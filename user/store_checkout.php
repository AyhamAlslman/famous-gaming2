<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ensure_user_auth_schema($conn);
ensure_store_orders_schema($conn);

$current_site_user = get_current_site_user($conn);

if (!$current_site_user) {
    $_SESSION['post_login_redirect'] = 'user/store.php';
    header('Location: ' . site_url('general/login.php?redirect=user/store.php'));
    exit;
}

$page_title = t('store_checkout_page_title');
$site_user_id = (int)$current_site_user['id'];
$error_msg = '';
$success_msg = '';
$checkout_items = [];
$success_order = null;
$success_order_items = [];
$cart_data_raw = $_POST['cart_data'] ?? '';
$checkout_action = sanitize_input($_POST['checkout_action'] ?? 'review');
$selected_method = sanitize_input($_POST['payment_method'] ?? 'Cash');
$card_number = sanitize_input($_POST['card_number'] ?? '');
$expiry_date = sanitize_input($_POST['expiry_date'] ?? '');
$cvv = sanitize_input($_POST['cvv'] ?? '');
$otp_code = sanitize_input($_POST['otp_code'] ?? '');
$otp_confirmed = sanitize_input($_POST['otp_confirmed'] ?? '0');
$subtotal = 0.0;
$payable_total = 0.0;

if (!in_array($selected_method, ['Cash', 'Visa', 'CliQ'], true)) {
    $selected_method = 'Cash';
}

function normalize_store_cart_payload($cart_json) {
    $decoded = json_decode((string)$cart_json, true);
    $cart = [];

    if (!is_array($decoded)) {
        return $cart;
    }

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $product_id = isset($item['id']) ? (int)$item['id'] : 0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;

        if ($product_id <= 0 || $quantity <= 0) {
            continue;
        }

        if (!isset($cart[$product_id])) {
            $cart[$product_id] = 0;
        }

        $cart[$product_id] = min(20, $cart[$product_id] + min(20, $quantity));
    }

    return $cart;
}

function load_store_cart_items($conn, $cart, &$error_msg, $lock_rows = false) {
    $items = [];
    $subtotal = 0.0;

    if (empty($cart)) {
        $error_msg = t('store_checkout_empty_text');
        return ['items' => [], 'subtotal' => 0.0];
    }

    foreach ($cart as $product_id => $quantity) {
        $sql = "SELECT id, product_name, category, price, image_path, stock_quantity
                FROM store_products
                WHERE id = ? AND status = 'Active'
                LIMIT 1" . ($lock_rows ? " FOR UPDATE" : "");
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$product) {
            $error_msg = t('store_checkout_product_missing');
            return ['items' => [], 'subtotal' => 0.0];
        }

        if ((int)$product['stock_quantity'] < $quantity) {
            $error_msg = t('store_checkout_stock_error', ['name' => $product['product_name']]);
            return ['items' => [], 'subtotal' => 0.0];
        }

        $line_total = (float)$product['price'] * $quantity;
        $subtotal += $line_total;
        $items[] = [
            'id' => (int)$product['id'],
            'name' => $product['product_name'],
            'category' => $product['category'],
            'price' => (float)$product['price'],
            'quantity' => $quantity,
            'line_total' => $line_total,
            'image_path' => $product['image_path']
        ];
    }

    return ['items' => $items, 'subtotal' => $subtotal];
}

function fetch_store_order_for_user($conn, $order_id, $user_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM store_orders WHERE id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $order;
}

function fetch_store_order_items($conn, $order_id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM store_order_items WHERE order_id = ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $items;
}

$success_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($success_order_id > 0 && isset($_GET['success'])) {
    $success_order = fetch_store_order_for_user($conn, $success_order_id, $site_user_id);
    if ($success_order) {
        $success_msg = t('store_checkout_success');
        $success_order_items = fetch_store_order_items($conn, $success_order_id);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = t('payment_session_expired');
    } else {
        $cart = normalize_store_cart_payload($cart_data_raw);
        $cart_details = load_store_cart_items($conn, $cart, $error_msg, false);
        $checkout_items = $cart_details['items'];
        $subtotal = (float)$cart_details['subtotal'];
        $payable_total = $subtotal;

        if ($checkout_action === 'confirm' && empty($error_msg)) {
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
                mysqli_begin_transaction($conn);

                try {
                    $locked_details = load_store_cart_items($conn, $cart, $error_msg, true);
                    if (!empty($error_msg)) {
                        throw new Exception($error_msg);
                    }

                    $checkout_items = $locked_details['items'];
                    $subtotal = (float)$locked_details['subtotal'];
                    $payable_total = $subtotal;
                    $order_code = generate_store_order_code();
                    $payment_status = 'Paid';
                    $order_status = 'Confirmed';
                    $paid_amount = $payable_total;
                    $order_customer_name = $current_site_user['full_name'];
                    $order_customer_phone = $current_site_user['phone'];
                    $order_customer_email = $current_site_user['email'];

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO store_orders
                         (order_code, user_id, customer_name, phone, email, subtotal, total_amount, payment_status, payment_method, paid_amount, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    mysqli_stmt_bind_param(
                        $stmt,
                        "sisssddssds",
                        $order_code,
                        $site_user_id,
                        $order_customer_name,
                        $order_customer_phone,
                        $order_customer_email,
                        $subtotal,
                        $payable_total,
                        $payment_status,
                        $selected_method,
                        $paid_amount,
                        $order_status
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(t('store_checkout_submit_error'));
                    }

                    $order_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);

                    $item_stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO store_order_items (order_id, product_id, product_name, category, quantity, item_price)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $stock_stmt = mysqli_prepare($conn, "UPDATE store_products SET stock_quantity = stock_quantity - ? WHERE id = ?");

                    foreach ($checkout_items as $item) {
                        $item_product_id = (int)$item['id'];
                        $item_name = $item['name'];
                        $item_category = $item['category'];
                        $item_quantity = (int)$item['quantity'];
                        $item_price = (float)$item['price'];

                        mysqli_stmt_bind_param(
                            $item_stmt,
                            "iissid",
                            $order_id,
                            $item_product_id,
                            $item_name,
                            $item_category,
                            $item_quantity,
                            $item_price
                        );
                        mysqli_stmt_execute($item_stmt);

                        mysqli_stmt_bind_param($stock_stmt, "ii", $item_quantity, $item_product_id);
                        mysqli_stmt_execute($stock_stmt);
                    }

                    mysqli_stmt_close($item_stmt);
                    mysqli_stmt_close($stock_stmt);

                    $earned_points = award_store_order_loyalty_points($conn, $site_user_id, $order_id, $payable_total);

                    create_admin_notification(
                        $conn,
                        'store_order_created',
                        'New store order',
                        $current_site_user['full_name'] . ' placed store order ' . $order_code . ' via ' . $selected_method . '.',
                        'store_orders',
                        $order_id,
                        'store_orders.php?order_id=' . $order_id
                    );
                    create_site_notification(
                        $conn,
                        $site_user_id,
                        'store_order_created',
                        t('store_checkout_success'),
                        t('store_checkout_success_points', ['points' => $earned_points]),
                        'user/my_bookings.php'
                    );

                    mysqli_commit($conn);
                    header('Location: ' . site_url('user/store_checkout.php?success=1&order_id=' . $order_id));
                    exit;
                } catch (Throwable $exception) {
                    mysqli_rollback($conn);
                    $error_msg = $exception->getMessage() ?: t('store_checkout_submit_error');
                }
            }
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero store-checkout-hero">
    <div class="container">
        <h1><?php echo t('store_checkout_hero_title'); ?></h1>
        <p><?php echo t('store_checkout_hero_text'); ?></p>
    </div>
</section>

<section class="content store-checkout-content">
    <div class="container">
        <?php if ($success_msg): ?>
            <div class="message success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if ($success_order): ?>
            <div class="checkout-shell store-checkout-success" data-store-order-success>
                <div class="checkout-panel checkout-summary-panel">
                    <div class="checkout-panel-header">
                        <div>
                            <span class="ticket-label"><?php echo t('store_order_code'); ?></span>
                            <h2><?php echo htmlspecialchars($success_order['order_code']); ?></h2>
                        </div>
                        <span class="checkout-status-pill status-paid"><?php echo htmlspecialchars(t('status_' . strtolower($success_order['payment_status']), [], $success_order['payment_status'])); ?></span>
                    </div>

                    <div class="checkout-summary-grid">
                        <div>
                            <span><?php echo t('common_customer'); ?></span>
                            <strong><?php echo htmlspecialchars($success_order['customer_name']); ?></strong>
                        </div>
                        <div>
                            <span><?php echo t('common_phone'); ?></span>
                            <strong><?php echo htmlspecialchars($success_order['phone']); ?></strong>
                        </div>
                        <div>
                            <span><?php echo t('common_payment'); ?></span>
                            <strong><?php echo htmlspecialchars(t('payment_' . strtolower($success_order['payment_method']), [], $success_order['payment_method'])); ?></strong>
                        </div>
                        <div>
                            <span><?php echo t('loyalty_points'); ?></span>
                            <strong><?php echo (int)$success_order['loyalty_points_earned']; ?></strong>
                        </div>
                    </div>

                    <div class="checkout-total-card">
                        <?php foreach ($success_order_items as $item): ?>
                            <div class="checkout-item-row">
                                <span><?php echo htmlspecialchars($item['product_name']); ?> x<?php echo (int)$item['quantity']; ?></span>
                                <strong><?php echo number_format((float)$item['item_total'], 2); ?> JOD</strong>
                            </div>
                        <?php endforeach; ?>
                        <div class="checkout-total-row checkout-total-row-final">
                            <span><?php echo t('payment_total_due'); ?></span>
                            <strong><?php echo number_format((float)$success_order['total_amount'], 2); ?> JOD</strong>
                        </div>
                    </div>
                </div>

                <div class="checkout-panel checkout-form-panel">
                    <div class="payment-success-panel">
                        <h3><?php echo t('store_checkout_order_ready'); ?></h3>
                        <p><?php echo t('store_checkout_success_points', ['points' => (int)$success_order['loyalty_points_earned']]); ?></p>
                        <div class="booking-ticket-actions">
                            <a href="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('payment_go_to_bookings'); ?></a>
                            <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn payment-secondary-btn"><?php echo t('store_checkout_back_store'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (empty($checkout_items)): ?>
            <div class="booking-lookup-panel">
                <span class="ticket-label"><?php echo t('store_basket'); ?></span>
                <h2><?php echo t('store_checkout_empty_title'); ?></h2>
                <p><?php echo t('store_checkout_empty_text'); ?></p>
                <a href="<?php echo htmlspecialchars(site_url('user/store.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('store_checkout_back_store'); ?></a>
            </div>
        <?php else: ?>
            <div class="checkout-shell">
                <div class="checkout-panel checkout-summary-panel">
                    <div class="checkout-panel-header">
                        <div>
                            <span class="ticket-label"><?php echo t('store_checkout_review'); ?></span>
                            <h2><?php echo t('store_basket_title'); ?></h2>
                        </div>
                    </div>

                    <div class="checkout-total-card store-checkout-items">
                        <?php foreach ($checkout_items as $item): ?>
                            <?php $item_image = site_asset_url($item['image_path'] ?? '', 'images/store.jpg'); ?>
                            <div class="store-checkout-item-row">
                                <img src="<?php echo htmlspecialchars($item_image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <div>
                                    <span><?php echo htmlspecialchars(translated_category_label($item['category'])); ?></span>
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <small><?php echo number_format($item['price'], 2); ?> JOD x <?php echo (int)$item['quantity']; ?></small>
                                </div>
                                <b><?php echo number_format($item['line_total'], 2); ?> JOD</b>
                            </div>
                        <?php endforeach; ?>
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

                    <p class="simulation-note"><?php echo t('payment_note'); ?></p>

                    <form method="POST" action="<?php echo htmlspecialchars(site_url('user/store_checkout.php'), ENT_QUOTES, 'UTF-8'); ?>" class="checkout-form">
                        <input type="hidden" name="checkout_action" value="confirm">
                        <input type="hidden" name="cart_data" value="<?php echo htmlspecialchars($cart_data_raw, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
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
                            <button type="submit" class="btn payment-submit-btn"><?php echo t('store_checkout_confirm'); ?></button>
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
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('[data-store-order-success]')) {
        window.localStorage.removeItem('famousGamingStoreCart');
    }

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
            this.value = digits.length >= 3 ? digits.slice(0, 2) + '/' + digits.slice(2) : digits;
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

<?php
mysqli_close($conn);
include dirname(__DIR__) . '/includes/footer.php';
?>
