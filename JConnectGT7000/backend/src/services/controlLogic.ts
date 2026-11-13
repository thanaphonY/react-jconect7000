/**
 * control-logic HTTP client — same hop PHP Control_model uses.
 * Default: http://127.0.0.1/controlapi → nginx → 127.0.0.1:3006
 * Override with CONTROL_LOGIC_URL.
 */

const DEFAULT_BASE_URL = 'http://127.0.0.1/controlapi';

export function controlLogicBaseUrl(): string {
  return (process.env.CONTROL_LOGIC_URL ?? DEFAULT_BASE_URL).replace(/\/$/, '');
}

export type ControlLogicResult =
  | { ok: true; data: Record<string, unknown>; httpStatus: number }
  | { ok: false; error: string; httpStatus?: number; data?: Record<string, unknown> };

export async function callControlLogic(
  method: 'GET' | 'POST' | 'PUT' | 'DELETE',
  path: string,
  body: unknown = null,
): Promise<ControlLogicResult> {
  const url = `${controlLogicBaseUrl()}${path.startsWith('/') ? path : `/${path}`}`;
  try {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: body !== null && method !== 'GET' && method !== 'DELETE' ? JSON.stringify(body) : undefined,
      signal: AbortSignal.timeout(8_000),
    });

    const text = await response.text();
    let parsed: Record<string, unknown> | null = null;
    if (text.trim()) {
      try {
        const value = JSON.parse(text) as unknown;
        if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
          parsed = value as Record<string, unknown>;
        } else {
          return {
            ok: false,
            error: `invalid response from control-logic (HTTP ${response.status})`,
            httpStatus: response.status,
          };
        }
      } catch {
        return {
          ok: false,
          error: `invalid response from control-logic (HTTP ${response.status})`,
          httpStatus: response.status,
        };
      }
    }

    if (!parsed) {
      return {
        ok: false,
        error: `invalid response from control-logic (HTTP ${response.status})`,
        httpStatus: response.status,
      };
    }

    if (parsed.ok === false) {
      return {
        ok: false,
        error: typeof parsed.error === 'string' ? parsed.error : 'control-logic error',
        httpStatus: response.status,
        data: parsed,
      };
    }

    return { ok: true, data: parsed, httpStatus: response.status };
  } catch (error) {
    return {
      ok: false,
      error: error instanceof Error ? error.message : 'control-logic unreachable',
    };
  }
}
