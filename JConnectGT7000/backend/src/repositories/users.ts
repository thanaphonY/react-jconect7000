import type { Queryable } from '../db.js';
import { parsePermissionData } from '../auth/menu.js';
import { hashPassword, verifyPassword } from '../auth/password.js';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  userType: string;
  profilePic: string | null;
  passwordHash: string | null;
  status?: string | null;
}

export interface UserListItem {
  id: number;
  name: string;
  email: string;
  userType: string;
  status: string | null;
  profilePic: string | null;
  createDate: string | null;
}

interface UserRow {
  ia_users_id: number;
  name: string | null;
  email: string | null;
  password?: string | null;
  user_type: string | null;
  profile_pic: string | null;
  status?: string | null;
  create_date?: string | null;
  var_key?: string | null;
  user_id?: string | null;
  is_deleted?: string | null;
}

function asUser(row: UserRow, includePassword: boolean): AuthUser {
  return {
    id: row.ia_users_id,
    name: row.name ?? '',
    email: row.email ?? '',
    userType: row.user_type ?? '',
    profilePic: row.profile_pic,
    passwordHash: includePassword ? (row.password ?? null) : null,
    status: row.status ?? null,
  };
}

function asListItem(row: UserRow): UserListItem {
  return {
    id: row.ia_users_id,
    name: row.name ?? '',
    email: row.email ?? '',
    userType: row.user_type ?? '',
    status: row.status ?? null,
    profilePic: row.profile_pic,
    createDate: row.create_date ?? null,
  };
}

export async function findUserByLogin(db: Queryable, login: string): Promise<AuthUser | null> {
  const result = await db.query(
    `SELECT ia_users_id, name, email, password, user_type, profile_pic, status
     FROM ia_users
     WHERE is_deleted = '0' AND (name = $1 OR email = $1)
     LIMIT 1`,
    [login],
  );
  const row = result.rows[0] as UserRow | undefined;
  return row ? asUser(row, true) : null;
}

export async function findUserById(db: Queryable, id: number): Promise<AuthUser | null> {
  const result = await db.query(
    `SELECT ia_users_id, name, email, user_type, profile_pic, status, password, user_id
     FROM ia_users
     WHERE ia_users_id = $1 AND is_deleted = '0'
     LIMIT 1`,
    [id],
  );
  const row = result.rows[0] as UserRow | undefined;
  return row ? asUser(row, true) : null;
}

export async function userCreatedBy(db: Queryable, id: number): Promise<string | null> {
  const result = await db.query(
    `SELECT user_id FROM ia_users WHERE ia_users_id = $1 AND is_deleted = '0' LIMIT 1`,
    [id],
  );
  const row = result.rows[0] as { user_id?: string | null } | undefined;
  return row?.user_id ?? null;
}

export async function findUserByEmail(db: Queryable, email: string): Promise<AuthUser | null> {
  const result = await db.query(
    `SELECT ia_users_id, name, email, password, user_type, profile_pic, status, var_key
     FROM ia_users
     WHERE LOWER(email) = LOWER($1) AND is_deleted = '0'
     LIMIT 1`,
    [email],
  );
  const row = result.rows[0] as UserRow | undefined;
  return row ? asUser(row, true) : null;
}

export async function findPermissionData(
  db: Queryable,
  userType: string,
): Promise<ReturnType<typeof parsePermissionData>> {
  const result = await db.query(`SELECT data FROM ia_permission WHERE user_type = $1 LIMIT 1`, [
    userType,
  ]);
  const row = result.rows[0] as { data?: string | null } | undefined;
  return parsePermissionData(row?.data ?? null);
}

export async function findWebsiteName(db: Queryable): Promise<string> {
  const result = await db.query(`SELECT value FROM ia_setting WHERE keys = 'website' LIMIT 1`);
  const row = result.rows[0] as { value?: string | null } | undefined;
  const value = row?.value?.trim();
  return value ? value : 'JConnect';
}

export interface ListUsersParams {
  page: number;
  pageSize: number;
  search?: string;
  userType?: string;
  status?: string;
  viewerId: number;
  allRead: boolean;
}

