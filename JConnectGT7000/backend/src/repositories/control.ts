import type { Queryable } from '../db.js';
import { callControlLogic } from '../services/controlLogic.js';

export type ControlHealth = { ok: boolean; message: string };

export async function controlHealth(): Promise<ControlHealth> {
  const result = await callControlLogic('GET', '/health');
  if (result.ok && result.data.ok) {
    return { ok: true, message: 'control-logic online' };
  }
  return { ok: false, message: 'control-logic offline' };
}

function groupQuery(groupId: number | null | undefined, ungrouped: boolean): string {
  if (ungrouped) return '?ungrouped=1';
  if (groupId && groupId > 0) return `?group_id=${groupId}`;
  return '';
}

export async function listGroups(): Promise<ControlLogicPayload> {
  return callControlLogic('GET', '/api/groups');
}

export async function createGroup(payload: Record<string, unknown>): Promise<ControlLogicPayload> {
  return callControlLogic('POST', '/api/groups', payload);
}

export async function updateGroup(
  id: number,
  payload: Record<string, unknown>,
): Promise<ControlLogicPayload> {
  return callControlLogic('PUT', `/api/groups/${id}`, payload);
}

export async function deleteGroup(id: number): Promise<ControlLogicPayload> {
  return callControlLogic('DELETE', `/api/groups/${id}`);
}

export async function listCommands(
  groupId?: number | null,
  ungrouped = false,
): Promise<ControlLogicPayload> {
  return callControlLogic('GET', `/api/commands${groupQuery(groupId, ungrouped)}`);
}

export async function createCommand(payload: Record<string, unknown>): Promise<ControlLogicPayload> {
  return callControlLogic('POST', '/api/commands', payload);
}

export async function updateCommand(
  id: number,
  payload: Record<string, unknown>,
): Promise<ControlLogicPayload> {
  return callControlLogic('PUT', `/api/commands/${id}`, payload);
}

export async function deleteCommand(id: number): Promise<ControlLogicPayload> {
  return callControlLogic('DELETE', `/api/commands/${id}`);
}

export async function executeCommand(
  id: number,
  value: string,
  userId: number,
): Promise<ControlLogicPayload> {
  return callControlLogic('POST', `/api/commands/${id}/execute`, {
    value,
    source: 'manual',
    user_id: userId,
  });
}

export async function releaseHold(id: number, userId: number): Promise<ControlLogicPayload> {
  return callControlLogic('POST', `/api/commands/${id}/auto`, { user_id: userId });
}

export async function listLogs(
  limit = 50,
  groupId?: number | null,
  ungrouped = false,
): Promise<ControlLogicPayload> {
  const qs = [`limit=${limit}`];
  if (ungrouped) qs.push('ungrouped=1');
  else if (groupId && groupId > 0) qs.push(`group_id=${groupId}`);
  return callControlLogic('GET', `/api/logs?${qs.join('&')}`);
}

export async function listSchedules(
  groupId?: number | null,
  ungrouped = false,
): Promise<ControlLogicPayload> {
  return callControlLogic('GET', `/api/schedules${groupQuery(groupId, ungrouped)}`);
}

export async function createSchedule(payload: Record<string, unknown>): Promise<ControlLogicPayload> {
  return callControlLogic('POST', '/api/schedules', payload);
}

export async function updateSchedule(
  id: number,
  payload: Record<string, unknown>,
): Promise<ControlLogicPayload> {
  return callControlLogic('PUT', `/api/schedules/${id}`, payload);
}

export async function deleteSchedule(id: number): Promise<ControlLogicPayload> {
  return callControlLogic('DELETE', `/api/schedules/${id}`);
}

export async function listSequences(
  groupId?: number | null,
  ungrouped = false,
): Promise<ControlLogicPayload> {
  return callControlLogic('GET', `/api/sequences${groupQuery(groupId, ungrouped)}`);
}

export async function createSequence(payload: Record<string, unknown>): Promise<ControlLogicPayload> {
  return callControlLogic('POST', '/api/sequences', payload);
}

export async function updateSequence(
  id: number,
  payload: Record<string, unknown>,
): Promise<ControlLogicPayload> {
  return callControlLogic('PUT', `/api/sequences/${id}`, payload);
}

export async function deleteSequence(id: number): Promise<ControlLogicPayload> {
  return callControlLogic('DELETE', `/api/sequences/${id}`);
}

export async function executeSequence(id: number, userId: number): Promise<ControlLogicPayload> {
  return callControlLogic('POST', `/api/sequences/${id}/execute`, { user_id: userId });
}

export async function stopSequence(id: number): Promise<ControlLogicPayload> {
  return callControlLogic('POST', `/api/sequences/${id}/stop`);
}

