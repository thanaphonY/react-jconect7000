import type { FastifyInstance } from 'fastify';
import { z } from 'zod';
import { resolveActor } from '../auth/actor.js';
import { verifyPassword } from '../auth/password.js';
import type { Queryable } from '../db.js';
import { parseBodyFields, resolveUploadedFilename } from '../http/multipart.js';
import { emailExists, findUserById, updateUser } from '../repositories/users.js';

export async function registerProfileRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/profile', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    const user = actor.user;
    return reply.send({
      data: {
        id: user.id,
        name: user.name,
        email: user.email,
        userType: user.userType,
        status: user.status ?? null,
        profilePic: user.profilePic,
      },
    });
  });

  app.put('/profile', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;

    const { fields, files } = await parseBodyFields(request);
    const parsed = z
      .object({
        name: z.string().trim().min(1),
        email: z.string().trim().email(),
        password: z.string().optional(),
        currentpassword: z.string().optional(),
        confirmPassword: z.string().optional(),
      })
      .safeParse({
        name: fields.name,
        email: fields.email,
        password: fields.password,
        currentpassword: fields.currentpassword,
        confirmPassword: fields.confirmPassword,
      });
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Name and email are required' },
      });
    }

    const existing = await findUserById(db, actor.user.id);
    if (!existing) {
      return reply.code(401).send({
        error: { code: 'UNAUTHORIZED', message: 'Sign in required' },
      });
    }

    if (await emailExists(db, parsed.data.email, actor.user.id)) {
      return reply.code(409).send({
        error: { code: 'EMAIL_EXISTS', message: 'This email already registered with us' },
      });
    }

    let newPassword: string | undefined;
    if (parsed.data.password) {
      if (!parsed.data.currentpassword) {
        return reply.code(400).send({
          error: { code: 'CURRENT_PASSWORD_REQUIRED', message: 'Current password is required' },
        });
      }
      const ok = await verifyPassword(parsed.data.currentpassword, existing.passwordHash);
      if (!ok) {
        return reply.code(400).send({
          error: { code: 'INVALID_CURRENT_PASSWORD', message: 'Enter valid current password' },
        });
      }
      if (parsed.data.password !== parsed.data.confirmPassword) {
        return reply.code(400).send({
          error: {
            code: 'PASSWORD_MISMATCH',
            message: 'Password and confirm password should be same',
          },
        });
      }
      newPassword = parsed.data.password;
    }

    const profilePic = await resolveUploadedFilename(
      files,
      'profile_pic',
      fields.fileOld,
      existing.profilePic || 'user.png',
    );

    // Profile keeps user_type/status readonly (mirror addEditProfile for self)
    await updateUser(db, actor.user.id, {
      name: parsed.data.name,
      email: parsed.data.email,
      profilePic,
      password: newPassword,
    });

    const updated = await findUserById(db, actor.user.id);
    return reply.send({
      data: {
        id: updated?.id,
        name: updated?.name,
        email: updated?.email,
        userType: updated?.userType,
        status: updated?.status ?? null,
        profilePic: updated?.profilePic,
      },
    });
  });
}
