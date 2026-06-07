const roomModel = require('../models/roomModel');
const menuModel = require('../models/menuModel');
const storeModel = require('../models/storeModel');

async function home(req, res, next) {
  try {
    const rooms = await roomModel.homeRooms();
    res.render('public/home', {
      title: 'FAMOUS GAMING',
      rooms,
      heroImage: '/images/home-hero-2026-optimized.jpg'
    });
  } catch (error) {
    next(error);
  }
}

function services(req, res) {
  res.render('public/services', { title: 'Services - FAMOUS GAMING' });
}

async function serviceGaming(req, res, next) {
  try {
    const rooms = await roomModel.available();
    res.render('public/service-gaming', { title: 'Gaming Service', rooms });
  } catch (error) {
    next(error);
  }
}

async function serviceHospitality(req, res, next) {
  try {
    const menuItems = await menuModel.available(['Drinks', 'Snacks']);
    res.render('public/service-hospitality', { title: 'Hospitality Service', menuItems });
  } catch (error) {
    next(error);
  }
}

function serviceEvents(req, res) {
  res.render('public/service-events', { title: 'Events Service' });
}

function about(req, res) {
  res.render('public/about', { title: 'About - FAMOUS GAMING' });
}

function contact(req, res) {
  res.render('public/contact', { title: 'Contact - FAMOUS GAMING' });
}

async function menu(req, res, next) {
  try {
    const categories = ['Drinks', 'Snacks'];
    const selectedCategory = categories.includes(req.query.category) ? req.query.category : '';
    const [menuItems, allMenuItems] = await Promise.all([
      menuModel.available(selectedCategory ? [selectedCategory] : categories),
      menuModel.available(categories)
    ]);
    const categoryCounts = categories.reduce((counts, category) => {
      counts[category] = allMenuItems.filter((item) => item.item_category === category).length;
      return counts;
    }, {});

    res.render('public/menu', {
      title: 'Snacks and Drinks - FAMOUS GAMING',
      menuItems,
      categories,
      selectedCategory,
      categoryCounts
    });
  } catch (error) {
    next(error);
  }
}

async function storePreview(req, res, next) {
  try {
    const products = await storeModel.products();
    res.render('store/index', { title: 'Store', products });
  } catch (error) {
    next(error);
  }
}

function forgotPassword(req, res) {
  res.render('auth/simple', {
    title: 'Forgot Password',
    heading: 'Forgot Password',
    text: 'Password reset email is not configured in the Node version yet. Admin can update passwords from the database or PHP tools during migration.'
  });
}

function resetPassword(req, res) {
  res.render('auth/simple', {
    title: 'Reset Password',
    heading: 'Reset Password',
    text: 'Password reset token flow is reserved for the next migration pass.'
  });
}

module.exports = {
  home,
  services,
  serviceGaming,
  serviceHospitality,
  serviceEvents,
  about,
  contact,
  menu,
  storePreview,
  forgotPassword,
  resetPassword
};
