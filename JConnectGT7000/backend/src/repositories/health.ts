import type { Queryable } from '../db.js';

const PING_SQL = 'SELECT 1';

export async function pingDatabase(db: Queryable): Promise<void> {
  await db.query(PING_SQL);
}
