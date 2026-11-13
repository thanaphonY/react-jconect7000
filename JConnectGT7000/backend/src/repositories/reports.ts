import { createReadStream } from 'node:fs';
import { unlink, stat } from 'node:fs/promises';
import path from 'node:path';
import type { Queryable } from '../db.js';
import {
  buildCsvContent,
  buildExcelXml,
  buildSimplePdf,
  slugifyTemplateName,
  writeExportBuffer,
  type ExportColumn,
  type ExportRow,
} from './reportExportFiles.js';

function iso(value: Date | string | null | undefined): string | null {
  if (!value) return null;
  return value instanceof Date ? value.toISOString() : String(value);
}

function parseJson<T>(value: unknown, fallback: T): T {
  if (value == null) return fallback;
  if (typeof value === 'object') return value as T;
  if (typeof value === 'string') {
    try {
      return JSON.parse(value) as T;
    } catch {
      return fallback;
    }
  }
  return fallback;
}

function getExportRoot(): string {
  return path.resolve(
    process.env.REPORT_EXPORT_DIR ?? path.join(process.cwd(), '../frontend/uploads/reports/export'),
  );
}

const MAX_ROWS = 50_000;

const BUCKET_SECONDS: Record<string, number> = {
  '1m': 60,
  '5m': 300,
  '10m': 600,
  '15m': 900,
  '30m': 1800,
  '1h': 3600,
  '4h': 14400,
  '12h': 43200,
  '24h': 86400,
  '1d': 86400,
};

export type DeviceMetricPair = {
  device_id: number;
  device_name?: string;
  metric: string;
  metric_label?: string;
};

export type ReportTemplateConfig = {
  builder_version?: number;
  data_source?: string;
  filters?: Record<string, unknown>;
  export_inputs?: Array<{ type: string; label?: string; required?: boolean }>;
  editor?: {
    report_title?: string;
    table_layout?: string;
    pdf_orientation?: string;
    pdf_title?: string;
    excel_sheet_name?: string;
    csv_delimiter?: string;
    display_columns?: Array<{ key: string; label: string }>;
  };
  template_type?: string;
};

export interface ReportTemplateRow {
  id: number;
  name: string;
  description: string;
  templateType: string;
  config: ReportTemplateConfig;
  updatedAt: string | null;
  createdAt: string | null;
}

export interface ReportFileRow {
  id: number;
  templateId: number | null;
  templateName: string;
  fileName: string;
  exportFormat: string;
  fileSize: number;
  exportedAt: string | null;
  scheduleId: number | null;
}

export interface ReportScheduleRow {
  id: number;
  templateId: number;
  templateName: string;
  name: string;
  isActive: boolean;
  scheduleType: string;
  scheduleConfig: { time?: string; weekday?: number; day?: number };
  timezone: string;
  exportFormat: string;
  recipients: string[];
  nextRunAt: string | null;
  lastRunAt: string | null;
}

export type RunFilters = {
  device_id?: number | null;
  metric?: string | null;
  room?: string | null;
  date?: string | null;
  month?: string | null;
  year?: string | null;
  start_date?: string | null;
  end_date?: string | null;
  action_type?: string | null;
  entity_type?: string | null;
};

function mapTemplate(row: {
  id: number;
  name: string | null;
  description: string | null;
  template_type: string | null;
  config: unknown;
  updated_at: Date | string | null;
  created_at?: Date | string | null;
}): ReportTemplateRow {
  return {
    id: row.id,
    name: row.name ?? '',
    description: row.description ?? '',
    templateType: row.template_type ?? 'custom_builder',
    config: parseJson<ReportTemplateConfig>(row.config, {}),
    updatedAt: iso(row.updated_at),
    createdAt: iso(row.created_at ?? null),
  };
}

export async function listReportTemplates(db: Queryable): Promise<ReportTemplateRow[]> {
  const result = await db.query(
    `SELECT id, name, description, template_type, config, created_at, updated_at
     FROM report_templates
     WHERE template_type = 'custom_builder'
     ORDER BY updated_at DESC NULLS LAST, id DESC`,
  );
  return (result.rows as Parameters<typeof mapTemplate>[0][]).map(mapTemplate);
}

export async function getReportTemplate(
  db: Queryable,
  id: number,
): Promise<ReportTemplateRow | null> {
  const result = await db.query(
    `SELECT id, name, description, template_type, config, created_at, updated_at
     FROM report_templates WHERE id = $1 LIMIT 1`,
    [id],
  );
  const row = result.rows[0] as Parameters<typeof mapTemplate>[0] | undefined;
  return row ? mapTemplate(row) : null;
}

export type SaveTemplateInput = {
  name: string;
  description?: string;
  config: ReportTemplateConfig;
  createdBy?: number | null;
};

export async function createReportTemplate(
  db: Queryable,
  input: SaveTemplateInput,
): Promise<ReportTemplateRow> {
  const result = await db.query(
    `INSERT INTO report_templates
       (name, description, template_type, config, created_by, html_content, is_default, created_at, updated_at)
     VALUES ($1, $2, 'custom_builder', $3::jsonb, $4, '', false, NOW(), NOW())
     RETURNING id, name, description, template_type, config, created_at, updated_at`,
    [
      input.name,
      input.description ?? '',
      JSON.stringify(input.config),
      input.createdBy ?? null,
    ],
  );
  return mapTemplate(result.rows[0] as Parameters<typeof mapTemplate>[0]);
}

export async function updateReportTemplate(
  db: Queryable,
  id: number,
  input: SaveTemplateInput,
): Promise<ReportTemplateRow | null> {
  const result = await db.query(
    `UPDATE report_templates
     SET name = $2,
         description = $3,
         template_type = 'custom_builder',
         config = $4::jsonb,
         updated_at = NOW()
     WHERE id = $1 AND template_type = 'custom_builder'
     RETURNING id, name, description, template_type, config, created_at, updated_at`,
    [id, input.name, input.description ?? '', JSON.stringify(input.config)],
  );
  const row = result.rows[0] as Parameters<typeof mapTemplate>[0] | undefined;
  return row ? mapTemplate(row) : null;
}

export async function deleteReportTemplate(db: Queryable, id: number): Promise<boolean> {
  const result = await db.query(
    `DELETE FROM report_templates WHERE id = $1 AND template_type = 'custom_builder'`,
    [id],
  );
  return (result.rowCount ?? 0) > 0;
}

