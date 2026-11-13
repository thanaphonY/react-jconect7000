/**
 * Snapshot triggers: when a source device metric *enters* a matching condition
 * on a camera device (config.snapshot_triggers[]), capture JPEG under media/snapshots.
 *
 * Fires on rising edge of the linked alarm Rule, and also on rising edge of Clear
 * (e.g. Door Opened == 3 and Door Closed == 1).
 *
 * Rule shape:
 * {
 *   enable: true,
 *   source_device_id: 12,
 *   source_metric_key: "door",
 *   source_trigger_index: 0,
 *   target_camera_device_id: 55,
 *   cooldown_sec: 30
 * }
 *
 * Legacy rules may omit source_trigger_index and use operator/value directly.
 */

/** Map "sourceDeviceId:metricKey" → [{ cameraDeviceId, rule, ... }] */
let index = new Map();
/** ruleKey → last fire timestamp */
const lastFired = new Map();
/** ruleKey → previous compare() result (for edge detection) */
const lastMatch = new Map();
/** "sourceDeviceId:metricKey" → last seen raw value (skip duplicate polls) */
const lastValue = new Map();
let takingSnapshot = false;

const BANGKOK_TZ = 'Asia/Bangkok';
const WEEKDAY_MAP = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

function getFetcher() {
  return require('./dataFetcher');
}

function compare(op, left, right) {
  const a = Number(left);
  const b = Number(right);
  const useNum = Number.isFinite(a) && Number.isFinite(b);
  const L = useNum ? a : String(left);
  const R = useNum ? b : String(right);
  switch (String(op || '==')) {
    case '==':
      return L == R; // eslint-disable-line eqeqeq
    case '!=':
      return L != R; // eslint-disable-line eqeqeq
    case '>':
      return L > R;
    case '>=':
      return L >= R;
    case '<':
      return L < R;
    case '<=':
      return L <= R;
    default:
      return false;
  }
}

function valuesEqual(a, b) {
  if (a === b) return true;
  const na = Number(a);
  const nb = Number(b);
  if (Number.isFinite(na) && Number.isFinite(nb)) return na === nb;
  return String(a) === String(b);
}

