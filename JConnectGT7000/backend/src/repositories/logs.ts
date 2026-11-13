import type { Queryable } from '../db.js';

function iso(value: Date | string | null): string | null {
  if (!value) return null;
  return value instanceof Date ? value.toISOString() : String(value);
}

export interface EventLogRow {
  id: number;
  createdAt: string | null;
  userName: string;
  actionType: string;
  entityType: string;
  message: string;
}

export async function listEventLogs(
  db: Queryable,
  page: number,
  pageSize: number,
): Promise<{ logs: EventLogRow[]; total: number }> {
  const count = await db.query(`SELECT COUNT(*)::int AS total FROM event_logs`);
  const total = Number((count.rows[0] as { total: number }).total);
  const result = await db.query(
    `SELECT e.id, e.created_at, e.action_type, e.entity_type, e.message, u.name AS user_name
     FROM event_logs e
     LEFT JOIN ia_users u ON u.ia_users_id = e.user_id
     ORDER BY e.created_at DESC NULLS LAST, e.id DESC
     LIMIT $1 OFFSET $2`,
    [pageSize, (page - 1) * pageSize],
  );
  const logs = (result.rows as {
    id: number;
    created_at: Date | string | null;
    action_type: string | null;
    entity_type: string | null;
    message: string | null;
    user_name: string | null;
  }[]).map((row) => ({
    id: row.id,
    createdAt: iso(row.created_at),
    userName: row.user_name ?? '',
    actionType: row.action_type ?? '',
    entityType: row.entity_type ?? '',
    message: row.message ?? '',
  }));
  return { logs, total };
}

