import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it } from 'vitest';
import { signAccessToken, resetRefreshTokens } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

type DeviceRow = {
  id: number;
  name: string;
  is_active: boolean;
  is_deleted: boolean;
  metrics: unknown;
  updated_at?: string;
};

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

  const devices: DeviceRow[] = [
    {
      id: 10,
      name: 'Alpha Sensor',
      is_active: true,
      is_deleted: false,
      metrics: [
        { type: 'modbus', key: 'temp', label: 'Temperature', unit: 'C', datatype: 'float', enable: true },
        { type: 'modbus', key: 'hum', label: 'Humidity', unit: '%', datatype: 'float', enable: true },
      ],
    },
    {
      id: 20,
      name: 'Beta Sensor',
      is_active: true,
      is_deleted: false,
      metrics: [
        {
          type: 'calc',
          key: 'avg_temp',
          label: 'Average temp',
          unit: 'C',
          datatype: 'float',
          enable: true,
          store_policy: 'always',
          pull_rate: 30,
          calc: {
            formula: '(a + b) / 2',
            inputs: [
              { var: 'a', metricKey: 'temp' },
              { var: 'b', source: 'random', min: 0, max: 10 },
            ],
            trigger: { mode: 'interval', intervalSec: 30 },
            decimals: 2,
          },
          triggers: [],
          normal_condition: {},
        },
      ],
    },
  ];

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

    if (
      sql.includes('FROM devices') &&
      sql.includes('ORDER BY name ASC') &&
      sql.includes('is_deleted = false')
    ) {
      return {
        rows: [...devices]
          .filter((d) => !d.is_deleted)
          .sort((a, b) => a.name.localeCompare(b.name))
          .map((d) => ({
            id: d.id,
            name: d.name,
            metrics: d.metrics,
            is_active: d.is_active,
          })),
      };
    }

    if (sql.includes('FROM devices') && sql.includes('WHERE id = $1') && sql.includes('is_deleted = false')) {
      const id = Number(values?.[0]);
      const row = devices.find((d) => d.id === id && !d.is_deleted);
      return {
        rows: row
          ? [{ id: row.id, name: row.name, metrics: row.metrics, is_active: row.is_active }]
          : [],
      };
    }

    if (sql.includes('UPDATE devices') && sql.includes('SET metrics')) {
      const metricsJson = String(values?.[0]);
      const id = Number(values?.[1]);
      const row = devices.find((d) => d.id === id && !d.is_deleted);
      if (!row) return { rows: [] };
      row.metrics = JSON.parse(metricsJson);
      row.updated_at = '2026-10-05 22:00:00';
      return { rows: [{ id: row.id }] };
    }

    throw new Error(`Unexpected SQL: ${sql}`);
  };

  return { query, devices };
}

