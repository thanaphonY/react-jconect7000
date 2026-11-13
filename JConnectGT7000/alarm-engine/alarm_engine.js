const db = require("./db");
const dayjs = require("dayjs");
const utc = require("dayjs/plugin/utc");
const tz = require("dayjs/plugin/timezone");
const crypto = require("crypto");

dayjs.extend(utc);
dayjs.extend(tz);

function normalize(val, type) {
  if (type === "float") return parseFloat(val);
  if (type === "int") return parseInt(val, 10);
  if (type === "string") return String(val);
  if (type === "boolean" || type === "bool") {
    if (val === "true" || val === "1" || val === 1 || val === true) return true;
    if (val === "false" || val === "0" || val === 0 || val === false) return false;
    return Boolean(val);
  }
  if (type === "json") return JSON.parse(val);
  if (type === "date") return new Date(val);
  if (type === "time") return new Date(`1970-01-01T${val}`).getTime();
  if (type === "datetime") return new Date(val);
  if (type === "timestamp") return new Date(val).getTime();
  return String(val);
}

function compare(val, operator, expected, datatype = "float") {
  const left = normalize(val, datatype);
  const right = normalize(expected, datatype);
  //console.log("[compare] left", left, "right", right, "operator", operator);
  switch (operator) {
    case ">":
      return left > right;
    case ">=":
      return left >= right;
    case "<":
      return left < right;
    case "<=":
      return left <= right;
    case "==":
      return left == right;
    case "!=":
      return left != right;
    default:
      return false;
  }
}

function isWithinDuration(durationList) {
  if (!Array.isArray(durationList) || durationList.length === 0) return true;
  const now = dayjs().tz("Asia/Bangkok");
  const currentDay = now.day();
  const currentTime = now.format("HH:mm");

  return durationList.some((d) => {
    if (!d.days.includes(currentDay)) return false;
    const start = d.start;
    const end = d.end;
    if (start <= end) {
      return currentTime >= start && currentTime <= end;
    }
    return currentTime >= start || currentTime <= end;
  });
}

function getTriggerId(trigger) {
  if (trigger.id) return trigger.id;
  const str = JSON.stringify(trigger);
  return crypto.createHash("md5").update(str).digest("hex");
}

function isTriggerEnabled(trigger) {
  return trigger.enable === true || trigger.enable === "true";
}

function formatClearMessage(cond, device, metric, value) {
  return (cond.message || `Cleared by clear_condition for ${device.name} ${metric.label}, value: ${value}`)
    .replace("{{device}}", device.name)
    .replace("{{label}}", metric.label)
    .replace("{{value}}", value);
}

function resolveClearCondition(trigger, value, datatype) {
  if (!trigger?.clear_condition) return null;
  const cond = trigger.clear_condition;
  if (!compare(value, cond.operator || "==", cond.value, datatype)) return null;
  return { cond, trigger };
}

async function autoClearAlarmState({
  device,
  metric,
  metricKey,
  prevState,
  value,
  cond,
  trigger,
  now,
  rowTriggerId,
}) {
  const msg = formatClearMessage(cond, device, metric, value);
  const channelIds = trigger.channels || [];
  const channelStr = JSON.stringify(channelIds);
  const durationMinutes = dayjs().diff(dayjs(prevState.lastTriggered), "minute");

  await db.query(
    `INSERT INTO alarm_logs
       (device_id, metric_key, trigger_id, value, message, severity, channels, created_at, trigger_type, duration_minutes)
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8::timestamptz, $9, $10)`,
    [
      device.id,
      metricKey,
      rowTriggerId,
      value,
      msg,
      trigger.severity || prevState.severity || "warning",
      channelStr,
      now,
      "auto_clear",
      durationMinutes,
    ]
  );

  await db.query(
    `UPDATE alarm_state
        SET active = false,
            acknowledged = false,
            last_cleared = $1::timestamptz,
            message = $2
      WHERE device_id = $3 AND metric_key = $4 AND trigger_id = $5`,
    [now, msg, device.id, metricKey, rowTriggerId]
  );
}

