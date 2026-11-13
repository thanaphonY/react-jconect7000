import type { Queryable } from '../db.js';

export type CalcTriggerMode = 'interval' | 'on_pull' | 'schedule';

export interface CalcInputDevice {
  var: string;
  metricKey: string;
  deviceId?: number;
}

export interface CalcInputRandom {
  var: string;
  source: 'random';
  min: number;
  max: number;
}

export type CalcInput = CalcInputDevice | CalcInputRandom;

export interface CalcTrigger {
  mode: CalcTriggerMode;
  intervalSec?: number;
  cron?: string;
}

export interface CalcMetricBody {
  type: 'calc';
  key: string;
  label: string;
  unit: string;
  datatype: 'float' | 'int';
  enable: boolean;
  store_policy: string;
  pull_rate: number;
  calc: {
    formula: string;
    inputs: CalcInput[];
    trigger: CalcTrigger;
    decimals: number;
    maxAgeSec?: number;
  };
  triggers: unknown[];
  normal_condition: Record<string, unknown>;
}

export interface CalculationRow {
  deviceId: number;
  deviceName: string;
  isActive: boolean;
  metric: Record<string, unknown>;
}

export interface CalcDeviceOption {
  id: number;
  name: string;
  metrics: unknown[];
}

export interface SaveCalculationInput {
  deviceId: number;
  originalDeviceId?: number | null;
  originalKey?: string | null;
  key: string;
  label?: string | null;
  unit?: string | null;
  datatype?: 'float' | 'int' | null;
  formula: string;
  inputs: unknown[];
  triggerMode?: string | null;
  intervalSec?: number | null;
  cron?: string | null;
  decimals?: number | null;
  maxAgeSec?: number | string | null;
}

export type CalculationErrorCode =
  | 'INVALID_INPUT'
  | 'NOT_FOUND'
  | 'DUPLICATE_KEY'
  | 'CRON_REQUIRED';

function decodeMetrics(value: unknown): unknown[] {
  let decoded: unknown = value;
  if (typeof decoded === 'string') {
    try {
      decoded = JSON.parse(decoded);
    } catch {
      return [];
    }
  }
  if (typeof decoded === 'string') {
    try {
      decoded = JSON.parse(decoded);
    } catch {
      return [];
    }
  }
  return Array.isArray(decoded) ? decoded : [];
}

function asMetricRecord(metric: unknown): Record<string, unknown> | null {
  if (!metric || typeof metric !== 'object' || Array.isArray(metric)) return null;
  return metric as Record<string, unknown>;
}

function normalizeInputs(raw: unknown[]): CalcInput[] {
  const normalized: CalcInput[] = [];
  for (const item of raw) {
    if (!item || typeof item !== 'object' || Array.isArray(item)) continue;
    const input = item as Record<string, unknown>;
    const varName = String(input.var ?? '').trim();
    if (!varName) continue;

    if (input.source === 'random') {
      normalized.push({
        var: varName,
        source: 'random',
        min: typeof input.min === 'number' && Number.isFinite(input.min) ? input.min : Number(input.min) || 0,
        max: typeof input.max === 'number' && Number.isFinite(input.max) ? input.max : Number(input.max) || 1,
      });
      continue;
    }

    const metricKey = String(input.metricKey ?? '').trim();
    if (!metricKey) continue;
    const source: CalcInputDevice = { var: varName, metricKey };
    if (input.deviceId !== undefined && input.deviceId !== null && input.deviceId !== '') {
      const deviceId = Number(input.deviceId);
      if (Number.isFinite(deviceId) && deviceId > 0) {
        source.deviceId = deviceId;
      }
    }
    normalized.push(source);
  }
  return normalized;
}

async function getDevice(
  db: Queryable,
  id: number,
): Promise<{ id: number; name: string; metrics: unknown; is_active: boolean } | null> {
  const result = await db.query(
    `SELECT id, name, metrics, is_active
     FROM devices
     WHERE id = $1 AND is_deleted = false`,
    [id],
  );
  const row = result.rows[0] as
    | { id: number; name: string | null; metrics: unknown; is_active: boolean | null }
    | undefined;
  if (!row) return null;
  return {
    id: row.id,
    name: row.name ?? '',
    metrics: row.metrics,
    is_active: Boolean(row.is_active),
  };
}

async function saveMetrics(db: Queryable, deviceId: number, metrics: unknown[]): Promise<boolean> {
  const result = await db.query(
    `UPDATE devices
     SET metrics = $1::jsonb,
         updated_at = NOW()
     WHERE id = $2 AND is_deleted = false
     RETURNING id`,
    [JSON.stringify(metrics), deviceId],
  );
  return result.rows.length > 0;
}

async function removeMetric(db: Queryable, deviceId: number, key: string): Promise<boolean> {
  if (!deviceId || !key) return false;
  const device = await getDevice(db, deviceId);
  if (!device) return false;
  const metrics = decodeMetrics(device.metrics).filter((metric) => {
    const record = asMetricRecord(metric);
    if (!record) return true;
    return !(record.type === 'calc' && record.key === key);
  });
  return saveMetrics(db, deviceId, metrics);
}

