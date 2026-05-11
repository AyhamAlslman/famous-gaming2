<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = 'Book Now - FAMOUS GAMING';
include 'includes/header.php';

$success_msg = '';
$error_msg = '';
$preselected_room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

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
        $validation_errors[] = 'Customer name is required';
    }

    if (!validate_phone($phone)) {
        $validation_errors[] = 'Invalid phone number format. Must be Jordan format (07XXXXXXXX)';
    }

    if (!validate_booking_date($booking_date)) {
        $validation_errors[] = 'Invalid booking date. Date must be today or in the future';
    }

    if (!validate_time($start_time)) {
        $validation_errors[] = 'Invalid time format';
    }

    if (!validate_hour_interval($start_time)) {
        $validation_errors[] = 'Start time must be on the hour (e.g., 10:00, 14:00, 18:00)';
    }

    $min_hours = (int)get_setting($conn, 'min_booking_hours', 1);
    $max_hours = (int)get_setting($conn, 'max_booking_hours', 12);

    if ($hours < $min_hours || $hours > $max_hours) {
        $validation_errors[] = "Hours must be between $min_hours and $max_hours";
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
            $error_msg = 'Selected room not found';
        } elseif ($room['status'] !== 'Available') {
            $error_msg = 'Selected room is not available';
        } else {
            // Check if within business hours
            if (!is_within_business_hours($conn, $booking_date, $start_time, $hours)) {
                $error_msg = 'Selected time is outside business hours. Please check our operating hours';
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

                    $error_msg = 'This room is already booked during your selected time slot:<br>';
                    $error_msg .= implode('<br>', $conflict_times);
                    $error_msg .= '<br>Please choose a different time or room';
                } else {
                    // Calculate total price
                    $total_price = $room['price_per_hour'] * $hours;

                    // Insert booking using prepared statement
                    $stmt = mysqli_prepare($conn, "INSERT INTO bookings (customer_name, phone, room_id, booking_date, start_time, hours, total_price, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");

                    mysqli_stmt_bind_param($stmt, "ssissids", $customer_name, $phone, $room_id, $booking_date, $start_time, $hours, $total_price, $notes);

                    if (mysqli_stmt_execute($stmt)) {
                        $booking_id = mysqli_insert_id($conn);
                        $success_msg = 'Booking submitted successfully! We will contact you soon to confirm.<br>';
                        $success_msg .= '<strong>Booking Details:</strong><br>';
                        $success_msg .= 'Room: ' . htmlspecialchars($room['room_name']) . '<br>';
                        $success_msg .= 'Date: ' . format_date($booking_date) . '<br>';
                        $success_msg .= 'Time: ' . format_time($start_time) . ' (' . $hours . ' hour' . ($hours > 1 ? 's' : '') . ')<br>';
                        $success_msg .= 'Total: ' . format_price($total_price);

                        // Clear form by redirecting
                        $_POST = [];
                    } else {
                        $error_msg = 'Error submitting booking. Please try again';
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
        <h1>Book Your Gaming Session</h1>
        <p>Fill out the form below to reserve your room</p>
    </div>
</section>

<section class="content">
    <div class="container">
        <?php if ($success_msg): ?>
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
                            <label class="form-label">Your Name *</label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" name="phone" class="form-control" required placeholder="07XXXXXXXX">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label">Select Room *</label>
                            <select name="room_id" id="room_id" class="form-select" required>
                                <option value="">Choose a room...</option>
                                <?php
                                $rooms_query = "SELECT * FROM rooms WHERE status = 'Available' ORDER BY room_name ASC";
                                $rooms_result = mysqli_query($conn, $rooms_query);
                                while ($room = mysqli_fetch_assoc($rooms_result)) {
                                    $selected = ($preselected_room_id === (int)$room['id']) ? ' selected' : '';
                                    echo '<option value="' . $room['id'] . '"' . $selected . '>';
                                    echo htmlspecialchars($room['room_name']) . ' - ' . htmlspecialchars($room['room_type']);
                                    echo ' (' . number_format($room['price_per_hour'], 2) . ' JOD/hr)';
                                    echo '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Booking Date *</label>
                            <input type="date" name="booking_date" id="booking_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Number of Hours *</label>
                            <input type="number" name="hours" id="hours" class="form-control" required min="1" max="12" value="2">
                        </div>
                    </div>
                </div>

                <div id="slot_availability">
                    <div class="slot-availability-container">
                        <strong class="slot-availability-title">Available Time Slots:</strong>
                        <div id="slot_status">
                            Loading slots...
                        </div>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label">Start Time *</label>
                    <select name="start_time" class="form-select" required id="start_time_select">
                        <option value="">Choose a time...</option>
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
                        Select room, date, and hours first to see available time slots
                    </small>
                </div>

                <div class="form-group mt-3">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="notes" class="form-control" rows="4"></textarea>
                </div>

                <button type="submit" class="btn booking-submit-btn mt-4 w-100">
                    Submit Booking
                </button>
            </form>
        </div>

        <div class="booking-info-container">
            <h3 class="booking-info-title">Booking Information</h3>
            <ul class="booking-info-list">
                <li>All bookings are subject to confirmation</li>
                <li>We will contact you within 30 minutes to confirm your booking</li>
                <li>Please arrive 10 minutes before your scheduled time</li>
                <li>Cancellations should be made at least 2 hours in advance</li>
                <li>Payment can be made at the venue (cash or card accepted)</li>
            </ul>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roomSelect = document.getElementById('room_id');
    const dateInput = document.getElementById('booking_date');
    const hoursInput = document.getElementById('hours');
    const timeSelect = document.getElementById('start_time_select');
    const slotAvailability = document.getElementById('slot_availability');
    const slotStatus = document.getElementById('slot_status');
    const bookingForm = document.getElementById('booking-form');
    const params = new URLSearchParams(window.location.search);
    const requestedRoomId = params.get('room_id');

    let currentSlots = [];

    // Function to fetch available slots
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
        slotStatus.innerHTML = '<span class="slot-status-loading">⏳ Loading available slots...</span>';

        // Make AJAX request
        fetch(`get_available_slots.php?room_id=${roomId}&booking_date=${bookingDate}&hours=${hours}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    slotStatus.innerHTML = `<span class="slot-status-error">❌ Error: ${data.error}</span>`;
                    return;
                }

                currentSlots = data.slots;
                updateSlotDisplay(data.slots);
                updateTimeSelectOptions(data.slots);
            })
            .catch(error => {
                console.error('Error fetching slots:', error);
                slotStatus.innerHTML = '<span class="slot-status-error">❌ Error loading slots. Please try again.</span>';
            });
    }

    // Function to update slot display
    function updateSlotDisplay(slots) {
        if (!slots || slots.length === 0) {
            slotStatus.innerHTML = '<span class="slot-status-warning">⚠️ No time slots configured for this room.</span>';
            return;
        }

        const availableSlots = slots.filter(slot => slot.available);
        const unavailableSlots = slots.filter(slot => !slot.available);

        let html = '<div class="slot-display-container">';

        if (availableSlots.length > 0) {
            html += '<div class="slot-section">';
            html += '<strong class="slot-section-title available">✓ Available Slots:</strong><br>';
            html += '<div class="slot-badges-container">';
            availableSlots.forEach(slot => {
                html += `<span class="slot-badge available">${slot.label}</span>`;
            });
            html += '</div></div>';
        }

        if (unavailableSlots.length > 0) {
            html += '<div class="slot-section">';
            html += '<strong class="slot-section-title unavailable">✗ Booked/Unavailable:</strong><br>';
            html += '<div class="slot-badges-container">';
            unavailableSlots.forEach(slot => {
                html += `<span class="slot-badge unavailable">${slot.label}</span>`;
            });
            html += '</div></div>';
        }

        html += '</div>';

        if (availableSlots.length === 0) {
            html += '<div class="slot-no-available">';
            html += '<strong>❌ No available slots for the selected date and duration.</strong>';
            html += '<small>Please try a different date, shorter duration, or another room.</small>';
            html += '</div>';
        }

        slotStatus.innerHTML = html;
    }

    // Function to update time select dropdown options
    function updateTimeSelectOptions(slots) {
        // Clear existing options except the first one
        timeSelect.innerHTML = '<option value="">Choose a time...</option>';

        if (!slots || slots.length === 0) {
            return;
        }

        slots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.time;
            option.textContent = slot.label;

            if (!slot.available) {
                option.disabled = true;
                option.textContent += ' (Booked)';
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
