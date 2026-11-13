import type { Queryable } from '../db.js';

function iso(value: Date | string | null | undefined): string | null {
  if (value == null) return null;
  return value instanceof Date ? value.toISOString() : String(value);
}

function asBool(value: unknown): boolean {
  return value === true || value === 't' || value === '1' || value === 1;
}

function parseJsonObject(value: unknown): Record<string, unknown> {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return value as Record<string, unknown>;
  }
  if (typeof value === 'string' && value.trim()) {
    try {
      const parsed = JSON.parse(value) as unknown;
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return parsed as Record<string, unknown>;
      }
    } catch {
      return {};
    }
  }
  return {};
}

function parseJsonArray(value: unknown): unknown[] {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string' && value.trim()) {
    try {
      const parsed = JSON.parse(value) as unknown;
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
  return [];
}

export interface ChannelRow {
  id: number;
  name: string;
  type: string;
  isActive: boolean;
  description: string;
  devicesCount: number;
}

export interface ChannelDetail extends ChannelRow {
  messageTemplate: string;
  config: Record<string, unknown>;
  createdAt: string | null;
  updatedAt: string | null;
}

export interface NotificationSummary {
  totalChannels: number;
  activeChannels: number;
  totalDevices: number;
  todayAlarms: number;
  channels: ChannelDetail[];
}

export interface ChannelDeviceRow {
  deviceId: number;
  deviceName: string;
  description: string;
  location: string;
  metricKey: string;
  metricLabel: string;
  metricUnit: string;
  triggerValue: string;
  triggerOperator: string;
  clearValue: string;
  clearOperator: string;
  severity: string;
  isEnabled: boolean;
}

export interface ChannelMessageRow {
  id: number;
  status: string;
  sentAt: string | null;
  error: string;
  message: string;
  deviceName: string;
}

export interface ChannelInput {
  name: string;
  type: string;
  isActive: boolean;
  messageTemplate: string;
  description: string;
  config: Record<string, unknown>;
}

export const DEFAULT_HEALTH_TEMPLATE = '{name}\n{datetime}\nStatus: {status}\n\n{alarms}';

export interface HealthCheckRow {
  id: number;
  name: string;
  isActive: boolean;
  scheduleType: string;
  scheduleTime: string;
  scheduleWeekday: number;
  scheduleDay: number;
  channelIds: number[];
  rooms: string[];
  messageTemplate: string;
  timezone: string;
  nextRunAt: string | null;
  lastRunAt: string | null;
  nextRunLabel: string;
  lastRunLabel: string;
}

export interface HealthCheckInput {
  id?: number | null;
  name: string;
  isActive: boolean;
  scheduleType: string;
  scheduleTime: string;
  scheduleWeekday: number;
  scheduleDay: number;
  channelIds: number[];
  messageTemplate: string;
}

export interface HealthRenderResult {
  status: 'attention' | 'normal';
  message: string;
}

export interface ChannelDeliverResult {
  channelId: number;
  name: string;
  type: string;
  success: boolean;
  error: string;
}

const CHANNEL_DEVICE_COUNT_SQL = `
  (
    SELECT COUNT(*)::int
    FROM devices d
    WHERE d.is_deleted = false
      AND d.is_active = true
      AND jsonb_typeof(d.metrics) = 'array'
      AND EXISTS (
        SELECT 1
        FROM jsonb_array_elements(d.metrics) metric
        CROSS JOIN LATERAL jsonb_array_elements(COALESCE(metric->'triggers', '[]'::jsonb)) trigger
        CROSS JOIN LATERAL jsonb_array_elements(COALESCE(trigger->'channels', '[]'::jsonb)) channel
        WHERE channel #>> '{}' = c.id::text
           OR (jsonb_typeof(channel) = 'number' AND (channel)::text = c.id::text)
      )
  )
`;

function mapChannel(row: {
  id: number;
  name: string | null;
  type: string | null;
  is_active: boolean | string | null;
  description: string | null;
  message_template?: string | null;
  config?: unknown;
  created_at?: Date | string | null;
  updated_at?: Date | string | null;
  devices_count?: number;
}): ChannelDetail {
  return {
    id: row.id,
    name: row.name ?? '',
    type: row.type ?? '',
    isActive: asBool(row.is_active),
    description: row.description ?? '',
    messageTemplate: row.message_template ?? '',
    config: parseJsonObject(row.config),
    createdAt: iso(row.created_at ?? null),
    updatedAt: iso(row.updated_at ?? null),
    devicesCount: Number(row.devices_count ?? 0),
  };
}

export async function listChannels(db: Queryable): Promise<ChannelRow[]> {
  const result = await db.query(
    `SELECT c.id, c.name, c.type, c.is_active, c.description,
            ${CHANNEL_DEVICE_COUNT_SQL} AS devices_count
     FROM notification_channels c
     WHERE c.is_deleted = false
     ORDER BY c.id DESC`,
  );
  return (result.rows as Parameters<typeof mapChannel>[0][]).map((row) => {
    const full = mapChannel(row);
    return {
      id: full.id,
      name: full.name,
      type: full.type,
      isActive: full.isActive,
      description: full.description,
      devicesCount: full.devicesCount,
    };
  });
}

export async function listChannelsDetailed(db: Queryable): Promise<ChannelDetail[]> {
  const result = await db.query(
    `SELECT c.id, c.name, c.type, c.is_active, c.description, c.message_template,
            c.config, c.created_at, c.updated_at,
            ${CHANNEL_DEVICE_COUNT_SQL} AS devices_count
     FROM notification_channels c
     WHERE c.is_deleted = false
     ORDER BY c.id DESC`,
  );
  return (result.rows as Parameters<typeof mapChannel>[0][]).map(mapChannel);
}

export async function getNotificationSummary(db: Queryable): Promise<NotificationSummary> {
  const channels = await listChannelsDetailed(db);
  const devices = await db.query(
    `SELECT COUNT(*)::int AS total FROM devices WHERE is_deleted = false`,
  );
  const alarms = await db.query(
    `SELECT COUNT(*)::int AS total
     FROM alarm_logs
     WHERE created_at::date = (NOW() AT TIME ZONE 'Asia/Bangkok')::date`,
  );
  return {
    totalChannels: channels.length,
    activeChannels: channels.filter((c) => c.isActive).length,
    totalDevices: Number((devices.rows[0] as { total: number }).total),
    todayAlarms: Number((alarms.rows[0] as { total: number }).total),
    channels,
  };
}

export async function getChannelById(db: Queryable, id: number): Promise<ChannelDetail | null> {
  const result = await db.query(
    `SELECT c.id, c.name, c.type, c.is_active, c.description, c.message_template,
            c.config, c.created_at, c.updated_at,
            ${CHANNEL_DEVICE_COUNT_SQL} AS devices_count
     FROM notification_channels c
     WHERE c.id = $1 AND c.is_deleted = false`,
    [id],
  );
  const row = result.rows[0] as Parameters<typeof mapChannel>[0] | undefined;
  return row ? mapChannel(row) : null;
}

export async function createChannel(db: Queryable, input: ChannelInput): Promise<ChannelDetail> {
  const result = await db.query(
    `INSERT INTO notification_channels
       (name, type, is_active, message_template, description, config, created_at, updated_at, is_deleted)
     VALUES ($1, $2, $3, $4, $5, $6::jsonb, NOW() AT TIME ZONE 'UTC', NOW() AT TIME ZONE 'UTC', false)
     RETURNING id`,
    [
      input.name,
      input.type,
      input.isActive,
      input.messageTemplate || null,
      input.description || null,
      JSON.stringify(input.config ?? {}),
    ],
  );
  const id = Number((result.rows[0] as { id: number }).id);
  const created = await getChannelById(db, id);
  if (!created) {
    throw new Error('Failed to load created channel');
  }
  return created;
}

export async function updateChannel(
  db: Queryable,
  id: number,
  input: ChannelInput,
): Promise<ChannelDetail | null> {
  const result = await db.query(
    `UPDATE notification_channels
        SET name = $2,
            type = $3,
            is_active = $4,
            message_template = $5,
            description = $6,
            config = $7::jsonb,
            updated_at = NOW() AT TIME ZONE 'UTC'
      WHERE id = $1 AND is_deleted = false
      RETURNING id`,
    [
      id,
      input.name,
      input.type,
      input.isActive,
      input.messageTemplate || null,
      input.description || null,
      JSON.stringify(input.config ?? {}),
    ],
  );
  if (!result.rows[0]) return null;
  return getChannelById(db, id);
}

export async function softDeleteChannel(db: Queryable, id: number): Promise<boolean> {
  const result = await db.query(
    `UPDATE notification_channels
        SET is_deleted = true, updated_at = NOW() AT TIME ZONE 'UTC'
      WHERE id = $1 AND is_deleted = false
      RETURNING id`,
    [id],
  );
  return Boolean(result.rows[0]);
}

export function previewChannelTemplate(template: string): string {
  const raw = template?.trim() ? template : '{{metric}} = {{value}}';
  return raw
    .replaceAll('{DEVICE_NAME}', 'Sample Device')
    .replaceAll('{METRIC_NAME}', 'Temperature')
    .replaceAll('{VALUE}', '25.5')
    .replaceAll('{UNIT}', '°C')
    .replaceAll('{LOCATION}', 'Server Room')
    .replaceAll('{TIMESTAMP}', new Date().toISOString().slice(0, 19).replace('T', ' '))
    .replaceAll('{SEVERITY}', 'Warning')
    .replaceAll('{{metric}}', 'Temperature')
    .replaceAll('{{value}}', '25.5°C')
    .replaceAll('{{device}}', 'Device-001')
    .replaceAll('{{timestamp}}', new Date().toISOString().slice(0, 19).replace('T', ' '));
}

export async function listChannelDevices(
  db: Queryable,
  channelId: number,
): Promise<ChannelDeviceRow[]> {
  const result = await db.query(
    `SELECT id, name, location, description, metrics
     FROM devices
     WHERE is_deleted = false AND is_active = true`,
  );
  const rows: ChannelDeviceRow[] = [];
  for (const device of result.rows as {
    id: number;
    name: string | null;
    location: string | null;
    description: string | null;
    metrics: unknown;
  }[]) {
    const metrics = Array.isArray(device.metrics)
      ? device.metrics
      : parseJsonArray(device.metrics);
    for (const metric of metrics) {
      if (!metric || typeof metric !== 'object') continue;
      const m = metric as Record<string, unknown>;
      const triggers = Array.isArray(m.triggers) ? m.triggers : [];
      for (const trigger of triggers) {
        if (!trigger || typeof trigger !== 'object') continue;
        const t = trigger as Record<string, unknown>;
        const channels = Array.isArray(t.channels) ? t.channels : [];
        const matched = channels.some((ch) => Number(ch) === channelId || String(ch) === String(channelId));
        if (!matched) continue;
        const clear = (t.clear && typeof t.clear === 'object' ? t.clear : {}) as Record<string, unknown>;
        rows.push({
          deviceId: device.id,
          deviceName: device.name ?? '',
          description: device.description ?? '',
          location: device.location ?? '',
          metricKey: String(m.key ?? ''),
          metricLabel: String(m.label ?? m.key ?? ''),
          metricUnit: String(m.unit ?? ''),
          triggerValue: String(t.value ?? ''),
          triggerOperator: String(t.operator ?? ''),
          clearValue: String(clear.value ?? t.clear_value ?? ''),
          clearOperator: String(clear.operator ?? t.clear_operator ?? ''),
          severity: String(t.severity ?? ''),
          isEnabled: t.enable === true || t.enable === 'true' || t.enable === 1 || t.enable === '1',
        });
      }
    }
  }
  return rows;
}

export async function listChannelMessages(
  db: Queryable,
  channelId: number,
  limit = 50,
): Promise<ChannelMessageRow[]> {
  // Match PHP getMessageHistory: INNER JOIN alarm_logs + devices (empty when no match)
  const result = await db.query(
    `SELECT ald.id, ald.status, ald.sent_at, ald.error, al.message, d.name AS device_name
     FROM alarm_log_dispatch ald
     INNER JOIN alarm_logs al ON al.id = ald.alarm_log_id
     INNER JOIN devices d ON d.id = al.device_id
     WHERE ald.channel_id = $1
     ORDER BY ald.sent_at DESC NULLS LAST, ald.id DESC
     LIMIT $2`,
    [channelId, limit],
  );
  return (result.rows as {
    id: number;
    status: string | null;
    sent_at: Date | string | null;
    error: string | null;
    message: string | null;
    device_name: string | null;
  }[]).map((row) => ({
    id: row.id,
    status: row.status ?? '',
    sentAt: iso(row.sent_at),
    error: row.error ?? '',
    message: row.message ?? '',
    deviceName: row.device_name ?? '',
  }));
}

export function alarmEngineBaseUrl(): string {
  return (process.env.ALARM_ENGINE_URL ?? 'http://127.0.0.1:3004').replace(/\/$/, '');
}

export async function sendTestViaAlarmEngine(
  channelId: number,
  message: string,
): Promise<{ success: boolean; message?: string; error?: string; channel?: string; type?: string }> {
  try {
    const response = await fetch(`${alarmEngineBaseUrl()}/api/send-test`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        channelId,
        message: message || 'Test notification from JConnect System',
      }),
      signal: AbortSignal.timeout(20_000),
    });
    const payload = (await response.json().catch(() => null)) as {
      success?: boolean;
      message?: string;
      error?: string;
      channel?: string;
      type?: string;
    } | null;
    if (!payload) {
      return { success: false, error: 'Invalid response from alarm worker service' };
    }
    return {
      success: Boolean(payload.success),
      message: payload.message,
      error: payload.error,
      channel: payload.channel,
      type: payload.type,
    };
  } catch {
    // Match PHP sendTestMessage when alarm worker is unreachable
    return { success: false, error: 'Failed to connect to alarm worker service' };
  }
}

