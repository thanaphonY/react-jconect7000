import bcrypt from 'bcryptjs';
import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it } from 'vitest';
import { signAccessToken, resetRefreshTokens } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

interface AlarmLog {
  id: number;
  device_id: number;
  metric_key: string;
  message: string;
  severity: string;
  status: string;
  value: string | null;
  note: string | null;
  created_at: string;
  updated_at: string | null;
  snapshot_path: string | null;
  snapshot_camera_device_id: number | null;
}

interface AlarmState {
  device_id: number;
  metric_key: string;
  active: boolean;
  message: string;
  severity: string;
  last_triggered: string;
  acknowledged: boolean;
  acknowledged_by: string | null;
  acknowledged_at: string | null;
}

function makeDb() {
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

  const logs: AlarmLog[] = [
    {
      id: 10,
      device_id: 5,
      metric_key: 'temperature',
      message: 'High temperature',
      severity: 'critical',
      status: 'active',
      value: '42',
      note: null,
      created_at: '2026-10-05T03:00:00.000Z',
      updated_at: null,
      snapshot_path: null,
      snapshot_camera_device_id: null,
    },
    {
      id: 11,
      device_id: 5,
      metric_key: 'temperature',
      message: 'Warm',
      severity: 'warning',
      status: 'acknowledged',
      value: '30',
      note: 'seen',
      created_at: '2026-10-04T03:00:00.000Z',
      updated_at: '2026-10-04T04:00:00.000Z',
      snapshot_path: null,
      snapshot_camera_device_id: null,
    },
  ];

  const states: AlarmState[] = [
    {
      device_id: 5,
      metric_key: 'temperature',
      active: true,
      message: 'High temperature',
      severity: 'critical',
      last_triggered: '2026-10-05T03:00:00.000Z',
      acknowledged: false,
      acknowledged_by: null,
      acknowledged_at: null,
    },
  ];

  const eventLogs: unknown[] = [];

  const query: Queryable['query'] = async (sql, values) => {
    if (sql.includes('FROM ia_users') && sql.includes('ia_users_id = $1')) {
      return { rows: [user] };
    }
    if (sql.includes('FROM ia_permission')) {
      return { rows: [] };
    }
    if (sql.includes('FROM ia_setting')) {
      return { rows: [{ value: 'JConnect' }] };
    }
    if (sql.includes('FROM alarm_state a') && sql.includes('WHERE a.active')) {
      return {
        rows: states
          .filter((s) => s.active)
          .map((s) => ({
            ...s,
            device_name: 'Sensor A',
            metrics: JSON.stringify([{ key: 'temperature', label: 'Temperature' }]),
          })),
      };
    }
    if (sql.includes('UPDATE alarm_state') && sql.includes('RETURNING device_id')) {
      const deviceId = Number(values?.[0]);
      const metricKey = String(values?.[1]);
      const by = String(values?.[2]);
      const row = states.find((s) => s.device_id === deviceId && s.metric_key === metricKey);
      if (!row) return { rows: [] };
      row.acknowledged = true;
      row.acknowledged_by = by;
      row.acknowledged_at = new Date().toISOString();
      return { rows: [{ device_id: deviceId }] };
    }
    if (sql.includes('COUNT(*)::int AS total') && sql.includes('FROM alarm_logs a') && sql.includes('LEFT JOIN devices')) {
      return { rows: [{ total: logs.length }] };
    }
    if (sql.includes('COUNT(*)::int AS total FROM alarm_logs') && sql.includes("status = 'active'")) {
      return { rows: [{ total: logs.filter((l) => l.status === 'active').length }] };
    }
    if (sql.includes('COUNT(*)::int AS total FROM alarm_logs')) {
      return { rows: [{ total: logs.length }] };
    }
    if (sql.includes('SELECT d.id, d.name') && sql.includes('FROM alarm_logs a')) {
      return { rows: [{ id: 5, name: 'Sensor A' }] };
    }
    if (sql.includes('GROUP BY severity') && sql.includes('FROM alarm_logs')) {
      const map = new Map<string, number>();
      for (const l of logs) map.set(l.severity, (map.get(l.severity) ?? 0) + 1);
      return { rows: [...map.entries()].map(([severity, count]) => ({ severity, count })) };
    }
    if (sql.includes('GROUP BY d.id, d.name') && sql.includes('FROM alarm_logs a')) {
      return { rows: [{ device_name: 'Sensor A', count: logs.length }] };
    }
    if (sql.includes('SELECT DISTINCT severity FROM alarm_logs')) {
      return { rows: [...new Set(logs.map((l) => l.severity))].map((severity) => ({ severity })) };
    }
    if (sql.includes('SELECT DISTINCT status FROM alarm_logs')) {
      return { rows: [...new Set(logs.map((l) => l.status))].map((status) => ({ status })) };
    }
    if (sql.includes('FROM alarm_logs a') && sql.includes('LEFT JOIN devices d') && sql.includes('LIMIT')) {
      return {
        rows: logs.map((l) => ({
          ...l,
          device_name: 'Sensor A',
        })),
      };
    }
    if (sql.includes('FROM alarm_logs a') && sql.includes('LEFT JOIN devices d') && sql.includes('ORDER BY a.created_at')) {
      return {
        rows: logs.map((l) => ({
          ...l,
          device_name: 'Sensor A',
        })),
      };
    }
    if (sql.includes('FROM alarm_logs a') && sql.includes('WHERE a.id = $1')) {
      const id = Number(values?.[0]);
      const l = logs.find((row) => row.id === id);
      if (!l) return { rows: [] };
      return {
        rows: [
          {
            ...l,
            device_name: 'Sensor A',
            device_information: '{"Brand":"Test"}',
          },
        ],
      };
    }
    if (sql.includes('FROM alarm_logs') && sql.includes('WHERE device_id = $1') && sql.includes('metric_key')) {
      const deviceId = Number(values?.[0]);
      const metricKey = String(values?.[1]);
      const exclude = Number(values?.[2]);
      return {
        rows: logs
          .filter((l) => l.device_id === deviceId && l.metric_key === metricKey && l.id !== exclude)
          .map((l) => ({
            id: l.id,
            created_at: l.created_at,
            severity: l.severity,
            status: l.status,
            message: l.message,
          })),
      };
    }
    if (sql.includes('FROM alarm_log_dispatch')) {
      return { rows: [] };
    }
    if (sql.includes('UPDATE alarm_logs') && sql.includes("status = 'acknowledged'") && sql.includes('RETURNING id')) {
      const id = Number(values?.[0]);
      const onlyActive = sql.includes("status = 'active'");
      const row = logs.find((l) => l.id === id);
      if (!row) return { rows: [] };
      if (onlyActive && row.status !== 'active') return { rows: [] };
      row.status = 'acknowledged';
      row.updated_at = new Date().toISOString();
      // note stays unchanged — PHP writes note only to event_logs metadata
      return { rows: [{ id }] };
    }
    if (sql.includes('UPDATE alarm_logs') && sql.includes("status = 'resolved'") && sql.includes('RETURNING id')) {
      const id = Number(values?.[0]);
      const row = logs.find((l) => l.id === id);
      if (!row) return { rows: [] };
      if (sql.includes("status IN ('active', 'acknowledged')") && !['active', 'acknowledged'].includes(row.status)) {
        return { rows: [] };
      }
      row.status = 'resolved';
      row.updated_at = new Date().toISOString();
      return { rows: [{ id }] };
    }
    if (sql.includes('INSERT INTO event_logs')) {
      eventLogs.push({ sql, values });
      return { rows: [] };
    }
    return { rows: [] };
  };

  return { query, logs, states, eventLogs, user };
}

