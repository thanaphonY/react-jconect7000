import type { Queryable } from '../db.js';

function asIso(value: Date | string | null): string | null {
  if (!value) {
    return null;
  }
  if (value instanceof Date) {
    return value.toISOString();
  }
  return String(value);
}

function metricLabel(metrics: unknown, key: string): string {
  let list: unknown = metrics;
  if (typeof metrics === 'string') {
    try {
      list = JSON.parse(metrics);
    } catch {
      return key;
    }
  }
  if (!Array.isArray(list)) {
    return key;
  }
  for (const entry of list) {
    if (!entry || typeof entry !== 'object') {
      continue;
    }
    const record = entry as { key?: unknown; label?: unknown };
    if (record.key === key) {
      return typeof record.label === 'string' && record.label ? record.label : key;
    }
  }
  return key;
}

function isAcknowledged(value: boolean | string | null): boolean {
  return value === true || value === 't' || value === 'true' || value === '1';
}

export interface ActiveAlarm {
  deviceId: number | null;
  deviceName: string;
  metricKey: string;
  metricLabel: string;
  message: string;
  severity: string;
  lastTriggered: string | null;
  acknowledged: boolean;
  acknowledgedBy: string | null;
  acknowledgedAt: string | null;
}

interface AlarmStateRow {
  device_id: number | null;
  device_name: string | null;
  metric_key: string | null;
  message: string | null;
  severity: string | null;
  last_triggered: Date | string | null;
  acknowledged: boolean | string | null;
  acknowledged_by: string | null;
  acknowledged_at: Date | string | null;
  metrics: unknown;
}

export async function listActiveAlarms(db: Queryable): Promise<ActiveAlarm[]> {
  const result = await db.query(
    `SELECT a.device_id, a.metric_key, a.message, a.severity, a.last_triggered,
            a.acknowledged, a.acknowledged_by, a.acknowledged_at,
            d.name AS device_name, d.metrics
     FROM alarm_state a
     LEFT JOIN devices d ON a.device_id = d.id
     WHERE a.active = true AND d.is_active = true AND d.is_deleted = false
     ORDER BY a.last_triggered DESC`,
  );
  return (result.rows as AlarmStateRow[]).map((row) => {
    const metricKey = row.metric_key ?? '';
    return {
      deviceId: row.device_id,
      deviceName: row.device_name ?? '',
      metricKey,
      metricLabel: metricLabel(row.metrics, metricKey),
      message: row.message ?? '',
      severity: row.severity ?? '',
      lastTriggered: asIso(row.last_triggered),
      acknowledged: isAcknowledged(row.acknowledged),
      acknowledgedBy: row.acknowledged_by,
      acknowledgedAt: asIso(row.acknowledged_at),
    };
  });
}

export async function acknowledgeAlarmState(
  db: Queryable,
  deviceId: number,
  metricKey: string,
  user: string,
): Promise<boolean> {
  const result = await db.query(
    `UPDATE alarm_state
     SET acknowledged = true,
         acknowledged_by = $3,
         acknowledged_at = NOW()
     WHERE device_id = $1 AND metric_key = $2
     RETURNING device_id`,
    [deviceId, metricKey, user],
  );
  return result.rows.length > 0;
}

export type AlarmDateRange =
  | 'all'
  | 'today'
  | 'yesterday'
  | 'last_7_days'
  | 'last_30_days'
  | 'custom';

export interface AlarmLogFilters {
  range?: AlarmDateRange | string | null;
  startDate?: string | null;
  endDate?: string | null;
  deviceFilter?: string | number | null;
  severityFilter?: string | null;
  statusFilter?: string | null;
  search?: string | null;
}

export interface AlarmLogRow {
  id: number;
  createdAt: string | null;
  updatedAt: string | null;
  deviceId: number | null;
  deviceName: string;
  metricKey: string;
  severity: string;
  message: string;
  status: string;
  value: string | null;
  note: string | null;
  snapshotPath: string | null;
  snapshotCameraDeviceId: number | null;
}

export interface AlarmLogDetail extends AlarmLogRow {
  deviceInformation: string | null;
}

export interface RelatedAlarm {
  id: number;
  createdAt: string | null;
  severity: string;
  status: string;
  message: string;
}

