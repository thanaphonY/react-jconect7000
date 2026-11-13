import bcrypt from 'bcryptjs';
import type { FastifyInstance } from 'fastify';
import { afterEach, describe, expect, it } from 'vitest';
import { resetRefreshTokens } from '../src/auth/tokens.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';

interface StoredUser {
  ia_users_id: number;
  name: string;
  email: string;
  password: string;
  user_type: string;
  profile_pic: string | null;
  is_deleted: string;
}

async function appWith(users: StoredUser[], permissionData: string | null = null): Promise<FastifyInstance> {
  const query: Queryable['query'] = async (sql, values) => {
    if (sql.includes('FROM ia_users') && sql.includes('name = $1')) {
      const login = String(values?.[0] ?? '');
      const user = users.find((row) => row.is_deleted === '0' && (row.name === login || row.email === login));
      return { rows: user ? [user] : [] };
    }
    if (sql.includes('FROM ia_users') && sql.includes('ia_users_id = $1')) {
      const id = Number(values?.[0]);
      const user = users.find((row) => row.ia_users_id === id && row.is_deleted === '0');
      return { rows: user ? [user] : [] };
    }
    if (sql.includes('FROM ia_permission')) {
      return { rows: permissionData ? [{ data: permissionData }] : [] };
    }
    if (sql.includes('FROM ia_setting')) {
      return { rows: [{ value: 'JConnect' }] };
    }
    return { rows: [] };
  };

  return buildApp({ pool: { query }, authSecret: SECRET, logger: false });
}

describe('auth', () => {
  let app: FastifyInstance | undefined;

  afterEach(async () => {
    resetRefreshTokens();
    await app?.close();
  });

  it('returns the website name for the login screen', async () => {
    app = await appWith([]);
    const response = await app.inject({ method: 'GET', url: '/api/v1/branding' });
    expect(response.statusCode).toBe(200);
    const data = response.json().data as {
      website: string;
      logo: string;
      favicon: string;
      register_allowed: string;
    };
    expect(data.website).toBe('JConnect');
    expect(data.register_allowed).toBe('0');
    // Empty DB branding falls back to files that exist under frontend/assets/images
    expect(data.logo.length).toBeGreaterThan(0);
    expect(data.favicon.length).toBeGreaterThan(0);
    expect(['logo.png', 'Logo.png']).toContain(data.logo);
    expect(['Logo.png', 'logo.png', 'favicon.png', 'favicon.ico']).toContain(data.favicon);
  });

  it('logs in with a PHP $2y$ hash and returns the admin menu', async () => {
    const hash = (await bcrypt.hash('123456', 4)).replace(/^\$2a\$/, '$2y$');
    app = await appWith([
      {
        ia_users_id: 1,
        name: 'Supperadmin',
        email: 'admin@example.com',
        password: hash,
        user_type: 'admin',
        profile_pic: null,
        is_deleted: '0',
      },
    ]);

    const response = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: { email: 'Supperadmin', password: '123456' },
    });

    expect(response.statusCode).toBe(200);
    const body = response.json();
    expect(body.data.user.name).toBe('Supperadmin');
    expect(body.data.accessToken).toEqual(expect.any(String));
    expect(body.data.menu.map((item: { path: string }) => item.path)).toEqual([
      '/home',
      '/dashboard',
      '/device',
      '/notification_manager',
      '/profile',
      '/user/userlist',
      '/eventlogs',
      '/alarmlogs',
      '/reports',
      '/devicetemplate',
      '/setting',
    ]);
    expect(response.headers['set-cookie']).toEqual(expect.stringContaining('refresh_token='));
    expect(response.headers['set-cookie']).toEqual(expect.stringContaining('HttpOnly'));
    expect(response.headers['set-cookie']).toEqual(expect.stringContaining('SameSite=Lax'));
  });

  it('rejects a wrong password', async () => {
    const hash = await bcrypt.hash('123456', 4);
    app = await appWith([
      {
        ia_users_id: 1,
        name: 'Supperadmin',
        email: 'admin@example.com',
        password: hash,
        user_type: 'admin',
        profile_pic: null,
        is_deleted: '0',
      },
    ]);

    const response = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: { email: 'Supperadmin', password: 'nope' },
    });

    expect(response.statusCode).toBe(401);
    expect(response.json()).toEqual({
      error: { code: 'INVALID_CREDENTIALS', message: 'Invalid details' },
    });
  });

  it('hides modules the permission row does not grant', async () => {
    const hash = await bcrypt.hash('123456', 4);
    app = await appWith(
      [
        {
          ia_users_id: 2,
          name: 'viewer',
          email: 'viewer@example.com',
          password: hash,
          user_type: 'viewer',
          profile_pic: null,
          is_deleted: '0',
        },
      ],
      JSON.stringify({ devices: { own_read: '1' } }),
    );

    const response = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: { email: 'viewer@example.com', password: '123456' },
    });

    expect(response.statusCode).toBe(200);
    expect(response.json().data.menu.map((item: { labelKey: string }) => item.labelKey)).toEqual([
      'home',
      'devices',
      'profile',
    ]);
  });

  it('refreshes from the cookie and logout clears it', async () => {
    const hash = await bcrypt.hash('123456', 4);
    app = await appWith([
      {
        ia_users_id: 1,
        name: 'Supperadmin',
        email: 'admin@example.com',
        password: hash,
        user_type: 'admin',
        profile_pic: null,
        is_deleted: '0',
      },
    ]);

    const login = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: { email: 'Supperadmin', password: '123456' },
    });
    const cookie = String(login.headers['set-cookie']).split(';')[0];

    const refreshed = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/refresh',
      headers: { cookie },
    });
    expect(refreshed.statusCode).toBe(200);
    expect(refreshed.json().data.user.name).toBe('Supperadmin');

    const logout = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/logout',
      headers: { cookie },
    });
    expect(logout.statusCode).toBe(200);
    expect(String(logout.headers['set-cookie'])).toEqual(expect.stringContaining('refresh_token=;'));

    const again = await app.inject({
      method: 'POST',
      url: '/api/v1/auth/refresh',
      headers: { cookie },
    });
    expect(again.statusCode).toBe(401);
  });
});
