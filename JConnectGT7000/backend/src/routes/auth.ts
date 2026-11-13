import { randomInt } from 'node:crypto';
import type { FastifyInstance, FastifyReply, FastifyRequest } from 'fastify';
import { z } from 'zod';
import { menuForUser } from '../auth/menu.js';
import { verifyPassword } from '../auth/password.js';
import {
  RESET_COOKIE,
  bumpResetAttempts,
  clearResetSession,
  issueResetSession,
  markResetVerified,
  readResetSession,
  readResetVerifiedToken,
  signResetVerifiedToken,
  touchResetSent,
} from '../auth/resetSession.js';
import {
  REFRESH_COOKIE,
  issueRefreshToken,
  readAccessToken,
  readRefreshToken,
  revokeRefreshToken,
  signAccessToken,
} from '../auth/tokens.js';
import type { Queryable } from '../db.js';
import {
  DEFAULT_OTP_EMAIL_BODY,
  renderTemplate,
  sendHtmlMail,
  type MailSettings,
} from '../mail/sendMail.js';
import { findEmailTemplate, getAllSettings } from '../repositories/settings.js';
import {
  consumePasswordResetOtp,
  findPermissionData,
  findUserByEmail,
  findUserById,
  findUserByLogin,
  findWebsiteName,
  resetPasswordForEmail,
  savePasswordResetOtp,
  verifyPasswordResetOtp,
  type AuthUser,
} from '../repositories/users.js';

const loginBody = z.object({
  email: z.string().trim().min(1),
  password: z.string().min(1),
  remember: z.boolean().optional(),
});

const emailBody = z.object({
  email: z.string().trim().email(),
});

const otpBody = z.object({
  email: z.string().trim().email(),
  otp: z.string().trim().min(4),
});

const resetPasswordBody = z.object({
  email: z.string().trim().email(),
  password: z.string().min(1),
  password_confirmation: z.string().min(1),
  resetToken: z.string().optional(),
});

function publicUser(user: AuthUser) {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    userType: user.userType,
    profilePic: user.profilePic,
  };
}

async function sessionPayload(db: Queryable, user: AuthUser, secret: string) {
  const permission =
    user.userType.toLowerCase() === 'admin' ? null : await findPermissionData(db, user.userType);
  const website = await findWebsiteName(db);
  return {
    accessToken: signAccessToken(user.id, secret),
    user: publicUser(user),
    menu: menuForUser(user.userType, permission),
    website,
  };
}

function setRefreshCookie(reply: FastifyReply, token: string, maxAgeSeconds: number): void {
  reply.setCookie(REFRESH_COOKIE, token, {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    secure: false,
    maxAge: maxAgeSeconds,
  });
}

function clearRefreshCookie(reply: FastifyReply): void {
  reply.clearCookie(REFRESH_COOKIE, { path: '/' });
}

function setResetCookie(reply: FastifyReply, token: string, maxAgeSeconds: number): void {
  reply.setCookie(RESET_COOKIE, token, {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    secure: false,
    maxAge: maxAgeSeconds,
  });
}

function clearResetCookie(reply: FastifyReply): void {
  reply.clearCookie(RESET_COOKIE, { path: '/' });
}

function bearerToken(request: FastifyRequest): string | null {
  const header = request.headers.authorization;
  if (!header?.startsWith('Bearer ')) {
    return null;
  }
  const token = header.slice('Bearer '.length).trim();
  return token.length > 0 ? token : null;
}

function generateOtp(): string {
  return String(randomInt(0, 1_000_000)).padStart(6, '0');
}

function maskEmail(email: string): string {
  const parts = email.split('@');
  if (parts.length !== 2) {
    return email;
  }
  const name = parts[0] ?? '';
  const visible = Math.max(1, Math.min(2, name.length));
  return `${name.slice(0, visible)}${'*'.repeat(Math.max(1, name.length - visible))}@${parts[1]}`;
}

