import { createHmac, randomBytes, timingSafeEqual } from 'node:crypto';

export const RESET_COOKIE = 'reset_token';

interface ResetRecord {
  email: string;
  verified: boolean;
  lastSentAt: number;
  attempts: number;
  expiresAt: number;
}

const RESET_TTL_MS = 30 * 60 * 1000;
const VERIFIED_TTL_MS = 10 * 60 * 1000;

const sessions = new Map<string, ResetRecord>();

export function resetPasswordSessions(): void {
  sessions.clear();
}

export function issueResetSession(
  email: string,
  now = Date.now(),
): { token: string; maxAgeSeconds: number } {
  const token = randomBytes(32).toString('base64url');
  sessions.set(token, {
    email: email.toLowerCase(),
    verified: false,
    lastSentAt: now,
    attempts: 0,
    expiresAt: now + RESET_TTL_MS,
  });
  return { token, maxAgeSeconds: Math.floor(RESET_TTL_MS / 1000) };
}

export function touchResetSent(token: string | undefined, now = Date.now()): ResetRecord | null {
  const record = readResetSession(token, now);
  if (!record || !token) {
    return null;
  }
  record.lastSentAt = now;
  record.verified = false;
  record.attempts = 0;
  record.expiresAt = now + RESET_TTL_MS;
  sessions.set(token, record);
  return record;
}

export function readResetSession(token: string | undefined, now = Date.now()): ResetRecord | null {
  if (!token) {
    return null;
  }
  const record = sessions.get(token);
  if (!record || record.expiresAt <= now) {
    if (record) {
      sessions.delete(token);
    }
    return null;
  }
  return record;
}

export function markResetVerified(token: string | undefined, now = Date.now()): boolean {
  const record = readResetSession(token, now);
  if (!record || !token) {
    return false;
  }
  record.verified = true;
  record.attempts = 0;
  record.expiresAt = now + VERIFIED_TTL_MS;
  sessions.set(token, record);
  return true;
}

export function bumpResetAttempts(token: string | undefined, now = Date.now()): number {
  const record = readResetSession(token, now);
  if (!record || !token) {
    return 999;
  }
  record.attempts += 1;
  sessions.set(token, record);
  return record.attempts;
}

export function clearResetSession(token: string | undefined): void {
  if (token) {
    sessions.delete(token);
  }
}

/** Optional signed short-lived token alternative to cookie (for clients that cannot store cookies). */
export function signResetVerifiedToken(email: string, secret: string, now = Date.now()): string {
  const body = Buffer.from(
    JSON.stringify({ email: email.toLowerCase(), exp: now + VERIFIED_TTL_MS, v: 1 }),
  ).toString('base64url');
  const signature = createHmac('sha256', secret).update(body).digest('base64url');
  return `${body}.${signature}`;
}

export function readResetVerifiedToken(
  token: string | undefined,
  secret: string,
  now = Date.now(),
): string | null {
  if (!token) {
    return null;
  }
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
    const payload = JSON.parse(Buffer.from(body, 'base64url').toString('utf8')) as {
      email?: string;
      exp?: number;
      v?: number;
    };
    if (typeof payload.email !== 'string' || typeof payload.exp !== 'number' || payload.exp <= now) {
      return null;
    }
    return payload.email.toLowerCase();
  } catch {
    return null;
  }
}
