import type { Queryable } from '../db.js';
import { inferDcimSelection } from '../dcim/taxonomy.js';

export interface CategoryRow {
  id: number;
  name: string;
  description: string;
  icon: string;
  color: string;
  isActive: boolean;
  devicesCount: number;
  templatesCount: number;
}

export interface TemplateSummary {
  id: number;
  name: string;
  description: string;
  protocol: string;
  categoryId: number | null;
  categoryName: string;
  isActive: boolean;
  image: string | null;
  imageUrl: string | null;
}

export interface TemplateDetail extends TemplateSummary {
  communicationConfig: Record<string, unknown> | unknown[];
  deviceInformation: Record<string, unknown>;
  metrics: unknown;
  defaultDashboardLayout: unknown;
  selectedDcimCategory: string;
  selectedDcimSubCategory: string;
  dcimCategory: string;
  dcimSubCategory: string;
}

export interface TemplateWriteInput {
  name: string;
  description: string;
  protocol: string;
  categoryId: number;
  isActive: boolean;
  communicationConfig: unknown;
  deviceInformation: unknown;
  metrics: unknown;
  defaultDashboardLayout: unknown;
  image: string | null;
}

interface CategorySql {
  id: number;
  name: string | null;
  description: string | null;
  icon: string | null;
  color: string | null;
  is_active: boolean | null;
  devices_count: number | string;
  templates_count: number | string;
}

interface TemplateSql {
  id: number;
  name: string | null;
  description: string | null;
  protocol: string | null;
  category_id: number | null;
  category_name: string | null;
  is_active: boolean | null;
  image?: string | null;
  communication_config?: unknown;
  device_information?: unknown;
  metrics?: unknown;
  default_dashboard_layout?: unknown;
}

function toCategory(row: CategorySql): CategoryRow {
  return {
    id: row.id,
    name: row.name ?? '',
    description: row.description ?? '',
    icon: row.icon ?? '',
    color: row.color ?? '',
    isActive: row.is_active !== false,
    devicesCount: Number(row.devices_count),
    templatesCount: Number(row.templates_count),
  };
}

function parseJsonObject(value: unknown): Record<string, unknown> {
  if (value == null) return {};
  if (typeof value === 'string') {
    try {
      const parsed: unknown = JSON.parse(value);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return parsed as Record<string, unknown>;
      }
      return {};
    } catch {
      return {};
    }
  }
  if (typeof value === 'object' && !Array.isArray(value)) {
    return value as Record<string, unknown>;
  }
  return {};
}

function parseJsonValue(value: unknown, fallback: unknown): unknown {
  if (value == null) return fallback;
  if (typeof value === 'string') {
    try {
      return JSON.parse(value) as unknown;
    } catch {
      return fallback;
    }
  }
  return value;
}

function templateImageUrl(image: string | null | undefined): string | null {
  if (!image || !String(image).trim()) return null;
  return `/api/v1/uploads/device-templates/${encodeURIComponent(String(image).trim())}`;
}

function toTemplateSummary(row: TemplateSql): TemplateSummary {
  const image = row.image ?? null;
  return {
    id: row.id,
    name: row.name ?? '',
    description: row.description ?? '',
    protocol: row.protocol ?? '',
    categoryId: row.category_id,
    categoryName: row.category_name ?? '',
    isActive: row.is_active !== false,
    image,
    imageUrl: templateImageUrl(image),
  };
}

function toTemplateDetail(row: TemplateSql): TemplateDetail {
  const summary = toTemplateSummary(row);
  const deviceInformation = parseJsonObject(row.device_information);
  const inferred = inferDcimSelection({
    deviceInformation,
    categoryName: summary.categoryName,
  });
  return {
    ...summary,
    communicationConfig: parseJsonValue(row.communication_config, {}) as
      | Record<string, unknown>
      | unknown[],
    deviceInformation,
    metrics: parseJsonValue(row.metrics, []),
    defaultDashboardLayout: parseJsonValue(row.default_dashboard_layout, []),
    selectedDcimCategory: inferred.category,
    selectedDcimSubCategory: inferred.subCategory,
    dcimCategory: inferred.category,
    dcimSubCategory: inferred.subCategory,
  };
}