export async function listReportDevices(
  db: Queryable,
): Promise<Array<{ id: number; name: string; location: string }>> {
  const result = await db.query(
    `SELECT id, name, location
     FROM devices
     WHERE is_deleted = false AND is_active = true
     ORDER BY name`,
  );
  return (result.rows as { id: number; name: string | null; location: string | null }[]).map(
    (r) => ({
      id: r.id,
      name: r.name ?? '',
      location: r.location ?? '',
    }),
  );
}

export async function listMetricsByDevice(
  db: Queryable,
  deviceId: number,
): Promise<Array<{ key: string; label: string }>> {
  const result = await db.query(
    `SELECT metrics FROM devices WHERE id = $1 AND metrics IS NOT NULL LIMIT 1`,
    [deviceId],
  );
  const row = result.rows[0] as { metrics: unknown } | undefined;
  if (!row?.metrics) return [];
  const metrics = parseJson<Array<Record<string, unknown>>>(row.metrics, []);
  if (!Array.isArray(metrics)) return [];
  const out: Array<{ key: string; label: string }> = [];
  for (const m of metrics) {
    const enable = m.enable;
    if (enable !== true && enable !== 'true') continue;
    const key = String(m.key ?? '').trim();
    if (!key) continue;
    const label = String(m.label ?? key).trim() || key;
    out.push({ key, label });
  }
  out.sort((a, b) => a.label.localeCompare(b.label));
  return out;
}

export async function listMetricKeys(db: Queryable): Promise<string[]> {
  const result = await db.query(
    `SELECT DISTINCT "key" AS metric_key
     FROM device_data
     WHERE "key" IS NOT NULL AND "key" != ''
     ORDER BY "key"`,
  );
  return (result.rows as { metric_key: string }[]).map((r) => r.metric_key);
}

export async function listRooms(db: Queryable): Promise<string[]> {
  const result = await db.query(
    `SELECT DISTINCT location AS room
     FROM devices
     WHERE is_deleted = false AND is_active = true
       AND location IS NOT NULL AND location != ''
     ORDER BY location`,
  );
  return (result.rows as { room: string }[]).map((r) => r.room);
}

export async function listActionTypes(db: Queryable): Promise<string[]> {
  const result = await db.query(
    `SELECT DISTINCT action_type
     FROM event_logs
     WHERE action_type IS NOT NULL AND action_type != ''
     ORDER BY action_type`,
  );
  return (result.rows as { action_type: string }[]).map((r) => r.action_type);
}

export async function listEntityTypes(db: Queryable): Promise<string[]> {
  const result = await db.query(
    `SELECT DISTINCT entity_type
     FROM event_logs
     WHERE entity_type IS NOT NULL AND entity_type != ''
     ORDER BY entity_type`,
  );
  return (result.rows as { entity_type: string }[]).map((r) => r.entity_type);
}

function bangkokToUtcString(dateTimeStr: string): string {
  if (!dateTimeStr || dateTimeStr.includes('+') || dateTimeStr.includes('Z')) {
    return dateTimeStr;
  }
  let s = dateTimeStr.trim();
  if (s.length <= 10) s += ' 00:00:00';
  // Interpret as Asia/Bangkok then convert to UTC with +00 suffix
  const asLocal = new Date(`${s.replace(' ', 'T')}+07:00`);
  if (Number.isNaN(asLocal.getTime())) return dateTimeStr;
  const y = asLocal.getUTCFullYear();
  const m = String(asLocal.getUTCMonth() + 1).padStart(2, '0');
  const d = String(asLocal.getUTCDate()).padStart(2, '0');
  const hh = String(asLocal.getUTCHours()).padStart(2, '0');
  const mm = String(asLocal.getUTCMinutes()).padStart(2, '0');
  const ss = String(asLocal.getUTCSeconds()).padStart(2, '0');
  return `${y}-${m}-${d} ${hh}:${mm}:${ss}+00`;
}

function getTimeRangeBounds(timeRange: string): [string, string] {
  const now = new Date();
  // Work in Bangkok offset (+7)
  const bangkokOffsetMs = 7 * 60 * 60 * 1000;
  const nowLocal = new Date(now.getTime() + bangkokOffsetMs);

  const pad = (n: number) => String(n).padStart(2, '0');
  const fmtLocal = (dt: Date) => {
    const y = dt.getUTCFullYear();
    const m = pad(dt.getUTCMonth() + 1);
    const d = pad(dt.getUTCDate());
    const h = pad(dt.getUTCHours());
    const mi = pad(dt.getUTCMinutes());
    const s = pad(dt.getUTCSeconds());
    return `${y}-${m}-${d} ${h}:${mi}:${s}`;
  };

  let startLocal = new Date(nowLocal.getTime());
  let endLocal = new Date(nowLocal.getTime());

  const setStartOfDay = (dt: Date) => {
    dt.setUTCHours(0, 0, 0, 0);
    return dt;
  };

  switch (timeRange) {
    case 'last_6h':
      startLocal = new Date(nowLocal.getTime() - 6 * 3600_000);
      break;
    case 'last_12h':
      startLocal = new Date(nowLocal.getTime() - 12 * 3600_000);
      break;
    case 'last_24h':
      startLocal = new Date(nowLocal.getTime() - 24 * 3600_000);
      break;
    case 'today':
      startLocal = setStartOfDay(new Date(nowLocal.getTime()));
      break;
    case 'yesterday': {
      const y = new Date(nowLocal.getTime() - 24 * 3600_000);
      startLocal = setStartOfDay(new Date(y.getTime()));
      endLocal = new Date(startLocal.getTime());
      endLocal.setUTCHours(23, 59, 59, 0);
      break;
    }
    case 'last_3d':
      startLocal = new Date(nowLocal.getTime() - 3 * 24 * 3600_000);
      break;
    case 'last_7d':
      startLocal = new Date(nowLocal.getTime() - 7 * 24 * 3600_000);
      break;
    case 'this_week': {
      const dow = nowLocal.getUTCDay(); // 0 Sun
      const mondayOffset = dow === 0 ? -6 : 1 - dow;
      startLocal = setStartOfDay(new Date(nowLocal.getTime() + mondayOffset * 24 * 3600_000));
      break;
    }
    case 'last_week': {
      const dow = nowLocal.getUTCDay();
      const mondayOffset = dow === 0 ? -6 : 1 - dow;
      const thisMon = setStartOfDay(new Date(nowLocal.getTime() + mondayOffset * 24 * 3600_000));
      startLocal = new Date(thisMon.getTime() - 7 * 24 * 3600_000);
      endLocal = new Date(startLocal.getTime() + 6 * 24 * 3600_000);
      endLocal.setUTCHours(23, 59, 59, 0);
      break;
    }
    case 'this_month':
      startLocal = setStartOfDay(
        new Date(Date.UTC(nowLocal.getUTCFullYear(), nowLocal.getUTCMonth(), 1)),
      );
      break;
    case 'last_month': {
      const first = new Date(Date.UTC(nowLocal.getUTCFullYear(), nowLocal.getUTCMonth() - 1, 1));
      startLocal = setStartOfDay(first);
      const lastDay = new Date(Date.UTC(nowLocal.getUTCFullYear(), nowLocal.getUTCMonth(), 0));
      endLocal = new Date(lastDay.getTime());
      endLocal.setUTCHours(23, 59, 59, 0);
      break;
    }
    case 'last_3m':
      startLocal = new Date(nowLocal.getTime());
      startLocal.setUTCMonth(startLocal.getUTCMonth() - 3);
      break;
    default:
      startLocal = new Date(nowLocal.getTime() - 24 * 3600_000);
  }

  // Convert Bangkok-wall-clock strings to UTC+00 for query
  return [
    bangkokToUtcString(fmtLocal(startLocal)),
    bangkokToUtcString(fmtLocal(endLocal)),
  ];
}

