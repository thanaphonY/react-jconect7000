import type { Queryable } from '../db.js';

export interface LayoutSummary {
  id: number;
  name: string;
  description: string;
  sortOrder: number | null;
  createdBy: number | null;
  creator: string;
  isHome: boolean;
}

export interface LayoutItem {
  id: number;
  widgetId: number | null;
  widget_id: number | null;
  widgetType: string;
  type: string;
  name: string;
  label: string;
  x: number;
  y: number;
  w: number;
  h: number;
  config: Record<string, unknown>;
}

export interface LayoutItemInput {
  widgetId: number | null;
  widgetType: string;
  x: number;
  y: number;
  w: number;
  h: number;
  config: Record<string, unknown>;
}

interface LayoutRow {
  id: number;
  name: string | null;
  description: string | null;
  sort_order: number | null;
  created_by: number | null;
  created_by_name?: string | null;
}

interface ItemRow {
  id: number;
  widget_id: number | null;
  x: number;
  y: number;
  w: number;
  h: number;
  config: unknown;
  widget_name: string | null;
  widget_type: string | null;
}

function asConfig(value: unknown): Record<string, unknown> {
  if (typeof value === 'string') {
    try {
      const parsed: unknown = JSON.parse(value);
      if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
        return parsed as Record<string, unknown>;
      }
    } catch {
      return {};
    }
  }
  if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
    return value as Record<string, unknown>;
  }
  return {};
}

function asLayout(row: LayoutRow, homeLayoutId: number | null = null): LayoutSummary {
  return {
    id: row.id,
    name: row.name ?? '',
    description: row.description ?? '',
    sortOrder: row.sort_order,
    createdBy: row.created_by,
    creator: row.created_by_name ?? '',
    isHome: homeLayoutId !== null && row.id === homeLayoutId,
  };
}

const layoutSelectSql = `
  SELECT dl.id, dl.name, dl.description, dl.sort_order, dl.created_by, u.name AS created_by_name
  FROM dashboard_layouts dl
  LEFT JOIN ia_users u ON dl.created_by = u.ia_users_id
`;

const layoutOrderSql = `
  ${layoutSelectSql}
  ORDER BY CASE WHEN dl.sort_order IS NULL THEN 1 ELSE 0 END, dl.sort_order, dl.name, dl.id
`;

export async function listLayouts(db: Queryable, homeLayoutId: number | null = null): Promise<LayoutSummary[]> {
  const result = await db.query(layoutOrderSql);
  return (result.rows as LayoutRow[]).map((row) => asLayout(row, homeLayoutId));
}

export async function findLayout(
  db: Queryable,
  id: number,
  homeLayoutId: number | null = null,
): Promise<LayoutSummary | null> {
  const result = await db.query(`${layoutSelectSql} WHERE dl.id = $1`, [id]);
  const row = result.rows[0] as LayoutRow | undefined;
  return row ? asLayout(row, homeLayoutId) : null;
}

export async function findUserHomeLayoutId(db: Queryable, userId: number): Promise<number | null> {
  const result = await db.query(
    `SELECT layout_id FROM user_dashboard_homes WHERE user_id = $1`,
    [userId],
  );
  const row = result.rows[0] as { layout_id?: number } | undefined;
  return row?.layout_id ?? null;
}

export async function layoutItems(db: Queryable, layoutId: number): Promise<LayoutItem[]> {
  const result = await db.query(
    `SELECT i.id, i.widget_id, i.x, i.y, i.w, i.h, i.config, w.name AS widget_name, w.widget_type
     FROM dashboard_layout_items i
     LEFT JOIN widgets w ON i.widget_id = w.id
     WHERE i.layout_id = $1
     ORDER BY i.y ASC, i.x ASC`,
    [layoutId],
  );
  return (result.rows as ItemRow[]).map((row) => {
    const config = asConfig(row.config);
    const configured = [config.label, config.zone_name].find(
      (value) => typeof value === 'string' && value.trim() !== '',
    );
    const widgetType = row.widget_type ?? '';
    return {
      id: row.id,
      widgetId: row.widget_id,
      widget_id: row.widget_id,
      widgetType,
      type: widgetType,
      name: row.widget_name ?? '',
      label: typeof configured === 'string' ? configured : (row.widget_name ?? ''),
      x: row.x,
      y: row.y,
      w: row.w,
      h: row.h,
      config,
    };
  });
}

