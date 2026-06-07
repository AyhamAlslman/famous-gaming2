const db = require('../config/db');

async function findByEmail(email) {
  const result = await db.query('SELECT * FROM site_users WHERE email = $1 LIMIT 1', [email]);
  return result.rows[0] || null;
}

async function findById(id) {
  const result = await db.query('SELECT * FROM site_users WHERE id = $1 LIMIT 1', [id]);
  return result.rows[0] || null;
}

async function create({ fullName, email, phone, passwordHash }) {
  const result = await db.query(
    `INSERT INTO site_users (full_name, email, phone, password, role)
     VALUES ($1, $2, $3, $4, 'user')
     RETURNING id, full_name, email, phone, role, loyalty_points, status`,
    [fullName, email, phone, passwordHash]
  );
  return result.rows[0];
}

async function updateLoyaltyPoints(userId, points) {
  if (!userId || !points) return;
  await db.query('UPDATE site_users SET loyalty_points = loyalty_points + $1 WHERE id = $2', [points, userId]);
}

async function updateProfile(userId, { fullName, email, phone, passwordHash = null }) {
  const params = [fullName, email, phone, userId];
  let sql = 'UPDATE site_users SET full_name = $1, email = $2, phone = $3 WHERE id = $4 RETURNING id, full_name, email, phone, role, loyalty_points, status';

  if (passwordHash) {
    params.splice(3, 0, passwordHash);
    sql = 'UPDATE site_users SET full_name = $1, email = $2, phone = $3, password = $4 WHERE id = $5 RETURNING id, full_name, email, phone, role, loyalty_points, status';
  }

  const result = await db.query(sql, params);
  return result.rows[0] || null;
}

module.exports = {
  findByEmail,
  findById,
  create,
  updateLoyaltyPoints,
  updateProfile
};