function resolveTimeBounds(filters: RunFilters): [string, string] | null {
  if (filters.date) {
    return [
      bangkokToUtcString(`${filters.date} 00:00:00`),
      bangkokToUtcString(`${filters.date} 23:59:59`),
    ];
  }
  if (filters.month) {
    const [y, m] = filters.month.split('-').map(Number);
    const last = new Date(Date.UTC(y!, m!, 0)).getUTCDate();
    return [
      bangkokToUtcString(`${filters.month}-01 00:00:00`),
      bangkokToUtcString(`${filters.month}-${String(last).padStart(2, '0')} 23:59:59`),
    ];
  }
  if (filters.year) {
    return [
      bangkokToUtcString(`${filters.year}-01-01 00:00:00`),
      bangkokToUtcString(`${filters.year}-12-31 23:59:59`),
    ];
  }
  if (filters.start_date && filters.end_date) {
    const s =
      filters.start_date.length > 10 ? filters.start_date : `${filters.start_date} 00:00:00`;
    const e = filters.end_date.length > 10 ? filters.end_date : `${filters.end_date} 23:59:59`;
    return [bangkokToUtcString(s), bangkokToUtcString(e)];
  }
  return null;
}

function formatBangkok(value: unknown): string {
  if (value == null || value === '') return '';
  const d = value instanceof Date ? value : new Date(String(value));
  if (Number.isNaN(d.getTime())) return String(value);
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Bangkok',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(d);
  const get = (t: string) => parts.find((p) => p.type === t)?.value ?? '';
  return `${get('day')}/${get('month')}/${get('year')} ${get('hour')}:${get('minute')}`;
}

function convertDatetimeColumn(rows: ExportRow[], column: string): void {
  for (const r of rows) {
    if (r[column] != null && r[column] !== '') {
      r[column] = formatBangkok(r[column]);
    }
  }
}

async function getMetricsDatatypeMap(
  db: Queryable,
  deviceIds: number[],
): Promise<Record<number, Record<string, string>>> {
  if (!deviceIds.length) return {};
  const result = await db.query(
    `SELECT id, metrics FROM devices WHERE id = ANY($1::int[]) AND metrics IS NOT NULL`,
    [deviceIds],
  );
  const out: Record<number, Record<string, string>> = {};
  for (const row of result.rows as { id: number; metrics: unknown }[]) {
    const metrics = parseJson<Array<Record<string, unknown>>>(row.metrics, []);
    out[row.id] = {};
    for (const m of metrics) {
      const key = String(m.key ?? '').trim();
      if (!key) continue;
      out[row.id]![key] = String(m.datatype ?? '').trim();
    }
  }
  return out;
}

async function enrichMetricLabels(db: Queryable, rows: ExportRow[]): Promise<void> {
  const deviceIds = [
    ...new Set(
      rows
        .map((r) => Number(r.device_id ?? 0))
        .filter((id) => id > 0),
    ),
  ];
  const keyToLabel: Record<number, Record<string, string>> = {};
  for (const did of deviceIds) {
    const list = await listMetricsByDevice(db, did);
    keyToLabel[did] = {};
    for (const m of list) keyToLabel[did]![m.key] = m.label;
  }
  for (const r of rows) {
    const did = Number(r.device_id ?? 0);
    const key = String(r.key ?? r.metric_key ?? '').trim();
    r.metric_label =
      did && key && keyToLabel[did]?.[key] ? keyToLabel[did]![key] : key;
  }
}

function formatFloats(
  rows: ExportRow[],
  datatypeMap: Record<number, Record<string, string>>,
): void {
  const valueKeys = ['value', 'value_min', 'value_max', 'value_avg', 'value_first', 'value_last'];
  for (const r of rows) {
    const did = Number(r.device_id ?? 0);
    const key = String(r.key ?? '').trim();
    if (!did || !key) continue;
    if (String(datatypeMap[did]?.[key] ?? '').toLowerCase() !== 'float') continue;
    for (const vk of valueKeys) {
      if (r[vk] != null && r[vk] !== '' && !Number.isNaN(Number(r[vk]))) {
        r[vk] = Math.round(Number(r[vk]) * 100) / 100;
      }
    }
  }
}

function pairClauses(
  selected: DeviceMetricPair[],
  deviceCol: string,
  metricCol: string,
  startIndex: number,
): { sql: string; values: unknown[]; next: number } {
  const parts: string[] = [];
  const values: unknown[] = [];
  let i = startIndex;
  for (const p of selected) {
    const pid = Number(p.device_id ?? 0);
    const metric = String(p.metric ?? '').trim();
    if (!pid || !metric) continue;
    parts.push(`(${deviceCol} = $${i} AND ${metricCol} = $${i + 1})`);
    values.push(pid, metric);
    i += 2;
  }
  if (!parts.length) return { sql: '', values: [], next: startIndex };
  return { sql: `(${parts.join(' OR ')})`, values, next: i };
}

