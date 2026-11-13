const db = require('../config/dbConfig');
const { evaluateLive } = require('./evaluate_live');
const { queueMetric } = require('../../shared/ws-bus');

const lastValues = new Map(); // Map<deviceId:metricKey, lastValue>
const lastSavedTime = new Map(); // Map<deviceId:metricKey, lastSavedTimestamp>

function comparableValue(v) {
  if (v !== null && typeof v === 'object') {
    try {
      return JSON.stringify(v);
    } catch (_e) {
      return String(v);
    }
  }
  return v;
}

function shouldStore(deviceId, key, newValue, storePolicy = 'always', intervalMinutes = null) {
  const idKey = `${deviceId}:${key}`;

  if (storePolicy === 'none') return false;

  if (storePolicy === 'on_change') {
    const last = lastValues.get(idKey);
    const next = comparableValue(newValue);
    if (last === next) return false;
    lastValues.set(idKey, next);
    return true;
  }

  if (storePolicy === 'interval' && intervalMinutes != null) {
    const lastTs = lastSavedTime.get(idKey);
    const now = Date.now();
    if (lastTs && now - lastTs < intervalMinutes * 60000) return false;
    lastSavedTime.set(idKey, now);
    return true;
  }

  // default: always
  return true;
}

function formatValueForLog(v) {
  if (v === null || v === undefined) return String(v);
  if (typeof v === 'number' && !Number.isInteger(v)) return Number(v.toFixed(4));
  if (typeof v === 'object') return JSON.stringify(v);
  return String(v);
}

function coerceBool(v) {
  if (typeof v === 'boolean') return v;
  if (typeof v === 'number') return v !== 0 && Number.isFinite(v);
  if (typeof v === 'string') {
    const s = v.trim().toLowerCase();
    if (['true', '1', 'yes', 'on'].includes(s)) return true;
    if (['false', '0', 'no', 'off', ''].includes(s)) return false;
  }
  return Boolean(v);
}

function isJsonLike(v) {
  if (v === null || v === undefined) return false;
  if (Array.isArray(v)) return true;
  if (typeof v !== 'object') return false;
  if (v instanceof Date) return false;
  if (typeof Buffer !== 'undefined' && Buffer.isBuffer(v)) return false;
  return true;
}

/**
 * Map metric datatype / runtime value → device_data columns.
 * Only one typed column is filled; others stay null.
 */
function resolveStorageColumns(metricDef, rawValue) {
  const dt = String(metricDef?.datatype || '').toLowerCase();
  const cols = {
    value: null,
    value_text: null,
    value_bool: null,
    value_json: null,
  };

  if (dt === 'bool' || dt === 'boolean' || typeof rawValue === 'boolean') {
    cols.value_bool = coerceBool(rawValue);
    return { cols, storedValue: cols.value_bool };
  }

  if (dt === 'json' || dt === 'jsonb' || isJsonLike(rawValue)) {
    if (typeof rawValue === 'string') {
      try {
        cols.value_json = JSON.parse(rawValue);
      } catch (_e) {
        cols.value_text = rawValue;
        return { cols, storedValue: rawValue };
      }
    } else {
      cols.value_json = rawValue;
    }
    return { cols, storedValue: cols.value_json };
  }

  const asString =
    typeof rawValue !== 'number' &&
    (typeof rawValue === 'string' ||
      dt === 'string' ||
      metricDef?.type === 'visual_random_string');

  if (asString) {
    cols.value_text = String(rawValue);
    return { cols, storedValue: cols.value_text };
  }

  const num = typeof rawValue === 'number' ? rawValue : Number(rawValue);
  cols.value = Number.isFinite(num) ? num : null;
  return { cols, storedValue: cols.value };
}

async function insertDeviceDataWithRetry(deviceId, key, cols, timestamp) {
  const baseTime = timestamp instanceof Date ? timestamp : new Date(timestamp || new Date());
  const maxRetries = 3;

  for (let attempt = 0; attempt <= maxRetries; attempt += 1) {
    const ts = new Date(baseTime.getTime() + attempt);
    try {
      await db.query(
        `INSERT INTO device_data
           (device_id, key, value, value_text, value_bool, value_json, "timestamp")
         VALUES ($1, $2, $3, $4, $5, $6, $7)`,
        [
          deviceId,
          key,
          cols.value,
          cols.value_text,
          cols.value_bool,
          cols.value_json,
          ts,
        ]
      );
      return ts;
    } catch (err) {
      if (err?.code === '23505' && attempt < maxRetries) continue;
      throw err;
    }
  }

  return baseTime;
}

async function saveDataPoint(device, datapoints) {
  if (!datapoints?.length) return;

  const incoming = datapoints
    .map((dp) => `${dp.key}=${formatValueForLog(dp.value)}`)
    .join(', ');
  //console.log(`[Fetch] id=${device.id} name="${device.name}" | incoming: ${incoming}`);

  const outcomes = [];

  for (const dp of datapoints) {
    const metricDef = device.metrics.find((m) => {
      const k = m.key ?? m.metricKey ?? m.metric_key ?? m.id;
      return k != null && String(k) === String(dp.key);
    });
    const storePolicy = metricDef?.store_policy || 'always';
    const intervalMinutes = metricDef?.interval_minutes || null;

    const shouldInsert = shouldStore(device.id, dp.key, dp.value, storePolicy, intervalMinutes);
    if (!shouldInsert) {
      //console.log(`[SKIP] ${device.name} - ${dp.key} (policy=${storePolicy})`);
      outcomes.push(`${dp.key}:skip(store_policy)`);
    } else {
      //console.log(`[STORE] ${device.name}-${device.id}  - ${dp.key} = ${formatValueForLog(dp.value)} (policy=${storePolicy})`);
      try {
        const { cols } = resolveStorageColumns(metricDef, dp.value);
        await insertDeviceDataWithRetry(device.id, dp.key, cols, dp.timestamp || new Date());
        outcomes.push(`${dp.key}:stored`);
      } catch (err) {
        console.error('[DB] Insert error:', err.message);
        outcomes.push(`${dp.key}:db_error`);
      }
    }

    // Always evaluate and broadcast, regardless of store_policy
    await evaluateLive(device.id, dp.key, dp.value);
    queueMetric({
      device_id: device.id,
      metric_key: dp.key,
      value: dp.value,
      timestamp: dp.timestamp || new Date()
    });
  }

  //console.log(`[Fetch] id=${device.id} name="${device.name}" | result: ${outcomes.join('; ')}`);

  // Trigger any on_pull calc metrics that depend on these values.
  // Lazy require avoids a load-time circular dependency (calc ↔ dataSaver).
  try {
    const { onMetricsUpdated } = require('../protocols/calc');
    onMetricsUpdated(device.id, datapoints);
  } catch (_e) {
    /* calc module optional */
  }

  // Camera snapshot triggers (door sensor etc. → JPEG to media/snapshots).
  try {
    const { onMetricSaved } = require('./snapshotTriggers');
    for (const dp of datapoints) {
      try {
        await onMetricSaved(device.id, dp.key, dp.value);
      } catch (e) {
        console.error('[SnapshotTrigger] async error:', e.message);
      }
    }
  } catch (_e) {
    /* snapshotTriggers optional */
  }
}

module.exports = { saveDataPoint, resolveStorageColumns };
