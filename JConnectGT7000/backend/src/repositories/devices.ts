import type { Queryable } from '../db.js';

export interface DeviceListQuery {
  search?: string;
  status?: string;
  template?: string;
  location?: string;
  page: number;
  pageSize: number;
}

export interface DeviceSummary {
  id: number;
  name: string;
  serialNumber: string;
  location: string;
  description: string;
  isActive: boolean;
  deviceTemplateId: number;
  templateName: string;
  brand: string;
  model: string;
  image: string | null;
  imageUrl: string | null;
  templateImage: string | null;
  categoryId: number | null;
  categoryName: string;
  customFields: Record<string, unknown>;
  deviceRole: string;
}

export interface DeviceDetail extends DeviceSummary {
  communicationConfig: unknown;
  metrics: unknown;
  config: unknown;
  gatewayDeviceId: number | null;
  snapshotTriggers: unknown;
  /** Protocol from communication_config or template (for form/wizard). */
  protocol: string;
  templateImageUrl: string | null;
}

export interface DeviceWriteInput {
  name: string;
  deviceTemplateId: number;
  serialNumber: string;
  location: string;
  description: string;
  isActive: boolean;
  deviceRole: 'native_device' | 'gateway' | 'endpoint';
  gatewayDeviceId: number | null;
  communicationConfig: unknown;
  metrics: unknown | null;
  customFields: Record<string, unknown>;
  snapshotTriggers: unknown | null;
  image: string | null;
}

export interface MetricSourceDevice {
  id: number;
  name: string;
  metrics: Array<{
    key: string;
    label: string;
    enable: boolean | string;
    triggers?: unknown[];
    calc?: unknown;
    source?: unknown;
  }>;
}

export interface DeviceMetricKey {
  key: string;
  label: string;
  unit: string;
  datatype: string;
  enable: boolean | string;
}

interface DeviceRow {
  id: number;
  name: string | null;
  serial_number: string | null;
  location: string | null;
  description: string | null;
  is_active: boolean | null;
  device_template_id: number;
  template_name: string | null;
  brand: string | null;
  model: string | null;
  image?: string | null;
  template_image?: string | null;
  template_protocol?: string | null;
  communication_config?: unknown;
  metrics?: unknown;
  config?: unknown;
  category_id?: number | null;
  category_name?: string | null;
}

function protocolOf(communication: unknown, templateProtocol?: string | null): string {
  const comm = asObject(communication);
  const fromComm = typeof comm.protocol === 'string' ? comm.protocol.trim() : '';
  if (fromComm) return fromComm;
  return templateProtocol ?? '';
}

function filters(query: DeviceListQuery): { where: string; values: unknown[] } {
  const where: string[] = ['d.is_deleted = false'];
  const values: unknown[] = [];
  if (query.status === 'active') {
    where.push('d.is_active = true');
  } else if (query.status === 'inactive') {
    where.push('d.is_active = false');
  }
  if (query.template) {
    values.push(query.template);
    where.push(`dt.name = $${values.length}`);
  }
  if (query.location) {
    values.push(query.location);
    where.push(`d.location = $${values.length}`);
  }
  if (query.search) {
    values.push(`%${query.search}%`);
    const slot = `$${values.length}`;
    where.push(`(
      d.name ILIKE ${slot} OR d.serial_number ILIKE ${slot} OR d.location ILIKE ${slot}
      OR d.description ILIKE ${slot} OR dt.name ILIKE ${slot} OR d.config::text ILIKE ${slot}
    )`);
  }
  return { where: where.join(' AND '), values };
}

function asObject(value: unknown): Record<string, unknown> {
  if (!value) return {};
  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value) as unknown;
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
        ? (parsed as Record<string, unknown>)
        : {};
    } catch {
      return {};
    }
  }
  if (typeof value === 'object' && !Array.isArray(value)) {
    return value as Record<string, unknown>;
  }
  return {};
}

function asArray(value: unknown): unknown[] {
  if (!value) return [];
  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value) as unknown;
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
  return Array.isArray(value) ? value : [];
}