export async function listCalculationPage(db: Queryable): Promise<{
  calculations: CalculationRow[];
  devices: CalcDeviceOption[];
}> {
  const result = await db.query(
    `SELECT id, name, metrics, is_active
     FROM devices
     WHERE is_deleted = false
     ORDER BY name ASC`,
  );

  const calculations: CalculationRow[] = [];
  const devices: CalcDeviceOption[] = [];

  for (const row of result.rows as {
    id: number;
    name: string | null;
    metrics: unknown;
    is_active: boolean | null;
  }[]) {
    const metrics = decodeMetrics(row.metrics);
    devices.push({
      id: row.id,
      name: row.name ?? '',
      metrics,
    });
    for (const metric of metrics) {
      const record = asMetricRecord(metric);
      if (!record || record.type !== 'calc') continue;
      calculations.push({
        deviceId: row.id,
        deviceName: row.name ?? '',
        isActive: Boolean(row.is_active),
        metric: record,
      });
    }
  }

  return { calculations, devices };
}

export async function saveCalculation(
  db: Queryable,
  input: SaveCalculationInput,
): Promise<{ calculation: CalculationRow } | { error: string; code: CalculationErrorCode }> {
  const deviceId = Number(input.deviceId);
  const key = String(input.key ?? '').trim();
  const formula = String(input.formula ?? '').trim();
  const inputs = normalizeInputs(Array.isArray(input.inputs) ? input.inputs : []);

  if (!deviceId || !key || !formula || inputs.length === 0) {
    return {
      error: 'Device, metric key, formula, and at least one input are required.',
      code: 'INVALID_INPUT',
    };
  }

  const target = await getDevice(db, deviceId);
  if (!target) {
    return { error: 'Device not found.', code: 'NOT_FOUND' };
  }

  const originalDeviceId = input.originalDeviceId ? Number(input.originalDeviceId) : 0;
  const originalKey = String(input.originalKey ?? '').trim();
  const metrics = decodeMetrics(target.metrics);

  let existingIndex = -1;
  for (let index = 0; index < metrics.length; index += 1) {
    const record = asMetricRecord(metrics[index]);
    if (
      originalDeviceId === deviceId &&
      record &&
      record.key === originalKey &&
      record.type === 'calc'
    ) {
      existingIndex = index;
    }
  }

  for (let index = 0; index < metrics.length; index += 1) {
    const record = asMetricRecord(metrics[index]);
    if (index !== existingIndex && record && record.key === key) {
      return {
        error: 'Metric key already exists on the selected device.',
        code: 'DUPLICATE_KEY',
      };
    }
  }

  let mode = String(input.triggerMode ?? 'interval');
  if (mode !== 'interval' && mode !== 'on_pull' && mode !== 'schedule') {
    mode = 'interval';
  }

  // Match PHP: (int) empty/null → 0, then max(1, …) → 1 (not 30)
  const intervalSec = Math.max(1, Math.trunc(Number(input.intervalSec)) || 0);
  const trigger: CalcTrigger = { mode: mode as CalcTriggerMode };
  if (mode === 'interval') {
    trigger.intervalSec = intervalSec;
  } else if (mode === 'schedule') {
    const cron = String(input.cron ?? '').trim();
    if (!cron) {
      return { error: 'Cron expression is required for schedule mode.', code: 'CRON_REQUIRED' };
    }
    trigger.cron = cron;
  }

  const decimalsRaw = Number(input.decimals);
  const decimals = Number.isFinite(decimalsRaw)
    ? Math.max(0, Math.min(8, Math.trunc(decimalsRaw)))
    : 2;

  const metric: CalcMetricBody = {
    type: 'calc',
    key,
    label: String(input.label ?? '').trim(),
    unit: String(input.unit ?? '').trim(),
    datatype: input.datatype === 'int' ? 'int' : 'float',
    enable: true,
    store_policy: 'always',
    pull_rate: intervalSec,
    calc: {
      formula,
      inputs,
      trigger,
      decimals,
    },
    triggers: [],
    normal_condition: {},
  };

  const maxAgeRaw = input.maxAgeSec;
  if (maxAgeRaw !== undefined && maxAgeRaw !== null && String(maxAgeRaw).trim() !== '') {
    metric.calc.maxAgeSec = Math.max(0, Math.trunc(Number(maxAgeRaw) || 0));
  }

  if (existingIndex >= 0) {
    const previous = asMetricRecord(metrics[existingIndex]) ?? {};
    metrics[existingIndex] = { ...previous, ...metric };
  } else {
    metrics.push(metric);
  }

  await saveMetrics(db, deviceId, metrics);

  if (originalDeviceId && originalDeviceId !== deviceId) {
    await removeMetric(db, originalDeviceId, originalKey);
  }

  return {
    calculation: {
      deviceId,
      deviceName: target.name,
      isActive: target.is_active,
      metric: (existingIndex >= 0
        ? (asMetricRecord(metrics[existingIndex]) ?? metric)
        : metric) as Record<string, unknown>,
    },
  };
}

export async function deleteCalculation(
  db: Queryable,
  deviceId: number,
  key: string,
): Promise<{ success: true } | { error: string; code: CalculationErrorCode }> {
  const trimmedKey = String(key ?? '').trim();
  if (!deviceId || !trimmedKey) {
    return { error: 'Device and metric key are required.', code: 'INVALID_INPUT' };
  }
  const device = await getDevice(db, deviceId);
  if (!device) {
    return { error: 'Device not found.', code: 'NOT_FOUND' };
  }
  await removeMetric(db, deviceId, trimmedKey);
  return { success: true };
}