export async function listCategories(db: Queryable): Promise<CategoryRow[]> {
  const result = await db.query(
    `SELECT dc.id, dc.name, dc.description, dc.icon, dc.color, dc.is_active,
            COUNT(DISTINCT d.id)::int AS devices_count,
            COUNT(DISTINCT dt.id)::int AS templates_count
     FROM device_categories dc
     LEFT JOIN devices d ON d.category_id = dc.id AND d.is_deleted = false
     LEFT JOIN device_templates dt ON dt.category_id = dc.id AND dt.is_active = true
     GROUP BY dc.id
     ORDER BY dc.name ASC`,
  );
  return (result.rows as CategorySql[]).map(toCategory);
}

export async function findCategoryByName(db: Queryable, name: string): Promise<number | null> {
  const result = await db.query(`SELECT id FROM device_categories WHERE name = $1 LIMIT 1`, [name]);
  const row = result.rows[0] as { id: number } | undefined;
  return row ? row.id : null;
}

export async function findCategory(db: Queryable, id: number): Promise<CategoryRow | null> {
  const result = await db.query(
    `SELECT dc.id, dc.name, dc.description, dc.icon, dc.color, dc.is_active,
            COUNT(DISTINCT d.id)::int AS devices_count,
            COUNT(DISTINCT dt.id)::int AS templates_count
     FROM device_categories dc
     LEFT JOIN devices d ON d.category_id = dc.id AND d.is_deleted = false
     LEFT JOIN device_templates dt ON dt.category_id = dc.id AND dt.is_active = true
     WHERE dc.id = $1
     GROUP BY dc.id`,
    [id],
  );
  const row = result.rows[0] as CategorySql | undefined;
  return row ? toCategory(row) : null;
}

export async function insertCategory(
  db: Queryable,
  input: { name: string; description: string; icon: string; color: string; isActive: boolean },
): Promise<number> {
  const result = await db.query(
    `INSERT INTO device_categories (name, description, icon, color, is_active, created_at)
     VALUES ($1, $2, $3, $4, $5, NOW())
     RETURNING id`,
    [input.name, input.description, input.icon || 'fa-server', input.color || '#007bff', input.isActive],
  );
  return Number((result.rows[0] as { id: number }).id);
}

export async function updateCategory(
  db: Queryable,
  id: number,
  input: { name: string; description: string; icon: string; color: string; isActive: boolean },
): Promise<boolean> {
  const result = await db.query(
    `UPDATE device_categories
     SET name = $2, description = $3, icon = $4, color = $5, is_active = $6, updated_at = NOW()
     WHERE id = $1
     RETURNING id`,
    [id, input.name, input.description, input.icon, input.color, input.isActive],
  );
  return result.rows.length > 0;
}

export async function categoryUsage(db: Queryable, id: number): Promise<{ devices: number; templates: number }> {
  const devices = await db.query(
    `SELECT COUNT(*)::int AS total FROM devices WHERE category_id = $1 AND is_deleted = false`,
    [id],
  );
  const templates = await db.query(
    `SELECT COUNT(*)::int AS total FROM device_templates WHERE category_id = $1 AND is_active = true`,
    [id],
  );
  return {
    devices: Number((devices.rows[0] as { total: number }).total),
    templates: Number((templates.rows[0] as { total: number }).total),
  };
}

export async function deleteCategory(db: Queryable, id: number): Promise<void> {
  await db.query(`DELETE FROM device_categories WHERE id = $1`, [id]);
}

export async function listTemplates(db: Queryable): Promise<TemplateSummary[]> {
  const result = await db.query(
    `SELECT dt.id, dt.name, dt.description, dt.protocol, dt.category_id, dt.is_active, dt.image,
            dc.name AS category_name
     FROM device_templates dt
     LEFT JOIN device_categories dc ON dc.id = dt.category_id
     ORDER BY dt.id DESC`,
  );
  return (result.rows as TemplateSql[]).map(toTemplateSummary);
}