function deviceImageUrl(image: string | null | undefined): string | null {
  if (!image) return null;
  return `/api/v1/uploads/devices/${encodeURIComponent(image)}`;
}

function templateImageUrl(image: string | null | undefined): string | null {
  if (!image) return null;
  return `/api/v1/uploads/device-templates/${encodeURIComponent(image)}`;
}

function resolveImageUrl(deviceImage: string | null | undefined, templateImage: string | null | undefined): string | null {
  return deviceImageUrl(deviceImage) ?? templateImageUrl(templateImage);
}

function deviceRoleOf(config: Record<string, unknown>): string {
  const role = String(config.role ?? 'native_device').toLowerCase();
  if (role === 'gateway' || role === 'endpoint' || role === 'native_device') {
    return role;
  }
  return 'native_device';
}

function gatewayIdOf(config: Record<string, unknown>): number | null {
  const raw = config.via_gateway;
  const id = typeof raw === 'number' ? raw : Number(raw);
  return Number.isInteger(id) && id > 0 ? id : null;
}

function customFieldsOf(config: Record<string, unknown>): Record<string, unknown> {
  const fields = config.custom_fields;
  if (fields && typeof fields === 'object' && !Array.isArray(fields)) {
    return fields as Record<string, unknown>;
  }
  return {};
}

function toSummary(row: DeviceRow): DeviceSummary {
  const config = asObject(row.config);
  return {
    id: row.id,
    name: row.name ?? '',
    serialNumber: row.serial_number ?? '',
    location: row.location ?? '',
    description: row.description ?? '',
    isActive: row.is_active !== false,
    deviceTemplateId: row.device_template_id,
    templateName: row.template_name ?? '',
    brand: row.brand ?? '',
    model: row.model ?? '',
    image: row.image ?? null,
    imageUrl: resolveImageUrl(row.image, row.template_image),
    templateImage: row.template_image ?? null,
    categoryId: row.category_id ?? null,
    categoryName: row.category_name ?? '',
    customFields: customFieldsOf(config),
    deviceRole: deviceRoleOf(config),
  };
}

const listFrom = `FROM devices d
  LEFT JOIN device_templates dt ON dt.id = d.device_template_id
  LEFT JOIN device_categories dc ON dc.id = d.category_id`;

const listSelect = `SELECT d.id, d.name, d.serial_number, d.location, d.description, d.is_active,
            d.device_template_id, d.image, d.config, d.category_id,
            dt.name AS template_name, dt.image AS template_image,
            dt.device_information->>'Brand' AS brand,
            dt.device_information->>'Model' AS model,
            dc.name AS category_name`;

export async function listDevices(
  db: Queryable,
  query: DeviceListQuery,
): Promise<{ devices: DeviceSummary[]; total: number }> {
  const { where, values } = filters(query);
  const count = await db.query(`SELECT COUNT(*)::int AS total ${listFrom} WHERE ${where}`, values);
  const total = Number((count.rows[0] as { total: number }).total);
  const offset = (query.page - 1) * query.pageSize;
  const rows = await db.query(
    `${listSelect}
     ${listFrom}
     WHERE ${where}
     ORDER BY d.name ASC
     LIMIT $${values.length + 1} OFFSET $${values.length + 2}`,
    [...values, query.pageSize, offset],
  );
  return { devices: (rows.rows as DeviceRow[]).map(toSummary), total };
}

export async function deviceFilters(
  db: Queryable,
): Promise<{ locations: string[]; templates: string[]; categories: string[] }> {
  const locations = await db.query(
    `SELECT DISTINCT location FROM devices
     WHERE is_deleted = false AND location IS NOT NULL AND location <> ''
     ORDER BY location ASC`,
  );
  const templates = await db.query(
    `SELECT DISTINCT dt.name AS template_name
     FROM devices d
     JOIN device_templates dt ON dt.id = d.device_template_id
     WHERE d.is_deleted = false AND dt.name IS NOT NULL AND dt.name <> ''
     ORDER BY dt.name ASC`,
  );
  const categories = await db.query(
    `SELECT DISTINCT dc.name AS category_name
     FROM devices d
     JOIN device_categories dc ON dc.id = d.category_id
     WHERE d.is_deleted = false AND dc.name IS NOT NULL AND dc.name <> ''
     ORDER BY dc.name ASC`,
  );
  return {
    locations: (locations.rows as { location: string }[]).map((row) => row.location),
    templates: (templates.rows as { template_name: string }[]).map((row) => row.template_name),
    categories: (categories.rows as { category_name: string }[]).map((row) => row.category_name),
  };
}

