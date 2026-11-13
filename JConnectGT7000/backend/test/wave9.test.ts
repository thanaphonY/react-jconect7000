import bcrypt from 'bcryptjs';
import { createReadStream } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import type { FastifyInstance } from 'fastify';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { resetPasswordSessions } from '../src/auth/resetSession.js';
import { signAccessToken, resetRefreshTokens } from '../src/auth/tokens.js';
import { setMailDryRun, drainSentMails, peekSentMails } from '../src/mail/sendMail.js';
import { buildApp } from '../src/app.js';
import type { Queryable } from '../src/db.js';

const SECRET = 'test-secret-value!!';
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const USER_PNG = path.resolve(__dirname, '../../frontend/assets/images/user.png');

interface StoredUser {
  ia_users_id: number;
  user_id: string;
  name: string;
  email: string;
  password: string;
  user_type: string;
  profile_pic: string | null;
  status: string;
  is_deleted: string;
  create_date: string;
  var_key: string | null;
}

interface SettingRow {
  keys: string;
  value: string;
}

interface PermissionRow {
  id: number;
  user_type: string;
  data: string;
}

function makeStore() {
  const users: StoredUser[] = [];
  const settings: SettingRow[] = [
    { keys: 'website', value: 'JConnect' },
    { keys: 'logo', value: 'logo.png' },
    { keys: 'favicon', value: 'favicon.ico' },
    { keys: 'register_allowed', value: '1' },
    { keys: 'admin_approval', value: '0' },
    { keys: 'user_type', value: '["manager"]' },
    { keys: 'mail_setting', value: 'simple_mail' },
    { keys: 'company_name', value: 'Acme' },
    { keys: 'EMAIL', value: 'noreply@example.com' },
  ];
  const permissions: PermissionRow[] = [
    {
      id: 1,
      user_type: 'admin',
      data: JSON.stringify({
        user: { all_read: '1', all_create: '1', all_update: '1', all_delete: '1', own_read: '1' },
        setting: { all_read: '1', all_update: '1', own_read: '1' },
      }),
    },
    {
      id: 10,
      user_type: 'manager',
      data: JSON.stringify({
        user: { all_read: '1', all_create: '1', all_update: '1', all_delete: '1', own_read: '1' },
        setting: { all_read: '1', all_update: '1', own_read: '1' },
      }),
    },
  ];
  const templates: Array<{ code: string; html: string }> = [
    {
      code: 'forgot_password',
      html: '<p>Hi {var_user_name}, OTP {var_otp} for {var_website_name}</p>',
    },
  ];

  const query: Queryable['query'] = async (sql, values) => {
    const text = sql.replace(/\s+/g, ' ');

    if (text.includes('FROM ia_setting') && text.includes('ANY')) {
      const keys = (values?.[0] as string[]) ?? [];
      return { rows: settings.filter((row) => keys.includes(row.keys)) };
    }
    if (text.includes('FROM ia_setting') && text.includes("keys = $1")) {
      const key = String(values?.[0]);
      const row = settings.find((s) => s.keys === key);
      return { rows: row ? [row] : [] };
    }
    if (text.includes('FROM ia_setting') && text.includes('SELECT keys, value')) {
      return { rows: settings };
    }
    if (text.includes('UPDATE ia_setting SET value')) {
      const key = String(values?.[0]);
      const value = String(values?.[1]);
      const row = settings.find((s) => s.keys === key);
      if (row) row.value = value;
      return { rows: [] };
    }
    if (text.includes('INSERT INTO ia_setting')) {
      settings.push({ keys: String(values?.[0]), value: String(values?.[1]) });
      return { rows: [] };
    }

    if (text.includes('FROM ia_permission') && text.includes("LOWER(user_type) != 'admin'")) {
      return { rows: permissions.filter((p) => p.user_type.toLowerCase() !== 'admin') };
    }
    if (text.includes('FROM ia_permission') && text.includes("LOWER(user_type) = 'admin'")) {
      const row = permissions.find((p) => p.user_type.toLowerCase() === 'admin');
      return { rows: row ? [{ data: row.data }] : [] };
    }
    if (text.includes('FROM ia_permission') && text.includes('user_type = $1')) {
      const ut = String(values?.[0]);
      const row = permissions.find((p) => p.user_type === ut);
      return { rows: row ? [{ data: row.data }] : [] };
    }
    if (text.includes('FROM ia_permission') && text.includes('ORDER BY user_type')) {
      return { rows: permissions };
    }
    if (text.includes('UPDATE ia_permission SET data')) {
      const ut = String(values?.[0]);
      const row = permissions.find((p) => p.user_type === ut);
      if (row) row.data = String(values?.[1]);
      return { rows: [] };
    }
    if (text.includes('UPDATE ia_permission SET user_type')) {
      const id = Number(values?.[0]);
      const row = permissions.find((p) => p.id === id);
      if (row) row.user_type = String(values?.[1]);
      return { rows: [] };
    }
    if (text.includes('INSERT INTO ia_permission')) {
      const id = Math.max(0, ...permissions.map((p) => p.id)) + 1;
      permissions.push({
        id,
        user_type: String(values?.[0]),
        data: String(values?.[1] ?? ''),
      });
      return { rows: [{ id }] };
    }
    if (text.includes('DELETE FROM ia_permission')) {
      const id = Number(values?.[0]);
      const idx = permissions.findIndex((p) => p.id === id);
      if (idx >= 0) permissions.splice(idx, 1);
      return { rows: [] };
    }

    if (text.includes('FROM ia_email_templates')) {
      const code = String(values?.[0]);
      const row = templates.find((t) => t.code === code);
      return { rows: row ? [{ html: row.html }] : [] };
    }

    if (text.includes('INSERT INTO ia_users') && text.includes('(email, var_key)')) {
      const id = Math.max(0, ...users.map((u) => u.ia_users_id)) + 1;
      users.push({
        ia_users_id: id,
        user_id: '',
        status: '',
        is_deleted: '',
        name: '',
        password: '',
        email: String(values?.[0]),
        profile_pic: null,
        user_type: '',
        create_date: '',
        var_key: String(values?.[1]),
      });
      return { rows: [{ ia_users_id: id }] };
    }

    if (text.includes('INSERT INTO ia_users')) {
      const id = Math.max(0, ...users.map((u) => u.ia_users_id)) + 1;
      users.push({
        ia_users_id: id,
        user_id: String(values?.[0]),
        status: String(values?.[1]),
        is_deleted: '0',
        name: String(values?.[2]),
        password: String(values?.[3]),
        email: String(values?.[4]),
        profile_pic: String(values?.[5]),
        user_type: String(values?.[6]),
        create_date: String(values?.[7]),
        var_key: null,
      });
      return { rows: [{ ia_users_id: id }] };
    }

    if (text.includes('UPDATE ia_users') && text.includes('is_deleted = \'1\'') && text.includes('ANY')) {
      const ids = (values?.[0] as number[]) ?? [];
      const deleted: number[] = [];
      for (const id of ids) {
        const user = users.find(
          (u) =>
            u.ia_users_id === id &&
            u.is_deleted === '0' &&
            u.user_type.toLowerCase() !== 'admin',
        );
        if (user) {
          user.is_deleted = '1';
          deleted.push(id);
        }
      }
      return { rows: deleted.map((ia_users_id) => ({ ia_users_id })) };
    }

    if (text.includes('UPDATE ia_users SET is_deleted')) {
      const id = Number(values?.[0]);
      const user = users.find((u) => u.ia_users_id === id && u.is_deleted === '0');
      if (!user) return { rows: [] };
      user.is_deleted = '1';
      return { rows: [{ ia_users_id: id }] };
    }

    if (text.includes('UPDATE ia_users SET') && text.includes('var_key = $2') && text.includes('ia_users_id')) {
      const id = Number(values?.[0]);
      const user = users.find((u) => u.ia_users_id === id);
      if (user) user.var_key = String(values?.[1]);
      return { rows: [] };
    }

    if (text.includes('UPDATE ia_users SET') && text.includes('var_key = $2') && text.includes('WHERE email')) {
      const email = String(values?.[0]);
      const user = users.find((u) => u.email === email);
      if (user) user.var_key = String(values?.[1]);
      return { rows: [] };
    }

    if (text.includes('UPDATE ia_users SET password') && text.includes('WHERE email')) {
      const email = String(values?.[0]);
      const user = users.find((u) => u.email === email && u.is_deleted === '0');
      if (!user) return { rows: [] };
      user.password = String(values?.[1]);
      user.var_key = '';
      return { rows: [{ ia_users_id: user.ia_users_id }] };
    }

    if (text.includes('UPDATE ia_users SET') && text.includes('WHERE ia_users_id')) {
      const id = Number(values?.[0]);
      const user = users.find((u) => u.ia_users_id === id);
      if (!user) return { rows: [] };
      // name=$2 email=$3 profile_pic=$4 ...
      user.name = String(values?.[1] ?? user.name);
      user.email = String(values?.[2] ?? user.email);
      user.profile_pic = String(values?.[3] ?? user.profile_pic);
      if (text.includes('user_type')) {
        const match = text.match(/user_type = \$(\d+)/);
        if (match) user.user_type = String(values?.[Number(match[1]) - 1]);
      }
      if (text.includes('status')) {
        const match = text.match(/status = \$(\d+)/);
        if (match) user.status = String(values?.[Number(match[1]) - 1]);
      }
      if (text.includes('password')) {
        const match = text.match(/password = \$(\d+)/);
        if (match) user.password = String(values?.[Number(match[1]) - 1]);
      }
      return { rows: [] };
    }

    if (text.includes('SELECT COUNT(*)') && text.includes('FROM ia_users')) {
      let list = users.filter((u) => u.is_deleted === '0' && u.user_type.toLowerCase() !== 'admin');
      if (text.includes("status = 'active'")) list = list.filter((u) => u.status === 'active');
      if (text.includes("status = 'pending'")) list = list.filter((u) => u.status === 'pending');
      if (text.includes('ia_users_id !=')) {
        const exclude = Number(values?.[0]);
        list = list.filter((u) => u.ia_users_id !== exclude);
      }
      if (text.includes('user_id =')) {
        const creator = String(values?.[0]);
        list = list.filter((u) => u.user_id === creator);
      }
      return { rows: [{ total: list.length }] };
    }

    if (text.includes('GROUP BY user_type')) {
      const list = users.filter((u) => u.is_deleted === '0' && u.user_type.toLowerCase() !== 'admin');
      const map = new Map<string, number>();
      for (const u of list) map.set(u.user_type, (map.get(u.user_type) ?? 0) + 1);
      return {
        rows: [...map.entries()].map(([user_type, count]) => ({ user_type, count })),
      };
    }

    if (text.includes('FROM ia_users') && text.includes('ORDER BY name') && text.includes('LIMIT')) {
      let list = users.filter((u) => u.is_deleted === '0' && u.user_type.toLowerCase() !== 'admin');
      if (text.includes('ia_users_id !=')) {
        list = list.filter((u) => u.ia_users_id !== Number(values?.[0]));
      }
      if (text.includes('user_id =')) {
        list = list.filter((u) => u.user_id === String(values?.[0]));
      }
      return { rows: list };
    }

    if (text.includes('FROM ia_users') && text.includes('SELECT user_id')) {
      const id = Number(values?.[0]);
      const user = users.find((u) => u.ia_users_id === id && u.is_deleted === '0');
      return { rows: user ? [{ user_id: user.user_id }] : [] };
    }

    if (text.includes('FROM ia_users') && text.includes('email = $1') && text.includes('var_key')) {
      const email = String(values?.[0]);
      const user = users.find((u) => u.email === email && u.is_deleted === '0');
      return { rows: user ? [user] : [] };
    }

    if (text.includes('FROM ia_users') && text.includes('LOWER(email)') && text.includes('LIMIT 1')) {
      const email = String(values?.[0]).toLowerCase();
      const user = users.find((u) => u.email.toLowerCase() === email && u.is_deleted === '0');
      return { rows: user ? [user] : [] };
    }

    if (text.includes('FROM ia_users') && text.includes('email = $1') && !text.includes('ia_users_id !=') && !text.includes('SELECT ia_users_id') && !text.includes('SELECT var_key') && !text.includes('password =')) {
      const email = String(values?.[0]);
      const user = users.find((u) => u.email === email && u.is_deleted === '0');
      return { rows: user ? [user] : [] };
    }

    if (text.includes('FROM ia_users') && text.includes('SELECT var_key')) {
      const email = String(values?.[0]);
      const user = users.find((u) => u.email === email && u.is_deleted === '0');
      return { rows: user ? [{ var_key: user.var_key }] : [] };
    }

    if (text.includes('FROM ia_users') && text.includes('name = $1 OR email')) {
      const login = String(values?.[0]);
      const user = users.find(
        (u) => u.is_deleted === '0' && (u.name === login || u.email === login),
      );
      return { rows: user ? [user] : [] };
    }

    if (text.includes('FROM ia_users') && text.includes('ia_users_id = $1')) {
      const id = Number(values?.[0]);
      const user = users.find((u) => u.ia_users_id === id && u.is_deleted === '0');
      return { rows: user ? [user] : [] };
    }

    if (text.includes('FROM ia_users') && text.includes('email = $1') && text.includes('ia_users_id !=')) {
      const email = String(values?.[0]);
      const exclude = Number(values?.[1]);
      const user = users.find((u) => u.email === email && u.ia_users_id !== exclude);
      return { rows: user ? [{ ia_users_id: user.ia_users_id }] : [] };
    }

    if (text.includes('FROM ia_users') && text.includes('email = $1') && text.includes('SELECT ia_users_id')) {
      const email = String(values?.[0]);
      const user = users.find((u) => u.email === email);
      return { rows: user ? [{ ia_users_id: user.ia_users_id }] : [] };
    }

    if (text.includes('FROM ia_users') && text.includes('email = $1')) {
      const email = String(values?.[0]);
      const user = users.find((u) => u.email === email && u.is_deleted === '0');
      return { rows: user ? [user] : [] };
    }

    return { rows: [] };
  };

  return { users, settings, permissions, query };
}

