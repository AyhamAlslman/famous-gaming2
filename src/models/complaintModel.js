const db = require('../config/db');
const { generateToken } = require('../utils/format');

async function create({ userId, sessionToken, customerName, email, phone, message }) {
  const result = await db.query(
    `INSERT INTO complaints (
      complaint_code, user_id, customer_session_token, customer_name, customer_email, phone, message, status
    )
    VALUES ($1, $2, $3, $4, $5, $6, $7, 'Open')
    RETURNING *`,
    [`CP-${generateToken(4).toUpperCase()}`, userId || null, sessionToken || null, customerName, email || null, phone || null, message]
  );
  return result.rows[0];
}

async function listForUser(userId, sessionToken) {
  if (userId) {
    const result = await db.query(
      `SELECT * FROM complaints
       WHERE user_id = $1 AND closed_for_customer_at IS NULL
       ORDER BY updated_at DESC NULLS LAST, created_at DESC, id DESC
       LIMIT 30`,
      [userId]
    );
    return result.rows;
  }

  const result = await db.query(
    `SELECT * FROM complaints
     WHERE customer_session_token = $1 AND closed_for_customer_at IS NULL
     ORDER BY updated_at DESC NULLS LAST, created_at DESC, id DESC
     LIMIT 30`,
    [sessionToken || '']
  );
  return result.rows;
}

module.exports = {
  create,
  listForUser
};