export async function findDevice(db: Queryable, id: number): Promise<DeviceDetail | null> {
  const result = await db.query(
    `SELECT d.id, d.name, d.serial_number, d.location, d.description, d.is_active, d.device_template_id,
            d.communication_config, d.metrics, d.config, d.category_id, d.image,
            dt.name AS template_name, dt.image AS template_image, dt.protocol AS template_protocol,
            dt.device_information->>'Brand' AS brand,
            dt.device_information->>'Model' AS model,
            dc.name AS category_name
     FROM devices d
     LEFT JOIN device_templates dt ON dt.id = d.device_template_id
     LEFT JOIN device_categories dc ON dc.id = d.category_id
     WHERE d.id = $1 AND d.is_deleted = false`,
    [id],
  );
  const row = result.rows[0] as DeviceRow | undefined;
  if (!row) {
    return null;
  }
  const config = asObject(row.config);
  const communicationConfig = row.communication_config ?? {};
  return {
    ...toSummary(row),
    communicationConfig,
    metrics: asArray(row.metrics),
    config,
    gatewayDeviceId: gatewayIdOf(config),
    snapshotTriggers: Array.isArray(config.snapshot_triggers) ? config.snapshot_triggers : [],
    protocol: protocolOf(communicationConfig, row.template_protocol),
    templateImageUrl: templateImageUrl(row.template_image),
  };
}

async function findTemplateMeta(
  db: Queryable,
  templateId: number,
): Promise<{
  id: number;
  category_id: number | null;
  protocol: string | null;
  communication_config: unknown;
  metrics: unknown;
} | null> {
  const result = await db.query(
    `SELECT id, category_id, protocol, communication_config, metrics
     FROM device_templates WHERE id = $1`,
    [templateId],
  );
  return (result.rows[0] as {
    id: number;
    category_id: number | null;
    protocol: string | null;
    communication_config: unknown;
    metrics: unknown;
  } | undefined) ?? null;
}

async function isGatewayDevice(db: Queryable, id: number): Promise<boolean> {
  const result = await db.query(
    `SELECT config FROM devices WHERE id = $1 AND is_deleted = false`,
    [id],
  );
  const row = result.rows[0] as { config: unknown } | undefined;
  if (!row) return false;
  return deviceRoleOf(asObject(row.config)) === 'gateway';
}

function buildConfig(
  existing: Record<string, unknown>,
  input: DeviceWriteInput,
): Record<string, unknown> {
  const config = { ...existing };
  config.custom_fields = input.customFields;
  config.role = input.deviceRole;
  if (input.snapshotTriggers !== null) {
    config.snapshot_triggers = input.snapshotTriggers;
  }
  if (input.deviceRole === 'endpoint' && input.gatewayDeviceId) {
    config.via_gateway = input.gatewayDeviceId;
    config.endpoint_template_id = input.deviceTemplateId;
  } else {
    delete config.via_gateway;
    delete config.endpoint_template_id;
  }
  return config;
}

/** LoRaWAN keys stored on devices.communication_config (PHP form parity). */
const LORAWAN_COMM_KEYS = [
  'application_server',
  'device_profile_id',
  'dev_eui',
  'app_eui',
  'app_key',
  'netskey',
  'appskey',
] as const;

