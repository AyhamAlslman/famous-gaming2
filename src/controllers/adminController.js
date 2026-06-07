const adminModel = require('../models/adminModel');

async function dashboard(req, res, next) {
  try {
    const [stats, bookings, complaints] = await Promise.all([
      adminModel.dashboardStats(),
      adminModel.recentBookings(),
      adminModel.recentComplaints()
    ]);
    res.render('admin/dashboard', {
      title: 'Admin Dashboard',
      stats,
      bookings,
      complaints
    });
  } catch (error) {
    next(error);
  }
}

async function listPage(req, res, next) {
  try {
    const page = req.params.page;
    const configs = {
      rooms: {
        title: 'Rooms Management',
        rows: await adminModel.rooms(),
        columns: ['id', 'room_name', 'room_type', 'price_per_hour', 'status']
      },
      bookings: {
        title: 'Bookings Management',
        rows: await adminModel.bookings(),
        columns: ['id', 'booking_code', 'customer_name', 'room_name', 'booking_date', 'start_time', 'status', 'payment_status']
      },
      menu: {
        title: 'Menu Items',
        rows: await adminModel.menuItems(),
        columns: ['id', 'item_name', 'item_category', 'item_price', 'is_available']
      },
      employees: {
        title: 'Employees',
        rows: await adminModel.employees(),
        columns: ['id', 'username', 'full_name', 'role', 'phone', 'email', 'status']
      },
      storeProducts: {
        title: 'Store Products',
        rows: await adminModel.storeProducts(),
        columns: ['id', 'product_name', 'category', 'price', 'stock_quantity', 'status']
      },
      storeOrders: {
        title: 'Store Orders',
        rows: await adminModel.storeOrders(),
        columns: ['id', 'order_code', 'customer_name', 'total_amount', 'payment_status', 'status', 'created_at']
      },
      complaints: {
        title: 'Complaints',
        rows: await adminModel.complaints(),
        columns: ['id', 'complaint_code', 'customer_name', 'phone', 'status', 'created_at']
      },
      customerTickets: {
        title: 'Customer Tickets',
        rows: await adminModel.customerTickets(),
        columns: ['id', 'booking_code', 'customer_name', 'room_name', 'booking_date', 'start_time', 'status']
      },
      notifications: {
        title: 'Admin Notifications',
        rows: await require('../models/notificationModel').adminNotifications(),
        columns: ['id', 'notification_type', 'title', 'related_table', 'related_id', 'is_read', 'created_at']
      }
    };

    const config = configs[page];
    if (!config) {
      return res.status(404).render('public/not-found', { title: 'Page not found' });
    }

    res.render('admin/list', {
      title: config.title,
      heading: config.title,
      rows: config.rows,
      columns: config.columns
    });
  } catch (error) {
    next(error);
  }
}

async function bookingDetails(req, res, next) {
  try {
    const details = await adminModel.bookingDetails(req.params.id || req.query.id);
    if (!details.booking) {
      return res.status(404).render('public/not-found', { title: 'Booking not found' });
    }
    res.render('admin/booking-details', {
      title: `Booking #${details.booking.id}`,
      booking: details.booking,
      items: details.items
    });
  } catch (error) {
    next(error);
  }
}

module.exports = {
  dashboard,
  listPage,
  bookingDetails
};
