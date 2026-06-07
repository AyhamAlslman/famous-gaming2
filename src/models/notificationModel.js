const db = require('../config/db');

async function createAdminNotification(type, title, message, relatedTable, relatedId, actionUrl) {
  await db.query(
    `INSERT INTO admin_notifications (notification_type, title, message, related_table, related_id, action_url)
     VALUES ($1, $2, $3, $4, $5, $6)`,
    [type, title, message, relatedTable || null, relatedId || null, actionUrl || null]
  );
}

async function createSiteNotification(userId, type, title, message, actionUrl) {
  if (!userId) return;
  await db.query(
    `INSERT INTO site_notifications (user_id, notification_type, title, message, action_url)
     VALUES ($1, $2, $3, $4, $5)`,
    [userId, type, title, message, actionUrl || null]
  );
}

async function unreadCount(userId) {
  if (!userId) return 0;
  const result = await db.query(
    'SELECT COUNT(*)::int AS count FROM site_notifications WHERE user_id = $1 AND is_read = false',
    [userId]
  );
  return result.rows[0]?.count || 0;
}

async function siteNotifications(userId, limit = 120) {
  if (!userId) return [];
  const result = await db.query(
    `SELECT *
     FROM site_notifications
     WHERE user_id = $1
     ORDER BY is_read ASC, created_at DESC, id DESC
     LIMIT $2`,
    [userId, limit]
  );
  return result.rows;
}

async function markSiteRead(userId, notificationId) {
  await db.query(
    `UPDATE site_notifications
     SET is_read = true, read_at = CURRENT_TIMESTAMP
     WHERE user_id = $1 AND id = $2`,
    [userId, notificationId]
  );
}

async function markAllSiteRead(userId) {
  await db.query(
    `UPDATE site_notifications
     SET is_read = true, read_at = CURRENT_TIMESTAMP
     WHERE user_id = $1 AND is_read = false`,
    [userId]
  );
}

async function adminNotifications(limit = 120) {
  const result = await db.query(
    `SELECT *
     FROM admin_notifications
     ORDER BY is_read ASC, created_at DESC, id DESC
     LIMIT $1`,
    [limit]
  );
  return result.rows;
}

module.exports = {
  createAdminNotification,
  createSiteNotification,
  unreadCount,
  siteNotifications,
  markSiteRead,
  markAllSiteRead,
  adminNotifications
};
