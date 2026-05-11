<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$booking_date = isset($_GET['booking_date']) ? sanitize_input($_GET['booking_date']) : '';
$hours = isset($_GET['hours']) ? intval($_GET['hours']) : 1;

if ($room_id === 0 || empty($booking_date)) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// Validate date
if (!validate_booking_date($booking_date)) {
    echo json_encode(['error' => 'Invalid date']);
    exit;
}

// Get all time slots for this room (or global slots)
$slots_query = "SELECT slot_time, slot_label
                FROM time_slots
                WHERE is_active = 1
                AND (room_id = ? OR room_id IS NULL)
                ORDER BY slot_time";

$stmt = mysqli_prepare($conn, $slots_query);
mysqli_stmt_bind_param($stmt, "i", $room_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$all_slots = [];

while ($row = mysqli_fetch_assoc($result)) {
    $all_slots[] = [
        'time' => $row['slot_time'],
        'label' => $row['slot_label'],
        'available' => true
    ];
}
mysqli_stmt_close($stmt);

// Check for existing bookings on this date for this room
$booking_query = "SELECT start_time, end_time
                  FROM bookings
                  WHERE room_id = ?
                  AND booking_date = ?
                  AND status != 'Cancelled'";

$stmt = mysqli_prepare($conn, $booking_query);
mysqli_stmt_bind_param($stmt, "is", $room_id, $booking_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$booked_times = [];
while ($row = mysqli_fetch_assoc($result)) {
    $booked_times[] = [
        'start' => $row['start_time'],
        'end' => $row['end_time']
    ];
}
mysqli_stmt_close($stmt);

// Mark slots as unavailable if they conflict with existing bookings
foreach ($all_slots as $key => $slot) {
    $slot_start = $slot['time'];

    // Calculate end time for this slot based on duration
    $slot_end_timestamp = strtotime($slot_start) + ($hours * 3600);
    $slot_end = date('H:i:s', $slot_end_timestamp);

    // Check if this slot conflicts with any booking
    foreach ($booked_times as $booked) {
        $booked_start = $booked['start'];
        $booked_end = $booked['end'];

        // Check for overlap
        // Slot overlaps if: slot_start < booked_end AND slot_end > booked_start
        if ($slot_start < $booked_end && $slot_end > $booked_start) {
            $all_slots[$key]['available'] = false;
            break;
        }
    }

    // Also check if slot end time goes beyond midnight (23:59:59)
    if ($slot_end_timestamp > strtotime('23:59:59')) {
        $all_slots[$key]['available'] = false;
    }
}

// Check business hours
$day_of_week = date('l', strtotime($booking_date));
$hours_query = "SELECT opening_time, closing_time, is_open
                FROM business_hours
                WHERE day_of_week = ?";

$stmt = mysqli_prepare($conn, $hours_query);
mysqli_stmt_bind_param($stmt, "s", $day_of_week);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$business_hours = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($business_hours && !$business_hours['is_open']) {
    // Mark all slots as unavailable if closed
    foreach ($all_slots as $key => $slot) {
        $all_slots[$key]['available'] = false;
    }
} elseif ($business_hours) {
    // Mark slots outside business hours as unavailable
    $opening = $business_hours['opening_time'];
    $closing = $business_hours['closing_time'];

    foreach ($all_slots as $key => $slot) {
        if ($slot['time'] < $opening || $slot['time'] >= $closing) {
            $all_slots[$key]['available'] = false;
        }
    }
}

echo json_encode([
    'success' => true,
    'slots' => $all_slots,
    'room_id' => $room_id,
    'date' => $booking_date,
    'hours' => $hours
]);

mysqli_close($conn);
?>