export interface DispatchHistoryRow {
  id: number;
  channelId: number | null;
  channelName: string;
  status: string;
  error: string | null;
  createdAt: string | null;
  sentAt: string | null;
}

function pushDateFilter(
  where: string[],
  params: unknown[],
  filters: AlarmLogFilters,
): void {
  const range = filters.range && filters.range !== 'all' ? filters.range : null;
  if (!range) {
    return;
  }
  switch (range) {
    case 'today':
      where.push(`DATE(a.created_at AT TIME ZONE 'Asia/Bangkok') = (NOW() AT TIME ZONE 'Asia/Bangkok')::date`);
      break;
    case 'yesterday':
      where.push(
        `DATE(a.created_at AT TIME ZONE 'Asia/Bangkok') = ((NOW() AT TIME ZONE 'Asia/Bangkok')::date - 1)`,
      );
      break;
    case 'last_7_days':
      where.push(
        `DATE(a.created_at AT TIME ZONE 'Asia/Bangkok') >= ((NOW() AT TIME ZONE 'Asia/Bangkok')::date - 7)`,
      );
      break;
    case 'last_30_days':
      where.push(
        `DATE(a.created_at AT TIME ZONE 'Asia/Bangkok') >= ((NOW() AT TIME ZONE 'Asia/Bangkok')::date - 30)`,
      );
      break;
    case 'custom':
      if (filters.startDate && filters.endDate) {
        params.push(filters.startDate, filters.endDate);
        where.push(
          `DATE(a.created_at AT TIME ZONE 'Asia/Bangkok') >= $${params.length - 1}::date`,
        );
        where.push(`DATE(a.created_at AT TIME ZONE 'Asia/Bangkok') <= $${params.length}::date`);
      }
      break;
    default:
      break;
  }
}

function buildAlarmWhere(filters: AlarmLogFilters): { whereSql: string; params: unknown[] } {
  const where: string[] = [];
  const params: unknown[] = [];
  pushDateFilter(where, params, filters);

  if (filters.deviceFilter && String(filters.deviceFilter) !== 'all') {
    params.push(Number(filters.deviceFilter));
    where.push(`a.device_id = $${params.length}`);
  }
  if (filters.severityFilter && filters.severityFilter !== 'all') {
    params.push(filters.severityFilter);
    where.push(`a.severity = $${params.length}`);
  }
  if (filters.statusFilter && filters.statusFilter !== 'all') {
    params.push(filters.statusFilter);
    where.push(`a.status = $${params.length}`);
  }
  if (filters.search && filters.search.trim()) {
    const term = `%${filters.search.trim()}%`;
    params.push(term, term, term, term);
    const i = params.length;
    where.push(
      `(d.name ILIKE $${i - 3} OR a.metric_key ILIKE $${i - 2} OR a.message ILIKE $${i - 1} OR a.severity ILIKE $${i})`,
    );
  }

  return {
    whereSql: where.length ? `WHERE ${where.join(' AND ')}` : '',
    params,
  };
}

type DbLogRow = {
  id: number;
  created_at: Date | string | null;
  updated_at?: Date | string | null;
  device_id: number | null;
  device_name: string | null;
  metric_key: string | null;
  severity: string | null;
  message: string | null;
  status: string | null;
  value?: string | null;
  note?: string | null;
  snapshot_path?: string | null;
  snapshot_camera_device_id?: number | null;
};

function mapLogRow(row: DbLogRow): AlarmLogRow {
  return {
    id: row.id,
    createdAt: asIso(row.created_at),
    updatedAt: asIso(row.updated_at ?? null),
    deviceId: row.device_id,
    deviceName: row.device_name ?? '',
    metricKey: row.metric_key ?? '',
    severity: row.severity ?? '',
    message: row.message ?? '',
    status: row.status ?? '',
    value: row.value ?? null,
    note: row.note ?? null,
    snapshotPath: row.snapshot_path ?? null,
    snapshotCameraDeviceId: row.snapshot_camera_device_id ?? null,
  };
}

