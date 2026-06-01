<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

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

foreach ($all_slots as $key => $slot) {
    $slot_start = $slot['time'];
    $slot_end_timestamp = strtotime($slot_start) + ($hours * 3600);
    if ($slot_end_timestamp > strtotime('23:59:59')) {
        $all_slots[$key]['available'] = false;
        continue;
    }

    if (!is_within_business_hours($conn, $booking_date, $slot_start, $hours)) {
        $all_slots[$key]['available'] = false;
        continue;
    }

    $availability = check_room_availability($conn, $room_id, $booking_date, $slot_start, $hours);
    if (!$availability['available']) {
        $all_slots[$key]['available'] = false;
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