export async function listUsers(
  db: Queryable,
  params: ListUsersParams,
): Promise<{ items: UserListItem[]; total: number }> {
  const conditions: string[] = [`is_deleted = '0'`, `LOWER(user_type) != 'admin'`];
  const values: unknown[] = [];

  if (params.allRead) {
    values.push(params.viewerId);
    conditions.push(`ia_users_id != $${values.length}`);
  } else {
    values.push(String(params.viewerId));
    conditions.push(`user_id = $${values.length}`);
  }

  if (params.userType && params.userType !== 'all') {
    values.push(params.userType);
    conditions.push(`user_type = $${values.length}`);
  }
  if (params.status && params.status !== 'all') {
    values.push(params.status);
    conditions.push(`status = $${values.length}`);
  }
  if (params.search) {
    values.push(`%${params.search}%`);
    const i = values.length;
    conditions.push(`(name ILIKE $${i} OR email ILIKE $${i} OR user_type ILIKE $${i})`);
  }

  const where = conditions.join(' AND ');
  const countResult = await db.query(`SELECT COUNT(*)::int AS total FROM ia_users WHERE ${where}`, values);
  const total = Number((countResult.rows[0] as { total: number }).total);

  const offset = (params.page - 1) * params.pageSize;
  values.push(params.pageSize);
  const limitIdx = values.length;
  values.push(offset);
  const offsetIdx = values.length;

  const result = await db.query(
    `SELECT ia_users_id, name, email, user_type, status, profile_pic, create_date
     FROM ia_users
     WHERE ${where}
     ORDER BY name ASC
     LIMIT $${limitIdx} OFFSET $${offsetIdx}`,
    values,
  );

  return {
    items: (result.rows as UserRow[]).map(asListItem),
    total,
  };
}

export async function userStatistics(db: Queryable): Promise<{
  totalUsers: number;
  activeUsers: number;
  pendingUsers: number;
  userTypes: number;
  userTypesBreakdown: Array<{ userType: string; count: number }>;
}> {
  const base = `is_deleted = '0' AND LOWER(user_type) != 'admin'`;
  const total = await db.query(`SELECT COUNT(*)::int AS total FROM ia_users WHERE ${base}`);
  const active = await db.query(
    `SELECT COUNT(*)::int AS total FROM ia_users WHERE ${base} AND status = 'active'`,
  );
  const pending = await db.query(
    `SELECT COUNT(*)::int AS total FROM ia_users WHERE ${base} AND status = 'pending'`,
  );
  const types = await db.query(
    `SELECT user_type, COUNT(*)::int AS count
     FROM ia_users
     WHERE ${base}
     GROUP BY user_type
     ORDER BY user_type ASC`,
  );
  const breakdown = (
    types.rows as Array<{ user_type: string | null; count: number }>
  ).map((row) => ({ userType: row.user_type ?? '', count: row.count }));

  return {
    totalUsers: Number((total.rows[0] as { total: number }).total),
    activeUsers: Number((active.rows[0] as { total: number }).total),
    pendingUsers: Number((pending.rows[0] as { total: number }).total),
    userTypes: breakdown.length,
    userTypesBreakdown: breakdown,
  };
}

export async function listUserTypes(db: Queryable): Promise<string[]> {
  const result = await db.query(
    `SELECT user_type FROM ia_permission WHERE LOWER(user_type) != 'admin' ORDER BY user_type ASC`,
  );
  return (result.rows as Array<{ user_type: string | null }>)
    .map((row) => row.user_type ?? '')
    .filter(Boolean);
}

export async function emailExists(
  db: Queryable,
  email: string,
  excludeId?: number,
): Promise<boolean> {
  if (excludeId !== undefined) {
    const result = await db.query(
      `SELECT ia_users_id FROM ia_users WHERE email = $1 AND ia_users_id != $2 LIMIT 1`,
      [email, excludeId],
    );
    return result.rows.length > 0;
  }
  const result = await db.query(`SELECT ia_users_id FROM ia_users WHERE email = $1 LIMIT 1`, [email]);
  return result.rows.length > 0;
}

export interface CreateUserInput {
  name: string;
  email: string;
  userType: string;
  password: string;
  profilePic: string;
  status?: string;
  createdByUserId: number;
  adminApproval: boolean;
}

export async function createUser(db: Queryable, input: CreateUserInput): Promise<number> {
  let status = input.status || 'active';
  if (!input.status && input.adminApproval) {
    status = 'pending';
  }
  const password = await hashPassword(input.password);
  const createDate = new Date().toISOString().slice(0, 10);
  const result = await db.query(
    `INSERT INTO ia_users (
       user_id, status, is_deleted, name, password, email, profile_pic, user_type, create_date
     ) VALUES ($1, $2, '0', $3, $4, $5, $6, $7, $8)
     RETURNING ia_users_id`,
    [
      String(input.createdByUserId),
      status,
      input.name,
      password,
      input.email,
      input.profilePic,
      input.userType,
      createDate,
    ],
  );
  return Number((result.rows[0] as { ia_users_id: number }).ia_users_id);
}

export interface UpdateUserInput {
  name: string;
  email: string;
  userType?: string;
  status?: string;
  profilePic: string;
  password?: string;
}

