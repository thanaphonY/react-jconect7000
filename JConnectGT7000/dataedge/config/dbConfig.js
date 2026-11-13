const path = require('path');
const { Pool } = require('pg');

// Optional overrides: PG_HOST, PG_PORT, PG_USER, PG_PASSWORD, PG_DATABASE
require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

const pool = new Pool({
  host: process.env.PG_HOST || 'localhost',
  // gen3/docker-compose.yml maps TimescaleDB to host port 5436 (same as backend .env)
  port: Number(process.env.PG_PORT || process.env.DB_PORT || 5433),
  user: process.env.PG_USER || 'jconnect',
  password: process.env.PG_PASSWORD || 'P@ssword-001',
  database: process.env.PG_DATABASE || 'jconnect',
});

module.exports = pool;



