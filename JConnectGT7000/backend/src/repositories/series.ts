import type { Queryable } from '../db.js';

export interface SeriesPoint {
  timestamp: string;
  value: number | null;
}

export async function listSeries(
  db: Queryable,
  key: string,
  limit: number,
): Promise<SeriesPoint[]> {
  const result = await db.query(
    `SELECT timestamp, value
     FROM device_data
     WHERE key ILIKE $1 AND value IS NOT NULL
     ORDER BY timestamp DESC
     LIMIT $2`,
    [key, limit],
  );
  const rows = result.rows as { timestamp: Date | string; value: number | null }[];
  return rows.reverse().map((row) => ({
    timestamp: row.timestamp instanceof Date ? row.timestamp.toISOString() : String(row.timestamp),
    value: row.value === null ? null : Number(row.value),
  }));
}