async function httpJson(
  url: string,
  headers: Record<string, string>,
  body: Record<string, unknown>,
): Promise<{ success: boolean; error?: string; response?: string }> {
  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: { Accept: 'application/json', ...headers },
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(20_000),
    });
    const text = await response.text().catch(() => '');
    if (!response.ok) {
      return { success: false, error: `HTTP ${response.status}` };
    }
    return { success: true, response: text };
  } catch (err) {
    return { success: false, error: err instanceof Error ? err.message : 'Request failed' };
  }
}

/**
 * Direct channel delivery mirroring PHP Health_checks_model::_send_channel.
 * Lets LINE/Telegram succeed without alarm-engine.
 */
export async function sendChannelDirect(
  type: string,
  config: Record<string, unknown>,
  message: string,
  name: string,
): Promise<{ success: boolean; error?: string }> {
  const channelType = String(type || '').toLowerCase();

  if (channelType === 'line' || channelType === 'line_oa') {
    const token = String(config.access_token ?? '');
    const to = String(config.to ?? config.user_id ?? '');
    if (!token || !to) {
      return { success: false, error: 'LINE token or recipient is missing' };
    }
    return httpJson(
      'https://api.line.me/v2/bot/message/push',
      {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      { to, messages: [{ type: 'text', text: message }] },
    );
  }

  if (channelType === 'telegram') {
    const token = String(config.bot_token ?? '');
    const chat = String(config.chat_id ?? '');
    if (!token || !chat) {
      return { success: false, error: 'Telegram token or chat id is missing' };
    }
    return httpJson(
      `https://api.telegram.org/bot${token}/sendMessage`,
      { 'Content-Type': 'application/json' },
      { chat_id: chat, text: message },
    );
  }

  if (channelType === 'webhook') {
    const url = String(config.url ?? '');
    if (!url) {
      return { success: false, error: 'Webhook URL is missing' };
    }
    const headers: Record<string, string> = { 'Content-Type': 'application/json' };
    const extraRaw = config.headers;
    let extra: unknown = extraRaw;
    if (typeof extraRaw === 'string' && extraRaw.trim()) {
      try {
        extra = JSON.parse(extraRaw);
      } catch {
        extra = null;
      }
    }
    if (extra && typeof extra === 'object' && !Array.isArray(extra)) {
      for (const [key, value] of Object.entries(extra as Record<string, unknown>)) {
        headers[key] = String(value);
      }
    }
    const payload = String(config.payload ?? '');
    let body: Record<string, unknown>;
    if (payload) {
      const replaced = payload.replaceAll('{{message}}', message).replaceAll('{message}', message);
      try {
        const decoded = JSON.parse(replaced) as unknown;
        body =
          decoded && typeof decoded === 'object' && !Array.isArray(decoded)
            ? (decoded as Record<string, unknown>)
            : { message };
      } catch {
        body = { message };
      }
    } else {
      body = { message };
    }
    return httpJson(url, headers, body);
  }

  if (channelType === 'email') {
    return sendEmailDirect(config, message, name);
  }

  return { success: false, error: 'Unsupported channel type' };
}

async function sendEmailDirect(
  config: Record<string, unknown>,
  message: string,
  name: string,
): Promise<{ success: boolean; error?: string }> {
  const nodemailer = (await import('nodemailer')).default;
  const rawTo = String(config.receivers ?? config.to ?? '');
  const receivers = rawTo
    .split(/[,\n]/)
    .map((s) => s.trim())
    .filter(Boolean);
  const host = String(config.host ?? config.smtp_host ?? '');
  if (!host || !receivers.length) {
    return { success: false, error: 'Email host or receivers are missing' };
  }
  const port = Number(config.port ?? config.smtp_port ?? 25) || 25;
  const secureRaw = config.secure ?? config.smtp_secure ?? '';
  const secure =
    secureRaw === true ||
    String(secureRaw).toLowerCase() === 'true' ||
    String(secureRaw).toLowerCase() === 'ssl';
  const user = String(config.username ?? config.smtp_user ?? config.SMTP_EMAIL ?? '');
  const pass = String(config.password ?? config.smtp_pass ?? config.SMTP_PASSWORD ?? '');
  const from = String(config.from ?? (user || 'healthcheck@localhost'));
  const fromName = String(config.sendername ?? 'JConnect');
  const subject =
    String(config.subject ?? '').trim() || `Health Check: ${name}`;

  try {
    if (process.env.MAIL_DRY_RUN === '1' || process.env.NODE_ENV === 'test') {
      return { success: true };
    }
    const transporter = nodemailer.createTransport({
      host,
      port,
      secure: secure && port === 465,
      auth: user && pass ? { user, pass } : undefined,
    });
    await transporter.sendMail({
      from: `"${fromName}" <${from}>`,
      to: receivers.join(', '),
      subject,
      text: message,
    });
    return { success: true };
  } catch (err) {
    return { success: false, error: err instanceof Error ? err.message : 'Email send failed' };
  }
}

function bangkokParts(date = new Date()): {
  year: number;
  month: number;
  day: number;
  hour: number;
  minute: number;
  second: number;
  weekday: number;
} {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Bangkok',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    weekday: 'short',
  }).formatToParts(date);
  const get = (type: Intl.DateTimeFormatPartTypes) =>
    parts.find((p) => p.type === type)?.value ?? '0';
  const weekdayMap: Record<string, number> = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
  };
  let hour = Number(get('hour'));
  if (hour === 24) hour = 0;
  return {
    year: Number(get('year')),
    month: Number(get('month')),
    day: Number(get('day')),
    hour,
    minute: Number(get('minute')),
    second: Number(get('second')),
    weekday: weekdayMap[get('weekday')] ?? 1,
  };
}