export async function listAlarmLogsFiltered(
  db: Queryable,
  page: number,
  pageSize: number,
  filters: AlarmLogFilters,
): Promise<{ logs: AlarmLogRow[]; total: number }> {
  const { whereSql, params } = buildAlarmWhere(filters);
  const count = await db.query(
    `SELECT COUNT(*)::int AS total
     FROM alarm_logs a
     LEFT JOIN devices d ON d.id = a.device_id
     ${whereSql}`,
    params,
  );
  const total = Number((count.rows[0] as { total: number }).total);
  const limitIdx = params.length + 1;
  const offsetIdx = params.length + 2;
  const result = await db.query(
    `SELECT a.id, a.created_at, a.updated_at, a.device_id, a.metric_key, a.severity, a.message,
            a.status, a.value, a.note, a.snapshot_path, a.snapshot_camera_device_id,
            d.name AS device_name
     FROM alarm_logs a
     LEFT JOIN devices d ON d.id = a.device_id
     ${whereSql}
     ORDER BY a.created_at DESC NULLS LAST, a.id DESC
     LIMIT $${limitIdx} OFFSET $${offsetIdx}`,
    [...params, pageSize, (page - 1) * pageSize],
  );
  return {
    logs: (result.rows as DbLogRow[]).map(mapLogRow),
    total,
  };
}

export async function listAlarmLogsForExport(
  db: Queryable,
  filters: AlarmLogFilters,
): Promise<AlarmLogRow[]> {
  const { whereSql, params } = buildAlarmWhere(filters);
  const result = await db.query(
    `SELECT a.id, a.created_at, a.updated_at, a.device_id, a.metric_key, a.severity, a.message,
            a.status, a.value, a.note, a.snapshot_path, a.snapshot_camera_device_id,
            d.name AS device_name
     FROM alarm_logs a
     LEFT JOIN devices d ON d.id = a.device_id
     ${whereSql}
     ORDER BY a.created_at DESC NULLS LAST, a.id DESC`,
    params,
  );
  return (result.rows as DbLogRow[]).map(mapLogRow);
}

export async function getAlarmLogStatistics(db: Queryable): Promise<{
  totalAlarms: number;
  activeAlarms: number;
  severityBreakdown: Array<{ severity: string; count: number }>;
  deviceBreakdown: Array<{ deviceName: string; count: number }>;
}> {
  const total = await db.query(`SELECT COUNT(*)::int AS total FROM alarm_logs`);
  const active = await db.query(
    `SELECT COUNT(*)::int AS total FROM alarm_logs WHERE status = 'active'`,
  );
  const severity = await db.query(
    `SELECT severity, COUNT(*)::int AS count
     FROM alarm_logs
     WHERE severity IS NOT NULL
     GROUP BY severity
     ORDER BY count DESC`,
  );
  const devices = await db.query(
    `SELECT d.name AS device_name, COUNT(*)::int AS count
     FROM alarm_logs a
     LEFT JOIN devices d ON d.id = a.device_id
     GROUP BY d.id, d.name
     ORDER BY count DESC
     LIMIT 5`,
  );
  return {
    totalAlarms: Number((total.rows[0] as { total: number }).total),
    activeAlarms: Number((active.rows[0] as { total: number }).total),
    severityBreakdown: (severity.rows as { severity: string | null; count: number }[]).map((row) => ({
      severity: row.severity ?? '',
      count: Number(row.count),
    })),
    deviceBreakdown: (devices.rows as { device_name: string | null; count: number }[]).map((row) => ({
      deviceName: row.device_name ?? '',
      count: Number(row.count),
    })),
  };
}

export async function getAlarmLogFilterOptions(db: Queryable): Promise<{
  devices: Array<{ id: number; name: string }>;
  severities: string[];
  statuses: string[];
}> {
  const devices = await db.query(
    `SELECT d.id, d.name
     FROM alarm_logs a
     JOIN devices d ON d.id = a.device_id
     WHERE d.name IS NOT NULL
     GROUP BY d.id, d.name
     ORDER BY d.name ASC`,
  );
  const severities = await db.query(
    `SELECT DISTINCT severity FROM alarm_logs WHERE severity IS NOT NULL ORDER BY severity ASC`,
  );
  const statuses = await db.query(
    `SELECT DISTINCT status FROM alarm_logs WHERE status IS NOT NULL ORDER BY status ASC`,
  );
  return {
    devices: (devices.rows as { id: number; name: string }[]).map((row) => ({
      id: row.id,
      name: row.name,
    })),
    severities: (severities.rows as { severity: string }[]).map((row) => row.severity),
    statuses: (statuses.rows as { status: string }[]).map((row) => row.status),
  };
}