async function fetchDeviceData(
  db: Queryable,
  filters: RunFilters,
  selected: DeviceMetricPair[],
  timeInterval: string | null,
  display: string[],
  sortOrder: 'asc' | 'desc',
): Promise<ExportRow[]> {
  const bounds = resolveTimeBounds(filters);
  const useAggregate = Boolean(timeInterval && display.length && bounds);

  if (useAggregate && bounds) {
    return fetchDeviceDataAggregated(
      db,
      filters,
      bounds[0],
      bounds[1],
      selected,
      timeInterval!,
      display,
      sortOrder,
    );
  }

  const values: unknown[] = [];
  let idx = 1;
  const where: string[] = [];

  const pairs = pairClauses(selected, 'dd.device_id', 'dd.key', idx);
  if (pairs.sql) {
    where.push(pairs.sql);
    values.push(...pairs.values);
    idx = pairs.next;
  } else {
    if (filters.device_id) {
      where.push(`dd.device_id = $${idx++}`);
      values.push(filters.device_id);
    }
    if (filters.metric) {
      where.push(`dd.key = $${idx++}`);
      values.push(filters.metric);
    }
  }
  if (filters.room) {
    where.push(`d.location = $${idx++}`);
    values.push(filters.room);
  }
  if (bounds) {
    where.push(`dd.timestamp >= $${idx++}`);
    values.push(bounds[0]);
    where.push(`dd.timestamp <= $${idx++}`);
    values.push(bounds[1]);
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const dir = sortOrder === 'asc' ? 'ASC' : 'DESC';
  const result = await db.query(
    `SELECT dd.id, dd.device_id, dd.key, dd.value, dd.timestamp, d.name AS device_name
     FROM device_data dd
     LEFT JOIN devices d ON d.id = dd.device_id
     ${whereSql}
     ORDER BY dd.timestamp ${dir}
     LIMIT ${MAX_ROWS}`,
    values,
  );
  const rows = result.rows as ExportRow[];
  await enrichMetricLabels(db, rows);
  return rows;
}

async function fetchDeviceDataAggregated(
  db: Queryable,
  filters: RunFilters,
  startDt: string,
  endDt: string,
  selected: DeviceMetricPair[],
  timeInterval: string,
  display: string[],
  sortOrder: 'asc' | 'desc',
): Promise<ExportRow[]> {
  const sec = BUCKET_SECONDS[timeInterval] ?? 3600;
  const selects = [
    'dd.device_id',
    'dd.key',
    `to_timestamp(floor(extract(epoch from dd.timestamp) / $1) * $2) AS bucket_ts`,
    'MAX(d.name) AS device_name',
  ];
  const values: unknown[] = [sec, sec, startDt, endDt];
  let idx = 5;

  if (display.includes('min')) selects.push('MIN(dd.value) AS value_min');
  if (display.includes('max')) selects.push('MAX(dd.value) AS value_max');
  if (display.includes('average')) selects.push('AVG(dd.value) AS value_avg');
  if (display.includes('first')) {
    selects.push('(array_agg(dd.value ORDER BY dd.timestamp ASC))[1] AS value_first');
  }
  if (display.includes('last')) {
    selects.push('(array_agg(dd.value ORDER BY dd.timestamp DESC))[1] AS value_last');
  }

  const where: string[] = ['dd.timestamp >= $3', 'dd.timestamp <= $4'];
  if (filters.room) {
    where.push(`d.location = $${idx++}`);
    values.push(filters.room);
  }
  const pairs = pairClauses(selected, 'dd.device_id', 'dd.key', idx);
  if (pairs.sql) {
    where.push(pairs.sql);
    values.push(...pairs.values);
    idx = pairs.next;
  } else {
    if (filters.device_id) {
      where.push(`dd.device_id = $${idx++}`);
      values.push(filters.device_id);
    }
    if (filters.metric) {
      where.push(`dd.key = $${idx++}`);
      values.push(filters.metric);
    }
  }

  // Reuse $1/$2 in GROUP BY so Postgres treats SELECT bucket_ts and GROUP BY as the same
  // expression (PHP binds identical literals; node-pg keeps distinct $n otherwise → 42803).
  const dir = sortOrder === 'asc' ? 'ASC' : 'DESC';
  const result = await db.query(
    `SELECT ${selects.join(', ')}
     FROM device_data dd
     LEFT JOIN devices d ON d.id = dd.device_id
     WHERE ${where.join(' AND ')}
     GROUP BY dd.device_id, dd.key, to_timestamp(floor(extract(epoch from dd.timestamp) / $1) * $2)
     ORDER BY bucket_ts ${dir}
     LIMIT ${MAX_ROWS}`,
    values,
  );
  const rows = (result.rows as ExportRow[]).map((r) => {
    const out = { ...r };
    if (out.bucket_ts != null) {
      out.timestamp = out.bucket_ts;
      delete out.bucket_ts;
    }
    return out;
  });
  await enrichMetricLabels(db, rows);
  return rows;
}

async function fetchAlarmLogs(
  db: Queryable,
  filters: RunFilters,
  selected: DeviceMetricPair[],
  sortOrder: 'asc' | 'desc',
): Promise<ExportRow[]> {
  const values: unknown[] = [];
  let idx = 1;
  const where: string[] = [];
  const pairs = pairClauses(selected, 'al.device_id', 'al.metric_key', idx);
  if (pairs.sql) {
    where.push(pairs.sql);
    values.push(...pairs.values);
    idx = pairs.next;
  } else {
    if (filters.device_id) {
      where.push(`al.device_id = $${idx++}`);
      values.push(filters.device_id);
    }
    if (filters.metric) {
      where.push(`al.metric_key = $${idx++}`);
      values.push(filters.metric);
    }
  }
  if (filters.room) {
    where.push(`d.location = $${idx++}`);
    values.push(filters.room);
  }
  const bounds = resolveTimeBounds(filters);
  if (bounds) {
    where.push(`al.created_at >= $${idx++}`);
    values.push(bounds[0]);
    where.push(`al.created_at <= $${idx++}`);
    values.push(bounds[1]);
  }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const dir = sortOrder === 'asc' ? 'ASC' : 'DESC';
  const result = await db.query(
    `SELECT al.id, al.device_id, al.metric_key, al.value, al.message, al.severity, al.status,
            al.created_at, d.name AS device_name
     FROM alarm_logs al
     LEFT JOIN devices d ON d.id = al.device_id
     ${whereSql}
     ORDER BY al.created_at ${dir}
     LIMIT ${MAX_ROWS}`,
    values,
  );
  const rows = result.rows as ExportRow[];
  for (const r of rows) {
    if (r.metric_key != null && r.key == null) r.key = r.metric_key;
  }
  await enrichMetricLabels(db, rows);
  return rows;
}

async function fetchEventLogs(db: Queryable, filters: RunFilters): Promise<ExportRow[]> {
  const values: unknown[] = [];
  let idx = 1;
  const where: string[] = [];
  if (filters.device_id) {
    where.push(`el.entity_type = 'Device'`);
    where.push(`el.entity_id = $${idx++}`);
    values.push(filters.device_id);
  }
  if (filters.action_type) {
    where.push(`el.action_type = $${idx++}`);
    values.push(filters.action_type);
  }
  if (filters.entity_type) {
    where.push(`el.entity_type = $${idx++}`);
    values.push(filters.entity_type);
  }
  const bounds = resolveTimeBounds(filters);
  if (bounds) {
    where.push(`el.created_at >= $${idx++}`);
    values.push(bounds[0]);
    where.push(`el.created_at <= $${idx++}`);
    values.push(bounds[1]);
  }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const result = await db.query(
    `SELECT el.id, el.user_id, el.action_type, el.entity_type, el.entity_id, el.message,
            el.created_at, el.metadata
     FROM event_logs el
     ${whereSql}
     ORDER BY el.created_at DESC
     LIMIT ${MAX_ROWS}`,
    values,
  );
  return result.rows as ExportRow[];
}

export async function fetchReportData(
  db: Queryable,
  template: ReportTemplateRow,
  filters: RunFilters,
): Promise<{ rows: ExportRow[]; dataSource: string; count: number }> {
  const config = template.config ?? {};
  let dataSource = config.data_source ?? 'device_data';
  if (!['device_data', 'alarm_logs', 'event_logs'].includes(dataSource)) {
    dataSource = 'device_data';
  }
  const filtersConfig = (config.filters ?? {}) as Record<string, unknown>;
  const selected = Array.isArray(filtersConfig.selected_device_metrics)
    ? (filtersConfig.selected_device_metrics as DeviceMetricPair[])
    : [];
  const sortOrder = filtersConfig.sort_order === 'asc' ? 'asc' : 'desc';

  const runFilters: RunFilters = { ...filters };
  const timeRange = filtersConfig.time_range as string | undefined;
  if (
    timeRange &&
    timeRange !== 'input_date_range' &&
    (dataSource === 'device_data' || dataSource === 'alarm_logs')
  ) {
    const [start, end] = getTimeRangeBounds(timeRange);
    runFilters.start_date = start;
    runFilters.end_date = end;
    runFilters.date = null;
    runFilters.month = null;
    runFilters.year = null;
  }

  const timeInterval = (filtersConfig.time_interval as string) || null;
  let display = Array.isArray(filtersConfig.display)
    ? (filtersConfig.display as string[])
    : [];
  if (!display.length && (dataSource === 'device_data' || dataSource === 'alarm_logs')) {
    display = ['min', 'max', 'average'];
  }

  let rows: ExportRow[];
  if (dataSource === 'alarm_logs') {
    rows = await fetchAlarmLogs(db, runFilters, selected, sortOrder);
    convertDatetimeColumn(rows, 'created_at');
  } else if (dataSource === 'event_logs') {
    rows = await fetchEventLogs(db, runFilters);
    convertDatetimeColumn(rows, 'created_at');
  } else {
    rows = await fetchDeviceData(
      db,
      runFilters,
      selected,
      timeInterval,
      display,
      sortOrder,
    );
    const deviceIds = [
      ...new Set([
        ...rows.map((r) => Number(r.device_id ?? 0)).filter(Boolean),
        ...selected.map((p) => Number(p.device_id ?? 0)).filter(Boolean),
      ]),
    ];
    const datatypeMap = await getMetricsDatatypeMap(db, deviceIds);
    formatFloats(rows, datatypeMap);
    convertDatetimeColumn(rows, 'timestamp');
  }

  return { rows, dataSource, count: rows.length };
}

function transformToPivot(
  rows: ExportRow[],
  displayColumns: ExportColumn[],
  selected: DeviceMetricPair[],
  sortOrder: 'asc' | 'desc',
): { cols: ExportColumn[]; rows: ExportRow[] } {
  const pivotValueKeys = ['value_min', 'value_max', 'value_avg', 'value_first', 'value_last'];
  const pivotDefaultLabels: Record<string, string> = {
    value_min: 'Min',
    value_max: 'Max',
    value_avg: 'Average',
    value_first: 'First',
    value_last: 'Last',
  };
  const idKeys = ['device_name', 'metric_label', 'device_id', 'key', 'metric_key'];

  let subCols = displayColumns
    .filter((c) => pivotValueKeys.includes(c.key))
    .map((c) => ({ key: c.key, label: c.label || pivotDefaultLabels[c.key] || c.key }));
  if (!subCols.length) {
    subCols = ['value_min', 'value_max', 'value_avg'].map((k) => ({
      key: k,
      label: pivotDefaultLabels[k]!,
    }));
  }

  let idOrder = idKeys;
  const ordered = displayColumns.map((c) => c.key).filter((k) => idKeys.includes(k));
  if (ordered.length) idOrder = ordered;

  const metricInfo: Record<string, { header: string }> = {};
  const seenMetric: Record<string, boolean> = {};
  for (const row of rows) {
    const deviceName = String(row.device_name ?? '').trim();
    const metricName = String(row.metric_label ?? row.key ?? row.metric_key ?? '').trim();
    const deviceId = row.device_id != null ? String(row.device_id) : '';
    const metricKey = String(row.key ?? row.metric_key ?? '').trim();
    const parts: string[] = [];
    for (const ik of idOrder) {
      if (ik === 'device_name' && deviceName) parts.push(deviceName);
      else if (ik === 'metric_label' && metricName) parts.push(metricName);
      else if (ik === 'device_id' && deviceId) parts.push(deviceId);
      else if ((ik === 'key' || ik === 'metric_key') && metricKey) parts.push(metricKey);
    }
    const header = parts.join(' ') || metricName || metricKey || deviceName;
    const idParts: string[] = [];
    if (deviceId) idParts.push(deviceId);
    if (metricKey) idParts.push(metricKey);
    else if (metricName) idParts.push(metricName);
    const metricId = idParts.join('|') || metricName || metricKey;
    if (!metricId) continue;
    if (!seenMetric[metricId]) {
      seenMetric[metricId] = true;
      metricInfo[metricId] = { header: header || metricId };
    }
  }

  const metricOrder: string[] = [];
  for (const dm of selected) {
    const did = dm.device_id != null ? String(dm.device_id) : '';
    const mkey = String(dm.metric ?? '').trim();
    const mid = did && mkey ? `${did}|${mkey}` : mkey || did;
    if (mid && seenMetric[mid] && !metricOrder.includes(mid)) metricOrder.push(mid);
  }
  for (const mid of Object.keys(seenMetric)) {
    if (!metricOrder.includes(mid)) metricOrder.push(mid);
  }

  let timeLabel = 'Time';
  for (const c of displayColumns) {
    if (c.key === 'timestamp') {
      timeLabel = c.label || 'Time';
      break;
    }
  }

  if (!metricOrder.length) {
    return { cols: [{ key: 'timestamp', label: timeLabel }], rows: [] };
  }

  const cols: ExportColumn[] = [{ key: 'timestamp', label: timeLabel }];
  for (const metricId of metricOrder) {
    const header = metricInfo[metricId]?.header ?? metricId;
    for (const sc of subCols) {
      cols.push({ key: `${metricId}_${sc.key}`, label: `${header} ${sc.label}` });
    }
  }

  const pivotByTs: Record<string, Record<string, Record<string, unknown>>> = {};
  for (const row of rows) {
    const ts = String(row.timestamp ?? '');
    const deviceId = row.device_id != null ? String(row.device_id) : '';
    const metricKey = String(row.key ?? row.metric_key ?? '').trim();
    const metricName = String(row.metric_label ?? metricKey).trim();
    const idParts: string[] = [];
    if (deviceId) idParts.push(deviceId);
    if (metricKey) idParts.push(metricKey);
    else if (metricName) idParts.push(metricName);
    const metricId = idParts.join('|') || metricName || metricKey;
    if (!ts || !metricId) continue;
    if (!pivotByTs[ts]) pivotByTs[ts] = {};
    if (!pivotByTs[ts]![metricId]) pivotByTs[ts]![metricId] = {};
    for (const sc of subCols) {
      pivotByTs[ts]![metricId]![sc.key] = row[sc.key] ?? '';
    }
  }

  const timestamps = Object.keys(pivotByTs).sort((a, b) =>
    sortOrder === 'asc' ? a.localeCompare(b) : b.localeCompare(a),
  );
  const outRows: ExportRow[] = timestamps.map((ts) => {
    const r: ExportRow = { timestamp: ts };
    for (const metricId of metricOrder) {
      const cell = pivotByTs[ts]?.[metricId] ?? {};
      for (const sc of subCols) {
        r[`${metricId}_${sc.key}`] = cell[sc.key] ?? '';
      }
    }
    return r;
  });

  return { cols, rows: outRows };
}

export async function listReportFiles(
  db: Queryable,
  page: number,
  pageSize: number,
  filters?: {
    templateId?: number | null;
    format?: string | null;
    dateFrom?: string | null;
    dateTo?: string | null;
  },
): Promise<{ files: ReportFileRow[]; total: number }> {
  const where: string[] = [];
  const values: unknown[] = [];
  let idx = 1;
  if (filters?.templateId) {
    where.push(`f.template_id = $${idx++}`);
    values.push(filters.templateId);
  }
  if (filters?.format) {
    where.push(`f.export_format = $${idx++}`);
    values.push(filters.format);
  }
  if (filters?.dateFrom) {
    where.push(`f.exported_at >= $${idx++}`);
    values.push(`${filters.dateFrom} 00:00:00`);
  }
  if (filters?.dateTo) {
    where.push(`f.exported_at <= $${idx++}`);
    values.push(`${filters.dateTo} 23:59:59`);
  }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const count = await db.query(
    `SELECT COUNT(*)::int AS total FROM report_export_files f ${whereSql}`,
    values,
  );
  const total = Number((count.rows[0] as { total: number }).total);
  const result = await db.query(
    `SELECT f.id, f.template_id, f.file_name, f.export_format, f.file_size, f.exported_at,
            f.schedule_id, t.name AS template_name
     FROM report_export_files f
     LEFT JOIN report_templates t ON t.id = f.template_id
     ${whereSql}
     ORDER BY f.exported_at DESC NULLS LAST, f.id DESC
     LIMIT $${idx} OFFSET $${idx + 1}`,
    [...values, pageSize, (page - 1) * pageSize],
  );
  const files = (result.rows as {
    id: number;
    template_id: number | null;
    file_name: string | null;
    export_format: string | null;
    file_size: number | null;
    exported_at: Date | string | null;
    schedule_id: number | null;
    template_name: string | null;
  }[]).map((row) => ({
    id: row.id,
    templateId: row.template_id,
    templateName: row.template_name ?? '',
    fileName: row.file_name ?? '',
    exportFormat: row.export_format ?? '',
    fileSize: Number(row.file_size ?? 0),
    exportedAt: iso(row.exported_at),
    scheduleId: row.schedule_id,
  }));
  return { files, total };
}

export async function openReportFile(
  db: Queryable,
  id: number,
): Promise<{ stream: ReturnType<typeof createReadStream>; fileName: string } | null> {
  const result = await db.query(
    `SELECT file_name, file_path FROM report_export_files WHERE id = $1`,
    [id],
  );
  const row = result.rows[0] as { file_name: string | null; file_path: string | null } | undefined;
  if (!row?.file_path) return null;
  if (row.file_path.includes('..')) return null;
  const exportRoot = getExportRoot();
  const target = path.resolve(exportRoot, row.file_path);
  if (!target.startsWith(`${exportRoot}${path.sep}`)) return null;
  try {
    await stat(target);
  } catch {
    return null;
  }
  return { stream: createReadStream(target), fileName: row.file_name || path.basename(target) };
}

export async function deleteReportFile(db: Queryable, id: number): Promise<boolean> {
  const result = await db.query(
    `SELECT file_path FROM report_export_files WHERE id = $1`,
    [id],
  );
  const row = result.rows[0] as { file_path: string | null } | undefined;
  if (!row) return false;
  if (row.file_path && !row.file_path.includes('..')) {
    const exportRoot = getExportRoot();
    const target = path.resolve(exportRoot, row.file_path);
    if (target.startsWith(`${exportRoot}${path.sep}`)) {
      try {
        await unlink(target);
      } catch {
        // ignore missing file
      }
    }
  }
  const del = await db.query(`DELETE FROM report_export_files WHERE id = $1`, [id]);
  return (del.rowCount ?? 0) > 0;
}

export type ExportResult =
  | { success: true; fileId?: number; fileName: string; downloadUrl?: string; buffer?: Buffer }
  | { success: false; message: string };

export async function buildAndSaveExport(
  db: Queryable,
  template: ReportTemplateRow,
  filters: RunFilters,
  format: 'pdf' | 'excel' | 'csv',
  options: {
    scheduleId?: number | null;
    exportedBy?: number | null;
    persist?: boolean;
  } = {},
): Promise<ExportResult> {
  const persist = options.persist !== false;
  const { rows: rawRows, dataSource } = await fetchReportData(db, template, filters);
  if (!rawRows.length) {
    return { success: false, message: 'No data to export' };
  }

  const editor = template.config.editor ?? {};
  const filtersConfig = (template.config.filters ?? {}) as Record<string, unknown>;
  const selected = Array.isArray(filtersConfig.selected_device_metrics)
    ? (filtersConfig.selected_device_metrics as DeviceMetricPair[])
    : [];
  const sortOrder = filtersConfig.sort_order === 'asc' ? 'asc' : 'desc';
  let displayColumns: ExportColumn[] = Array.isArray(editor.display_columns)
    ? editor.display_columns.map((c) => ({
        key: c.key,
        label: c.label || c.key,
      }))
    : [];
  if (!displayColumns.length) {
    displayColumns = [
      { key: 'timestamp', label: 'Time' },
      { key: 'device_name', label: 'Device' },
      { key: 'key', label: 'Metric' },
      { key: 'value', label: 'Value' },
    ];
  }

  const tableLayout = editor.table_layout === 'pivot' ? 'pivot' : 'rows';
  const usePivot = tableLayout === 'pivot' && dataSource === 'device_data';

  let cols: ExportColumn[];
  let rows: ExportRow[];
  if (usePivot) {
    const pivoted = transformToPivot(rawRows, displayColumns, selected, sortOrder);
    cols = pivoted.cols;
    rows = pivoted.rows;
  } else {
    cols = displayColumns;
    rows = rawRows;
  }

  const now = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  const ts = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;
  const subdir = `${now.getFullYear()}/${pad(now.getMonth() + 1)}`;
  const slug = slugifyTemplateName(template.name);

  let content: Buffer;
  let ext: string;
  let exportFormat = format;
  if (format === 'csv') {
    content = buildCsvContent(cols, rows, editor.csv_delimiter ?? ',');
    ext = 'csv';
  } else if (format === 'excel') {
    content = buildExcelXml(cols, rows, editor.excel_sheet_name ?? 'Sheet1');
    ext = 'xls';
  } else {
    const printTitle =
      (editor.pdf_title ?? '').trim() ||
      (editor.report_title ?? '').trim() ||
      template.name;
    content = buildSimplePdf(
      printTitle,
      cols,
      rows,
      editor.pdf_orientation === 'landscape' ? 'landscape' : 'portrait',
    );
    ext = 'pdf';
  }

  const filename = `${slug}_${ts}.${ext}`;
  const relpath = `${subdir}/${filename}`;

  if (!persist) {
    return { success: true, fileName: filename, buffer: content };
  }

  const fileSize = await writeExportBuffer(getExportRoot(), relpath, content);
  const insert = await db.query(
    `INSERT INTO report_export_files
       (template_id, schedule_id, file_path, file_name, file_size, export_format, exported_at, exported_by)
     VALUES ($1, $2, $3, $4, $5, $6, NOW(), $7)
     RETURNING id`,
    [
      template.id,
      options.scheduleId ?? null,
      relpath,
      filename,
      fileSize,
      exportFormat,
      options.exportedBy ?? null,
    ],
  );
  const fileId = Number((insert.rows[0] as { id: number }).id);
  return {
    success: true,
    fileId,
    fileName: filename,
    downloadUrl: `/api/v1/report-files/${fileId}`,
  };
}

function computeNextRun(
  scheduleType: string,
  scheduleConfig: { time?: string; weekday?: number; day?: number },
  timezone: string,
  from: Date = new Date(),
): string {
  const tz = timezone || 'Asia/Bangkok';
  // Approximate using Asia/Bangkok offset when tz is Bangkok; otherwise use UTC offset of now in that zone via Intl
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: tz,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  });
  const parts = formatter.formatToParts(from);
  const get = (t: string) => Number(parts.find((p) => p.type === t)?.value ?? 0);
  const nowLocal = {
    y: get('year'),
    m: get('month'),
    d: get('day'),
    h: get('hour'),
    mi: get('minute'),
    s: get('second'),
  };

  let time = scheduleConfig.time || '08:00';
  if (!/^\d{1,2}:\d{2}/.test(time)) time = '08:00';
  const hour = Number(time.slice(0, 2));
  const minute = Number(time.slice(3, 5));
  const type = scheduleType || 'daily';
  const day = Math.min(28, Math.max(1, Number(scheduleConfig.day ?? 1)));

  const localToUtcIso = (y: number, m: number, d: number, h: number, mi: number): string => {
    // Build a date string and interpret in target timezone by comparing offsets
    const guess = new Date(Date.UTC(y, m - 1, d, h, mi, 0));
    // Correct for timezone: format guess in tz and adjust
    const p2 = formatter.formatToParts(guess);
    const g = (t: string) => Number(p2.find((p) => p.type === t)?.value ?? 0);
    const asLocal = Date.UTC(g('year'), g('month') - 1, g('day'), g('hour'), g('minute'), 0);
    const wanted = Date.UTC(y, m - 1, d, h, mi, 0);
    const corrected = new Date(guess.getTime() + (wanted - asLocal));
    return corrected.toISOString();
  };

  const nowStamp = Date.UTC(nowLocal.y, nowLocal.m - 1, nowLocal.d, nowLocal.h, nowLocal.mi, nowLocal.s);

  if (type === 'weekly') {
    let weekday = Number(scheduleConfig.weekday ?? 1);
    if (weekday < 1 || weekday > 7) weekday = 1;
    // ISO weekday: getDay 0=Sun -> 7
    const jsDow = new Date(Date.UTC(nowLocal.y, nowLocal.m - 1, nowLocal.d)).getUTCDay();
    const isoNow = jsDow === 0 ? 7 : jsDow;
    let diff = (weekday - isoNow + 7) % 7;
    let target = Date.UTC(nowLocal.y, nowLocal.m - 1, nowLocal.d + diff, hour, minute, 0);
    if (diff === 0 && target <= nowStamp) {
      diff = 7;
      target = Date.UTC(nowLocal.y, nowLocal.m - 1, nowLocal.d + diff, hour, minute, 0);
    }
    const dt = new Date(target);
    return localToUtcIso(
      dt.getUTCFullYear(),
      dt.getUTCMonth() + 1,
      dt.getUTCDate(),
      hour,
      minute,
    );
  }

  if (type === 'monthly') {
    let y = nowLocal.y;
    let m = nowLocal.m;
    let target = Date.UTC(y, m - 1, day, hour, minute, 0);
    if (target <= nowStamp) {
      m += 1;
      if (m > 12) {
        m = 1;
        y += 1;
      }
      target = Date.UTC(y, m - 1, day, hour, minute, 0);
    }
    const dt = new Date(target);
    return localToUtcIso(
      dt.getUTCFullYear(),
      dt.getUTCMonth() + 1,
      day,
      hour,
      minute,
    );
  }

  // daily
  let target = Date.UTC(nowLocal.y, nowLocal.m - 1, nowLocal.d, hour, minute, 0);
  if (target <= nowStamp) {
    target = Date.UTC(nowLocal.y, nowLocal.m - 1, nowLocal.d + 1, hour, minute, 0);
  }
  const dt = new Date(target);
  return localToUtcIso(
    dt.getUTCFullYear(),
    dt.getUTCMonth() + 1,
    dt.getUTCDate(),
    hour,
    minute,
  );
}