function bangkokLabel(value: Date | string | null | undefined): string {
  if (!value) return '-';
  try {
    const dt = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(dt.getTime())) return '-';
    return new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Asia/Bangkok',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    })
      .format(dt)
      .replace(',', '');
  } catch {
    return '-';
  }
}

function normalizeTime(value: unknown): string {
  const raw = String(value ?? '').trim();
  if (!/^\d{1,2}:\d{2}/.test(raw)) return '08:00';
  return raw.slice(0, 5);
}

function computeNextRunAt(row: {
  scheduleType: string;
  scheduleTime: string;
  scheduleWeekday: number;
  scheduleDay: number;
  timezone?: string;
}): string {
  const now = bangkokParts();
  const time = normalizeTime(row.scheduleTime);
  const hour = Number(time.slice(0, 2));
  const minute = Number(time.slice(3, 5));
  const type = row.scheduleType || 'daily';
  const day = Math.min(28, Math.max(1, Number(row.scheduleDay) || 1));

  const toUtcIso = (y: number, m: number, d: number, h: number, min: number): string => {
    // Asia/Bangkok is UTC+7 year-round
    const utc = Date.UTC(y, m - 1, d, h - 7, min, 0);
    return new Date(utc).toISOString();
  };

  const nowStamp = Date.UTC(now.year, now.month - 1, now.day, now.hour, now.minute, now.second);
  let nextY = now.year;
  let nextM = now.month;
  let nextD = now.day;
  const nextH = hour;
  const nextMin = minute;

  if (type === 'weekly') {
    let weekday = Number(row.scheduleWeekday) || 1;
    if (weekday < 1 || weekday > 7) weekday = 1;
    let diff = (weekday - now.weekday + 7) % 7;
    const candidate = Date.UTC(now.year, now.month - 1, now.day, hour, minute, 0);
    if (diff === 0 && candidate <= nowStamp) diff = 7;
    const base = new Date(Date.UTC(now.year, now.month - 1, now.day));
    base.setUTCDate(base.getUTCDate() + diff);
    nextY = base.getUTCFullYear();
    nextM = base.getUTCMonth() + 1;
    nextD = base.getUTCDate();
  } else if (type === 'monthly') {
    nextD = day;
    const candidate = Date.UTC(now.year, now.month - 1, day, hour, minute, 0);
    if (candidate <= nowStamp) {
      const nextMonth = new Date(Date.UTC(now.year, now.month, 1));
      nextY = nextMonth.getUTCFullYear();
      nextM = nextMonth.getUTCMonth() + 1;
      nextD = day;
    }
  } else {
    const candidate = Date.UTC(now.year, now.month - 1, now.day, hour, minute, 0);
    if (candidate <= nowStamp) {
      const tomorrow = new Date(Date.UTC(now.year, now.month - 1, now.day + 1));
      nextY = tomorrow.getUTCFullYear();
      nextM = tomorrow.getUTCMonth() + 1;
      nextD = tomorrow.getUTCDate();
    }
  }

  // Guard loop for edge cases
  let guard = 0;
  while (Date.UTC(nextY, nextM - 1, nextD, nextH, nextMin, 0) <= nowStamp && guard < 24) {
    if (type === 'weekly') {
      const base = new Date(Date.UTC(nextY, nextM - 1, nextD + 7));
      nextY = base.getUTCFullYear();
      nextM = base.getUTCMonth() + 1;
      nextD = base.getUTCDate();
    } else if (type === 'monthly') {
      const base = new Date(Date.UTC(nextY, nextM, 1));
      nextY = base.getUTCFullYear();
      nextM = base.getUTCMonth() + 1;
      nextD = day;
    } else {
      const base = new Date(Date.UTC(nextY, nextM - 1, nextD + 1));
      nextY = base.getUTCFullYear();
      nextM = base.getUTCMonth() + 1;
      nextD = base.getUTCDate();
    }
    guard += 1;
  }

  return toUtcIso(nextY, nextM, nextD, nextH, nextMin);
}