function normalizeCommunication(
  raw: unknown,
  role: DeviceWriteInput['deviceRole'],
  protocol: string | null,
  gatewayDeviceId: number | null,
): Record<string, unknown> {
  let communication = asObject(raw);
  if (role === 'endpoint') {
    communication = { ...communication, protocol: 'gateway_endpoint' };
    const source =
      communication.source && typeof communication.source === 'object' && !Array.isArray(communication.source)
        ? { ...(communication.source as Record<string, unknown>) }
        : {};
    if (gatewayDeviceId) {
      source.gateway_device_id = gatewayDeviceId;
    }
    communication.source = source;
    return communication;
  }
  const resolvedProtocol = String(communication.protocol ?? protocol ?? '').toLowerCase();
  if (protocol && !communication.protocol) {
    communication.protocol = protocol;
  }
  // Keep LoRaWAN fields even when empty so round-trip matches PHP form posts.
  if (resolvedProtocol === 'lorawan' || protocol === 'lorawan') {
    for (const key of LORAWAN_COMM_KEYS) {
      if (!(key in communication)) {
        communication[key] = '';
      } else if (communication[key] === null || communication[key] === undefined) {
        communication[key] = '';
      } else {
        communication[key] = String(communication[key]);
      }
    }
    if (!communication.protocol) {
      communication.protocol = 'lorawan';
    }
  }
  return communication;
}

function normalizeMetrics(
  raw: unknown,
  role: DeviceWriteInput['deviceRole'],
  gatewayDeviceId: number | null,
  templateMetrics: unknown,
  isCreate: boolean,
): unknown[] {
  let metrics = asArray(raw);
  if (isCreate && role !== 'endpoint' && metrics.length === 0) {
    metrics = asArray(templateMetrics);
  }
  if (role === 'endpoint') {
    if (metrics.length === 0) {
      metrics = asArray(templateMetrics);
    }
    return metrics.map((metric) => {
      if (!metric || typeof metric !== 'object' || Array.isArray(metric)) {
        return metric;
      }
      const item = { ...(metric as Record<string, unknown>) };
      item.protocol = 'gateway_endpoint';
      item.type = 'gateway_mapped';
      item.pull_rate = 0;
      const source =
        item.source && typeof item.source === 'object' && !Array.isArray(item.source)
          ? { ...(item.source as Record<string, unknown>) }
          : {};
      if (gatewayDeviceId) {
        source.gateway_device_id = gatewayDeviceId;
      }
      item.source = source;
      return item;
    });
  }
  return metrics;
}

export type DeviceWriteResult =
  | { ok: true; id: number }
  | { ok: false; code: 'TEMPLATE_REQUIRED' | 'INVALID_GATEWAY' | 'NOT_FOUND' };

export async function insertDevice(db: Queryable, input: DeviceWriteInput): Promise<DeviceWriteResult> {
  const template = await findTemplateMeta(db, input.deviceTemplateId);
  if (!template) {
    return { ok: false, code: 'TEMPLATE_REQUIRED' };
  }
  if (input.deviceRole === 'endpoint') {
    if (!input.gatewayDeviceId || !(await isGatewayDevice(db, input.gatewayDeviceId))) {
      return { ok: false, code: 'INVALID_GATEWAY' };
    }
  }

  const communicationConfig = normalizeCommunication(
    input.communicationConfig ?? template.communication_config ?? {},
    input.deviceRole,
    template.protocol,
    input.gatewayDeviceId,
  );
  const metrics = normalizeMetrics(
    input.metrics,
    input.deviceRole,
    input.gatewayDeviceId,
    template.metrics,
    true,
  );
  const config = buildConfig({}, input);

  const result = await db.query(
    `INSERT INTO devices (
       name, device_template_id, serial_number, location, description, is_active, category_id,
       communication_config, metrics, config, image, is_deleted, created_at
     )
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8::jsonb, $9::jsonb, $10::jsonb, $11, false, NOW())
     RETURNING id`,
    [
      input.name,
      input.deviceTemplateId,
      input.serialNumber,
      input.location,
      input.description,
      input.isActive,
      template.category_id,
      JSON.stringify(communicationConfig),
      JSON.stringify(metrics),
      JSON.stringify(config),
      input.image,
    ],
  );
  const row = result.rows[0] as { id: number } | undefined;
  if (!row) {
    return { ok: false, code: 'TEMPLATE_REQUIRED' };
  }
  return { ok: true, id: Number(row.id) };
}

