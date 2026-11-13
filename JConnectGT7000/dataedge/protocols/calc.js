/**
 * Calculation "protocol" — derived metrics.
 *
 * A calc metric lives in a device's `metrics` JSON like any other metric, but
 * `type === 'calc'`. Instead of polling hardware it evaluates a math formula
 * over other metric values (same device or cross-device) and writes the result
 * into `device_data` via the shared `saveDataPoint()` — so the computed value
 * behaves exactly like a pulled metric (timeseries, realtime broadcast, alarms).
 *
 * Metric shape:
 * {
 *   type: 'calc',
 *   key: 'avg_temp', label, unit, datatype: 'float' | 'int',
 *   store_policy, interval_minutes, enable,
 *   calc: {
 *     formula: '(a + b) / 2',
 *     inputs: [
 *       { var: 'a', deviceId?: <int, default host>, metricKey: 'temp1' },   // device metric
 *       { var: 'r', source: 'random', min: 0, max: 100, integer?: true },   // random number
 *       ...
 *     ],
 *     trigger: {
 *       mode: 'interval' | 'on_pull' | 'schedule',
 *       intervalSec?: <number, default metric.pull_rate || 10>,
 *       cron?: '<5-field cron>',
 *       onInputs?: 'any' | ['a','b']   // which input vars trigger recompute (on_pull)
 *     },
 *     decimals?: <int>,    // round float output
 *     maxAgeSec?: <int>    // ignore inputs older than this (0 = no limit)
 *   }
 * }
 */

const db = require('../config/dbConfig');
const cron = require('node-cron');
const { create, all } = require('mathjs');

// Restricted mathjs instance: disable the two documented RCE vectors so a
// formula string can never import code or define units.
const math = create(all, { matrix: 'Array', number: 'number' });
try {
  math.import(
    {
      import: function () {
        throw new Error('import disabled');
      },
      createUnit: function () {
        throw new Error('createUnit disabled');
      },
    },
    { override: true }
  );
} catch (_e) {
  /* already restricted */
}

/** deviceId -> Map<metricKey, intervalId> */
const calcIntervals = new Map();
/** deviceId -> Map<metricKey, cronTask> */
const calcCrons = new Map();
/** "srcDeviceId|srcMetricKey" -> Map<targetNodeKey, { deviceId, metricKey }> */
const onPullIndex = new Map();
/** deviceId(Number) -> device object (so recompute can read metric defs w/o a require cycle) */
const calcRegistry = new Map();
/** Re-entrancy guard: "deviceId|metricKey" currently computing. */
const computing = new Set();
/** Node keys (calc metrics) that participate in an on-pull dependency cycle. */
let cyclicCalcKeys = new Set();
/** formula string -> compiled mathjs expression */
const compiledCache = new Map();

const SEP = '\u0000';
function nodeKey(deviceId, metricKey) {
  return `${Number(deviceId)}${SEP}${metricKey}`;
}
function srcKeyOf(deviceId, metricKey) {
  return `${Number(deviceId)}|${metricKey}`;
}

function metricEnabled(m) {
  const en = m?.enable;
  return en !== false && en !== 'false';
}

function getCompiled(formula) {
  let c = compiledCache.get(formula);
  if (!c) {
    c = math.compile(formula);
    compiledCache.set(formula, c);
  }
  return c;
}

/** Latest value per metricKey for one source device (numeric only). */
async function getLatestValues(deviceId, metricKeys, maxAgeSec) {
  if (!metricKeys.length) return new Map();
  const { rows } = await db.query(
    `SELECT DISTINCT ON (key)
            key AS "metricKey",
            value,
            value_text AS "valueText",
            "timestamp" AS ts
       FROM device_data
      WHERE device_id = $1 AND key = ANY($2::text[])
      ORDER BY key, "timestamp" DESC`,
    [Number(deviceId), metricKeys]
  );
  const out = new Map();
  const now = Date.now();
  const ageLimit = Number(maxAgeSec) > 0 ? Number(maxAgeSec) : 0;
  for (const r of rows) {
    if (ageLimit && r.ts) {
      const age = (now - new Date(r.ts).getTime()) / 1000;
      if (age > ageLimit) continue;
    }
    let v = r.value;
    if (v === null || v === undefined) {
      const t = Number(r.valueText);
      v = Number.isFinite(t) ? t : null;
    }
    if (v !== null && Number.isFinite(Number(v))) out.set(r.metricKey, Number(v));
  }
  return out;
}

