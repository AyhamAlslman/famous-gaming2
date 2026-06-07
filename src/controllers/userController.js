const bcrypt = require('bcryptjs');
const userModel = require('../models/userModel');
const notificationModel = require('../models/notificationModel');

async function dashboard(req, res, next) {
  try {
    const rooms = await require('../models/roomModel').homeRooms();
    res.render('user/dashboard', {
      title: 'User Dashboard',
      rooms
    });
  } catch (error) {
    next(error);
  }
}

function profile(req, res) {
  res.render('user/profile', { title: 'Profile' });
}

async function updateProfile(req, res, next) {
  try {
    const fullName = String(req.body.full_name || '').trim();
    const email = String(req.body.email || '').trim().toLowerCase();
    const phone = String(req.body.phone || '').trim();
    const password = String(req.body.password || '');
    const confirmPassword = String(req.body.confirm_password || '');

    if (!fullName || !email) {
      req.flash('error', 'Name and email are required.');
      return res.redirect('/profile');
    }

    if (password && password !== confirmPassword) {
      req.flash('error', 'Passwords do not match.');
      return res.redirect('/profile');
    }

    const existing = await userModel.findByEmail(email);
    if (existing && existing.id !== req.session.user.id) {
      req.flash('error', 'This email is already used by another account.');
      return res.redirect('/profile');
    }

    const passwordHash = password ? await bcrypt.hash(password, 12) : null;
    const updated = await userModel.updateProfile(req.session.user.id, { fullName, email, phone, passwordHash });
    req.session.user = {
      id: updated.id,
      full_name: updated.full_name,
      email: updated.email,
      phone: updated.phone,
      role: updated.role,
      loyalty_points: updated.loyalty_points
    };
    req.flash('success', 'Profile updated.');
    res.redirect('/profile');
  } catch (error) {
    next(error);
  }
}

async function notifications(req, res, next) {
  try {
    const notifications = await notificationModel.siteNotifications(req.session.user.id);
    const unreadCount = notifications.filter((item) => !item.is_read).length;
    res.render('user/notifications', {
      title: 'Notifications',
      notifications,
      unreadCount
    });
  } catch (error) {
    next(error);
  }
}

async function notificationAction(req, res, next) {
  try {
    if (req.body.action === 'mark_all_read') {
      await notificationModel.markAllSiteRead(req.session.user.id);
    } else if (req.body.action === 'mark_read' && req.body.notification_id) {
      await notificationModel.markSiteRead(req.session.user.id, req.body.notification_id);
    }
    res.redirect('/notifications');
  } catch (error) {
    next(error);
  }
}

module.exports = {
  dashboard,
  profile,
  updateProfile,
  notifications,
  notificationAction
};
