import { buildApp } from './app.js';
import { env } from './config.js';
import { createPool, type Queryable } from './db.js';

const pool = createPool(env.DATABASE_URL);
const db: Queryable = {
  query: (text, values) => pool.query(text, values),
};
const app = await buildApp({
  pool: db,
  authSecret: env.AUTH_SECRET,
  logger: { level: process.env.LOG_LEVEL ?? 'info' },
});

let shuttingDown = false;

async function shutdown(exitCode: number): Promise<void> {
  if (shuttingDown) {
    return;
  }
  shuttingDown = true;
  await app.close();
  await pool.end();
  process.exit(exitCode);
}

process.on('SIGINT', () => {
  void shutdown(0);
});
process.on('SIGTERM', () => {
  void shutdown(0);
});

try {
  await app.listen({ port: env.PORT, host: '0.0.0.0' });
} catch (err) {
  app.log.error(err);
  await pool.end();
  process.exit(1);
}
