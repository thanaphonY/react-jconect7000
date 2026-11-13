import { createHmac, randomBytes, timingSafeEqual } from 'node:crypto';

const ACCESS_TTL_MS = 15 * 60 * 1000;
const REFRESH_TTL_MS = 12 * 60 * 60 * 1000;
const REMEMBER_TTL_MS = 30 * 24 * 60 * 60 * 1000;

export const REFRESH_COOKIE = 'refresh_token';

interface AccessPayload {
  sub: number;
  exp: number;
}

interface RefreshRecord {
  userId: number;
  expiresAt: number;
}

const refreshTokens = new Map<string, RefreshRecord>();

export function resetRefreshTokens(): void {
  refreshTokens.clear();
}

export function signAccessToken(userId: number, secret: string, now = Date.now()): string {
  const body = Buffer.from(JSON.stringify({ sub: userId, exp: now + ACCESS_TTL_MS } satisfies AccessPayload)).toString(
    'base64url',
  );
  const signature = createHmac('sha256', secret).update(body).digest('base64url');
  return `${body}.${signature}`;
}

export function readAccessToken(token: string, secret: string, now = Date.now()): AccessPayload | null {
  const parts = token.split('.');
  if (parts.length !== 2) {
    return null;
  }
  const [body, signature] = parts;
  if (!body || !signature) {
    return null;
  }
  const expected = createHmac('sha256', secret).update(body).digest('base64url');
  const actualBuf = Buffer.from(signature);
  const expectedBuf = Buffer.from(expected);
  if (actualBuf.length !== expectedBuf.length || !timingSafeEqual(actualBuf, expectedBuf)) {
    return null;
  }
  try {
    const payload = JSON.parse(Buffer.from(body, 'base64url').toString('utf8')) as AccessPayload;
    if (typeof payload.sub !== 'number' || typeof payload.exp !== 'number' || payload.exp <= now) {
      return null;
    }
    return payload;
  } catch {
    return null;
  }
}

export function issueRefreshToken(userId: number, remember: boolean, now = Date.now()): { token: string; maxAgeSeconds: number } {
  const token = randomBytes(32).toString('base64url');
  const ttl = remember ? REMEMBER_TTL_MS : REFRESH_TTL_MS;
  refreshTokens.set(token, { userId, expiresAt: now + ttl });
  return { token, maxAgeSeconds: Math.floor(ttl / 1000) };
}

export function readRefreshToken(token: string | undefined, now = Date.now()): number | null {
  if (!token) {
    return null;
  }
  const record = refreshTokens.get(token);
  if (!record || record.expiresAt <= now) {
    if (record) {
      refreshTokens.delete(token);
    }
    return null;
  }
  return record.userId;
}

export function revokeRefreshToken(token: string | undefined): void {
  if (token) {
    refreshTokens.delete(token);
  }
}