export async function pauseSequence(id: number): Promise<ControlLogicPayload> {
  return callControlLogic('POST', `/api/sequences/${id}/pause`);
}

export async function resumeSequence(id: number): Promise<ControlLogicPayload> {
  return callControlLogic('POST', `/api/sequences/${id}/resume`);
}

export async function listRules(
  groupId?: number | null,
  ungrouped = false,
): Promise<ControlLogicPayload> {
  return callControlLogic('GET', `/api/rules${groupQuery(groupId, ungrouped)}`);
}

export async function createRule(payload: Record<string, unknown>): Promise<ControlLogicPayload> {
  return callControlLogic('POST', '/api/rules', payload);
}

export async function updateRule(
  id: number,
  payload: Record<string, unknown>,
): Promise<ControlLogicPayload> {
  return callControlLogic('PUT', `/api/rules/${id}`, payload);
}

export async function deleteRule(id: number): Promise<ControlLogicPayload> {
  return callControlLogic('DELETE', `/api/rules/${id}`);
}

type ControlLogicPayload =
  | { ok: true; data: Record<string, unknown>; httpStatus: number }
  | { ok: false; error: string; httpStatus?: number; data?: Record<string, unknown> };

export interface ControlDeviceOption {
  id: number;
  name: string;
}

export async function listControlDevices(db: Queryable): Promise<ControlDeviceOption[]> {
  const result = await db.query(
    `SELECT id, name FROM devices WHERE is_deleted = false ORDER BY name ASC`,
  );
  return (result.rows as { id: number; name: string }[]).map((row) => ({
    id: row.id,
    name: row.name ?? '',
  }));
}

function decodeJson(value: unknown): Record<string, unknown> | unknown[] {
  if (Array.isArray(value) || (typeof value === 'object' && value !== null)) {
    return value as Record<string, unknown> | unknown[];
  }
  if (typeof value !== 'string' || value === '') return [];
  try {
    const parsed = JSON.parse(value) as unknown;
    if (Array.isArray(parsed) || (typeof parsed === 'object' && parsed !== null)) {
      return parsed as Record<string, unknown> | unknown[];
    }
  } catch {
    /* ignore */
  }
  return [];
}

function asObject(value: unknown): Record<string, unknown> {
  const decoded = decodeJson(value);
  return typeof decoded === 'object' && decoded !== null && !Array.isArray(decoded)
    ? (decoded as Record<string, unknown>)
    : {};
}

function asArray(value: unknown): unknown[] {
  const decoded = decodeJson(value);
  return Array.isArray(decoded) ? decoded : [];
}

function isWriteTargetMetric(metric: Record<string, unknown>): boolean {
  const key = String(metric.key ?? '')
    .trim()
    .toUpperCase();
  const src =
    typeof metric.source === 'object' && metric.source !== null && !Array.isArray(metric.source)
      ? (metric.source as Record<string, unknown>)
      : {};
  const kind = String(src.kind ?? '').toLowerCase();
  const oid = String(metric.oid ?? '');
  if (/^DO\d+$/.test(key)) return true;
  if (['digital_output', 'do', 'relay'].includes(kind)) return true;
  if (oid.startsWith('1.3.6.1.4.1.12020.1.3.')) return true;
  if (metric.writable || String(metric.access ?? '').toLowerCase() === 'write') return true;
  return false;
}

function buildWriteTarget(
  metric: Record<string, unknown>,
  gatewayDeviceId: number | null,
): Record<string, unknown> | null {
  const key = String(metric.key ?? '').trim();
  if (!key) return null;
  const oid = String(metric.oid ?? '').trim();
  const type = String(metric.type ?? '').toLowerCase();
  const isJ1000Do = oid.startsWith('1.3.6.1.4.1.12020.1.3.') || /^DO\d+$/i.test(key);

  let protocol = 'snmp_set';
  if (type.startsWith('modbus')) protocol = 'modbus_write';
  else if (type.startsWith('bacnet')) protocol = 'bacnet_write';
  else if (type.startsWith('mqtt')) protocol = 'mqtt_pub';

  const writeConfig: Record<string, unknown> = { metric_key: key };
  if (oid) writeConfig.oid = oid;
  if (gatewayDeviceId) writeConfig.gateway_device_id = gatewayDeviceId;
  writeConfig.on_value = isJ1000Do ? 3 : 1;
  writeConfig.off_value = 0;
  if (metric.function_code !== undefined) writeConfig.function_code = metric.function_code;
  if (metric.address !== undefined) writeConfig.address = metric.address;

  const label = String(metric.label ?? key);
  let hint = oid || key;
  if (gatewayDeviceId) hint = `gateway DO / dry contact · ${hint}`;

  return { key, label, hint, protocol, write_config: writeConfig };
}

