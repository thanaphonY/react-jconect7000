import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it } from 'vitest';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

async function appWith(query: Queryable['query']): Promise<FastifyInstance> {
  return buildApp({
    pool: { query },
    authSecret: 'test-secret-value!!',
    logger: false,
  });
}

describe('GET /api/v1/health', () => {
  let app: FastifyInstance | undefined;

  afterEach(async () => {
    await app?.close();
  });

  it('returns ok when SELECT 1 succeeds', async () => {
    const queries: string[] = [];
    app = await appWith(async (sql) => {
      queries.push(sql);
      return { rows: [{ '?column?': 1 }] };
    });

    const response = await app.inject({
      method: 'GET',
      url: '/api/v1/health',
    });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toEqual({ data: { status: 'ok' } });
    expect(queries).toEqual(['SELECT 1']);
  });

  it('returns DB_UNAVAILABLE when the database query fails', async () => {
    app = await appWith(async () => {
      throw new Error('connect ECONNREFUSED 127.0.0.1:5432');
    });

    const response = await app.inject({
      method: 'GET',
      url: '/api/v1/health',
    });

    expect(response.statusCode).toBe(503);
    expect(response.json()).toEqual({
      error: {
        code: 'DB_UNAVAILABLE',
        message: 'Database is unavailable',
      },
    });
  });

  it('allows the Vite dev origins with credentials', async () => {
    app = await appWith(async () => ({ rows: [] }));

    for (const origin of ['http://127.0.0.1:5173', 'http://localhost:5173']) {
      const response = await app.inject({
        method: 'GET',
        url: '/api/v1/health',
        headers: { origin },
      });

      expect(response.statusCode).toBe(200);
      expect(response.headers['access-control-allow-origin']).toBe(origin);
      expect(response.headers['access-control-allow-credentials']).toBe('true');
    }
  });
});
