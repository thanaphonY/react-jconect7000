import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { signAccessToken, resetRefreshTokens } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

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

  const devices = [
    { id: 10, name: 'CRAC-01', metrics: '[]', communication_config: '{}', config: '{}' },
    { id: 11, name: 'J1000', metrics: '[{"key":"DO1","oid":"1.3.6.1.4.1.12020.1.3.1.1.1","label":"DO1"}]', communication_config: '{"protocol":"snmp"}', config: '{}' },
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
    if (sql.includes('FROM devices') && sql.includes('is_deleted = false') && sql.includes('ORDER BY name')) {
      return { rows: devices.map((d) => ({ id: d.id, name: d.name })) };
    }
    if (sql.includes('FROM devices d') && sql.includes('LEFT JOIN device_templates')) {
      const id = Number(values?.[0]);
      const row = devices.find((d) => d.id === id);
      return {
        rows: row
          ? [
              {
                ...row,
                template_protocol: 'snmp',
                template_name: 'Template',
              },
            ]
          : [],
      };
    }
    if (sql.includes('FROM devices') && sql.includes('WHERE id = $1')) {
      const id = Number(values?.[0]);
      const row = devices.find((d) => d.id === id);
      return { rows: row ? [row] : [] };
    }
    if (sql.includes('FROM device_data')) {
      return { rows: [] };
    }
    throw new Error(`Unexpected SQL: ${sql}`);
  };

  return { query };
}

describe('wave 18 control via control-logic', () => {
  let app: FastifyInstance | undefined;
  const originalFetch = globalThis.fetch;

  afterEach(async () => {
    globalThis.fetch = originalFetch;
    resetRefreshTokens();
    vi.restoreAllMocks();
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

  it('reports control-logic offline when /health is not JSON', async () => {
    const { token } = await start();
    globalThis.fetch = vi.fn(async () =>
      new Response('<html>404</html>', { status: 404, headers: { 'Content-Type': 'text/html' } }),
    ) as typeof fetch;

    const res = await app!.inject({
      method: 'GET',
      url: '/api/v1/control/health',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toEqual({
      data: { ok: false, message: 'control-logic offline' },
    });
  });

  it('bootstrap returns offline shell with devices when engine is down', async () => {
    const { token } = await start();
    globalThis.fetch = vi.fn(async () => {
      throw new Error('connection refused');
    }) as typeof fetch;

    const res = await app!.inject({
      method: 'GET',
      url: '/api/v1/control/bootstrap',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(res.statusCode).toBe(200);
    const body = res.json();
    expect(body.data.engine.ok).toBe(false);
    expect(body.data.groups).toEqual([]);
    expect(body.data.can_create).toBe(true);
    expect(body.data.devices.length).toBe(2);
  });

  it('proxies group create to control-logic when online', async () => {
    const { token } = await start();
    const calls: Array<{ url: string; method: string; body: unknown }> = [];

    globalThis.fetch = vi.fn(async (input: string | URL | Request, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      const body = init?.body ? JSON.parse(String(init.body)) : null;
      calls.push({ url, method, body });

      if (url.endsWith('/health')) {
        return new Response(JSON.stringify({ ok: true }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      if (url.includes('/api/groups') && method === 'POST') {
        return new Response(
          JSON.stringify({
            ok: true,
            group: { id: 5, name: body.name, kind: body.kind, description: body.description },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } },
        );
      }
      return new Response(JSON.stringify({ ok: false, error: 'unexpected' }), { status: 500 });
    }) as typeof fetch;

    const res = await app!.inject({
      method: 'POST',
      url: '/api/v1/control/groups',
      headers: { authorization: `Bearer ${token}` },
      payload: { name: 'Server Room', kind: 'room', description: 'Main CRAC' },
    });
    expect(res.statusCode).toBe(201);
    expect(res.json().data.group.name).toBe('Server Room');
    expect(calls.some((c) => c.url.includes('/api/groups') && c.method === 'POST')).toBe(true);
  });

  it('proxies command execute and schedule save', async () => {
    const { token } = await start();
    globalThis.fetch = vi.fn(async (input: string | URL | Request, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      if (url.includes('/execute') && method === 'POST') {
        return new Response(JSON.stringify({ ok: true, note: 'written' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      if (url.includes('/api/schedules') && method === 'POST') {
        const body = JSON.parse(String(init?.body));
        return new Response(
          JSON.stringify({
            ok: true,
            schedule: { id: 1, ...body },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } },
        );
      }
      return new Response(JSON.stringify({ ok: false, error: 'unexpected' }), { status: 500 });
    }) as typeof fetch;

    const exec = await app!.inject({
      method: 'POST',
      url: '/api/v1/control/commands/3/execute',
      headers: { authorization: `Bearer ${token}` },
      payload: { value: '1' },
    });
    expect(exec.statusCode).toBe(200);
    expect(exec.json().data.ok).toBe(true);

    const sch = await app!.inject({
      method: 'POST',
      url: '/api/v1/control/schedules',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Morning on',
        command_id: 3,
        time: '08:30',
        days: [1, 2, 3, 4, 5],
        action: 'on',
        enabled: true,
      },
    });
    expect(sch.statusCode).toBe(201);
    expect(sch.json().data.schedule.cron).toBe('30 8 * * 1,2,3,4,5');
  });

  it('loads device write context from devices table', async () => {
    const { token } = await start();
    const res = await app!.inject({
      method: 'GET',
      url: '/api/v1/control/devices/11/context',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(res.statusCode).toBe(200);
    const body = res.json().data;
    expect(body.name).toBe('J1000');
    expect(body.write_targets.length).toBeGreaterThan(0);
    expect(body.write_targets[0].key).toBe('DO1');
  });

  it('bootstrap loads group inventory when control-logic is online', async () => {
    const { token } = await start();
    globalThis.fetch = vi.fn(async (input: string | URL | Request) => {
      const url = String(input);
      if (url.endsWith('/health')) {
        return new Response(JSON.stringify({ ok: true }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      if (url.includes('/api/groups')) {
        return new Response(
          JSON.stringify({
            ok: true,
            groups: [{ id: 1, name: 'Building A', kind: 'building', command_count: 2 }],
            ungrouped_count: 1,
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } },
        );
      }
      if (url.includes('/api/commands')) {
        return new Response(
          JSON.stringify({
            ok: true,
            commands: [{ id: 9, name: 'CRAC On', device_id: 10, enabled: true }],
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } },
        );
      }
      if (url.includes('/api/logs') || url.includes('/api/schedules') || url.includes('/api/sequences') || url.includes('/api/rules')) {
        const key = url.includes('logs')
          ? 'logs'
          : url.includes('schedules')
            ? 'schedules'
            : url.includes('sequences')
              ? 'sequences'
              : 'rules';
        return new Response(JSON.stringify({ ok: true, [key]: [] }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      return new Response(JSON.stringify({ ok: false, error: url }), { status: 500 });
    }) as typeof fetch;

    const res = await app!.inject({
      method: 'GET',
      url: '/api/v1/control/bootstrap?group=1',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(res.statusCode).toBe(200);
    const body = res.json().data;
    expect(body.engine.ok).toBe(true);
    expect(body.groups).toHaveLength(1);
    expect(body.current_group.id).toBe(1);
    expect(body.commands[0].name).toBe('CRAC On');
  });
});
