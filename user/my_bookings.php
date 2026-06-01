<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

function booking_payment_status_key($status) {
    return strtolower(str_replace(' ', '_', trim((string)$status)));
}

$page_title = t('my_bookings_page_title');
$shared_hero_image = site_url('images/shared-public-hero.jpg');
$success_msg = '';
$error_msg = '';

ensure_user_auth_schema($conn);
$current_site_user = get_current_site_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_session_token = $_SESSION['customer_booking_token'] ?? '';
    $site_user_id = $current_site_user ? (int)$current_site_user['id'] : 0;
    $ticket_action = sanitize_input($_POST['ticket_action'] ?? '');
    $ticket_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;

    if ($ticket_action === 'cancel' && $ticket_id > 0 && (!empty($customer_session_token) || $site_user_id > 0)) {
        $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = 'Cancelled' WHERE id = ? AND status = 'Confirmed' AND (customer_session_token = ? OR user_id = ?)");
        mysqli_stmt_bind_param($stmt, "isi", $ticket_id, $customer_session_token, $site_user_id);
        mysqli_stmt_execute($stmt);
        $updated = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($updated > 0) {
            create_admin_notification(
                $conn,
                'booking_cancelled',
                'Booking cancelled',
                'Booking #' . $ticket_id . ' was cancelled by the customer.',
                'bookings',
                $ticket_id,
                'booking_details.php?id=' . $ticket_id
            );

            if ($site_user_id > 0) {
                create_site_notification(
                    $conn,
                    $site_user_id,
                    'booking_cancelled',
                    t('my_bookings_cancel_success'),
                    t('my_bookings_cancel_success'),
                    'user/my_bookings.php'
                );
            }
        }

        $success_msg = $updated > 0 ? t('my_bookings_cancel_success') : t('my_bookings_cancel_error');
    } elseif ($ticket_action === 'cancel') {
        $error_msg = t('my_bookings_session_missing');
    } elseif ($ticket_action === 'rate' && $ticket_id > 0) {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $rating_message = sanitize_input($_POST['rating_message'] ?? '');
        $customer_name = $current_site_user['full_name'] ?? ($_SESSION['customer_name'] ?? 'Customer');
        $phone = $current_site_user['phone'] ?? ($_SESSION['customer_phone'] ?? '');
        $feedback_message = 'Rating ' . $rating . '/5 for booking #' . $ticket_id;

        if ($rating_message !== '') {
            $feedback_message .= ': ' . $rating_message;
        }

        $rating_user_id = $current_site_user ? (int)$current_site_user['id'] : null;
        $stmt = mysqli_prepare($conn, "INSERT INTO complaints (user_id, customer_name, phone, message) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $rating_user_id, $customer_name, $phone, $feedback_message);

        if (mysqli_stmt_execute($stmt)) {
            $complaint_id = mysqli_insert_id($conn);
            create_admin_notification(
                $conn,
                'feedback_created',
                'New booking rating submitted',
                $customer_name . ' rated booking #' . $ticket_id . ' with ' . $rating . '/5.',
                'complaints',
                $complaint_id,
                'complaints_full_crud.php'
            );
            $success_msg = t('my_bookings_rating_saved');
        } else {
            $error_msg = t('complaints_error');
        }

        mysqli_stmt_close($stmt);
    }

    $booking_lookup = strtoupper(sanitize_input($_POST['booking_lookup'] ?? ''));
    $phone = sanitize_input($_POST['phone'] ?? '');
    $booking_id = ctype_digit($booking_lookup) ? (int)$booking_lookup : 0;

    if ($ticket_action === 'cancel' || $ticket_action === 'rate') {
        // Ticket action already handled above.
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
$store_orders = [];

if (!empty($customer_session_token) || $current_site_user) {
    $query = "SELECT b.*, r.room_name, r.room_type
              FROM bookings b
              LEFT JOIN rooms r ON b.room_id = r.id
              WHERE b.customer_session_token = ? OR b.user_id = ?
              ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC";
    $stmt = mysqli_prepare($conn, $query);
    $site_user_id = $current_site_user ? (int)$current_site_user['id'] : 0;
    mysqli_stmt_bind_param($stmt, "si", $customer_session_token, $site_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

if ($current_site_user) {
    $store_orders = get_user_store_orders($conn, (int)$current_site_user['id'], 80);
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="hero my-bookings-hero" style="--page-hero-image: url('<?php echo htmlspecialchars($shared_hero_image, ENT_QUOTES, 'UTF-8'); ?>');">
    <div class="container my-bookings-hero-copy">
        <h1><?php echo t('my_bookings_hero_title'); ?></h1>
        <p><?php echo t('my_bookings_hero_text'); ?></p>
    </div>
</section>

<section class="content my-bookings-content">
    <div class="container">
        <?php if ($success_msg): ?>
            <div class="message success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="message error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($bookings) || !empty($store_orders)): ?>
            <div class="my-bookings-heading">
                <div>
                    <span class="ticket-label"><?php echo t('my_bookings_history_title'); ?></span>
                    <?php if ($current_site_user): ?>
                        <h2><?php echo htmlspecialchars($current_site_user['full_name']); ?></h2>
                    <?php endif; ?>
                </div>
                <?php if ($current_site_user): ?>
                    <div class="loyalty-summary-pill">
                        <span><?php echo t('loyalty_points'); ?></span>
                        <strong><?php echo (int)$current_site_user['loyalty_points']; ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($bookings)): ?>
                <div class="my-bookings-history-panel">
                    <div class="my-bookings-table-title">
                        <div>
                            <span class="ticket-label"><?php echo t('booking_ticket_label'); ?></span>
                            <h3><?php echo t('my_bookings_history_title'); ?></h3>
                        </div>
                    </div>
                <div class="responsive-table-wrapper my-bookings-table-wrapper">
                    <table class="minimal-data-table bookings-data-table">
                        <thead>
                            <tr>
                                <th><?php echo t('booking_code_label'); ?></th>
                                <th><?php echo t('common_customer'); ?></th>
                                <th><?php echo t('booking_device_session'); ?></th>
                                <th><?php echo t('common_date'); ?></th>
                                <th><?php echo t('common_time'); ?></th>
                                <th><?php echo t('common_payment'); ?></th>
                                <th><?php echo t('common_total'); ?></th>
                                <th><?php echo t('common_status'); ?></th>
                                <th><?php echo t('common_actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <?php
                                $ticket_code = $booking['booking_code'] ?: ('FG-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT));
                                $ticket_device = trim(($booking['room_name'] ?? '') . ' - ' . ($booking['room_type'] ?? ''), ' -');
                                $ticket_status = t('status_' . strtolower($booking['status']), [], $booking['status']);
                                $ticket_payment_status = $booking['payment_status'] ?? 'Unpaid';
                                $ticket_payment = t('status_' . booking_payment_status_key($ticket_payment_status), [], $ticket_payment_status);
                                $ticket_total = number_format((float)($booking['final_total'] ?? $booking['total_price']), 2) . ' JOD';
                                $ticket_time = format_time($booking['start_time']) . ' - ' . translated_hours_label($booking['hours']);
                                ?>
                                <tr>
                                    <td data-label="<?php echo htmlspecialchars(t('booking_code_label'), ENT_QUOTES, 'UTF-8'); ?>" class="booking-code-cell"><strong><?php echo htmlspecialchars($ticket_code); ?></strong></td>
                                    <td data-label="<?php echo htmlspecialchars(t('common_customer'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                    <td data-label="<?php echo htmlspecialchars(t('booking_device_session'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ticket_device); ?></td>
                                    <td data-label="<?php echo htmlspecialchars(t('common_date'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo format_date($booking['booking_date']); ?></td>
                                    <td data-label="<?php echo htmlspecialchars(t('common_time'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ticket_time); ?></td>
                                    <td data-label="<?php echo htmlspecialchars(t('common_payment'), ENT_QUOTES, 'UTF-8'); ?>"><span class="booking-payment-pill"><?php echo htmlspecialchars($ticket_payment); ?></span></td>
                                    <td data-label="<?php echo htmlspecialchars(t('common_total'), ENT_QUOTES, 'UTF-8'); ?>" class="booking-total-cell"><?php echo htmlspecialchars($ticket_total); ?></td>
                                    <td data-label="<?php echo htmlspecialchars(t('common_status'), ENT_QUOTES, 'UTF-8'); ?>"><span class="ticket-status status-<?php echo strtolower(htmlspecialchars($booking['status'])); ?>"><?php echo htmlspecialchars($ticket_status); ?></span></td>
                                    <td data-label="<?php echo htmlspecialchars(t('common_actions'), ENT_QUOTES, 'UTF-8'); ?>" class="booking-actions-cell">
                                        <div class="table-action-group">
                                            <button
                                                type="button"
                                                class="btn btn-small payment-secondary-btn"
                                                data-ticket-open
                                                data-ticket-code="<?php echo htmlspecialchars($ticket_code, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-ticket-customer="<?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-ticket-device="<?php echo htmlspecialchars($ticket_device, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-ticket-date="<?php echo htmlspecialchars(format_date($booking['booking_date']), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-ticket-time="<?php echo htmlspecialchars($ticket_time, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-ticket-status="<?php echo htmlspecialchars($ticket_status, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-ticket-payment="<?php echo htmlspecialchars($ticket_payment, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-ticket-total="<?php echo htmlspecialchars($ticket_total, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-ticket-points="<?php echo (int)($booking['loyalty_points_earned'] ?? 0); ?>"
                                                data-ticket-barcode="<?php echo htmlspecialchars(render_booking_barcode($booking['booking_code'] ?: $booking['id']), ENT_QUOTES, 'UTF-8'); ?>"
                                            ><?php echo t('booking_ticket_label'); ?></button>
                                            <button type="button" class="btn btn-small payment-secondary-btn" data-invoice-open><?php echo t('my_bookings_invoice'); ?></button>
                                            <?php if ($booking['status'] !== 'Cancelled'): ?>
                                                <button type="button" class="btn btn-small payment-secondary-btn" data-rating-open data-booking-id="<?php echo (int)$booking['id']; ?>"><?php echo t('my_bookings_rate'); ?></button>
                                            <?php endif; ?>
                                            <?php if ($booking['status'] !== 'Cancelled' && ($booking['payment_status'] ?? 'Unpaid') !== 'Paid'): ?>
                                                <a href="<?php echo htmlspecialchars(site_url('user/payment.php?booking_id=' . (int)$booking['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-small payment-checkout-btn"><?php echo t('my_bookings_simulate_payment'); ?></a>
                                            <?php endif; ?>
                                            <?php if ($booking['status'] === 'Confirmed'): ?>
                                                <form method="POST" action="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="ticket-action-form" data-confirm-message="<?php echo htmlspecialchars(t('my_bookings_cancel_confirm'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm-title="<?php echo htmlspecialchars(t('modal_confirm_title'), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                                                    <input type="hidden" name="ticket_action" value="cancel">
                                                    <button type="submit" class="btn btn-small ticket-cancel-btn"><?php echo t('my_bookings_cancel'); ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($store_orders)): ?>
                <div class="store-order-history-section">
                    <div class="home-section-heading">
                        <span class="ticket-label"><?php echo t('nav_store'); ?></span>
                        <h2><?php echo t('store_order_history_title'); ?></h2>
                        <p><?php echo t('store_order_history_text'); ?></p>
                    </div>

                    <div class="store-order-history-list">
                        <?php foreach ($store_orders as $order): ?>
                            <?php $order_items = get_store_order_items($conn, (int)$order['id']); ?>
                            <?php
                            $store_order_payment_status = $order['payment_status'];
                            $store_order_payment_key = normalize_status_key($store_order_payment_status);
                            $store_order_payment_class = normalize_status_class($store_order_payment_status);
                            ?>
                            <article class="store-order-card">
                                <div class="store-order-card-head">
                                    <div>
                                        <span><?php echo t('store_order_code'); ?></span>
                                        <h3><?php echo htmlspecialchars($order['order_code']); ?></h3>
                                    </div>
                                    <span class="checkout-status-pill status-<?php echo htmlspecialchars($store_order_payment_class, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(t('status_' . $store_order_payment_key, [], $store_order_payment_status)); ?>
                                    </span>
                                </div>
                                <div class="store-order-card-grid">
                                    <div>
                                        <span><?php echo t('common_date'); ?></span>
                                        <strong><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></strong>
                                    </div>
                                    <div>
                                        <span><?php echo t('common_payment'); ?></span>
                                        <strong><?php echo htmlspecialchars(t('payment_' . strtolower($order['payment_method']), [], $order['payment_method'])); ?></strong>
                                    </div>
                                    <div>
                                        <span><?php echo t('common_total'); ?></span>
                                        <strong><?php echo number_format((float)$order['total_amount'], 2); ?> JOD</strong>
                                    </div>
                                    <div>
                                        <span><?php echo t('loyalty_points'); ?></span>
                                        <strong><?php echo (int)$order['loyalty_points_earned']; ?></strong>
                                    </div>
                                </div>
                                <?php if (!empty($order_items)): ?>
                                    <div class="store-order-items">
                                        <?php foreach ($order_items as $item): ?>
                                            <div>
                                                <span><?php echo htmlspecialchars($item['product_name']); ?> x<?php echo (int)$item['quantity']; ?></span>
                                                <strong><?php echo number_format((float)$item['item_total'], 2); ?> JOD</strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-bookings">
                <h2><?php echo t('my_bookings_empty_title'); ?></h2>
                <p><?php echo t('my_bookings_empty_text'); ?></p>
                <a href="<?php echo htmlspecialchars(site_url('user/user_dashboard.php#dashboard-rooms'), ENT_QUOTES, 'UTF-8'); ?>" class="btn"><?php echo t('nav_book_now'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="booking-ticket-modal ticket-preview-modal is-hidden" id="ticketPreviewModal" hidden>
    <div class="booking-ticket-modal-backdrop" data-ticket-preview-close></div>
    <div class="booking-ticket-modal-panel">
        <button type="button" class="booking-ticket-close" data-ticket-preview-close aria-label="<?php echo htmlspecialchars(t('booking_close_ticket'), ENT_QUOTES, 'UTF-8'); ?>">X</button>
        <div class="booking-ticket" id="ticketPreviewCard">
            <div class="booking-ticket-header">
                <div>
                    <span class="ticket-label"><?php echo t('booking_ticket_label'); ?></span>
                    <h2><?php echo t('booking_ticket_ready'); ?></h2>
                    <p><?php echo t('booking_ticket_arrival'); ?></p>
                </div>
                <span class="ticket-status" data-ticket-preview-status></span>
            </div>

            <div class="booking-ticket-code">
                <span><?php echo t('booking_barcode'); ?></span>
                <div class="ticket-barcode" data-ticket-preview-barcode aria-label="<?php echo htmlspecialchars(t('booking_barcode'), ENT_QUOTES, 'UTF-8'); ?>"></div>
            </div>

            <div class="booking-ticket-grid">
                <div><span><?php echo t('common_customer'); ?></span><strong data-ticket-preview-customer></strong></div>
                <div><span><?php echo t('booking_device_session'); ?></span><strong data-ticket-preview-device></strong></div>
                <div><span><?php echo t('common_date'); ?></span><strong data-ticket-preview-date></strong></div>
                <div><span><?php echo t('common_time'); ?></span><strong data-ticket-preview-time></strong></div>
                <div><span><?php echo t('common_payment'); ?></span><strong data-ticket-preview-payment></strong></div>
                <div><span><?php echo t('common_total'); ?></span><strong data-ticket-preview-total></strong></div>
                <div><span><?php echo t('loyalty_points'); ?></span><strong data-ticket-preview-points></strong></div>
            </div>

            <div class="booking-ticket-actions">
                <button type="button" class="btn download-ticket-btn"><?php echo t('booking_save_ticket'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="site-modal rating-modal" id="ratingModal" hidden>
    <div class="site-modal-backdrop" data-rating-close></div>
    <div class="site-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ratingModalTitle">
        <button type="button" class="site-modal-close" data-rating-close aria-label="<?php echo htmlspecialchars(t('common_close'), ENT_QUOTES, 'UTF-8'); ?>">X</button>
        <h3 id="ratingModalTitle"><?php echo t('my_bookings_rating_title'); ?></h3>
        <form method="POST" action="<?php echo htmlspecialchars(site_url('user/my_bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="rating-form">
            <input type="hidden" name="ticket_action" value="rate">
            <input type="hidden" name="booking_id" id="ratingBookingId" value="">
            <div class="rating-options" role="radiogroup" aria-label="<?php echo htmlspecialchars(t('my_bookings_rating_title'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                    <label>
                        <input type="radio" name="rating" value="<?php echo $rating; ?>" <?php echo $rating === 5 ? 'checked' : ''; ?>>
                        <span><?php echo $rating; ?></span>
                    </label>
                <?php endfor; ?>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo t('complaints_message'); ?></label>
                <textarea name="rating_message" class="form-control" rows="4"></textarea>
            </div>
            <button type="submit" class="btn w-100"><?php echo t('common_submit'); ?></button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ratingModal = document.getElementById('ratingModal');
    const ratingBookingId = document.getElementById('ratingBookingId');
    const ticketPreviewModal = document.getElementById('ticketPreviewModal');
    const ticketPreviewCard = document.getElementById('ticketPreviewCard');

    function setPreviewText(selector, value) {
        const target = ticketPreviewModal ? ticketPreviewModal.querySelector(selector) : null;

        if (target) {
            target.textContent = value || '';
        }
    }

    function openTicketPreview(button) {
        if (!ticketPreviewModal || !ticketPreviewCard) {
            return;
        }

        const data = button.dataset;
        ticketPreviewCard.dataset.ticketCode = data.ticketCode || '';
        ticketPreviewCard.dataset.ticketCustomer = data.ticketCustomer || '';
        ticketPreviewCard.dataset.ticketDevice = data.ticketDevice || '';
        ticketPreviewCard.dataset.ticketDate = data.ticketDate || '';
        ticketPreviewCard.dataset.ticketTime = data.ticketTime || '';
        ticketPreviewCard.dataset.ticketStatus = data.ticketStatus || '';

        setPreviewText('[data-ticket-preview-status]', data.ticketStatus || '');
        setPreviewText('[data-ticket-preview-customer]', data.ticketCustomer || '');
        setPreviewText('[data-ticket-preview-device]', data.ticketDevice || '');
        setPreviewText('[data-ticket-preview-date]', data.ticketDate || '');
        setPreviewText('[data-ticket-preview-time]', data.ticketTime || '');
        setPreviewText('[data-ticket-preview-payment]', data.ticketPayment || '');
        setPreviewText('[data-ticket-preview-total]', data.ticketTotal || '');
        setPreviewText('[data-ticket-preview-points]', data.ticketPoints || '0');

        const barcode = ticketPreviewModal.querySelector('[data-ticket-preview-barcode]');

        if (barcode) {
            barcode.innerHTML = data.ticketBarcode || '';
        }

        ticketPreviewModal.hidden = false;
        ticketPreviewModal.classList.remove('is-hidden');
        document.body.classList.add('booking-ticket-modal-open');
    }

    function closeTicketPreview() {
        if (!ticketPreviewModal) {
            return;
        }

        ticketPreviewModal.classList.add('is-hidden');
        ticketPreviewModal.hidden = true;
        document.body.classList.remove('booking-ticket-modal-open');
    }

    document.querySelectorAll('[data-ticket-open]').forEach(function(button) {
        button.addEventListener('click', function() {
            openTicketPreview(button);
        });
    });

    document.querySelectorAll('[data-ticket-preview-close]').forEach(function(button) {
        button.addEventListener('click', closeTicketPreview);
    });

    document.querySelectorAll('[data-rating-open]').forEach(function(button) {
        button.addEventListener('click', function() {
            if (!ratingModal || !ratingBookingId) {
                return;
            }

            ratingBookingId.value = button.dataset.bookingId || '';
            ratingModal.hidden = false;
            document.body.classList.add('site-modal-open');
        });
    });

    document.querySelectorAll('[data-rating-close]').forEach(function(button) {
        button.addEventListener('click', function() {
            if (ratingModal) {
                ratingModal.hidden = true;
                document.body.classList.remove('site-modal-open');
            }
        });
    });

    document.querySelectorAll('[data-invoice-open]').forEach(function(button) {
        button.addEventListener('click', function() {
            const ticketButton = button.closest('.table-action-group') ? button.closest('.table-action-group').querySelector('[data-ticket-open]') : null;
            if (!ticketButton || !window.showSiteModal) {
                return;
            }

            const details = [
                '<?php echo addslashes(t('booking_code_label')); ?>: ' + (ticketButton.dataset.ticketCode || ''),
                '<?php echo addslashes(t('booking_device_session')); ?>: ' + (ticketButton.dataset.ticketDevice || ''),
                '<?php echo addslashes(t('common_date')); ?>: ' + (ticketButton.dataset.ticketDate || ''),
                '<?php echo addslashes(t('common_time')); ?>: ' + (ticketButton.dataset.ticketTime || ''),
                '<?php echo addslashes(t('common_payment')); ?>: ' + (ticketButton.dataset.ticketPayment || ''),
                '<?php echo addslashes(t('common_total')); ?>: ' + (ticketButton.dataset.ticketTotal || '')
            ].join('\n');

            window.showSiteModal({
                title: '<?php echo addslashes(t('my_bookings_invoice')); ?>',
                message: details,
                type: 'info'
            });
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && ticketPreviewModal && !ticketPreviewModal.hidden) {
            closeTicketPreview();
        }
    });
});
</script>

<?php
include dirname(__DIR__) . '/includes/footer.php';
?>