async function loadDeviceRow(
  db: Queryable,
  deviceId: number,
  withTemplate: boolean,
): Promise<Record<string, unknown> | null> {
  if (deviceId <= 0) return null;
  if (withTemplate) {
    const result = await db.query(
      `SELECT d.id, d.name, d.metrics, d.communication_config, d.config,
              t.protocol AS template_protocol, t.name AS template_name
       FROM devices d
       LEFT JOIN device_templates t ON t.id = d.device_template_id
       WHERE d.id = $1 AND d.is_deleted = false`,
      [deviceId],
    );
    return (result.rows[0] as Record<string, unknown> | undefined) ?? null;
  }
  const result = await db.query(
    `SELECT id, name, metrics, communication_config, config
     FROM devices WHERE id = $1 AND is_deleted = false`,
    [deviceId],
  );
  return (result.rows[0] as Record<string, unknown> | undefined) ?? null;
}

export async function getDeviceControlContext(
  db: Queryable,
  deviceId: number,
  writeDeviceId = 0,
): Promise<Record<string, unknown> | null> {
  const row = await loadDeviceRow(db, deviceId, true);
  if (!row) return null;

  const metrics = asArray(row.metrics);
  const comm = asObject(row.communication_config);
  const config = asObject(row.config);
  const role = String(config.role ?? '').toLowerCase();
  const protocol = String(comm.protocol ?? row.template_protocol ?? '').toLowerCase();
  const isEndpoint = role === 'endpoint' || protocol === 'gateway_endpoint';

  const source =
    typeof comm.source === 'object' && comm.source !== null && !Array.isArray(comm.source)
      ? (comm.source as Record<string, unknown>)
      : {};
  const gatewayId = Number(source.gateway_device_id ?? config.via_gateway ?? 0) || 0;
  const gateway = gatewayId > 0 ? await loadDeviceRow(db, gatewayId, false) : null;

  const feedback: Record<string, unknown>[] = [];
  for (const metric of metrics) {
    if (!metric || typeof metric !== 'object') continue;
    const m = metric as Record<string, unknown>;
    const key = String(m.key ?? '').trim();
    if (!key) continue;
    const src =
      typeof m.source === 'object' && m.source !== null && !Array.isArray(m.source)
        ? (m.source as Record<string, unknown>)
        : {};
    const gwKey = String(src.gateway_metric_key ?? src.metric_key ?? '').trim();
    feedback.push({
      key,
      label: String(m.label ?? key),
      type: String(m.type ?? ''),
      from_gateway: gwKey || null,
    });
  }

  const explicitWrite =
    writeDeviceId > 0 ? await loadDeviceRow(db, writeDeviceId, false) : null;
  const writeRow = explicitWrite ?? (isEndpoint && gateway ? gateway : row);
  const resolvedWriteId = Number(writeRow.id);
  const crossDevice = resolvedWriteId !== Number(row.id);
  const writeMetrics = asArray(writeRow.metrics);

  const writeTargets: Record<string, unknown>[] = [];
  for (const metric of writeMetrics) {
    if (!metric || typeof metric !== 'object') continue;
    const m = metric as Record<string, unknown>;
    if (!isWriteTargetMetric(m)) continue;
    const built = buildWriteTarget(m, crossDevice ? resolvedWriteId : null);
    if (built) writeTargets.push(built);
  }

  return {
    id: Number(row.id),
    name: row.name,
    role: isEndpoint ? 'endpoint' : role === 'gateway' ? 'gateway' : 'device',
    protocol,
    template_name: row.template_name ?? null,
    gateway: gateway
      ? { id: Number(gateway.id), name: gateway.name }
      : null,
    write_via: {
      id: resolvedWriteId,
      name: writeRow.name,
      cross_device: crossDevice,
    },
    default_write_via_id: isEndpoint && gateway ? Number(gateway.id) : 0,
    feedback_metrics: feedback,
    write_targets: writeTargets,
  };
}

type MetricSample = {
  value: unknown;
  value_text: unknown;
  value_bool: unknown;
  value_json: unknown;
  timestamp: unknown;
};

async function latestMetricsMap(
  db: Queryable,
  deviceId: number,
): Promise<Record<string, MetricSample>> {
  const result = await db.query(
    `SELECT DISTINCT ON (key)
            key, value, value_text, value_bool, value_json, timestamp
     FROM device_data
     WHERE device_id = $1
     ORDER BY key, timestamp DESC`,
    [deviceId],
  );
  const out: Record<string, MetricSample> = {};
  for (const row of result.rows as {
    key: string;
    value: unknown;
    value_text: unknown;
    value_bool: unknown;
    value_json: unknown;
    timestamp: unknown;
  }[]) {
    out[row.key] = {
      value: row.value,
      value_text: row.value_text,
      value_bool: row.value_bool,
      value_json: row.value_json,
      timestamp:
        row.timestamp instanceof Date ? row.timestamp.toISOString() : row.timestamp,
    };
  }
  return out;
}

