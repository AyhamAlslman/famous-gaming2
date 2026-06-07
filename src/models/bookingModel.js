const db = require('../config/db');
const { generateBookingCode, generateToken } = require('../utils/format');

async function conflicts(roomId, bookingDate, startTime, hours, excludeBookingId = null) {
  const params = [roomId, bookingDate, startTime, Number(hours)];
  let extra = '';

  if (excludeBookingId) {
    params.push(excludeBookingId);
    extra = `AND id <> $5`;
  }

  const result = await db.query(
    `SELECT id, customer_name, start_time, hours
     FROM bookings
     WHERE room_id = $1
       AND booking_date = $2
       AND status IN ('Pending', 'Confirmed')
       ${extra}
       AND tsrange(
         ($2::date + start_time)::timestamp,
         (($2::date + start_time)::timestamp + (hours * interval '1 hour')),
         '[)'
       ) && tsrange(
         ($2::date + $3::time)::timestamp,
         (($2::date + $3::time)::timestamp + ($4 * interval '1 hour')),
         '[)'
       )
     ORDER BY start_time ASC`,
    params
  );
  return result.rows;
}

async function availableSlots(roomId, bookingDate, hours) {
  const result = await db.query(
    `SELECT ts.slot_time, ts.slot_label
     FROM time_slots ts
     WHERE ts.is_active = true
       AND (ts.room_id = $1 OR ts.room_id IS NULL)
     ORDER BY ts.slot_time ASC`,
    [roomId || null]
  );

  const slots = [];
  for (const slot of result.rows) {
    const slotConflicts = await conflicts(roomId, bookingDate, slot.slot_time, hours || 1);
    if (slotConflicts.length === 0) {
      slots.push(slot);
    }
  }
  return slots;
}

async function createBooking(payload, menuItems = []) {
  return db.transaction(async (client) => {
    const bookingCode = generateBookingCode();
    const sessionToken = payload.customerSessionToken || generateToken();
    const ticketToken = generateToken();
    const insert = await client.query(
      `INSERT INTO bookings (
        booking_code, customer_name, phone, email, customer_session_token, ticket_token, user_id,
        room_id, booking_date, start_time, hours, total_price, additional_items_total,
        status, payment_method, notes
      )
      VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, 'Confirmed', $14, $15)
      RETURNING *`,
      [
        bookingCode,
        payload.customerName,
        payload.phone,
        payload.email || null,
        sessionToken,
        ticketToken,
        payload.userId || null,
        payload.roomId,
        payload.bookingDate,
        payload.startTime,
        payload.hours,
        payload.totalPrice,
        payload.addonsTotal || 0,
        payload.paymentMethod || 'Cash',
        payload.notes || null
      ]
    );

    const booking = insert.rows[0];
    for (const item of menuItems) {
      await client.query(
        `INSERT INTO booking_items (booking_id, menu_item_id, quantity, item_price)
         VALUES ($1, $2, $3, $4)`,
        [booking.id, item.id, item.quantity, item.price]
      );
    }

    return booking;
  });
}

async function findForUser(bookingId, sessionToken, userId = 0) {
  const result = await db.query(
    `SELECT b.*, r.room_name, r.room_type
     FROM bookings b
     LEFT JOIN rooms r ON r.id = b.room_id
     WHERE b.id = $1 AND (b.customer_session_token = $2 OR b.user_id = $3)
     LIMIT 1`,
    [bookingId, sessionToken || '', userId || 0]
  );
  return result.rows[0] || null;
}

async function listForUser(sessionToken, userId = 0) {
  const result = await db.query(
    `SELECT b.*, r.room_name, r.room_type
     FROM bookings b
     LEFT JOIN rooms r ON r.id = b.room_id
     WHERE b.customer_session_token = $1 OR b.user_id = $2
     ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC`,
    [sessionToken || '', userId || 0]
  );
  return result.rows;
}

async function items(bookingId) {
  const result = await db.query(
    `SELECT bi.*, COALESCE(mi.item_name, 'Item') AS item_name
     FROM booking_items bi
     LEFT JOIN menu_items mi ON mi.id = bi.menu_item_id
     WHERE bi.booking_id = $1
     ORDER BY bi.id ASC`,
    [bookingId]
  );
  return result.rows;
}

async function cancel(bookingId, sessionToken, userId = 0) {
  const result = await db.query(
    `UPDATE bookings
     SET status = 'Cancelled'
     WHERE id = $1 AND status = 'Confirmed' AND (customer_session_token = $2 OR user_id = $3)
     RETURNING *`,
    [bookingId, sessionToken || '', userId || 0]
  );
  return result.rows[0] || null;
}

async function markPayment(bookingId, sessionToken, userId, paymentStatus, paymentMethod, paidAmount) {
  const result = await db.query(
    `UPDATE bookings
     SET payment_status = $1, payment_method = $2, paid_amount = $3
     WHERE id = $4 AND (customer_session_token = $5 OR user_id = $6)
     RETURNING *`,
    [paymentStatus, paymentMethod, paidAmount, bookingId, sessionToken || '', userId || 0]
  );
  return result.rows[0] || null;
}

async function awardLoyaltyIfNeeded(bookingId) {
  return db.transaction(async (client) => {
    const bookingResult = await client.query('SELECT * FROM bookings WHERE id = $1 FOR UPDATE', [bookingId]);
    const booking = bookingResult.rows[0];

    if (!booking || !booking.user_id || Number(booking.loyalty_points_earned || 0) > 0 || booking.payment_status !== 'Paid') {
      return 0;
    }

    const settings = await client.query(
      "SELECT setting_key, setting_value FROM system_settings WHERE setting_key = 'loyalty_points_per_jod'"
    );
    const earnPerJod = Number(settings.rows[0]?.setting_value || 1);
    const points = Math.max(0, Math.floor(Number(booking.final_total || booking.total_price || 0) * earnPerJod));

    if (points > 0) {
      await client.query('UPDATE bookings SET loyalty_points_earned = $1 WHERE id = $2', [points, bookingId]);
      await client.query('UPDATE site_users SET loyalty_points = loyalty_points + $1 WHERE id = $2', [points, booking.user_id]);
    }

    return points;
  });
}

module.exports = {
  conflicts,
  availableSlots,
  createBooking,
  findForUser,
  listForUser,
  items,
  cancel,
  markPayment,
  awardLoyaltyIfNeeded
};
