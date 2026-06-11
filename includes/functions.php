<?php
/**
 * Helper Functions for PlayStation PlayRoom
 * Validation, Sanitization, Image Handling, and Utilities
 */

if (!defined('SITE_SCHEMA_BOOTSTRAP_VERSION')) {
    define('SITE_SCHEMA_BOOTSTRAP_VERSION', '2026-06-01-performance-1');
}

if (!defined('SITE_RUNTIME_CACHE_TTL_SHORT')) {
    define('SITE_RUNTIME_CACHE_TTL_SHORT', 15);
}

if (!defined('SITE_RUNTIME_CACHE_TTL_MEDIUM')) {
    define('SITE_RUNTIME_CACHE_TTL_MEDIUM', 30);
}

function runtime_cache_storage_key() {
    return '_site_runtime_cache';
}

function runtime_cache_get($key, &$found = null) {
    if (!isset($GLOBALS['site_runtime_request_cache']) || !is_array($GLOBALS['site_runtime_request_cache'])) {
        $GLOBALS['site_runtime_request_cache'] = [];
    }

    if (array_key_exists($key, $GLOBALS['site_runtime_request_cache'])) {
        $found = true;
        return $GLOBALS['site_runtime_request_cache'][$key];
    }

    $storage_key = runtime_cache_storage_key();
    $session_cache = $_SESSION[$storage_key][$key] ?? null;

    if (is_array($session_cache) && (int)($session_cache['expires_at'] ?? 0) >= time()) {
        $GLOBALS['site_runtime_request_cache'][$key] = $session_cache['value'];
        $found = true;
        return $session_cache['value'];
    }

    $found = false;
    return null;
}

function runtime_cache_set($key, $value, $ttl = SITE_RUNTIME_CACHE_TTL_SHORT) {
    if (!isset($GLOBALS['site_runtime_request_cache']) || !is_array($GLOBALS['site_runtime_request_cache'])) {
        $GLOBALS['site_runtime_request_cache'] = [];
    }

    $GLOBALS['site_runtime_request_cache'][$key] = $value;
    $storage_key = runtime_cache_storage_key();

    if (!isset($_SESSION[$storage_key]) || !is_array($_SESSION[$storage_key])) {
        $_SESSION[$storage_key] = [];
    }

    $_SESSION[$storage_key][$key] = [
        'expires_at' => time() + max(1, (int)$ttl),
        'value' => $value,
    ];

    return $value;
}

function runtime_cache_forget($prefix = null) {
    if (!isset($GLOBALS['site_runtime_request_cache']) || !is_array($GLOBALS['site_runtime_request_cache'])) {
        $GLOBALS['site_runtime_request_cache'] = [];
    }

    $storage_key = runtime_cache_storage_key();

    if ($prefix === null) {
        $GLOBALS['site_runtime_request_cache'] = [];
        unset($_SESSION[$storage_key]);
        return;
    }

    foreach (array_keys($GLOBALS['site_runtime_request_cache']) as $key) {
        if (strpos($key, $prefix) === 0) {
            unset($GLOBALS['site_runtime_request_cache'][$key]);
        }
    }

    if (!empty($_SESSION[$storage_key]) && is_array($_SESSION[$storage_key])) {
        foreach (array_keys($_SESSION[$storage_key]) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($_SESSION[$storage_key][$key]);
            }
        }
    }
}

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

function normalize_status_key($status) {
    return strtolower(str_replace([' ', '-'], '_', trim((string)$status)));
}

function normalize_status_class($status) {
    $status_key = normalize_status_key($status);

    if ($status_key === 'pending_payment') {
        return 'pending';
    }

    return str_replace('_', '-', $status_key);
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
 * Upload site user profile image with security checks
 * Returns array with 'success' boolean, 'message', and 'file_path' if successful
 */
function upload_site_user_profile_image($file, $user_id) {
    $validation = validate_image($file, 4);
    if (!$validation['success']) {
        return $validation;
    }

    $extension = $validation['extension'];
    $filename = 'site_user_' . $user_id . '_' . time() . '.' . $extension;
    $upload_dir = __DIR__ . '/../uploads/site_users/';
    $file_path = $upload_dir . $filename;
    $relative_path = 'uploads/site_users/' . $filename;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'message' => 'Failed to save uploaded file'];
    }

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

/**
 * Ensure customer booking confirmation columns exist.
 */