function normalizeMapList(raw: unknown): string[] {
  let list: unknown[] = [];
  if (typeof raw === 'string') list = raw.split(',');
  else if (Array.isArray(raw)) list = raw;
  return list.map((item) => String(item).trim()).filter(Boolean);
}

function valueInMap(candidates: unknown[], mapped: string[]): boolean {
  if (!mapped.length) return false;
  const needles: string[] = [];
  for (const c of candidates) {
    if (c === null || c === undefined || c === '') continue;
    needles.push(String(c).trim().toLowerCase());
    if (typeof c === 'number' || (typeof c === 'string' && c !== '' && !Number.isNaN(Number(c)))) {
      needles.push(String(0 + Number(c)));
    }
  }
  for (const m of mapped) {
    const ml = m.trim().toLowerCase();
    if (needles.includes(ml)) return true;
    if (!Number.isNaN(Number(m))) {
      for (const c of candidates) {
        if (c !== null && c !== undefined && c !== '' && !Number.isNaN(Number(c)) && Number(c) === Number(m)) {
          return true;
        }
      }
    }
  }
  return false;
}

function inferCommonStatus(value: unknown, text = ''): 'on' | 'off' | null {
  const tokens: string[] = [];
  if (text) tokens.push(text.trim().toLowerCase());
  if (value !== null && value !== undefined && value !== '') {
    tokens.push(String(value).trim().toLowerCase());
    if (!Number.isNaN(Number(value))) tokens.push(String(0 + Number(value)));
  }
  for (const t of tokens) {
    if (['3', 'on', 'running', 'run'].includes(t)) return 'on';
    if (['0', 'off', 'stopped', 'stop', 'idle'].includes(t)) return 'off';
  }
  return null;
}

function statusTextLabel(writeConfig: Record<string, unknown>, which: 'on' | 'off'): string {
  const key = which === 'off' ? 'feedback_off_label' : 'feedback_on_label';
  const s = String(writeConfig[key] ?? '').trim();
  if (s) return s;
  return which === 'off' ? 'Off' : 'On';
}

function interpretFeedback(
  latest: MetricSample | null | undefined,
  enabled: boolean,
  writeConfig: Record<string, unknown>,
): {
  state: string;
  label: string;
  value_display: string;
  timestamp: unknown;
  stale: boolean;
} {
  if (!enabled) {
    return { state: 'disabled', label: 'Disabled', value_display: '—', timestamp: null, stale: false };
  }
  if (!latest) {
    return { state: 'unknown', label: 'No data', value_display: '—', timestamp: null, stale: false };
  }

  const timestamp = latest.timestamp ?? null;
  let stale = false;
  if (timestamp) {
    const ts = Date.parse(String(timestamp));
    stale = !Number.isNaN(ts) && Date.now() - ts > 600_000;
  }

  const value = latest.value;
  const text = String(latest.value_text ?? '').trim();
  const bool = latest.value_bool;

  let display = '—';
  if (text) display = text;
  else if (value !== null && value !== undefined && value !== '') {
    display = typeof value === 'number' || !Number.isNaN(Number(value))
      ? String(Number(value))
      : String(value);
  } else if (bool !== null && bool !== undefined && bool !== '') {
    const truth = bool === true || bool === 't' || bool === '1' || bool === 1;
    display = truth ? 'true' : 'false';
  }

  const fbOn = normalizeMapList(writeConfig.feedback_on);
  const fbOff = normalizeMapList(writeConfig.feedback_off);
  const onLabel = statusTextLabel(writeConfig, 'on');
  const offLabel = statusTextLabel(writeConfig, 'off');
  const candidates: unknown[] = [value, text];
  if (bool === true || bool === 't' || bool === '1' || bool === 1) {
    candidates.push('1', 'true', 'on');
  } else if (bool === false || bool === 'f' || bool === '0' || bool === 0) {
    candidates.push('0', 'false', 'off');
  }

  if (fbOn.length || fbOff.length) {
    const onHit = valueInMap(candidates, fbOn);
    const offHit = valueInMap(candidates, fbOff);
    let state = 'unknown';
    let label = display !== '—' ? display : 'Unknown';
    if (onHit && !offHit) {
      state = 'on';
      label = onLabel;
    } else if (offHit && !onHit) {
      state = 'off';
      label = offLabel;
    } else if (onHit && offHit) {
      state = 'unknown';
      label = 'Ambiguous';
    } else {
      const inferred = inferCommonStatus(value, text);
      if (inferred) {
        state = inferred;
        label = inferred === 'on' ? onLabel : offLabel;
      }
    }
    return { state, label, value_display: display, timestamp, stale };
  }

  const onWords = ['on', 'running', 'run', 'start', 'started', 'true', 'yes', 'open', 'active', '1'];
  const offWords = ['off', 'stopped', 'stop', 'false', 'no', 'idle', 'inactive', 'closed', '0'];

  let state = 'unknown';
  let label = 'Unknown';
  if (bool === true || bool === 't' || bool === '1' || bool === 1) {
    state = 'on';
    label = onLabel;
  } else if (bool === false || bool === 'f' || bool === '0' || bool === 0) {
    state = 'off';
    label = offLabel;
  } else if (text) {
    const tl = text.toLowerCase();
    if (onWords.includes(tl)) {
      state = 'on';
      label = onLabel;
    } else if (offWords.includes(tl)) {
      state = 'off';
      label = offLabel;
    } else {
      label = text;
    }
  }

  if (state === 'unknown' && value !== null && value !== undefined && value !== '') {
    const inferred = inferCommonStatus(value, text);
    if (inferred) {
      state = inferred;
      label = inferred === 'on' ? onLabel : offLabel;
    } else {
      const n = Number(value);
      if (n === 0) {
        state = 'off';
        label = offLabel;
      } else {
        state = 'on';
        label = onLabel;
      }
    }
  }

  if (state === 'unknown' && label === 'Unknown' && display !== '—') {
    label = display;
  }

  return { state, label, value_display: display, timestamp, stale };
}