/**
 * Maintenance mode: when a device's `config.maintenance` is on, suppress alarm
 * creation + notifications. Honors optional `config.maintenanceUntil` (ISO) so
 * the window auto-expires without anyone having to toggle it back off.
 */
function isInMaintenance(device) {
  let cfg = device?.config;
  if (typeof cfg === "string") {
    try {
      cfg = JSON.parse(cfg);
    } catch {
      cfg = null;
    }
  }
  if (!cfg || typeof cfg !== "object") return false;
  if (cfg.maintenance !== true && cfg.maintenance !== "true") return false;
  const until = cfg.maintenanceUntil;
  if (until) {
    const t = new Date(until).getTime();
    if (Number.isFinite(t) && Date.now() > t) return false; // window expired
  }
  return true;
}

async function evaluate(device, metric, value) {
  if (!metric.triggers || !Array.isArray(metric.triggers)) return;
  if (isInMaintenance(device)) {
    //console.log(`[evaluate] device ${device.id} in maintenance → alarms suppressed`);
    return;
  }

  const now = dayjs().tz("Asia/Bangkok").toISOString();
  const metricKey = metric.key;
  const datatype = metric.datatype || "float";

  for (const trigger of metric.triggers) {
    const triggerId = getTriggerId(trigger);

    const stateRes = await db.query(
      `SELECT *, last_triggered AS "lastTriggered", trigger_id AS "triggerId"
         FROM alarm_state
        WHERE device_id = $1 AND metric_key = $2 AND trigger_id = $3`,
      [device.id, metricKey, triggerId]
    );
    const stateRow = stateRes.rows?.[0];
    const prevActive = Boolean(stateRow?.active);

    if (!prevActive) {
      //console.log("[evaluate] inactive / new episode", triggerId, "trigger", trigger);
    } else {
      //console.log("[evaluate] triggerId", triggerId, "active state", stateRow, "trigger", trigger);
    }

    const enabled = trigger.enable === true || trigger.enable === "true";
    if (!enabled) continue;

    const matched = compare(value, trigger.operator || "==", trigger.value, datatype);
    const validTime = !trigger.duration || isWithinDuration(trigger.duration);

    //console.log("[evaluate] value", value, "trigger.operator", trigger.operator, "trigger.value", trigger.value, "datatype", datatype);
    //console.log("[evaluate] matched", matched, "validTime", validTime);

    if (!matched || !validTime) continue;

    const channelIds = trigger.channels || [];
    const channelStr = JSON.stringify(channelIds);
    let triggerType = "trigger";

    const repeatMin = parseInt(trigger.repeat_interval, 10);
    const safeRepeatMin = Number.isNaN(repeatMin) ? 0 : repeatMin;

    if (prevActive) {
      const lastSent = dayjs(stateRow.lastTriggered).tz("Asia/Bangkok");
      const nowTime = dayjs().tz("Asia/Bangkok");
      const diffSeconds = nowTime.diff(lastSent, "second");
      //console.log("lastSent ", lastSent, " safeRepeatMin ", safeRepeatMin, "dif ", diffSeconds);
      if (safeRepeatMin === 0) {
        continue;
      }

      if (diffSeconds < safeRepeatMin * 60) {
        continue;
      }
      triggerType = "repeat";
      //console.log("triggerType", triggerType, "diffSeconds", diffSeconds, "safeRepeatMin", safeRepeatMin);
    }

    const msg = (trigger.message || "")
      .replace("{{device}}", device.name)
      .replace("{{label}}", metric.label)
      .replace("{{value}}", value);

    const msgalarm = triggerType === "repeat" ? `Alarm repeat!\n${msg}` : msg;

    await db.query(
      `INSERT INTO alarm_logs
         (device_id, metric_key, trigger_id, value, message, severity, channels, created_at, trigger_type, duration_minutes)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8::timestamptz, $9, $10)`,
      [
        device.id,
        metricKey,
        triggerId,
        value,
        msgalarm,
        trigger.severity || "warning",
        JSON.stringify(channelIds),
        now,
        triggerType,
        null,
      ]
    );

    await db.query(
      `INSERT INTO alarm_state
         (device_id, metric_key, trigger_id, active, last_triggered, message, severity)
       VALUES ($1, $2, $3, true, $4::timestamptz, $5, $6)
       ON CONFLICT (device_id, metric_key, trigger_id)
       DO UPDATE SET active = true,
                     last_triggered = EXCLUDED.last_triggered,
                     message = EXCLUDED.message,
                     severity = EXCLUDED.severity`,
      [device.id, metricKey, triggerId, now, msgalarm, trigger.severity || "warning"]
    );
  }

  const triggerById = new Map(metric.triggers.map((t) => [getTriggerId(t), t]));

  const activeRes = await db.query(
    `SELECT *, last_triggered AS "lastTriggered", trigger_id AS "triggerId"
       FROM alarm_state
      WHERE device_id = $1 AND metric_key = $2 AND active = true`,
    [device.id, metricKey]
  );

  const clearedIds = new Set();

  for (const prevState of activeRes.rows || []) {
    const rowTriggerId = prevState.triggerId || prevState.trigger_id;
    if (clearedIds.has(rowTriggerId)) continue;

    const matchedTrigger = triggerById.get(rowTriggerId);
    let resolved = null;

    if (matchedTrigger?.clear_condition) {
      resolved = resolveClearCondition(matchedTrigger, value, datatype);
    } else if (!triggerById.has(rowTriggerId)) {
      for (const candidate of metric.triggers) {
        if (!isTriggerEnabled(candidate)) continue;
        resolved = resolveClearCondition(candidate, value, datatype);
        if (resolved) {
          console.log(
            "[evaluate] orphaned alarm",
            rowTriggerId,
            "cleared via clear_condition from trigger",
            getTriggerId(candidate)
          );
          break;
        }
      }
    }

    if (!resolved) continue;

    await autoClearAlarmState({
      device,
      metric,
      metricKey,
      prevState,
      value,
      cond: resolved.cond,
      trigger: resolved.trigger,
      now,
      rowTriggerId,
    });
    clearedIds.add(rowTriggerId);
  }

  if (Array.isArray(metric.normal_condition) && metric.normal_condition.length > 0) {
    const triggerId = getTriggerId(metric.triggers);
    const cond = metric.normal_condition;
    const stateRes = await db.query(
      `SELECT *, last_triggered AS "lastTriggered", trigger_id AS "triggerId"
         FROM alarm_state
        WHERE device_id = $1 AND metric_key = $2 AND active = true`,
      [device.id, metricKey]
    );

    const channelIds = cond.channels || [];
    const channelStr = JSON.stringify(channelIds);

    for (const prevState of stateRes.rows || []) {
      const isNormal = compare(value, cond.operator || "==", cond.value, datatype);
      if (isNormal && prevState?.active) {
        const durationMinutes = dayjs().diff(dayjs(prevState.lastTriggered), "minute");
        const msg = (cond.message || `Cleared by normal condition for ${device.name} ${metric.label}, value: ${value}`)
          .replace("{{device}}", device.name)
          .replace("{{label}}", metric.label)
          .replace("{{value}}", value)
          .replace("{{duration}}", durationMinutes);

        const rowTriggerId = prevState.triggerId || triggerId;
        await db.query(
          `INSERT INTO alarm_logs
             (device_id, metric_key, trigger_id, value, message, severity, channels, created_at, trigger_type, duration_minutes)
           VALUES ($1, $2, $3, $4, $5, $6, $7, $8::timestamptz, $9, $10)`,
          [
            device.id,
            metricKey,
            rowTriggerId,
            value,
            msg,
            prevState.severity || "warning",
            channelStr,
            now,
            "auto_clear",
            durationMinutes,
          ]
        );

        await db.query(
          `UPDATE alarm_state
              SET active = false,
                  acknowledged = false,
                  last_cleared = $1::timestamptz,
                  message = $2
            WHERE device_id = $3 AND metric_key = $4 AND trigger_id = $5`,
          [now, msg, device.id, metricKey, rowTriggerId]
        );
      }
    }
  }
}

module.exports = { evaluate };
