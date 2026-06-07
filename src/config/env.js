const path = require('path');
require('dotenv').config({ path: path.join(process.cwd(), '.env') });

const env = {
  nodeEnv: process.env.NODE_ENV || 'development',
  port: Number(process.env.PORT || 3000),
  appBaseUrl: process.env.APP_BASE_URL || `http://localhost:${process.env.PORT || 3000}`,
  sessionSecret: process.env.SESSION_SECRET || 'local-famous-gaming-secret',
  postgres: {
    host: process.env.PGHOST || 'localhost',
    port: Number(process.env.PGPORT || 5432),
    database: process.env.PGDATABASE || 'playroom_node',
    user: process.env.PGUSER || 'postgres',
    password: process.env.PGPASSWORD || ''
  }
};

module.exports = env;