function commandRequestedState(log: Record<string, unknown> | null): 'on' | 'off' | null {
  if (!log) return null;
  const v = String(log.requested_value ?? '')
    .trim()
    .toLowerCase();
  if (['0', 'off', 'false'].includes(v)) return 'off';
  if (['1', 'on', 'true'].includes(v)) return 'on';
  return null;
}

function cardTone(
  commandState: string | null,
  statusState: string,
  enabled: boolean,
): string {
  if (!enabled || statusState === 'disabled') return 'disabled';
  if (commandState === 'on' && statusState === 'on') return 'on';
  if (commandState === 'off' && statusState === 'off') return 'off';
  return 'mismatch';
}

export async function commandLiveStatus(
  db: Queryable,
  commands: Record<string, unknown>[],
  logs: Record<string, unknown>[] = [],
): Promise<{ items: Record<string, unknown>[]; summary: Record<string, number> }> {
  const latestByDevice: Record<number, Record<string, MetricSample>> = {};
  const lastLog: Record<number, Record<string, unknown>> = {};
  const lastWritten: Record<number, Record<string, unknown>> = {};

  for (const log of logs) {
    const cid = Number(log.command_id ?? 0);
    if (cid <= 0) continue;
    if (!lastLog[cid]) lastLog[cid] = log;
    if (!lastWritten[cid] && log.result === 'written') lastWritten[cid] = log;
  }

  const items: Record<string, unknown>[] = [];
  const summary: Record<string, number> = { on: 0, off: 0, mismatch: 0, unknown: 0, disabled: 0 };

  for (const c of commands) {
    const deviceId = Number(c.device_id ?? 0);
    const key = String(c.feedback_metric_key ?? '').trim();
    if (deviceId > 0 && !(deviceId in latestByDevice)) {
      latestByDevice[deviceId] = await latestMetricsMap(db, deviceId);
    }
    const latest =
      key && latestByDevice[deviceId] ? latestByDevice[deviceId][key] ?? null : null;
    let cfg = c.write_config;
    if (typeof cfg === 'string') {
      try {
        cfg = JSON.parse(cfg) as unknown;
      } catch {
        cfg = {};
      }
    }
    if (!cfg || typeof cfg !== 'object' || Array.isArray(cfg)) cfg = {};
    const interp = interpretFeedback(latest, Boolean(c.enabled), cfg as Record<string, unknown>);
    const cid = Number(c.id ?? 0);
    const log = lastWritten[cid] ?? lastLog[cid] ?? null;
    const commandState = commandRequestedState(log);
    const tone = cardTone(commandState, interp.state, Boolean(c.enabled));
    summary[tone] = (summary[tone] ?? 0) + 1;
    items.push({
      id: cid,
      name: String(c.name ?? ''),
      description: String(c.description ?? ''),
      device_id: deviceId,
      device_name: String(c.device_name ?? ''),
      enabled: Boolean(c.enabled),
      feedback_metric_key: key || null,
      state: interp.state,
      state_label: interp.label,
      value_display: interp.value_display,
      timestamp: interp.timestamp,
      stale: interp.stale,
      command_state: commandState,
      command_label: commandState === 'on' ? 'On' : commandState === 'off' ? 'Off' : null,
      card_tone: tone,
      hold_source: c.hold_source === 'manual' ? 'manual' : null,
      last_action: log
        ? {
            source: log.source ?? '',
            sequence_name: log.sequence_name ?? '',
            rule_name: log.rule_name ?? '',
            value: log.requested_value ?? '',
            result: log.result ?? '',
            at: log.created_at ?? '',
          }
        : null,
    });
  }

  items.sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? '')));
  return { items, summary };
}