function getBangkokTimeParts() {
  const now = new Date();
  const dayStr = now.toLocaleDateString('en-US', { timeZone: BANGKOK_TZ, weekday: 'short' });
  const currentDay = WEEKDAY_MAP[dayStr] ?? 0;
  const currentTime = now.toLocaleTimeString('en-GB', {
    timeZone: BANGKOK_TZ,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
  return { currentDay, currentTime };
}

function isWithinDuration(durationList) {
  if (!Array.isArray(durationList) || durationList.length === 0) return true;
  const { currentDay, currentTime } = getBangkokTimeParts();

  return durationList.some((d) => {
    if (!Array.isArray(d.days) || !d.days.includes(currentDay)) return false;
    const start = d.start;
    const end = d.end;
    if (start <= end) {
      return currentTime >= start && currentTime <= end;
    }
    return currentTime >= start || currentTime <= end;
  });
}

function parseDeviceMetrics(device) {
  if (!device) return [];
  if (Array.isArray(device.metrics)) return device.metrics;
  if (typeof device.metrics === 'string') {
    try {
      const parsed = JSON.parse(device.metrics);
      return Array.isArray(parsed) ? parsed : [];
    } catch (_e) {
      return [];
    }
  }
  return [];
}

function resolveAlarmTrigger(sourceDeviceId, metricKey, rule) {
  const device = getFetcher().getDeviceById(Number(sourceDeviceId));
  if (!device) return null;

  const metrics = parseDeviceMetrics(device);
  const metric = metrics.find((m) => String(m.key) === String(metricKey));
  if (!metric) return null;

  const triggers = Array.isArray(metric.triggers) ? metric.triggers : [];
  const idx = rule?.source_trigger_index;
  if (idx !== '' && idx != null && Number.isFinite(Number(idx))) {
    const trigger = triggers[Number(idx)];
    if (trigger) return { trigger, metric };
    return null;
  }

  if (rule?.operator != null && rule.operator !== '') {
    return {
      trigger: {
        enable: true,
        operator: rule.operator,
        value: rule.value,
        duration: [],
      },
      metric,
      legacy: true,
    };
  }

  return null;
}

function isTriggerEnabled(trigger) {
  return trigger?.enable !== false && trigger?.enable !== 'false';
}

function rebuildSnapshotIndex(devices) {
  const list = devices || getFetcher().getAllDevices() || [];
  const next = new Map();

  for (const cam of list) {
    if (String(cam.protocol || '').toLowerCase() !== 'rtsp') continue;
    const rules = Array.isArray(cam.config?.snapshot_triggers)
      ? cam.config.snapshot_triggers
      : [];
    for (const rule of rules) {
      if (!rule || rule.enable === false || rule.enable === 'false') continue;
      const srcId = Number(rule.source_device_id);
      const key = String(rule.source_metric_key || '').trim();
      if (!Number.isFinite(srcId) || !key) continue;
      const mapKey = `${srcId}:${key}`;
      if (!next.has(mapKey)) next.set(mapKey, []);
      next.get(mapKey).push({
        cameraDeviceId: Number(rule.target_camera_device_id) || Number(cam.id),
        rule,
      });
    }
  }

  index = next;
  console.log(`[SnapshotTrigger] index rebuilt: ${index.size} source metric binding(s)`);
}

function ruleIdentity(cameraDeviceId, rule) {
  if (rule.source_trigger_index !== '' && rule.source_trigger_index != null) {
    return `${cameraDeviceId}:${rule.source_device_id}:${rule.source_metric_key}:t${rule.source_trigger_index}`;
  }
  return `${cameraDeviceId}:${rule.source_device_id}:${rule.source_metric_key}:${rule.operator}:${rule.value}`;
}

function hasClearCondition(trigger) {
  const clear = trigger?.clear_condition;
  if (!clear || typeof clear !== 'object') return false;
  const op = clear.operator;
  if (op == null || op === '') return false;
  return clear.value != null && clear.value !== '';
}

async function attachSnapshotToAlarm(sourceDeviceId, metricKey, publicPath, cameraDeviceId) {
  if (!publicPath) return null;
  const db = require('../config/dbConfig');
  try {
    const result = await db.query(
      `UPDATE alarm_logs
          SET snapshot_path = $1,
              snapshot_camera_device_id = $2
        WHERE id = (
          SELECT id
            FROM alarm_logs
           WHERE device_id = $3
             AND metric_key = $4
             AND created_at >= NOW() - INTERVAL '3 minutes'
             AND (snapshot_path IS NULL OR snapshot_path = '')
           ORDER BY created_at DESC
           LIMIT 1
        )
        RETURNING id`,
      [publicPath, Number(cameraDeviceId) || null, Number(sourceDeviceId), String(metricKey)]
    );
    if (result.rowCount) {
      console.log(
        `[SnapshotTrigger] attached ${publicPath} to alarm_log id=${result.rows[0].id}`
      );
      return result.rows[0].id;
    }
    console.log(
      `[SnapshotTrigger] no recent alarm_log for snapshot (device=${sourceDeviceId} metric=${metricKey})`
    );
    return null;
  } catch (e) {
    if (/snapshot_path|snapshot_camera_device_id/i.test(String(e.message || ''))) {
      console.warn(
        '[SnapshotTrigger] snapshot_path column missing — run dataedge/migrations/add_alarm_logs_snapshot_path.sql'
      );
    } else {
      console.error('[SnapshotTrigger] attach alarm error:', e.message);
    }
    return null;
  }
}

async function captureForBinding({
  camera,
  cameraDeviceId,
  sourceDeviceId,
  metricKey,
  value,
  rule,
  edgeKind,
  fireKey,
}) {
  const cooldownMs = Math.max(Number(rule.cooldown_sec) || 30, 1) * 1000;
  const last = lastFired.get(fireKey) || 0;
  if (Date.now() - last < cooldownMs) {
    console.log(`[SnapshotTrigger] cooldown skip ${fireKey}`);
    return;
  }

  lastFired.set(fireKey, Date.now());
  console.log(
    `[SnapshotTrigger] fire camera=${cameraDeviceId} from device=${sourceDeviceId} metric=${metricKey} value=${value} (${edgeKind})`
  );

  takingSnapshot = true;
  try {
    const { takeSnapshot } = require('../protocols/rtsp');
    const result = await takeSnapshot(camera);
    if (!result.ok) {
      console.error(`[SnapshotTrigger] snapshot failed:`, result.error);
    } else {
      console.log(`[SnapshotTrigger] saved ${result.publicPath} via ${result.method}`);
      await attachSnapshotToAlarm(sourceDeviceId, metricKey, result.publicPath, cameraDeviceId);
    }
  } catch (e) {
    console.error(`[SnapshotTrigger] error:`, e.message);
  } finally {
    takingSnapshot = false;
  }
}

async function onMetricSaved(sourceDeviceId, metricKey, value) {
  if (takingSnapshot) return;
  const mapKey = `${Number(sourceDeviceId)}:${String(metricKey)}`;
  const bindings = index.get(mapKey);
  if (!bindings || !bindings.length) return;

  // Skip identical repeated polls (e.g. door stays open every 30s)
  if (lastValue.has(mapKey) && valuesEqual(lastValue.get(mapKey), value)) {
    return;
  }
  lastValue.set(mapKey, value);

  const { getDeviceById } = getFetcher();

  for (const { cameraDeviceId, rule } of bindings) {
    const resolved = resolveAlarmTrigger(sourceDeviceId, metricKey, rule);
    if (!resolved) {
      console.log(`[SnapshotTrigger] skip unresolved trigger camera=${cameraDeviceId}`);
      continue;
    }

    const { trigger: alarmTrigger } = resolved;
    if (!isTriggerEnabled(alarmTrigger)) {
      console.log(`[SnapshotTrigger] skip inactive source trigger camera=${cameraDeviceId}`);
      continue;
    }

    if (!isWithinDuration(alarmTrigger.duration)) {
      console.log(`[SnapshotTrigger] skip outside schedule camera=${cameraDeviceId}`);
      continue;
    }

    const idKey = ruleIdentity(cameraDeviceId, rule);
    const alarmKey = `${idKey}:alarm`;
    const clearKey = `${idKey}:clear`;

    const alarmMatched = compare(alarmTrigger.operator, value, alarmTrigger.value);
    const wasAlarm = lastMatch.has(alarmKey) ? lastMatch.get(alarmKey) : false;
    lastMatch.set(alarmKey, alarmMatched);

    let clearMatched = false;
    let wasClear = false;
    if (hasClearCondition(alarmTrigger)) {
      const clear = alarmTrigger.clear_condition;
      clearMatched = compare(clear.operator, value, clear.value);
      wasClear = lastMatch.has(clearKey) ? lastMatch.get(clearKey) : false;
      lastMatch.set(clearKey, clearMatched);
    }

    const alarmEdge = alarmMatched && !wasAlarm;
    const clearEdge = hasClearCondition(alarmTrigger) && clearMatched && !wasClear;

    if (!alarmEdge && !clearEdge) {
      if (alarmMatched && wasAlarm) {
        console.log(`[SnapshotTrigger] edge skip (still alarm) ${alarmKey} value=${value}`);
      }
      if (clearMatched && wasClear) {
        console.log(`[SnapshotTrigger] edge skip (still clear) ${clearKey} value=${value}`);
      }
      continue;
    }

    const camera = getDeviceById(cameraDeviceId);
    if (!camera || String(camera.protocol || '').toLowerCase() !== 'rtsp') {
      console.warn(`[SnapshotTrigger] camera ${cameraDeviceId} not loaded or not rtsp`);
      continue;
    }

    // Prefer alarm edge if both somehow true in one sample (unusual)
    if (alarmEdge) {
      await captureForBinding({
        camera,
        cameraDeviceId,
        sourceDeviceId,
        metricKey,
        value,
        rule,
        edgeKind: 'alarm',
        fireKey: alarmKey,
      });
    } else if (clearEdge) {
      await captureForBinding({
        camera,
        cameraDeviceId,
        sourceDeviceId,
        metricKey,
        value,
        rule,
        edgeKind: 'clear',
        fireKey: clearKey,
      });
    }
  }
}

module.exports = {
  rebuildSnapshotIndex,
  onMetricSaved,
  compare,
  isWithinDuration,
  resolveAlarmTrigger,
  isTriggerEnabled,
  ruleIdentity,
  hasClearCondition,
  attachSnapshotToAlarm,
};
