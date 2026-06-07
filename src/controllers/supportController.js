const { generateToken } = require('../utils/format');
const supportModel = require('../models/supportModel');

async function message(req, res, next) {
  try {
    const text = req.body.message || '';
    req.session.supportToken = req.session.supportToken || generateToken();
    await supportModel.saveMessage(req.session.supportToken, 'user', text);
    const response = await supportModel.answer(text);
    await supportModel.saveMessage(req.session.supportToken, 'bot', response.answer, response.intent, response);
    res.json({
      intent: response.intent,
      answer: response.answer,
      sections: response.sections || [],
      source: 'postgres-local',
      actions: []
    });
  } catch (error) {
    next(error);
  }
}

function info(req, res) {
  res.json({
    success: true,
    endpoint: '/support-chatbot',
    method: 'POST',
    body: { message: 'your question' }
  });
}

module.exports = {
  message,
  info
};
