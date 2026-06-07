const db = require('../config/db');

async function answer(message) {
  const text = String(message || '').toLowerCase();

  if (!text.trim()) {
    return { intent: 'empty', answer: 'Please write your question first.', sections: [] };
  }

  if (text.includes('price') || text.includes('سعر') || text.includes('ارخص') || text.includes('اغلى')) {
    const result = await db.query(
      `SELECT room_name, room_type, price_per_hour, status
       FROM rooms
       ORDER BY price_per_hour ASC, room_name ASC`
    );
    if (result.rows.length) {
      const cheapest = result.rows[0];
      return {
        intent: 'pricing',
        answer: `The cheapest room is ${cheapest.room_name} (${cheapest.room_type}) at ${Number(cheapest.price_per_hour).toFixed(2)} JOD/hr.`,
        sections: result.rows.map((room) => `${room.room_name} - ${room.room_type}: ${Number(room.price_per_hour).toFixed(2)} JOD/hr - ${room.status}`)
      };
    }
  }

  if (text.includes('room') || text.includes('غرف') || text.includes('ps5') || text.includes('ps4')) {
    const result = await db.query('SELECT room_name, room_type, price_per_hour, status FROM rooms ORDER BY room_name ASC');
    return {
      intent: 'rooms',
      answer: `The website has ${result.rows.length} gaming rooms available in the database.`,
      sections: result.rows.map((room) => `${room.room_name} - ${room.room_type} - ${room.status}`)
    };
  }

  if (text.includes('store') || text.includes('product') || text.includes('متجر')) {
    const result = await db.query('SELECT product_name, price, stock_quantity FROM store_products WHERE status = $1 ORDER BY product_name ASC LIMIT 8', ['Active']);
    return {
      intent: 'store',
      answer: 'Here are active store products from the website database.',
      sections: result.rows.map((item) => `${item.product_name}: ${Number(item.price).toFixed(2)} JOD, stock ${item.stock_quantity}`)
    };
  }

  return {
    intent: 'general',
    answer: 'I searched the website database. Try asking about rooms, prices, bookings, store products, or complaints.',
    sections: []
  };
}

async function saveMessage(sessionToken, sender, messageText, intent = null, payload = null) {
  let session = await db.query('SELECT id FROM chatbot_sessions WHERE session_token = $1 LIMIT 1', [sessionToken]);

  if (!session.rows[0]) {
    session = await db.query(
      `INSERT INTO chatbot_sessions (session_token, language, status)
       VALUES ($1, 'en', 'Open')
       RETURNING id`,
      [sessionToken]
    );
  }

  await db.query(
    `INSERT INTO chatbot_messages (session_id, sender, message_text, intent, response_payload)
     VALUES ($1, $2, $3, $4, $5)`,
    [session.rows[0].id, sender, messageText, intent, payload ? JSON.stringify(payload) : null]
  );
}

module.exports = {
  answer,
  saveMessage
};