describe('wave 17 calculation save', () => {
  let app: FastifyInstance | undefined;

  afterEach(async () => {
    resetRefreshTokens();
    if (app) {
      await app.close();
      app = undefined;
    }
  });

  async function start() {
    const db = makeDb();
    app = await buildApp({ pool: db, authSecret: SECRET });
    await app.ready();
    const token = signAccessToken(1, SECRET);
    return { db, token };
  }

  it('lists calculations with formula nested under calc and device options', async () => {
    const { token } = await start();
    const res = await app!.inject({
      method: 'GET',
      url: '/api/v1/calculations',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(res.statusCode).toBe(200);
    const body = res.json() as {
      data: {
        calculations: Array<{
          deviceId: number;
          deviceName: string;
          metric: { key: string; calc: { formula: string } };
        }>;
        devices: Array<{ id: number; name: string; metrics: unknown[] }>;
      };
    };
    expect(body.data.calculations).toHaveLength(1);
    expect(body.data.calculations[0]).toMatchObject({
      deviceId: 20,
      deviceName: 'Beta Sensor',
      metric: { key: 'avg_temp', calc: { formula: '(a + b) / 2' } },
    });
    expect(body.data.devices).toHaveLength(2);
    expect(body.data.devices[0].name).toBe('Alpha Sensor');
  });

  it('creates a calculation metric on a device', async () => {
    const { token, db } = await start();
    const res = await app!.inject({
      method: 'POST',
      url: '/api/v1/calculations',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        deviceId: 10,
        key: 'diff',
        label: 'Diff',
        unit: 'C',
        datatype: 'float',
        formula: 'a - b',
        inputs: [
          { var: 'a', metricKey: 'temp' },
          { var: 'b', metricKey: 'hum', deviceId: 10 },
        ],
        triggerMode: 'interval',
        intervalSec: 15,
        decimals: 3,
        maxAgeSec: 60,
      },
    });
    expect(res.statusCode).toBe(200);
    const body = res.json() as {
      data: {
        deviceId: number;
        metric: {
          key: string;
          type: string;
          datatype: string;
          pull_rate: number;
          calc: {
            formula: string;
            decimals: number;
            maxAgeSec: number;
            trigger: { mode: string; intervalSec: number };
            inputs: Array<{ var: string; metricKey?: string; deviceId?: number }>;
          };
        };
      };
    };
    expect(body.data.deviceId).toBe(10);
    expect(body.data.metric).toMatchObject({
      type: 'calc',
      key: 'diff',
      label: 'Diff',
      unit: 'C',
      datatype: 'float',
      pull_rate: 15,
      calc: {
        formula: 'a - b',
        decimals: 3,
        maxAgeSec: 60,
        trigger: { mode: 'interval', intervalSec: 15 },
      },
    });
    expect(body.data.metric.calc.inputs).toEqual([
      { var: 'a', metricKey: 'temp' },
      { var: 'b', metricKey: 'hum', deviceId: 10 },
    ]);

    const alpha = db.devices.find((d) => d.id === 10)!;
    const metrics = alpha.metrics as Array<{ key: string; type: string }>;
    expect(metrics.some((m) => m.type === 'calc' && m.key === 'diff')).toBe(true);
  });

  it('updates an existing calculation and rejects duplicate keys', async () => {
    const { token, db } = await start();

    const update = await app!.inject({
      method: 'POST',
      url: '/api/v1/calculations',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        deviceId: 20,
        originalDeviceId: 20,
        originalKey: 'avg_temp',
        key: 'avg_temp',
        label: 'Avg updated',
        formula: '(a + b) / 2',
        inputs: [{ var: 'a', metricKey: 'temp' }],
        triggerMode: 'on_pull',
        decimals: 1,
      },
    });
    expect(update.statusCode).toBe(200);
    const updated = update.json() as {
      data: { metric: { label: string; calc: { trigger: { mode: string }; decimals: number } } };
    };
    expect(updated.data.metric.label).toBe('Avg updated');
    expect(updated.data.metric.calc.trigger.mode).toBe('on_pull');
    expect(updated.data.metric.calc.decimals).toBe(1);

    const betaMetrics = db.devices.find((d) => d.id === 20)!.metrics as Array<{ key: string }>;
    expect(betaMetrics.filter((m) => m.key === 'avg_temp')).toHaveLength(1);

    const duplicate = await app!.inject({
      method: 'POST',
      url: '/api/v1/calculations',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        deviceId: 20,
        key: 'avg_temp',
        formula: 'a',
        inputs: [{ var: 'a', metricKey: 'temp' }],
      },
    });
    expect(duplicate.statusCode).toBe(409);
  });

  it('moves calculation to another device and requires cron for schedule mode', async () => {
    const { token, db } = await start();

    const missingCron = await app!.inject({
      method: 'POST',
      url: '/api/v1/calculations',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        deviceId: 10,
        key: 'scheduled',
        formula: 'a',
        inputs: [{ var: 'a', metricKey: 'temp' }],
        triggerMode: 'schedule',
        cron: '',
      },
    });
    expect(missingCron.statusCode).toBe(400);

    const move = await app!.inject({
      method: 'POST',
      url: '/api/v1/calculations',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        deviceId: 10,
        originalDeviceId: 20,
        originalKey: 'avg_temp',
        key: 'avg_temp',
        label: 'Moved',
        formula: 'a',
        inputs: [{ var: 'a', source: 'random', min: 1, max: 5 }],
        triggerMode: 'schedule',
        cron: '*/5 * * * *',
        decimals: 2,
      },
    });
    expect(move.statusCode).toBe(200);

    const alpha = db.devices.find((d) => d.id === 10)!.metrics as Array<{ key: string; type: string }>;
    const beta = db.devices.find((d) => d.id === 20)!.metrics as Array<{ key: string; type: string }>;
    expect(alpha.some((m) => m.type === 'calc' && m.key === 'avg_temp')).toBe(true);
    expect(beta.some((m) => m.type === 'calc' && m.key === 'avg_temp')).toBe(false);
  });

  it('deletes a calculation metric', async () => {
    const { token, db } = await start();
    const res = await app!.inject({
      method: 'DELETE',
      url: '/api/v1/calculations/20/avg_temp',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toEqual({ data: { success: true } });
    const beta = db.devices.find((d) => d.id === 20)!.metrics as Array<{ type: string }>;
    expect(beta.some((m) => m.type === 'calc')).toBe(false);
  });

  it('uses pull_rate 1 when intervalSec is empty (PHP cast)', async () => {
    const { token } = await start();
    const res = await app!.inject({
      method: 'POST',
      url: '/api/v1/calculations',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        deviceId: 10,
        key: 'empty_interval',
        formula: 'a',
        inputs: [{ var: 'a', metricKey: 'temp' }],
        triggerMode: 'schedule',
        cron: '*/5 * * * *',
      },
    });
    expect(res.statusCode).toBe(200);
    const body = res.json() as {
      data: { metric: { pull_rate: number; calc: { trigger: { mode: string; cron: string } } } };
    };
    expect(body.data.metric.pull_rate).toBe(1);
    expect(body.data.metric.calc.trigger).toEqual({ mode: 'schedule', cron: '*/5 * * * *' });
  });
});