function hydrateHealth(row: {
  id: number;
  name: string | null;
  is_active: boolean | string | null;
  schedule_type: string | null;
  schedule_config: unknown;
  timezone: string | null;
  channel_ids: unknown;
  rooms: unknown;
  message_template: string | null;
  last_run_at: Date | string | null;
  next_run_at: Date | string | null;
}): HealthCheckRow {
  const config = parseJsonObject(row.schedule_config);
  const channelIds = parseJsonArray(row.channel_ids).map((v) => Number(v)).filter((n) => Number.isFinite(n));
  const rooms = parseJsonArray(row.rooms).map((v) => String(v)).filter(Boolean);
  return {
    id: row.id,
    name: row.name ?? '',
    isActive: asBool(row.is_active),
    scheduleType: row.schedule_type ?? 'daily',
    scheduleTime: normalizeTime(config.time ?? '08:00'),
    scheduleWeekday: Number(config.weekday ?? 1) || 1,
    scheduleDay: Number(config.day ?? 1) || 1,
    channelIds,
    rooms,
    messageTemplate: row.message_template ?? DEFAULT_HEALTH_TEMPLATE,
    timezone: row.timezone ?? 'Asia/Bangkok',
    nextRunAt: iso(row.next_run_at),
    lastRunAt: iso(row.last_run_at),
    nextRunLabel: bangkokLabel(row.next_run_at),
    lastRunLabel: bangkokLabel(row.last_run_at),
  };
}