export async function getAlarmLogDetail(
  db: Queryable,
  alarmId: number,
): Promise<{
  alarm: AlarmLogDetail;
  relatedAlarms: RelatedAlarm[];
  dispatchHistory: DispatchHistoryRow[];
} | null> {
  const result = await db.query(
    `SELECT a.id, a.created_at, a.updated_at, a.device_id, a.metric_key, a.severity, a.message,
            a.status, a.value, a.note, a.snapshot_path, a.snapshot_camera_device_id,
            d.name AS device_name, d.device_information
     FROM alarm_logs a
     LEFT JOIN devices d ON d.id = a.device_id
     WHERE a.id = $1`,
    [alarmId],
  );
  const row = result.rows[0] as (DbLogRow & { device_information?: string | null }) | undefined;
  if (!row) {
    return null;
  }
  const alarm: AlarmLogDetail = {
    ...mapLogRow(row),
    deviceInformation: row.device_information ?? null,
  };

  const related = await db.query(
    `SELECT id, created_at, severity, status, message
     FROM alarm_logs
     WHERE device_id = $1 AND metric_key = $2 AND id != $3
     ORDER BY created_at DESC
     LIMIT 5`,
    [alarm.deviceId, alarm.metricKey, alarmId],
  );
  const dispatch = await db.query(
    `SELECT d.id, d.channel_id, d.status, d.error, d.created_at, d.sent_at,
            c.name AS channel_name
     FROM alarm_log_dispatch d
     LEFT JOIN notification_channels c ON c.id = d.channel_id
     WHERE d.alarm_log_id = $1
     ORDER BY d.created_at DESC`,
    [alarmId],
  );

  return {
    alarm,
    relatedAlarms: (related.rows as {
      id: number;
      created_at: Date | string | null;
      severity: string | null;
      status: string | null;
      message: string | null;
    }[]).map((r) => ({
      id: r.id,
      createdAt: asIso(r.created_at),
      severity: r.severity ?? '',
      status: r.status ?? '',
      message: r.message ?? '',
    })),
    dispatchHistory: (dispatch.rows as {
      id: number;
      channel_id: number | null;
      channel_name: string | null;
      status: string | null;
      error: string | null;
      created_at: Date | string | null;
      sent_at: Date | string | null;
    }[]).map((r) => ({
      id: r.id,
      channelId: r.channel_id,
      channelName: r.channel_name ?? '',
      status: r.status ?? '',
      error: r.error,
      createdAt: asIso(r.created_at),
      sentAt: asIso(r.sent_at),
    })),
  };
}

export async function insertEventLog(
  db: Queryable,
  input: {
    userId: number | null;
    actionType: string;
    entityType: string;
    entityId: number;
    message: string;
    metadata?: Record<string, unknown>;
  },
): Promise<void> {
  await db.query(
    `INSERT INTO event_logs (user_id, action_type, entity_type, entity_id, message, metadata, created_at)
     VALUES ($1, $2, $3, $4, $5, $6::jsonb, NOW() AT TIME ZONE 'UTC')`,
    [
      input.userId,
      input.actionType,
      input.entityType,
      input.entityId,
      input.message,
      JSON.stringify(input.metadata ?? {}),
    ],
  );
}

export async function acknowledgeAlarmLog(
  db: Queryable,
  alarmId: number,
  userId: number | null,
  note?: string | null,
): Promise<boolean> {
  // PHP: status + updated_at only; note goes to event_logs metadata, not alarm_logs.note
  const result = await db.query(
    `UPDATE alarm_logs
     SET status = 'acknowledged',
         updated_at = NOW()
     WHERE id = $1
     RETURNING id`,
    [alarmId],
  );
  const ok = result.rows.length > 0;
  if (ok) {
    await insertEventLog(db, {
      userId,
      actionType: 'acknowledge',
      entityType: 'alarm_logs',
      entityId: alarmId,
      message: 'Alarm acknowledged by user',
      metadata: {
        alarm_id: alarmId,
        note: note ?? null,
        previous_status: 'active',
        new_status: 'acknowledged',
      },
    });
  }
  return ok;
}

