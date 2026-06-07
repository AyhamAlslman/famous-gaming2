const db = require('../config/db');

async function findByIdentifier(identifier) {
  const result = await db.query(
    'SELECT * FROM admins WHERE username = $1 OR email = $1 LIMIT 1',
    [identifier]
  );
  return result.rows[0] || null;
}

async function dashboardStats() {
  const result = await db.query(`
    SELECT
      (SELECT COUNT(*)::int FROM rooms) AS total_rooms,
      (SELECT COUNT(*)::int FROM bookings) AS total_bookings,
      (SELECT COUNT(*)::int FROM bookings WHERE status = 'Pending') AS pending_bookings,
      (SELECT COUNT(*)::int FROM complaints) AS total_complaints,
      (SELECT COUNT(*)::int FROM admins) AS total_admins,
      (SELECT COUNT(*)::int FROM site_users) AS total_users,
      (SELECT COUNT(*)::int FROM menu_items) AS menu_items,
      (SELECT COUNT(*)::int FROM store_products) AS store_products,
      (SELECT COUNT(*)::int FROM store_orders) AS store_orders,
      (SELECT COALESCE(SUM(paid_amount), 0)::numeric FROM bookings WHERE payment_status = 'Paid') AS booking_revenue,
      (SELECT COALESCE(SUM(paid_amount), 0)::numeric FROM store_orders WHERE payment_status = 'Paid') AS store_revenue
  `);
  return result.rows[0];
}

async function recentBookings(limit = 12) {
  const result = await db.query(
    `SELECT b.*, r.room_name
     FROM bookings b
     LEFT JOIN rooms r ON r.id = b.room_id
     ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC
     LIMIT $1`,
    [limit]
  );
  return result.rows;
}

async function recentComplaints(limit = 12) {
  const result = await db.query('SELECT * FROM complaints ORDER BY created_at DESC, id DESC LIMIT $1', [limit]);
  return result.rows;
}

async function rooms() {
  const result = await db.query('SELECT * FROM rooms ORDER BY id DESC');
  return result.rows;
}

async function bookings() {
  const result = await db.query(
    `SELECT b.*, r.room_name, r.room_type
     FROM bookings b
     LEFT JOIN rooms r ON r.id = b.room_id
     ORDER BY b.id DESC`
  );
  return result.rows;
}

async function menuItems() {
  const result = await db.query('SELECT * FROM menu_items ORDER BY item_category, item_name');
  return result.rows;
}

async function employees() {
  const result = await db.query('SELECT id, username, full_name, role, phone, email, status, created_at FROM admins ORDER BY id DESC');
  return result.rows;
}

async function storeProducts() {
  const result = await db.query('SELECT * FROM store_products ORDER BY created_at DESC, id DESC');
  return result.rows;
}

async function storeOrders() {
  const result = await db.query(
    `SELECT so.*, su.full_name AS user_full_name, su.email AS user_email
     FROM store_orders so
     LEFT JOIN site_users su ON su.id = so.user_id
     ORDER BY so.created_at DESC, so.id DESC`
  );
  return result.rows;
}

async function complaints() {
  const result = await db.query(
    `SELECT c.*, su.full_name AS site_user_name, a.full_name AS replied_by_name
     FROM complaints c
     LEFT JOIN site_users su ON su.id = c.user_id
     LEFT JOIN admins a ON a.id = c.replied_by_admin_id
     ORDER BY c.updated_at DESC, c.created_at DESC, c.id DESC`
  );
  return result.rows;
}

async function customerTickets() {
  const result = await db.query(
    `SELECT b.*, r.room_name, r.room_type
     FROM bookings b
     LEFT JOIN rooms r ON r.id = b.room_id
     WHERE b.booking_code IS NOT NULL OR b.customer_session_token IS NOT NULL
     ORDER BY b.created_at DESC, b.id DESC`
  );
  return result.rows;
}

async function bookingDetails(id) {
  const booking = await db.query(
    `SELECT b.*, r.room_name, r.room_type, r.price_per_hour
     FROM bookings b
     LEFT JOIN rooms r ON r.id = b.room_id
     WHERE b.id = $1
     LIMIT 1`,
    [id]
  );
  const items = await db.query(
    `SELECT bi.*, mi.item_name, mi.item_category
     FROM booking_items bi
     LEFT JOIN menu_items mi ON mi.id = bi.menu_item_id
     WHERE bi.booking_id = $1
     ORDER BY bi.id ASC`,
    [id]
  );
  return { booking: booking.rows[0] || null, items: items.rows };
}

async function logAction(adminId, action, tableName, recordId, ipAddress) {
  await db.query(
    `INSERT INTO audit_log (admin_id, action, table_name, record_id, ip_address)
     VALUES ($1, $2, $3, $4, $5)`,
    [adminId || null, action, tableName, recordId || null, ipAddress || null]
  );
}

module.exports = {
  findByIdentifier,
  dashboardStats,
  recentBookings,
  recentComplaints,
  rooms,
  bookings,
  menuItems,
  employees,
  storeProducts,
  storeOrders,
  complaints,
  customerTickets,
  bookingDetails,
  logAction
};
