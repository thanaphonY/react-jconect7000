import type { Queryable } from '../db.js';
import {
  createChirpstackApplication,
  deleteChirpstackApplication,
  listChirpstackDeviceProfiles,
  updateChirpstackApplication,
} from '../services/chirpstack.js';

export interface LorawanApplication {
  id: number;
  applicationId: string;
  name: string;
  description: string;
  tenantId: string;
  createdAt: string;
}

type DbRow = {
  id: number;
  application_id: string;
  name: string | null;
  description: string | null;
  tenant_id: string;
  created_at: Date | string | null;
};

/** Format like PHP DB display: YYYY-MM-DD HH:mm:ss in Asia/Bangkok (not UTC ISO). */
function formatCreatedAt(value: Date | string | null): string {
  if (value == null) return '';
  if (typeof value === 'string') {
    const raw = value.trim();
    // Naive local DB timestamp — pass through like PHP htmlspecialchars($app['created_at']).
    if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(\.\d+)?$/.test(raw)) {
      return raw.replace('T', ' ').slice(0, 19);
    }
  }
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value).replace('T', ' ').slice(0, 19);
  }
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Bangkok',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).formatToParts(date);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';
  return `${get('year')}-${get('month')}-${get('day')} ${get('hour')}:${get('minute')}:${get('second')}`;
}

function mapRow(row: DbRow): LorawanApplication {
  return {
    id: row.id,
    applicationId: String(row.application_id),
    name: row.name ?? '',
    description: row.description ?? '',
    tenantId: String(row.tenant_id),
    createdAt: formatCreatedAt(row.created_at),
  };
}

export async function listLorawanApplications(db: Queryable): Promise<LorawanApplication[]> {
  const result = await db.query(
    `SELECT id, application_id, name, description, tenant_id, created_at
     FROM lorawan_applications
     ORDER BY name ASC`,
  );
  return (result.rows as DbRow[]).map(mapRow);
}

export async function getLorawanApplicationByApplicationId(
  db: Queryable,
  applicationId: string,
): Promise<LorawanApplication | null> {
  const result = await db.query(
    `SELECT id, application_id, name, description, tenant_id, created_at
     FROM lorawan_applications
     WHERE application_id = $1
     LIMIT 1`,
    [applicationId],
  );
  const row = (result.rows as DbRow[])[0];
  return row ? mapRow(row) : null;
}

export async function createLorawanApplication(
  db: Queryable,
  input: { name: string; description: string; tenantId: string },
): Promise<{ application: LorawanApplication } | { error: string; code: string }> {
  const chirp = await createChirpstackApplication({
    name: input.name,
    description: input.description,
    tenantId: input.tenantId,
  });

  if (!chirp.ok) {
    // Soft message like PHP add_application — no raw ChirpStack JSON noise.
    return { error: 'Failed to create application', code: 'CHIRPSTACK_ERROR' };
  }

  const data = chirp.data as { id?: string } | null;
  const applicationId = data && typeof data.id === 'string' ? data.id : null;
  if (!applicationId) {
    return { error: 'Failed to create application', code: 'CHIRPSTACK_ERROR' };
  }

  const inserted = await db.query(
    `INSERT INTO lorawan_applications (application_id, name, description, tenant_id, created_at)
     VALUES ($1, $2, $3, $4, NOW())
     RETURNING id, application_id, name, description, tenant_id, created_at`,
    [applicationId, input.name, input.description, input.tenantId],
  );
  const row = (inserted.rows as DbRow[])[0];
  if (!row) {
    return { error: 'Failed to save application locally', code: 'DB_ERROR' };
  }
  return { application: mapRow(row) };
}

export async function updateLorawanApplication(
  db: Queryable,
  applicationId: string,
  input: { name: string; description: string },
): Promise<{ application: LorawanApplication } | { error: string; code: string }> {
  const existing = await getLorawanApplicationByApplicationId(db, applicationId);
  if (!existing) {
    return { error: 'Application not found', code: 'NOT_FOUND' };
  }

  const chirp = await updateChirpstackApplication(applicationId, {
    name: input.name,
    description: input.description,
    tenantId: existing.tenantId,
  });

  if (!chirp.ok) {
    return { error: chirp.error || 'Failed to update application', code: 'CHIRPSTACK_ERROR' };
  }

  const updated = await db.query(
    `UPDATE lorawan_applications
     SET name = $2, description = $3
     WHERE application_id = $1
     RETURNING id, application_id, name, description, tenant_id, created_at`,
    [applicationId, input.name, input.description],
  );
  const row = (updated.rows as DbRow[])[0];
  if (!row) {
    return { error: 'Application not found', code: 'NOT_FOUND' };
  }
  return { application: mapRow(row) };
}

export async function deleteLorawanApplication(
  db: Queryable,
  applicationId: string,
): Promise<{ ok: true } | { error: string; code: string }> {
  const existing = await getLorawanApplicationByApplicationId(db, applicationId);
  if (!existing) {
    return { error: 'Application not found', code: 'NOT_FOUND' };
  }

  const chirp = await deleteChirpstackApplication(applicationId);
  if (!chirp.ok) {
    return { error: chirp.error || 'Failed to delete application', code: 'CHIRPSTACK_ERROR' };
  }

  await db.query(`DELETE FROM lorawan_applications WHERE application_id = $1`, [applicationId]);
  return { ok: true };
}

export type LorawanDeviceProfile = {
  id: string;
  name: string;
};

export async function fetchLorawanDeviceProfiles(
  tenantId: string,
): Promise<
  | { profiles: LorawanDeviceProfile[]; totalCount: number }
  | { error: string; code: string }
> {
  const chirp = await listChirpstackDeviceProfiles(tenantId);
  if (!chirp.ok) {
    // Match PHP get_device_profile: "Failed to fetch device profiles"
    return { error: 'Failed to fetch device profiles', code: 'CHIRPSTACK_ERROR' };
  }

  const payload = chirp.data as {
    result?: Array<{ id?: string; name?: string }>;
    totalCount?: number;
  } | null;

  const result = Array.isArray(payload?.result) ? payload.result : [];
  return {
    profiles: result
      .filter((row) => row && typeof row.id === 'string')
      .map((row) => ({
        id: String(row.id),
        name: typeof row.name === 'string' ? row.name : String(row.id),
      })),
    totalCount: typeof payload?.totalCount === 'number' ? payload.totalCount : result.length,
  };
}