export async function updateDevice(
  db: Queryable,
  id: number,
  input: DeviceWriteInput,
): Promise<DeviceWriteResult> {
  const existing = await findDevice(db, id);
  if (!existing) {
    return { ok: false, code: 'NOT_FOUND' };
  }
  const template = await findTemplateMeta(db, input.deviceTemplateId);
  if (!template) {
    return { ok: false, code: 'TEMPLATE_REQUIRED' };
  }
  if (input.deviceRole === 'endpoint') {
    if (
      !input.gatewayDeviceId ||
      input.gatewayDeviceId === id ||
      !(await isGatewayDevice(db, input.gatewayDeviceId))
    ) {
      return { ok: false, code: 'INVALID_GATEWAY' };
    }
  }

  const communicationConfig = normalizeCommunication(
    input.communicationConfig ?? existing.communicationConfig ?? {},
    input.deviceRole,
    template.protocol,
    input.gatewayDeviceId,
  );
  const metrics = normalizeMetrics(
    input.metrics ?? existing.metrics,
    input.deviceRole,
    input.gatewayDeviceId,
    template.metrics,
    false,
  );
  const config = buildConfig(asObject(existing.config), input);
  const image = input.image ?? existing.image;

  const result = await db.query(
    `UPDATE devices
     SET name = $2,
         device_template_id = $3,
         serial_number = $4,
         location = $5,
         description = $6,
         is_active = $7,
         category_id = $8,
         communication_config = $9::jsonb,
         metrics = $10::jsonb,
         config = $11::jsonb,
         image = $12,
         updated_at = NOW()
     WHERE id = $1 AND is_deleted = false
     RETURNING id`,
    [
      id,
      input.name,
      input.deviceTemplateId,
      input.serialNumber,
      input.location,
      input.description,
      input.isActive,
      template.category_id,
      JSON.stringify(communicationConfig),
      JSON.stringify(metrics),
      JSON.stringify(config),
      image,
    ],
  );
  if (result.rows.length === 0) {
    return { ok: false, code: 'NOT_FOUND' };
  }
  return { ok: true, id };
}

/** @deprecated Prefer insertDevice with DeviceWriteInput; kept for older call sites. */
export async function insertDeviceBasic(
  db: Queryable,
  input: {
    name: string;
    deviceTemplateId: number;
    serialNumber: string;
    location: string;
    description: string;
    isActive: boolean;
  },
): Promise<number | null> {
  const result = await insertDevice(db, {
    ...input,
    deviceRole: 'native_device',
    gatewayDeviceId: null,
    communicationConfig: null,
    metrics: null,
    customFields: {},
    snapshotTriggers: null,
    image: null,
  });
  return result.ok ? result.id : null;
}

export async function softDeleteDevice(db: Queryable, id: number): Promise<boolean> {
  const result = await db.query(
    `UPDATE devices SET is_deleted = true, updated_at = NOW() WHERE id = $1 AND is_deleted = false RETURNING id`,
    [id],
  );
  return result.rows.length > 0;
}

