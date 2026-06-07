function requireUser(req, res, next) {
  if (req.session.user) {
    return next();
  }

  req.session.postLoginRedirect = req.originalUrl;
  req.flash('error', 'Please login first.');
  return res.redirect(`/login?redirect=${encodeURIComponent(req.originalUrl)}`);
}

function requireAdmin(req, res, next) {
  if (req.session.admin) {
    return next();
  }

  req.session.postLoginRedirect = req.originalUrl;
  req.flash('error', 'Please login as admin first.');
  return res.redirect(`/login?redirect=${encodeURIComponent(req.originalUrl)}`);
}

module.exports = {
  requireUser,
  requireAdmin
};
