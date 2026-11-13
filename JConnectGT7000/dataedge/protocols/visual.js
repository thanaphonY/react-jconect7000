const { saveDataPoint } = require('../core/dataSaver');

/** deviceId -> Map<metricKey, intervalId> */
const visualIntervals = new Map();

function clampInt(n, lo, hi) {
  return Math.min(hi, Math.max(lo, Math.round(n)));
}

function randomIntInclusive(min, max) {
  const lo = Math.ceil(min);
  const hi = Math.floor(max);
  if (hi < lo) return lo;
  return Math.floor(Math.random() * (hi - lo + 1)) + lo;
}

function randomFloatInRange(min, max, decimals) {
  const lo = Math.min(min, max);
  const hi = Math.max(min, max);
  const v = Math.random() * (hi - lo) + lo;
  const d = Number.isFinite(decimals) && decimals >= 0 ? decimals : 2;
  const p = 10 ** d;
  return Math.round(v * p) / p;
}

function parseChoices(visual) {
  const raw = visual?.choices;
  if (Array.isArray(raw)) {
    return raw.map((x) => String(x)).filter((s) => s.length > 0);
  }
  if (typeof raw === 'string' && raw.trim()) {
    return raw
      .split(/[\n,]/)
      .map((s) => s.trim())
      .filter(Boolean);
  }
  return [];
}

function randomString(metric) {
  const visual = metric.visual && typeof metric.visual === 'object' ? metric.visual : {};
  const choices = parseChoices(visual);
  if (choices.length > 0) {
    return choices[randomIntInclusive(0, choices.length - 1)];
  }
  const length = clampInt(Number(visual.length) || 8, 1, 256);
  const rawCs = String(visual.charset || 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
  const charset = rawCs.length ? rawCs : 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let out = '';
  for (let i = 0; i < length; i += 1) {
    out += charset[randomIntInclusive(0, Math.max(0, charset.length - 1))];
  }
  return out;
}

function metricEnabled(m) {
  const en = m?.enable;
  return en !== false && en !== 'false';
}

function generateVisualValue(metric) {
  const t = String(metric?.type || '');
  const dt = String(metric?.datatype || 'number').toLowerCase();
  const visual = metric.visual && typeof metric.visual === 'object' ? metric.visual : {};

  if (t === 'visual_random_string') {
    return randomString(metric);
  }

  if (t === 'visual_random_number') {
    const min = Number.isFinite(Number(visual.min)) ? Number(visual.min) : 0;
    const max = Number.isFinite(Number(visual.max)) ? Number(visual.max) : 100;
    if (dt === 'float') {
      const dec =
        visual.decimals != null && Number.isFinite(Number(visual.decimals))
          ? clampInt(Number(visual.decimals), 0, 8)
          : 2;
      return randomFloatInRange(min, max, dec);
    }
    return randomIntInclusive(min, max);
  }

  return null;
}

function fetchVisual(device) {
  if (!visualIntervals.has(device.id)) {
    visualIntervals.set(device.id, new Map());
  }

  const deviceIntervals = visualIntervals.get(device.id);
  const visualMetrics = (device.metrics || []).filter(
    (m) =>
      metricEnabled(m) &&
      (m.type === 'visual_random_number' || m.type === 'visual_random_string')
  );

  console.log(
    `[VISUAL] polling device id=${device.id} name="${device.name}" visual metrics=${visualMetrics.length}`
  );

  for (const metric of device.metrics || []) {
    if (!metricEnabled(metric)) continue;
    if (metric.type !== 'visual_random_number' && metric.type !== 'visual_random_string') continue;

    const oldInterval = deviceIntervals.get(metric.key);
    if (oldInterval) clearInterval(oldInterval);

    const intervalSec = Math.max(Number(metric.pull_rate) || 10, 1);
    const pullMs = intervalSec * 1000;

    const tick = async () => {
      try {
        const val = generateVisualValue(metric);
        if (val === null || val === undefined) {
          console.warn(`[VISUAL] ${device.name} - ${metric.key}: no value generated`);
          return;
        }
        await saveDataPoint(device, [
          {
            key: metric.key,
            value: val,
            timestamp: new Date(),
          },
        ]);
        
      } catch (err) {
        console.error(`[VISUAL] ${device.name} - ${metric.key} ERROR:`, err?.message || err);
      }
    };

    tick();
    const intervalId = setInterval(tick, pullMs);

    deviceIntervals.set(metric.key, intervalId);
  }

  // Clear intervals for metrics that were removed from the device definition
  const keys = new Set(visualMetrics.map((m) => m.key));
  for (const [key, id] of deviceIntervals.entries()) {
    if (!keys.has(key)) {
      clearInterval(id);
      deviceIntervals.delete(key);
    }
  }
}

function cleanupVisualDevice(deviceId) {
  console.log(`[VISUAL] Cleaning up device ${deviceId}...`);
  if (!visualIntervals.has(deviceId)) return;
  const deviceIntervals = visualIntervals.get(deviceId);
  for (const intervalId of deviceIntervals.values()) {
    clearInterval(intervalId);
  }
  visualIntervals.delete(deviceId);
  console.log(`[VISUAL] Device ${deviceId} cleanup completed`);
}

module.exports = { fetchVisual, cleanupVisualDevice };
