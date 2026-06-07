const format = require('../utils/format');

function viewLocals(req, res, next) {
  res.locals.currentUser = req.session.user || null;
  res.locals.currentAdmin = req.session.admin || null;
  res.locals.successMessages = req.flash('success');
  res.locals.errorMessages = req.flash('error');
  res.locals.path = req.path;
  res.locals.asset = (target) => `/${String(target || '').replace(/^\/+/, '')}`;
  res.locals.format = format;
  res.locals.old = req.body || {};
  next();
}

module.exports = viewLocals;
