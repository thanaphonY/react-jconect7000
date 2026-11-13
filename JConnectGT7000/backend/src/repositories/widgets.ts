import type { Queryable } from '../db.js';

export interface WidgetRow {
  id: number;
  name: string;
  widgetType: string;
  config: Record<string, unknown>;
}

interface DbWidget {
  id: number;
  name: string;
  widget_type: string;
  config: unknown;
}

/** PHP dump / legacy rows may store config as a JSON string inside jsonb. Always expose an object. */
export function normalizeWidgetConfig(config: unknown): Record<string, unknown> {
  if (config == null) return {};
  if (typeof config === 'object' && !Array.isArray(config)) {
    return config as Record<string, unknown>;
  }
  if (typeof config === 'string') {
    try {
      const parsed = JSON.parse(config) as unknown;
      return normalizeWidgetConfig(parsed);
    } catch {
      return {};
    }
  }
  return {};
}

function toWidget(row: DbWidget): WidgetRow {
  return {
    id: row.id,
    name: row.name,
    widgetType: row.widget_type,
    config: normalizeWidgetConfig(row.config),
  };
}

export async function listWidgets(db: Queryable): Promise<WidgetRow[]> {
  const result = await db.query(
    `SELECT id, name, widget_type, config FROM widgets ORDER BY LOWER(name) ASC`,
  );
  return (result.rows as DbWidget[]).map(toWidget);
}

export async function findWidget(db: Queryable, id: number): Promise<WidgetRow | null> {
  const result = await db.query(
    `SELECT id, name, widget_type, config FROM widgets WHERE id = $1`,
    [id],
  );
  const row = result.rows[0] as DbWidget | undefined;
  return row ? toWidget(row) : null;
}

export async function insertWidget(
  db: Queryable,
  input: { name: string; widgetType: string; config: unknown },
): Promise<WidgetRow> {
  const config = normalizeWidgetConfig(input.config);
  const result = await db.query(
    `INSERT INTO widgets (name, widget_type, config)
     VALUES ($1, $2, $3::jsonb)
     RETURNING id, name, widget_type, config`,
    [input.name, input.widgetType, JSON.stringify(config)],
  );
  return toWidget(result.rows[0] as DbWidget);
}

export async function updateWidget(
  db: Queryable,
  id: number,
  input: { name: string; widgetType: string; config: unknown },
): Promise<WidgetRow | null> {
  const config = normalizeWidgetConfig(input.config);
  const result = await db.query(
    `UPDATE widgets
     SET name = $2, widget_type = $3, config = $4::jsonb, updated_at = now()
     WHERE id = $1
     RETURNING id, name, widget_type, config`,
    [id, input.name, input.widgetType, JSON.stringify(config)],
  );
  const row = result.rows[0] as DbWidget | undefined;
  return row ? toWidget(row) : null;
}

export async function deleteWidget(db: Queryable, id: number): Promise<'deleted' | 'missing' | 'in_use'> {
  try {
    const result = await db.query(`DELETE FROM widgets WHERE id = $1 RETURNING id`, [id]);
    return result.rows.length > 0 ? 'deleted' : 'missing';
  } catch (error) {
    if (typeof error === 'object' && error !== null && 'code' in error && error.code === '23503') {
      return 'in_use';
    }
    throw error;
  }
}