export async function listHealthChecks(db: Queryable): Promise<HealthCheckRow[]> {
  const result = await db.query(
    `SELECT * FROM health_checks ORDER BY next_run_at ASC NULLS LAST, id ASC`,
  );
  return (result.rows as Parameters<typeof hydrateHealth>[0][]).map(hydrateHealth);
}

export async function getHealthCheck(db: Queryable, id: number): Promise<HealthCheckRow | null> {
  const result = await db.query(`SELECT * FROM health_checks WHERE id = $1`, [id]);
  const row = result.rows[0] as Parameters<typeof hydrateHealth>[0] | undefined;
  return row ? hydrateHealth(row) : null;
}

export async function saveHealthCheck(db: Queryable, input: HealthCheckInput): Promise<number> {
  const name = input.name.trim();
  if (!name) return 0;
  const template = input.messageTemplate.trim() || DEFAULT_HEALTH_TEMPLATE;
  const scheduleType = ['daily', 'weekly', 'monthly'].includes(input.scheduleType)
    ? input.scheduleType
    : 'daily';
  const config = {
    time: normalizeTime(input.scheduleTime),
    weekday: Number(input.scheduleWeekday) || 1,
    day: Math.min(28, Math.max(1, Number(input.scheduleDay) || 1)),
  };
  const channels = [...new Set(input.channelIds.map((n) => Number(n)).filter((n) => Number.isFinite(n) && n > 0))];
  const id = Number(input.id ?? 0);

  if (id > 0) {
    await db.query(
      `UPDATE health_checks
          SET name = $2,
              is_active = $3,
              schedule_type = $4,
              schedule_config = $5::jsonb,
              timezone = 'Asia/Bangkok',
              channel_ids = $6::jsonb,
              message_template = $7,
              updated_at = NOW()
        WHERE id = $1`,
      [id, name, input.isActive, scheduleType, JSON.stringify(config), JSON.stringify(channels), template],
    );
  } else {
    const inserted = await db.query(
      `INSERT INTO health_checks
         (name, is_active, schedule_type, schedule_config, timezone, channel_ids, rooms, message_template)
       VALUES ($1, $2, $3, $4::jsonb, 'Asia/Bangkok', $5::jsonb, '[]'::jsonb, $6)
       RETURNING id`,
      [name, input.isActive, scheduleType, JSON.stringify(config), JSON.stringify(channels), template],
    );
    const newId = Number((inserted.rows[0] as { id: number }).id);
    const next = computeNextRunAt({
      scheduleType,
      scheduleTime: config.time,
      scheduleWeekday: config.weekday,
      scheduleDay: config.day,
    });
    await db.query(`UPDATE health_checks SET next_run_at = $2 WHERE id = $1`, [newId, next]);
    return newId;
  }

  const next = computeNextRunAt({
    scheduleType,
    scheduleTime: config.time,
    scheduleWeekday: config.weekday,
    scheduleDay: config.day,
  });
  await db.query(`UPDATE health_checks SET next_run_at = $2, updated_at = NOW() WHERE id = $1`, [id, next]);
  return id;
}

