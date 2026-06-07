const db = require('../config/db');

async function getSettings() {
  const result = await db.query('SELECT setting_key, setting_value FROM system_settings');
  return Object.fromEntries(result.rows.map((row) => [row.setting_key, row.setting_value]));
}

async function loyaltySettings() {
  const settings = await getSettings();
  return {
    earnPerJod: Number(settings.loyalty_points_per_jod || 1),
    redeemPointsPerJod: Number(settings.loyalty_points_per_jod_discount || 10),
    minBookingHours: Number(settings.min_booking_hours || 1),
    maxBookingHours: Number(settings.max_booking_hours || 12)
  };
}

module.exports = {
  getSettings,
  loyaltySettings
};