export async function resolveAlarmLog(
  db: Queryable,
  alarmId: number,
  userId: number | null,
  resolutionNote: string,
): Promise<boolean> {
  // PHP: status + updated_at only; resolution_note goes to event_logs metadata
  const result = await db.query(
    `UPDATE alarm_logs
     SET status = 'resolved',
         updated_at = NOW()
     WHERE id = $1
     RETURNING id`,
    [alarmId],
  );
  const ok = result.rows.length > 0;
  if (ok) {
    await insertEventLog(db, {
      userId,
      actionType: 'resolve',
      entityType: 'alarm_logs',
      entityId: alarmId,
      message: 'Alarm resolved by user',
      metadata: {
        alarm_id: alarmId,
        resolution_note: resolutionNote,
        previous_status: 'acknowledged',
        new_status: 'resolved',
      },
    });
  }
  return ok;
}

export async function bulkAcknowledgeAlarmLogs(
  db: Queryable,
  alarmIds: number[],
  userId: number | null,
  note?: string | null,
): Promise<{ successCount: number; failedCount: number }> {
  let successCount = 0;
  let failedCount = 0;
  for (const alarmId of alarmIds) {
    const result = await db.query(
      `UPDATE alarm_logs
       SET status = 'acknowledged',
           updated_at = NOW()
       WHERE id = $1 AND status = 'active'
       RETURNING id`,
      [alarmId],
    );
    if (result.rows.length > 0) {
      successCount += 1;
      await insertEventLog(db, {
        userId,
        actionType: 'acknowledge',
        entityType: 'alarm_logs',
        entityId: alarmId,
        message: 'Alarm acknowledged in bulk operation',
        metadata: { alarm_id: alarmId, note: note ?? null, bulk_operation: true },
      });
    } else {
      failedCount += 1;
    }
  }
  return { successCount, failedCount };
}

export async function bulkResolveAlarmLogs(
  db: Queryable,
  alarmIds: number[],
  userId: number | null,
  resolutionNote: string,
): Promise<{ successCount: number; failedCount: number }> {
  let successCount = 0;
  let failedCount = 0;
  for (const alarmId of alarmIds) {
    const result = await db.query(
      `UPDATE alarm_logs
       SET status = 'resolved',
           updated_at = NOW()
       WHERE id = $1 AND status IN ('active', 'acknowledged')
       RETURNING id`,
      [alarmId],
    );
    if (result.rows.length > 0) {
      successCount += 1;
      await insertEventLog(db, {
        userId,
        actionType: 'resolve',
        entityType: 'alarm_logs',
        entityId: alarmId,
        message: 'Alarm resolved in bulk operation',
        metadata: {
          alarm_id: alarmId,
          resolution_note: resolutionNote,
          bulk_operation: true,
        },
      });
    } else {
      failedCount += 1;
    }
  }
  return { successCount, failedCount };
}