async function sendPasswordResetOtpEmail(
  db: Queryable,
  user: AuthUser,
  email: string,
): Promise<boolean> {
  const otp = generateOtp();
  const expireMinutes = 10;
  await savePasswordResetOtp(db, user.id, otp, expireMinutes * 60);

  const settings = (await getAllSettings(db)) as MailSettings & Record<string, string>;
  const template = await findEmailTemplate(db, 'forgot_password');
  let body =
    template?.html && template.html.trim() ? template.html : DEFAULT_OTP_EMAIL_BODY;
  body = renderTemplate(body, {
    user_name: user.name,
    action_url: '',
    sender_name: settings.company_name || '',
    website_name: settings.website || 'JConnect',
    otp,
    otp_expire_minutes: expireMinutes,
    varification_link: '',
    url_link: '',
  });
  if (!body.includes(otp)) {
    body += `<p>OTP: <strong>${otp}</strong></p>`;
  }

  return sendHtmlMail(settings, email, 'Reset password OTP', body);
}

export async function registerAuthRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.post('/auth/login', async (request, reply) => {
    const parsed = loginBody.safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Email and password are required' },
      });
    }

    const user = await findUserByLogin(db, parsed.data.email);
    const matches = user ? await verifyPassword(parsed.data.password, user.passwordHash) : false;
    if (!user || !matches) {
      return reply.code(401).send({
        error: { code: 'INVALID_CREDENTIALS', message: 'Invalid details' },
      });
    }

    const refresh = issueRefreshToken(user.id, parsed.data.remember === true);
    setRefreshCookie(reply, refresh.token, refresh.maxAgeSeconds);
    return reply.code(200).send({ data: await sessionPayload(db, user, authSecret) });
  });

  app.post('/auth/refresh', async (request, reply) => {
    const userId = readRefreshToken(request.cookies[REFRESH_COOKIE]);
    if (userId === null) {
      clearRefreshCookie(reply);
      return reply.code(401).send({
        error: { code: 'UNAUTHORIZED', message: 'Sign in required' },
      });
    }
    const user = await findUserById(db, userId);
    if (!user) {
      clearRefreshCookie(reply);
      return reply.code(401).send({
        error: { code: 'UNAUTHORIZED', message: 'Sign in required' },
      });
    }
    return reply.code(200).send({ data: await sessionPayload(db, user, authSecret) });
  });

  app.post('/auth/logout', async (request, reply) => {
    revokeRefreshToken(request.cookies[REFRESH_COOKIE]);
    clearRefreshCookie(reply);
    return reply.code(200).send({ data: { ok: true } });
  });

  app.get('/auth/me', async (request, reply) => {
    const token = bearerToken(request);
    const payload = token ? readAccessToken(token, authSecret) : null;
    if (!payload) {
      return reply.code(401).send({
        error: { code: 'UNAUTHORIZED', message: 'Sign in required' },
      });
    }
    const user = await findUserById(db, payload.sub);
    if (!user) {
      return reply.code(401).send({
        error: { code: 'UNAUTHORIZED', message: 'Sign in required' },
      });
    }
    const session = await sessionPayload(db, user, authSecret);
    return reply.code(200).send({
      data: { user: session.user, menu: session.menu, website: session.website },
    });
  });

  app.post('/auth/forgot-password', async (request, reply) => {
    const parsed = emailBody.safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'A valid email is required' },
      });
    }
    const email = parsed.data.email.toLowerCase();
    const existingSession = readResetSession(request.cookies[RESET_COOKIE]);
    if (
      existingSession &&
      existingSession.email === email &&
      Date.now() - existingSession.lastSentAt < 60_000
    ) {
      return reply.code(429).send({
        error: { code: 'RATE_LIMITED', message: 'Please wait before resend' },
      });
    }

    const user = await findUserByEmail(db, email);
    if (!user) {
      return reply.code(404).send({
        error: { code: 'ACCOUNT_NOT_FOUND', message: 'This account does not exist' },
      });
    }

    const sent = await sendPasswordResetOtpEmail(db, user, email);
    if (!sent) {
      return reply.code(500).send({
        error: { code: 'EMAIL_FAILED', message: 'Failed to send email' },
      });
    }

    const session = issueResetSession(email);
    setResetCookie(reply, session.token, session.maxAgeSeconds);
    return reply.send({
      data: {
        ok: true,
        email,
        maskedEmail: maskEmail(email),
      },
    });
  });

  app.post('/auth/resend-otp', async (request, reply) => {
    const parsed = emailBody.safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'A valid email is required' },
      });
    }
    const email = parsed.data.email.toLowerCase();
    let session = readResetSession(request.cookies[RESET_COOKIE]);
    if (!session || session.email !== email) {
      return reply.code(400).send({
        error: { code: 'OTP_SESSION_REQUIRED', message: 'Start forgot password first' },
      });
    }
    if (Date.now() - session.lastSentAt < 60_000) {
      return reply.code(429).send({
        error: { code: 'RATE_LIMITED', message: 'Please wait before resend' },
      });
    }

    const user = await findUserByEmail(db, email);
    if (!user) {
      return reply.code(404).send({
        error: { code: 'ACCOUNT_NOT_FOUND', message: 'This account does not exist' },
      });
    }

    const sent = await sendPasswordResetOtpEmail(db, user, email);
    if (!sent) {
      return reply.code(500).send({
        error: { code: 'EMAIL_FAILED', message: 'Failed to send email' },
      });
    }

    session = touchResetSent(request.cookies[RESET_COOKIE]) ?? session;
    setResetCookie(reply, request.cookies[RESET_COOKIE]!, Math.floor((session.expiresAt - Date.now()) / 1000));
    return reply.send({
      data: { ok: true, email, maskedEmail: maskEmail(email) },
    });
  });

  app.post('/auth/verify-otp', async (request, reply) => {
    const parsed = otpBody.safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Email and otp are required' },
      });
    }
    const email = parsed.data.email.toLowerCase();
    const otp = parsed.data.otp.replace(/\D/g, '');
    const session = readResetSession(request.cookies[RESET_COOKIE]);
    if (!session || session.email !== email) {
      return reply.code(400).send({
        error: { code: 'OTP_SESSION_REQUIRED', message: 'Start forgot password first' },
      });
    }
    if (session.attempts >= 5) {
      return reply.code(429).send({
        error: { code: 'TOO_MANY_ATTEMPTS', message: 'OTP too many attempts' },
      });
    }

    const result = await verifyPasswordResetOtp(db, email, otp);
    if (result === 'ok') {
      await consumePasswordResetOtp(db, email);
      markResetVerified(request.cookies[RESET_COOKIE]);
      const resetToken = signResetVerifiedToken(email, authSecret);
      return reply.send({
        data: {
          ok: true,
          verified: true,
          email,
          resetToken,
        },
      });
    }

    bumpResetAttempts(request.cookies[RESET_COOKIE]);
    if (result === 'expired') {
      return reply.code(400).send({
        error: { code: 'OTP_EXPIRED', message: 'OTP expired' },
      });
    }
    return reply.code(400).send({
      error: { code: 'INVALID_OTP', message: 'Invalid OTP' },
    });
  });

  app.post('/auth/reset-password', async (request, reply) => {
    const parsed = resetPasswordBody.safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Email, password and confirmation are required' },
      });
    }
    if (parsed.data.password !== parsed.data.password_confirmation) {
      return reply.code(400).send({
        error: {
          code: 'PASSWORD_MISMATCH',
          message: 'Password and confirm password should be same',
        },
      });
    }

    const email = parsed.data.email.toLowerCase();
    const session = readResetSession(request.cookies[RESET_COOKIE]);
    const tokenEmail = readResetVerifiedToken(parsed.data.resetToken, authSecret);
    const verified =
      (session?.verified === true && session.email === email) || tokenEmail === email;

    if (!verified) {
      return reply.code(403).send({
        error: { code: 'OTP_NOT_VERIFIED', message: 'OTP verification required' },
      });
    }

    const ok = await resetPasswordForEmail(db, email, parsed.data.password);
    if (!ok) {
      return reply.code(500).send({
        error: { code: 'RESET_FAILED', message: 'Unable to update password' },
      });
    }

    clearResetSession(request.cookies[RESET_COOKIE]);
    clearResetCookie(reply);
    return reply.send({ data: { ok: true } });
  });
}
