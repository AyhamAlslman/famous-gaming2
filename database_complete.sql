-- =====================================================
-- PlayStation PlayRoom - Complete Database Setup
-- This file contains the complete database schema with all enhancements
-- Run this for fresh installations
-- =====================================================

CREATE DATABASE IF NOT EXISTS playroom_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE playroom_db;

-- =====================================================
-- DROP EXISTING TABLES (if any)
-- =====================================================
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS time_slots;
DROP TABLE IF EXISTS business_hours;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS store_products;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS admins;

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Admins/Employees Table
CREATE TABLE admins (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rooms Table
CREATE TABLE rooms (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookings Table
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    room_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    hours INT NOT NULL,
    end_time TIME GENERATED ALWAYS AS (ADDTIME(start_time, SEC_TO_TIME(hours * 3600))) STORED,
    total_price DECIMAL(10,2) NOT NULL,
    additional_items_total DECIMAL(10,2) DEFAULT 0.00,
    final_total DECIMAL(10,2) GENERATED ALWAYS AS (total_price + IFNULL(additional_items_total, 0)) STORED,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    payment_status VARCHAR(20) DEFAULT 'Unpaid',
    payment_method VARCHAR(20) DEFAULT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    INDEX idx_booking_datetime (room_id, booking_date, start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additional Menu Items/Services Table
CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    item_category VARCHAR(50) NOT NULL,
    item_price DECIMAL(10,2) NOT NULL,
    item_description TEXT,
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Booking Additional Items (Order Items)
CREATE TABLE booking_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    item_price DECIMAL(10,2) NOT NULL,
    item_total DECIMAL(10,2) GENERATED ALWAYS AS (quantity * item_price) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Store Products Table
CREATE TABLE store_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    image_path VARCHAR(255) DEFAULT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Complaints Table
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CONFIGURATION TABLES
-- =====================================================

-- Business Hours Table
CREATE TABLE business_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL UNIQUE,
    is_open BOOLEAN NOT NULL DEFAULT TRUE,
    opening_time TIME NOT NULL DEFAULT '09:00:00',
    closing_time TIME NOT NULL DEFAULT '23:59:00',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Time Slots Table (now room-specific)
CREATE TABLE time_slots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT DEFAULT NULL,
    slot_time TIME NOT NULL,
    slot_label VARCHAR(20) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_slot (room_id, slot_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Settings Table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type VARCHAR(20) NOT NULL DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Log Table
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Default Admin Accounts (passwords will be hashed by update_passwords.php)
INSERT INTO admins (username, password, full_name, role, phone, email, status) VALUES
('admin', 'admin123', 'System Administrator', 'admin', '0791234567', 'admin@famousgaming.jo', 'Active'),
('employee1', 'emp123', 'Ahmed Al-Khatib', 'employee', '0799876543', 'ahmed@famousgaming.jo', 'Active'),
('employee2', 'emp123', 'Khaled Al-Majali', 'employee', '0797654321', 'khaled@famousgaming.jo', 'Active'),
('employee3', 'emp123', 'Fahad Al-Tarawneh', 'employee', '0793334444', 'fahad@famousgaming.jo', 'Inactive');

-- Default Rooms
INSERT INTO rooms (room_name, room_type, price_per_hour, status, services, description) VALUES
('Room 1', 'PS5', 3.00, 'Available', '4K Screen, High-speed Internet, Free Drinks, Air Conditioning', 'Modern PS5 room with 4K display'),
('Room 2', 'PS5', 3.00, 'Available', '4K Screen, High-speed Internet, Free Drinks, Air Conditioning', 'PS5 gaming room with fast internet'),
('Room 3', 'PS4', 2.00, 'Available', 'HD Screen, Internet, Drinks, Air Conditioning', 'Classic PS4 gaming room'),
('Room 4', 'PS4', 2.00, 'Busy', 'HD Screen, Internet, Drinks, Air Conditioning', 'Comfortable PS4 room for families'),
('VIP Room', 'PS5', 3.00, 'Available', 'Large 4K Screen, Ultra-fast Internet, VIP Service, AC, Comfortable Chairs', 'Luxury VIP room with all amenities'),
('Room 5', 'PS5', 3.00, 'Available', '4K Screen, Professional Headset, High-speed Internet, Drinks', 'PS5 room with professional audio');

-- Sample Menu Items (Snacks, Drinks, Extra Services)
INSERT INTO menu_items (item_name, item_category, item_price, item_description, is_available) VALUES
-- Drinks
('Coca Cola', 'Drinks', 0.50, 'Cold soft drink 330ml', TRUE),
('Pepsi', 'Drinks', 0.50, 'Cold soft drink 330ml', TRUE),
('Red Bull', 'Drinks', 1.00, 'Energy drink 250ml', TRUE),
('Water Bottle', 'Drinks', 0.35, 'Mineral water 500ml', TRUE),
('Fresh Orange Juice', 'Drinks', 1.50, 'Freshly squeezed orange juice', TRUE),
('Coffee', 'Drinks', 1.00, 'Hot coffee', TRUE),
-- Snacks
('Chips', 'Snacks', 0.50, 'Potato chips', TRUE),
('Chocolate Bar', 'Snacks', 0.50, 'Assorted chocolate bars', TRUE),
('Popcorn', 'Snacks', 1.00, 'Fresh popcorn', TRUE),
('Sandwich', 'Snacks', 2.50, 'Chicken or cheese sandwich', TRUE),
('Pizza Slice', 'Snacks', 2.00, 'Large pizza slice', TRUE),
-- Extra Services
('Extra Controller', 'Services', 1.00, 'Additional PS controller for multiplayer', TRUE),
('VR Headset', 'Services', 4.00, 'PlayStation VR headset rental', TRUE),
('Gaming Headset', 'Services', 1.00, 'Professional gaming headset', TRUE),
('Extended Time', 'Services', 3.00, 'Extra 1 hour gaming time', TRUE);

-- Sample Store Products
INSERT INTO store_products (product_name, category, price, description, image_path, stock_quantity, status) VALUES
('PlayStation 5 Slim Console', 'PlayStation Consoles', 289.00, 'Current generation console bundle for premium home gaming.', 'images/store/ps5-slim-console.svg', 4, 'Active'),
('PlayStation 4 Console', 'PlayStation Consoles', 159.00, 'Reliable PS4 system for lounge setups, tournaments, and home entertainment.', 'images/store/ps4-console.svg', 6, 'Active'),
('DualSense Wireless Controller', 'Controllers', 55.00, 'Official PS5 wireless controller with adaptive triggers and premium grip.', 'images/store/dualsense-controller.svg', 12, 'Active'),
('DualShock 4 Controller', 'Controllers', 39.00, 'Classic PS4 controller with responsive analog sticks and solid battery life.', 'images/store/dualshock-controller.svg', 9, 'Active'),
('EA Sports FC 25', 'Games / CDs', 27.00, 'Popular football title for competitive PlayStation sessions.', 'images/store/fc25-game.svg', 7, 'Active'),
('Marvel''s Spider-Man 2', 'Games / CDs', 34.00, 'Story-driven PS5 action title and one of the strongest showcase games for the console.', 'images/store/spiderman2-game.svg', 5, 'Active'),
('Silicone Controller Cover - Crimson', 'Controller Covers', 7.50, 'Protective anti-slip cover with a premium red finish for everyday gaming use.', 'images/store/silicone-cover-red.svg', 20, 'Active'),
('Silicone Controller Cover - Midnight', 'Controller Covers', 7.50, 'Soft-touch black controller skin with improved grip and scratch protection.', 'images/store/silicone-cover-black.svg', 15, 'Active'),
('Pulse 3D Wireless Headset', 'PlayStation Accessories', 69.00, 'Immersive headset tuned for PlayStation audio with a clean modern profile.', 'images/store/pulse-headset.svg', 5, 'Active'),
('Dual Controller Charging Dock', 'PlayStation Accessories', 24.00, 'Compact dock that charges two PlayStation controllers at the same time.', 'images/store/charging-dock.svg', 8, 'Active');

-- Sample Bookings
INSERT INTO bookings (customer_name, phone, room_id, booking_date, start_time, hours, total_price, status, payment_status, payment_method, notes) VALUES
('Ahmed Al-Nsour', '0791234567', 1, '2025-12-31', '14:00:00', 2, 6.00, 'Confirmed', 'Paid', 'Cash', 'FIFA tournament booking'),
('Khaled Al-Zoubi', '0797654321', 2, '2025-12-31', '16:00:00', 3, 9.00, 'Pending', 'Unpaid', NULL, 'Waiting for confirmation'),
('Saad Al-Bakhit', '0799876543', 4, '2025-12-31', '10:00:00', 4, 8.00, 'Confirmed', 'Paid', 'CliQ', 'Family booking'),
('Mohammed Al-Hmoud', '0795556666', 5, '2026-01-01', '18:00:00', 5, 15.00, 'Confirmed', 'Paid', 'Visa', 'VIP special event'),
('Fahad Al-Rawashdeh', '0792223333', 3, '2026-01-02', '12:00:00', 3, 6.00, 'Pending', 'Unpaid', NULL, 'Kids gaming session');

-- Sample Complaints
INSERT INTO complaints (customer_name, phone, message) VALUES
('Mohammed Al-Adwan', '0791112233', 'Excellent service, hope to see more modern games'),
('Fahad Al-Fayez', NULL, 'Suggestion: Add food delivery service from nearby restaurants'),
('Nawaf Al-Habashneh', '0793334444', 'Very clean room and fast service, thank you'),
('Abdullah Al-Khawaldeh', '0799998888', 'Suggestion: Weekly gaming tournaments'),
('Sultan Al-Momani', '0797776666', 'Room 3 air conditioning is a bit weak');

-- Business Hours (9 AM - 12 AM for all days)
INSERT INTO business_hours (day_of_week, is_open, opening_time, closing_time) VALUES
('Monday', TRUE, '09:00:00', '23:59:00'),
('Tuesday', TRUE, '09:00:00', '23:59:00'),
('Wednesday', TRUE, '09:00:00', '23:59:00'),
('Thursday', TRUE, '09:00:00', '23:59:00'),
('Friday', TRUE, '09:00:00', '23:59:00'),
('Saturday', TRUE, '09:00:00', '23:59:00'),
('Sunday', TRUE, '09:00:00', '23:59:00');

-- Time Slots (1-hour intervals from 9 AM to 11 PM)
-- NULL room_id means global slots (apply to all rooms)
INSERT INTO time_slots (room_id, slot_time, slot_label) VALUES
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
(NULL, '23:00:00', '11:00 PM');

-- System Settings (only implemented settings included)
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('max_booking_hours', '12', 'integer', 'Maximum hours allowed per booking'),
('min_booking_hours', '1', 'integer', 'Minimum hours allowed per booking');

-- =====================================================
-- SETUP COMPLETE
-- =====================================================
-- Next Steps:
-- 1. Run update_passwords.php to hash admin passwords
-- 2. Delete update_passwords.php after use
-- 3. Upload room images via admin panel
-- 4. Change default passwords immediately
-- =====================================================
