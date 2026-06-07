const storeModel = require('../models/storeModel');
const notificationModel = require('../models/notificationModel');

const STORE_CATEGORIES = [
  'PlayStation Consoles',
  'Controllers',
  'Games / CDs',
  'Controller Covers',
  'PlayStation Accessories'
];

function normalizeCartPayload(cartData, body = {}) {
  if (cartData) {
    try {
      const parsed = JSON.parse(cartData);
      const merged = new Map();
      if (Array.isArray(parsed)) {
        for (const item of parsed) {
          const productId = Number(item.id || item.productId);
          const quantity = Math.max(0, Math.min(20, Number(item.quantity || 0)));
          if (productId > 0 && quantity > 0) {
            merged.set(productId, Math.min(20, (merged.get(productId) || 0) + quantity));
          }
        }
      }
      return Array.from(merged, ([productId, quantity]) => ({ productId, quantity }));
    } catch (error) {
      return [];
    }
  }

  const productId = Number(body.product_id);
  const quantity = Math.max(1, Math.min(20, Number(body.quantity || 1)));
  return productId > 0 ? [{ productId, quantity }] : [];
}

function serializeCart(items) {
  return JSON.stringify(items.map((item) => ({ id: item.productId, quantity: item.quantity })));
}

function checkoutRenderData(overrides = {}) {
  return {
    title: 'Store Checkout',
    checkoutItems: [],
    subtotal: 0,
    selectedMethod: 'Cash',
    cartData: '[]',
    error: '',
    successOrder: null,
    successOrderItems: [],
    cliqTransferNumber: '0798497188',
    ...overrides
  };
}

async function index(req, res, next) {
  try {
    const selectedCategory = STORE_CATEGORIES.includes(req.query.category) ? req.query.category : '';
    const products = await storeModel.products(selectedCategory);
    res.render('store/index', {
      title: 'Store',
      products,
      categories: STORE_CATEGORIES,
      selectedCategory,
      canOrder: Boolean(req.session.user)
    });
  } catch (error) {
    next(error);
  }
}

async function checkout(req, res, next) {
  try {
    const checkoutAction = req.body.checkout_action || 'review';
    const paymentMethod = ['Cash', 'Visa', 'CliQ'].includes(req.body.payment_method) ? req.body.payment_method : 'Cash';
    const cart = normalizeCartPayload(req.body.cart_data, req.body);

    if (!cart.length) {
      return res.render('store/checkout', checkoutRenderData({
        error: 'Your store basket is empty.'
      }));
    }

    const cartDetails = await storeModel.cartItems(cart);
    const cartData = serializeCart(cart);

    if (checkoutAction !== 'confirm') {
      return res.render('store/checkout', checkoutRenderData({
        checkoutItems: cartDetails.items,
        subtotal: cartDetails.subtotal,
        selectedMethod: paymentMethod,
        cartData
      }));
    }

    if (paymentMethod === 'Visa') {
      const cardNumber = String(req.body.card_number || '').replace(/\s+/g, '');
      const expiryDate = String(req.body.expiry_date || '').trim();
      const cvv = String(req.body.cvv || '').trim();
      if (cardNumber.length < 12 || !expiryDate || cvv.length < 3) {
        return res.status(422).render('store/checkout', checkoutRenderData({
          checkoutItems: cartDetails.items,
          subtotal: cartDetails.subtotal,
          selectedMethod: paymentMethod,
          cartData,
          error: 'Please enter valid Visa payment details.'
        }));
      }
    }

    const order = await storeModel.createOrder(req.session.user, cart, paymentMethod);
    const isPaid = paymentMethod === 'Visa';
    await notificationModel.createAdminNotification(
      'store_order_created',
      isPaid ? 'New store order' : 'Store order waiting for payment confirmation',
      `${req.session.user.full_name} placed store order ${order.order_code} via ${paymentMethod}.`,
      'store_orders',
      order.id,
      `/admin/store-orders?order_id=${order.id}`
    );
    await notificationModel.createSiteNotification(
      req.session.user.id,
      'store_order_created',
      isPaid ? 'Store order confirmed' : 'Store order saved',
      isPaid ? 'Your store order has been confirmed.' : 'Your store order is waiting for admin confirmation.',
      '/my-bookings'
    );

    return res.redirect(`/store/checkout?success=1&order_id=${order.id}`);
  } catch (error) {
    return res.status(422).render('store/checkout', checkoutRenderData({
      error: error.message || 'Could not create store order.'
    }));
  }
}

async function checkoutReview(req, res, next) {
  try {
    const orderId = Number(req.query.order_id || 0);
    if (orderId > 0 && req.query.success) {
      const successOrder = await storeModel.findOrderForUser(orderId, req.session.user.id);
      if (successOrder) {
        const successOrderItems = await storeModel.orderItems(successOrder.id);
        return res.render('store/checkout', checkoutRenderData({
          successOrder,
          successOrderItems
        }));
      }
    }

    return res.render('store/checkout', checkoutRenderData());
  } catch (error) {
    next(error);
  }
}

module.exports = {
  index,
  checkout,
  checkoutReview
};
