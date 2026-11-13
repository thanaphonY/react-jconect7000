const crypto = require("crypto");
const db = require("./db");
const dayjs = require("dayjs");
const utc = require("dayjs/plugin/utc");
const tz = require("dayjs/plugin/timezone");

dayjs.extend(utc);
dayjs.extend(tz);

/** IANA zone for {{date_time}}, {{date}}, {{time}}, {{timezone}} (matches alarm_engine Asia/Bangkok). */
const NOTIFICATION_TEMPLATE_TZ = "Asia/Bangkok";

/** Same id as alarm_engine.evaluate (MD5 of trigger JSON when trigger.id unset). */
function getTriggerId(trigger) {
  if (!trigger || typeof trigger !== "object") return "";
  if (trigger.id != null && String(trigger.id).trim() !== "") return String(trigger.id);
  try {
    const str = JSON.stringify(trigger);
    return crypto.createHash("md5").update(str).digest("hex");
  } catch {
    return "";
  }
}

function parseMetrics(raw) {
  if (raw == null) return [];
  if (Array.isArray(raw)) return raw;
  if (typeof raw === "string") {
    try {
      const v = JSON.parse(raw || "[]");
      return Array.isArray(v) ? v : [];
    } catch {
      return [];
    }
  }
  return [];
}

function metricRowKey(m) {
  return String(m?.key ?? m?.metricKey ?? m?.metric_key ?? "").trim();
}

function findMetric(metrics, metricKey) {
  const k = String(metricKey || "").trim();
  return metrics.find((m) => metricRowKey(m) === k) || null;
}

function findTriggerForJob(triggers, triggerId) {
  if (!Array.isArray(triggers) || triggerId == null || String(triggerId).trim() === "") return null;
  const tid = String(triggerId).trim();
  for (const t of triggers) {
    if (getTriggerId(t) === tid) return t;
  }
  return null;
}

function formatDt(v) {
  if (v == null || v === "") return "";
  try {
    return dayjs(v).tz(NOTIFICATION_TEMPLATE_TZ).format("DD/MM/YYYY HH:mm:ss");
  } catch {
    return "";
  }
}

function formatDateOnly(v) {
  if (v == null || v === "") return "";
  try {
    return dayjs(v).tz(NOTIFICATION_TEMPLATE_TZ).format("DD/MM/YYYY");
  } catch {
    return "";
  }
}

function formatTimeOnly(v) {
  if (v == null || v === "") return "";
  try {
    return dayjs(v).tz(NOTIFICATION_TEMPLATE_TZ).format("HH:mm:ss");
  } catch {
    return "";
  }
}

/** e.g. Asia/Bangkok (UTC +07:00) — offset from alarm log instant (DST-aware zones). */
function formatTimezoneDisplay(isoOrDate, tz) {
  try {
    const d = isoOrDate != null && isoOrDate !== "" ? dayjs(isoOrDate).tz(tz) : dayjs().tz(tz);
    if (!d.isValid()) return tz;
    const z = d.format("Z");
    return `${tz} (UTC ${z})`;
  } catch {
    return tz;
  }
}

/**
 * Build flat placeholder map for notification channel messageTemplate (alarm worker).
 * Supports dotted keys, e.g. {{metric.label}}, {{trigger.alarm.operator}}, {{alert.level}}, {{severity}}, {{severity_raw}},
 * {{date_time}}, {{date}}, {{time}}, {{timezone}} (from alarm log createdAt; timezone = IANA + UTC offset in parentheses).
 * legacy {{device}}, {{label}}, {{value}}, {{message}}.
 * On auto_clear: {{trigger.severity}} and {{severity}} append " (return to normal)"; {{severity_raw}} stays the configured value.
 */
