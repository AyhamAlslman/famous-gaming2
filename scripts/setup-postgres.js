const fs = require('fs/promises');
const path = require('path');
const { Client } = require('pg');
const env = require('../src/config/env');

async function setup() {
  const sqlPath = path.join(process.cwd(), 'sql', 'postgres', 'schema.sql');
  const sql = await fs.readFile(sqlPath, 'utf8');
  const client = new Client(env.postgres);
  await client.connect();
  try {
    await client.query(sql);
    console.log(`PostgreSQL schema created in database "${env.postgres.database}".`);
  } finally {
    await client.end();
  }
}

setup().catch((error) => {
  console.error(error);
  process.exit(1);
});