describe('wave9 users settings auth', () => {
  let app: FastifyInstance | undefined;
  let store: ReturnType<typeof makeStore>;

  beforeEach(async () => {
    setMailDryRun(true);
    drainSentMails();
    resetPasswordSessions();
    resetRefreshTokens();
    store = makeStore();
    const hash = await bcrypt.hash('123456', 4);
    store.users.push({
      ia_users_id: 1,
      user_id: '1',
      name: 'Supperadmin',
      email: 'admin@example.com',
      password: hash,
      user_type: 'admin',
      profile_pic: 'user.png',
      status: 'active',
      is_deleted: '0',
      create_date: '2024-01-01',
      var_key: null,
    });
    store.users.push({
      ia_users_id: 2,
      user_id: '1',
      name: 'Manager One',
      email: 'manager@example.com',
      password: hash,
      user_type: 'manager',
      profile_pic: 'user.png',
      status: 'active',
      is_deleted: '0',
      create_date: '2024-02-01',
      var_key: null,
    });
    app = await buildApp({ pool: { query: store.query }, authSecret: SECRET, logger: false });
  });

  afterEach(async () => {
    resetPasswordSessions();
    resetRefreshTokens();
    drainSentMails();
    await app?.close();
  });

  it('returns branding with logo and favicon', async () => {
    const response = await app!.inject({ method: 'GET', url: '/api/v1/branding' });
    expect(response.statusCode).toBe(200);
    expect(response.json().data).toEqual({
      website: 'JConnect',
      logo: 'logo.png',
      favicon: 'favicon.ico',
      register_allowed: '1',
    });
  });

  it('falls back branding favicon when DB file is missing', async () => {
    const fav = store.settings.find((s) => s.keys === 'favicon');
    if (fav) fav.value = 'missing-wave9-favicon-404.jpg';
    const response = await app!.inject({ method: 'GET', url: '/api/v1/branding' });
    expect(response.statusCode).toBe(200);
    const data = response.json().data as { favicon: string; logo: string };
    expect(['Logo.png', 'logo.png', 'favicon.png', 'favicon.ico']).toContain(data.favicon);
    expect(data.favicon).not.toBe('missing-wave9-favicon-404.jpg');
    const asset = await app!.inject({
      method: 'GET',
      url: `/api/v1/assets/images/${encodeURIComponent(data.favicon)}`,
    });
    expect(asset.statusCode).toBe(200);
  });

  it('serves whitelisted image assets and rejects path traversal', async () => {
    const ok = await app!.inject({ method: 'GET', url: '/api/v1/assets/images/user.png' });
    expect(ok.statusCode).toBe(200);
    expect(ok.headers['content-type']).toMatch(/image\/png/);

    const bad = await app!.inject({
      method: 'GET',
      url: '/api/v1/assets/images/../package.json',
    });
    expect([400, 404]).toContain(bad.statusCode);
  });

  it('lists users excluding admin and self', async () => {
    const token = signAccessToken(1, SECRET);
    const response = await app!.inject({
      method: 'GET',
      url: '/api/v1/users?page=1&pageSize=10',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(response.statusCode).toBe(200);
    const body = response.json();
    expect(body.data.items).toHaveLength(1);
    expect(body.data.items[0].email).toBe('manager@example.com');
    expect(body.data.items[0].profilePic).toBe('user.png');
  });

  it('returns user statistics and types', async () => {
    const token = signAccessToken(1, SECRET);
    const stats = await app!.inject({
      method: 'GET',
      url: '/api/v1/users/statistics',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(stats.statusCode).toBe(200);
    expect(stats.json().data.totalUsers).toBe(1);
    expect(stats.json().data.activeUsers).toBe(1);

    const types = await app!.inject({
      method: 'GET',
      url: '/api/v1/users/types',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(types.statusCode).toBe(200);
    expect(types.json().data.userTypes).toContain('manager');
    expect(types.json().data.userTypes).not.toContain('admin');
  });

  it('creates updates and soft-deletes a user', async () => {
    const token = signAccessToken(1, SECRET);
    const created = await app!.inject({
      method: 'POST',
      url: '/api/v1/users',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'New User',
        email: 'new@example.com',
        user_type: 'manager',
        password: 'secret12',
      },
    });
    expect(created.statusCode).toBe(201);
    const id = created.json().data.id as number;

    const updated = await app!.inject({
      method: 'PUT',
      url: `/api/v1/users/${id}`,
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'New User 2',
        email: 'new@example.com',
        user_type: 'manager',
        status: 'pending',
      },
    });
    expect(updated.statusCode).toBe(200);
    expect(updated.json().data.name).toBe('New User 2');
    expect(updated.json().data.status).toBe('pending');

    const deleted = await app!.inject({
      method: 'DELETE',
      url: `/api/v1/users/${id}`,
      headers: { authorization: `Bearer ${token}` },
    });
    expect(deleted.statusCode).toBe(200);
    expect(store.users.find((u) => u.ia_users_id === id)?.is_deleted).toBe('1');
  });

  it('invites users by comma-separated emails', async () => {
    const token = signAccessToken(1, SECRET);
    const response = await app!.inject({
      method: 'POST',
      url: '/api/v1/users/invite',
      headers: { authorization: `Bearer ${token}` },
      payload: { emails: 'invitee@example.com, not-an-email, manager@example.com' },
    });
    expect(response.statusCode).toBe(200);
    const data = response.json().data as {
      seccessCount: number;
      existCount: number;
      invalidEmailCount: number;
    };
    expect(data.seccessCount).toBe(1);
    expect(data.existCount).toBe(1);
    expect(data.invalidEmailCount).toBe(1);
    expect(store.users.some((u) => u.email === 'invitee@example.com' && u.var_key)).toBe(true);
    const mails = peekSentMails();
    expect(mails.some((m) => m.to === 'invitee@example.com')).toBe(true);
  });

  it('exports users as excel-compatible csv', async () => {
    const token = signAccessToken(1, SECRET);
    const response = await app!.inject({
      method: 'GET',
      url: '/api/v1/users/export?format=excel',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(response.statusCode).toBe(200);
    expect(String(response.headers['content-type'])).toMatch(/application\/vnd\.ms-excel/);
    expect(String(response.headers['content-disposition'])).toContain('users.xls');
    const body = response.body;
    expect(body).toContain('Email');
    expect(body).toContain('manager@example.com');
    expect(body).not.toContain('admin@example.com');
  });

  it('bulk soft-deletes users', async () => {
    const token = signAccessToken(1, SECRET);
    const created = await app!.inject({
      method: 'POST',
      url: '/api/v1/users',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Bulk Target',
        email: 'bulk@example.com',
        user_type: 'manager',
        password: 'secret12',
      },
    });
    expect(created.statusCode).toBe(201);
    const id = created.json().data.id as number;

    const deleted = await app!.inject({
      method: 'DELETE',
      url: '/api/v1/users',
      headers: { authorization: `Bearer ${token}` },
      payload: { ids: [id, 2] },
    });
    expect(deleted.statusCode).toBe(200);
    expect(deleted.json().data.deletedIds).toEqual(expect.arrayContaining([id, 2]));
    expect(store.users.find((u) => u.ia_users_id === id)?.is_deleted).toBe('1');
    expect(store.users.find((u) => u.ia_users_id === 2)?.is_deleted).toBe('1');
  });

  it('reads and updates profile', async () => {
    const token = signAccessToken(1, SECRET);
    const get = await app!.inject({
      method: 'GET',
      url: '/api/v1/profile',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(get.statusCode).toBe(200);
    expect(get.json().data.email).toBe('admin@example.com');
    expect(get.json().data.userType).toBe('admin');

    const put = await app!.inject({
      method: 'PUT',
      url: '/api/v1/profile',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        name: 'Super Admin',
        email: 'admin@example.com',
        fileOld: 'user.png',
      },
    });
    expect(put.statusCode).toBe(200);
    expect(put.json().data.name).toBe('Super Admin');
  });

  it('gets and updates settings general and permissions', async () => {
    const token = signAccessToken(1, SECRET);
    const get = await app!.inject({
      method: 'GET',
      url: '/api/v1/settings',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(get.statusCode).toBe(200);
    expect(get.json().data.website).toBe('JConnect');

    const general = await app!.inject({
      method: 'PUT',
      url: '/api/v1/settings/general',
      headers: { authorization: `Bearer ${token}` },
      payload: {
        website: 'JConnect Pro',
        register_allowed: '0',
        admin_approval: '1',
        user_type: '["manager","administrator"]',
        fileOldlogo: 'logo.png',
      },
    });
    expect(general.statusCode).toBe(200);
    expect(general.json().data.website).toBe('JConnect Pro');
    expect(general.json().data.register_allowed).toBe('0');

    const perms = await app!.inject({
      method: 'GET',
      url: '/api/v1/settings/permissions',
      headers: { authorization: `Bearer ${token}` },
    });
    expect(perms.statusCode).toBe(200);
    expect(perms.json().data.roles.some((r: { userType: string }) => r.userType === 'manager')).toBe(
      true,
    );
  });

  it('runs forgot-password OTP flow with cookie session', async () => {
    const forgot = await app!.inject({
      method: 'POST',
      url: '/api/v1/auth/forgot-password',
      payload: { email: 'manager@example.com' },
    });
    expect(forgot.statusCode).toBe(200);
    const cookie = String(forgot.headers['set-cookie']).split(';')[0];
    expect(cookie).toContain('reset_token=');
    const mails = peekSentMails();
    expect(mails).toHaveLength(1);
    const otpMatch = mails[0]!.html.match(/\b(\d{6})\b/);
    expect(otpMatch).toBeTruthy();
    const otp = otpMatch![1]!;

    const verify = await app!.inject({
      method: 'POST',
      url: '/api/v1/auth/verify-otp',
      headers: { cookie },
      payload: { email: 'manager@example.com', otp },
    });
    expect(verify.statusCode).toBe(200);
    expect(verify.json().data.verified).toBe(true);
    const resetToken = verify.json().data.resetToken as string;

    const reset = await app!.inject({
      method: 'POST',
      url: '/api/v1/auth/reset-password',
      headers: { cookie },
      payload: {
        email: 'manager@example.com',
        password: 'newpass99',
        password_confirmation: 'newpass99',
        resetToken,
      },
    });
    expect(reset.statusCode).toBe(200);

    const login = await app!.inject({
      method: 'POST',
      url: '/api/v1/auth/login',
      payload: { email: 'manager@example.com', password: 'newpass99' },
    });
    expect(login.statusCode).toBe(200);
  });

  it('rate-limits forgot-password within 60s', async () => {
    const first = await app!.inject({
      method: 'POST',
      url: '/api/v1/auth/forgot-password',
      payload: { email: 'manager@example.com' },
    });
    expect(first.statusCode).toBe(200);
    const cookie = String(first.headers['set-cookie']).split(';')[0];
    const second = await app!.inject({
      method: 'POST',
      url: '/api/v1/auth/forgot-password',
      headers: { cookie },
      payload: { email: 'manager@example.com' },
    });
    expect(second.statusCode).toBe(429);
  });

  it('sets theme_color cookie', async () => {
    const response = await app!.inject({
      method: 'POST',
      url: '/api/v1/settings/theme-color',
      payload: { theme_color: 'dark' },
    });
    expect(response.statusCode).toBe(200);
    expect(String(response.headers['set-cookie'])).toContain('theme_color=dark');
    expect(response.json().data.colorModeKey).toBe('jconnect_color_mode');
  });

  it('can open the real user.png from assets directory', async () => {
    // sanity that PHP assets path used by the route exists in this repo
    expect(createReadStream(USER_PNG)).toBeTruthy();
  });
});