function ruleDecode(raw: unknown): Record<string, unknown> {
  if (typeof raw === 'object' && raw !== null && !Array.isArray(raw)) {
    return raw as Record<string, unknown>;
  }
  if (typeof raw === 'string' && raw !== '') {
    try {
      const decoded = JSON.parse(raw) as unknown;
      if (typeof decoded === 'object' && decoded !== null && !Array.isArray(decoded)) {
        return decoded as Record<string, unknown>;
      }
    } catch {
      /* ignore */
    }
  }
  return {};
}

function ruleConditionSummary(
  rule: Record<string, unknown>,
  commandNames: Record<number, string>,
  deviceNames: Record<number, string>,
): string {
  const c = ruleDecode(rule.conditions);
  const items = Array.isArray(c.items) ? c.items : [];
  if (!items.length) return 'No conditions';
  const match = String(c.match ?? 'all').toLowerCase() === 'any' ? 'Any' : 'All';
  const parts: string[] = [];
  for (const item of items) {
    if (!item || typeof item !== 'object') continue;
    const row = item as Record<string, unknown>;
    const type = String(row.type ?? '').toLowerCase();
    if (type === 'command_status') {
      const cid = Number(row.command_id ?? 0);
      const name = commandNames[cid] ?? `#${cid}`;
      const state = String(row.state ?? 'off').toLowerCase() === 'on' ? 'On' : 'Off';
      parts.push(`${name} is ${state}`);
    } else if (type === 'alarm') {
      const did = Number(row.device_id ?? 0);
      const dname = deviceNames[did] ?? `device #${did}`;
      const key = String(row.metric_key ?? '').trim();
      parts.push(key ? `alarm ${dname} · ${key}` : `any alarm on ${dname}`);
    } else if (type === 'metric') {
      const did = Number(row.device_id ?? 0);
      const dname = deviceNames[did] ?? `device #${did}`;
      const key = String(row.key ?? row.metric_key ?? '');
      const op = String(row.op ?? '>');
      const val = String(row.value ?? '');
      parts.push(`${dname} ${key} ${op} ${val}`);
    }
  }
  return `${match}: ${parts.length ? parts.join('; ') : 'No conditions'}`;
}

function ruleActionSummary(
  rule: Record<string, unknown>,
  commandNames: Record<number, string>,
  sequenceNames: Record<number, string>,
): string {
  const a = ruleDecode(rule.action);
  const kind = String(a.kind ?? a.type ?? '').toLowerCase();
  if (kind === 'sequence') {
    const sid = Number(a.sequence_id ?? 0);
    return `Sequence ${sequenceNames[sid] ?? `#${sid}`}`;
  }
  const cmds = Array.isArray(a.commands) ? a.commands : [];
  const parts: string[] = [];
  for (const c of cmds) {
    if (!c || typeof c !== 'object') continue;
    const row = c as Record<string, unknown>;
    const cid = Number(row.command_id ?? 0);
    const name = commandNames[cid] ?? `#${cid}`;
    const action = String(row.action ?? 'on').toLowerCase() === 'off' ? 'Off' : 'On';
    parts.push(`${name} ${action}`);
  }
  return parts.length ? parts.join(', ') : 'No action';
}

