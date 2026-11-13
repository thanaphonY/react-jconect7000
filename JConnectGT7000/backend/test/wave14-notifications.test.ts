import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { signAccessToken, resetRefreshTokens } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

interface Channel {
  id: number;
  name: string;
  type: string;
  is_active: boolean;
  message_template: string | null;
  description: string | null;
  config: Record<string, unknown>;
  is_deleted: boolean;
  created_at: string;
  updated_at: string;
}

interface HealthCheck {
  id: number;
  name: string;
  is_active: boolean;
  schedule_type: string;
  schedule_config: { time: string; weekday: number; day: number };
  timezone: string;
  channel_ids: number[];
  rooms: unknown[];
  message_template: string;
  last_run_at: string | null;
  next_run_at: string | null;
  created_at: string;
  updated_at: string;
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

  let nextChannelId = 2;
  let nextHealthId = 2;
  const channels: Channel[] = [
    {
      id: 1,
      name: 'Ops LINE',
      type: 'line_oa',
      is_active: true,
      message_template: '{{metric}} = {{value}}',
      description: 'Primary',
      config: { access_token: 'tok', to: 'U123' },
      is_deleted: false,
      created_at: '2026-10-01T00:00:00.000Z',
      updated_at: '2026-10-01T00:00:00.000Z',
    },
  ];
  const healthChecks: HealthCheck[] = [
    {
      id: 1,
      name: 'Morning',
      is_active: true,
      schedule_type: 'daily',
      schedule_config: { time: '08:00', weekday: 1, day: 1 },
      timezone: 'Asia/Bangkok',
      channel_ids: [1],
      rooms: [],
      message_template: '{name}\n{datetime}\nStatus: {status}\n\n{alarms}',
      last_run_at: null,
      next_run_at: '2026-10-06T01:00:00.000Z',
      created_at: '2026-10-01T00:00:00.000Z',
      updated_at: '2026-10-01T00:00:00.000Z',
    },
  ];
  const healthLogs: unknown[] = [];

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
    if (sql.includes('COUNT(*)::int AS total FROM devices WHERE is_deleted = false')) {
      return { rows: [{ total: 3 }] };
    }
    if (sql.includes('COUNT(*)::int AS total') && sql.includes('FROM alarm_logs') && sql.includes('Asia/Bangkok')) {
      return { rows: [{ total: 2 }] };
    }
    if (sql.includes("COUNT(*)::int AS total FROM alarm_logs WHERE status = 'active'")) {
      return { rows: [{ total: 0 }] };
    }
    if (sql.includes('FROM alarm_logs al') && sql.includes("status = 'active'")) {
      return { rows: [] };
    }
    if (sql.includes('INSERT INTO health_check_logs')) {
      healthLogs.push({
        health_check_id: values?.[0],
        status: values?.[1],
        message: values?.[2],
        channel_results: values?.[3],
      });
      return { rows: [] };
    }
    if (sql.includes('FROM notification_channels c') && sql.includes('WHERE c.is_deleted = false') && !sql.includes('WHERE c.id')) {
      return {
        rows: channels
          .filter((c) => !c.is_deleted)
          .map((c) => ({
            ...c,
            devices_count: c.id === 1 ? 1 : 0,
          })),
      };
    }
    if (sql.includes('FROM notification_channels c') && sql.includes('WHERE c.id = $1')) {
      const id = Number(values?.[0]);
      const channel = channels.find((c) => c.id === id && !c.is_deleted);
      return {
        rows: channel
          ? [{ ...channel, devices_count: channel.id === 1 ? 1 : 0 }]
          : [],
      };
    }
    if (sql.includes('INSERT INTO notification_channels')) {
      const row: Channel = {
        id: nextChannelId++,
        name: String(values?.[0]),
        type: String(values?.[1]),
        is_active: Boolean(values?.[2]),
        message_template: (values?.[3] as string | null) ?? null,
        description: (values?.[4] as string | null) ?? null,
        config: JSON.parse(String(values?.[5] || '{}')) as Record<string, unknown>,
        is_deleted: false,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };
      channels.push(row);
      return { rows: [{ id: row.id }] };
    }
    if (sql.includes('UPDATE notification_channels') && sql.includes('SET name = $2')) {
      const id = Number(values?.[0]);
      const channel = channels.find((c) => c.id === id && !c.is_deleted);
      if (!channel) return { rows: [] };
      channel.name = String(values?.[1]);
      channel.type = String(values?.[2]);
      channel.is_active = Boolean(values?.[3]);
      channel.message_template = (values?.[4] as string | null) ?? null;
      channel.description = (values?.[5] as string | null) ?? null;
      channel.config = JSON.parse(String(values?.[6] || '{}')) as Record<string, unknown>;
      channel.updated_at = new Date().toISOString();
      return { rows: [{ id }] };
    }
    if (sql.includes('UPDATE notification_channels') && sql.includes('is_deleted = true')) {
      const id = Number(values?.[0]);
      const channel = channels.find((c) => c.id === id && !c.is_deleted);
      if (!channel) return { rows: [] };
      channel.is_deleted = true;
      return { rows: [{ id }] };
    }
    if (sql.includes('FROM devices') && sql.includes('is_active = true') && sql.includes('metrics')) {
      return {
        rows: [
          {
            id: 10,
            name: 'Sensor A',
            location: 'Room 1',
            description: 'Temp sensor',
            metrics: [
              {
                key: 'temperature',
                label: 'Temperature',
                unit: '°C',
                triggers: [
                  {
                    value: 30,
                    operator: '>',
                    severity: 'warning',
                    enable: 'true',
                    channels: [1],
                    clear: { value: 28, operator: '<' },
                  },
                ],
              },
            ],
          },
        ],
      };
    }
    if (sql.includes('FROM alarm_log_dispatch ald')) {
      return {
        rows: [
          {
            id: 99,
            status: 'sent',
            sent_at: '2026-10-05T02:00:00.000Z',
            error: null,
            message: 'High temp',
            device_name: 'Sensor A',
          },
        ],
      };
    }
    if (sql.includes('FROM health_checks ORDER BY')) {
      return { rows: healthChecks };
    }
    if (sql.includes('FROM health_checks WHERE id = $1')) {
      const id = Number(values?.[0]);
      return { rows: healthChecks.filter((h) => h.id === id) };
    }
    if (sql.includes('INSERT INTO health_checks')) {
      const row: HealthCheck = {
        id: nextHealthId++,
        name: String(values?.[0]),
        is_active: Boolean(values?.[1]),
        schedule_type: String(values?.[2]),
        schedule_config: JSON.parse(String(values?.[3])) as HealthCheck['schedule_config'],
        timezone: 'Asia/Bangkok',
        channel_ids: JSON.parse(String(values?.[4])) as number[],
        rooms: [],
        message_template: String(values?.[5]),
        last_run_at: null,
        next_run_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };
      healthChecks.push(row);
      return { rows: [{ id: row.id }] };
    }
    if (sql.includes('UPDATE health_checks') && sql.includes('SET name = $2')) {
      const id = Number(values?.[0]);
      const row = healthChecks.find((h) => h.id === id);
      if (!row) return { rows: [] };
      row.name = String(values?.[1]);
      row.is_active = Boolean(values?.[2]);
      row.schedule_type = String(values?.[3]);
      row.schedule_config = JSON.parse(String(values?.[4])) as HealthCheck['schedule_config'];
      row.channel_ids = JSON.parse(String(values?.[5])) as number[];
      row.message_template = String(values?.[6]);
      row.updated_at = new Date().toISOString();
      return { rows: [] };
    }
    if (sql.includes('UPDATE health_checks SET next_run_at')) {
      const id = Number(values?.[0]);
      const row = healthChecks.find((h) => h.id === id);
      if (row) row.next_run_at = String(values?.[1]);
      return { rows: [] };
    }
    if (sql.includes('DELETE FROM health_checks')) {
      const id = Number(values?.[0]);
      const idx = healthChecks.findIndex((h) => h.id === id);
      if (idx < 0) return { rows: [] };
      healthChecks.splice(idx, 1);
      return { rows: [{ id }] };
    }
    if (sql.includes('FROM notification_channels') && sql.includes('ANY($1::int[])')) {
      const ids = (values?.[0] as number[]) ?? [];
      return {
        rows: channels.filter((c) => ids.includes(c.id) && !c.is_deleted),
      };
    }
    throw new Error(`Unexpected SQL in test: ${sql.slice(0, 160)}`);
  };

  return { query } as Queryable;
}

