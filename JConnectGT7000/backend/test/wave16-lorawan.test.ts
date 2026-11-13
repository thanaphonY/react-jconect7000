import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { signAccessToken, resetRefreshTokens } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

type AppRow = {
  id: number;
  application_id: string;
  name: string;
  description: string;
  tenant_id: string;
  created_at: string | Date;
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

  let nextId = 3;
  const apps: AppRow[] = [
    {
      id: 2,
      application_id: 'ee8c80f1-2587-48c9-a177-ea59595c6dc2',
      name: 'External Network Room',
      description: 'Internal Network Room Sensor',
      tenant_id: 'a8bfa40a-9bec-4a6d-bd4f-d406982ce39d',
      created_at: '2025-07-14 17:18:16',
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

    if (sql.includes('FROM lorawan_applications') && sql.includes('ORDER BY name ASC')) {
      return {
        rows: [...apps].sort((a, b) => a.name.localeCompare(b.name)),
      };
    }

    if (sql.includes('DELETE FROM lorawan_applications')) {
      const id = String(values?.[0]);
      const idx = apps.findIndex((a) => a.application_id === id);
      if (idx >= 0) apps.splice(idx, 1);
      return { rows: [] };
    }

    if (sql.includes('FROM lorawan_applications') && sql.includes('WHERE application_id = $1')) {
      const id = String(values?.[0] ?? '');
      const row = apps.find((a) => a.application_id === id);
      return { rows: row ? [row] : [] };
    }

    if (sql.includes('INSERT INTO lorawan_applications')) {
      const row: AppRow = {
        id: nextId++,
        application_id: String(values?.[0]),
        name: String(values?.[1]),
        description: String(values?.[2] ?? ''),
        tenant_id: String(values?.[3]),
        created_at: '2026-10-05 10:00:00',
      };
      apps.push(row);
      return { rows: [row] };
    }

    if (sql.includes('UPDATE lorawan_applications')) {
      const id = String(values?.[0]);
      const row = apps.find((a) => a.application_id === id);
      if (!row) return { rows: [] };
      row.name = String(values?.[1]);
      row.description = String(values?.[2] ?? '');
      return { rows: [row] };
    }

    throw new Error(`Unexpected SQL: ${sql}`);
  };

  return { query, apps };
}

describe('wave 16 lorawan create/edit via ChirpStack', () => {
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

  it('lists local applications with tenant and created fields', async () => {
    const { token } = await start();
    const res = await app!.inject({
      method: 'GET',
      url: '/api/v1/lorawan/applications',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(res.statusCode).toBe(200);
    const body = res.json() as {
      data: Array<{
        id: number;
        applicationId: string;
        name: string;
        tenantId: string;
        createdAt: string;
      }>;
    };
    expect(body.data).toHaveLength(1);
    expect(body.data[0]).toMatchObject({
      id: 2,
      applicationId: 'ee8c80f1-2587-48c9-a177-ea59595c6dc2',
      name: 'External Network Room',
      tenantId: 'a8bfa40a-9bec-4a6d-bd4f-d406982ce39d',
      createdAt: '2025-07-14 17:18:16',
    });
  });

  it('formats Date created_at as Asia/Bangkok local, not UTC ISO', async () => {
    const { token, db } = await start();
    // Same instant as PHP seed `2025-07-14 17:18:16` Bangkok (= 10:18:16 UTC).
    db.apps[0]!.created_at = new Date('2025-07-14T10:18:16.000Z');

    const res = await app!.inject({
      method: 'GET',
      url: '/api/v1/lorawan/applications',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(res.statusCode).toBe(200);
    const body = res.json() as { data: Array<{ createdAt: string }> };
    expect(body.data[0]?.createdAt).toBe('2025-07-14 17:18:16');
  });

  it('creates an application through ChirpStack then saves locally', async () => {
    const { token, db } = await start();
    const fetchMock = vi.fn(async (input: string | URL | Request, init?: RequestInit) => {
      const url = String(input);
      expect(url).toContain('/applications');
      expect(init?.method).toBe('POST');
      const body = JSON.parse(String(init?.body)) as {
        application: { name: string; description: string; tenantId: string };
      };
      expect(body.application).toEqual({
        name: 'Room A',
        description: 'Sensors',
        tenantId: 'a8bfa40a-9bec-4a6d-bd4f-d406982ce39d',
      });
      return new Response(JSON.stringify({ id: '11111111-2222-3333-4444-555555555555' }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    });
    globalThis.fetch = fetchMock as typeof fetch;

    const res = await app!.inject({
      method: 'POST',
      url: '/api/v1/lorawan/applications',
      headers: {
        authorization: `Bearer ${token}`,
        'content-type': 'application/json',
      },
      payload: {
        name: 'Room A',
        description: 'Sensors',
        tenantId: 'a8bfa40a-9bec-4a6d-bd4f-d406982ce39d',
      },
    });

    expect(res.statusCode).toBe(201);
    const body = res.json() as { data: { applicationId: string; name: string } };
    expect(body.data.applicationId).toBe('11111111-2222-3333-4444-555555555555');
    expect(body.data.name).toBe('Room A');
    expect(db.apps).toHaveLength(2);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('updates an application through ChirpStack then updates local row', async () => {
    const { token, db } = await start();
    const applicationId = 'ee8c80f1-2587-48c9-a177-ea59595c6dc2';
    const fetchMock = vi.fn(async (_input: string | URL | Request, init?: RequestInit) => {
      expect(init?.method).toBe('PUT');
      const body = JSON.parse(String(init?.body)) as {
        application: { name: string; description: string; tenantId: string };
      };
      expect(body.application.tenantId).toBe('a8bfa40a-9bec-4a6d-bd4f-d406982ce39d');
      expect(body.application.name).toBe('External Renamed');
      return new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } });
    });
    globalThis.fetch = fetchMock as typeof fetch;

    const res = await app!.inject({
      method: 'PUT',
      url: `/api/v1/lorawan/applications/${applicationId}`,
      headers: {
        authorization: `Bearer ${token}`,
        'content-type': 'application/json',
      },
      payload: { name: 'External Renamed', description: 'Updated desc' },
    });

    expect(res.statusCode).toBe(200);
    expect(db.apps[0]?.name).toBe('External Renamed');
    expect(db.apps[0]?.description).toBe('Updated desc');
  });

  it('deletes an application through ChirpStack then removes local row', async () => {
    const { token, db } = await start();
    const applicationId = 'ee8c80f1-2587-48c9-a177-ea59595c6dc2';
    globalThis.fetch = vi.fn(async () => new Response('', { status: 200 })) as typeof fetch;

    const res = await app!.inject({
      method: 'DELETE',
      url: `/api/v1/lorawan/applications/${applicationId}`,
      headers: { authorization: `Bearer ${token}` },
    });

    expect(res.statusCode).toBe(200);
    expect(db.apps).toHaveLength(0);
  });

  it('proxies device profiles from ChirpStack by tenantId', async () => {
    const { token } = await start();
    globalThis.fetch = vi.fn(async (input: string | URL | Request) => {
      const url = String(input);
      expect(url).toContain('device-profiles');
      expect(url).toContain('tenantId=a8bfa40a-9bec-4a6d-bd4f-d406982ce39d');
      return new Response(
        JSON.stringify({
          result: [{ id: 'profile-1', name: 'OTAA' }],
          totalCount: 1,
        }),
        { status: 200, headers: { 'Content-Type': 'application/json' } },
      );
    }) as typeof fetch;

    const res = await app!.inject({
      method: 'GET',
      url: '/api/v1/lorawan/device-profiles?tenantId=a8bfa40a-9bec-4a6d-bd4f-d406982ce39d',
      headers: { authorization: `Bearer ${token}` },
    });

    expect(res.statusCode).toBe(200);
    const body = res.json() as {
      data: { result: Array<{ id: string; name: string }>; totalCount: number };
    };
    expect(body.data.result).toEqual([{ id: 'profile-1', name: 'OTAA' }]);
    expect(body.data.totalCount).toBe(1);
  });

  it('returns CHIRPSTACK_ERROR when create fails upstream', async () => {
    const { token } = await start();
    globalThis.fetch = vi.fn(async () =>
      new Response(JSON.stringify({ message: 'unauthorized' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      }),
    ) as typeof fetch;

    const res = await app!.inject({
      method: 'POST',
      url: '/api/v1/lorawan/applications',
      headers: {
        authorization: `Bearer ${token}`,
        'content-type': 'application/json',
      },
      payload: {
        name: 'Fail',
        description: '',
        tenantId: 'a8bfa40a-9bec-4a6d-bd4f-d406982ce39d',
      },
    });

    expect(res.statusCode).toBe(502);
    const body = res.json() as { error: { code: string; message: string } };
    expect(body.error.code).toBe('CHIRPSTACK_ERROR');
    expect(body.error.message).toBe('Failed to create application');
  });

  it('returns Failed to fetch device profiles when ChirpStack is down', async () => {
    const { token } = await start();
    globalThis.fetch = vi.fn(async () => {
      throw new Error('fetch failed');
    }) as typeof fetch;

    const res = await app!.inject({
      method: 'GET',
      url: '/api/v1/lorawan/device-profiles?tenantId=a8bfa40a-9bec-4a6d-bd4f-d406982ce39d',
      headers: { authorization: `Bearer ${token}` },
    });

    expect(res.statusCode).toBe(502);
    const body = res.json() as { error: { code: string; message: string } };
    expect(body.error.code).toBe('CHIRPSTACK_ERROR');
    expect(body.error.message).toBe('Failed to fetch device profiles');
  });
});