/**
 * Compute one calc metric and store the result.
 * @param override optional { deviceId, metricKey, value } — freshly-pulled value
 *                 used in place of the DB latest for that input (on_pull mode).
 */
async function recompute(deviceId, metricKey, override) {
  // Lazy require breaks the calc <-> dataSaver circular dependency.
  const { saveDataPoint } = require('../core/dataSaver');

  const guard = `${Number(deviceId)}|${metricKey}`;
  if (computing.has(guard)) return; // re-entrancy / self-reference guard
  const device = calcRegistry.get(Number(deviceId));
  if (!device) return;
  const metric = (device.metrics || []).find(
    (m) => m.type === 'calc' && String(m.key) === String(metricKey)
  );
  if (!metric || !metricEnabled(metric)) return;

  const cfg = metric.calc || {};
  const formula = String(cfg.formula || '').trim();
  const inputs = Array.isArray(cfg.inputs) ? cfg.inputs : [];
  if (!formula) return;

  computing.add(guard);
  try {
    const scope = {};
    // Batch latest-value reads per source device.
    const byDevice = new Map();
    for (const inp of inputs) {
      if (!inp || !inp.var) continue;
      // Random-number input: generated locally, no device/metric lookup.
      if (String(inp.source) === 'random') {
        const min = Number.isFinite(Number(inp.min)) ? Number(inp.min) : 0;
        const max = Number.isFinite(Number(inp.max)) ? Number(inp.max) : 1;
        const lo = Math.min(min, max);
        const hi = Math.max(min, max);
        let rv = lo + Math.random() * (hi - lo);
        if (inp.integer === true || inp.integer === 'true') rv = Math.round(rv);
        scope[inp.var] = rv;
        continue;
      }
      if (!inp.metricKey) continue;
      const srcDev = Number(inp.deviceId || device.id);
      if (!byDevice.has(srcDev)) byDevice.set(srcDev, []);
      byDevice.get(srcDev).push(inp);
    }

    for (const [srcDev, list] of byDevice) {
      const keys = [...new Set(list.map((x) => String(x.metricKey)))];
      const latest = await getLatestValues(srcDev, keys, cfg.maxAgeSec);
      for (const inp of list) {
        let v = latest.has(String(inp.metricKey)) ? latest.get(String(inp.metricKey)) : undefined;
        if (
          override &&
          Number(override.deviceId) === srcDev &&
          String(override.metricKey) === String(inp.metricKey) &&
          Number.isFinite(Number(override.value))
        ) {
          v = Number(override.value);
        }
        if (v === undefined || v === null || !Number.isFinite(Number(v))) {
          console.warn(
            `[CALC] ${device.name}#${device.id} - ${metricKey}: input "${inp.var}" (${srcDev}|${inp.metricKey}) unavailable → skip`
          );
          return;
        }
        scope[inp.var] = Number(v);
      }
    }

    let result;
    try {
      result = getCompiled(formula).evaluate(scope);
    } catch (e) {
      console.error(`[CALC] ${device.name}#${device.id} - ${metricKey} formula error: ${e.message}`);
      return;
    }

    let num = Number(result);
    if (!Number.isFinite(num)) {
      console.warn(`[CALC] ${device.name}#${device.id} - ${metricKey}: non-finite result → skip`);
      return;
    }
    if (String(metric.datatype).toLowerCase() === 'int') {
      num = Math.round(num);
    } if (String(metric.datatype).toLowerCase() === 'int') {
      num = Math.round(num);
    } else {
      // Cap float at 2 decimal places before DB write (cfg.decimals may be lower).
      const decimals = Number.isFinite(Number(cfg.decimals))
        ? Math.max(0, Math.min(2, Number(cfg.decimals)))
        : 2;
      const p = 10 ** decimals;
      num = Math.round(num * p) / p;
    }

    await saveDataPoint(device, [{ key: metricKey, value: num, timestamp: new Date() }]);
    //console.log(`[CALC] ${device.name}#${device.id} - ${metricKey} = ${num}`);
  } catch (err) {
    console.error(`[CALC] ${device.name}#${device.id} - ${metricKey} ERROR:`, err?.message || err);
  } finally {
    computing.delete(guard);
  }
}

