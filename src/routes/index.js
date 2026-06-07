const express = require('express');
const publicController = require('../controllers/publicController');
const authController = require('../controllers/authController');
const bookingController = require('../controllers/bookingController');
const storeController = require('../controllers/storeController');
const complaintController = require('../controllers/complaintController');
const adminController = require('../controllers/adminController');
const supportController = require('../controllers/supportController');
const userController = require('../controllers/userController');
const { requireUser, requireAdmin } = require('../middleware/auth');

const router = express.Router();

router.get('/', publicController.home);
router.get('/services', publicController.services);
router.get('/services/gaming', publicController.serviceGaming);
router.get('/services/hospitality', publicController.serviceHospitality);
router.get('/services/events', publicController.serviceEvents);
router.get('/about', publicController.about);
router.get('/contact', publicController.contact);
router.get('/menu', publicController.menu);
router.get('/forgot-password', publicController.forgotPassword);
router.get('/reset-password', publicController.resetPassword);

router.get('/login', authController.showLogin);
router.post('/login', authController.login);
router.get('/register', authController.showRegister);
router.post('/register', authController.register);
router.get('/logout', authController.logout);

router.get('/dashboard', requireUser, userController.dashboard);
router.post('/dashboard', requireUser, userController.updateProfile);
router.get('/profile', requireUser, userController.profile);
router.post('/profile', requireUser, userController.updateProfile);
router.get('/notifications', requireUser, userController.notifications);
router.post('/notifications', requireUser, userController.notificationAction);
router.get('/booking', requireUser, bookingController.showForm);
router.post('/booking', requireUser, bookingController.create);
router.get(['/available-slots', '/api/available-slots'], bookingController.availableSlots);
router.get('/my-bookings', requireUser, bookingController.myBookings);
router.post('/my-bookings/cancel', requireUser, bookingController.cancel);
router.get('/payment', requireUser, bookingController.showPayment);
router.post('/payment', requireUser, bookingController.pay);

router.get('/store', storeController.index);
router.get('/store/checkout', requireUser, storeController.checkoutReview);
router.post('/store/checkout', requireUser, storeController.checkout);

router.get('/complaints', complaintController.index);
router.post('/complaints', complaintController.create);
router.get('/support-chatbot', supportController.info);
router.post('/support-chatbot', supportController.message);

router.get('/visa-payment', requireUser, (req, res) => {
  res.redirect(`/payment${req.url.includes('?') ? req.url.slice(req.url.indexOf('?')) : ''}`);
});

router.get(['/admin', '/admin/dashboard'], requireAdmin, adminController.dashboard);
router.get('/admin/bookings/:id', requireAdmin, adminController.bookingDetails);
router.get('/admin/booking-details', requireAdmin, adminController.bookingDetails);
router.get('/admin/rooms', requireAdmin, (req, res, next) => { req.params.page = 'rooms'; adminController.listPage(req, res, next); });
router.get('/admin/bookings', requireAdmin, (req, res, next) => { req.params.page = 'bookings'; adminController.listPage(req, res, next); });
router.get('/admin/menu', requireAdmin, (req, res, next) => { req.params.page = 'menu'; adminController.listPage(req, res, next); });
router.get('/admin/employees', requireAdmin, (req, res, next) => { req.params.page = 'employees'; adminController.listPage(req, res, next); });
router.get('/admin/store-products', requireAdmin, (req, res, next) => { req.params.page = 'storeProducts'; adminController.listPage(req, res, next); });
router.get('/admin/store-orders', requireAdmin, (req, res, next) => { req.params.page = 'storeOrders'; adminController.listPage(req, res, next); });
router.get('/admin/complaints', requireAdmin, (req, res, next) => { req.params.page = 'complaints'; adminController.listPage(req, res, next); });
router.get('/admin/customer-tickets', requireAdmin, (req, res, next) => { req.params.page = 'customerTickets'; adminController.listPage(req, res, next); });
router.get('/admin/notifications', requireAdmin, (req, res, next) => { req.params.page = 'notifications'; adminController.listPage(req, res, next); });

module.exports = router;
