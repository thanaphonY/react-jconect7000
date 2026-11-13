import type { Queryable } from '../db.js';
import { imageExists } from '../assets/images.js';
import { parsePermissionData } from '../auth/menu.js';

export type SettingsMap = Record<string, string>;

const BRANDING_KEYS = ['website', 'logo', 'favicon', 'register_allowed'] as const;

/** Prefer DB value when the file exists; otherwise first existing fallback (PHP login Logo.png / forgot favicon.ico). */
async function resolveExistingAsset(
  preferred: string | undefined,
  fallbacks: readonly string[],
): Promise<string> {
  const candidate = preferred?.trim() ?? '';
  if (candidate && (await imageExists(candidate))) {
    return candidate;
  }
  for (const name of fallbacks) {
    if (await imageExists(name)) {
      return name;
    }
  }
  return candidate || fallbacks[0] || '';
}

const GENERAL_KEYS = [
  'website',
  'logo',
  'register_allowed',
  'admin_approval',
  'user_type',
] as const;

const EMAIL_KEYS = [
  'mail_setting',
  'company_name',
  'SMTP_EMAIL',
  'HOST',
  'PORT',
  'SMTP_SECURE',
  'SMTP_PASSWORD',
  'EMAIL',
] as const;

const SETTINGS_KEYS = [...new Set([...GENERAL_KEYS, ...EMAIL_KEYS, 'favicon', 'language'])];

export async function getAllSettings(db: Queryable): Promise<SettingsMap> {
  const result = await db.query(`SELECT keys, value FROM ia_setting`);
  const map: SettingsMap = {};
  for (const row of result.rows as Array<{ keys?: string | null; value?: string | null }>) {
    if (row.keys) {
      map[row.keys] = row.value ?? '';
    }
  }
  return map;
}

export async function getSettingsByKeys(db: Queryable, keys: readonly string[]): Promise<SettingsMap> {
  if (keys.length === 0) {
    return {};
  }
  const result = await db.query(`SELECT keys, value FROM ia_setting WHERE keys = ANY($1::text[])`, [
    [...keys],
  ]);
  const map: SettingsMap = {};
  for (const key of keys) {
    map[key] = '';
  }
  for (const row of result.rows as Array<{ keys?: string | null; value?: string | null }>) {
    if (row.keys) {
      map[row.keys] = row.value ?? '';
    }
  }
  return map;
}

export async function getBranding(db: Queryable): Promise<{
  website: string;
  logo: string;
  favicon: string;
  register_allowed: string;
}> {
  const map = await getSettingsByKeys(db, BRANDING_KEYS);
  const logo = await resolveExistingAsset(map.logo, ['logo.png', 'Logo.png']);
  // Prefer Logo.png (PHP login), then favicon.png / favicon.ico (PHP forgot).
  const favicon = await resolveExistingAsset(map.favicon, [
    'Logo.png',
    'favicon.png',
    'favicon.ico',
  ]);
  return {
    website: map.website?.trim() || 'JConnect',
    logo,
    favicon,
    register_allowed: map.register_allowed || '0',
  };
}

export async function getAppSettingsMap(db: Queryable): Promise<SettingsMap> {
  return getSettingsByKeys(db, SETTINGS_KEYS);
}

export async function upsertSetting(db: Queryable, key: string, value: string): Promise<void> {
  const existing = await db.query(`SELECT id FROM ia_setting WHERE keys = $1 LIMIT 1`, [key]);
  if (existing.rows.length > 0) {
    await db.query(`UPDATE ia_setting SET value = $2 WHERE keys = $1`, [key, value]);
    return;
  }
  await db.query(`INSERT INTO ia_setting (keys, value) VALUES ($1, $2)`, [key, value]);
}

export async function updateSettingsMap(db: Queryable, data: Record<string, string>): Promise<void> {
  for (const [key, value] of Object.entries(data)) {
    await upsertSetting(db, key, value);
  }
}

export interface PermissionRole {
  id: number;
  userType: string;
  data: ReturnType<typeof parsePermissionData>;
  rawData: string | null;
}

export async function listPermissionRoles(db: Queryable): Promise<PermissionRole[]> {
  const result = await db.query(
    `SELECT id, user_type, data FROM ia_permission WHERE LOWER(user_type) != 'admin' ORDER BY user_type ASC`,
  );
  return (result.rows as Array<{ id: number; user_type: string | null; data: string | null }>).map(
    (row) => ({
      id: row.id,
      userType: row.user_type ?? '',
      data: parsePermissionData(row.data),
      rawData: row.data,
    }),
  );
}

export async function listAllPermissionRoles(db: Queryable): Promise<PermissionRole[]> {
  const result = await db.query(`SELECT id, user_type, data FROM ia_permission ORDER BY user_type ASC`);
  return (result.rows as Array<{ id: number; user_type: string | null; data: string | null }>).map(
    (row) => ({
      id: row.id,
      userType: row.user_type ?? '',
      data: parsePermissionData(row.data),
      rawData: row.data,
    }),
  );
}

export async function getAdminPermissionData(db: Queryable): Promise<string | null> {
  const result = await db.query(
    `SELECT data FROM ia_permission WHERE LOWER(user_type) = 'admin' LIMIT 1`,
  );
  const row = result.rows[0] as { data?: string | null } | undefined;
  return row?.data ?? null;
}

export async function updatePermissionData(
  db: Queryable,
  userType: string,
  dataJson: string,
): Promise<void> {
  await db.query(`UPDATE ia_permission SET data = $2 WHERE user_type = $1`, [userType, dataJson]);
}

export async function renamePermissionRole(db: Queryable, id: number, userType: string): Promise<void> {
  await db.query(`UPDATE ia_permission SET user_type = $2 WHERE id = $1`, [id, userType]);
}

export async function insertPermissionRole(
  db: Queryable,
  userType: string,
  data: string | null,
): Promise<number> {
  const result = await db.query(
    `INSERT INTO ia_permission (user_type, data) VALUES ($1, $2) RETURNING id`,
    [userType, data],
  );
  return Number((result.rows[0] as { id: number }).id);
}

export async function deletePermissionRole(db: Queryable, id: number): Promise<void> {
  await db.query(`DELETE FROM ia_permission WHERE id = $1`, [id]);
}

export async function findEmailTemplate(
  db: Queryable,
  code: string,
): Promise<{ html: string | null } | null> {
  const result = await db.query(
    `SELECT html FROM ia_email_templates WHERE code = $1 LIMIT 1`,
    [code],
  );
  const row = result.rows[0] as { html?: string | null } | undefined;
  return row ? { html: row.html ?? null } : null;
}

export { GENERAL_KEYS, EMAIL_KEYS, BRANDING_KEYS };