/** Format datetime like PHP export: d/m/Y H:i:s in Asia/Bangkok. */
export function formatExportDateTime(iso: string | null): string {
  if (!iso) {
    return '';
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Bangkok',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).formatToParts(date);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';
  return `${get('day')}/${get('month')}/${get('year')} ${get('hour')}:${get('minute')}:${get('second')}`;
}

function csvEscape(value: string): string {
  if (/[",\n\r]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`;
  }
  return value;
}

export function buildAlarmLogsCsv(logs: AlarmLogRow[]): string {
  const header = ['Date Time', 'Device', 'Metric', 'Severity', 'Message'];
  const lines = [
    header.join(','),
    ...logs.map((log) =>
      [
        csvEscape(formatExportDateTime(log.createdAt)),
        csvEscape(log.deviceName),
        csvEscape(log.metricKey),
        csvEscape(log.severity),
        csvEscape(log.message),
      ].join(','),
    ),
  ];
  return `\uFEFF${lines.join('\r\n')}`;
}

/** Real OOXML .xlsx (matches PHP PhpSpreadsheet Xlsx writer). */
export async function buildAlarmLogsExcelXlsx(logs: AlarmLogRow[]): Promise<Buffer> {
  const ExcelJS = (await import('exceljs')).default;
  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet('Alarm Logs');
  sheet.addRow(['Date Time', 'Device', 'Metric', 'Severity', 'Message']);
  for (const log of logs) {
    sheet.addRow([
      formatExportDateTime(log.createdAt),
      log.deviceName,
      log.metricKey,
      log.severity,
      log.message,
    ]);
  }
  sheet.columns.forEach((col) => {
    col.width = 18;
  });
  const buffer = await workbook.xlsx.writeBuffer();
  return Buffer.from(buffer);
}

/** Real application/pdf via PDFKit (not HTML). */
export async function buildAlarmLogsPdf(
  logs: AlarmLogRow[],
  periodLabel: string,
): Promise<Buffer> {
  const PDFDocument = (await import('pdfkit')).default;
  return new Promise((resolve, reject) => {
    const doc = new PDFDocument({
      size: 'A4',
      layout: 'landscape',
      margin: 36,
      info: { Title: 'Alarm Logs Report', Author: 'JConnect' },
    });
    const chunks: Buffer[] = [];
    doc.on('data', (chunk: Buffer) => chunks.push(chunk));
    doc.on('end', () => resolve(Buffer.concat(chunks)));
    doc.on('error', reject);

    doc.fontSize(16).text('Alarm Logs Report', { align: 'center' });
    if (periodLabel) {
      doc.moveDown(0.3);
      doc.fontSize(11).text(periodLabel, { align: 'center' });
    }
    doc.moveDown(0.8);

    const headers = ['#', 'Date Time', 'Device', 'Metric', 'Severity', 'Message'];
    const colWidths = [28, 110, 100, 90, 70, 280];
    const startX = doc.x;
    let y = doc.y;

    const drawRow = (cells: string[], bold = false) => {
      let x = startX;
      doc.fontSize(9).font(bold ? 'Helvetica-Bold' : 'Helvetica');
      const heights = cells.map((c, i) => doc.heightOfString(c || '', { width: colWidths[i]! - 4 }));
      const rowH = Math.max(16, ...heights) + 4;
      if (y + rowH > doc.page.height - 48) {
        doc.addPage();
        y = doc.y;
      }
      cells.forEach((cell, i) => {
        doc.text(cell || '', x + 2, y + 2, { width: colWidths[i]! - 4, height: rowH });
        x += colWidths[i]!;
      });
      y += rowH;
      doc.y = y;
    };

    drawRow(headers, true);
    if (logs.length === 0) {
      drawRow(['', 'No data found', '', '', '', '']);
    } else {
      logs.forEach((log, index) => {
        drawRow([
          String(index + 1),
          formatExportDateTime(log.createdAt),
          log.deviceName,
          log.metricKey,
          log.severity,
          log.message,
        ]);
      });
    }
    doc.end();
  });
}

export function alarmLogsPeriodLabel(filters: AlarmLogFilters): string {
  const range = filters.range ?? '';
  let startDate = filters.startDate ?? '';
  let endDate = filters.endDate ?? '';
  const bangkokToday = () => {
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Bangkok',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(new Date());
  };
  const addDays = (isoDate: string, days: number) => {
    const d = new Date(`${isoDate}T12:00:00+07:00`);
    d.setDate(d.getDate() + days);
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Bangkok',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(d);
  };
  const fmt = (isoDate: string) => {
    const [y, m, d] = isoDate.split('-');
    return `${d}/${m}/${y}`;
  };
  const today = bangkokToday();
  switch (range) {
    case 'today':
      startDate = endDate = today;
      break;
    case 'yesterday':
      startDate = endDate = addDays(today, -1);
      break;
    case 'last_7_days':
      startDate = addDays(today, -7);
      endDate = today;
      break;
    case 'last_30_days':
      startDate = addDays(today, -30);
      endDate = today;
      break;
    default:
      break;
  }
  if (startDate && endDate) {
    return `ระหว่างวันที่ ${fmt(startDate)} ถึง ${fmt(endDate)}`;
  }
  return '';
}
