const bookingModel = require('../models/bookingModel');
const roomModel = require('../models/roomModel');
const menuModel = require('../models/menuModel');
const notificationModel = require('../models/notificationModel');
const settingsModel = require('../models/settingsModel');
const { formatTime, hoursLabel, money, bookingCode, generateToken } = require('../utils/format');

async function showForm(req, res, next) {
  try {
    const [rooms, menuItems, settings] = await Promise.all([
      roomModel.available(),
      menuModel.available(['Drinks', 'Snacks']),
      settingsModel.loyaltySettings()
    ]);
    res.render('booking/form', {
      title: 'Book Now - FAMOUS GAMING',
      rooms,
      menuItems,
      settings,
      preselectedRoomId: Number(req.query.room_id || 0),
      confirmedBooking: null
    });
  } catch (error) {
    next(error);
  }
}

async function create(req, res, next) {
  try {
    const user = req.session.user;
    const roomId = Number(req.body.room_id);
    const hours = Number(req.body.hours || 1);
    const room = await roomModel.findById(roomId);

    if (!room || room.status !== 'Available') {
      req.flash('error', 'Selected room is not available.');
      return res.redirect('/booking#booking-form');
    }

    const conflicts = await bookingModel.conflicts(roomId, req.body.booking_date, req.body.start_time, hours);
    if (conflicts.length > 0) {
      req.flash('error', `This room is already booked around ${formatTime(conflicts[0].start_time)}.`);
      return res.redirect('/booking#booking-form');
    }

    const selectedItems = [];
    const selectedQuantities = req.body.menu_items || {};
    const selectedIds = Object.keys(selectedQuantities).map(Number).filter(Boolean);
    const menuRows = await menuModel.findMany(selectedIds);
    let addonsTotal = 0;

    for (const menuRow of menuRows) {
      const quantity = Math.max(0, Math.min(20, Number(selectedQuantities[menuRow.id] || 0)));
      if (quantity > 0) {
        addonsTotal += Number(menuRow.item_price) * quantity;
        selectedItems.push({ id: menuRow.id, quantity, price: menuRow.item_price });
      }
    }

    req.session.customerBookingToken = req.session.customerBookingToken || generateToken();
    const booking = await bookingModel.createBooking({
      customerName: req.body.customer_name || user.full_name,
      phone: req.body.phone || user.phone,
      email: user.email,
      customerSessionToken: req.session.customerBookingToken,
      userId: user.id,
      roomId,
      bookingDate: req.body.booking_date,
      startTime: req.body.start_time,
      hours,
      totalPrice: Number(room.price_per_hour) * hours,
      addonsTotal,
      paymentMethod: req.body.payment_method || 'Cash',
      notes: req.body.notes || ''
    }, selectedItems);

    await notificationModel.createAdminNotification(
      'booking_created',
      'New booking created',
      `${booking.customer_name} booked ${room.room_name}.`,
      'bookings',
      booking.id,
      `/admin/bookings/${booking.id}`
    );
    await notificationModel.createSiteNotification(user.id, 'booking_created', 'Your reservation is ready', 'Your booking has been confirmed.', '/my-bookings');

    req.flash('success', `Booking confirmed: ${booking.booking_code || bookingCode(booking.id)}.`);
    return res.redirect('/my-bookings');
  } catch (error) {
    next(error);
  }
}

async function availableSlots(req, res, next) {
  try {
    const roomId = Number(req.query.room_id || req.body.room_id);
    const date = req.query.booking_date || req.body.booking_date;
    const hours = Number(req.query.hours || req.body.hours || 1);
    if (!roomId || !date) {
      return res.json({
        success: false,
        slots: [],
        message: 'room_id and booking_date are required.'
      });
    }
    const slots = await bookingModel.availableSlots(roomId, date, hours);
    res.json({ success: true, slots });
  } catch (error) {
    next(error);
  }
}

async function myBookings(req, res, next) {
  try {
    const userId = req.session.user?.id || 0;
    const sessionToken = req.session.customerBookingToken || '';
    const [bookings, storeOrders] = await Promise.all([
      bookingModel.listForUser(sessionToken, userId),
      require('../models/storeModel').listOrdersForUser(userId)
    ]);
    res.render('booking/my-bookings', {
      title: 'My Bookings',
      bookings,
      storeOrders,
      bookingCode,
      money,
      formatTime,
      hoursLabel
    });
  } catch (error) {
    next(error);
  }
}

async function cancel(req, res, next) {
  try {
    const cancelled = await bookingModel.cancel(req.body.booking_id, req.session.customerBookingToken, req.session.user?.id);
    req.flash(cancelled ? 'success' : 'error', cancelled ? 'Booking cancelled.' : 'Could not cancel this booking.');
    res.redirect('/my-bookings');
  } catch (error) {
    next(error);
  }
}

async function showPayment(req, res, next) {
  try {
    const bookingId = Number(req.query.booking_id || req.body.booking_id);
    const booking = await bookingModel.findForUser(bookingId, req.session.customerBookingToken, req.session.user?.id);
    const items = booking ? await bookingModel.items(booking.id) : [];
    res.render('booking/payment', { title: 'Payment', booking, items });
  } catch (error) {
    next(error);
  }
}

async function pay(req, res, next) {
  try {
    const booking = await bookingModel.findForUser(req.body.booking_id, req.session.customerBookingToken, req.session.user?.id);
    if (!booking) {
      req.flash('error', 'Booking was not found.');
      return res.redirect('/my-bookings');
    }

    const method = ['Cash', 'Visa', 'CliQ'].includes(req.body.payment_method) ? req.body.payment_method : 'Cash';
    const finalTotal = Number(booking.final_total || booking.total_price || 0);
    const isPaid = method === 'Visa';
    const updated = await bookingModel.markPayment(
      booking.id,
      req.session.customerBookingToken,
      req.session.user?.id,
      isPaid ? 'Paid' : 'Pending Payment',
      method,
      isPaid ? finalTotal : 0
    );

    if (updated && isPaid) {
      await bookingModel.awardLoyaltyIfNeeded(booking.id);
    }

    req.flash('success', isPaid ? 'Payment completed.' : 'Payment method saved for admin confirmation.');
    res.redirect(`/payment?booking_id=${booking.id}`);
  } catch (error) {
    next(error);
  }
}

module.exports = {
  showForm,
  create,
  availableSlots,
  myBookings,
  cancel,
  showPayment,
  pay
};
