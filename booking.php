<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = t('booking_page_title');
include 'includes/header.php';

$success_msg = '';
$error_msg = '';
$confirmed_booking = null;
$preselected_room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
ensure_booking_confirmation_schema($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $customer_name = sanitize_input($_POST['customer_name']);
    $phone = sanitize_input($_POST['phone']);
    $room_id = intval($_POST['room_id']);
    $preselected_room_id = $room_id;
    $booking_date = sanitize_input($_POST['booking_date']);
    $start_time = sanitize_input($_POST['start_time']);
    $hours = intval($_POST['hours']);
    $notes = sanitize_input($_POST['notes']);

    // Validate inputs
    $validation_errors = [];

    if (empty($customer_name)) {
        $validation_errors[] = t('booking_validation_name');
    }

    if (!validate_phone($phone)) {
        $validation_errors[] = t('booking_validation_phone');
    }

    if (!validate_booking_date($booking_date)) {
        $validation_errors[] = t('booking_validation_date');
    }

    if (!validate_time($start_time)) {
        $validation_errors[] = t('booking_validation_time');
    }

    if (!validate_hour_interval($start_time)) {
        $validation_errors[] = t('booking_validation_hour_interval');
    }

    $min_hours = (int)get_setting($conn, 'min_booking_hours', 1);
    $max_hours = (int)get_setting($conn, 'max_booking_hours', 12);

    if ($hours < $min_hours || $hours > $max_hours) {
        $validation_errors[] = t('booking_validation_hours_range', ['min' => $min_hours, 'max' => $max_hours]);
    }

    if (count($validation_errors) > 0) {
        $error_msg = implode('<br>', $validation_errors);
    } else {
        // Get room details using prepared statement
        $stmt = mysqli_prepare($conn, "SELECT id, room_name, price_per_hour, status FROM rooms WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $room_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $room = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$room) {
            $error_msg = t('booking_room_not_found');
        } elseif ($room['status'] !== 'Available') {
            $error_msg = t('booking_room_unavailable');
        } else {
            // Check if within business hours
            if (!is_within_business_hours($conn, $booking_date, $start_time, $hours)) {
                $error_msg = t('booking_business_hours_error');
            } else {
                // Check for booking conflicts
                $availability = check_room_availability($conn, $room_id, $booking_date, $start_time, $hours);

                if (!$availability['available']) {
                    $conflicts = $availability['conflicts'];
                    $conflict_times = [];

                    foreach ($conflicts as $conflict) {
                        $conflict_start = format_time($conflict['start_time']);
                        $conflict_end = date('g:i A', strtotime($conflict['start_time']) + ($conflict['hours'] * 3600));
                        $conflict_times[] = "$conflict_start - $conflict_end";
                    }

                    $error_msg = t('booking_conflict_intro') . '<br>';
                    $error_msg .= implode('<br>', $conflict_times);
                    $error_msg .= '<br>' . t('booking_conflict_outro');
                } else {
                    // Calculate total price
                    $total_price = $room['price_per_hour'] * $hours;
                    $booking_code = generate_booking_code();
                    $customer_session_token = get_customer_session_token();
                    $_SESSION['customer_booking_token'] = $customer_session_token;
                    $_SESSION['customer_name'] = $customer_name;
                    $_SESSION['customer_phone'] = $phone;

                    // Insert booking using prepared statement
                    $stmt = mysqli_prepare($conn, "INSERT INTO bookings (booking_code, customer_name, phone, customer_session_token, room_id, booking_date, start_time, hours, total_price, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed', ?)");

                    mysqli_stmt_bind_param($stmt, "ssssissids", $booking_code, $customer_name, $phone, $customer_session_token, $room_id, $booking_date, $start_time, $hours, $total_price, $notes);

                    if (mysqli_stmt_execute($stmt)) {
                        $booking_id = mysqli_insert_id($conn);
                        create_admin_notification(
                            $conn,
                            'booking_created',
                            'New booking created',
                            $customer_name . ' booked ' . $room['room_name'] . ' on ' . $booking_date . ' at ' . $start_time . '.',
                            'bookings',
                            $booking_id,
                            'booking_details.php?id=' . $booking_id
                        );
                        create_admin_notification(
                            $conn,
                            'payment_pending',
                            'Payment still pending',
                            'Booking #' . $booking_id . ' is confirmed and waiting for payment.',
                            'bookings',
                            $booking_id,
                            'booking_details.php?id=' . $booking_id
                        );
                        $success_msg = t('booking_success');
                        $confirmed_booking = get_customer_booking_by_id($conn, $booking_id);

                        // Clear form by redirecting
                        $_POST = [];
                    } else {
                        $error_msg = t('booking_submit_error');
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}
?>

<section class="hero">
    <div class="container">
        <h1><?php echo t('booking_hero_title'); ?></h1>
        <p><?php echo t('booking_hero_text'); ?></p>
    </div>
</section>

<section class="content">
    <div class="container">
        <?php if ($confirmed_booking): ?>
            <div class="booking-ticket-modal" role="dialog" aria-modal="true" aria-labelledby="booking-ticket-title">
                <div class="booking-ticket-modal-backdrop" data-close-ticket-modal></div>
                <div class="booking-ticket-modal-panel">
                    <button type="button" class="booking-ticket-close" data-close-ticket-modal aria-label="<?php echo htmlspecialchars(t('booking_close_ticket'), ENT_QUOTES, 'UTF-8'); ?>">X</button>
                    <div class="message success booking-ticket-modal-message">
                        <?php echo $success_msg; ?>
                    </div>
                    <div class="booking-ticket"
                         data-ticket-code="<?php echo htmlspecialchars($confirmed_booking['booking_code'] ?: ('FG-' . str_pad($confirmed_booking['id'], 6, '0', STR_PAD_LEFT))); ?>"
                         data-ticket-customer="<?php echo htmlspecialchars($confirmed_booking['customer_name']); ?>"
                         data-ticket-device="<?php echo htmlspecialchars($confirmed_booking['room_name'] . ' - ' . $confirmed_booking['room_type']); ?>"
                         data-ticket-date="<?php echo htmlspecialchars(format_date($confirmed_booking['booking_date'])); ?>"
                         data-ticket-time="<?php echo htmlspecialchars(format_time($confirmed_booking['start_time']) . ' - ' . translated_hours_label($confirmed_booking['hours'])); ?>"
                         data-ticket-status="<?php echo htmlspecialchars($confirmed_booking['status']); ?>">
                        <div class="booking-ticket-header">
                            <div>
                                <span class="ticket-label"><?php echo t('booking_ticket_label'); ?></span>
                                <h2 id="booking-ticket-title"><?php echo t('booking_ticket_ready'); ?></h2>
                                <p><?php echo t('booking_ticket_arrival'); ?></p>
                            </div>
                            <span class="ticket-status"><?php echo htmlspecialchars(t('status_' . strtolower($confirmed_booking['status']), [], $confirmed_booking['status'])); ?></span>
                        </div>

                        <div class="booking-ticket-code">
                            <span><?php echo t('booking_barcode'); ?></span>
                            <div class="ticket-barcode" aria-label="<?php echo htmlspecialchars(t('booking_barcode'), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo render_booking_barcode($confirmed_booking['booking_code'] ?: $confirmed_booking['id']); ?>
                            </div>
                        </div>

                        <div class="booking-ticket-grid">
                            <div>
                                <span><?php echo t('common_customer'); ?></span>
                                <strong><?php echo htmlspecialchars($confirmed_booking['customer_name']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('booking_device_session'); ?></span>
                                <strong><?php echo htmlspecialchars($confirmed_booking['room_name']); ?> - <?php echo htmlspecialchars($confirmed_booking['room_type']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('common_date'); ?></span>
                                <strong><?php echo format_date($confirmed_booking['booking_date']); ?></strong>
                            </div>
                            <div>
                                <span><?php echo t('common_time'); ?></span>
                                <strong><?php echo format_time($confirmed_booking['start_time']); ?> - <?php echo translated_hours_label($confirmed_booking['hours']); ?></strong>
                            </div>
                        </div>

                        <div class="booking-ticket-actions">
                            <button type="button" class="btn download-ticket-btn"><?php echo t('booking_save_ticket'); ?></button>
                            <a href="my_bookings.php" class="btn"><?php echo t('booking_view_bookings'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($success_msg): ?>
            <div class="message success">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="message error">
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="form-container" id="booking-form">
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_name'); ?></label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_phone'); ?></label>
                            <input type="tel" name="phone" class="form-control" required placeholder="07XXXXXXXX">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_room'); ?></label>
                            <select name="room_id" id="room_id" class="form-select" required>
                                <option value=""><?php echo t('booking_form_choose_room'); ?></option>
                                <?php
                                $rooms_query = "SELECT * FROM rooms WHERE status = 'Available' ORDER BY room_name ASC";
                                $rooms_result = mysqli_query($conn, $rooms_query);
                                while ($room = mysqli_fetch_assoc($rooms_result)) {
                                    $selected = ($preselected_room_id === (int)$room['id']) ? ' selected' : '';
                                    echo '<option value="' . $room['id'] . '"' . $selected . '>';
                                    echo htmlspecialchars($room['room_name']) . ' - ' . htmlspecialchars($room['room_type']);
                                    echo ' (' . number_format($room['price_per_hour'], 2) . ' ' . t('home_room_price_suffix') . ')';
                                    echo '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_date'); ?></label>
                            <input type="date" name="booking_date" id="booking_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label"><?php echo t('booking_form_hours'); ?></label>
                            <input type="number" name="hours" id="hours" class="form-control" required min="1" max="12" value="2">
                        </div>
                    </div>
                </div>

                <div id="slot_availability">
                    <div class="slot-availability-container">
                        <strong class="slot-availability-title"><?php echo t('booking_slots_title'); ?></strong>
                        <div id="slot_status">
                            <?php echo t('booking_slots_loading'); ?>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label"><?php echo t('booking_form_start_time'); ?></label>
                    <select name="start_time" class="form-select" required id="start_time_select">
                        <option value=""><?php echo t('booking_form_choose_time'); ?></option>
                        <?php
                        // Fetch global time slots (room_id IS NULL) for initial display
                        $time_query = "SELECT slot_time, slot_label FROM time_slots WHERE is_active = 1 AND room_id IS NULL ORDER BY slot_time";
                        $time_result = mysqli_query($conn, $time_query);
                        if ($time_result && mysqli_num_rows($time_result) > 0) {
                            while ($slot = mysqli_fetch_assoc($time_result)) {
                                echo '<option value="' . htmlspecialchars($slot['slot_time']) . '">';
                                echo htmlspecialchars($slot['slot_label']);
                                echo '</option>';
                            }
                        } else {
                            // Fallback to default slots if none in database
                            for ($hour = 9; $hour <= 23; $hour++) {
                                $time_24 = sprintf('%02d:00:00', $hour);
                                $time_12 = date('g:i A', strtotime($time_24));
                                echo '<option value="' . $time_24 . '">' . $time_12 . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <small class="booking-time-hint form-text">
                        <?php echo t('booking_form_time_hint'); ?>
                    </small>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label"><?php echo t('booking_form_notes'); ?></label>
                    <textarea name="notes" class="form-control" rows="4"></textarea>
                </div>

                <button type="submit" class="btn booking-submit-btn mt-4 w-100">
                    <?php echo t('booking_submit'); ?>
                </button>
            </form>
        </div>

        <div class="booking-info-container">
            <h3 class="booking-info-title"><?php echo t('booking_info_title'); ?></h3>
            <ul class="booking-info-list">
                <li><?php echo t('booking_info_1'); ?></li>
                <li><?php echo t('booking_info_2'); ?></li>
                <li><?php echo t('booking_info_3'); ?></li>
                <li><?php echo t('booking_info_4'); ?></li>
                <li><?php echo t('booking_info_5'); ?></li>
            </ul>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookingTexts = <?php echo json_encode([
        'loading' => t('booking_slots_loading'),
        'error' => t('booking_slots_error'),
        'errorGeneric' => t('booking_slots_error_generic'),
        'noneConfigured' => t('booking_slots_none_configured'),
        'available' => t('booking_slots_available'),
        'unavailable' => t('booking_slots_unavailable'),
        'noneAvailableTitle' => t('booking_slots_none_available_title'),
        'noneAvailableText' => t('booking_slots_none_available_text'),
        'chooseTime' => t('booking_form_choose_time'),
        'booked' => t('booking_slot_booked')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const roomSelect = document.getElementById('room_id');
    const dateInput = document.getElementById('booking_date');
    const hoursInput = document.getElementById('hours');
    const timeSelect = document.getElementById('start_time_select');
    const slotAvailability = document.getElementById('slot_availability');
    const slotStatus = document.getElementById('slot_status');
    const bookingForm = document.getElementById('booking-form');
    const params = new URLSearchParams(window.location.search);
    const requestedRoomId = params.get('room_id');

    function fetchAvailableSlots() {
        const roomId = roomSelect.value;
        const bookingDate = dateInput.value;
        const hours = hoursInput.value;

        // Only fetch if all required fields are filled
        if (!roomId || !bookingDate || !hours) {
            slotAvailability.style.display = 'none';
            return;
        }

        // Show loading state
        slotAvailability.style.display = 'block';
        slotStatus.innerHTML = '<span class="slot-status-loading">' + bookingTexts.loading + '</span>';

        // Make AJAX request
        fetch(`get_available_slots.php?room_id=${roomId}&booking_date=${bookingDate}&hours=${hours}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    slotStatus.innerHTML = '<span class="slot-status-error">' + bookingTexts.error.replace(':message', data.error) + '</span>';
                    return;
                }

                currentSlots = data.slots;
                updateSlotDisplay(data.slots);
                updateTimeSelectOptions(data.slots);
            })
            .catch(error => {
                console.error('Error fetching slots:', error);
                slotStatus.innerHTML = '<span class="slot-status-error">' + bookingTexts.errorGeneric + '</span>';
            });
    }

    // Function to update slot display
    function updateSlotDisplay(slots) {
        if (!slots || slots.length === 0) {
            slotStatus.innerHTML = '<span class="slot-status-warning">' + bookingTexts.noneConfigured + '</span>';
            return;
        }

        const availableSlots = slots.filter(slot => slot.available);
        const unavailableSlots = slots.filter(slot => !slot.available);

        let html = '<div class="slot-display-container">';

        if (availableSlots.length > 0) {
            html += '<div class="slot-section">';
            html += '<strong class="slot-section-title available">' + bookingTexts.available + '</strong><br>';
            html += '<div class="slot-badges-container">';
            availableSlots.forEach(slot => {
                html += `<span class="slot-badge available">${slot.label}</span>`;
            });
            html += '</div></div>';
        }

        if (unavailableSlots.length > 0) {
            html += '<div class="slot-section">';
            html += '<strong class="slot-section-title unavailable">' + bookingTexts.unavailable + '</strong><br>';
            html += '<div class="slot-badges-container">';
            unavailableSlots.forEach(slot => {
                html += `<span class="slot-badge unavailable">${slot.label}</span>`;
            });
            html += '</div></div>';
        }

        html += '</div>';

        if (availableSlots.length === 0) {
            html += '<div class="slot-no-available">';
            html += '<strong>' + bookingTexts.noneAvailableTitle + '</strong>';
            html += '<small>' + bookingTexts.noneAvailableText + '</small>';
            html += '</div>';
        }

        slotStatus.innerHTML = html;
    }

    // Function to update time select dropdown options
    function updateTimeSelectOptions(slots) {
        // Clear existing options except the first one
        timeSelect.innerHTML = '<option value="">' + bookingTexts.chooseTime + '</option>';

        if (!slots || slots.length === 0) {
            return;
        }

        slots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.time;
            option.textContent = slot.label;

            if (!slot.available) {
                option.disabled = true;
                option.textContent += ' (' + bookingTexts.booked + ')';
                option.style.color = '#999';
                option.style.textDecoration = 'line-through';
            }

            timeSelect.appendChild(option);
        });
    }

    // Attach event listeners
    roomSelect.addEventListener('change', fetchAvailableSlots);
    dateInput.addEventListener('change', fetchAvailableSlots);
    hoursInput.addEventListener('change', fetchAvailableSlots);
    hoursInput.addEventListener('input', fetchAvailableSlots);

    if (requestedRoomId && roomSelect.querySelector(`option[value="${requestedRoomId}"]`)) {
        roomSelect.value = requestedRoomId;

        if (window.location.hash === '#booking-form' && bookingForm) {
            bookingForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
});
</script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
