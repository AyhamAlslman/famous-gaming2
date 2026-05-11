<?php
/**
 * Helper Functions for PlayStation PlayRoom
 * Validation, Sanitization, Image Handling, and Utilities
 */

// =====================================================
// 1. INPUT SANITIZATION & VALIDATION
// =====================================================

/**
 * Sanitize string input to prevent XSS
 */
function sanitize_input($data) {
    if ($data === null) return null;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate phone number (Jordan format)
 */
function validate_phone($phone) {
    $pattern = '/^07[0-9]{8}$/';
    return preg_match($pattern, $phone);
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate date format and ensure it's not in the past
 */
function validate_booking_date($date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return false;
    }

    // Check if date is today or in the future
    $today = strtotime(date('Y-m-d'));
    return $timestamp >= $today;
}

/**
 * Validate time format (HH:MM:SS or HH:MM)
 */
function validate_time($time) {
    return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time);
}

/**
 * Validate that time is in 1-hour intervals
 */
function validate_hour_interval($time) {
    if (!validate_time($time)) {
        return false;
    }

    // Extract minutes
    $parts = explode(':', $time);
    $minutes = (int)$parts[1];

    // Must be on the hour (00 minutes)
    return $minutes === 0;
}

// =====================================================
// 2. IMAGE UPLOAD & VALIDATION
// =====================================================

/**
 * Validate uploaded image file
 * Returns array with 'success' boolean and 'message' string
 */
function validate_image($file, $max_size_mb = 5) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        $message = $errors[$file['error']] ?? 'Unknown upload error';
        return ['success' => false, 'message' => $message];
    }

    // Check file size
    $max_size_bytes = $max_size_mb * 1024 * 1024;
    if ($file['size'] > $max_size_bytes) {
        return ['success' => false, 'message' => "File size exceeds {$max_size_mb}MB limit"];
    }

    // Check file type by extension
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, JPEG, PNG, GIF'];
    }

    // Check MIME type
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_mimes)) {
        return ['success' => false, 'message' => 'Invalid file type detected'];
    }

    // Verify it's actually an image
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['success' => false, 'message' => 'File is not a valid image'];
    }

    return ['success' => true, 'message' => 'Valid image', 'mime' => $mime_type, 'extension' => $file_extension];
}

/**
 * Upload image file with security checks
 * Returns array with 'success' boolean, 'message', and 'file_path' if successful
 */
function upload_room_image($file, $room_id) {
    global $conn;

    // Validate image
    $validation = validate_image($file);
    if (!$validation['success']) {
        return $validation;
    }

    // Generate unique filename
    $extension = $validation['extension'];
    $filename = 'room_' . $room_id . '_' . time() . '.' . $extension;
    $upload_dir = __DIR__ . '/../uploads/rooms/';
    $file_path = $upload_dir . $filename;
    $relative_path = 'uploads/rooms/' . $filename;

    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'message' => 'Failed to save uploaded file'];
    }

    // Set proper permissions
    chmod($file_path, 0644);

    return [
        'success' => true,
        'message' => 'Image uploaded successfully',
        'file_path' => $relative_path,
        'filename' => $filename
    ];
}

/**
 * Upload store product image with security checks
 * Returns array with 'success' boolean, 'message', and 'file_path' if successful
 */
function upload_store_product_image($file, $product_id) {
    // Validate image
    $validation = validate_image($file);
    if (!$validation['success']) {
        return $validation;
    }

    // Generate unique filename
    $extension = $validation['extension'];
    $filename = 'store_product_' . $product_id . '_' . time() . '.' . $extension;
    $upload_dir = __DIR__ . '/../uploads/store_products/';
    $file_path = $upload_dir . $filename;
    $relative_path = 'uploads/store_products/' . $filename;

    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'message' => 'Failed to save uploaded file'];
    }

    // Set proper permissions
    chmod($file_path, 0644);

    return [
        'success' => true,
        'message' => 'Image uploaded successfully',
        'file_path' => $relative_path,
        'filename' => $filename
    ];
}

/**
 * Delete image file from server
 */
function delete_image($image_path) {
    if (empty($image_path)) {
        return true;
    }

    $full_path = __DIR__ . '/../' . $image_path;

    if (file_exists($full_path)) {
        return unlink($full_path);
    }

    return true;
}

// =====================================================
// 3. BOOKING VALIDATION & TIME SLOT CHECKING
// =====================================================

/**
 * Get business hours for a specific day
 */
function get_business_hours($conn, $date) {
    $day_of_week = date('l', strtotime($date)); // Monday, Tuesday, etc.

    $stmt = mysqli_prepare($conn, "SELECT is_open, opening_time, closing_time FROM business_hours WHERE day_of_week = ?");
    mysqli_stmt_bind_param($stmt, "s", $day_of_week);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        return $row;
    }

    mysqli_stmt_close($stmt);

    // Default if not found
    return ['is_open' => true, 'opening_time' => '09:00:00', 'closing_time' => '23:59:00'];
}