export async function deleteHealthCheck(db: Queryable, id: number): Promise<boolean> {
  const result = await db.query(`DELETE FROM health_checks WHERE id = $1 RETURNING id`, [id]);
  return Boolean(result.rows[0]);
}

async function activeAlarmCount(db: Queryable): Promise<number> {
  const result = await db.query(
    `SELECT COUNT(*)::int AS total FROM alarm_logs WHERE status = 'active'`,
  );
  return Number((result.rows[0] as { total: number }).total);
}

async function openAlarms(
  db: Queryable,
): Promise<Array<{ room: string; device: string; message: string }>> {
  const result = await db.query(
    `SELECT al.message, al.metric_key, d.name AS device_name, d.location
     FROM alarm_logs al
     LEFT JOIN devices d ON d.id = al.device_id
     WHERE al.status = 'active'
     ORDER BY al.created_at DESC
     LIMIT 20`,
  );
  return (result.rows as {
    message: string | null;
    metric_key: string | null;
    device_name: string | null;
    location: string | null;
  }[]).map((row) => {
    const message = (row.message ?? '').trim() || (row.metric_key ?? 'Alarm').trim();
    return {
      room: row.location ?? '',
      device: row.device_name ?? '',
      message,
    };
  });
}

async function replaceMetricPlaceholders(db: Queryable, template: string): Promise<string> {
  const matches = [...template.matchAll(/\{(\d+)\.([^}]+)\}/g)];
  if (!matches.length) return template;
  const pairs = new Map<string, [number, string]>();
  for (const match of matches) {
    pairs.set(`${match[1]}\0${match[2].toLowerCase()}`, [Number(match[1]), match[2]]);
  }
  const conditions: string[] = [];
  const params: unknown[] = [];
  for (const [, pair] of pairs) {
    conditions.push(`(dd.device_id = $${params.length + 1} AND lower(dd.key) = lower($${params.length + 2}))`);
    params.push(pair[0], pair[1]);
  }
  const result = await db.query(
    `SELECT DISTINCT ON (dd.device_id, lower(dd.key))
        dd.device_id, dd.key, dd.value, d.metrics
     FROM device_data dd
     JOIN devices d ON d.id = dd.device_id
     WHERE ${conditions.join(' OR ')}
     ORDER BY dd.device_id, lower(dd.key), dd.timestamp DESC`,
    params,
  );
  const found = new Map<string, string>();
  for (const row of result.rows as {
    device_id: number;
    key: string;
    value: string | null;
    metrics: unknown;
  }[]) {
    let unit = '';
    const metrics = Array.isArray(row.metrics) ? row.metrics : parseJsonArray(row.metrics);
    for (const metric of metrics) {
      if (metric && typeof metric === 'object') {
        const m = metric as { key?: unknown; unit?: unknown };
        if (String(m.key ?? '').toLowerCase() === String(row.key).toLowerCase()) {
          unit = String(m.unit ?? '').trim();
          break;
        }
      }
    }
    let value = String(row.value ?? '').trim();
    if (value !== '' && !Number.isNaN(Number(value))) {
      value = Number(Number(value).toFixed(2)).toString();
    }
    found.set(`${row.device_id}\0${row.key.toLowerCase()}`, unit ? `${value} ${unit}` : value);
  }
  return template.replace(/\{(\d+)\.([^}]+)\}/g, (_full, deviceId: string, key: string) => {
    return found.get(`${deviceId}\0${key.toLowerCase()}`) ?? '-';
  });
}

