import { mkdtemp, rm, writeFile, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import type { FastifyInstance } from 'fastify';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { signAccessToken, resetRefreshTokens } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

interface Template {
  id: number;
  name: string;
  description: string;
  template_type: string;
  config: Record<string, unknown>;
  created_by: number | null;
  created_at: string;
  updated_at: string;
  html_content: string;
  is_default: boolean;
}

interface Schedule {
  id: number;
  template_id: number;
  name: string;
  is_active: boolean;
  schedule_type: string;
  schedule_config: Record<string, unknown>;
  timezone: string;
  export_format: string;
  recipients: string[];
  report_params: Record<string, unknown>;
  next_run_at: string | null;
  last_run_at: string | null;
  created_by: number | null;
  created_at: string;
  updated_at: string;
}

interface ExportFile {
  id: number;
  template_id: number;
  schedule_id: number | null;
  file_path: string;
  file_name: string;
  file_size: number;
  export_format: string;
  exported_at: string;
  exported_by: number | null;
}

function makeDb(exportDir: string) {
  process.env.REPORT_EXPORT_DIR = exportDir;

  const user = {
    ia_users_id: 1,
    name: 'Supperadmin',
    email: 'admin@example.com',
    password: '',
    user_type: 'admin',
    profile_pic: null as string | null,
    is_deleted: '0',
    status: 'active',
  };

  let nextTemplateId = 2;
  let nextScheduleId = 2;
  let nextFileId = 2;
  let lastAggregateSql = '';

  const templates: Template[] = [
    {
      id: 1,
      name: 'Daily Temp',
      description: 'Temperature report',
      template_type: 'custom_builder',
      config: {
        builder_version: 1,
        data_source: 'device_data',
        filters: {
          time_range: 'last_24h',
          time_interval: '1h',
          display: ['min', 'max', 'average'],
          sort_order: 'desc',
          selected_device_metrics: [
            { device_id: 5, device_name: 'Sensor A', metric: 'temperature', metric_label: 'Temp' },
          ],
        },
        export_inputs: [],
        editor: {
          report_title: 'Daily Temp',
          table_layout: 'rows',
          pdf_orientation: 'portrait',
          pdf_title: '',
          excel_sheet_name: 'Sheet1',
          csv_delimiter: ',',
          display_columns: [
            { key: 'timestamp', label: 'Time' },
            { key: 'device_name', label: 'Device' },
            { key: 'key', label: 'Metric' },
            { key: 'value_avg', label: 'Average' },
          ],
        },
        template_type: 'custom_builder',
      },
      created_by: 1,
      created_at: '2026-10-01T00:00:00.000Z',
      updated_at: '2026-10-02T00:00:00.000Z',
      html_content: '',
      is_default: false,
    },
  ];

  const schedules: Schedule[] = [
    {
      id: 1,
      template_id: 1,
      name: 'Morning',
      is_active: true,
      schedule_type: 'daily',
      schedule_config: { time: '08:00', weekday: 1, day: 1 },
      timezone: 'Asia/Bangkok',
      export_format: 'csv',
      recipients: [],
      report_params: {},
      next_run_at: '2026-10-06T01:00:00.000Z',
      last_run_at: null,
      created_by: 1,
      created_at: '2026-10-01T00:00:00.000Z',
      updated_at: '2026-10-01T00:00:00.000Z',
    },
  ];

  const files: ExportFile[] = [
    {
      id: 1,
      template_id: 1,
      schedule_id: null,
      file_path: '2026/10/Daily_Temp_test.csv',
      file_name: 'Daily_Temp_test.csv',
      file_size: 12,
      export_format: 'csv',
      exported_at: '2026-10-05T03:00:00.000Z',
      exported_by: 1,
    },
  ];

  const devices = [
    {
      id: 5,
      name: 'Sensor A',
      location: 'Room 1',
      is_deleted: false,
      is_active: true,
      metrics: [
        { key: 'temperature', label: 'Temp', enable: true, datatype: 'float' },
        { key: 'humidity', label: 'Humidity', enable: true, datatype: 'float' },
      ],
    },
  ];

  const deviceData = [
    {
      id: 100,
      device_id: 5,
      key: 'temperature',
      value: 25.456,
      timestamp: '2026-10-05T02:00:00.000Z',
    },
    {
      id: 101,
      device_id: 5,
      key: 'temperature',
      value: 26.1,
      timestamp: '2026-10-05T02:30:00.000Z',
    },
  ];

  const query: Queryable['query'] = async (sql, values) => {
    if (sql.includes('FROM ia_users') && sql.includes('ia_users_id = $1')) {
      return { rows: [user], rowCount: 1 };
    }
    if (sql.includes('FROM ia_permission')) {
      return { rows: [], rowCount: 0 };
    }
    if (sql.includes('FROM ia_setting')) {
      return { rows: [{ value: 'JConnect' }], rowCount: 1 };
    }

    if (sql.includes('FROM report_templates') && sql.includes("template_type = 'custom_builder'") && !sql.includes('WHERE id')) {
      return {
        rows: templates
          .filter((t) => t.template_type === 'custom_builder')
          .map((t) => ({ ...t })),
        rowCount: templates.length,
      };
    }

    if (sql.includes('FROM report_templates WHERE id = $1')) {
      const t = templates.find((x) => x.id === Number(values?.[0]));
      return { rows: t ? [{ ...t }] : [], rowCount: t ? 1 : 0 };
    }

    if (sql.includes('INSERT INTO report_templates')) {
      const row: Template = {
        id: nextTemplateId++,
        name: String(values?.[0] ?? ''),
        description: String(values?.[1] ?? ''),
        template_type: 'custom_builder',
        config: typeof values?.[2] === 'string' ? JSON.parse(values[2] as string) : (values?.[2] as Record<string, unknown>),
        created_by: Number(values?.[3] ?? 0) || null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        html_content: '',
        is_default: false,
      };
      templates.push(row);
      return { rows: [{ ...row }], rowCount: 1 };
    }

    if (sql.includes('UPDATE report_templates')) {
      const id = Number(values?.[0]);
      const t = templates.find((x) => x.id === id);
      if (!t) return { rows: [], rowCount: 0 };
      t.name = String(values?.[1] ?? t.name);
      t.description = String(values?.[2] ?? t.description);
      t.config =
        typeof values?.[3] === 'string'
          ? JSON.parse(values[3] as string)
          : (values?.[3] as Record<string, unknown>);
      t.updated_at = new Date().toISOString();
      return { rows: [{ ...t }], rowCount: 1 };
    }

    if (sql.includes('DELETE FROM report_templates')) {
      const id = Number(values?.[0]);
      const idx = templates.findIndex((x) => x.id === id && x.template_type === 'custom_builder');
      if (idx < 0) return { rows: [], rowCount: 0 };
      templates.splice(idx, 1);
      return { rows: [], rowCount: 1 };
    }

    if (sql.includes('FROM devices') && sql.includes('is_active = true') && sql.includes('ORDER BY name') && !sql.includes('DISTINCT location')) {
      return {
        rows: devices.map((d) => ({ id: d.id, name: d.name, location: d.location })),
        rowCount: devices.length,
      };
    }

    if (sql.includes('SELECT metrics FROM devices WHERE id = $1')) {
      const d = devices.find((x) => x.id === Number(values?.[0]));
      return { rows: d ? [{ metrics: d.metrics }] : [], rowCount: d ? 1 : 0 };
    }

    if (sql.includes('SELECT id, metrics FROM devices WHERE id = ANY')) {
      const ids = (values?.[0] as number[]) ?? [];
      return {
        rows: devices.filter((d) => ids.includes(d.id)).map((d) => ({ id: d.id, metrics: d.metrics })),
        rowCount: devices.length,
      };
    }

    if (sql.includes('DISTINCT location AS room')) {
      return { rows: [{ room: 'Room 1' }], rowCount: 1 };
    }

    if (sql.includes('DISTINCT "key" AS metric_key')) {
      return { rows: [{ metric_key: 'temperature' }], rowCount: 1 };
    }

    if (sql.includes('DISTINCT action_type')) {
      return { rows: [{ action_type: 'create' }], rowCount: 1 };
    }

    if (sql.includes('DISTINCT entity_type')) {
      return { rows: [{ entity_type: 'Device' }], rowCount: 1 };
    }

    // Aggregated device_data fetch
    if (sql.includes('FROM device_data dd') && sql.includes('bucket_ts')) {
      lastAggregateSql = sql;
      return {
        rows: [
          {
            device_id: 5,
            key: 'temperature',
            bucket_ts: '2026-10-05T02:00:00.000Z',
            device_name: 'Sensor A',
            value_min: 25.456,
            value_max: 26.1,
            value_avg: 25.778,
          },
        ],
        rowCount: 1,
      };
    }

    if (sql.includes('FROM device_data dd') && sql.includes('dd.value')) {
      return {
        rows: deviceData.map((r) => ({
          ...r,
          device_name: 'Sensor A',
        })),
        rowCount: deviceData.length,
      };
    }

    if (sql.includes('FROM report_schedules s') && sql.includes('ORDER BY s.next_run_at')) {
      return {
        rows: schedules.map((s) => ({
          ...s,
          template_name: templates.find((t) => t.id === s.template_id)?.name ?? null,
        })),
        rowCount: schedules.length,
      };
    }

    if (sql.includes('FROM report_schedules s') && sql.includes('WHERE s.id = $1')) {
      const s = schedules.find((x) => x.id === Number(values?.[0]));
      if (!s) return { rows: [], rowCount: 0 };
      return {
        rows: [
          {
            ...s,
            template_name: templates.find((t) => t.id === s.template_id)?.name ?? null,
          },
        ],
        rowCount: 1,
      };
    }

    if (sql.includes('INSERT INTO report_schedules')) {
      const row: Schedule = {
        id: nextScheduleId++,
        template_id: Number(values?.[0]),
        name: String(values?.[1]),
        is_active: Boolean(values?.[2]),
        schedule_type: String(values?.[3]),
        schedule_config:
          typeof values?.[4] === 'string'
            ? JSON.parse(values[4] as string)
            : (values?.[4] as Record<string, unknown>),
        timezone: String(values?.[5]),
        export_format: String(values?.[6]),
        recipients:
          typeof values?.[7] === 'string' ? JSON.parse(values[7] as string) : (values?.[7] as string[]),
        report_params: {},
        next_run_at: String(values?.[8]),
        last_run_at: null,
        created_by: Number(values?.[9] ?? 0) || null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };
      schedules.push(row);
      return { rows: [{ id: row.id }], rowCount: 1 };
    }

    if (sql.includes('UPDATE report_schedules')) {
      const id = Number(values?.[0]);
      const s = schedules.find((x) => x.id === id);
      if (!s) return { rows: [], rowCount: 0 };
      s.template_id = Number(values?.[1]);
      s.name = String(values?.[2]);
      s.is_active = Boolean(values?.[3]);
      s.schedule_type = String(values?.[4]);
      s.schedule_config =
        typeof values?.[5] === 'string'
          ? JSON.parse(values[5] as string)
          : (values?.[5] as Record<string, unknown>);
      s.timezone = String(values?.[6]);
      s.export_format = String(values?.[7]);
      s.recipients =
        typeof values?.[8] === 'string' ? JSON.parse(values[8] as string) : (values?.[8] as string[]);
      s.next_run_at = String(values?.[9]);
      s.updated_at = new Date().toISOString();
      return { rows: [{ id }], rowCount: 1 };
    }

    if (sql.includes('DELETE FROM report_schedules')) {
      const id = Number(values?.[0]);
      const idx = schedules.findIndex((x) => x.id === id);
      if (idx < 0) return { rows: [], rowCount: 0 };
      schedules.splice(idx, 1);
      return { rows: [], rowCount: 1 };
    }

    if (sql.includes('COUNT(*)::int AS total FROM report_export_files')) {
      return { rows: [{ total: files.length }], rowCount: 1 };
    }

    if (sql.includes('FROM report_export_files f') && sql.includes('LEFT JOIN report_templates')) {
      return {
        rows: files.map((f) => ({
          ...f,
          template_name: templates.find((t) => t.id === f.template_id)?.name ?? null,
        })),
        rowCount: files.length,
      };
    }

    if (sql.includes('SELECT file_name, file_path FROM report_export_files WHERE id = $1')) {
      const f = files.find((x) => x.id === Number(values?.[0]));
      return {
        rows: f ? [{ file_name: f.file_name, file_path: f.file_path }] : [],
        rowCount: f ? 1 : 0,
      };
    }

    if (sql.includes('SELECT file_path FROM report_export_files WHERE id = $1')) {
      const f = files.find((x) => x.id === Number(values?.[0]));
      return { rows: f ? [{ file_path: f.file_path }] : [], rowCount: f ? 1 : 0 };
    }

    if (sql.includes('DELETE FROM report_export_files')) {
      const id = Number(values?.[0]);
      const idx = files.findIndex((x) => x.id === id);
      if (idx < 0) return { rows: [], rowCount: 0 };
      files.splice(idx, 1);
      return { rows: [], rowCount: 1 };
    }

    if (sql.includes('INSERT INTO report_export_files')) {
      const row: ExportFile = {
        id: nextFileId++,
        template_id: Number(values?.[0]),
        schedule_id: (values?.[1] as number | null) ?? null,
        file_path: String(values?.[2]),
        file_name: String(values?.[3]),
        file_size: Number(values?.[4] ?? 0),
        export_format: String(values?.[5]),
        exported_at: new Date().toISOString(),
        exported_by: (values?.[6] as number | null) ?? null,
      };
      files.push(row);
      return { rows: [{ id: row.id }], rowCount: 1 };
    }

    return { rows: [], rowCount: 0 };
  };

  return {
    query,
    templates,
    schedules,
    files,
    getLastAggregateSql: () => lastAggregateSql,
  };
}

describe('wave 15 reports', () => {
  let app: FastifyInstance;
  let exportDir: string;
  let token: string;
  let db: ReturnType<typeof makeDb>;

  beforeEach(async () => {
    resetRefreshTokens();
    exportDir = await mkdtemp(path.join(tmpdir(), 'jconnect-reports-'));
    await mkdir(path.join(exportDir, '2026', '10'), { recursive: true });
    await writeFile(path.join(exportDir, '2026', '10', 'Daily_Temp_test.csv'), 'a,b\n1,2\n');
    db = makeDb(exportDir);
    app = await buildApp({ pool: db, authSecret: SECRET, logger: false });
    token = signAccessToken(1, SECRET);
  });

  afterEach(async () => {
    await app.close();
    resetRefreshTokens();
    await rm(exportDir, { recursive: true, force: true });
    delete process.env.REPORT_EXPORT_DIR;
  });

  it('lists templates and returns one by id', async () => {
    const list = await app.inject({
      method: 'GET',
      url: '/api/v1/report-templates',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(list.statusCode).toBe(200);
    const body = list.json() as { data: Array<{ id: number; name: string }> };
    expect(body.data[0]?.name).toBe('Daily Temp');

    const one = await app.inject({
      method: 'GET',
      url: '/api/v1/report-templates/1',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(one.statusCode).toBe(200);
    expect((one.json() as { data: { config: { data_source: string } } }).data.config.data_source).toBe(
      'device_data',
    );
  });

  it('creates, updates, and deletes a template', async () => {
    const created = await app.inject({
      method: 'POST',
      url: '/api/v1/report-templates',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'New Report',
        description: 'desc',
        dataSource: 'device_data',
        filters: {
          time_range: 'today',
          time_interval: '15m',
          display: ['min', 'max'],
          sort_order: 'asc',
          selected_device_metrics: [
            { device_id: 5, device_name: 'Sensor A', metric: 'humidity', metric_label: 'Humidity' },
          ],
        },
        editor: {
          report_title: 'Humidity',
          table_layout: 'pivot',
          display_columns: [
            { key: 'timestamp', label: 'Time' },
            { key: 'value_min', label: 'Min' },
          ],
        },
      },
    });
    expect(created.statusCode).toBe(201);
    const id = (created.json() as { data: { id: number } }).data.id;

    const updated = await app.inject({
      method: 'PUT',
      url: `/api/v1/report-templates/${id}`,
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Updated Report',
        description: 'changed',
        dataSource: 'device_data',
        filters: { time_range: 'last_7d', time_interval: '1h', display: ['average'], sort_order: 'desc' },
        editor: { report_title: 'Updated', table_layout: 'rows', display_columns: [] },
      },
    });
    expect(updated.statusCode).toBe(200);
    expect((updated.json() as { data: { name: string } }).data.name).toBe('Updated Report');

    const deleted = await app.inject({
      method: 'DELETE',
      url: `/api/v1/report-templates/${id}`,
      headers: { authorization: `Bearer ${token}` },
    });
    expect(deleted.statusCode).toBe(200);
  });

  it('fetches report data and exports csv to file manager', async () => {
    const fetchRes = await app.inject({
      method: 'POST',
      url: '/api/v1/report-templates/1/fetch',
      headers: { authorization: `Bearer ${token}` },
      payload: { filters: {} },
    });
    expect(fetchRes.statusCode).toBe(200);
    const fetchBody = fetchRes.json() as { data: { count: number; rows: unknown[] } };
    expect(fetchBody.data.count).toBeGreaterThan(0);
    expect(db.getLastAggregateSql()).toContain(
      'GROUP BY dd.device_id, dd.key, to_timestamp(floor(extract(epoch from dd.timestamp) / $1) * $2)',
    );
    expect(db.getLastAggregateSql()).not.toMatch(/GROUP BY.*\/ \$[3-9]/);

    const exportRes = await app.inject({
      method: 'POST',
      url: '/api/v1/report-templates/1/export',
      headers: { authorization: `Bearer ${token}` },
      payload: { format: 'csv', saveToFileManager: true, filters: {} },
    });
    expect(exportRes.statusCode).toBe(200);
    const exportBody = exportRes.json() as { data: { fileId: number; fileName: string } };
    expect(exportBody.data.fileId).toBeGreaterThan(1);
    expect(exportBody.data.fileName).toMatch(/\.csv$/);
  });

  it('manages schedules and deletes export files', async () => {
    const list = await app.inject({
      method: 'GET',
      url: '/api/v1/report-schedules',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(list.statusCode).toBe(200);
    expect((list.json() as { data: unknown[] }).data).toHaveLength(1);

    const created = await app.inject({
      method: 'POST',
      url: '/api/v1/report-schedules',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        templateId: 1,
        name: 'Weekly',
        scheduleType: 'weekly',
        scheduleTime: '09:30',
        scheduleWeekday: 2,
        exportFormat: 'pdf',
        isActive: true,
      },
    });
    expect(created.statusCode).toBe(201);
    const sid = (created.json() as { data: { id: number } }).data.id;

    const updated = await app.inject({
      method: 'PUT',
      url: `/api/v1/report-schedules/${sid}`,
      headers: { authorization: `Bearer ${token}` },
      payload: {
        templateId: 1,
        name: 'Weekly Updated',
        scheduleType: 'weekly',
        scheduleTime: '10:00',
        scheduleWeekday: 3,
        exportFormat: 'excel',
        isActive: false,
      },
    });
    expect(updated.statusCode).toBe(200);
    expect((updated.json() as { data: { name: string; isActive: boolean } }).data.isActive).toBe(false);

    const delSchedule = await app.inject({
      method: 'DELETE',
      url: `/api/v1/report-schedules/${sid}`,
      headers: { authorization: `Bearer ${token}` },
    });
    expect(delSchedule.statusCode).toBe(200);

    const delFile = await app.inject({
      method: 'DELETE',
      url: '/api/v1/report-files/1',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(delFile.statusCode).toBe(200);
  });

  it('returns device metrics lookup', async () => {
    const res = await app.inject({
      method: 'GET',
      url: '/api/v1/report-templates/lookups/metrics-by-device/5',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(res.statusCode).toBe(200);
    const body = res.json() as { data: Array<{ key: string; label: string }> };
    expect(body.data.some((m) => m.key === 'temperature')).toBe(true);
  });
});