function ensure_booking_confirmation_schema($conn) {
    $columns = [];
    $result = mysqli_query($conn, "SHOW COLUMNS FROM bookings");

    if ($result) {
        while ($column = mysqli_fetch_assoc($result)) {
            $columns[$column['Field']] = true;
        }
    }

    if (!isset($columns['booking_code'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN booking_code VARCHAR(40) NULL UNIQUE AFTER id");
    }

    if (!isset($columns['customer_session_token'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN customer_session_token VARCHAR(64) NULL AFTER phone");
        mysqli_query($conn, "ALTER TABLE bookings ADD INDEX idx_customer_session_token (customer_session_token)");
    }

    if (!isset($columns['user_id'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN user_id INT NULL AFTER customer_session_token");
        mysqli_query($conn, "ALTER TABLE bookings ADD INDEX idx_booking_user_id (user_id)");
    }

    if (!isset($columns['additional_items_total'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN additional_items_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_price");
    }

    if (!isset($columns['final_total'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN final_total DECIMAL(10,2) GENERATED ALWAYS AS (total_price + IFNULL(additional_items_total, 0)) STORED AFTER additional_items_total");
    }

    if (!isset($columns['loyalty_points_earned'])) {
        $final_total_check = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'final_total'");
        $after_column = ($final_total_check && mysqli_num_rows($final_total_check) > 0) ? 'final_total' : 'additional_items_total';
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN loyalty_points_earned INT NOT NULL DEFAULT 0 AFTER " . $after_column);
    }

    if (!isset($columns['loyalty_points_redeemed'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN loyalty_points_redeemed INT NOT NULL DEFAULT 0 AFTER loyalty_points_earned");
    }

    if (!isset($columns['loyalty_discount'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN loyalty_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER loyalty_points_redeemed");
    }

    if (!isset($columns['payment_status'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN payment_status VARCHAR(20) DEFAULT 'Unpaid' AFTER status");
    }

    if (!isset($columns['payment_method'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN payment_method VARCHAR(20) DEFAULT NULL AFTER payment_status");
    }

    if (!isset($columns['paid_amount'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN paid_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_method");
    }

    if (!isset($columns['notes'])) {
        mysqli_query($conn, "ALTER TABLE bookings ADD COLUMN notes TEXT NULL AFTER paid_amount");
    }
}

/**
 * Ensure booking add-on order rows exist.
 */
function ensure_booking_items_table($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS booking_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        booking_id INT NOT NULL,
        menu_item_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        item_price DECIMAL(10,2) NOT NULL,
        item_total DECIMAL(10,2) GENERATED ALWAYS AS (quantity * item_price) STORED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_booking_items_booking (booking_id),
        INDEX idx_booking_items_menu_item (menu_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Ensure public store inventory exists.
 */
function ensure_store_products_schema($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS store_products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        product_name VARCHAR(150) NOT NULL,
        category VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT,
        image_path VARCHAR(255) DEFAULT NULL,
        stock_quantity INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_store_products_status_category (status, category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Ensure store orders and order item rows exist.
 */
function ensure_store_orders_schema($conn) {
    ensure_store_products_schema($conn);

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS store_orders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        order_code VARCHAR(40) NOT NULL UNIQUE,
        user_id INT NOT NULL,
        customer_name VARCHAR(120) NOT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        loyalty_points_redeemed INT NOT NULL DEFAULT 0,
        loyalty_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        loyalty_points_earned INT NOT NULL DEFAULT 0,
        payment_status VARCHAR(20) NOT NULL DEFAULT 'Unpaid',
        payment_method VARCHAR(20) DEFAULT NULL,
        paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        stock_deducted TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_store_orders_user_created (user_id, created_at),
        INDEX idx_store_orders_status_payment (status, payment_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS store_order_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        order_id INT NOT NULL,
        product_id INT NULL,
        product_name VARCHAR(150) NOT NULL,
        category VARCHAR(100) DEFAULT NULL,
        quantity INT NOT NULL DEFAULT 1,
        item_price DECIMAL(10,2) NOT NULL,
        item_total DECIMAL(10,2) GENERATED ALWAYS AS (quantity * item_price) STORED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_store_order_items_order (order_id),
        INDEX idx_store_order_items_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $order_columns = [];
    $order_columns_result = mysqli_query($conn, "SHOW COLUMNS FROM store_orders");
    if ($order_columns_result) {
        while ($column = mysqli_fetch_assoc($order_columns_result)) {
            $order_columns[$column['Field']] = true;
        }
    }

    if (!isset($order_columns['loyalty_points_redeemed'])) {
        mysqli_query($conn, "ALTER TABLE store_orders ADD COLUMN loyalty_points_redeemed INT NOT NULL DEFAULT 0 AFTER subtotal");
    }

    if (!isset($order_columns['loyalty_discount'])) {
        mysqli_query($conn, "ALTER TABLE store_orders ADD COLUMN loyalty_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER loyalty_points_redeemed");
    }

    if (!isset($order_columns['loyalty_points_earned'])) {
        mysqli_query($conn, "ALTER TABLE store_orders ADD COLUMN loyalty_points_earned INT NOT NULL DEFAULT 0 AFTER total_amount");
    }

    if (!isset($order_columns['stock_deducted'])) {
        mysqli_query($conn, "ALTER TABLE store_orders ADD COLUMN stock_deducted TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    }
}

/**
 * Ensure support/complaint records can be linked back to registered customers.
 */
function ensure_complaints_schema($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS complaints (
        id INT PRIMARY KEY AUTO_INCREMENT,
        complaint_code VARCHAR(40) NULL UNIQUE,
        user_id INT NULL,
        customer_session_token VARCHAR(64) NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_email VARCHAR(150) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Open',
        admin_reply TEXT NULL,
        replied_by_admin_id INT NULL,
        replied_at TIMESTAMP NULL DEFAULT NULL,
        closed_for_customer_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_complaints_code (complaint_code),
        INDEX idx_complaints_session_created (customer_session_token, created_at),
        INDEX idx_complaints_user_created (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = [];
    $result = mysqli_query($conn, "SHOW COLUMNS FROM complaints");

    if ($result) {
        while ($column = mysqli_fetch_assoc($result)) {
            $columns[$column['Field']] = true;
        }
    }

    if (!isset($columns['user_id'])) {
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN user_id INT NULL AFTER id");
        mysqli_query($conn, "ALTER TABLE complaints ADD INDEX idx_complaints_user_created (user_id, created_at)");
    }

    if (!isset($columns['complaint_code'])) {
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN complaint_code VARCHAR(40) NULL UNIQUE AFTER id");
        mysqli_query($conn, "ALTER TABLE complaints ADD INDEX idx_complaints_code (complaint_code)");
    }

    if (!isset($columns['customer_session_token'])) {
        $after_column = isset($columns['user_id']) ? 'user_id' : 'id';
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN customer_session_token VARCHAR(64) NULL AFTER " . $after_column);
        mysqli_query($conn, "ALTER TABLE complaints ADD INDEX idx_complaints_session_created (customer_session_token, created_at)");
    }

    if (!isset($columns['customer_email'])) {
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN customer_email VARCHAR(150) DEFAULT NULL AFTER customer_name");
    }

    if (!isset($columns['status'])) {
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Open' AFTER message");
    }

    if (!isset($columns['admin_reply'])) {
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN admin_reply TEXT NULL AFTER status");
    }

    if (!isset($columns['replied_by_admin_id'])) {
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN replied_by_admin_id INT NULL AFTER admin_reply");
    }

    if (!isset($columns['replied_at'])) {
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN replied_at TIMESTAMP NULL DEFAULT NULL AFTER replied_by_admin_id");
    }

    if (!isset($columns['closed_for_customer_at'])) {
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN closed_for_customer_at TIMESTAMP NULL DEFAULT NULL AFTER replied_at");
    }

    if (!isset($columns['updated_at'])) {
        mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }

    ensure_complaint_messages_schema($conn);
}

function ensure_complaint_messages_schema($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS complaint_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        complaint_id INT NOT NULL,
        sender_type VARCHAR(20) NOT NULL,
        sender_user_id INT NULL,
        sender_admin_id INT NULL,
        message_text TEXT NOT NULL,
        emailed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_complaint_messages_complaint_created (complaint_id, created_at),
        INDEX idx_complaint_messages_sender (sender_type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function add_complaint_message($conn, $complaint_id, $sender_type, $message_text, $sender_user_id = null, $sender_admin_id = null) {
    ensure_complaint_messages_schema($conn);
    $complaint_id = (int)$complaint_id;
    $message_text = trim((string)$message_text);

    if ($complaint_id <= 0 || $message_text === '') {
        return false;
    }

    $sender_user_id = $sender_user_id !== null ? (int)$sender_user_id : null;
    $sender_admin_id = $sender_admin_id !== null ? (int)$sender_admin_id : null;
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO complaint_messages (complaint_id, sender_type, sender_user_id, sender_admin_id, message_text)
         VALUES (?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "isiis", $complaint_id, $sender_type, $sender_user_id, $sender_admin_id, $message_text);
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $saved;
}

function get_complaint_messages($conn, $complaint_id) {
    ensure_complaint_messages_schema($conn);
    $complaint_id = (int)$complaint_id;
    $messages = [];

    if ($complaint_id <= 0) {
        return $messages;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT cm.*, su.full_name AS site_user_name, a.full_name AS admin_name
         FROM complaint_messages cm
         LEFT JOIN site_users su ON cm.sender_user_id = su.id
         LEFT JOIN admins a ON cm.sender_admin_id = a.id
         WHERE cm.complaint_id = ?
         ORDER BY cm.created_at ASC, cm.id ASC"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $complaint_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
        }
        mysqli_stmt_close($stmt);
    }

    return $messages;
}

function close_stale_customer_support_threads($conn) {
    ensure_complaints_schema($conn);

    mysqli_query(
        $conn,
        "UPDATE complaints c
         JOIN (
            SELECT cm.complaint_id, cm.sender_type, cm.created_at
            FROM complaint_messages cm
            JOIN (
                SELECT complaint_id, MAX(id) AS last_message_id
                FROM complaint_messages
                GROUP BY complaint_id
            ) last_message ON last_message.last_message_id = cm.id
         ) latest ON latest.complaint_id = c.id
         SET c.status = 'Closed',
             c.closed_for_customer_at = NOW()
         WHERE c.closed_for_customer_at IS NULL
           AND latest.sender_type = 'admin'
           AND latest.created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );
}

/**
 * Ensure customer accounts and loyalty storage exist.
 */
function ensure_core_project_schema($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admins (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'employee',
        phone VARCHAR(20) DEFAULT NULL,
        email VARCHAR(100) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rooms (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_name VARCHAR(100) NOT NULL,
        room_type VARCHAR(50) NOT NULL,
        price_per_hour DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Available',
        services TEXT,
        description TEXT,
        image_path VARCHAR(255) DEFAULT NULL,
        image_uploaded_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS menu_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        item_name VARCHAR(100) NOT NULL,
        item_category VARCHAR(50) NOT NULL,
        item_price DECIMAL(10,2) NOT NULL,
        item_description TEXT,
        is_available BOOLEAN NOT NULL DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bookings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        booking_code VARCHAR(40) NULL UNIQUE,
        customer_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        customer_session_token VARCHAR(64) NULL,
        user_id INT NULL,
        room_id INT NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        hours INT NOT NULL,
        end_time TIME GENERATED ALWAYS AS (ADDTIME(start_time, SEC_TO_TIME(hours * 3600))) STORED,
        total_price DECIMAL(10,2) NOT NULL,
        additional_items_total DECIMAL(10,2) DEFAULT 0.00,
        final_total DECIMAL(10,2) GENERATED ALWAYS AS (total_price + IFNULL(additional_items_total, 0)) STORED,
        loyalty_points_earned INT NOT NULL DEFAULT 0,
        loyalty_points_redeemed INT NOT NULL DEFAULT 0,
        loyalty_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        payment_status VARCHAR(20) DEFAULT 'Unpaid',
        payment_method VARCHAR(20) DEFAULT NULL,
        paid_amount DECIMAL(10,2) DEFAULT 0.00,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_customer_session_token (customer_session_token),
        INDEX idx_booking_user_id (user_id),
        INDEX idx_booking_datetime (room_id, booking_date, start_time, end_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS business_hours (
        id INT PRIMARY KEY AUTO_INCREMENT,
        day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL UNIQUE,
        is_open BOOLEAN NOT NULL DEFAULT TRUE,
        opening_time TIME NOT NULL DEFAULT '09:00:00',
        closing_time TIME NOT NULL DEFAULT '23:59:00',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS time_slots (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_id INT DEFAULT NULL,
        slot_time TIME NOT NULL,
        slot_label VARCHAR(20) NOT NULL,
        is_active BOOLEAN NOT NULL DEFAULT TRUE,
        UNIQUE KEY unique_room_slot (room_id, slot_time),
        INDEX idx_time_slots_active_time (is_active, slot_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS audit_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        admin_id INT NULL,
        action VARCHAR(50) NOT NULL,
        table_name VARCHAR(50) NOT NULL,
        record_id INT NULL,
        old_values TEXT,
        new_values TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_admin_created (admin_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function seed_project_default_data($conn) {
    if (database_count_rows($conn, "SELECT COUNT(*) FROM admins") === 0) {
        mysqli_query($conn, "INSERT INTO admins (username, password, full_name, role, phone, email, status) VALUES
            ('admin', 'admin123', 'System Administrator', 'admin', '0791234567', 'admin@famousgaming.jo', 'Active'),
            ('employee1', 'emp123', 'Ahmed Al-Khatib', 'employee', '0799876543', 'ahmed@famousgaming.jo', 'Active'),
            ('employee2', 'emp123', 'Khaled Al-Majali', 'employee', '0797654321', 'khaled@famousgaming.jo', 'Active'),
            ('employee3', 'emp123', 'Fahad Al-Tarawneh', 'employee', '0793334444', 'fahad@famousgaming.jo', 'Inactive')");
    }

    if (database_count_rows($conn, "SELECT COUNT(*) FROM rooms") === 0) {
        mysqli_query($conn, "INSERT INTO rooms (room_name, room_type, price_per_hour, status, services, description) VALUES
            ('Room 1', 'PS5', 3.00, 'Available', '4K Screen, High-speed Internet, Free Drinks, Air Conditioning', 'Modern PS5 room with 4K display'),
            ('Room 2', 'PS5', 3.00, 'Available', '4K Screen, High-speed Internet, Free Drinks, Air Conditioning', 'PS5 gaming room with fast internet'),
            ('Room 3', 'PS4', 2.00, 'Available', 'HD Screen, Internet, Drinks, Air Conditioning', 'Classic PS4 gaming room'),
            ('Room 4', 'PS4', 2.00, 'Busy', 'HD Screen, Internet, Drinks, Air Conditioning', 'Comfortable PS4 room for families'),
            ('VIP Room', 'PS5', 3.00, 'Available', 'Large 4K Screen, Ultra-fast Internet, VIP Service, AC, Comfortable Chairs', 'Luxury VIP room with all amenities'),
            ('Room 5', 'PS5', 3.00, 'Available', '4K Screen, Professional Headset, High-speed Internet, Drinks', 'PS5 room with professional audio')");
    }

    if (database_count_rows($conn, "SELECT COUNT(*) FROM menu_items") === 0) {
        mysqli_query($conn, "INSERT INTO menu_items (item_name, item_category, item_price, item_description, is_available) VALUES
            ('Coca Cola', 'Drinks', 0.50, 'Cold soft drink 330ml', TRUE),
            ('Pepsi', 'Drinks', 0.50, 'Cold soft drink 330ml', TRUE),
            ('Red Bull', 'Drinks', 1.00, 'Energy drink 250ml', TRUE),
            ('Water Bottle', 'Drinks', 0.35, 'Mineral water 500ml', TRUE),
            ('Fresh Orange Juice', 'Drinks', 1.50, 'Freshly squeezed orange juice', TRUE),
            ('Coffee', 'Drinks', 1.00, 'Hot coffee', TRUE),
            ('Chips', 'Snacks', 0.50, 'Potato chips', TRUE),
            ('Chocolate Bar', 'Snacks', 0.50, 'Assorted chocolate bars', TRUE),
            ('Popcorn', 'Snacks', 1.00, 'Fresh popcorn', TRUE),
            ('Sandwich', 'Snacks', 2.50, 'Chicken or cheese sandwich', TRUE),
            ('Pizza Slice', 'Snacks', 2.00, 'Large pizza slice', TRUE),
            ('Extra Controller', 'Services', 1.00, 'Additional PS controller for multiplayer', TRUE),
            ('VR Headset', 'Services', 4.00, 'PlayStation VR headset rental', TRUE),
            ('Gaming Headset', 'Services', 1.00, 'Professional gaming headset', TRUE),
            ('Extended Time', 'Services', 3.00, 'Extra 1 hour gaming time', TRUE)");
    }

    if (database_count_rows($conn, "SELECT COUNT(*) FROM store_products") === 0) {
        mysqli_query($conn, "INSERT INTO store_products (product_name, category, price, description, image_path, stock_quantity, status) VALUES
            ('PlayStation 5 Slim Console', 'PlayStation Consoles', 289.00, 'Current generation console bundle for premium home gaming.', 'images/store/ps5-slim-console.svg', 4, 'Active'),
            ('PlayStation 4 Console', 'PlayStation Consoles', 159.00, 'Reliable PS4 system for lounge setups, tournaments, and home entertainment.', 'images/store/ps4-console.svg', 6, 'Active'),
            ('DualSense Wireless Controller', 'Controllers', 55.00, 'Official PS5 wireless controller with adaptive triggers and premium grip.', 'images/store/dualsense-controller.svg', 12, 'Active'),
            ('DualShock 4 Controller', 'Controllers', 39.00, 'Classic PS4 controller with responsive analog sticks and solid battery life.', 'images/store/dualshock-controller.svg', 9, 'Active'),
            ('EA Sports FC 25', 'Games / CDs', 27.00, 'Popular football title for competitive PlayStation sessions.', 'images/store/fc25-game.svg', 7, 'Active'),
            ('Marvel''s Spider-Man 2', 'Games / CDs', 34.00, 'Story-driven PS5 action title and one of the strongest showcase games for the console.', 'images/store/spiderman2-game.svg', 5, 'Active'),
            ('Silicone Controller Cover - Crimson', 'Controller Covers', 7.50, 'Protective anti-slip cover with a premium red finish for everyday gaming use.', 'images/store/silicone-cover-red.svg', 20, 'Active'),
            ('Silicone Controller Cover - Midnight', 'Controller Covers', 7.50, 'Soft-touch black controller skin with improved grip and scratch protection.', 'images/store/silicone-cover-black.svg', 15, 'Active'),
            ('Pulse 3D Wireless Headset', 'PlayStation Accessories', 69.00, 'Immersive headset tuned for PlayStation audio with a clean modern profile.', 'images/store/pulse-headset.svg', 5, 'Active'),
            ('Dual Controller Charging Dock', 'PlayStation Accessories', 24.00, 'Compact dock that charges two PlayStation controllers at the same time.', 'images/store.jpg', 8, 'Active')");
    }

    if (database_count_rows($conn, "SELECT COUNT(*) FROM business_hours") === 0) {
        mysqli_query($conn, "INSERT INTO business_hours (day_of_week, is_open, opening_time, closing_time) VALUES
            ('Monday', TRUE, '09:00:00', '23:59:00'),
            ('Tuesday', TRUE, '09:00:00', '23:59:00'),
            ('Wednesday', TRUE, '09:00:00', '23:59:00'),
            ('Thursday', TRUE, '09:00:00', '23:59:00'),
            ('Friday', TRUE, '09:00:00', '23:59:00'),
            ('Saturday', TRUE, '09:00:00', '23:59:00'),
            ('Sunday', TRUE, '09:00:00', '23:59:00')");
    }

    if (database_count_rows($conn, "SELECT COUNT(*) FROM time_slots") === 0) {
        mysqli_query($conn, "INSERT INTO time_slots (room_id, slot_time, slot_label) VALUES
            (NULL, '09:00:00', '9:00 AM'),
            (NULL, '10:00:00', '10:00 AM'),
            (NULL, '11:00:00', '11:00 AM'),
            (NULL, '12:00:00', '12:00 PM'),
            (NULL, '13:00:00', '1:00 PM'),
            (NULL, '14:00:00', '2:00 PM'),
            (NULL, '15:00:00', '3:00 PM'),
            (NULL, '16:00:00', '4:00 PM'),
            (NULL, '17:00:00', '5:00 PM'),
            (NULL, '18:00:00', '6:00 PM'),
            (NULL, '19:00:00', '7:00 PM'),
            (NULL, '20:00:00', '8:00 PM'),
            (NULL, '21:00:00', '9:00 PM'),
            (NULL, '22:00:00', '10:00 PM'),
            (NULL, '23:00:00', '11:00 PM')");
    }

    if (database_count_rows($conn, "SELECT COUNT(*) FROM bookings") === 0 && database_count_rows($conn, "SELECT COUNT(*) FROM rooms") > 0) {
        mysqli_query($conn, "INSERT INTO bookings (customer_name, phone, room_id, booking_date, start_time, hours, total_price, status, payment_status, payment_method, notes) VALUES
            ('Ahmed Al-Nsour', '0791234567', 1, '2026-05-30', '14:00:00', 2, 6.00, 'Confirmed', 'Paid', 'Cash', 'FIFA tournament booking'),
            ('Khaled Al-Zoubi', '0797654321', 2, '2026-05-31', '16:00:00', 3, 9.00, 'Pending', 'Unpaid', NULL, 'Waiting for confirmation'),
            ('Saad Al-Bakhit', '0799876543', 4, '2026-06-01', '10:00:00', 4, 8.00, 'Confirmed', 'Paid', 'CliQ', 'Family booking'),
            ('Mohammed Al-Hmoud', '0795556666', 5, '2026-06-02', '18:00:00', 5, 15.00, 'Confirmed', 'Paid', 'Visa', 'VIP special event'),
            ('Fahad Al-Rawashdeh', '0792223333', 3, '2026-06-03', '12:00:00', 3, 6.00, 'Pending', 'Unpaid', NULL, 'Kids gaming session')");
    }

    if (database_count_rows($conn, "SELECT COUNT(*) FROM complaints") === 0) {
        mysqli_query($conn, "INSERT INTO complaints (customer_name, phone, message) VALUES
            ('Mohammed Al-Adwan', '0791112233', 'Excellent service, hope to see more modern games'),
            ('Fahad Al-Fayez', NULL, 'Suggestion: Add food delivery service from nearby restaurants'),
            ('Nawaf Al-Habashneh', '0793334444', 'Very clean room and fast service, thank you'),
            ('Abdullah Al-Khawaldeh', '0799998888', 'Suggestion: Weekly gaming tournaments'),
            ('Sultan Al-Momani', '0797776666', 'Room 3 air conditioning is a bit weak')");
    }
}

function schema_bootstrap_flag_path() {
    $cache_dir = dirname(__DIR__) . '/uploads/cache';

    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }

    return $cache_dir . '/schema-bootstrap-' . SITE_SCHEMA_BOOTSTRAP_VERSION . '.flag';
}

function mark_schema_bootstrap_ready() {
    $_SESSION['site_schema_bootstrap_version'] = SITE_SCHEMA_BOOTSTRAP_VERSION;
    $_SESSION['site_schema_bootstrap_checked_at'] = time();
    @touch(schema_bootstrap_flag_path());
}

function is_schema_bootstrap_ready($conn) {
    if (($_SESSION['site_schema_bootstrap_version'] ?? '') === SITE_SCHEMA_BOOTSTRAP_VERSION) {
        return true;
    }

    $flag_path = schema_bootstrap_flag_path();
    if (!is_file($flag_path)) {
        return false;
    }

    $core_tables = ['site_users', 'admins', 'rooms', 'bookings'];
    foreach ($core_tables as $core_table) {
        if (!database_table_exists($conn, $core_table)) {
            return false;
        }
    }

    mark_schema_bootstrap_ready();
    return true;
}

function ensure_user_auth_schema($conn) {
    static $request_bootstrapped = false;

    if ($request_bootstrapped) {
        return;
    }

    $request_bootstrapped = true;

    if (is_schema_bootstrap_ready($conn)) {
        return;
    }

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS site_users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(20) DEFAULT NULL,
        profile_image VARCHAR(255) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        loyalty_points INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_site_users_role_status (role, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $profile_image_column = mysqli_query($conn, "SHOW COLUMNS FROM site_users LIKE 'profile_image'");
    if ($profile_image_column && mysqli_num_rows($profile_image_column) === 0) {
        mysqli_query($conn, "ALTER TABLE site_users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER phone");
    }

    ensure_site_user_password_reset_schema($conn);

    ensure_core_project_schema($conn);
    ensure_loyalty_settings_schema($conn);
    ensure_booking_confirmation_schema($conn);
    ensure_booking_items_table($conn);
    ensure_store_products_schema($conn);
    ensure_store_orders_schema($conn);
    ensure_complaints_schema($conn);
    ensure_site_notifications_table($conn);
    ensure_database_relationships($conn);
    ensure_reporting_views($conn);
    seed_project_default_data($conn);
    mark_schema_bootstrap_ready();
}

/**
 * Store loyalty rules in the database so points stay connected to users and admin data.
 */
function ensure_loyalty_settings_schema($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        setting_type VARCHAR(20) NOT NULL DEFAULT 'string',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    mysqli_query($conn, "INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
        ('loyalty_points_per_jod', '1', 'decimal', 'Loyalty points earned for each paid JOD'),
        ('loyalty_points_per_jod_discount', '10', 'decimal', 'Loyalty points needed for 1 JOD discount')");
}

function database_table_exists($conn, $table_name) {
    $table_name = mysqli_real_escape_string($conn, $table_name);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '" . $table_name . "'");

    return $result && mysqli_num_rows($result) > 0;
}

function database_constraint_exists($conn, $constraint_name) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT 1
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND CONSTRAINT_NAME = ?
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'
         LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $constraint_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (bool)$exists;
}

function database_foreign_key_link_exists($conn, $table_name, $column_name, $referenced_table_name) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT 1
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
           AND REFERENCED_TABLE_NAME = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "sss", $table_name, $column_name, $referenced_table_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (bool)$exists;
}

function database_count_rows($conn, $query) {
    $result = mysqli_query($conn, $query);
    if (!$result || !($row = mysqli_fetch_row($result))) {
        return 1;
    }

    return (int)$row[0];
}

function ensure_foreign_key_if_clean($conn, $constraint_name, $table_name, $column_name, $referenced_table_name, $required_tables, $orphan_query, $alter_query) {
    if (
        database_constraint_exists($conn, $constraint_name) ||
        database_foreign_key_link_exists($conn, $table_name, $column_name, $referenced_table_name)
    ) {
        return;
    }

    foreach ($required_tables as $table_name) {
        if (!database_table_exists($conn, $table_name)) {
            return;
        }
    }

    if (database_count_rows($conn, $orphan_query) === 0) {
        mysqli_query($conn, $alter_query);
    }
}

function ensure_database_relationships($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    ensure_foreign_key_if_clean(
        $conn,
        'fk_bookings_user',
        'bookings',
        'user_id',
        'site_users',
        ['bookings', 'site_users'],
        "SELECT COUNT(*) FROM bookings b LEFT JOIN site_users su ON su.id = b.user_id WHERE b.user_id IS NOT NULL AND su.id IS NULL",
        "ALTER TABLE bookings ADD CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES site_users(id) ON DELETE SET NULL"
    );

    ensure_foreign_key_if_clean(
        $conn,
        'fk_booking_items_booking',
        'booking_items',
        'booking_id',
        'bookings',
        ['booking_items', 'bookings'],
        "SELECT COUNT(*) FROM booking_items bi LEFT JOIN bookings b ON b.id = bi.booking_id WHERE b.id IS NULL",
        "ALTER TABLE booking_items ADD CONSTRAINT fk_booking_items_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE"
    );

    ensure_foreign_key_if_clean(
        $conn,
        'fk_booking_items_menu_item',
        'booking_items',
        'menu_item_id',
        'menu_items',
        ['booking_items', 'menu_items'],
        "SELECT COUNT(*) FROM booking_items bi LEFT JOIN menu_items mi ON mi.id = bi.menu_item_id WHERE mi.id IS NULL",
        "ALTER TABLE booking_items ADD CONSTRAINT fk_booking_items_menu_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE"
    );

    ensure_foreign_key_if_clean(
        $conn,
        'fk_complaints_user',
        'complaints',
        'user_id',
        'site_users',
        ['complaints', 'site_users'],
        "SELECT COUNT(*) FROM complaints c LEFT JOIN site_users su ON su.id = c.user_id WHERE c.user_id IS NOT NULL AND su.id IS NULL",
        "ALTER TABLE complaints ADD CONSTRAINT fk_complaints_user FOREIGN KEY (user_id) REFERENCES site_users(id) ON DELETE SET NULL"
    );

    ensure_foreign_key_if_clean(
        $conn,
        'fk_site_notifications_user',
        'site_notifications',
        'user_id',
        'site_users',
        ['site_notifications', 'site_users'],
        "SELECT COUNT(*) FROM site_notifications sn LEFT JOIN site_users su ON su.id = sn.user_id WHERE su.id IS NULL",
        "ALTER TABLE site_notifications ADD CONSTRAINT fk_site_notifications_user FOREIGN KEY (user_id) REFERENCES site_users(id) ON DELETE CASCADE"
    );

    ensure_foreign_key_if_clean(
        $conn,
        'fk_store_orders_user',
        'store_orders',
        'user_id',
        'site_users',
        ['store_orders', 'site_users'],
        "SELECT COUNT(*) FROM store_orders so LEFT JOIN site_users su ON su.id = so.user_id WHERE su.id IS NULL",
        "ALTER TABLE store_orders ADD CONSTRAINT fk_store_orders_user FOREIGN KEY (user_id) REFERENCES site_users(id) ON DELETE CASCADE"
    );

    ensure_foreign_key_if_clean(
        $conn,
        'fk_store_order_items_order',
        'store_order_items',
        'order_id',
        'store_orders',
        ['store_order_items', 'store_orders'],
        "SELECT COUNT(*) FROM store_order_items soi LEFT JOIN store_orders so ON so.id = soi.order_id WHERE so.id IS NULL",
        "ALTER TABLE store_order_items ADD CONSTRAINT fk_store_order_items_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE"
    );

    ensure_foreign_key_if_clean(
        $conn,
        'fk_store_order_items_product',
        'store_order_items',
        'product_id',
        'store_products',
        ['store_order_items', 'store_products'],
        "SELECT COUNT(*) FROM store_order_items soi LEFT JOIN store_products sp ON sp.id = soi.product_id WHERE soi.product_id IS NOT NULL AND sp.id IS NULL",
        "ALTER TABLE store_order_items ADD CONSTRAINT fk_store_order_items_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE SET NULL"
    );
}

function database_view_exists($conn, $view_name) {
    $view_name = mysqli_real_escape_string($conn, $view_name);
    $result = mysqli_query($conn, "SHOW FULL TABLES LIKE '" . $view_name . "'");

    if (!$result || !($row = mysqli_fetch_row($result))) {
        return false;
    }

    return isset($row[1]) && strtolower($row[1]) === 'view';
}

function ensure_reporting_views($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!database_view_exists($conn, 'customer_orders_unified')) {
        mysqli_query($conn, "CREATE VIEW customer_orders_unified AS
            SELECT
                'booking' AS record_type,
                b.id AS record_id,
                COALESCE(b.booking_code, CONCAT('FG-', LPAD(b.id, 6, '0'))) AS record_code,
                b.user_id,
                b.customer_name,
                b.phone,
                NULL AS email,
                r.room_name AS item_label,
                b.status,
                b.payment_status,
                b.payment_method,
                b.final_total AS total_amount,
                b.paid_amount,
                b.loyalty_points_earned,
                b.loyalty_points_redeemed,
                b.loyalty_discount,
                b.created_at,
                b.updated_at,
                'user/my_bookings.php' AS action_url
            FROM bookings b
            LEFT JOIN rooms r ON r.id = b.room_id
            UNION ALL
            SELECT
                'store_order' AS record_type,
                so.id AS record_id,
                so.order_code AS record_code,
                so.user_id,
                so.customer_name,
                so.phone,
                so.email,
                'Store order' AS item_label,
                so.status,
                so.payment_status,
                so.payment_method,
                so.total_amount,
                so.paid_amount,
                so.loyalty_points_earned,
                so.loyalty_points_redeemed,
                so.loyalty_discount,
                so.created_at,
                so.updated_at,
                'user/my_bookings.php' AS action_url
            FROM store_orders so");
    }

    if (!database_view_exists($conn, 'notifications_unified')) {
        mysqli_query($conn, "CREATE VIEW notifications_unified AS
            SELECT
                'admin' AS audience,
                NULL AS user_id,
                id AS notification_id,
                notification_type,
                title,
                message,
                related_table,
                related_id,
                action_url,
                is_read,
                read_at,
                created_at
            FROM admin_notifications
            UNION ALL
            SELECT
                'user' AS audience,
                user_id,
                id AS notification_id,
                notification_type,
                title,
                message,
                NULL AS related_table,
                NULL AS related_id,
                action_url,
                is_read,
                read_at,
                created_at
            FROM site_notifications");
    }
}

function get_loyalty_settings($conn = null) {
    if (!$conn) {
        global $conn;
    }

    $settings = [
        'earn_per_jod' => 1.0,
        'redeem_points_per_jod' => 10.0
    ];

    if (!$conn instanceof mysqli) {
        return $settings;
    }

    ensure_loyalty_settings_schema($conn);
    $result = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('loyalty_points_per_jod', 'loyalty_points_per_jod_discount')");

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['setting_key'] === 'loyalty_points_per_jod') {
                $settings['earn_per_jod'] = max(0.0, (float)$row['setting_value']);
            }

            if ($row['setting_key'] === 'loyalty_points_per_jod_discount') {
                $settings['redeem_points_per_jod'] = max(1.0, (float)$row['setting_value']);
            }
        }
    }

    return $settings;
}

/**
 * Get the currently logged in site user, if any.
 */
function clear_current_site_user_cache($user_id = null) {
    if ($user_id !== null && (int)$user_id > 0) {
        runtime_cache_forget('site_user_profile_' . (int)$user_id);
        return;
    }

    runtime_cache_forget('site_user_profile_');
}

function get_current_site_user($conn, $force_refresh = false) {
    ensure_user_auth_schema($conn);

    $user_id = isset($_SESSION['site_user_id']) ? (int)$_SESSION['site_user_id'] : 0;
    if ($user_id <= 0) {
        clear_current_site_user_cache();
        return null;
    }

    $cache_key = 'site_user_profile_' . $user_id;
    if (!$force_refresh) {
        $cached_user = runtime_cache_get($cache_key, $found_cached_user);
        if ($found_cached_user) {
            return $cached_user;
        }
    }

    $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, phone, profile_image, role, loyalty_points, status FROM site_users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user || $user['status'] !== 'Active') {
        clear_current_site_user_cache($user_id);
        unset($_SESSION['site_user_id'], $_SESSION['site_user_name'], $_SESSION['site_user_role']);
        return null;
    }

    $_SESSION['site_user_name'] = $user['full_name'];
    $_SESSION['site_user_loyalty_points'] = (int)$user['loyalty_points'];
    $_SESSION['site_user_avatar'] = $user['profile_image'] ?? '';

    return runtime_cache_set($cache_key, $user, SITE_RUNTIME_CACHE_TTL_MEDIUM);
}

function calculate_loyalty_points($amount, $conn = null) {
    $amount = (float)$amount;
    $settings = get_loyalty_settings($conn);

    return $amount > 0 ? (int)floor($amount * $settings['earn_per_jod']) : 0;
}

function loyalty_points_to_amount($points, $conn = null) {
    $settings = get_loyalty_settings($conn);

    return round(max(0, (int)$points) / $settings['redeem_points_per_jod'], 2);
}

function loyalty_amount_to_points($amount, $conn = null) {
    $settings = get_loyalty_settings($conn);

    return (int)ceil(max(0, (float)$amount) * $settings['redeem_points_per_jod']);
}

function refresh_site_user_points_session($conn, $user_id) {
    $user_id = (int)$user_id;

    if ($user_id <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare($conn, "SELECT loyalty_points FROM site_users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $points = $row ? (int)$row['loyalty_points'] : 0;

    if ((int)($_SESSION['site_user_id'] ?? 0) === $user_id) {
        $_SESSION['site_user_loyalty_points'] = $points;
    }

    return $points;
}

function add_loyalty_points($conn, $user_id, $points) {
    $user_id = (int)$user_id;
    $points = max(0, (int)$points);

    if ($user_id <= 0 || $points <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare($conn, "UPDATE site_users SET loyalty_points = loyalty_points + ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $points, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    refresh_site_user_points_session($conn, $user_id);

    return $points;
}

function redeem_loyalty_points($conn, $user_id, $requested_points, $max_discount) {
    $user_id = (int)$user_id;
    $requested_points = max(0, (int)$requested_points);
    $max_discount = max(0, (float)$max_discount);

    if ($user_id <= 0 || $requested_points <= 0 || $max_discount <= 0) {
        return ['points' => 0, 'discount' => 0.0];
    }

    $available_points = refresh_site_user_points_session($conn, $user_id);
    $max_points_for_discount = loyalty_amount_to_points($max_discount, $conn);
    $points_to_redeem = min($requested_points, $available_points, $max_points_for_discount);
    $discount = min($max_discount, loyalty_points_to_amount($points_to_redeem, $conn));

    if ($points_to_redeem <= 0 || $discount <= 0) {
        return ['points' => 0, 'discount' => 0.0];
    }

    $stmt = mysqli_prepare($conn, "UPDATE site_users SET loyalty_points = loyalty_points - ? WHERE id = ? AND loyalty_points >= ?");
    mysqli_stmt_bind_param($stmt, "iii", $points_to_redeem, $user_id, $points_to_redeem);
    mysqli_stmt_execute($stmt);
    $updated = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($updated <= 0) {
        return ['points' => 0, 'discount' => 0.0];
    }

    refresh_site_user_points_session($conn, $user_id);

    return ['points' => $points_to_redeem, 'discount' => $discount];
}

function generate_store_order_code() {
    return 'FGS-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function get_store_order_items($conn, $order_id) {
    ensure_store_orders_schema($conn);
    $order_id = (int)$order_id;

    if ($order_id <= 0) {
        return [];
    }

    $stmt = mysqli_prepare($conn, "SELECT * FROM store_order_items WHERE order_id = ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);

    return $items;
}

function get_user_store_orders($conn, $user_id, $limit = 80) {
    ensure_store_orders_schema($conn);
    $user_id = (int)$user_id;
    $limit = max(1, min(160, (int)$limit));
    $orders = [];

    if ($user_id <= 0) {
        return $orders;
    }

    $stmt = mysqli_prepare($conn, "SELECT * FROM store_orders WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT " . $limit);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $orders = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    mysqli_stmt_close($stmt);

    return $orders;
}

function award_loyalty_points($conn, $user_id, $booking_id, $amount) {
    $user_id = (int)$user_id;
    $booking_id = (int)$booking_id;

    if ($user_id <= 0 || $booking_id <= 0) {
        return 0;
    }

    $points = calculate_loyalty_points($amount, $conn);

    add_loyalty_points($conn, $user_id, $points);

    $stmt = mysqli_prepare($conn, "UPDATE bookings SET loyalty_points_earned = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $points, $booking_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $points;
}

function award_store_order_loyalty_points($conn, $user_id, $order_id, $amount) {
    $user_id = (int)$user_id;
    $order_id = (int)$order_id;

    if ($user_id <= 0 || $order_id <= 0) {
        return 0;
    }

    $points = calculate_loyalty_points($amount, $conn);
    if ($points <= 0) {
        return 0;
    }

    add_loyalty_points($conn, $user_id, $points);

    $stmt = mysqli_prepare($conn, "UPDATE store_orders SET loyalty_points_earned = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $points, $order_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $points;
}

function award_booking_loyalty_points_if_needed($conn, $booking_id) {
    $booking_id = (int)$booking_id;

    if ($booking_id <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id, total_price, additional_items_total, final_total, loyalty_points_earned
         FROM bookings
         WHERE id = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$booking || (int)($booking['user_id'] ?? 0) <= 0 || (int)($booking['loyalty_points_earned'] ?? 0) > 0) {
        return 0;
    }

    $amount = isset($booking['final_total'])
        ? (float)$booking['final_total']
        : ((float)$booking['total_price'] + (float)$booking['additional_items_total']);
    $points = calculate_loyalty_points($amount, $conn);

    if ($points <= 0) {
        return 0;
    }

    $update_stmt = mysqli_prepare(
        $conn,
        "UPDATE bookings
         SET loyalty_points_earned = ?
         WHERE id = ? AND user_id = ? AND loyalty_points_earned = 0"
    );
    $user_id = (int)$booking['user_id'];
    mysqli_stmt_bind_param($update_stmt, "iii", $points, $booking_id, $user_id);
    mysqli_stmt_execute($update_stmt);
    $updated = mysqli_stmt_affected_rows($update_stmt);
    mysqli_stmt_close($update_stmt);

    if ($updated <= 0) {
        return 0;
    }

    add_loyalty_points($conn, $user_id, $points);

    return $points;
}

function award_store_order_loyalty_points_if_needed($conn, $order_id) {
    $order_id = (int)$order_id;

    if ($order_id <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id, total_amount, loyalty_points_earned
         FROM store_orders
         WHERE id = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$order || (int)($order['user_id'] ?? 0) <= 0 || (int)($order['loyalty_points_earned'] ?? 0) > 0) {
        return 0;
    }

    $points = calculate_loyalty_points((float)$order['total_amount'], $conn);

    if ($points <= 0) {
        return 0;
    }

    $update_stmt = mysqli_prepare(
        $conn,
        "UPDATE store_orders
         SET loyalty_points_earned = ?
         WHERE id = ? AND user_id = ? AND loyalty_points_earned = 0"
    );
    $user_id = (int)$order['user_id'];
    mysqli_stmt_bind_param($update_stmt, "iii", $points, $order_id, $user_id);
    mysqli_stmt_execute($update_stmt);
    $updated = mysqli_stmt_affected_rows($update_stmt);
    mysqli_stmt_close($update_stmt);

    if ($updated <= 0) {
        return 0;
    }

    add_loyalty_points($conn, $user_id, $points);

    return $points;
}

function get_current_business_status($conn) {
    $today = date('Y-m-d');
    $hours = get_business_hours($conn, $today);
    $now = strtotime(date('H:i:s'));
    $opening = strtotime($hours['opening_time']);
    $closing = strtotime($hours['closing_time']);

    if (empty($hours['is_open'])) {
        return [
            'state' => 'closed',
            'label' => t('status_closed_now'),
            'hint' => t('status_closed_hint')
        ];
    }

    if ($now >= $opening && $now <= $closing) {
        return [
            'state' => 'open',
            'label' => t('status_open_now'),
            'hint' => t('status_open_until', ['time' => format_time($hours['closing_time'])])
        ];
    }

    return [
        'state' => 'closed',
        'label' => t('status_closed_now'),
        'hint' => t('status_opens_at', ['time' => format_time($hours['opening_time'])])
    ];
}

function is_valid_luhn_number($number) {
    $digits = preg_replace('/\D+/', '', (string)$number);
    if ($digits === '' || strlen($digits) < 13 || strlen($digits) > 19) {
        return false;
    }

    $sum = 0;
    $alternate = false;

    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $n = (int)$digits[$i];
        if ($alternate) {
            $n *= 2;
            if ($n > 9) {
                $n -= 9;
            }
        }

        $sum += $n;
        $alternate = !$alternate;
    }

    return $sum % 10 === 0;
}

function is_valid_future_expiry($expiry_date) {
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', (string)$expiry_date, $matches)) {
        return false;
    }

    $month = (int)$matches[1];
    $year = 2000 + (int)$matches[2];
    $expires_at = strtotime(sprintf('%04d-%02d-01 +1 month -1 day 23:59:59', $year, $month));

    return $expires_at !== false && $expires_at >= time();
}

function safe_local_redirect($target, $fallback = 'index.php') {
    $target = trim((string)$target);

    if ($target === '' || preg_match('/^https?:\/\//i', $target) || str_contains($target, '..')) {
        return $fallback;
    }

    if (!preg_match('/^[a-zA-Z0-9_\/.\-]+(\?[a-zA-Z0-9_=&%.\-]+)?(#[-a-zA-Z0-9_]+)?$/', $target)) {
        return $fallback;
    }

    return $target;
}

function site_asset_is_external($path) {
    return is_string($path) && preg_match('/^https?:\/\//i', trim($path));
}

function site_asset_exists($path) {
    $path = trim((string)$path);

    if ($path === '') {
        return false;
    }

    if (site_asset_is_external($path)) {
        return true;
    }

    if (str_contains($path, '..')) {
        return false;
    }

    $root = defined('SITE_ROOT_PATH') ? SITE_ROOT_PATH : dirname(__DIR__);

    return file_exists($root . '/' . ltrim($path, '/'));
}

function site_asset_url($path, $fallback = '') {
    $path = trim((string)$path);
    $target = site_asset_exists($path) ? $path : trim((string)$fallback);

    if ($target === '') {
        return '';
    }

    if (site_asset_is_external($target)) {
        return $target;
    }

    return function_exists('site_url') ? site_url($target) : $target;
}

function site_host_is_loopback($host) {
    $host = strtolower(trim((string)$host));
    $host = preg_replace('/^\[(.*)\]$/', '$1', $host);

    return $host === ''
        || $host === 'localhost'
        || $host === '::1'
        || preg_match('/^127\./', $host);
}

function site_normalize_request_host($host) {
    $host = trim((string)$host);

    if (strpos($host, ',') !== false) {
        $host = trim(explode(',', $host)[0]);
    }

    $host = preg_replace('/^https?:\/\//i', '', $host);
    $host = preg_replace('/\/.*$/', '', $host);

    return $host !== '' ? $host : 'localhost';
}

function site_host_without_port($host) {
    $host = site_normalize_request_host($host);

    if (preg_match('/^\[(.*)\](?::\d+)?$/', $host, $matches)) {
        return $matches[1];
    }

    if (substr_count($host, ':') > 1) {
        return $host;
    }

    return preg_replace('/:\d+$/', '', $host);
}

function site_host_port($host, $scheme) {
    $host = site_normalize_request_host($host);

    if (preg_match('/^\[.*\]:(\d+)$/', $host, $matches) || preg_match('/^[^:]+:(\d+)$/', $host, $matches)) {
        $port_number = (int)$matches[1];
        if (($scheme === 'http' && $port_number !== 80) || ($scheme === 'https' && $port_number !== 443)) {
            return ':' . $port_number;
        }
    }

    return '';
}

function site_detect_lan_ipv4() {
    $candidates = [];

    foreach (['SERVER_ADDR', 'LOCAL_ADDR'] as $server_key) {
        if (!empty($_SERVER[$server_key])) {
            $candidates[] = (string)$_SERVER[$server_key];
        }
    }

    $hostname = gethostname();
    if ($hostname) {
        $host_addresses = gethostbynamel($hostname);
        if (is_array($host_addresses)) {
            $candidates = array_merge($candidates, $host_addresses);
        }

        $candidates[] = gethostbyname($hostname);
    }

    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if (!site_host_is_loopback($candidate) && filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $candidate;
        }
    }

    return '';
}

function site_configured_public_url() {
    $configured_url = trim((string)(getenv('FG_PUBLIC_SITE_URL') ?: getenv('FG_SITE_URL') ?: ''));

    if ($configured_url === '') {
        $url_file = (defined('SITE_ROOT_PATH') ? SITE_ROOT_PATH : dirname(__DIR__)) . '/.public-site-url';
        if (is_readable($url_file)) {
            $configured_url = trim((string)file_get_contents($url_file));
        }
    }

    if ($configured_url !== '' && !preg_match('/^https?:\/\//i', $configured_url)) {
        $configured_url = 'http://' . $configured_url;
    }

    return $configured_url !== '' ? rtrim($configured_url, '/') : '';
}

function site_public_base_url() {
    $configured_url = site_configured_public_url();

    if ($configured_url !== '') {
        return $configured_url;
    }

    $is_https = (
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https')
        ||
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );
    $scheme = $is_https ? 'https' : 'http';
    $http_host = site_normalize_request_host($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    $port = site_host_port($http_host, $scheme);
    $host = trim(site_host_without_port($http_host), '[]');

    if (site_host_is_loopback($host)) {
        $lan_host = site_detect_lan_ipv4();
        if ($lan_host !== '') {
            $host = $lan_host;
        }
    }

    if (strpos($host, ':') !== false && $host[0] !== '[') {
        $host = '[' . $host . ']';
    }

    return $scheme . '://' . $host . $port;
}

function site_absolute_url($path = '') {
    $target = function_exists('site_url') ? site_url($path) : (string)$path;

    if (preg_match('/^https?:\/\//i', $target)) {
        return $target;
    }

    $base_url = site_public_base_url();
    $base_path = (string)parse_url($base_url, PHP_URL_PATH);

    if ($base_path !== '' && $base_path !== '/' && strpos($target, $base_path . '/') === 0) {
        $target = substr($target, strlen($base_path));
    }

    return rtrim($base_url, '/') . '/' . ltrim($target, '/');
}

function ensure_phpmailer_loaded() {
    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        return true;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;

        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            return true;
        }
    }

    $phpmailer_src = __DIR__ . '/PHPMailer/src/';
    $required_files = [
        $phpmailer_src . 'Exception.php',
        $phpmailer_src . 'PHPMailer.php',
        $phpmailer_src . 'SMTP.php',
    ];

    foreach ($required_files as $required_file) {
        if (!file_exists($required_file)) {
            return false;
        }
    }

    require_once $phpmailer_src . 'Exception.php';
    require_once $phpmailer_src . 'PHPMailer.php';
    require_once $phpmailer_src . 'SMTP.php';

    return class_exists('\PHPMailer\PHPMailer\PHPMailer');
}

function site_password_reset_mailer_config() {
    $smtp_password = getenv('FG_SMTP_PASSWORD');
    if (($smtp_password === false || $smtp_password === '') && defined('SMTP_PASSWORD')) {
        $smtp_password = SMTP_PASSWORD;
    }
    if ($smtp_password !== false) {
        // Google displays app passwords in groups; SMTP requires the 16 characters without spaces.
        $smtp_password = preg_replace('/\s+/', '', $smtp_password);
    }

    $direct_values = [
        // Local XAMPP fallback values for testing:
        // Replace these مباشرة if you want to test without environment variables.
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'ayhamalslman367@gmail.com',
        'password' => '',
        'from_email' => 'ayhamalslman367@gmail.com',
        'from_name' => 'FAMOUS GAMING',
        'encryption' => 'tls',
    ];

    return [
        // Environment variables are used first if available.
        // Otherwise, the direct fallback values above are used for local testing.
        'host' => getenv('FG_SMTP_HOST') ?:     $direct_values['host'],
        'port' => (int)(getenv('FG_SMTP_PORT') ?: $direct_values['port']),
        'username' => getenv('FG_SMTP_USERNAME') ?: $direct_values['username'],
        'password' => $smtp_password ?: $direct_values['password'],
        'from_email' => getenv('FG_SMTP_FROM_EMAIL') ?: $direct_values['from_email'],
        'from_name' => getenv('FG_SMTP_FROM_NAME') ?: $direct_values['from_name'],
        'encryption' => getenv('FG_SMTP_ENCRYPTION') ?: $direct_values['encryption'],
    ];
}

function site_password_reset_mailer_is_configured($config) {
    return !empty($config['host'])
        && !empty($config['port'])
        && !empty($config['username'])
        && !empty($config['password'])
        && !empty($config['from_email'])
        && !empty($config['from_name'])
        && $config['host'] !== 'smtp.example.com'
        && $config['username'] !== 'your-email@example.com'
        && $config['username'] !== 'your-email@gmail.com'
        && $config['username'] !== 'your-gmail@gmail.com'
        && $config['password'] !== 'your-app-password-here'
        && $config['password'] !== 'your-16-character-app-password'
        && $config['from_email'] !== 'your-email@example.com'
        && $config['from_email'] !== 'your-email@gmail.com'
        && $config['from_email'] !== 'your-gmail@gmail.com';
}

function password_reset_mail_log_path() {
    $root = defined('SITE_ROOT_PATH') ? SITE_ROOT_PATH : dirname(__DIR__);
    return $root . '/logs/password-reset-mail.log';
}

function sanitize_password_reset_debug_line($line) {
    $line = (string)$line;
    $line = preg_replace('/334\s+[A-Za-z0-9+\/=]+/', '334 [REDACTED-CHALLENGE]', $line);
    $line = preg_replace('/AUTH\s+(PLAIN|LOGIN)\s+[A-Za-z0-9+\/=]+/i', 'AUTH $1 [REDACTED]', $line);
    $line = preg_replace('/([?&]token=)[a-f0-9]{32,}/i', '$1[REDACTED-TOKEN]', $line);
    $line = preg_replace('/\b[0-9A-Za-z._%+-]+@[0-9A-Za-z.-]+\.[A-Za-z]{2,}\b/', '[REDACTED-EMAIL]', $line);
    return $line;
}

function write_password_reset_mail_log($heading, array $lines = []) {
    $log_path = password_reset_mail_log_path();
    $log_dir = dirname($log_path);

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $heading . PHP_EOL;

    foreach ($lines as $line) {
        $entry .= sanitize_password_reset_debug_line($line) . PHP_EOL;
    }

    $entry .= str_repeat('-', 80) . PHP_EOL;
    file_put_contents($log_path, $entry, FILE_APPEND);
}

function extract_password_reset_mail_error($fallback_error, array $debug_lines = []) {
    $candidates = [];

    foreach ($debug_lines as $line) {
        $sanitized = trim(sanitize_password_reset_debug_line($line));
        if ($sanitized === '') {
            continue;
        }

        if (stripos($sanitized, '535 ') !== false || stripos($sanitized, 'SMTP ERROR:') !== false) {
            $candidates[] = $sanitized;
        }
    }

    if (!empty($candidates)) {
        return implode(' | ', array_slice($candidates, -2));
    }

    return trim((string)$fallback_error);
}

function ensure_site_user_password_reset_schema($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS password_resets (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        email VARCHAR(150) NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_password_resets_email (email),
        INDEX idx_password_resets_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $password_reset_token_column = mysqli_query($conn, "SHOW COLUMNS FROM site_users LIKE 'password_reset_token'");
    if ($password_reset_token_column && mysqli_num_rows($password_reset_token_column) === 0) {
        mysqli_query($conn, "ALTER TABLE site_users ADD COLUMN password_reset_token VARCHAR(128) DEFAULT NULL AFTER password");
        mysqli_query($conn, "ALTER TABLE site_users ADD INDEX idx_site_users_password_reset_token (password_reset_token)");
    }

    $password_reset_expiry_column = mysqli_query($conn, "SHOW COLUMNS FROM site_users LIKE 'password_reset_expires_at'");
    if ($password_reset_expiry_column && mysqli_num_rows($password_reset_expiry_column) === 0) {
        mysqli_query($conn, "ALTER TABLE site_users ADD COLUMN password_reset_expires_at DATETIME DEFAULT NULL AFTER password_reset_token");
    }
}

function clear_site_user_password_reset_token($conn, $user_id) {
    $user_id = (int)$user_id;

    if ($user_id <= 0) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "UPDATE site_users SET password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "i", $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return (bool)$success;
}

function send_site_password_reset_otp_email($user, $otp_code, &$error_message = null) {
    $error_message = null;
    $debug_lines = [];
    $mailer = null;

    if (!ensure_phpmailer_loaded()) {
        $error_message = function_exists('t') ? t('auth_reset_send_failed') : 'Password reset email could not be sent right now.';
        return false;
    }

    $config = site_password_reset_mailer_config();
    if (!site_password_reset_mailer_is_configured($config)) {
        $error_message = function_exists('t') ? t('auth_reset_mail_not_configured') : 'Email delivery is not available right now. Please try again later.';
        return false;
    }

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        $mailer->Port = (int)$config['port'];
        $mailer->Timeout = 15;
        $mailer->SMTPDebug = 2;
        $mailer->Debugoutput = function ($str, $level) use (&$debug_lines) {
            $debug_lines[] = '[' . $level . '] ' . sanitize_password_reset_debug_line($str);
        };
        $mailer->SMTPSecure = (($config['encryption'] ?? 'tls') === 'ssl')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $user_name = trim((string)($user['full_name'] ?? '')) ?: (string)($user['email'] ?? 'Player');
        $safe_name = htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8');
        $safe_code = htmlspecialchars((string)$otp_code, ENT_QUOTES, 'UTF-8');

        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($config['from_email'], $config['from_name']);
        $mailer->addAddress((string)$user['email'], $user_name);
        $mailer->isHTML(true);
        $mailer->Subject = function_exists('t') ? t('auth_otp_email_subject') : 'Your FAMOUS GAMING verification code';
        $email_direction = function_exists('site_direction') ? site_direction() : 'ltr';
        $email_align = $email_direction === 'rtl' ? 'right' : 'left';
        $mailer->Body = '
            <div dir="' . $email_direction . '" style="margin: 0; padding: 32px 14px; background: #f3f6fa; font-family: Arial, sans-serif; color: #172033;">
                <div style="max-width: 560px; margin: 0 auto; overflow: hidden; border: 1px solid #dde5ee; border-radius: 8px; background: #ffffff;">
                    <div style="padding: 22px 28px; background: #101827; color: #ffffff; text-align: center;">
                        <div style="font-size: 21px; font-weight: 800;">FAMOUS GAMING</div>
                        <div style="margin-top: 5px; color: #8cecff; font-size: 12px; text-transform: uppercase;">' . htmlspecialchars(function_exists('t') ? t('auth_otp_email_kicker') : 'Secure account verification', ENT_QUOTES, 'UTF-8') . '</div>
                    </div>
                    <div style="padding: 30px 28px; text-align: ' . $email_align . ';">
                        <p style="margin: 0 0 12px; font-size: 16px; font-weight: 700;">' . htmlspecialchars(function_exists('t') ? t('auth_reset_email_greeting', ['name' => $user_name], 'Hello ' . $user_name . ',') : ('Hello ' . $user_name . ','), ENT_QUOTES, 'UTF-8') . '</p>
                        <p style="margin: 0 0 22px; color: #4b5870; line-height: 1.7;">' . htmlspecialchars(function_exists('t') ? t('auth_otp_email_intro') : 'Use this verification code to reset your password:', ENT_QUOTES, 'UTF-8') . '</p>
                        <div style="padding: 22px; border: 1px solid #dbe6ef; border-radius: 8px; background: #f8fbfd; text-align: center;">
                            <div style="margin-bottom: 8px; color: #68758a; font-size: 12px; font-weight: 700; text-transform: uppercase;">' . htmlspecialchars(function_exists('t') ? t('auth_otp_label') : 'Verification code', ENT_QUOTES, 'UTF-8') . '</div>
                            <div style="direction: ltr; color: #101827; font-family: Consolas, monospace; font-size: 34px; font-weight: 800; letter-spacing: 10px;">' . $safe_code . '</div>
                        </div>
                        <p style="margin: 20px 0 8px; color: #4b5870; line-height: 1.7;">' . htmlspecialchars(function_exists('t') ? t('auth_otp_email_expiry') : 'This code expires in 10 minutes.', ENT_QUOTES, 'UTF-8') . '</p>
                        <p style="margin: 0; color: #b42318; line-height: 1.7; font-weight: 700;">' . htmlspecialchars(function_exists('t') ? t('auth_otp_email_security') : 'Never share this code with anyone.', ENT_QUOTES, 'UTF-8') . '</p>
                    </div>
                    <div style="padding: 18px 28px; border-top: 1px solid #e7edf3; background: #f8fafc; color: #68758a; font-size: 12px; line-height: 1.6; text-align: ' . $email_align . ';">
                        ' . htmlspecialchars(function_exists('t') ? t('auth_reset_email_ignore') : 'If you did not request this, you can safely ignore this email.', ENT_QUOTES, 'UTF-8') . '
                    </div>
                </div>
            </div>';
        $mailer->AltBody =
            (function_exists('t') ? t('auth_otp_email_intro') : 'Use this code to reset your password:') . "\n\n"
            . $otp_code . "\n\n"
            . (function_exists('t') ? t('auth_otp_email_expiry') : 'This code expires in 10 minutes.') . "\n"
            . (function_exists('t') ? t('auth_otp_email_security') : 'Never share this code with anyone.');

        $sent = $mailer->send();
        write_password_reset_mail_log('Password reset OTP email sent successfully', $debug_lines);
        return $sent;
    } catch (\Throwable $exception) {
        $mailer_error = $mailer instanceof \PHPMailer\PHPMailer\PHPMailer ? $mailer->ErrorInfo : '';
        $error_message = extract_password_reset_mail_error($mailer_error ?: $exception->getMessage(), $debug_lines);
        write_password_reset_mail_log('Password reset OTP email failed', array_merge([$error_message], $debug_lines));
        return false;
    }
}

function request_site_user_password_reset_otp($conn, $email) {
    ensure_user_auth_schema($conn);
    $email = strtolower(trim((string)$email));

    if ($email === '' || !validate_email($email)) {
        return ['success' => false, 'message' => function_exists('t') ? t('auth_email_invalid') : 'Invalid email address.'];
    }

    $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, status FROM site_users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'message' => function_exists('t') ? t('booking_submit_error') : 'Unable to process your request.'];
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$user) {
        return ['success' => false, 'message' => function_exists('t') ? t('auth_reset_email_not_found') : 'No account was found with that email address.'];
    }

    if (($user['status'] ?? 'Inactive') !== 'Active') {
        return ['success' => false, 'message' => function_exists('t') ? t('auth_inactive') : 'This account is inactive.'];
    }

    $otp_code = (string)random_int(100000, 999999);
    $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', time() + 600);
    $update_stmt = mysqli_prepare($conn, "UPDATE site_users SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ?");

    if (!$update_stmt) {
        return ['success' => false, 'message' => function_exists('t') ? t('booking_submit_error') : 'Unable to process your request.'];
    }

    mysqli_stmt_bind_param($update_stmt, "ssi", $otp_hash, $expires_at, $user['id']);
    $updated = mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);

    if (!$updated) {
        return ['success' => false, 'message' => function_exists('t') ? t('booking_submit_error') : 'Unable to process your request.'];
    }

    $send_error = null;
    if (!send_site_password_reset_otp_email($user, $otp_code, $send_error)) {
        $language = function_exists('site_language') ? site_language() : 'en';
        return [
            'success' => false,
            'email_sent' => false,
            'user_id' => (int)$user['id'],
            'email' => $email,
            'otp_code' => $otp_code,
            'message' => $language === 'ar'
                ? 'تعذر إرسال البريد، لكن تم إنشاء رمز التحقق. استخدم الرمز الظاهر لإكمال إعادة التعيين.'
                : 'Email could not be sent, but the verification code was created. Use the shown code to finish the reset.',
        ];
    }

    return ['success' => true, 'email_sent' => true, 'user_id' => (int)$user['id'], 'email' => $email, 'message' => function_exists('t') ? t('auth_otp_sent') : 'Verification code sent.'];
}

function verify_site_user_password_reset_otp($conn, $user_id, $email, $otp_code) {
    $user_id = (int)$user_id;
    $email = strtolower(trim((string)$email));
    $otp_code = trim((string)$otp_code);

    if ($user_id <= 0 || !preg_match('/^\d{6}$/', $otp_code)) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "SELECT password_reset_token, password_reset_expires_at FROM site_users WHERE id = ? AND email = ? AND status = 'Active' LIMIT 1");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "is", $user_id, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    $expires_at = $user ? strtotime((string)$user['password_reset_expires_at']) : false;
    return $user
        && $expires_at !== false
        && $expires_at >= time()
        && password_verify($otp_code, (string)$user['password_reset_token']);
}

function reset_site_user_password_after_otp($conn, $user_id, $email, $new_password) {
    $user_id = (int)$user_id;
    $email = strtolower(trim((string)$email));
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "UPDATE site_users SET password = ?, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = ? AND email = ? AND status = 'Active'");

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "sis", $hashed_password, $user_id, $email);
    $success = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);

    if ($success) {
        clear_current_site_user_cache($user_id);
    }

    return $success;
}

function send_site_password_reset_email($user, $reset_url, &$error_message = null) {
    $error_message = null;
    $debug_lines = [];

    if (!ensure_phpmailer_loaded()) {
        $error_message = 'PHPMailer source files are missing.';
        error_log('Password reset email failed: ' . $error_message);
        write_password_reset_mail_log('PHPMailer load failure', [$error_message]);
        return false;
    }

    $config = site_password_reset_mailer_config();

    if (!site_password_reset_mailer_is_configured($config)) {
        $error_message = 'SMTP settings are not configured yet.';
        error_log('Password reset email failed: ' . $error_message);
        write_password_reset_mail_log('SMTP configuration missing', [$error_message]);
        return false;
    }

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        $mailer->Port = (int)$config['port'];
        $mailer->Timeout = 15;
        $mailer->SMTPDebug = 2;
        $mailer->Debugoutput = function ($str, $level) use (&$debug_lines) {
            $debug_lines[] = '[' . $level . '] ' . sanitize_password_reset_debug_line($str);
        };

        if (($config['encryption'] ?? 'tls') === 'ssl') {
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($config['from_email'], $config['from_name']);
        $mailer->addAddress((string)$user['email'], (string)$user['full_name']);
        $mailer->isHTML(true);
        $mailer->Subject = function_exists('t') ? t('auth_reset_email_subject') : 'Reset your password';

        $user_name = trim((string)($user['full_name'] ?? ''));
        if ($user_name === '') {
            $user_name = (string)($user['email'] ?? 'Player');
        }

        $safe_name = htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8');
        $safe_url = htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8');
        $email_direction = function_exists('site_direction') ? site_direction() : 'ltr';
        $email_align = $email_direction === 'rtl' ? 'right' : 'left';
        $safe_subject = htmlspecialchars(function_exists('t') ? t('auth_reset_email_subject') : 'Reset your password', ENT_QUOTES, 'UTF-8');
        $safe_greeting = htmlspecialchars(function_exists('t') ? t('auth_reset_email_greeting', ['name' => $user_name], 'Hello ' . $user_name . ',') : ('Hello ' . $user_name . ','), ENT_QUOTES, 'UTF-8');
        $safe_intro = htmlspecialchars(function_exists('t') ? t('auth_reset_email_intro') : 'We received a request to reset your password.', ENT_QUOTES, 'UTF-8');
        $safe_action = htmlspecialchars(function_exists('t') ? t('auth_reset_email_action') : 'Use the secure button below to choose a new password.', ENT_QUOTES, 'UTF-8');
        $safe_button = htmlspecialchars(function_exists('t') ? t('auth_reset_email_button') : 'Reset Password', ENT_QUOTES, 'UTF-8');
        $safe_expiry = htmlspecialchars(function_exists('t') ? t('auth_reset_email_expiry') : 'This link expires in 15 minutes.', ENT_QUOTES, 'UTF-8');
        $safe_ignore = htmlspecialchars(function_exists('t') ? t('auth_reset_email_ignore') : 'If you did not request this, you can safely ignore this email.', ENT_QUOTES, 'UTF-8');

        $mailer->Body = '
            <div dir="' . $email_direction . '" style="margin:0; padding:32px 12px; background:#f1f5f9; font-family:Arial,Tahoma,sans-serif; color:#172033;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; border-collapse:separate; background:#ffffff; border:1px solid #dbe5ee; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 30px; background:#0b1728; text-align:center;">
                            <div style="color:#ffffff; font-size:23px; font-weight:800; line-height:1.3;">FAMOUS GAMING</div>
                            <div style="margin-top:6px; color:#72d7ed; font-size:12px; font-weight:700;">' . $safe_subject . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 30px; text-align:' . $email_align . ';">
                            <p style="margin:0 0 18px; color:#172033; font-size:17px; font-weight:700; line-height:1.7;">' . $safe_greeting . '</p>
                            <p style="margin:0 0 12px; color:#475569; font-size:15px; line-height:1.8;">' . $safe_intro . '</p>
                            <p style="margin:0 0 26px; color:#475569; font-size:15px; line-height:1.8;">' . $safe_action . '</p>
                            <div style="margin:0 0 26px; text-align:center;">
                                <a href="' . $safe_url . '" style="display:inline-block; padding:14px 28px; background:#23bfe2; color:#071827; text-decoration:none; border-radius:6px; font-size:15px; font-weight:800; line-height:1.4;">' . $safe_button . '</a>
                            </div>
                            <div style="padding:14px 16px; border:1px solid #cfe7ee; border-radius:6px; background:#f3fbfd; color:#245367; font-size:13px; font-weight:700; line-height:1.7;">' . $safe_expiry . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 30px; border-top:1px solid #e5edf3; background:#f8fafc; color:#64748b; text-align:' . $email_align . '; font-size:12px; line-height:1.7;">' . $safe_ignore . '</td>
                    </tr>
                </table>
            </div>';
        $mailer->AltBody =
            (function_exists('t') ? t('auth_reset_email_subject') : 'Reset your password') . "\n\n"
            . (function_exists('t') ? t('auth_reset_email_intro') : 'We received a request to reset your password.') . "\n"
            . (function_exists('t') ? t('auth_reset_email_action') : 'Use the secure link below to choose a new password.') . "\n"
            . $reset_url . "\n\n"
            . (function_exists('t') ? t('auth_reset_email_expiry') : 'This link expires in 15 minutes.') . "\n"
            . (function_exists('t') ? t('auth_reset_email_ignore') : 'If you did not request this, you can ignore this email.');

        $sent = $mailer->send();
        write_password_reset_mail_log('Password reset email sent successfully', $debug_lines);
        return $sent;
    } catch (\Throwable $exception) {
        $error_message = extract_password_reset_mail_error($mailer->ErrorInfo ?: $exception->getMessage(), $debug_lines);
        error_log('Password reset email failed: ' . $error_message);
        write_password_reset_mail_log('Password reset email failed', array_merge([$error_message], $debug_lines));
        return false;
    }
}

function create_site_user_password_reset_link($conn, $email) {
    ensure_user_auth_schema($conn);
    $email = strtolower(trim((string)$email));

    if ($email === '' || !validate_email($email)) {
        return [
            'success' => false,
            'code' => 'invalid_email',
            'message' => function_exists('t') ? t('auth_email_invalid') : 'Invalid email address.',
        ];
    }

    $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, status FROM site_users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        return [
            'success' => false,
            'code' => 'prepare_failed',
            'message' => function_exists('t') ? t('booking_submit_error') : 'Unable to process your request right now.',
        ];
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$user) {
        return [
            'success' => false,
            'code' => 'email_not_found',
            'message' => function_exists('t') ? t('auth_reset_email_not_found') : 'No account was found with that email address.',
        ];
    }

    if (($user['status'] ?? 'Inactive') !== 'Active') {
        return [
            'success' => false,
            'code' => 'inactive_account',
            'message' => function_exists('t') ? t('auth_inactive') : 'This account is inactive.',
        ];
    }

    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', time() + 900);

    mysqli_query($conn, "DELETE FROM password_resets WHERE expires_at < NOW()");

    $delete_stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ? OR email = ?");
    if ($delete_stmt) {
        mysqli_stmt_bind_param($delete_stmt, "is", $user['id'], $email);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
    }

    $insert_stmt = mysqli_prepare($conn, "INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    if (!$insert_stmt) {
        return [
            'success' => false,
            'code' => 'insert_prepare_failed',
            'message' => function_exists('t') ? t('booking_submit_error') : 'Unable to process your request right now.',
        ];
    }

    mysqli_stmt_bind_param($insert_stmt, "isss", $user['id'], $email, $token_hash, $expires_at);
    $stored = mysqli_stmt_execute($insert_stmt);
    mysqli_stmt_close($insert_stmt);

    if (!$stored) {
        return [
            'success' => false,
            'code' => 'token_store_failed',
            'message' => function_exists('t') ? t('booking_submit_error') : 'Unable to process your request right now.',
        ];
    }

    $language = function_exists('site_language') ? site_language() : 'en';
    $reset_url = site_absolute_url('general/reset_password.php?token=' . rawurlencode($token) . '&lang=' . rawurlencode($language));

    return [
        'success' => true,
        'email_sent' => false,
        'code' => 'local_reset_link',
        'reset_url' => $reset_url,
        'message' => $language === 'ar'
            ? 'تم إنشاء رابط إعادة التعيين. افتحه من أي جهاز متصل بنفس رابط الموقع.'
            : 'Password reset link created. Open it from any device connected to this site.',
    ];
}

function request_site_user_password_reset($conn, $email) {
    ensure_user_auth_schema($conn);
    $email = strtolower(trim((string)$email));

    if ($email === '' || !validate_email($email)) {
        return [
            'success' => false,
            'email_sent' => false,
            'code' => 'invalid_email',
            'message' => function_exists('t') ? t('auth_email_invalid') : 'Invalid email address.',
        ];
    }

    $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, status FROM site_users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        return [
            'success' => false,
            'email_sent' => false,
            'code' => 'prepare_failed',
            'message' => function_exists('t') ? t('booking_submit_error') : 'Unable to process your request right now.',
        ];
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$user) {
        return [
            'success' => false,
            'email_sent' => false,
            'code' => 'email_not_found',
            'message' => function_exists('t') ? t('auth_reset_email_not_found') : 'No account was found with that email address.',
        ];
    }

    if (($user['status'] ?? 'Inactive') !== 'Active') {
        return [
            'success' => false,
            'email_sent' => false,
            'code' => 'inactive_account',
            'message' => function_exists('t') ? t('auth_inactive') : 'This account is inactive.',
        ];
    }

    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', time() + 900);

    mysqli_query($conn, "DELETE FROM password_resets WHERE expires_at < NOW()");

    $delete_stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ? OR email = ?");
    if ($delete_stmt) {
        mysqli_stmt_bind_param($delete_stmt, "is", $user['id'], $email);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
    }

    $insert_stmt = mysqli_prepare($conn, "INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    if (!$insert_stmt) {
        return [
            'success' => false,
            'email_sent' => false,
            'code' => 'insert_prepare_failed',
            'message' => function_exists('t') ? t('booking_submit_error') : 'Unable to process your request right now.',
        ];
    }

    mysqli_stmt_bind_param($insert_stmt, "isss", $user['id'], $email, $token_hash, $expires_at);
    $stored = mysqli_stmt_execute($insert_stmt);
    mysqli_stmt_close($insert_stmt);

    if (!$stored) {
        return [
            'success' => false,
            'email_sent' => false,
            'code' => 'token_store_failed',
            'message' => function_exists('t') ? t('booking_submit_error') : 'Unable to process your request right now.',
        ];
    }

    $language = function_exists('site_language') ? site_language() : 'en';
    $reset_url = site_absolute_url('general/reset_password.php?token=' . rawurlencode($token) . '&lang=' . rawurlencode($language));
    $send_error = null;
    $mail_is_ready = ensure_phpmailer_loaded() && site_password_reset_mailer_is_configured(site_password_reset_mailer_config());

    $cleanup_password_reset_request = static function () use ($conn, $user, $email) {
        $cleanup_stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ? AND email = ?");
        if ($cleanup_stmt) {
            mysqli_stmt_bind_param($cleanup_stmt, "is", $user['id'], $email);
            mysqli_stmt_execute($cleanup_stmt);
            mysqli_stmt_close($cleanup_stmt);
        }
    };

    if (!$mail_is_ready) {
        $cleanup_password_reset_request();

        return [
            'success' => false,
            'email_sent' => false,
            'code' => 'mail_not_configured',
            'message' => function_exists('t') ? t('auth_reset_mail_not_configured') : 'Email delivery is not available right now. Please try again later.',
        ];
    }

    if (!send_site_password_reset_email($user, $reset_url, $send_error)) {
        error_log('Password reset email failed for ' . $email . ': ' . (string)$send_error);
        $cleanup_password_reset_request();

        return [
            'success' => false,
            'email_sent' => false,
            'code' => 'send_failed',
            'message' => function_exists('t') ? t('auth_reset_send_failed') : 'The reset email could not be sent right now. Please try again later.',
        ];
    }

    return [
        'success' => true,
        'email_sent' => true,
        'code' => 'sent',
        'message' => function_exists('t') ? t('auth_reset_success') : 'Password reset email sent successfully.',
    ];

}

function get_site_user_by_password_reset_token($conn, $token) {
    ensure_user_auth_schema($conn);
    $token = trim((string)$token);

    if ($token === '' || strlen($token) < 32 || !preg_match('/^[a-f0-9]+$/i', $token)) {
        return null;
    }

    $token_hash = hash('sha256', $token);
    $stmt = mysqli_prepare($conn, "SELECT u.id, u.full_name, u.email, u.status, pr.expires_at AS password_reset_expires_at
        FROM password_resets pr
        INNER JOIN site_users u ON u.id = pr.user_id AND u.email = pr.email
        WHERE pr.token_hash = ?
        LIMIT 1");
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "s", $token_hash);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$user || ($user['status'] ?? 'Inactive') !== 'Active') {
        return null;
    }

    $expires_at = strtotime((string)($user['password_reset_expires_at'] ?? ''));
    if ($expires_at === false || $expires_at < time()) {
        $cleanup_stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE token_hash = ?");
        if ($cleanup_stmt) {
            mysqli_stmt_bind_param($cleanup_stmt, "s", $token_hash);
            mysqli_stmt_execute($cleanup_stmt);
            mysqli_stmt_close($cleanup_stmt);
        }
        return null;
    }

    return $user;
}

function reset_site_user_password_by_token($conn, $token, $new_password) {
    $user = get_site_user_by_password_reset_token($conn, $token);

    if (!$user) {
        return false;
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "UPDATE site_users SET password = ? WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user['id']);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ?");
        if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, "i", $user['id']);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }

        clear_current_site_user_cache((int)$user['id']);

        if ((int)($_SESSION['site_user_id'] ?? 0) === (int)$user['id']) {
            unset($_SESSION['site_user_id'], $_SESSION['site_user_name'], $_SESSION['site_user_role'], $_SESSION['site_user_loyalty_points']);
        }
    }

    return (bool)$success;
}

/**
 * Keep a stable customer token in the PHP session for My Bookings.
 */
function get_customer_session_token() {
    if (empty($_SESSION['customer_booking_token'])) {
        $_SESSION['customer_booking_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['customer_booking_token'];
}

/**
 * Generate a short, shop-friendly booking code.
 */
function generate_booking_code() {
    return 'FG-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function send_guest_support_reply_email($ticket, $reply, &$error_message = null) {
    $error_message = null;
    $debug_lines = [];

    if (!ensure_phpmailer_loaded()) {
        $error_message = 'PHPMailer source files are missing.';
        return false;
    }

    $config = site_password_reset_mailer_config();
    if (!site_password_reset_mailer_is_configured($config)) {
        $error_message = function_exists('t') ? t('auth_reset_mail_not_configured') : 'Email delivery is not available right now. Please try again later.';
        return false;
    }

    $email = trim((string)($ticket['customer_email'] ?? ''));
    if ($email === '' || !validate_email($email)) {
        $error_message = function_exists('t') ? t('auth_email_invalid') : 'Invalid email address.';
        return false;
    }

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        $mailer->Port = (int)$config['port'];
        $mailer->Timeout = 15;
        $mailer->SMTPDebug = 2;
        $mailer->Debugoutput = function ($str, $level) use (&$debug_lines) {
            $debug_lines[] = '[' . $level . '] ' . sanitize_password_reset_debug_line($str);
        };
        $mailer->SMTPSecure = (($config['encryption'] ?? 'tls') === 'ssl')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $ticket_code = (string)($ticket['complaint_code'] ?? ('#' . ($ticket['id'] ?? '')));
        $customer_name = trim((string)($ticket['customer_name'] ?? 'Customer')) ?: 'Customer';
        $safe_name = htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8');
        $safe_code = htmlspecialchars($ticket_code, ENT_QUOTES, 'UTF-8');
        $safe_message = nl2br(htmlspecialchars((string)($ticket['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $safe_reply = nl2br(htmlspecialchars((string)$reply, ENT_QUOTES, 'UTF-8'));
        $email_direction = function_exists('site_direction') ? site_direction() : 'ltr';
        $email_align = $email_direction === 'rtl' ? 'right' : 'left';

        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($config['from_email'], $config['from_name']);
        $mailer->addAddress($email, $customer_name);
        $mailer->isHTML(true);
        $mailer->Subject = 'FAMOUS GAMING Support Reply - ' . $ticket_code;
        $mailer->Body = '
            <div dir="' . $email_direction . '" style="margin:0; padding:30px 12px; background:#f1f5f9; font-family:Arial,Tahoma,sans-serif; color:#172033;">
                <div style="max-width:620px; margin:0 auto; background:#ffffff; border:1px solid #dbe5ee; border-radius:8px; overflow:hidden;">
                    <div style="padding:22px 28px; background:#0b1728; color:#ffffff; text-align:center;">
                        <div style="font-size:22px; font-weight:800;">FAMOUS GAMING</div>
                        <div style="margin-top:6px; color:#72d7ed; font-size:12px; font-weight:700;">Support Reply ' . $safe_code . '</div>
                    </div>
                    <div style="padding:28px; text-align:' . $email_align . '; line-height:1.8;">
                        <p style="margin:0 0 16px; font-weight:700;">Hello ' . $safe_name . ',</p>
                        <p style="margin:0 0 18px; color:#475569;">The admin team replied to your support request.</p>
                        <div style="margin:0 0 18px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                            <strong style="display:block; margin-bottom:8px;">Your message</strong>
                            <div style="color:#475569;">' . $safe_message . '</div>
                        </div>
                        <div style="padding:16px; background:#ecfeff; border:1px solid #a5f3fc; border-radius:8px;">
                            <strong style="display:block; margin-bottom:8px;">Admin reply</strong>
                            <div>' . $safe_reply . '</div>
                        </div>
                    </div>
                </div>
            </div>';
        $mailer->AltBody = "FAMOUS GAMING Support Reply - " . $ticket_code . "\n\n"
            . "Your message:\n" . (string)($ticket['message'] ?? '') . "\n\n"
            . "Admin reply:\n" . (string)$reply;

        $sent = $mailer->send();
        write_password_reset_mail_log('Guest support reply email sent', $debug_lines);
        return $sent;
    } catch (\Throwable $exception) {
        $mailer_error = isset($mailer) && $mailer instanceof \PHPMailer\PHPMailer\PHPMailer ? $mailer->ErrorInfo : '';
        $error_message = extract_password_reset_mail_error($mailer_error ?: $exception->getMessage(), $debug_lines);
        write_password_reset_mail_log('Guest support reply email failed', array_merge([$error_message], $debug_lines));
        return false;
    }
}

/**
 * Generate a short support ticket code.
 */
function generate_support_ticket_code() {
    return 'SUP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

/**
 * Render a simple visual barcode from the stored booking code.
 */
function render_booking_barcode($value) {
    $hash = hash('sha256', (string)$value);
    $bars = '';

    for ($i = 0; $i < 48; $i++) {
        $hex = hexdec($hash[$i % strlen($hash)]);
        $width = 2 + ($hex % 4);
        $height = 42 + (($hex * 3) % 24);
        $bars .= '<span style="--bar-width:' . $width . 'px; --bar-height:' . $height . 'px;"></span>';
    }

    return $bars;
}

/**
 * Fetch a booking with room details for customer tickets.
 */
function get_customer_booking_by_id($conn, $booking_id) {
    $query = "SELECT b.*, r.room_name, r.room_type
              FROM bookings b
              LEFT JOIN rooms r ON b.room_id = r.id
              WHERE b.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $booking;
}

/**
 * Ensure admin notifications table exists.
 */
function ensure_admin_notifications_table($conn) {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $cached_ready = runtime_cache_get('schema_admin_notifications_table_ready', $found_cached_ready);
    if ($found_cached_ready && $cached_ready) {
        return;
    }

    $query = "CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        notification_type VARCHAR(50) NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        related_table VARCHAR(50) DEFAULT NULL,
        related_id INT DEFAULT NULL,
        action_url VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        read_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_notification_read_created (is_read, created_at),
        INDEX idx_notification_related (related_table, related_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    mysqli_query($conn, $query);
    runtime_cache_set('schema_admin_notifications_table_ready', true, 21600);
}

function clear_admin_notification_cache() {
    runtime_cache_forget('admin_notifications_');
}

/**
 * Create a new internal admin notification.
 */
function create_admin_notification($conn, $type, $title, $message, $related_table = null, $related_id = null, $action_url = null) {
    ensure_admin_notifications_table($conn);
    if (is_array($title)) {
        $title = notification_bilingual_text($title['en'] ?? '', $title['ar'] ?? '');
    }
    if (is_array($message)) {
        $message = notification_bilingual_text($message['en'] ?? '', $message['ar'] ?? '');
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO admin_notifications (notification_type, title, message, related_table, related_id, action_url)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ssssis", $type, $title, $message, $related_table, $related_id, $action_url);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        clear_admin_notification_cache();
    }

    return $success;
}

/**
 * Count unread internal admin notifications.
 */
function count_unread_admin_notifications($conn) {
    ensure_admin_notifications_table($conn);

    $cached_count = runtime_cache_get('admin_notifications_unread_count', $found_cached_count);
    if ($found_cached_count) {
        return (int)$cached_count;
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS unread_count FROM admin_notifications WHERE is_read = 0");
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        return (int)runtime_cache_set('admin_notifications_unread_count', (int)$row['unread_count'], SITE_RUNTIME_CACHE_TTL_SHORT);
    }

    return 0;
}

/**
 * Fetch recent internal admin notifications.
 */
function get_recent_admin_notifications($conn, $limit = 6) {
    ensure_admin_notifications_table($conn);
    $limit = max(1, min(20, (int)$limit));
    $cache_key = 'admin_notifications_recent_' . $limit;
    $cached_notifications = runtime_cache_get($cache_key, $found_cached_notifications);

    if ($found_cached_notifications) {
        return is_array($cached_notifications) ? $cached_notifications : [];
    }

    $notifications = [];

    $result = mysqli_query(
        $conn,
        "SELECT * FROM admin_notifications ORDER BY created_at DESC, id DESC LIMIT " . $limit
    );

    if ($result) {
        $notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return runtime_cache_set($cache_key, $notifications, SITE_RUNTIME_CACHE_TTL_SHORT);
}

/**
 * Mark one notification as read.
 */
function mark_admin_notification_read($conn, $notification_id) {
    ensure_admin_notifications_table($conn);
    $notification_id = (int)$notification_id;

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND is_read = 0"
    );
    mysqli_stmt_bind_param($stmt, "i", $notification_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        clear_admin_notification_cache();
    }

    return $success;
}

/**
 * Mark all notifications as read.
 */
function mark_all_admin_notifications_read($conn) {
    ensure_admin_notifications_table($conn);
    $success = mysqli_query($conn, "UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");

    if ($success) {
        clear_admin_notification_cache();
    }

    return $success;
}

/**
 * Fetch all internal admin notifications for the full notifications page.
 */
function get_all_admin_notifications($conn, $limit = 120) {
    ensure_admin_notifications_table($conn);
    $limit = max(20, min(250, (int)$limit));
    $notifications = [];

    $result = mysqli_query(
        $conn,
        "SELECT * FROM admin_notifications ORDER BY created_at DESC, id DESC LIMIT " . $limit
    );

    if ($result) {
        $notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $notifications;
}

/**
 * Ensure customer-facing notifications table exists.
 */
function ensure_site_notifications_table($conn) {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $cached_ready = runtime_cache_get('schema_site_notifications_table_ready', $found_cached_ready);
    if ($found_cached_ready && $cached_ready) {
        return;
    }

    $query = "CREATE TABLE IF NOT EXISTS site_notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        action_url VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        read_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_site_notifications_user_read (user_id, is_read, created_at),
        INDEX idx_site_notifications_user_created (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    mysqli_query($conn, $query);
    runtime_cache_set('schema_site_notifications_table_ready', true, 21600);
}

function clear_site_notification_cache($user_id = null) {
    if ($user_id !== null && (int)$user_id > 0) {
        runtime_cache_forget('site_notifications_' . (int)$user_id . '_');
        return;
    }

    runtime_cache_forget('site_notifications_');
}

function ensure_smart_notification_events_table($conn) {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $query = "CREATE TABLE IF NOT EXISTS smart_notification_events (
        id INT PRIMARY KEY AUTO_INCREMENT,
        event_key VARCHAR(160) NOT NULL UNIQUE,
        audience_type VARCHAR(20) NOT NULL,
        audience_id INT DEFAULT NULL,
        notification_table VARCHAR(40) DEFAULT NULL,
        notification_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_smart_notification_audience (audience_type, audience_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    mysqli_query($conn, $query);
}

function smart_notifications_enabled($conn) {
    $value = get_setting($conn, 'smart_notifications_enabled', null);

    if ($value === null) {
        mysqli_query(
            $conn,
            "INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description)
             VALUES ('smart_notifications_enabled', '1', 'boolean', 'Enable smart booking reminders, availability alerts, and store activity notifications')"
        );
        $value = '1';
    }

    return $value !== '0';
}

function smart_notification_text($en, $ar) {
    return function_exists('site_language') && site_language() === 'ar' ? $ar : $en;
}

function get_notification_type_meta($type) {
    $type = (string)$type;
    $groups = [
        'booking' => [
            'booking_created',
            'booking_updated',
            'booking_cancelled',
            'booking_reminder',
            'booking_available',
            'payment_received',
            'payment_pending',
        ],
        'store' => [
            'store_order_created',
            'store_order_updated',
            'store_activity',
            'low_stock',
        ],
        'support' => [
            'complaint_created',
            'support_message',
            'feedback_created',
        ],
        'account' => [
            'welcome',
            'account',
        ],
    ];

    $group = 'system';
    foreach ($groups as $group_key => $types) {
        if (in_array($type, $types, true) || str_starts_with($type, $group_key . '_')) {
            $group = $group_key;
            break;
        }
    }

    $labels = [
        'booking' => smart_notification_text('Booking', 'الحجوزات'),
        'store' => smart_notification_text('Store', 'المتجر'),
        'support' => smart_notification_text('Support', 'الدعم'),
        'account' => smart_notification_text('Account', 'الحساب'),
        'system' => smart_notification_text('System', 'النظام'),
    ];

    return [
        'group' => $group,
        'label' => $labels[$group] ?? $labels['system'],
        'class' => 'notification-type-' . $group,
    ];
}

function notification_localized_value($value, $fallback = '') {
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        $language = function_exists('site_language') ? site_language() : SITE_DEFAULT_LANGUAGE;
        if (!empty($decoded[$language])) {
            return (string)$decoded[$language];
        }
        if (!empty($decoded['en'])) {
            return (string)$decoded['en'];
        }
        if (!empty($decoded['ar'])) {
            return (string)$decoded['ar'];
        }
    }

    return $value;
}

function notification_bilingual_text($en, $ar) {
    return json_encode(
        ['en' => (string)$en, 'ar' => (string)$ar],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function localize_notification_for_display($notification) {
    $notification = is_array($notification) ? $notification : [];
    $type = (string)($notification['notification_type'] ?? '');
    $title = notification_localized_value($notification['title'] ?? '');
    $message = notification_localized_value($notification['message'] ?? '');
    $related_id = (int)($notification['related_id'] ?? 0);
    $action_url = (string)($notification['action_url'] ?? '');

    $code = '';
    if ($related_id > 0) {
        $code = '#' . $related_id;
    } elseif (preg_match('/(?:booking_id|order_id|ticket_id|id)=([0-9]+)/', $action_url, $match)) {
        $code = '#' . $match[1];
    }

    $known = [
        'booking_created' => [
            'title' => ['Booking update', 'تحديث الحجز'],
            'message' => ['A booking was created or updated' . ($code ? ' ' . $code : '') . '.', 'تم إنشاء أو تحديث حجز' . ($code ? ' ' . $code : '') . '.'],
        ],
        'booking_updated' => [
            'title' => ['Booking updated', 'تم تحديث الحجز'],
            'message' => ['Booking details were updated' . ($code ? ' ' . $code : '') . '.', 'تم تحديث تفاصيل الحجز' . ($code ? ' ' . $code : '') . '.'],
        ],
        'booking_cancelled' => [
            'title' => ['Booking cancelled', 'تم إلغاء الحجز'],
            'message' => ['Booking was cancelled' . ($code ? ' ' . $code : '') . '.', 'تم إلغاء الحجز' . ($code ? ' ' . $code : '') . '.'],
        ],
        'booking_reminder' => [
            'title' => ['Booking reminder', 'تذكير بالحجز'],
            'message' => ['A booking is coming up soon' . ($code ? ' ' . $code : '') . '.', 'يوجد حجز قريب' . ($code ? ' ' . $code : '') . '.'],
        ],
        'booking_available' => [
            'title' => ['Rooms available today', 'غرف متاحة اليوم'],
            'message' => ['There is room availability today.', 'توجد غرف متاحة اليوم.'],
        ],
        'payment_pending' => [
            'title' => ['Payment pending', 'الدفع بانتظار التأكيد'],
            'message' => ['Payment is waiting for confirmation' . ($code ? ' ' . $code : '') . '.', 'الدفع بانتظار التأكيد' . ($code ? ' ' . $code : '') . '.'],
        ],
        'payment_updated' => [
            'title' => ['Payment updated', 'تم تحديث الدفع'],
            'message' => ['Payment details were updated' . ($code ? ' ' . $code : '') . '.', 'تم تحديث تفاصيل الدفع' . ($code ? ' ' . $code : '') . '.'],
        ],
        'payment_received' => [
            'title' => ['Payment received', 'تم استلام الدفع'],
            'message' => ['Payment was received' . ($code ? ' ' . $code : '') . '.', 'تم استلام الدفع' . ($code ? ' ' . $code : '') . '.'],
        ],
        'store_order_created' => [
            'title' => ['Store order update', 'تحديث طلب المتجر'],
            'message' => ['A store order was created' . ($code ? ' ' . $code : '') . '.', 'تم إنشاء طلب متجر' . ($code ? ' ' . $code : '') . '.'],
        ],
        'store_order_updated' => [
            'title' => ['Store order updated', 'تم تحديث طلب المتجر'],
            'message' => ['Store order details were updated' . ($code ? ' ' . $code : '') . '.', 'تم تحديث تفاصيل طلب المتجر' . ($code ? ' ' . $code : '') . '.'],
        ],
        'store_activity' => [
            'title' => ['Store activity', 'نشاط المتجر'],
            'message' => ['Store activity needs review.', 'يوجد نشاط متجر يحتاج متابعة.'],
        ],
        'low_stock' => [
            'title' => ['Low stock alert', 'تنبيه مخزون منخفض'],
            'message' => ['Some products are low in stock.', 'بعض المنتجات مخزونها منخفض.'],
        ],
        'support_created' => [
            'title' => ['New support conversation', 'محادثة دعم جديدة'],
            'message' => ['A customer opened a support conversation' . ($code ? ' ' . $code : '') . '.', 'فتح عميل محادثة دعم' . ($code ? ' ' . $code : '') . '.'],
        ],
        'support_reply' => [
            'title' => ['Support conversation updated', 'تم تحديث محادثة الدعم'],
            'message' => ['A customer replied to a support conversation' . ($code ? ' ' . $code : '') . '.', 'رد عميل على محادثة دعم' . ($code ? ' ' . $code : '') . '.'],
        ],
        'support_replied' => [
            'title' => ['Admin replied', 'ردت الإدارة'],
            'message' => ['Your support conversation has a new admin reply' . ($code ? ' ' . $code : '') . '.', 'يوجد رد جديد من الإدارة على محادثة الدعم' . ($code ? ' ' . $code : '') . '.'],
        ],
        'support_sent' => [
            'title' => ['Support message sent', 'تم إرسال رسالة الدعم'],
            'message' => ['Your support message was sent to the admin team.', 'تم إرسال رسالة الدعم لفريق الإدارة.'],
        ],
        'feedback_created' => [
            'title' => ['New feedback submitted', 'ملاحظة جديدة'],
            'message' => ['New customer feedback was submitted.', 'تم إرسال ملاحظة جديدة من عميل.'],
        ],
        'complaint_created' => [
            'title' => ['New complaint submitted', 'شكوى جديدة'],
            'message' => ['A new complaint was submitted.', 'تم إرسال شكوى جديدة.'],
        ],
        'welcome' => [
            'title' => ['Welcome to FAMOUS GAMING', 'أهلاً بك في فيمس جيمينج'],
            'message' => ['Your account is ready. Start by choosing a room or checking the gaming store.', 'حسابك جاهز. ابدأ باختيار غرفة أو تصفح متجر الألعاب.'],
        ],
    ];

    if (isset($known[$type])) {
        $title = smart_notification_text($known[$type]['title'][0], $known[$type]['title'][1]);
        $message = smart_notification_text($known[$type]['message'][0], $known[$type]['message'][1]);
    }

    $notification['display_title'] = $title;
    $notification['display_message'] = $message;

    return $notification;
}

function record_smart_notification_event($conn, $event_key, $audience_type, $audience_id = null, $notification_table = null, $notification_id = null) {
    ensure_smart_notification_events_table($conn);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT IGNORE INTO smart_notification_events (event_key, audience_type, audience_id, notification_table, notification_id)
         VALUES (?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    $audience_id = $audience_id !== null ? (int)$audience_id : null;
    $notification_id = $notification_id !== null ? (int)$notification_id : null;
    mysqli_stmt_bind_param($stmt, "ssisi", $event_key, $audience_type, $audience_id, $notification_table, $notification_id);
    $success = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $success;
}

function create_unique_admin_notification($conn, $event_key, $type, $title, $message, $related_table = null, $related_id = null, $action_url = null) {
    if (!record_smart_notification_event($conn, $event_key, 'admin', null, null, null)) {
        return false;
    }

    $success = create_admin_notification($conn, $type, $title, $message, $related_table, $related_id, $action_url);

    if ($success) {
        $notification_id = (int)mysqli_insert_id($conn);
        $stmt = mysqli_prepare($conn, "UPDATE smart_notification_events SET notification_table = 'admin_notifications', notification_id = ? WHERE event_key = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "is", $notification_id, $event_key);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    return $success;
}

function create_unique_site_notification($conn, $event_key, $user_id, $type, $title, $message, $action_url = null) {
    $user_id = (int)$user_id;
    if ($user_id <= 0 || !record_smart_notification_event($conn, $event_key, 'user', $user_id, null, null)) {
        return false;
    }

    $success = create_site_notification($conn, $user_id, $type, $title, $message, $action_url);

    if ($success) {
        $notification_id = (int)mysqli_insert_id($conn);
        $stmt = mysqli_prepare($conn, "UPDATE smart_notification_events SET notification_table = 'site_notifications', notification_id = ? WHERE event_key = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "is", $notification_id, $event_key);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    return $success;
}

function generate_site_smart_notifications($conn, $user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0 || !smart_notifications_enabled($conn)) {
        return 0;
    }

    ensure_site_notifications_table($conn);
    ensure_smart_notification_events_table($conn);
    $created = 0;
    $today = date('Y-m-d');

    $stmt = mysqli_prepare(
        $conn,
        "SELECT b.id, b.booking_code, b.booking_date, b.start_time, b.status, r.room_name
         FROM bookings b
         LEFT JOIN rooms r ON r.id = b.room_id
         WHERE b.user_id = ?
           AND b.status IN ('Pending', 'Confirmed')
           AND TIMESTAMP(b.booking_date, b.start_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
         ORDER BY b.booking_date ASC, b.start_time ASC
         LIMIT 5"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($booking = $result ? mysqli_fetch_assoc($result) : null) {
            $booking_id = (int)$booking['id'];
            $event_key = 'user_booking_reminder_' . $user_id . '_' . $booking_id . '_' . $today;
            $title = smart_notification_text('Booking reminder', 'تذكير بالحجز');
            $message = smart_notification_text(
                'Your booking for ' . ($booking['room_name'] ?? 'a room') . ' starts on ' . format_date($booking['booking_date']) . ' at ' . format_time($booking['start_time']) . '.',
                'حجزك في ' . ($booking['room_name'] ?? 'الغرفة') . ' يبدأ بتاريخ ' . format_date($booking['booking_date']) . ' الساعة ' . format_time($booking['start_time']) . '.'
            );
            $created += create_unique_site_notification($conn, $event_key, $user_id, 'booking_reminder', $title, $message, 'user/my_bookings.php') ? 1 : 0;
        }
        mysqli_stmt_close($stmt);
    }

    $available_result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS available_rooms
         FROM rooms
         WHERE status = 'Available'"
    );
    $available_row = $available_result ? mysqli_fetch_assoc($available_result) : null;
    $available_rooms = (int)($available_row['available_rooms'] ?? 0);

    if ($available_rooms > 0) {
        $event_key = 'user_availability_' . $user_id . '_' . $today;
        $title = smart_notification_text('Rooms available today', 'غرف متاحة اليوم');
        $message = smart_notification_text(
            $available_rooms . ' room(s) are available now. Good time to reserve your gaming session.',
            'يوجد ' . $available_rooms . ' غرف متاحة الآن. وقت مناسب لتحجز جلستك.'
        );
        $created += create_unique_site_notification($conn, $event_key, $user_id, 'booking_available', $title, $message, 'user/room_booking.php#booking-form') ? 1 : 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, order_code, status, payment_status, updated_at
         FROM store_orders
         WHERE user_id = ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           AND (payment_status IN ('Pending Payment', 'Paid') OR status IN ('Confirmed', 'Completed'))
         ORDER BY updated_at DESC, id DESC
         LIMIT 4"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($order = $result ? mysqli_fetch_assoc($result) : null) {
            $order_id = (int)$order['id'];
            $event_key = 'user_store_activity_' . $user_id . '_' . $order_id . '_' . normalize_status_key($order['status'] . '_' . $order['payment_status']);
            $title = smart_notification_text('Store order update', 'تحديث طلب المتجر');
            $message = smart_notification_text(
                'Store order ' . $order['order_code'] . ' is now ' . $order['status'] . ' / ' . $order['payment_status'] . '.',
                'طلب المتجر ' . $order['order_code'] . ' حالته الآن ' . $order['status'] . ' / ' . $order['payment_status'] . '.'
            );
            $created += create_unique_site_notification($conn, $event_key, $user_id, 'store_activity', $title, $message, 'user/my_bookings.php') ? 1 : 0;
        }
        mysqli_stmt_close($stmt);
    }

    return $created;
}

function generate_admin_smart_notifications($conn) {
    if (!smart_notifications_enabled($conn)) {
        return 0;
    }

    ensure_admin_notifications_table($conn);
    ensure_smart_notification_events_table($conn);
    $created = 0;
    $today = date('Y-m-d');

    $result = mysqli_query(
        $conn,
        "SELECT b.id, b.customer_name, b.booking_date, b.start_time, b.status, r.room_name
         FROM bookings b
         LEFT JOIN rooms r ON r.id = b.room_id
         WHERE b.status IN ('Pending', 'Confirmed')
           AND TIMESTAMP(b.booking_date, b.start_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 HOUR)
         ORDER BY b.booking_date ASC, b.start_time ASC
         LIMIT 8"
    );

    if ($result) {
        while ($booking = mysqli_fetch_assoc($result)) {
            $booking_id = (int)$booking['id'];
            $event_key = 'admin_booking_due_' . $booking_id . '_' . date('YmdH', strtotime($booking['booking_date'] . ' ' . $booking['start_time']));
            $title = smart_notification_text('Upcoming booking reminder', 'تذكير حجز قريب');
            $message = smart_notification_text(
                ($booking['customer_name'] ?? 'Customer') . ' has a booking for ' . ($booking['room_name'] ?? 'room') . ' at ' . format_time($booking['start_time']) . '.',
                ($booking['customer_name'] ?? 'العميل') . ' لديه حجز في ' . ($booking['room_name'] ?? 'الغرفة') . ' الساعة ' . format_time($booking['start_time']) . '.'
            );
            $created += create_unique_admin_notification($conn, $event_key, 'booking_reminder', $title, $message, 'bookings', $booking_id, 'booking_details.php?id=' . $booking_id) ? 1 : 0;
        }
    }

    $pending_store_result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS pending_count
         FROM store_orders
         WHERE status = 'Pending' OR payment_status = 'Pending Payment'"
    );
    $pending_store_row = $pending_store_result ? mysqli_fetch_assoc($pending_store_result) : null;
    $pending_store_count = (int)($pending_store_row['pending_count'] ?? 0);

    if ($pending_store_count > 0) {
        $event_key = 'admin_store_pending_' . $today . '_' . $pending_store_count;
        $title = smart_notification_text('Store activity needs review', 'نشاط متجر يحتاج متابعة');
        $message = smart_notification_text(
            $pending_store_count . ' store order(s) are waiting for payment or status review.',
            'يوجد ' . $pending_store_count . ' طلب متجر بانتظار مراجعة الدفع أو الحالة.'
        );
        $created += create_unique_admin_notification($conn, $event_key, 'store_activity', $title, $message, 'store_orders', null, 'store_orders.php') ? 1 : 0;
    }

    $low_stock_result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS low_stock_count
         FROM store_products
         WHERE status = 'Active' AND stock_quantity BETWEEN 1 AND 3"
    );
    $low_stock_row = $low_stock_result ? mysqli_fetch_assoc($low_stock_result) : null;
    $low_stock_count = (int)($low_stock_row['low_stock_count'] ?? 0);

    if ($low_stock_count > 0) {
        $event_key = 'admin_store_low_stock_' . $today . '_' . $low_stock_count;
        $title = smart_notification_text('Low stock alert', 'تنبيه مخزون منخفض');
        $message = smart_notification_text(
            $low_stock_count . ' active product(s) are close to running out.',
            'يوجد ' . $low_stock_count . ' منتجات فعالة قاربت على النفاد.'
        );
        $created += create_unique_admin_notification($conn, $event_key, 'store_activity', $title, $message, 'store_products', null, 'store_products.php') ? 1 : 0;
    }

    $available_result = mysqli_query($conn, "SELECT COUNT(*) AS available_rooms FROM rooms WHERE status = 'Available'");
    $available_row = $available_result ? mysqli_fetch_assoc($available_result) : null;
    $available_rooms = (int)($available_row['available_rooms'] ?? 0);

    if ($available_rooms > 0) {
        $event_key = 'admin_booking_availability_' . $today;
        $title = smart_notification_text('Booking availability today', 'توفر حجوزات اليوم');
        $message = smart_notification_text(
            $available_rooms . ' room(s) are available. Use this window for walk-ins or promotions.',
            'يوجد ' . $available_rooms . ' غرف متاحة. استغلها للحجوزات السريعة أو العروض.'
        );
        $created += create_unique_admin_notification($conn, $event_key, 'booking_available', $title, $message, 'rooms', null, 'rooms_full_crud.php') ? 1 : 0;
    }

    return $created;
}

/**
 * Create a new customer-facing notification.
 */
function create_site_notification($conn, $user_id, $type, $title, $message, $action_url = null) {
    ensure_site_notifications_table($conn);
    $user_id = (int)$user_id;
    if (is_array($title)) {
        $title = notification_bilingual_text($title['en'] ?? '', $title['ar'] ?? '');
    }
    if (is_array($message)) {
        $message = notification_bilingual_text($message['en'] ?? '', $message['ar'] ?? '');
    }

    if ($user_id <= 0) {
        return false;
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO site_notifications (user_id, notification_type, title, message, action_url)
         VALUES (?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "issss", $user_id, $type, $title, $message, $action_url);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        clear_site_notification_cache($user_id);
    }

    return $success;
}

/**
 * Count unread customer notifications.
 */
function count_unread_site_notifications($conn, $user_id) {
    ensure_site_notifications_table($conn);
    $user_id = (int)$user_id;

    if ($user_id <= 0) {
        return 0;
    }

    $cache_key = 'site_notifications_' . $user_id . '_unread_count';
    $cached_count = runtime_cache_get($cache_key, $found_cached_count);
    if ($found_cached_count) {
        return (int)$cached_count;
    }

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS unread_count FROM site_notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (int)runtime_cache_set($cache_key, $row ? (int)$row['unread_count'] : 0, SITE_RUNTIME_CACHE_TTL_SHORT);
}

/**
 * Fetch customer notifications.
 */
function get_site_notifications($conn, $user_id, $limit = 80) {
    ensure_site_notifications_table($conn);
    $user_id = (int)$user_id;
    $limit = max(6, min(160, (int)$limit));
    $notifications = [];

    if ($user_id <= 0) {
        return $notifications;
    }

    $cache_key = 'site_notifications_' . $user_id . '_list_' . $limit;
    $cached_notifications = runtime_cache_get($cache_key, $found_cached_notifications);
    if ($found_cached_notifications) {
        return is_array($cached_notifications) ? $cached_notifications : [];
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM site_notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT " . $limit
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        $notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    mysqli_stmt_close($stmt);

    return runtime_cache_set($cache_key, $notifications, SITE_RUNTIME_CACHE_TTL_SHORT);
}

function get_recent_site_notifications($conn, $user_id, $limit = 5) {
    return get_site_notifications($conn, $user_id, $limit);
}

/**
 * Mark one customer notification as read.
 */
function mark_site_notification_read($conn, $user_id, $notification_id) {
    ensure_site_notifications_table($conn);
    $user_id = (int)$user_id;
    $notification_id = (int)$notification_id;

    if ($user_id <= 0 || $notification_id <= 0) {
        return false;
    }

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE site_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ? AND is_read = 0"
    );
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        clear_site_notification_cache($user_id);
    }

    return $success;
}

/**
 * Mark all customer notifications as read.
 */
function mark_all_site_notifications_read($conn, $user_id) {
    ensure_site_notifications_table($conn);
    $user_id = (int)$user_id;

    if ($user_id <= 0) {
        return false;
    }

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE site_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        clear_site_notification_cache($user_id);
    }

    return $success;
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

function smart_i18n($key, $replacements = []) {
    $language = function_exists('site_language') ? site_language() : 'en';
    $texts = [
        'en' => [
            'chat_default' => 'I can help with bookings, complaints, store products, popular times, and recommendations. Ask me what you need.',
            'chat_booking' => 'To book, choose a room, date, duration, and available start time. I can also suggest popular rooms and booking times below.',
            'chat_complaint' => 'For complaints or support notes, open the support form and describe the issue. The admin team will see it in the dashboard.',
            'chat_store' => 'Here are smart store picks based on recent store activity and your history when available.',
            'chat_hours' => 'Popular booking times are based on confirmed and pending bookings in the system.',
            'chat_products' => 'These products are recommended from store sales and available stock.',
            'chat_rooms' => 'These rooms are recommended from booking demand and your history when available.',
            'action_book' => 'Book a room',
            'action_complaint' => 'Send complaint',
            'action_store' => 'Open store',
            'action_bookings' => 'My bookings',
            'recommended_rooms' => 'Recommended rooms',
            'recommended_products' => 'Recommended products',
            'popular_times' => 'Popular times',
            'no_data' => 'Not enough data yet. Add more bookings or store orders to improve recommendations.',
            'insight_demand_title' => 'Booking demand forecast',
            'insight_customer_title' => 'Customer behavior',
            'insight_reports_title' => 'Automatic admin report',
            'insight_peak_title' => 'Popular booking times',
            'insight_store_title' => 'Smart store recommendations',
            'insight_room_title' => 'Smart room recommendations',
            'demand_low' => 'Demand looks light. Good time to promote available rooms.',
            'demand_medium' => 'Demand is steady. Keep staff ready around the peak slots.',
            'demand_high' => 'Demand is high. Watch room availability and pending payments closely.',
            'customer_summary' => ':users active users, :bookings bookings, :complaints complaints.',
            'admin_summary' => 'Today: :todayBookings bookings, :todayRevenue JOD paid revenue, :pending pending actions.',
        ],
        'ar' => [
            'chat_default' => 'أقدر أساعدك بالحجوزات، الشكاوى، منتجات المتجر، الأوقات المشهورة، والتوصيات. اسألني شو بتحتاج.',
            'chat_booking' => 'للحجز اختار الغرفة والتاريخ والمدة ووقت البداية المتاح. كمان أقدر أقترح غرف وأوقات مناسبة بالأسفل.',
            'chat_complaint' => 'للشكاوى أو ملاحظات الدعم افتح نموذج الدعم واكتب المشكلة. الإدارة ستراها داخل لوحة التحكم.',
            'chat_store' => 'هاي اختيارات ذكية من المتجر حسب حركة المبيعات وتاريخك إذا كنت مسجل دخول.',
            'chat_hours' => 'الأوقات المشهورة محسوبة من الحجوزات المؤكدة والمعلقة داخل النظام.',
            'chat_products' => 'هاي المنتجات مقترحة من مبيعات المتجر والمخزون المتاح.',
            'chat_rooms' => 'هاي الغرف مقترحة من الطلب على الحجوزات وتاريخك إذا كان متوفر.',
            'action_book' => 'احجز غرفة',
            'action_complaint' => 'إرسال شكوى',
            'action_store' => 'افتح المتجر',
            'action_bookings' => 'حجوزاتي',
            'recommended_rooms' => 'غرف مقترحة',
            'recommended_products' => 'منتجات مقترحة',
            'popular_times' => 'أوقات مشهورة',
            'no_data' => 'لا توجد بيانات كافية بعد. أضف حجوزات أو طلبات متجر أكثر لتحسين التوصيات.',
            'insight_demand_title' => 'توقع الطلب على الحجوزات',
            'insight_customer_title' => 'سلوك العملاء',
            'insight_reports_title' => 'تقرير تلقائي للإدارة',
            'insight_peak_title' => 'أوقات الحجز المشهورة',
            'insight_store_title' => 'توصيات المتجر الذكية',
            'insight_room_title' => 'توصيات الغرف الذكية',
            'demand_low' => 'الطلب خفيف. وقت مناسب لترويج الغرف المتاحة.',
            'demand_medium' => 'الطلب مستقر. جهز الفريق حول أوقات الذروة.',
            'demand_high' => 'الطلب مرتفع. راقب توفر الغرف والمدفوعات المعلقة.',
            'customer_summary' => ':users مستخدم نشط، :bookings حجز، :complaints شكوى.',
            'admin_summary' => 'اليوم: :todayBookings حجوزات، :todayRevenue دينار مدفوع، :pending إجراء معلق.',
        ],
    ];

    $text = $texts[$language][$key] ?? $texts['en'][$key] ?? $key;

    foreach ($replacements as $placeholder => $value) {
        $text = str_replace(':' . $placeholder, (string)$value, $text);
    }

    return $text;
}

function get_smart_room_recommendations($conn, $user_id = 0, $limit = 3) {
    $limit = max(1, min(6, (int)$limit));
    $user_id = (int)$user_id;
    $rooms = [];

    if ($user_id > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT r.id, r.room_name, r.room_type, r.price_per_hour, r.description, COUNT(b.id) AS score
             FROM bookings b
             INNER JOIN rooms r ON r.id = b.room_id
             WHERE b.user_id = ?
             GROUP BY r.id, r.room_name, r.room_type, r.price_per_hour, r.description
             ORDER BY score DESC, MAX(b.created_at) DESC
             LIMIT " . $limit
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                $rooms = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (count($rooms) < $limit) {
        $result = mysqli_query(
            $conn,
            "SELECT r.id, r.room_name, r.room_type, r.price_per_hour, r.description, COUNT(b.id) AS score
             FROM rooms r
             LEFT JOIN bookings b ON b.room_id = r.id AND b.status IN ('Pending', 'Confirmed', 'Completed')
             GROUP BY r.id, r.room_name, r.room_type, r.price_per_hour, r.description
             ORDER BY score DESC, r.status ASC, r.room_name ASC
             LIMIT " . $limit
        );

        if ($result) {
            foreach (mysqli_fetch_all($result, MYSQLI_ASSOC) as $room) {
                $exists = false;
                foreach ($rooms as $existing_room) {
                    if ((int)$existing_room['id'] === (int)$room['id']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $rooms[] = $room;
                }
                if (count($rooms) >= $limit) {
                    break;
                }
            }
        }
    }

    return array_slice($rooms, 0, $limit);
}

function get_smart_store_recommendations($conn, $user_id = 0, $limit = 4) {
    $limit = max(1, min(8, (int)$limit));
    $user_id = (int)$user_id;
    $category = '';

    if ($user_id > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT soi.category, COUNT(*) AS score
             FROM store_orders so
             INNER JOIN store_order_items soi ON soi.order_id = so.id
             WHERE so.user_id = ?
             GROUP BY soi.category
             ORDER BY score DESC
             LIMIT 1"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            $category = $row['category'] ?? '';
            mysqli_stmt_close($stmt);
        }
    }

    $products = [];

    if ($category !== '') {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, product_name, category, price, description, image_path, stock_quantity
             FROM store_products
             WHERE status = 'Active' AND stock_quantity > 0 AND category = ?
             ORDER BY stock_quantity DESC, created_at DESC
             LIMIT " . $limit
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $category);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (count($products) < $limit) {
        $result = mysqli_query(
            $conn,
            "SELECT sp.id, sp.product_name, sp.category, sp.price, sp.description, sp.image_path, sp.stock_quantity,
                    COALESCE(SUM(soi.quantity), 0) AS units_sold
             FROM store_products sp
             LEFT JOIN store_order_items soi ON soi.product_id = sp.id
             LEFT JOIN store_orders so ON so.id = soi.order_id AND so.payment_status = 'Paid'
             WHERE sp.status = 'Active' AND sp.stock_quantity > 0
             GROUP BY sp.id, sp.product_name, sp.category, sp.price, sp.description, sp.image_path, sp.stock_quantity
             ORDER BY units_sold DESC, sp.created_at DESC
             LIMIT " . $limit
        );

        if ($result) {
            foreach (mysqli_fetch_all($result, MYSQLI_ASSOC) as $product) {
                $exists = false;
                foreach ($products as $existing_product) {
                    if ((int)$existing_product['id'] === (int)$product['id']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $products[] = $product;
                }
                if (count($products) >= $limit) {
                    break;
                }
            }
        }
    }

    return array_slice($products, 0, $limit);
}

function get_popular_booking_times($conn, $limit = 4) {
    $limit = max(1, min(8, (int)$limit));
    $times = [];
    $result = mysqli_query(
        $conn,
        "SELECT start_time, COUNT(*) AS booking_count
         FROM bookings
         WHERE status IN ('Pending', 'Confirmed', 'Completed')
         GROUP BY start_time
         ORDER BY booking_count DESC, start_time ASC
         LIMIT " . $limit
    );

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $times[] = [
                'time' => format_time($row['start_time']),
                'count' => (int)$row['booking_count'],
            ];
        }
    }

    return $times;
}

function get_admin_smart_insights($conn) {
    $today_bookings = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM bookings WHERE booking_date = CURDATE()"))['count'] ?? 0);
    $next_7_bookings = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM bookings WHERE booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"))['count'] ?? 0);
    $active_users = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM site_users WHERE status = 'Active'"))['count'] ?? 0);
    $booking_count = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM bookings"))['count'] ?? 0);
    $complaints_count = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM complaints"))['count'] ?? 0);
    $pending_bookings = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM bookings WHERE status = 'Pending'"))['count'] ?? 0);
    $pending_store_orders = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM store_orders WHERE status = 'Pending'"))['count'] ?? 0);
    $today_booking_revenue = (float)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(paid_amount), 0) AS total FROM bookings WHERE payment_status = 'Paid' AND booking_date = CURDATE()"))['total'] ?? 0);
    $today_store_revenue = (float)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(paid_amount), 0) AS total FROM store_orders WHERE payment_status = 'Paid' AND DATE(created_at) = CURDATE()"))['total'] ?? 0);
    $demand_level = $next_7_bookings >= 18 ? 'high' : ($next_7_bookings >= 7 ? 'medium' : 'low');

    return [
        [
            'type' => 'demand',
            'title' => smart_i18n('insight_demand_title'),
            'value' => $next_7_bookings,
            'text' => smart_i18n('demand_' . $demand_level),
        ],
        [
            'type' => 'customers',
            'title' => smart_i18n('insight_customer_title'),
            'value' => $active_users,
            'text' => smart_i18n('customer_summary', [
                'users' => $active_users,
                'bookings' => $booking_count,
                'complaints' => $complaints_count,
            ]),
        ],
        [
            'type' => 'report',
            'title' => smart_i18n('insight_reports_title'),
            'value' => number_format($today_booking_revenue + $today_store_revenue, 2),
            'text' => smart_i18n('admin_summary', [
                'todayBookings' => $today_bookings,
                'todayRevenue' => number_format($today_booking_revenue + $today_store_revenue, 2),
                'pending' => $pending_bookings + $pending_store_orders,
            ]),
        ],
    ];
}

function ensure_smart_support_tables($conn) {
    static $checked = false;
    if ($checked) {
        return true;
    }

    $checked = true;
    $session_table = "CREATE TABLE IF NOT EXISTS chatbot_sessions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        session_token VARCHAR(64) NOT NULL,
        language VARCHAR(5) NOT NULL DEFAULT 'en',
        page_url VARCHAR(255) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Open',
        closed_for_user TINYINT(1) NOT NULL DEFAULT 0,
        last_user_message_at TIMESTAMP NULL DEFAULT NULL,
        last_admin_message_at TIMESTAMP NULL DEFAULT NULL,
        closed_at TIMESTAMP NULL DEFAULT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_chatbot_sessions_status (status, closed_for_user, updated_at),
        INDEX idx_chatbot_sessions_user_started (user_id, started_at),
        INDEX idx_chatbot_sessions_token (session_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $message_table = "CREATE TABLE IF NOT EXISTS chatbot_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        session_id INT NOT NULL,
        sender VARCHAR(20) NOT NULL,
        message_text TEXT NOT NULL,
        intent VARCHAR(50) DEFAULT NULL,
        response_payload LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chatbot_messages_session_created (session_id, created_at),
        INDEX idx_chatbot_messages_intent (intent)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $report_table = "CREATE TABLE IF NOT EXISTS smart_report_snapshots (
        id INT PRIMARY KEY AUTO_INCREMENT,
        report_type VARCHAR(50) NOT NULL,
        report_title VARCHAR(150) NOT NULL,
        metric_value VARCHAR(80) DEFAULT NULL,
        report_payload LONGTEXT DEFAULT NULL,
        created_by_admin_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_smart_report_type_created (report_type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $created = mysqli_query($conn, $session_table)
        && mysqli_query($conn, $message_table)
        && mysqli_query($conn, $report_table);

    $columns = [];
    $result = mysqli_query($conn, "SHOW COLUMNS FROM chatbot_sessions");
    if ($result) {
        while ($column = mysqli_fetch_assoc($result)) {
            $columns[$column['Field']] = true;
        }
    }

    if (!isset($columns['status'])) {
        mysqli_query($conn, "ALTER TABLE chatbot_sessions ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Open' AFTER page_url");
    }
    if (!isset($columns['closed_for_user'])) {
        mysqli_query($conn, "ALTER TABLE chatbot_sessions ADD COLUMN closed_for_user TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    }
    if (!isset($columns['last_user_message_at'])) {
        mysqli_query($conn, "ALTER TABLE chatbot_sessions ADD COLUMN last_user_message_at TIMESTAMP NULL DEFAULT NULL AFTER closed_for_user");
    }
    if (!isset($columns['last_admin_message_at'])) {
        mysqli_query($conn, "ALTER TABLE chatbot_sessions ADD COLUMN last_admin_message_at TIMESTAMP NULL DEFAULT NULL AFTER last_user_message_at");
    }
    if (!isset($columns['closed_at'])) {
        mysqli_query($conn, "ALTER TABLE chatbot_sessions ADD COLUMN closed_at TIMESTAMP NULL DEFAULT NULL AFTER last_admin_message_at");
    }

    return $created;
}

function close_inactive_user_support_chats($conn, $user_id = 0) {
    ensure_smart_support_tables($conn);

    $where_user = '';
    if ((int)$user_id > 0) {
        $where_user = ' AND user_id = ' . (int)$user_id;
    }

    return mysqli_query(
        $conn,
        "UPDATE chatbot_sessions
         SET status = 'Closed', closed_for_user = 1, closed_at = NOW()
         WHERE status = 'Open'
           AND user_id IS NOT NULL
           AND last_admin_message_at IS NOT NULL
           AND last_admin_message_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
           AND (last_user_message_at IS NULL OR last_user_message_at < last_admin_message_at)"
           . $where_user
    );
}

function get_support_chatbot_session_id($conn, $user_id = 0) {
    ensure_smart_support_tables($conn);
    close_inactive_user_support_chats($conn, (int)$user_id);

    if (empty($_SESSION['support_chatbot_token'])) {
        $_SESSION['support_chatbot_token'] = bin2hex(random_bytes(16));
    }

    $session_token = $_SESSION['support_chatbot_token'];
    $language = function_exists('site_language') ? site_language() : 'en';
    $page_url = $_SERVER['HTTP_REFERER'] ?? ($_SERVER['REQUEST_URI'] ?? null);
    $user_id = $user_id > 0 ? (int)$user_id : null;

    if ($user_id !== null) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM chatbot_sessions WHERE user_id = ? AND status = 'Open' AND closed_for_user = 0 ORDER BY id DESC LIMIT 1");
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id FROM chatbot_sessions WHERE session_token = ? AND status = 'Open' AND closed_for_user = 0 ORDER BY id DESC LIMIT 1");
    }

    if ($stmt) {
        if ($user_id !== null) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $session_token);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        if ($row) {
            return (int)$row['id'];
        }
    }

    if ($user_id === null) {
        $stmt = mysqli_prepare($conn, "INSERT INTO chatbot_sessions (user_id, session_token, language, page_url, status) VALUES (NULL, ?, ?, ?, 'Open')");
        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, "sss", $session_token, $language, $page_url);
        mysqli_stmt_execute($stmt);
        $session_id = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        return $session_id;
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO chatbot_sessions (user_id, session_token, language, page_url, status) VALUES (?, ?, ?, ?, 'Open')");
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, "isss", $user_id, $session_token, $language, $page_url);
    mysqli_stmt_execute($stmt);
    $session_id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $session_id;
}

function support_chatbot_message_has_any($message, $keywords) {
    foreach ($keywords as $keyword) {
        if ($keyword !== '' && (function_exists('mb_strpos') ? mb_strpos($message, $keyword, 0, 'UTF-8') : strpos($message, $keyword)) !== false) {
            return true;
        }
    }

    return false;
}

function support_chatbot_detect_language_v2($message) {
    $message = (string)$message;

    if ($message !== '' && preg_match('/[\x{0600}-\x{06FF}]/u', $message)) {
        return 'ar';
    }

    if (function_exists('site_language') && site_language() === 'ar') {
        return 'ar';
    }

    return 'en';
}

function support_chatbot_text_v2($key, $message = '') {
    $language = support_chatbot_detect_language_v2($message);
    $texts = [
        'en' => [
            'chat_default' => 'I can help with bookings, payments, complaints, store products, opening hours, and location. Ask me anything about the playroom.',
            'chat_booking' => 'To book a room, choose the room, date, duration, and available start time. After that you can add snacks or drinks and continue to payment.',
            'chat_payment' => 'Visa simulation marks the booking as paid immediately. Cash and CliQ stay under Pending Payment until the admin confirms them.',
            'chat_complaint' => 'You can open the complaints page, write the issue or feedback clearly, and the admin team will receive it in the dashboard.',
            'chat_store' => 'The store includes games, accessories, snacks, and drinks. I can also show recommended products from recent activity and available stock.',
            'chat_recommendation' => 'Based on current demand and available stock, here are the best recommendations I can show you right now.',
            'chat_location' => 'FAMOUS GAMING is in Rainbow Street, Jabal Amman, Amman, Jordan. You can also open the Contact page or Google Maps from the location card.',
            'chat_hours' => 'Opening hours are 9:00 AM - 12:00 AM from Sunday to Thursday, and 9:00 AM - 1:00 AM on Friday and Saturday.',
            'chat_contact' => 'You can contact us by phone at +962 79 849 7188 or by email at bookings@famousgaming.jo and info@famousgaming.jo.',
            'chat_account' => 'You can log in or create an account to manage bookings, payments, support requests, store orders, and loyalty points.',
            'action_book' => 'Book a room',
            'action_complaint' => 'Send complaint',
            'action_store' => 'Open store',
            'action_contact' => 'Open contact page',
            'action_login' => 'Login',
            'action_bookings' => 'My bookings',
            'recommended_rooms' => 'Recommended rooms',
            'recommended_products' => 'Recommended products',
            'popular_times' => 'Popular times',
            'bookings_count' => 'bookings',
            'room_rate_unit' => 'JOD/hr',
            'product_rate_unit' => 'JOD',
        ],
        'ar' => [
            'chat_default' => 'أقدر أساعدك بالحجوزات، الدفع، الشكاوى، منتجات المتجر، أوقات الدوام، والموقع. اسألني أي شيء يخص البلاي روم.',
            'chat_booking' => 'للحجز اختر الغرفة والتاريخ والمدة ووقت البداية المتاح. وبعدها تقدر تضيف سناكات أو مشروبات وتكمل للدفع.',
            'chat_payment' => 'دفع الفيزا التجريبي يعلّم الحجز مدفوع مباشرة. أما الكاش وCliQ فتبقى حالتهما بانتظار تأكيد الإدارة.',
            'chat_complaint' => 'تقدر تفتح صفحة الشكاوى وتكتب المشكلة أو الملاحظة بشكل واضح، والإدارة ستستلمها مباشرة داخل لوحة التحكم.',
            'chat_store' => 'المتجر فيه ألعاب وإكسسوارات وسناكات ومشروبات. وأقدر أيضًا أعرض لك منتجات مقترحة حسب النشاط الأخير والمخزون المتاح.',
            'chat_recommendation' => 'حسب الطلب الحالي والمخزون المتوفر، هذه أفضل التوصيات التي أقدر أعرضها لك الآن.',
            'chat_location' => 'فاموس جيمينج موجود في شارع الرينبو، جبل عمان، عمّان، الأردن. وتقدر أيضًا تفتح صفحة التواصل أو خرائط جوجل من بطاقة الموقع.',
            'chat_hours' => 'أوقات الدوام من الأحد إلى الخميس من 9:00 صباحًا إلى 12:00 منتصف الليل، ويومي الجمعة والسبت من 9:00 صباحًا إلى 1:00 بعد منتصف الليل.',
            'chat_contact' => 'تقدر تتواصل معنا على الرقم +962 79 849 7188 أو عبر الإيميل bookings@famousgaming.jo و info@famousgaming.jo.',
            'chat_account' => 'تقدر تسجل دخول أو تنشئ حساب لإدارة الحجوزات، الدفعات، طلبات الدعم، طلبات المتجر، ونقاط الولاء.',
            'action_book' => 'احجز غرفة',
            'action_complaint' => 'إرسال شكوى',
            'action_store' => 'افتح المتجر',
            'action_contact' => 'صفحة التواصل',
            'action_login' => 'تسجيل الدخول',
            'action_bookings' => 'حجوزاتي',
            'recommended_rooms' => 'غرف مقترحة',
            'recommended_products' => 'منتجات مقترحة',
            'popular_times' => 'أوقات مشهورة',
            'bookings_count' => 'حجوزات',
            'room_rate_unit' => 'د.أ/ساعة',
            'product_rate_unit' => 'د.أ',
        ],
    ];

    return $texts[$language][$key] ?? smart_i18n($key);
}

function detect_support_chatbot_intent_v2($message) {
    $message = trim((string)$message);
    $message = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);

    if (support_chatbot_message_has_any($message, [
        'payment', 'pay', 'visa', 'cash', 'cliq', 'card', 'checkout', 'pending payment',
        'دفع', 'ادفع', 'فيزا', 'كاش', 'كليك', 'بطاقة', 'بطاقه', 'cvv', 'رصيد'
    ])) {
        return 'payment';
    }

    if (support_chatbot_message_has_any($message, [
        'complaint', 'support', 'help', 'issue', 'problem', 'feedback',
        'شكوى', 'شكاوى', 'مشكلة', 'مشكله', 'دعم', 'مساعدة', 'ساعدني', 'ملاحظة', 'ملاحظه'
    ])) {
        return 'complaint';
    }

    if (support_chatbot_message_has_any($message, [
        'location', 'address', 'map', 'where', 'place', 'branch',
        'موقع', 'الموقع', 'عنوان', 'وين', 'وينكم', 'وين موقعكم', 'جبل عمان', 'جبل عمّان', 'الرينبو', 'rainbow'
    ])) {
        return 'location';
    }

    if (support_chatbot_message_has_any($message, [
        'hours', 'open', 'close', 'opening', 'working hours', 'schedule',
        'دوام', 'مواعيد', 'اوقات', 'أوقات', 'ساعات', 'تفتحوا', 'بتفتحوا', 'بتسكروا', 'متى', 'مفتوح', 'مغلق'
    ])) {
        return 'hours';
    }

    if (support_chatbot_message_has_any($message, [
        'contact', 'phone', 'email', 'call', 'whatsapp',
        'تواصل', 'اتصال', 'رقم', 'الهاتف', 'ايميل', 'إيميل', 'بريد', 'واتساب'
    ])) {
        return 'contact';
    }

    if (support_chatbot_message_has_any($message, [
        'store', 'product', 'products', 'controller', 'game', 'accessory', 'snack', 'drink', 'menu', 'basket', 'order',
        'متجر', 'منتج', 'منتجات', 'شراء', 'بلايستيشن', 'بلاي ستيشن', 'يد', 'لعبة', 'العاب', 'ألعاب', 'اكسسوار', 'إكسسوار', 'سناك', 'مشروب', 'مشروبات', 'منيو', 'سلة'
    ])) {
        return 'store';
    }

    if (support_chatbot_message_has_any($message, [
        'recommend', 'suggest', 'best', 'popular', 'top',
        'توصية', 'توصيه', 'اقترح', 'اقترحلي', 'اقتراح', 'رشح', 'انصح', 'أفضل', 'افضل', 'مشهور'
    ])) {
        return 'recommendation';
    }

    if (support_chatbot_message_has_any($message, [
        'booking', 'book', 'room', 'rooms', 'reserve', 'reservation', 'available', 'availability', 'slot', 'time', 'date', 'price', 'cost',
        'حجز', 'احجز', 'أحجز', 'حجوزات', 'غرفة', 'غرف', 'متاح', 'المتاح', 'توفر', 'وقت', 'موعد', 'تاريخ', 'ساعة', 'سعر', 'اسعار', 'أسعار'
    ])) {
        return 'booking';
    }

    if (support_chatbot_message_has_any($message, [
        'login', 'register', 'signup', 'sign up', 'account', 'profile', 'password',
        'تسجيل', 'دخول', 'حساب', 'بروفايل', 'ملف', 'كلمة المرور', 'باسورد', 'انشاء حساب', 'إنشاء حساب'
    ])) {
        return 'account';
    }

    return 'general';
}

function detect_support_chatbot_intent($message) {
    $message = trim((string)$message);
    $message = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);

    if (support_chatbot_message_has_any($message, ['booking', 'book', 'room', 'حجز', 'احجز', 'أحجز', 'غرفة', 'غرف', 'وقت', 'موعد'])) {
        return 'booking';
    }

    if (support_chatbot_message_has_any($message, ['complaint', 'support', 'help', 'شكوى', 'مشكلة', 'دعم', 'مساعدة', 'ساعدني'])) {
        return 'complaint';
    }

    if (support_chatbot_message_has_any($message, ['store', 'product', 'controller', 'game', 'متجر', 'منتج', 'منتجات', 'شراء', 'بلايستيشن', 'يد', 'لعبة', 'العاب', 'ألعاب'])) {
        return 'store';
    }

    if (support_chatbot_message_has_any($message, ['recommend', 'suggest', 'توصية', 'اقترح', 'اقتراح', 'رشح', 'انصح'])) {
        return 'recommendation';
    }

    return 'general';
}

function log_support_chatbot_message($conn, $session_id, $sender, $message_text, $intent = null, $payload = null) {
    $session_id = (int)$session_id;
    if ($session_id <= 0) {
        return false;
    }

    $payload_text = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = mysqli_prepare($conn, "INSERT INTO chatbot_messages (session_id, sender, message_text, intent, response_payload) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "issss", $session_id, $sender, $message_text, $intent, $payload_text);
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($saved) {
        if ($sender === 'admin') {
            mysqli_query($conn, "UPDATE chatbot_sessions SET last_admin_message_at = NOW(), status = 'Open' WHERE id = " . $session_id);
        } elseif ($sender === 'user') {
            mysqli_query($conn, "UPDATE chatbot_sessions SET last_user_message_at = NOW(), closed_for_user = 0, status = 'Open' WHERE id = " . $session_id);
        }
    }

    return $saved;
}

function build_support_chatbot_response($conn, $message, $user_id = 0) {
    $message = trim((string)$message);
    $message = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);
    $intent = detect_support_chatbot_intent_v2($message);
    $room_rate_unit = support_chatbot_text_v2('room_rate_unit', $message);
    $product_rate_unit = support_chatbot_text_v2('product_rate_unit', $message);
    $bookings_count_label = support_chatbot_text_v2('bookings_count', $message);

    $build_room_section = static function ($rooms) use ($message, $room_rate_unit) {
        return [
            'title' => support_chatbot_text_v2('recommended_rooms', $message),
            'items' => array_map(static function ($room) use ($room_rate_unit) {
                return [
                    'title' => $room['room_name'] ?? '',
                    'meta' => trim(($room['room_type'] ?? '') . ' - ' . number_format((float)($room['price_per_hour'] ?? 0), 2) . ' ' . $room_rate_unit),
                ];
            }, $rooms),
        ];
    };

    $build_product_section = static function ($products) use ($message, $product_rate_unit) {
        return [
            'title' => support_chatbot_text_v2('recommended_products', $message),
            'items' => array_map(static function ($product) use ($product_rate_unit) {
                return [
                    'title' => $product['product_name'] ?? '',
                    'meta' => trim(($product['category'] ?? '') . ' - ' . number_format((float)($product['price'] ?? 0), 2) . ' ' . $product_rate_unit),
                ];
            }, $products),
        ];
    };

    $build_time_section = static function ($times) use ($message, $bookings_count_label) {
        return [
            'title' => support_chatbot_text_v2('popular_times', $message),
            'items' => array_map(static function ($time) use ($bookings_count_label) {
                return [
                    'title' => $time['time'] ?? '',
                    'meta' => trim((string)($time['count'] ?? 0) . ' ' . $bookings_count_label),
                ];
            }, $times),
        ];
    };

    $actions = [
        'book' => ['label' => support_chatbot_text_v2('action_book', $message), 'url' => site_url('user/room_booking.php#booking-form')],
        'complaint' => ['label' => support_chatbot_text_v2('action_complaint', $message), 'url' => site_url('user/complaints.php')],
        'store' => ['label' => support_chatbot_text_v2('action_store', $message), 'url' => site_url('user/store.php')],
        'contact' => ['label' => support_chatbot_text_v2('action_contact', $message), 'url' => site_url('general/contact.php')],
        'login' => ['label' => support_chatbot_text_v2('action_login', $message), 'url' => site_url('general/login.php')],
        'bookings' => ['label' => support_chatbot_text_v2('action_bookings', $message), 'url' => site_url('user/my_bookings.php')],
    ];

    $answer = support_chatbot_text_v2('chat_default', $message);
    $sections = [];
    $response_actions = [$actions['book'], $actions['store'], $actions['contact']];

    if ($intent === 'booking') {
        $rooms = get_smart_room_recommendations($conn, $user_id, 3);
        $times = get_popular_booking_times($conn, 3);
        $answer = support_chatbot_text_v2('chat_booking', $message);
        $sections = [$build_room_section($rooms), $build_time_section($times)];
        $response_actions = [$actions['book'], $actions['bookings'], $actions['contact']];
    } elseif ($intent === 'payment') {
        $answer = support_chatbot_text_v2('chat_payment', $message);
        $response_actions = [$actions['bookings'], $actions['book'], $actions['contact']];
    } elseif ($intent === 'complaint') {
        $answer = support_chatbot_text_v2('chat_complaint', $message);
        $response_actions = [$actions['complaint'], $actions['contact']];
    } elseif ($intent === 'store') {
        $products = get_smart_store_recommendations($conn, $user_id, 3);
        $answer = support_chatbot_text_v2('chat_store', $message);
        $sections = [$build_product_section($products)];
        $response_actions = [$actions['store'], $actions['contact']];
    } elseif ($intent === 'recommendation') {
        $rooms = get_smart_room_recommendations($conn, $user_id, 3);
        $products = get_smart_store_recommendations($conn, $user_id, 3);
        $times = get_popular_booking_times($conn, 3);
        $answer = support_chatbot_text_v2('chat_recommendation', $message);
        $sections = [$build_room_section($rooms), $build_product_section($products), $build_time_section($times)];
        $response_actions = [$actions['book'], $actions['store'], $actions['bookings']];
    } elseif ($intent === 'location') {
        $answer = support_chatbot_text_v2('chat_location', $message);
        $response_actions = [$actions['contact'], $actions['book']];
    } elseif ($intent === 'hours') {
        $times = get_popular_booking_times($conn, 3);
        $answer = support_chatbot_text_v2('chat_hours', $message);
        $sections = [$build_time_section($times)];
        $response_actions = [$actions['book'], $actions['contact']];
    } elseif ($intent === 'contact') {
        $answer = support_chatbot_text_v2('chat_contact', $message);
        $response_actions = [$actions['contact'], $actions['complaint']];
    } elseif ($intent === 'account') {
        $answer = support_chatbot_text_v2('chat_account', $message);
        $response_actions = [$actions['login'], $actions['bookings']];
    }

    return [
        'intent' => $intent,
        'answer' => $answer,
        'sections' => array_values(array_filter($sections, static function ($section) {
            return !empty($section['items']);
        })),
        'actions' => $response_actions,
    ];
}

?>