export async function findTemplate(db: Queryable, id: number): Promise<TemplateDetail | null> {
  const result = await db.query(
    `SELECT dt.id, dt.name, dt.description, dt.protocol, dt.category_id, dt.is_active, dt.image,
            dt.communication_config, dt.device_information, dt.metrics, dt.default_dashboard_layout,
            dc.name AS category_name
     FROM device_templates dt
     LEFT JOIN device_categories dc ON dc.id = dt.category_id
     WHERE dt.id = $1`,
    [id],
  );
  const row = result.rows[0] as TemplateSql | undefined;
  return row ? toTemplateDetail(row) : null;
}

function asJsonParam(value: unknown, fallback: unknown): string {
  if (value === undefined || value === null) {
    return JSON.stringify(fallback);
  }
  if (typeof value === 'string') {
    try {
      JSON.parse(value);
      return value;
    } catch {
      return JSON.stringify(fallback);
    }
  }
  return JSON.stringify(value);
}

export async function insertTemplate(db: Queryable, input: TemplateWriteInput): Promise<number> {
  const result = await db.query(
    `INSERT INTO device_templates (
       name, description, protocol, category_id, is_active,
       metrics, communication_config, device_information, default_dashboard_layout, image, created_at
     ) VALUES (
       $1, $2, $3, $4, $5,
       $6::jsonb, $7::jsonb, $8::jsonb, $9::jsonb, $10, NOW()
     )
     RETURNING id`,
    [
      input.name,
      input.description,
      input.protocol,
      input.categoryId,
      input.isActive,
      asJsonParam(input.metrics, []),
      asJsonParam(input.communicationConfig, {}),
      asJsonParam(input.deviceInformation, {}),
      asJsonParam(input.defaultDashboardLayout, []),
      input.image,
    ],
  );
  return Number((result.rows[0] as { id: number }).id);
}

export async function updateTemplate(
  db: Queryable,
  id: number,
  input: TemplateWriteInput,
): Promise<boolean> {
  const result = await db.query(
    `UPDATE device_templates
     SET name = $2,
         description = $3,
         protocol = $4,
         category_id = $5,
         is_active = $6,
         metrics = $7::jsonb,
         communication_config = $8::jsonb,
         device_information = $9::jsonb,
         default_dashboard_layout = $10::jsonb,
         image = COALESCE($11, image),
         updated_at = NOW()
     WHERE id = $1
     RETURNING id`,
    [
      id,
      input.name,
      input.description,
      input.protocol,
      input.categoryId,
      input.isActive,
      asJsonParam(input.metrics, []),
      asJsonParam(input.communicationConfig, {}),
      asJsonParam(input.deviceInformation, {}),
      asJsonParam(input.defaultDashboardLayout, []),
      input.image,
    ],
  );
  return result.rows.length > 0;
}

/** Resolve or create a device_categories row for a DCIM "Category / Sub" label (PHP parity). */
export async function ensureCategoryIdForLabel(db: Queryable, label: string): Promise<number> {
  const existing = await db.query(
    `SELECT id, is_active FROM device_categories WHERE name = $1 LIMIT 1`,
    [label],
  );
  const row = existing.rows[0] as { id: number; is_active: boolean | null } | undefined;
  if (row) {
    if (row.is_active === false) {
      await db.query(
        `UPDATE device_categories SET is_active = true, updated_at = NOW() WHERE id = $1`,
        [row.id],
      );
    }
    return row.id;
  }
  return insertCategory(db, {
    name: label,
    description: 'DCIM device category',
    icon: 'fa-server',
    color: '#007bff',
    isActive: true,
  });
}

export async function deleteTemplate(db: Queryable, id: number): Promise<void> {
  await db.query(`DELETE FROM device_templates WHERE id = $1`, [id]);
}

export async function templateDeviceCount(db: Queryable, id: number): Promise<number> {
  const result = await db.query(
    `SELECT COUNT(*)::int AS total FROM devices WHERE device_template_id = $1 AND is_deleted = false`,
    [id],
  );
  return Number((result.rows[0] as { total: number }).total);
}