export async function renderHealthCheck(
  db: Queryable,
  row: Pick<HealthCheckRow, 'name' | 'messageTemplate' | 'timezone'>,
): Promise<HealthRenderResult> {
  const alarmCount = await activeAlarmCount(db);
  const alarms = alarmCount ? await openAlarms(db) : [];
  const status = alarmCount ? 'Attention' : 'System Normal';
  let alarmText = '';
  if (alarms.length) {
    const lines = ['Open alarms'];
    for (const alarm of alarms.slice(0, 20)) {
      const where = `${alarm.room ? `${alarm.room} / ` : ''}${alarm.device}`.trim();
      lines.push(`- ${where ? `${where}: ` : ''}${alarm.message}`);
    }
    alarmText = lines.join('\n');
  }
  const now = bangkokParts();
  const datetime = `${String(now.day).padStart(2, '0')}/${String(now.month).padStart(2, '0')}/${now.year} ${String(now.hour).padStart(2, '0')}:${String(now.minute).padStart(2, '0')}`;
  let template = row.messageTemplate?.trim() || DEFAULT_HEALTH_TEMPLATE;
  template = await replaceMetricPlaceholders(db, template);
  const message = template
    .replaceAll('{name}', row.name)
    .replaceAll('{datetime}', datetime)
    .replaceAll('{status}', status)
    .replaceAll('{alarms_count}', String(alarmCount))
    .replaceAll('{alarms}', alarmText);
  return {
    status: alarms.length ? 'attention' : 'normal',
    message,
  };
}