export async function resolveHomeLayout(
  db: Queryable,
  userId: number,
  requestedId?: number,
): Promise<LayoutSummary | null> {
  const homeLayoutId = await findUserHomeLayoutId(db, userId);
  if (requestedId) {
    return findLayout(db, requestedId, homeLayoutId);
  }
  if (homeLayoutId) {
    const layout = await findLayout(db, homeLayoutId, homeLayoutId);
    if (layout) {
      return layout;
    }
  }
  const layouts = await listLayouts(db, homeLayoutId);
  return layouts[0] ?? null;
}

export async function insertLayout(
  db: Queryable,
  input: { name: string; description: string; createdBy: number; sortOrder: number | null },
): Promise<number> {
  const result = await db.query(
    `INSERT INTO dashboard_layouts (name, description, layout, created_by, sort_order)
     VALUES ($1, $2, '[]'::jsonb, $3, $4)
     RETURNING id`,
    [input.name, input.description, input.createdBy, input.sortOrder],
  );
  const row = result.rows[0] as { id: number };
  return row.id;
}

export async function updateLayout(
  db: Queryable,
  id: number,
  input: { name: string; description: string; sortOrder: number | null },
): Promise<void> {
  await db.query(
    `UPDATE dashboard_layouts
     SET name = $2, description = $3, sort_order = $4, updated_at = now()
     WHERE id = $1`,
    [id, input.name, input.description, input.sortOrder],
  );
}

export async function deleteLayout(db: Queryable, id: number): Promise<void> {
  await db.query(`DELETE FROM dashboard_layout_items WHERE layout_id = $1`, [id]);
  await db.query(`DELETE FROM user_dashboard_homes WHERE layout_id = $1`, [id]);
  await db.query(`DELETE FROM dashboard_layouts WHERE id = $1`, [id]);
}

export async function setUserHome(db: Queryable, userId: number, layoutId: number): Promise<void> {
  await db.query(
    `INSERT INTO user_dashboard_homes (user_id, layout_id, updated_at)
     VALUES ($1, $2, now())
     ON CONFLICT (user_id) DO UPDATE
     SET layout_id = EXCLUDED.layout_id, updated_at = EXCLUDED.updated_at`,
    [userId, layoutId],
  );
}

/** PHP update_sort_orders: assign 10, 20, 30… in the given id order. */
export async function updateSortOrders(db: Queryable, orderedIds: number[]): Promise<number> {
  let order = 10;
  let updated = 0;
  for (const id of orderedIds) {
    if (!Number.isInteger(id) || id <= 0) continue;
    await db.query(`UPDATE dashboard_layouts SET sort_order = $2, updated_at = now() WHERE id = $1`, [
      id,
      order,
    ]);
    order += 10;
    updated += 1;
  }
  return updated;
}

/** PHP layoutsave: DELETE all items for the layout, then INSERT the new set. */
export async function replaceLayoutItems(
  db: Queryable,
  layoutId: number,
  items: LayoutItemInput[],
): Promise<void> {
  await db.query(`DELETE FROM dashboard_layout_items WHERE layout_id = $1`, [layoutId]);
  for (const item of items) {
    await db.query(
      `INSERT INTO dashboard_layout_items (layout_id, widget_id, x, y, w, h, config)
       VALUES ($1, $2, $3, $4, $5, $6, $7::jsonb)`,
      [
        layoutId,
        item.widgetId,
        item.x,
        item.y,
        item.w,
        item.h,
        JSON.stringify(item.config ?? {}),
      ],
    );
  }
}