export function runningSequenceDiagrams(
  sequences: Record<string, unknown>[],
  commands: Record<string, unknown>[],
): Record<string, unknown>[] {
  const names: Record<number, { name: string; device_name: string }> = {};
  for (const c of commands) {
    const cid = Number(c.id ?? 0);
    if (cid > 0) {
      names[cid] = {
        name: String(c.name ?? ''),
        device_name: String(c.device_name ?? ''),
      };
    }
  }
  const out: Record<string, unknown>[] = [];
  for (const seq of sequences) {
    if (!seq.running) continue;
    let steps: unknown = seq.steps;
    if (typeof steps === 'string') {
      try {
        steps = JSON.parse(steps) as unknown;
      } catch {
        steps = [];
      }
    }
    const stepList: unknown[] = Array.isArray(steps) ? steps : [];
    const formatted: Record<string, unknown>[] = [];
    let i = 0;
    for (const s of stepList) {
      if (!s || typeof s !== 'object') continue;
      const row = s as Record<string, unknown>;
      const cid = Number(row.command_id ?? 0);
      if (cid <= 0) continue;
      i += 1;
      formatted.push({
        index: Number(row.index ?? i),
        command_id: cid,
        command_name: names[cid]?.name ?? `#${cid}`,
        device_name: names[cid]?.device_name ?? '',
        action: row.action === 'off' ? 'off' : 'on',
        wait: ['on', 'off'].includes(String(row.wait ?? '')) ? row.wait : 'none',
        delay_sec: Number(row.delay_sec ?? 0),
      });
    }
    const progress =
      typeof seq.progress === 'object' && seq.progress !== null && !Array.isArray(seq.progress)
        ? (seq.progress as Record<string, unknown>)
        : null;
    out.push({
      id: Number(seq.id ?? 0),
      name: String(seq.name ?? ''),
      loop: Boolean(seq.loop || seq.loop_enabled),
      loop_count: Number(seq.loop_count ?? 0),
      loop_delay_sec: Number(seq.loop_delay_sec ?? 0),
      running: true,
      paused:
        Boolean(seq.paused) ||
        (progress !== null &&
          (progress.phase === 'paused' || Boolean(progress.paused))),
      progress,
      steps: formatted,
    });
  }
  return out;
}

export function activeRuleCards(
  rules: Record<string, unknown>[],
  commands: Record<string, unknown>[],
  sequences: Record<string, unknown>[],
  devices: ControlDeviceOption[],
): Record<string, unknown>[] {
  const commandNames: Record<number, string> = {};
  for (const c of commands) {
    const cid = Number(c.id ?? 0);
    if (cid > 0) commandNames[cid] = String(c.name ?? '');
  }
  const sequenceNames: Record<number, string> = {};
  for (const s of sequences) {
    const sid = Number(s.id ?? 0);
    if (sid > 0) sequenceNames[sid] = String(s.name ?? '');
  }
  const deviceNames: Record<number, string> = {};
  for (const d of devices) deviceNames[d.id] = d.name;

  const out: Array<Record<string, unknown> & { _sort: number }> = [];
  for (const rule of rules) {
    const matched = Boolean(rule.matched);
    const latched = Boolean(rule.latched);
    const triggeredAt = rule.last_triggered_at ?? rule.last_fired_at ?? null;
    const live = matched || latched;
    if (!live && !triggeredAt) continue;
    const cooldownLeft = Number(rule.cooldown_remaining_sec ?? 0);
    let status: string;
    if (live) {
      status = latched || matched ? 'Active' : 'Matching';
      if (cooldownLeft > 0 && !latched) status = 'Cooldown';
    } else {
      status = 'Last active';
    }
    out.push({
      id: Number(rule.id ?? 0),
      name: String(rule.name ?? ''),
      when: ruleConditionSummary(rule, commandNames, deviceNames),
      then: ruleActionSummary(rule, commandNames, sequenceNames),
      matched,
      latched,
      live,
      status,
      last_triggered_at: triggeredAt,
      last_result: rule.last_result ?? null,
      cooldown_remaining_sec: cooldownLeft,
      skipped: rule.skipped ?? null,
      _sort: triggeredAt ? Date.parse(String(triggeredAt)) || 0 : 0,
    });
  }
  out.sort((a, b) => {
    if (Boolean(a.live) !== Boolean(b.live)) return a.live ? -1 : 1;
    return (b._sort ?? 0) - (a._sort ?? 0);
  });
  return out.slice(0, 5).map(({ _sort: _ignored, ...rest }) => rest);
}

export function buildCron(time: string, days: number[]): string {
  const match = /^(\d{1,2}):(\d{2})$/.exec(String(time));
  const hour = Math.max(0, Math.min(23, Number(match?.[1] ?? 8)));
  const minute = Math.max(0, Math.min(59, Number(match?.[2] ?? 0)));
  const valid = [...new Set(days.filter((d) => d >= 0 && d <= 6))].sort((a, b) => a - b);
  const dow = valid.length === 0 || valid.length === 7 ? '*' : valid.join(',');
  return `${minute} ${hour} * * ${dow}`;
}