/** Remove all on-pull index entries that point at calc metrics of a device. */
function removeDeviceFromOnPullIndex(deviceId) {
  const id = Number(deviceId);
  for (const [srcKey, targets] of onPullIndex.entries()) {
    for (const [tnk, t] of targets.entries()) {
      if (Number(t.deviceId) === id) targets.delete(tnk);
    }
    if (targets.size === 0) onPullIndex.delete(srcKey);
  }
}

/** Rebuild the calc→calc dependency graph and flag metrics involved in cycles. */
function rebuildCalcGraph() {
  const nodes = new Map(); // nodeKey -> { deviceId, metricKey, inputs }
  for (const [devId, device] of calcRegistry.entries()) {
    for (const m of device.metrics || []) {
      if (m.type !== 'calc') continue;
      nodes.set(nodeKey(devId, m.key), {
        deviceId: devId,
        metricKey: m.key,
        inputs: Array.isArray(m.calc?.inputs) ? m.calc.inputs : [],
      });
    }
  }
  // adjacency: srcCalc -> [calc metrics that consume it]
  const adj = new Map();
  for (const [nk, node] of nodes.entries()) {
    for (const inp of node.inputs) {
      if (!inp || !inp.metricKey) continue;
      const srcNk = nodeKey(Number(inp.deviceId || node.deviceId), inp.metricKey);
      if (nodes.has(srcNk)) {
        if (!adj.has(srcNk)) adj.set(srcNk, []);
        adj.get(srcNk).push(nk);
      }
    }
  }
  const cyclic = new Set();
  for (const start of nodes.keys()) {
    const seen = new Set();
    const stack = [...(adj.get(start) || [])];
    while (stack.length) {
      const n = stack.pop();
      if (n === start) {
        cyclic.add(start);
        break;
      }
      if (seen.has(n)) continue;
      seen.add(n);
      for (const nx of adj.get(n) || []) stack.push(nx);
    }
  }
  cyclicCalcKeys = cyclic;
}

function registerOnPull(calcDeviceId, metric) {
  const nk = nodeKey(calcDeviceId, metric.key);
  if (cyclicCalcKeys.has(nk)) {
    console.error(
      `[CALC] device#${calcDeviceId} metric "${metric.key}" is part of an on_pull dependency cycle → on_pull disabled`
    );
    return;
  }
  const cfg = metric.calc || {};
  const inputs = Array.isArray(cfg.inputs) ? cfg.inputs : [];
  const onInputs = cfg.trigger?.onInputs;
  const target = { deviceId: Number(calcDeviceId), metricKey: metric.key };
  const tnk = nodeKey(calcDeviceId, metric.key);
  for (const inp of inputs) {
    if (!inp || !inp.metricKey) continue;
    if (Array.isArray(onInputs) && onInputs.length && !onInputs.includes(inp.var)) continue;
    const srcDev = Number(inp.deviceId || calcDeviceId);
    const sk = srcKeyOf(srcDev, inp.metricKey);
    if (!onPullIndex.has(sk)) onPullIndex.set(sk, new Map());
    onPullIndex.get(sk).set(tnk, target);
  }
}