function mapSchedule(row: {
  id: number;
  template_id: number;
  template_name?: string | null;
  name: string | null;
  is_active: boolean | null;
  schedule_type: string | null;
  schedule_config: unknown;
  timezone: string | null;
  export_format: string | null;
  recipients: unknown;
  next_run_at: Date | string | null;
  last_run_at: Date | string | null;
}): ReportScheduleRow {
  return {
    id: row.id,
    templateId: row.template_id,
    templateName: row.template_name ?? '',
    name: row.name ?? '',
    isActive: Boolean(row.is_active),
    scheduleType: row.schedule_type ?? 'daily',
    scheduleConfig: parseJson(row.schedule_config, {}),
    timezone: row.timezone ?? 'Asia/Bangkok',
    exportFormat: row.export_format ?? 'pdf',
    recipients: parseJson<string[]>(row.recipients, []),
    nextRunAt: iso(row.next_run_at),
    lastRunAt: iso(row.last_run_at),
  };
}

export async function listReportSchedules(db: Queryable): Promise<ReportScheduleRow[]> {
  const result = await db.query(
    `SELECT s.*, t.name AS template_name
     FROM report_schedules s
     LEFT JOIN report_templates t ON t.id = s.template_id
     ORDER BY s.next_run_at ASC NULLS LAST, s.id ASC`,
  );
  return (result.rows as Parameters<typeof mapSchedule>[0][]).map(mapSchedule);
}