async function buildAlarmTemplateVars(job) {
  const deviceNameFromJoin = String(job.deviceName || "").trim();
  const metricKey = String(job.metricKey || "");
  const triggerType = String(job.triggerType || "");
  const isClear = triggerType === "auto_clear";

  let deviceName = deviceNameFromJoin || String(job.deviceId ?? "");
  let metric = null;
  let trigger = null;

  try {
    const { rows } = await db.query(`SELECT name, metrics FROM devices WHERE id = $1`, [job.deviceId]);
    const row = rows[0];
    if (row) {
      if (row.name != null && String(row.name).trim() !== "") deviceName = String(row.name).trim();
      const metrics = parseMetrics(row.metrics);
      metric = findMetric(metrics, metricKey);
      if (metric && Array.isArray(metric.triggers)) {
        trigger = findTriggerForJob(metric.triggers, job.triggerId);
      }
    }
  } catch (e) {
    console.warn("[templateContext] device lookup failed:", e?.message || e);
  }

  const metricLabel = String(metric?.label ?? "").trim() || metricKey;
  const metricUnit = metric?.unit != null && String(metric.unit).trim() !== "" ? String(metric.unit) : "";

  const alarmOp = String(trigger?.operator ?? "");
  const alarmVal = trigger?.value != null && trigger?.value !== "" ? String(trigger.value) : "";
  const clearOp = String(trigger?.clear_condition?.operator ?? "");
  const clearVal =
    trigger?.clear_condition?.value != null && trigger.clear_condition.value !== ""
      ? String(trigger.clear_condition.value)
      : "";

  const contextualOp = isClear ? clearOp : alarmOp;
  const contextualVal = isClear ? clearVal : alarmVal;

  const createdAt = job.createdAt;
  const durRaw = job.durationMinutes;
  const durMin =
    durRaw != null && durRaw !== "" && Number.isFinite(Number(durRaw)) ? Math.max(0, Math.floor(Number(durRaw))) : null;

  let timeDetected = "";
  let timeReturnedNormal = "";
  if (isClear) {
    timeReturnedNormal = formatDt(createdAt);
    if (durMin != null && durMin > 0 && createdAt) {
      timeDetected = formatDt(dayjs(createdAt).subtract(durMin, "minute").toDate());
    }
  } else {
    timeDetected = formatDt(createdAt);
  }

  const durationMinutesStr = durMin != null ? String(durMin) : "";
  const durationHuman = durMin != null ? `${durMin} min` : "";

  const msg = String(job.message ?? "");
  const severityRaw = String(trigger?.severity ?? job.severity ?? "").trim();
  const returnNormalSuffix = " (return to normal)";
  /** {{trigger.severity}} / {{severity}}: same display; on clear append suffix so templates match ops wording. */
  const triggerSeverityDisplay =
    isClear && severityRaw ? `${severityRaw}${returnNormalSuffix}` : isClear ? "(return to normal)" : severityRaw;

  /** Human-readable phase from alarm_logs.triggerType — Alarm vs Return To Normal. */
  const alertLevel = isClear ? "Return To Normal" : "Alarm";

  const valueStr = job.value != null && job.value !== "" ? String(job.value) : "";

  const episodeId = job.episodeId != null && String(job.episodeId).trim() !== "" ? String(job.episodeId).trim() : "";

  const vars = {
    "episode.id": episodeId,
    episode_id: episodeId,
    "device.name": deviceName,
    "metric.key": metricKey,
    "metric.label": metricLabel,
    "metric.value": valueStr,
    "metric.unit": metricUnit,
    "trigger.severity": triggerSeverityDisplay,
    "alert.level": alertLevel,
    alert_level: alertLevel,
    "trigger.alarm.operator": alarmOp,
    "trigger.alarm.value": alarmVal,
    "trigger.clear.operator": clearOp,
    "trigger.clear.value": clearVal,
    "trigger.operator": contextualOp,
    "trigger.value": contextualVal,
    message: msg,
    "time.detected": timeDetected,
    "time.returned": timeReturnedNormal,
    "time.returned_normal": timeReturnedNormal,
    "time.returned_to_normal": timeReturnedNormal,
    time_detected: timeDetected,
    time_returned_normal: timeReturnedNormal,
    "duration.minutes": durationMinutesStr,
    duration: durationHuman || durationMinutesStr,
    // legacy + internal
    device: deviceName,
    label: metricLabel,
    value: valueStr,
    deviceName,
    metricKey,
    metricLabel,
    severity: triggerSeverityDisplay,
    severity_raw: severityRaw,
    createdAt,
    date_time: formatDt(createdAt),
    date: formatDateOnly(createdAt),
    time: formatTimeOnly(createdAt),
    timezone: formatTimezoneDisplay(createdAt, NOTIFICATION_TEMPLATE_TZ),
    triggerType,
    durationMinutes: durationMinutesStr,
  };

  return vars;
}

/**
 * Replace {{key}} / {{ key }} / {{a.b}} using longest keys first so nested names win over prefixes.
 */
function renderNotificationTemplate(template, vars) {
  let t = template == null ? "" : String(template);
  const keys = Object.keys(vars).filter((k) => vars[k] !== undefined && vars[k] !== null);
  keys.sort((a, b) => b.length - a.length);

  for (const k of keys) {
    const esc = k.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const re = new RegExp(`\\{\\{\\s*${esc}\\s*\\}\\}`, "gi");
    t = t.replace(re, String(vars[k]));
  }

  t = t.replace(/\{\{\s*([^}]+?)\s*\}\}/g, () => "");
  return t;
}

module.exports = {
  buildAlarmTemplateVars,
  renderNotificationTemplate,
};
