const crypto = require('crypto');

function pad(value, length = 2) {
  return String(value).padStart(length, '0');
}

function formatDate(value) {
  if (!value) return '';
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTime(value) {
  if (!value) return '';
  const raw = String(value);
  const [hourPart, minutePart = '0'] = raw.split(':');
  let hour = Number(hourPart);
  const minutes = Number(minutePart);
  if (Number.isNaN(hour)) return raw;
  const suffix = hour >= 12 ? 'PM' : 'AM';
  hour = hour % 12 || 12;
  return `${hour}:${pad(minutes)} ${suffix}`;
}

function hoursLabel(hours) {
  const count = Number(hours || 0);
  return `${count} ${count === 1 ? 'hour' : 'hours'}`;
}

function money(value) {
  return `${Number(value || 0).toFixed(2)} JOD`;
}

function bookingCode(id) {
  return `FG-${String(id).padStart(6, '0')}`;
}

function generateBookingCode() {
  return `FG-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${crypto.randomBytes(4).toString('hex').toUpperCase()}`;
}

function generateToken(bytes = 32) {
  return crypto.randomBytes(bytes).toString('hex');
}

function normalizeStatusClass(status) {
  return String(status || '').trim().toLowerCase().replace(/\s+/g, '-');
}

function safeRedirect(target, fallback = '/') {
  const value = String(target || '').trim();
  if (!value || value.startsWith('http://') || value.startsWith('https://') || value.startsWith('//')) {
    return fallback;
  }
  return value.startsWith('/') ? value : `/${value}`;
}

module.exports = {
  formatDate,
  formatTime,
  hoursLabel,
  money,
  bookingCode,
  generateBookingCode,
  generateToken,
  normalizeStatusClass,
  safeRedirect
};