function compactMetricsForBuilder(metrics: unknown): MetricSourceDevice['metrics'] {
  const compact: MetricSourceDevice['metrics'] = [];
  for (const metric of asArray(metrics)) {
    if (!metric || typeof metric !== 'object' || Array.isArray(metric)) continue;
    const record = metric as Record<string, unknown>;
    if (typeof record.key !== 'string' || !record.key) continue;
    const item: MetricSourceDevice['metrics'][number] = {
      key: record.key,
      label: typeof record.label === 'string' ? record.label : record.key,
      enable: (record.enable as boolean | string | undefined) ?? true,
    };
    if (record.calc && typeof record.calc === 'object' && !Array.isArray(record.calc)) {
      (item as Record<string, unknown>).calc = record.calc;
    }
    if (record.source && typeof record.source === 'object' && !Array.isArray(record.source)) {
      (item as Record<string, unknown>).source = record.source;
    }
    if (Array.isArray(record.triggers) && record.triggers.length > 0) {
      item.triggers = record.triggers.map((trigger) => {
        if (!trigger || typeof trigger !== 'object' || Array.isArray(trigger)) return {};
        const t = trigger as Record<string, unknown>;
        return {
          enable: t.enable ?? true,
          operator: t.operator ?? '',
          value: t.value ?? '',
          message: t.message ?? '',
          severity: t.severity ?? null,
          repeat_interval: t.repeat_interval ?? null,
          clear_condition: t.clear_condition ?? null,
          // Duration schedule grid + notification channel IDs (metric wizard parity).
          duration: Array.isArray(t.duration) ? t.duration : [],
          channels: Array.isArray(t.channels) ? t.channels : [],
        };
      });
    }
    compact.push(item);
  }
  return compact;
}

export async function listMetricSources(db: Queryable): Promise<MetricSourceDevice[]> {
  const result = await db.query(
    `SELECT id, name, metrics FROM devices WHERE is_deleted = false ORDER BY name ASC`,
  );
  return (result.rows as { id: number; name: string | null; metrics: unknown }[]).map((row) => ({
    id: row.id,
    name: row.name ?? '',
    metrics: compactMetricsForBuilder(row.metrics),
  }));
}

export async function listGatewayDevices(
  db: Queryable,
  excludeId?: number,
): Promise<Array<{ id: number; name: string }>> {
  const result = await db.query(
    `SELECT id, name, config FROM devices WHERE is_deleted = false ORDER BY name ASC`,
  );
  const gateways: Array<{ id: number; name: string }> = [];
  for (const row of result.rows as { id: number; name: string | null; config: unknown }[]) {
    if (excludeId !== undefined && row.id === excludeId) continue;
    if (deviceRoleOf(asObject(row.config)) === 'gateway') {
      gateways.push({ id: row.id, name: row.name ?? '' });
    }
  }
  return gateways;
}

export function metricKeysFromDevice(metrics: unknown): DeviceMetricKey[] {
  const keys: DeviceMetricKey[] = [];
  for (const metric of asArray(metrics)) {
    if (!metric || typeof metric !== 'object' || Array.isArray(metric)) continue;
    const record = metric as Record<string, unknown>;
    if (typeof record.key !== 'string' || !record.key) continue;
    keys.push({
      key: record.key,
      label: typeof record.label === 'string' ? record.label : record.key,
      unit: typeof record.unit === 'string' ? record.unit : '',
      datatype: typeof record.datatype === 'string' ? record.datatype : '',
      enable: (record.enable as boolean | string | undefined) ?? true,
    });
  }
  return keys;
}

export interface LatestMetricRow {
  key: string;
  value: number | null;
  valueText: string | null;
  valueBool: boolean | null;
  valueJson: unknown;
  timestamp: string | null;
}

export async function latestMetricsForDevice(
  db: Queryable,
  deviceId: number,
): Promise<LatestMetricRow[]> {
  const result = await db.query(
    `SELECT DISTINCT ON (key)
            key, value, value_text, value_bool, value_json, timestamp
     FROM device_data
     WHERE device_id = $1
     ORDER BY key, timestamp DESC`,
    [deviceId],
  );
  return (result.rows as {
    key: string;
    value: number | null;
    value_text: string | null;
    value_bool: boolean | null;
    value_json: unknown;
    timestamp: Date | string | null;
  }[]).map((row) => ({
    key: row.key,
    value: row.value === null ? null : Number(row.value),
    valueText: row.value_text,
    valueBool: row.value_bool,
    valueJson: row.value_json,
    timestamp:
      row.timestamp instanceof Date
        ? row.timestamp.toISOString()
        : row.timestamp
          ? String(row.timestamp)
          : null,
  }));
}