function clearDeviceSchedules(deviceId) {
  const intervals = calcIntervals.get(deviceId);
  if (intervals) {
    for (const id of intervals.values()) clearInterval(id);
    intervals.clear();
  }
  const crons = calcCrons.get(deviceId);
  if (crons) {
    for (const task of crons.values()) {
      try {
        task.stop();
      } catch (_e) {
        /* ignore */
      }
    }
    crons.clear();
  }
}

/** (Re)register all calc metrics for a device. Safe to call on reload. */
function fetchCalc(device) {
  calcRegistry.set(Number(device.id), device);
  clearDeviceSchedules(device.id);
  removeDeviceFromOnPullIndex(device.id);

  const calcMetrics = (device.metrics || []).filter((m) => m.type === 'calc' && metricEnabled(m));
  if (calcMetrics.length === 0) return;

  // Refresh the cycle map now that this device's metrics are in the registry.
  rebuildCalcGraph();

  if (!calcIntervals.has(device.id)) calcIntervals.set(device.id, new Map());
  if (!calcCrons.has(device.id)) calcCrons.set(device.id, new Map());
  const intervals = calcIntervals.get(device.id);
  const crons = calcCrons.get(device.id);

  //console.log(`[CALC] device#${device.id} "${device.name}" calc metrics=${calcMetrics.length}`);

  for (const metric of calcMetrics) {
    const cfg = metric.calc || {};
    const mode = String(cfg.trigger?.mode || 'interval').toLowerCase();

    if (mode === 'schedule' && cfg.trigger?.cron) {
      if (!cron.validate(cfg.trigger.cron)) {
        console.error(`[CALC] device#${device.id} - ${metric.key}: invalid cron "${cfg.trigger.cron}"`);
        continue;
      }
      const task = cron.schedule(cfg.trigger.cron, () => {
        recompute(device.id, metric.key).catch(() => {});
      });
      crons.set(metric.key, task);
      //console.log(`[CALC] device#${device.id} - ${metric.key}: schedule "${cfg.trigger.cron}"`);
    } else if (mode === 'on_pull') {
      registerOnPull(device.id, metric);
      recompute(device.id, metric.key).catch(() => {}); // seed once
      //console.log(`[CALC] device#${device.id} - ${metric.key}: on_pull`);
    } else {
      const sec = Math.max(Number(cfg.trigger?.intervalSec || metric.pull_rate || 10), 1);
      const id = setInterval(() => {
        recompute(device.id, metric.key).catch(() => {});
      }, sec * 1000);
      intervals.set(metric.key, id);
      recompute(device.id, metric.key).catch(() => {}); // run once now
      //console.log(`[CALC] device#${device.id} - ${metric.key}: interval ${sec}s`);
    }
  }
}

function cleanupCalcDevice(deviceId) {
  const id = Number(deviceId);
  clearDeviceSchedules(id);
  calcIntervals.delete(id);
  calcCrons.delete(id);
  removeDeviceFromOnPullIndex(id);
  calcRegistry.delete(id);
  rebuildCalcGraph();
}

/**
 * Called by dataSaver after each save: trigger any on_pull calc metrics that
 * depend on the just-updated (deviceId, metricKey) values.
 * @param points array of { key, value, timestamp }
 */
function onMetricsUpdated(srcDeviceId, points) {
  if (onPullIndex.size === 0 || !Array.isArray(points)) return;
  for (const p of points) {
    const sk = srcKeyOf(srcDeviceId, p.key);
    const targets = onPullIndex.get(sk);
    if (!targets || targets.size === 0) continue;
    for (const t of targets.values()) {
      // Don't let a calc retrigger off its own output write.
      if (Number(t.deviceId) === Number(srcDeviceId) && String(t.metricKey) === String(p.key)) continue;
      recompute(t.deviceId, t.metricKey, {
        deviceId: srcDeviceId,
        metricKey: p.key,
        value: p.value,
      }).catch(() => {});
    }
  }
}

module.exports = { fetchCalc, cleanupCalcDevice, onMetricsUpdated, recompute };
