const db = require('../config/db');

async function available(categories = []) {
  const params = [];
  let where = 'WHERE is_available = true';

  if (categories.length > 0) {
    params.push(categories);
    where += ` AND item_category = ANY($1)`;
  }

  const result = await db.query(
    `SELECT * FROM menu_items ${where} ORDER BY item_category, item_name`,
    params
  );
  return result.rows;
}

async function findMany(ids = []) {
  if (!ids.length) return [];
  const result = await db.query(
    'SELECT * FROM menu_items WHERE is_available = true AND id = ANY($1::int[])',
    [ids]
  );
  return result.rows;
}

module.exports = {
  available,
  findMany
};