export function mergeWriteConfigFields(
  writeConfig: Record<string, unknown>,
  fields: {
    on_value?: unknown;
    off_value?: unknown;
    feedback_on?: string;
    feedback_off?: string;
    feedback_on_label?: string;
    feedback_off_label?: string;
  },
): Record<string, unknown> {
  const out = { ...writeConfig };
  const coerce = (raw: unknown): string | number | null => {
    const s = String(raw ?? '').trim();
    if (s === '') return null;
    if (!Number.isNaN(Number(s)) && s !== '') {
      return s.includes('.') ? Number(s) : Number.parseInt(s, 10);
    }
    return s;
  };
  const on = coerce(fields.on_value);
  const off = coerce(fields.off_value);
  if (on === null) delete out.on_value;
  else out.on_value = on;
  if (off === null) delete out.off_value;
  else out.off_value = off;

  const parseList = (raw: unknown) =>
    String(raw ?? '')
      .split(',')
      .map((p) => p.trim())
      .filter(Boolean);

  const fbOn = parseList(fields.feedback_on);
  const fbOff = parseList(fields.feedback_off);
  if (fbOn.length) out.feedback_on = fbOn;
  else delete out.feedback_on;
  if (fbOff.length) out.feedback_off = fbOff;
  else delete out.feedback_off;

  const onLabel = String(fields.feedback_on_label ?? '').trim();
  const offLabel = String(fields.feedback_off_label ?? '').trim();
  if (onLabel) out.feedback_on_label = onLabel;
  else delete out.feedback_on_label;
  if (offLabel) out.feedback_off_label = offLabel;
  else delete out.feedback_off_label;

  return out;
}

export function normalizeSequenceSteps(steps: unknown[]): Record<string, unknown>[] {
  const clean: Record<string, unknown>[] = [];
  for (const s of steps) {
    if (!s || typeof s !== 'object') continue;
    const row = s as Record<string, unknown>;
    const cid = Number(row.command_id ?? 0);
    if (cid <= 0) continue;
    let wait = String(row.wait ?? 'none').toLowerCase();
    if (!['on', 'off'].includes(wait)) wait = 'none';
    let unit = String(row.delay_unit ?? 's').toLowerCase();
    if (!['s', 'min', 'h'].includes(unit)) unit = 's';
    clean.push({
      command_id: cid,
      action: String(row.action ?? 'on').toLowerCase() === 'off' ? 'off' : 'on',
      wait,
      timeout_sec: Math.max(0, Number(row.timeout_sec ?? 0)),
      delay_sec: Math.max(0, Math.min(86400, Number(row.delay_sec ?? 0))),
      delay_unit: unit,
    });
  }
  return clean;
}

export function normalizeRuleConditions(raw: Record<string, unknown>): Record<string, unknown> {
  const match = String(raw.match ?? 'all').toLowerCase() === 'any' ? 'any' : 'all';
  const items: Record<string, unknown>[] = [];
  const list = Array.isArray(raw.items) ? raw.items : Array.isArray(raw) ? (raw as unknown[]) : [];
  for (const item of list) {
    if (!item || typeof item !== 'object') continue;
    const row = item as Record<string, unknown>;
    const type = String(row.type ?? '').toLowerCase();
    if (type === 'command_status') {
      const cid = Number(row.command_id ?? 0);
      if (cid <= 0) continue;
      items.push({
        type: 'command_status',
        command_id: cid,
        state: String(row.state ?? 'off').toLowerCase() === 'on' ? 'on' : 'off',
      });
    } else if (type === 'alarm') {
      const did = Number(row.device_id ?? 0);
      if (did <= 0) continue;
      const key = String(row.metric_key ?? '').trim();
      items.push({
        type: 'alarm',
        device_id: did,
        metric_key: key !== '' ? key : null,
      });
    } else if (type === 'metric') {
      const did = Number(row.device_id ?? 0);
      const key = String(row.key ?? row.metric_key ?? '').trim();
      let op = String(row.op ?? '>');
      if (!['>', '>=', '<', '<=', '==', '!='].includes(op)) op = '>';
      if (did <= 0 || !key) continue;
      items.push({
        type: 'metric',
        device_id: did,
        key,
        op,
        value: row.value ?? '',
      });
    }
  }
  return { match, items };
}

export function normalizeRuleAction(raw: Record<string, unknown>): Record<string, unknown> {
  const kind = String(raw.kind ?? raw.type ?? 'command').toLowerCase();
  if (kind === 'sequence') {
    return {
      kind: 'sequence',
      sequence_id: Number(raw.sequence_id ?? 0),
      commands: [],
    };
  }
  const commands: Record<string, unknown>[] = [];
  const list = Array.isArray(raw.commands) ? raw.commands : [];
  for (const c of list) {
    if (!c || typeof c !== 'object') continue;
    const row = c as Record<string, unknown>;
    const cid = Number(row.command_id ?? 0);
    if (cid <= 0) continue;
    commands.push({
      command_id: cid,
      action: String(row.action ?? 'on').toLowerCase() === 'off' ? 'off' : 'on',
    });
  }
  return { kind: 'command', sequence_id: null, commands };
}

export function asRecordArray(value: unknown): Record<string, unknown>[] {
  if (!Array.isArray(value)) return [];
  return value.filter(
    (item): item is Record<string, unknown> =>
      typeof item === 'object' && item !== null && !Array.isArray(item),
  );
}