describe('wave13 alarms', () => {
  let app: FastifyInstance | undefined;

  afterEach(async () => {
    resetRefreshTokens();
    await app?.close();
  });

  async function authedApp() {
    const store = makeDb();
    store.user.password = await bcrypt.hash('123456', 4);
    app = await buildApp({ pool: { query: store.query }, authSecret: SECRET, logger: false });
    const token = signAccessToken(1, SECRET);
    return { app, store, token };
  }

  it('lists active alarm state and acknowledges by device/metric', async () => {
    const { app: server, store, token } = await authedApp();
    const list = await server.inject({
      method: 'GET',
      url: '/api/v1/alarms/active',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(list.statusCode).toBe(200);
    expect(list.json().data[0].metricLabel).toBe('Temperature');
    expect(list.json().data[0].acknowledged).toBe(false);

    const ack = await server.inject({
      method: 'POST',
      url: '/api/v1/alarms/active/5/temperature/acknowledge',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(ack.statusCode).toBe(200);
    expect(store.states[0]!.acknowledged).toBe(true);
    expect(store.states[0]!.acknowledged_by).toBe('Supperadmin');
  });

  it('acknowledges and resolves alarm logs with event log entries', async () => {
    const { app: server, store, token } = await authedApp();
    const priorNote = store.logs.find((l) => l.id === 10)?.note ?? null;

    const ack = await server.inject({
      method: 'POST',
      url: '/api/v1/alarm-logs/acknowledge',
      headers: { authorization: `Bearer ${token}` },
      payload: { alarm_id: 10, note: 'looking' },
    });
    expect(ack.statusCode).toBe(200);
    expect(store.logs.find((l) => l.id === 10)?.status).toBe('acknowledged');
    expect(store.logs.find((l) => l.id === 10)?.note).toBe(priorNote);
    expect(store.eventLogs.length).toBeGreaterThan(0);

    const resolvePriorNote = store.logs.find((l) => l.id === 11)?.note;
    const resolve = await server.inject({
      method: 'POST',
      url: '/api/v1/alarm-logs/11/resolve',
      headers: { authorization: `Bearer ${token}` },
      payload: { resolution_note: 'fixed sensor' },
    });
    expect(resolve.statusCode).toBe(200);
    expect(store.logs.find((l) => l.id === 11)?.status).toBe('resolved');
    expect(store.logs.find((l) => l.id === 11)?.note).toBe(resolvePriorNote);
  });

  it('exports csv with PHP column headers', async () => {
    const { app: server, token } = await authedApp();
    const response = await server.inject({
      method: 'GET',
      url: '/api/v1/alarm-logs/export?format=csv&range=all',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(response.statusCode).toBe(200);
    expect(response.headers['content-type']).toContain('text/csv');
    expect(response.body).toContain('Date Time,Device,Metric,Severity,Message');
    expect(response.body).toContain('Sensor A');
  });

  it('exports real xlsx and pdf content types', async () => {
    const { app: server, token } = await authedApp();
    const excel = await server.inject({
      method: 'GET',
      url: '/api/v1/alarm-logs/export?format=excel&range=all',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(excel.statusCode).toBe(200);
    expect(excel.headers['content-type']).toContain(
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );
    expect(excel.headers['content-disposition']).toContain('.xlsx');
    expect(Buffer.from(excel.rawPayload).subarray(0, 2).toString()).toBe('PK');

    const pdf = await server.inject({
      method: 'GET',
      url: '/api/v1/alarm-logs/export?format=pdf&range=all',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(pdf.statusCode).toBe(200);
    expect(pdf.headers['content-type']).toContain('application/pdf');
    expect(pdf.headers['content-disposition']).toContain('.pdf');
    expect(Buffer.from(pdf.rawPayload).subarray(0, 4).toString()).toBe('%PDF');
  });

  it('returns statistics and filter options', async () => {
    const { app: server, token } = await authedApp();
    const stats = await server.inject({
      method: 'GET',
      url: '/api/v1/alarm-logs/statistics',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(stats.statusCode).toBe(200);
    expect(stats.json().data.totalAlarms).toBe(2);
    expect(stats.json().data.activeAlarms).toBe(1);

    const filters = await server.inject({
      method: 'GET',
      url: '/api/v1/alarm-logs/filters',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(filters.statusCode).toBe(200);
    expect(filters.json().data.devices[0].name).toBe('Sensor A');
    expect(filters.json().data.statuses).toContain('active');
  });
});