export async function latestMetricValue(
  db: Queryable,
  deviceId: number,
  key: string,
): Promise<LatestMetricRow | null> {
  const result = await db.query(
    `SELECT key, value, value_text, value_bool, value_json, timestamp
     FROM device_data
     WHERE device_id = $1 AND key = $2
     ORDER BY timestamp DESC
     LIMIT 1`,
    [deviceId, key],
  );
  const row = result.rows[0] as
    | {
        key: string;
        value: number | null;
        value_text: string | null;
        value_bool: boolean | null;
        value_json: unknown;
        timestamp: Date | string | null;
      }
    | undefined;
  if (!row) return null;
  return {
    key: row.key,
    value: row.value === null ? null : Number(row.value),
    valueText: row.value_text,
    valueBool: row.value_bool,
    valueJson: row.value_json,
    timestamp:
      row.timestamp instanceof Date
        ? row.timestamp.toISOString()
        : row.timestamp
          ? String(row.timestamp)
          : null,
  };
}

const RANGE_INTERVAL: Record<string, string> = {
  '1h': '1 hour',
  '6h': '6 hours',
  '24h': '24 hours',
  '7d': '7 days',
  '31d': '31 days',
  '1y': '1 year',
};

export async function metricTimeseries(
  db: Queryable,
  deviceId: number,
  key: string,
  range = '24h',
): Promise<Array<{ timestamp: string; value: number | null }>> {
  const interval = RANGE_INTERVAL[range] ?? RANGE_INTERVAL['24h'];
  const result = await db.query(
    `SELECT timestamp, value
     FROM device_data
     WHERE device_id = $1 AND key = $2 AND timestamp > NOW() - $3::interval
     ORDER BY timestamp ASC`,
    [deviceId, key, interval],
  );
  return (result.rows as { timestamp: Date | string; value: number | null }[]).map((row) => ({
    timestamp: row.timestamp instanceof Date ? row.timestamp.toISOString() : String(row.timestamp),
    value: row.value === null ? null : Number(row.value),
  }));
}

export interface CsvImportRow {
  name: string;
  serial_number?: string;
  location?: string;
  description?: string;
  app_key?: string;
  dev_eui?: string;
  application_server?: string;
  device_template_id?: number;
}

export async function importDevicesFromCsvRows(
  db: Queryable,
  rows: CsvImportRow[],
  options?: { registerChirpStack?: boolean },
): Promise<{ imported: number; results: Array<{ name: string; id: number | null; chirpstack: string }> }> {
  const results: Array<{ name: string; id: number | null; chirpstack: string }> = [];
  let imported = 0;
  for (const row of rows) {
    const name = row.name?.trim();
    if (!name) {
      results.push({ name: '', id: null, chirpstack: 'skipped' });
      continue;
    }
    const templateId = row.device_template_id && row.device_template_id > 0 ? row.device_template_id : 11;
    const communicationConfig = {
      app_eui: 'A840410000000100',
      app_key: row.app_key ?? '',
      dev_eui: row.dev_eui ?? '',
      device_profile_id: '82b307a1-3ffd-4840-a0af-6e4587d43e9b',
      application_server: row.application_server ?? '',
      protocol: 'lorawan',
    };
    const created = await insertDevice(db, {
      name,
      deviceTemplateId: templateId,
      serialNumber: row.serial_number ?? '',
      location: row.location ?? '',
      description: row.description ?? '',
      isActive: true,
      deviceRole: 'native_device',
      gatewayDeviceId: null,
      communicationConfig,
      metrics: null,
      customFields: {},
      snapshotTriggers: null,
      image: null,
    });
    if (!created.ok) {
      results.push({ name, id: null, chirpstack: 'db_failed' });
      continue;
    }
    imported += 1;
    // ChirpStack registration stays optional: PHP uses chirpstack_* config.
    // Structure the response so webapp can show per-row status without blocking import.
    const chirpstack =
      options?.registerChirpStack === false
        ? 'skipped'
        : 'stubbed_pending_chirpstack_service';
    results.push({ name, id: created.id, chirpstack });
  }
  return { imported, results };
}