export async function getReportSchedule(
  db: Queryable,
  id: number,
): Promise<ReportScheduleRow | null> {
  const result = await db.query(
    `SELECT s.*, t.name AS template_name
     FROM report_schedules s
     LEFT JOIN report_templates t ON t.id = s.template_id
     WHERE s.id = $1 LIMIT 1`,
    [id],
  );
  const row = result.rows[0] as Parameters<typeof mapSchedule>[0] | undefined;
  return row ? mapSchedule(row) : null;
}

export type SaveScheduleInput = {
  templateId: number;
  name: string;
  isActive: boolean;
  scheduleType: string;
  scheduleConfig: { time?: string; weekday?: number; day?: number };
  timezone?: string;
  exportFormat: string;
  recipients?: string[];
  createdBy?: number | null;
};

export async function createReportSchedule(
  db: Queryable,
  input: SaveScheduleInput,
): Promise<ReportScheduleRow> {
  const timezone = input.timezone || 'Asia/Bangkok';
  const nextRun = computeNextRun(input.scheduleType, input.scheduleConfig, timezone);
  const result = await db.query(
    `INSERT INTO report_schedules
       (template_id, name, is_active, schedule_type, schedule_config, timezone,
        export_format, recipients, report_params, next_run_at, created_by, created_at, updated_at)
     VALUES ($1, $2, $3, $4, $5::jsonb, $6, $7, $8::jsonb, '{}'::jsonb, $9, $10, NOW(), NOW())
     RETURNING id`,
    [
      input.templateId,
      input.name,
      input.isActive,
      input.scheduleType || 'daily',
      JSON.stringify(input.scheduleConfig ?? {}),
      timezone,
      ['pdf', 'excel', 'csv'].includes(input.exportFormat) ? input.exportFormat : 'pdf',
      JSON.stringify(input.recipients ?? []),
      nextRun,
      input.createdBy ?? null,
    ],
  );
  const id = Number((result.rows[0] as { id: number }).id);
  const saved = await getReportSchedule(db, id);
  return saved!;
}

