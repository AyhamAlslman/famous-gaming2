const bcrypt = require('bcryptjs');
const userModel = require('../models/userModel');
const adminModel = require('../models/adminModel');
const { safeRedirect } = require('../utils/format');

function normalizeBcryptHash(hash) {
  return String(hash || '').replace(/^\$2y\$/, '$2a$');
}

async function verifyPassword(password, hash) {
  if (!hash) return false;
  if (hash === password) return true;
  return bcrypt.compare(password, normalizeBcryptHash(hash));
}

function showLogin(req, res) {
  res.render('auth/login', {
    title: 'Login - FAMOUS GAMING',
    redirect: safeRedirect(req.query.redirect || req.session.postLoginRedirect || '/dashboard', '/dashboard')
  });
}

async function login(req, res, next) {
  try {
    const identifier = String(req.body.login_identifier || '').trim();
    const password = String(req.body.password || '');
    const redirect = safeRedirect(req.body.redirect || req.query.redirect || req.session.postLoginRedirect, '/dashboard');

    if (!identifier || !password) {
      req.flash('error', 'Email/username and password are required.');
      return res.redirect(`/login?redirect=${encodeURIComponent(redirect)}`);
    }

    const admin = await adminModel.findByIdentifier(identifier);
    if (admin && admin.role !== 'employee' && admin.status === 'Active' && await verifyPassword(password, admin.password)) {
      req.session.admin = {
        id: admin.id,
        username: admin.username,
        fullName: admin.full_name,
        role: admin.role
      };
      await adminModel.logAction(admin.id, 'LOGIN', 'admins', admin.id, req.ip);
      return res.redirect(redirect.startsWith('/admin') ? redirect : '/admin/dashboard');
    }

    const user = await userModel.findByEmail(identifier);
    if (!user || user.status !== 'Active' || !await verifyPassword(password, user.password)) {
      req.flash('error', 'Invalid login details.');
      return res.redirect(`/login?redirect=${encodeURIComponent(redirect)}`);
    }

    req.session.user = {
      id: user.id,
      full_name: user.full_name,
      email: user.email,
      phone: user.phone,
      role: user.role,
      loyalty_points: user.loyalty_points
    };
    req.session.customerBookingToken = req.session.customerBookingToken || require('../utils/format').generateToken();
    delete req.session.postLoginRedirect;
    return res.redirect(redirect.startsWith('/admin') ? '/dashboard' : redirect);
  } catch (error) {
    next(error);
  }
}

function showRegister(req, res) {
  res.render('auth/register', {
    title: 'Register - FAMOUS GAMING',
    redirect: safeRedirect(req.query.redirect || '/dashboard', '/dashboard')
  });
}

async function register(req, res, next) {
  try {
    const fullName = String(req.body.full_name || '').trim();
    const email = String(req.body.email || '').trim().toLowerCase();
    const phone = String(req.body.phone || '').trim();
    const password = String(req.body.password || '');
    const redirect = safeRedirect(req.body.redirect || '/dashboard');

    if (!fullName || !email || !password) {
      req.flash('error', 'Name, email, and password are required.');
      return res.redirect('/register');
    }

    if (await userModel.findByEmail(email)) {
      req.flash('error', 'This email is already registered.');
      return res.redirect('/register');
    }

    const passwordHash = await bcrypt.hash(password, 12);
    const user = await userModel.create({ fullName, email, phone, passwordHash });
    req.session.user = {
      id: user.id,
      full_name: user.full_name,
      email: user.email,
      phone: user.phone,
      role: user.role,
      loyalty_points: user.loyalty_points
    };
    req.session.customerBookingToken = req.session.customerBookingToken || require('../utils/format').generateToken();
    req.flash('success', 'Account created successfully.');
    return res.redirect(redirect);
  } catch (error) {
    next(error);
  }
}

function logout(req, res) {
  req.session.destroy(() => {
    res.redirect('/');
  });
}

module.exports = {
  showLogin,
  login,
  showRegister,
  register,
  logout
};
