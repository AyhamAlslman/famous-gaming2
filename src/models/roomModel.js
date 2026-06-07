const db = require('../config/db');

async function all() {
  const result = await db.query('SELECT * FROM rooms ORDER BY room_name ASC');
  return result.rows;
}

async function available() {
  const result = await db.query("SELECT * FROM rooms WHERE status = 'Available' ORDER BY room_name ASC");
  return result.rows;
}

async function findById(id) {
  const result = await db.query('SELECT * FROM rooms WHERE id = $1 LIMIT 1', [id]);
  return result.rows[0] || null;
}

async function homeRooms() {
  const result = await db.query(`
    SELECT
      r.*,
      CASE
        WHEN r.status = 'Busy' THEN 'Busy'
        WHEN EXISTS (
          SELECT 1
          FROM bookings active_booking
          WHERE active_booking.room_id = r.id
            AND active_booking.status IN ('Pending', 'Confirmed')
            AND active_booking.booking_date = CURRENT_DATE
            AND LOCALTIME >= active_booking.start_time
            AND LOCALTIME < active_booking.end_time
        ) THEN 'Busy'
        ELSE 'Available'
      END AS current_status
    FROM rooms r
    ORDER BY current_status ASC, r.room_name ASC
  `);
  return result.rows;
}

module.exports = {
  all,
  available,
  findById,
  homeRooms
};
