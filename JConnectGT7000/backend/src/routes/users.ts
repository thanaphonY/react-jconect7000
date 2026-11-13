import type { FastifyInstance, FastifyReply, FastifyRequest } from 'fastify';
import { z } from 'zod';
import { actorCan, actorHasAllRead, resolveActor, sendForbidden } from '../auth/actor.js';
import { verifyPassword } from '../auth/password.js';
import type { Queryable } from '../db.js';
import { parseBodyFields, resolveUploadedFilename } from '../http/multipart.js';
import {
  DEFAULT_INVITE_EMAIL_BODY,
  renderTemplate,
  sendHtmlMail,
  type MailSettings,
} from '../mail/sendMail.js';
import { findEmailTemplate, getAllSettings } from '../repositories/settings.js';
import {
  createUser,
  emailExists,
  findUserById,
  getSettingValue,
  insertPendingInvite,
  listUsers,
  listUsersForExport,
  listUserTypes,
  randomInviteKey,
  softDeleteUser,
  softDeleteUsers,
  updateUser,
  userCreatedBy,
  userStatistics,
} from '../repositories/users.js';

function idParam(raw: string): number | null {
  const id = Number(raw);
  return Number.isInteger(id) && id > 0 ? id : null;
}

function csvEscape(value: string | null | undefined): string {
  const text = value ?? '';
  if (/[",\n\r]/.test(text)) {
    return `"${text.replace(/"/g, '""')}"`;
  }
  return text;
}

function inviteBaseUrl(): string {
  return (process.env.PUBLIC_APP_URL || process.env.APP_BASE_URL || 'http://127.0.0.1').replace(
    /\/$/,
    '',
  );
}

function applyInviteTemplate(html: string, email: string, link: string): string {
  let body = renderTemplate(html, {
    user_email: email,
  });
  // PHP InvitePeople uses the typo {var_inviation_link}
  body = body.split('{var_inviation_link}').join(link);
  return body;
}

async function handleBulkDelete(
  request: FastifyRequest,
  reply: FastifyReply,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  const actor = await resolveActor(request, reply, db, authSecret);
  if (!actor) return;
  if (!actorCan(actor, 'user', 'own_delete') && !actorCan(actor, 'user', 'all_delete')) {
    return sendForbidden(reply);
  }
  const parsed = z
    .object({
      ids: z.array(z.number().int().positive()).min(1),
    })
    .safeParse(request.body ?? {});
  if (!parsed.success) {
    return reply.code(400).send({
      error: { code: 'INVALID_INPUT', message: 'ids must be a non-empty array of user ids' },
    });
  }
  const ids = [...new Set(parsed.data.ids)].filter((id) => id !== actor.user.id);
  if (ids.length === 0) {
    return reply.code(400).send({
      error: { code: 'CANNOT_DELETE_SELF', message: 'Cannot delete your own account' },
    });
  }
  const deletedIds = await softDeleteUsers(db, ids);
  return reply.send({ data: { ok: true, deletedIds } });
}

export async function registerUserRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/users/statistics', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_read') && !actorCan(actor, 'user', 'all_read')) {
      return sendForbidden(reply);
    }
    return reply.send({ data: await userStatistics(db) });
  });

  app.get('/users/types', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_read') && !actorCan(actor, 'user', 'all_read')) {
      return sendForbidden(reply);
    }
    const types = await listUserTypes(db);
    return reply.send({ data: { userTypes: types } });
  });

  app.get('/users/export', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_read') && !actorCan(actor, 'user', 'all_read')) {
      return sendForbidden(reply);
    }
    const query = request.query as Record<string, string | undefined>;
    const format = (query.format || 'excel').toLowerCase();
    if (format !== 'excel' && format !== 'csv') {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'format must be excel or csv' },
      });
    }
    const items = await listUsersForExport(db, {
      search: query.search?.trim() || undefined,
      userType: query.user_type?.trim() || undefined,
      status: query.status?.trim() || undefined,
      viewerId: actor.user.id,
      allRead: actorHasAllRead(actor, 'user'),
    });
    const header = ['ID', 'Name', 'Email', 'User Type', 'Status', 'Created Date'];
    const lines = [
      header.join(','),
      ...items.map((row) =>
        [
          String(row.id),
          csvEscape(row.name),
          csvEscape(row.email),
          csvEscape(row.userType),
          csvEscape(row.status),
          csvEscape(row.createDate),
        ].join(','),
      ),
    ];
    const body = `\uFEFF${lines.join('\r\n')}`;
    reply.header('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
    reply.header('Content-Disposition', 'attachment; filename="users.xls"');
    return reply.send(body);
  });

  app.get('/users', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_read') && !actorCan(actor, 'user', 'all_read')) {
      return sendForbidden(reply);
    }
    const query = request.query as Record<string, string | undefined>;
    const page = Math.max(1, Number(query.page) || 1);
    const pageSize = Math.min(100, Math.max(1, Number(query.pageSize) || 10));
    const result = await listUsers(db, {
      page,
      pageSize,
      search: query.search?.trim() || undefined,
      userType: query.user_type?.trim() || undefined,
      status: query.status?.trim() || undefined,
      viewerId: actor.user.id,
      allRead: actorHasAllRead(actor, 'user'),
    });
    return reply.send({
      data: {
        items: result.items,
        total: result.total,
        page,
        pageSize,
      },
    });
  });

  app.post('/users/invite', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_create') && !actorCan(actor, 'user', 'all_create')) {
      return sendForbidden(reply);
    }
    const parsed = z
      .object({
        emails: z.string().min(1),
      })
      .safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'emails is required (comma-separated)' },
      });
    }

    const emailArray = parsed.data.emails
      .split(',')
      .map((e) => e.trim())
      .filter(Boolean);
    const result = {
      existCount: 0,
      seccessCount: 0,
      invalidEmailCount: 0,
      noTemplate: 0 as number | string,
    };

    const template = await findEmailTemplate(db, 'invitation');
    const templateHtml =
      template?.html && template.html.trim() ? template.html : DEFAULT_INVITE_EMAIL_BODY;

    const settings = (await getAllSettings(db)) as MailSettings & Record<string, string>;
    const varKey = randomInviteKey();
    const base = inviteBaseUrl();

    for (const mailValue of emailArray) {
      if (!z.string().email().safeParse(mailValue).success) {
        result.invalidEmailCount += 1;
        continue;
      }
      if (await emailExists(db, mailValue)) {
        result.existCount += 1;
        continue;
      }
      const link = `<a href="${base}/user/registration?invited=${encodeURIComponent(varKey)}">Click here</a>`;
      const html = applyInviteTemplate(templateHtml, mailValue, link);
      const sent = await sendHtmlMail(settings, mailValue, 'Invitation for registration', html);
      if (sent) {
        await insertPendingInvite(db, mailValue, varKey);
        result.seccessCount += 1;
      }
    }

    return reply.send({ data: result });
  });

  app.post('/users/bulk-delete', async (request, reply) => {
    return handleBulkDelete(request, reply, db, authSecret);
  });

  app.delete('/users', async (request, reply) => {
    return handleBulkDelete(request, reply, db, authSecret);
  });

  app.get('/users/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_read') && !actorCan(actor, 'user', 'all_read')) {
      return sendForbidden(reply);
    }
    const id = idParam((request.params as { id: string }).id);
    if (id === null) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid user id' } });
    }
    const user = await findUserById(db, id);
    if (!user || user.userType.toLowerCase() === 'admin') {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'User not found' } });
    }
    if (!actorHasAllRead(actor, 'user')) {
      const creator = await userCreatedBy(db, id);
      if (creator !== String(actor.user.id)) {
        return sendForbidden(reply);
      }
    }
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

  app.post('/users', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_create') && !actorCan(actor, 'user', 'all_create')) {
      return sendForbidden(reply);
    }

    const { fields, files } = await parseBodyFields(request);
    const parsed = z
      .object({
        name: z.string().trim().min(1),
        email: z.string().trim().email(),
        user_type: z.string().trim().min(1),
        password: z.string().min(1),
        status: z.string().optional(),
      })
      .safeParse({
        name: fields.name,
        email: fields.email,
        user_type: fields.user_type,
        password: fields.password,
        status: fields.status,
      });
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Name, email, user_type and password are required' },
      });
    }
    if (parsed.data.user_type.toLowerCase() === 'admin') {
      return sendForbidden(reply, 'Cannot create admin users');
    }
    if (await emailExists(db, parsed.data.email)) {
      return reply.code(409).send({
        error: { code: 'EMAIL_EXISTS', message: 'This email already registered with us' },
      });
    }

    const profilePic = await resolveUploadedFilename(
      files,
      'profile_pic',
      fields.fileOld,
      'user.png',
    );
    const adminApproval = (await getSettingValue(db, 'admin_approval')) === '1';
    const id = await createUser(db, {
      name: parsed.data.name,
      email: parsed.data.email,
      userType: parsed.data.user_type,
      password: parsed.data.password,
      profilePic,
      status: parsed.data.status,
      createdByUserId: actor.user.id,
      adminApproval,
    });
    const created = await findUserById(db, id);
    return reply.code(201).send({
      data: {
        id,
        name: created?.name ?? parsed.data.name,
        email: created?.email ?? parsed.data.email,
        userType: created?.userType ?? parsed.data.user_type,
        status: created?.status ?? null,
        profilePic: created?.profilePic ?? profilePic,
      },
    });
  });

  app.put('/users/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_update') && !actorCan(actor, 'user', 'all_update')) {
      return sendForbidden(reply);
    }
    const id = idParam((request.params as { id: string }).id);
    if (id === null) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid user id' } });
    }
    const existing = await findUserById(db, id);
    if (!existing || existing.userType.toLowerCase() === 'admin') {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'User not found' } });
    }

    const { fields, files } = await parseBodyFields(request);
    const parsed = z
      .object({
        name: z.string().trim().min(1),
        email: z.string().trim().email(),
        user_type: z.string().trim().min(1),
        status: z.string().optional(),
        password: z.string().optional(),
        currentpassword: z.string().optional(),
        confirmPassword: z.string().optional(),
      })
      .safeParse({
        name: fields.name,
        email: fields.email,
        user_type: fields.user_type,
        status: fields.status,
        password: fields.password,
        currentpassword: fields.currentpassword,
        confirmPassword: fields.confirmPassword,
      });
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Name, email and user_type are required' },
      });
    }
    if (parsed.data.user_type.toLowerCase() === 'admin') {
      return sendForbidden(reply, 'Cannot set user_type to admin');
    }
    if (await emailExists(db, parsed.data.email, id)) {
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

    await updateUser(db, id, {
      name: parsed.data.name,
      email: parsed.data.email,
      userType: parsed.data.user_type,
      status: parsed.data.status,
      profilePic,
      password: newPassword,
    });
    const updated = await findUserById(db, id);
    return reply.send({
      data: {
        id,
        name: updated?.name,
        email: updated?.email,
        userType: updated?.userType,
        status: updated?.status ?? null,
        profilePic: updated?.profilePic,
      },
    });
  });

  app.post('/users/:id/avatar', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_update') && !actorCan(actor, 'user', 'all_update')) {
      return sendForbidden(reply);
    }
    const id = idParam((request.params as { id: string }).id);
    if (id === null) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid user id' } });
    }
    const existing = await findUserById(db, id);
    if (!existing) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'User not found' } });
    }
    const { fields, files } = await parseBodyFields(request);
    if (!files.profile_pic && !files.avatar) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'profile_pic file is required' },
      });
    }
    const profilePic = await resolveUploadedFilename(
      files,
      files.profile_pic ? 'profile_pic' : 'avatar',
      fields.fileOld,
      existing.profilePic || 'user.png',
    );
    await updateUser(db, id, {
      name: existing.name,
      email: existing.email,
      profilePic,
    });
    return reply.send({ data: { id, profilePic } });
  });

  app.delete('/users/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'user', 'own_delete') && !actorCan(actor, 'user', 'all_delete')) {
      return sendForbidden(reply);
    }
    const id = idParam((request.params as { id: string }).id);
    if (id === null) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid user id' } });
    }
    if (id === actor.user.id) {
      return reply.code(400).send({
        error: { code: 'CANNOT_DELETE_SELF', message: 'Cannot delete your own account' },
      });
    }
    const existing = await findUserById(db, id);
    if (!existing || existing.userType.toLowerCase() === 'admin') {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'User not found' } });
    }
    const ok = await softDeleteUser(db, id);
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'User not found' } });
    }
    return reply.send({ data: { ok: true, id } });
  });
}