describe('wave 14 notifications', () => {
  let app: FastifyInstance | undefined;
  const originalFetch = globalThis.fetch;

  afterEach(async () => {
    globalThis.fetch = originalFetch;
    resetRefreshTokens();
    if (app) {
      await app.close();
      app = undefined;
    }
  });

  async function authed() {
    app = await buildApp({ pool: makeDb(), authSecret: SECRET });
    await app.ready();
    const token = signAccessToken(1, SECRET);
    return token;
  }

  it('lists channels and summary', async () => {
    const token = await authed();
    const list = await app!.inject({
      method: 'GET',
      url: '/api/v1/notification-channels',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(list.statusCode).toBe(200);
    const listBody = list.json() as { data: Array<{ id: number; name: string }> };
    expect(listBody.data[0]?.name).toBe('Ops LINE');

    const summary = await app!.inject({
      method: 'GET',
      url: '/api/v1/notification-channels/summary',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(summary.statusCode).toBe(200);
    const summaryBody = summary.json() as {
      data: { totalChannels: number; activeChannels: number; todayAlarms: number };
    };
    expect(summaryBody.data.totalChannels).toBe(1);
    expect(summaryBody.data.activeChannels).toBe(1);
    expect(summaryBody.data.todayAlarms).toBe(2);
  });

  it('creates updates and soft-deletes a channel', async () => {
    const token = await authed();
    const created = await app!.inject({
      method: 'POST',
      url: '/api/v1/notification-channels',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Mail Ops',
        type: 'email',
        isActive: true,
        messageTemplate: 'Alert {{metric}}',
        description: 'Mail',
        config: {
          smtp_host: 'smtp.example.com',
          smtp_port: 587,
          to: 'ops@example.com',
        },
      },
    });
    expect(created.statusCode).toBe(201);
    const createdBody = created.json() as { data: { id: number; type: string } };
    expect(createdBody.data.type).toBe('email');

    const updated = await app!.inject({
      method: 'PUT',
      url: `/api/v1/notification-channels/${createdBody.data.id}`,
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Mail Ops 2',
        type: 'email',
        isActive: false,
        messageTemplate: 'Alert {{metric}}',
        description: 'Mail',
        config: { smtp_host: 'smtp.example.com', to: 'ops@example.com' },
      },
    });
    expect(updated.statusCode).toBe(200);
    expect((updated.json() as { data: { name: string; isActive: boolean } }).data.name).toBe('Mail Ops 2');
    expect((updated.json() as { data: { isActive: boolean } }).data.isActive).toBe(false);

    const deleted = await app!.inject({
      method: 'DELETE',
      url: `/api/v1/notification-channels/${createdBody.data.id}`,
      headers: { authorization: `Bearer ${token}` },
    });
    expect(deleted.statusCode).toBe(200);
  });

  it('returns devices messages and template preview', async () => {
    const token = await authed();
    const devices = await app!.inject({
      method: 'GET',
      url: '/api/v1/notification-channels/1/devices',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(devices.statusCode).toBe(200);
    const deviceBody = devices.json() as { data: Array<{ deviceName: string; metricLabel: string }> };
    expect(deviceBody.data[0]?.deviceName).toBe('Sensor A');

    const messages = await app!.inject({
      method: 'GET',
      url: '/api/v1/notification-channels/1/messages',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(messages.statusCode).toBe(200);
    expect((messages.json() as { data: Array<{ status: string }> }).data[0]?.status).toBe('sent');

    const template = await app!.inject({
      method: 'GET',
      url: '/api/v1/notification-channels/1/template',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(template.statusCode).toBe(200);
    expect((template.json() as { data: { preview: string } }).data.preview).toContain('Temperature');
  });

  it('proxies test-send to alarm-engine', async () => {
    const token = await authed();
    globalThis.fetch = vi.fn(async () =>
      new Response(
        JSON.stringify({
          success: true,
          message: 'Test message sent successfully',
          channel: 'Ops LINE',
          type: 'line_oa',
        }),
        { status: 200, headers: { 'Content-Type': 'application/json' } },
      ),
    ) as typeof fetch;

    const response = await app!.inject({
      method: 'POST',
      url: '/api/v1/notification-channels/1/test-send',
      headers: { authorization: `Bearer ${token}` },
      payload: { message: 'Hello test' },
    });
    expect(response.statusCode).toBe(200);
    const body = response.json() as { data: { success: boolean; message: string } };
    expect(body.data.success).toBe(true);
    expect(body.data.message).toContain('sent successfully');
    expect(globalThis.fetch).toHaveBeenCalled();
    const call = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(String(call[0])).toContain('/api/send-test');
    expect(JSON.parse(String((call[1] as RequestInit).body))).toMatchObject({
      channelId: 1,
      message: 'Hello test',
    });
  });

  it('returns PHP-style error when alarm worker is down', async () => {
    const token = await authed();
    globalThis.fetch = vi.fn(async () => {
      throw new TypeError('fetch failed');
    }) as typeof fetch;

    const response = await app!.inject({
      method: 'POST',
      url: '/api/v1/notification-channels/1/test-send',
      headers: { authorization: `Bearer ${token}` },
      payload: { message: 'Hello test' },
    });
    expect(response.statusCode).toBe(200);
    const body = response.json() as {
      data: { success: boolean; error: string; channel: string; type: string };
    };
    expect(body.data.success).toBe(false);
    expect(body.data.error).toBe('Failed to connect to alarm worker service');
    expect(body.data.channel).toBe('Ops LINE');
    expect(body.data.type).toBe('line_oa');
  });

  it('saves previews and sends health checks', async () => {
    const token = await authed();
    globalThis.fetch = vi.fn(async (url) => {
      expect(String(url)).toContain('api.line.me');
      return new Response(JSON.stringify({}), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    }) as typeof fetch;

    const list = await app!.inject({
      method: 'GET',
      url: '/api/v1/notification-health-checks',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(list.statusCode).toBe(200);
    const listBody = list.json() as {
      data: { checks: Array<{ name: string }>; defaultTemplate: string };
    };
    expect(listBody.data.checks[0]?.name).toBe('Morning');
    expect(listBody.data.defaultTemplate).toContain('{status}');

    const preview = await app!.inject({
      method: 'POST',
      url: '/api/v1/notification-health-checks/preview',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Morning',
        scheduleType: 'daily',
        scheduleTime: '08:00',
        isActive: true,
        channelIds: [1],
        messageTemplate: '{name}\nStatus: {status}',
      },
    });
    expect(preview.statusCode).toBe(200);
    expect((preview.json() as { data: { message: string } }).data.message).toContain('System Normal');

    const saved = await app!.inject({
      method: 'POST',
      url: '/api/v1/notification-health-checks',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Evening',
        scheduleType: 'weekly',
        scheduleTime: '18:00',
        scheduleWeekday: 5,
        isActive: true,
        channelIds: [1],
        messageTemplate: '',
      },
    });
    expect(saved.statusCode).toBe(200);
    expect((saved.json() as { data: { success: boolean; id: number } }).data.success).toBe(true);

    const sent = await app!.inject({
      method: 'POST',
      url: '/api/v1/notification-health-checks/1/send',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(sent.statusCode).toBe(200);
    expect((sent.json() as { data: { success: boolean } }).data.success).toBe(true);
    expect(globalThis.fetch).toHaveBeenCalled();
    const call = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(String(call[0])).toContain('api.line.me/v2/bot/message/push');
  });
});
