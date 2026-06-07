const db = require('../config/db');
const { generateToken } = require('../utils/format');

async function products(category = '') {
  const params = [];
  let where = "WHERE status = 'Active'";
  if (category) {
    params.push(category);
    where += ' AND category = $1';
  }

  const result = await db.query(
    `SELECT * FROM store_products
     ${where}
     ORDER BY created_at DESC, id DESC`,
    params
  );
  return result.rows;
}

async function findProduct(id) {
  const result = await db.query('SELECT * FROM store_products WHERE id = $1 LIMIT 1', [id]);
  return result.rows[0] || null;
}

function orderCode() {
  return `SO-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${generateToken(4).toUpperCase()}`;
}

async function createOrder(user, items, paymentMethod = 'Cash') {
  return db.transaction(async (client) => {
    let subtotal = 0;
    const productRows = [];

    for (const item of items) {
      const productResult = await client.query('SELECT * FROM store_products WHERE id = $1 FOR UPDATE', [item.productId]);
      const product = productResult.rows[0];
      if (!product || product.status !== 'Active' || Number(product.stock_quantity) < item.quantity) {
        throw new Error('Product is unavailable or out of stock.');
      }
      subtotal += Number(product.price) * item.quantity;
      productRows.push({ product, quantity: item.quantity });
    }

    const status = paymentMethod === 'Visa' ? 'Paid' : 'Pending Payment';
    const orderStatus = paymentMethod === 'Visa' ? 'Confirmed' : 'Pending';
    const paidAmount = paymentMethod === 'Visa' ? subtotal : 0;
    const orderResult = await client.query(
      `INSERT INTO store_orders (
        order_code, user_id, customer_name, phone, email, subtotal, total_amount,
        payment_status, payment_method, paid_amount, status, stock_deducted
      )
      VALUES ($1, $2, $3, $4, $5, $6, $6, $7, $8, $9, $10, true)
      RETURNING *`,
      [orderCode(), user.id, user.full_name, user.phone, user.email, subtotal, status, paymentMethod, paidAmount, orderStatus]
    );
    const order = orderResult.rows[0];

    for (const row of productRows) {
      await client.query(
        `INSERT INTO store_order_items (order_id, product_id, product_name, category, quantity, item_price)
         VALUES ($1, $2, $3, $4, $5, $6)`,
        [order.id, row.product.id, row.product.product_name, row.product.category, row.quantity, row.product.price]
      );
      await client.query(
        'UPDATE store_products SET stock_quantity = stock_quantity - $1 WHERE id = $2',
        [row.quantity, row.product.id]
      );
    }

    return order;
  });
}

async function cartItems(items = []) {
  const rows = [];
  let subtotal = 0;

  for (const item of items) {
    const product = await findProduct(item.productId);
    if (!product || product.status !== 'Active') {
      throw new Error('A product in your basket is no longer available.');
    }
    if (Number(product.stock_quantity) < item.quantity) {
      throw new Error(`${product.product_name} does not have enough stock.`);
    }

    const lineTotal = Number(product.price) * item.quantity;
    subtotal += lineTotal;
    rows.push({
      id: product.id,
      productId: product.id,
      product_name: product.product_name,
      category: product.category,
      price: Number(product.price),
      quantity: item.quantity,
      line_total: lineTotal,
      image_path: product.image_path,
      stock_quantity: product.stock_quantity
    });
  }

  return { items: rows, subtotal };
}

async function listOrdersForUser(userId) {
  const result = await db.query(
    `SELECT * FROM store_orders WHERE user_id = $1 ORDER BY created_at DESC, id DESC`,
    [userId]
  );
  return result.rows;
}

async function findOrderForUser(orderId, userId) {
  const result = await db.query(
    'SELECT * FROM store_orders WHERE id = $1 AND user_id = $2 LIMIT 1',
    [orderId, userId]
  );
  return result.rows[0] || null;
}

async function orderItems(orderId) {
  const result = await db.query(
    'SELECT * FROM store_order_items WHERE order_id = $1 ORDER BY id ASC',
    [orderId]
  );
  return result.rows;
}

module.exports = {
  products,
  findProduct,
  createOrder,
  cartItems,
  listOrdersForUser,
  findOrderForUser,
  orderItems
};