export async function updateUser(db: Queryable, id: number, input: UpdateUserInput): Promise<void> {
  const fields: string[] = ['name = $2', 'email = $3', 'profile_pic = $4'];
  const values: unknown[] = [id, input.name, input.email, input.profilePic];

  if (input.userType !== undefined) {
    values.push(input.userType);
    fields.push(`user_type = $${values.length}`);
  }
  if (input.status !== undefined && input.status !== '') {
    values.push(input.status);
    fields.push(`status = $${values.length}`);
  }
  if (input.password) {
    values.push(await hashPassword(input.password));
    fields.push(`password = $${values.length}`);
  }

  await db.query(`UPDATE ia_users SET ${fields.join(', ')} WHERE ia_users_id = $1`, values);
}

export async function softDeleteUser(db: Queryable, id: number): Promise<boolean> {
  const result = await db.query(
    `UPDATE ia_users SET is_deleted = '1' WHERE ia_users_id = $1 AND is_deleted = '0' RETURNING ia_users_id`,
    [id],
  );
  return result.rows.length > 0;
}

export async function softDeleteUsers(db: Queryable, ids: number[]): Promise<number[]> {
  if (ids.length === 0) {
    return [];
  }
  const result = await db.query(
    `UPDATE ia_users
     SET is_deleted = '1'
     WHERE ia_users_id = ANY($1::int[])
       AND is_deleted = '0'
       AND LOWER(COALESCE(user_type, '')) != 'admin'
     RETURNING ia_users_id`,
    [ids],
  );
  return (result.rows as Array<{ ia_users_id: number }>).map((row) => row.ia_users_id);
}

/** Random invite key mirroring PHP User::randomString() (8 alphanumerics). */
export function randomInviteKey(length = 8): string {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let out = '';
  for (let i = 0; i < length; i += 1) {
    out += chars[Math.floor(Math.random() * chars.length)]!;
  }
  return out;
}

export async function insertPendingInvite(
  db: Queryable,
  email: string,
  varKey: string,
): Promise<number> {
  const result = await db.query(
    `INSERT INTO ia_users (email, var_key) VALUES ($1, $2) RETURNING ia_users_id`,
    [email, varKey],
  );
  return Number((result.rows[0] as { ia_users_id: number }).ia_users_id);
}

export async function listUsersForExport(
  db: Queryable,
  params: Omit<ListUsersParams, 'page' | 'pageSize'>,
): Promise<UserListItem[]> {
  const result = await listUsers(db, {
    ...params,
    page: 1,
    pageSize: 10_000,
  });
  return result.items;
}

export async function savePasswordResetOtp(
  db: Queryable,
  userId: number,
  otp: string,
  ttlSeconds = 600,
): Promise<void> {
  const payload = JSON.stringify({
    t: 'otp',
    h: await hashPassword(otp),
    e: Math.floor(Date.now() / 1000) + ttlSeconds,
  });
  await db.query(`UPDATE ia_users SET var_key = $2 WHERE ia_users_id = $1`, [userId, payload]);
}

export async function verifyPasswordResetOtp(
  db: Queryable,
  email: string,
  otp: string,
): Promise<'ok' | 'invalid' | 'expired'> {
  const result = await db.query(
    `SELECT var_key FROM ia_users WHERE email = $1 AND is_deleted = '0' LIMIT 1`,
    [email],
  );
  const row = result.rows[0] as { var_key?: string | null } | undefined;
  if (!row?.var_key) {
    return 'invalid';
  }
  let payload: { t?: string; h?: string; e?: number };
  try {
    payload = JSON.parse(row.var_key) as { t?: string; h?: string; e?: number };
  } catch {
    return 'invalid';
  }
  if (payload.t !== 'otp' || !payload.h || !payload.e) {
    return 'invalid';
  }
  if (Math.floor(Date.now() / 1000) > payload.e) {
    return 'expired';
  }
  const ok = await verifyPassword(otp, payload.h);
  return ok ? 'ok' : 'invalid';
}

export async function consumePasswordResetOtp(db: Queryable, email: string): Promise<void> {
  const payload = JSON.stringify({
    t: 'otp_ok',
    e: Math.floor(Date.now() / 1000) + 600,
  });
  await db.query(`UPDATE ia_users SET var_key = $2 WHERE email = $1`, [email, payload]);
}

export async function resetPasswordForEmail(
  db: Queryable,
  email: string,
  password: string,
): Promise<boolean> {
  const hashed = await hashPassword(password);
  const result = await db.query(
    `UPDATE ia_users SET password = $2, var_key = '' WHERE email = $1 AND is_deleted = '0' RETURNING ia_users_id`,
    [email, hashed],
  );
  return result.rows.length > 0;
}

export async function getSettingValue(db: Queryable, key: string): Promise<string | null> {
  const result = await db.query(`SELECT value FROM ia_setting WHERE keys = $1 LIMIT 1`, [key]);
  const row = result.rows[0] as { value?: string | null } | undefined;
  return row?.value ?? null;
}
