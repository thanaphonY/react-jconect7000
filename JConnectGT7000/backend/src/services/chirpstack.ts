/**
 * ChirpStack REST client — same endpoint and auth as PHP Lorawan_model.
 * Base URL defaults to the nginx /loraapi/rest proxy (port 8090 upstream).
 */

const DEFAULT_BASE_URL = 'http://127.0.0.1/loraapi/rest/api';
const DEFAULT_API_KEY =
  'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJjaGlycHN0YWNrIiwiaXNzIjoiY2hpcnBzdGFjayIsInN1YiI6ImZjZjI2NjNhLTNiNTItNDhiYi1hNDY0LWYwNWU5N2M5YTA2NSIsInR5cCI6ImtleSJ9.X9sKAgsbuIWXMmzJPEiUIXnWr_-6BnLsJDQWkYXZeyo';

export type ChirpstackConfig = {
  baseUrl: string;
  apiKey: string;
};

export function chirpstackConfig(): ChirpstackConfig {
  return {
    baseUrl: (process.env.CHIRPSTACK_BASE_URL ?? DEFAULT_BASE_URL).replace(/\/$/, ''),
    apiKey: process.env.CHIRPSTACK_API_KEY ?? DEFAULT_API_KEY,
  };
}

export type ChirpstackResult =
  | { ok: true; data: unknown; httpStatus: number }
  | { ok: false; error: string; httpStatus?: number; data?: unknown };

export async function callChirpstackAPI(
  endpoint: string,
  method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'GET',
  data: unknown = null,
  queryParams: Record<string, string | number> = {},
): Promise<ChirpstackResult> {
  const { baseUrl, apiKey } = chirpstackConfig();
  const path = endpoint.replace(/^\//, '');
  const url = new URL(`${baseUrl}/${path}`);
  for (const [key, value] of Object.entries(queryParams)) {
    url.searchParams.set(key, String(value));
  }

  try {
    const response = await fetch(url.toString(), {
      method,
      headers: {
        Authorization: apiKey,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: data !== null && method !== 'GET' && method !== 'DELETE' ? JSON.stringify(data) : undefined,
      signal: AbortSignal.timeout(10_000),
    });

    const text = await response.text();
    let parsed: unknown = null;
    if (text.trim()) {
      try {
        parsed = JSON.parse(text) as unknown;
      } catch {
        return {
          ok: false,
          error: 'Invalid ChirpStack JSON response',
          httpStatus: response.status,
          data: text,
        };
      }
    }

    if (!response.ok) {
      const message =
        parsed && typeof parsed === 'object' && parsed !== null && 'message' in parsed
          ? String((parsed as { message: unknown }).message)
          : `HTTP ${response.status}`;
      return { ok: false, error: message, httpStatus: response.status, data: parsed };
    }

    return { ok: true, data: parsed ?? {}, httpStatus: response.status };
  } catch (error) {
    return {
      ok: false,
      error: error instanceof Error ? error.message : 'ChirpStack request failed',
    };
  }
}

export async function createChirpstackApplication(payload: {
  name: string;
  description: string;
  tenantId: string;
}): Promise<ChirpstackResult> {
  return callChirpstackAPI('applications', 'POST', { application: payload });
}

export async function updateChirpstackApplication(
  applicationId: string,
  payload: { name: string; description: string; tenantId: string },
): Promise<ChirpstackResult> {
  return callChirpstackAPI(`applications/${encodeURIComponent(applicationId)}`, 'PUT', {
    application: payload,
  });
}

export async function deleteChirpstackApplication(applicationId: string): Promise<ChirpstackResult> {
  return callChirpstackAPI(`applications/${encodeURIComponent(applicationId)}`, 'DELETE');
}

export async function listChirpstackDeviceProfiles(
  tenantId: string,
  limit = 100,
  offset = 0,
): Promise<ChirpstackResult> {
  return callChirpstackAPI('device-profiles', 'GET', null, {
    tenantId,
    limit,
    offset,
  });
}
