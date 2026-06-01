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
        user_id INT NULL,
        customer_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
        $profile_image_column = mysqli_query($conn, "SHOW COLUMNS FROM site_users LIKE 'profile_image'");
        if ($profile_image_column && mysqli_num_rows($profile_image_column) === 0) {
            mysqli_query($conn, "ALTER TABLE site_users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER phone");
        }
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

/**
 * Create a new customer-facing notification.
 */
function create_site_notification($conn, $user_id, $type, $title, $message, $action_url = null) {
    ensure_site_notifications_table($conn);
    $user_id = (int)$user_id;

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

?>
