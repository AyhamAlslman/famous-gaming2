const app = require('./app');
const env = require('./config/env');
const { query } = require('./config/db');

async function start() {
  try {
    await query('SELECT 1');
    app.listen(env.port, () => {
      console.log(`FAMOUS GAMING Node app running on ${env.appBaseUrl}`);
    });
  } catch (error) {
    console.error('Could not connect to PostgreSQL. Run npm run db:setup after configuring .env.');
    console.error(error.message);
    process.exit(1);
  }
}

start();
