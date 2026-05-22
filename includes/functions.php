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
function ensure_user_auth_schema($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS site_users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(20) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        loyalty_points INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_site_users_role_status (role, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensure_booking_confirmation_schema($conn);
    ensure_booking_items_table($conn);
    ensure_store_products_schema($conn);
    ensure_store_orders_schema($conn);
    ensure_complaints_schema($conn);
    ensure_site_notifications_table($conn);
}

/**
 * Get the currently logged in site user, if any.
 */
function get_current_site_user($conn) {
    ensure_user_auth_schema($conn);

    $user_id = isset($_SESSION['site_user_id']) ? (int)$_SESSION['site_user_id'] : 0;
    if ($user_id <= 0) {
        return null;
    }

    $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, phone, role, loyalty_points, status FROM site_users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user || $user['status'] !== 'Active') {
        unset($_SESSION['site_user_id'], $_SESSION['site_user_name'], $_SESSION['site_user_role']);
        return null;
    }

    $_SESSION['site_user_name'] = $user['full_name'];
    $_SESSION['site_user_loyalty_points'] = (int)$user['loyalty_points'];

    return $user;
}

function calculate_loyalty_points($amount) {
    $amount = (float)$amount;
    return $amount > 0 ? (int)floor($amount) : 0;
}

function loyalty_points_to_amount($points) {
    return round(max(0, (int)$points) / 10, 2);
}

function loyalty_amount_to_points($amount) {
    return (int)ceil(max(0, (float)$amount) * 10);
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
    $max_points_for_discount = loyalty_amount_to_points($max_discount);
    $points_to_redeem = min($requested_points, $available_points, $max_points_for_discount);
    $discount = min($max_discount, loyalty_points_to_amount($points_to_redeem));

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

    $points = calculate_loyalty_points($amount);

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

    $points = calculate_loyalty_points($amount);
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

    return $success;
}

/**
 * Count unread internal admin notifications.
 */
function count_unread_admin_notifications($conn) {
    ensure_admin_notifications_table($conn);

    $result = mysqli_query($conn, "SELECT COUNT(*) AS unread_count FROM admin_notifications WHERE is_read = 0");
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        return (int)$row['unread_count'];
    }

    return 0;
}

/**
 * Fetch recent internal admin notifications.
 */
function get_recent_admin_notifications($conn, $limit = 6) {
    ensure_admin_notifications_table($conn);
    $limit = max(1, min(20, (int)$limit));
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

    return $success;
}

/**
 * Mark all notifications as read.
 */
function mark_all_admin_notifications_read($conn) {
    ensure_admin_notifications_table($conn);
    return mysqli_query($conn, "UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
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

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS unread_count FROM site_notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ? (int)$row['unread_count'] : 0;
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

    return $notifications;
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
