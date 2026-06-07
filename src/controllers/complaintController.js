const complaintModel = require('../models/complaintModel');
const notificationModel = require('../models/notificationModel');
const { generateToken } = require('../utils/format');

async function index(req, res, next) {
  try {
    req.session.customerComplaintToken = req.session.customerComplaintToken || generateToken();
    const complaints = await complaintModel.listForUser(req.session.user?.id, req.session.customerComplaintToken);
    res.render('complaints/index', { title: 'Complaints', complaints });
  } catch (error) {
    next(error);
  }
}

async function create(req, res, next) {
  try {
    req.session.customerComplaintToken = req.session.customerComplaintToken || generateToken();
    const customerName = req.body.customer_name || req.session.user?.full_name || 'Customer';
    const phone = req.body.phone || req.session.user?.phone || '';
    const email = req.body.customer_email || req.session.user?.email || '';
    const complaint = await complaintModel.create({
      userId: req.session.user?.id,
      sessionToken: req.session.customerComplaintToken,
      customerName,
      email,
      phone,
      message: req.body.message
    });
    await notificationModel.createAdminNotification(
      'complaint_created',
      'New complaint',
      `${customerName} submitted complaint ${complaint.complaint_code || complaint.id}.`,
      'complaints',
      complaint.id,
      `/admin/complaints?ticket_id=${complaint.id}`
    );
    req.flash('success', 'Your complaint was sent.');
    res.redirect('/complaints');
  } catch (error) {
    next(error);
  }
}

module.exports = {
  index,
  create
};