export async function updateReportSchedule(
  db: Queryable,
  id: number,
  input: SaveScheduleInput,
): Promise<ReportScheduleRow | null> {
  const timezone = input.timezone || 'Asia/Bangkok';
  const nextRun = computeNextRun(input.scheduleType, input.scheduleConfig, timezone);
  const result = await db.query(
    `UPDATE report_schedules
     SET template_id = $2,
         name = $3,
         is_active = $4,
         schedule_type = $5,
         schedule_config = $6::jsonb,
         timezone = $7,
         export_format = $8,
         recipients = $9::jsonb,
         next_run_at = $10,
         updated_at = NOW()
     WHERE id = $1
     RETURNING id`,
    [
      id,
      input.templateId,
      input.name,
      input.isActive,
      input.scheduleType || 'daily',
      JSON.stringify(input.scheduleConfig ?? {}),
      timezone,
      ['pdf', 'excel', 'csv'].includes(input.exportFormat) ? input.exportFormat : 'pdf',
      JSON.stringify(input.recipients ?? []),
      nextRun,
    ],
  );
  if (!result.rows[0]) return null;
  return getReportSchedule(db, id);
}

export async function deleteReportSchedule(db: Queryable, id: number): Promise<boolean> {
  const result = await db.query(`DELETE FROM report_schedules WHERE id = $1`, [id]);
  return (result.rowCount ?? 0) > 0;
}

export { getExportRoot };