export async function deliverHealthCheck(
  db: Queryable,
  row: HealthCheckRow,
  rendered: HealthRenderResult,
): Promise<ChannelDeliverResult[]> {
  if (!row.channelIds.length) {
    return [{ channelId: 0, name: '', type: '', success: false, error: 'No channel selected' }];
  }
  const result = await db.query(
    `SELECT id, name, type, is_active, config
     FROM notification_channels
     WHERE id = ANY($1::int[]) AND is_deleted = false`,
    [row.channelIds],
  );
  const byId = new Map<
    number,
    {
      id: number;
      name: string;
      type: string;
      is_active: boolean | string | null;
      config: Record<string, unknown>;
    }
  >();
  for (const channel of result.rows as {
    id: number;
    name: string | null;
    type: string | null;
    is_active: boolean | string | null;
    config: unknown;
  }[]) {
    byId.set(channel.id, {
      id: channel.id,
      name: channel.name ?? '',
      type: channel.type ?? '',
      is_active: channel.is_active,
      config: parseJsonObject(channel.config),
    });
  }
  const results: ChannelDeliverResult[] = [];
  for (const id of row.channelIds) {
    const channel = byId.get(id);
    if (!channel || !asBool(channel.is_active)) {
      results.push({
        channelId: id,
        name: channel?.name ?? '',
        type: channel?.type ?? '',
        success: false,
        error: 'Channel is inactive',
      });
      continue;
    }
    try {
      // PHP health send uses _send_channel directly (not alarm-engine)
      const sent = await sendChannelDirect(
        channel.type,
        channel.config,
        rendered.message,
        row.name,
      );
      results.push({
        channelId: id,
        name: channel.name,
        type: channel.type,
        success: Boolean(sent.success),
        error: sent.error ?? '',
      });
    } catch (err) {
      results.push({
        channelId: id,
        name: channel.name,
        type: channel.type,
        success: false,
        error: err instanceof Error ? err.message : 'Send failed',
      });
    }
  }
  return results;
}

export async function writeHealthCheckLog(
  db: Queryable,
  id: number,
  rendered: HealthRenderResult,
  results: ChannelDeliverResult[],
): Promise<boolean> {
  const ok = results.every((r) => r.success);
  await db.query(
    `INSERT INTO health_check_logs (health_check_id, status, message, channel_results)
     VALUES ($1, $2, $3, $4::jsonb)`,
    [id, ok ? rendered.status : 'failed', rendered.message, JSON.stringify(results)],
  );
  return ok;
}