/**
 * Check if booking time is within business hours
 */
function is_within_business_hours($conn, $date, $start_time, $hours) {
    $business_hours = get_business_hours($conn, $date);

    if (!$business_hours['is_open']) {
        return false;
    }

    $opening = strtotime($business_hours['opening_time']);
    $closing = strtotime($business_hours['closing_time']);
    $start = strtotime($start_time);
    $end = strtotime($start_time) + ($hours * 3600);

    return ($start >= $opening && $end <= $closing);
}

/**
 * Check if room is available for booking (no time conflicts)
 * Uses prepared statements for security
 */
function check_room_availability($conn, $room_id, $booking_date, $start_time, $hours, $exclude_booking_id = null) {
    // Calculate end time
    $end_time = date('H:i:s', strtotime($start_time) + ($hours * 3600));

    // Query to find overlapping bookings
    $query = "SELECT id, customer_name, start_time, hours
              FROM bookings
              WHERE room_id = ?
              AND booking_date = ?
              AND status IN ('Pending', 'Confirmed')
              AND (
                  (start_time < ? AND ADDTIME(start_time, SEC_TO_TIME(hours * 3600)) > ?) OR
                  (start_time >= ? AND start_time < ?) OR
                  (? >= start_time AND ? < ADDTIME(start_time, SEC_TO_TIME(hours * 3600)))
              )";

    if ($exclude_booking_id !== null) {
        $query .= " AND id != ?";
    }

    $stmt = mysqli_prepare($conn, $query);

    if ($exclude_booking_id !== null) {
        mysqli_stmt_bind_param($stmt, "isssssssi", $room_id, $booking_date, $end_time, $start_time, $start_time, $end_time, $start_time, $start_time, $exclude_booking_id);
    } else {
        mysqli_stmt_bind_param($stmt, "isssssss", $room_id, $booking_date, $end_time, $start_time, $start_time, $end_time, $start_time, $start_time);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $conflicts = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return [
        'available' => count($conflicts) === 0,
        'conflicts' => $conflicts
    ];
}

/**
 * Get available time slots for a room on a specific date
 * Supports room-specific slots or global slots
 */
function get_available_time_slots($conn, $room_id, $booking_date) {
    // Get time slots for this specific room or global slots (room_id IS NULL)
    $query = "SELECT slot_time, slot_label FROM time_slots
              WHERE is_active = 1
              AND (room_id = ? OR room_id IS NULL)
              ORDER BY slot_time";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $all_slots = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    // Get business hours for the date
    $business_hours = get_business_hours($conn, $booking_date);

    $available_slots = [];

    foreach ($all_slots as $slot) {
        $slot_time = $slot['slot_time'];

        // Check if within business hours
        if (!is_within_business_hours($conn, $booking_date, $slot_time, 1)) {
            continue;
        }

        // Check if slot is available (no conflicts)
        $availability = check_room_availability($conn, $room_id, $booking_date, $slot_time, 1);

        if ($availability['available']) {
            $available_slots[] = $slot;
        }
    }

    return $available_slots;
}

// =====================================================
// 4. UTILITY FUNCTIONS
// =====================================================

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get setting value from database
 */
function get_setting($conn, $key, $default = null) {
    $stmt = mysqli_prepare($conn, "SELECT setting_value FROM system_settings WHERE setting_key = ?");
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        return $row['setting_value'];
    }

    mysqli_stmt_close($stmt);
    return $default;
}

/**
 * Format price with currency
 */
function format_price($price) {
    return '$' . number_format($price, 2);
}

/**
 * Format date for display
 */
function format_date($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Format time for display
 */
function format_time($time) {
    return date('g:i A', strtotime($time));
}

/**
 * Get room status badge HTML
 */
function get_status_badge($status) {
    $badges = [
        'Available' => '<span style="color: #28a745;">🟢 Available</span>',
        'Busy' => '<span style="color: #dc3545;">🔴 Busy</span>',
        'Pending' => '<span style="color: #ffc107;">⏳ Pending</span>',
        'Confirmed' => '<span style="color: #28a745;">✅ Confirmed</span>',
        'Cancelled' => '<span style="color: #6c757d;">❌ Cancelled</span>'
    ];

    return $badges[$status] ?? $status;
}

/**
 * Log admin action for audit
 */
function log_admin_action($conn, $admin_id, $action, $table_name, $record_id = null, $old_values = null, $new_values = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = mysqli_prepare($conn, "INSERT INTO audit_log (admin_id, action, table_name, record_id, old_values, new_values, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");

    mysqli_stmt_bind_param($stmt, "issssss", $admin_id, $action, $table_name, $record_id, $old_values, $new_values, $ip_address);

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

?>
