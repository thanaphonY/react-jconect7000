import type { FastifyInstance, FastifyReply } from 'fastify';
import { z } from 'zod';
import { actorCan, resolveActor, sendForbidden } from '../auth/actor.js';
import type { Queryable } from '../db.js';
import { parseBodyFields, resolveUploadedFilename } from '../http/multipart.js';
import {
  deletePermissionRole,
  getAdminPermissionData,
  getAppSettingsMap,
  insertPermissionRole,
  listAllPermissionRoles,
  listPermissionRoles,
  renamePermissionRole,
  updatePermissionData,
  updateSettingsMap,
} from '../repositories/settings.js';

const THEME_COOKIE = 'theme_color';

async function requireSettingAccess(
  request: Parameters<typeof resolveActor>[0],
  reply: FastifyReply,
  db: Queryable,
  authSecret: string,
) {
  const actor = await resolveActor(request, reply, db, authSecret);
  if (!actor) return null;
  if (!actor.isAdmin && !actorCan(actor, 'setting', 'all_read') && !actorCan(actor, 'setting', 'own_read')) {
    void sendForbidden(reply);
    return null;
  }
  return actor;
}

export async function registerSettingsRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/settings', async (request, reply) => {
    if (!(await requireSettingAccess(request, reply, db, authSecret))) return;
    const settings = await getAppSettingsMap(db);
    return reply.send({ data: settings });
  });

  app.put('/settings/general', async (request, reply) => {
    const actor = await requireSettingAccess(request, reply, db, authSecret);
    if (!actor) return;
    if (!actor.isAdmin && !actorCan(actor, 'setting', 'all_update') && !actorCan(actor, 'setting', 'own_update')) {
      return sendForbidden(reply);
    }

    const { fields, files } = await parseBodyFields(request);
    const website = fields.website?.trim();
    if (!website) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Website is required' },
      });
    }

    let userTypeValue = fields.user_type ?? '[]';
    if (fields['user_type[]']) {
      // multipart may send repeated fields — already flattened; accept JSON string
      userTypeValue = fields['user_type[]'];
    }
    try {
      const parsed = JSON.parse(userTypeValue) as unknown;
      if (!Array.isArray(parsed)) {
        // single string value → wrap
        userTypeValue = JSON.stringify([userTypeValue]);
      } else {
        userTypeValue = JSON.stringify(parsed.map(String));
      }
    } catch {
      if (userTypeValue.includes(',')) {
        userTypeValue = JSON.stringify(userTypeValue.split(',').map((s) => s.trim()).filter(Boolean));
      } else if (userTypeValue) {
        userTypeValue = JSON.stringify([userTypeValue]);
      } else {
        userTypeValue = '[]';
      }
    }

    const logo = await resolveUploadedFilename(files, 'logo', fields.fileOldlogo, '');
    const registerAllowed = fields.register_allowed === '1' || fields.register_allowed === 'true' ? '1' : '0';
    const adminApproval = fields.admin_approval === '1' || fields.admin_approval === 'true' ? '1' : '0';

    const data: Record<string, string> = {
      website,
      register_allowed: registerAllowed,
      admin_approval: adminApproval,
      user_type: userTypeValue,
    };
    if (logo) {
      data.logo = logo;
    } else if (fields.fileOldlogo) {
      data.logo = fields.fileOldlogo;
    }

    await updateSettingsMap(db, data);
    return reply.send({ data: await getAppSettingsMap(db) });
  });

  app.put('/settings/email', async (request, reply) => {
    const actor = await requireSettingAccess(request, reply, db, authSecret);
    if (!actor) return;
    if (!actor.isAdmin && !actorCan(actor, 'setting', 'all_update') && !actorCan(actor, 'setting', 'own_update')) {
      return sendForbidden(reply);
    }

    const { fields } = await parseBodyFields(request);
    const parsed = z
      .object({
        mail_setting: z.enum(['php_mailer', 'simple_mail']),
        company_name: z.string().optional().default(''),
        SMTP_EMAIL: z.string().optional().default(''),
        HOST: z.string().optional().default(''),
        PORT: z.string().optional().default(''),
        SMTP_SECURE: z.string().optional().default(''),
        SMTP_PASSWORD: z.string().optional().default(''),
        EMAIL: z.string().optional().default(''),
      })
      .safeParse(fields);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'mail_setting is required' },
      });
    }
    await updateSettingsMap(db, parsed.data);
    return reply.send({ data: await getAppSettingsMap(db) });
  });

  app.get('/settings/permissions', async (request, reply) => {
    if (!(await requireSettingAccess(request, reply, db, authSecret))) return;
    const roles = await listPermissionRoles(db);
    return reply.send({
      data: {
        roles: roles.map((role) => ({
          id: role.id,
          userType: role.userType,
          data: role.data,
        })),
      },
    });
  });

  app.put('/settings/permissions', async (request, reply) => {
    const actor = await requireSettingAccess(request, reply, db, authSecret);
    if (!actor) return;
    if (!actor.isAdmin && !actorCan(actor, 'setting', 'all_update') && !actorCan(actor, 'setting', 'own_update')) {
      return sendForbidden(reply);
    }

    const body = (request.body ?? {}) as { data?: Record<string, Record<string, Record<string, string>>> };
    if (!body.data || typeof body.data !== 'object') {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'data permission map is required' },
      });
    }

    for (const [userTypeRaw, modules] of Object.entries(body.data)) {
      const userType = userTypeRaw.replace(/_SPACE_/g, ' ');
      const normalized: Record<string, Record<string, string>> = {};
      for (const [moduleRaw, methods] of Object.entries(modules)) {
        const moduleName = moduleRaw.replace(/_SPACE_/g, ' ');
        normalized[moduleName] = methods;
      }
      await updatePermissionData(db, userType, JSON.stringify(normalized));
    }

    const roles = await listPermissionRoles(db);
    return reply.send({
      data: {
        roles: roles.map((role) => ({
          id: role.id,
          userType: role.userType,
          data: role.data,
        })),
      },
    });
  });

  app.put('/settings/roles', async (request, reply) => {
    const actor = await requireSettingAccess(request, reply, db, authSecret);
    if (!actor) return;
    if (!actor.isAdmin && !actorCan(actor, 'setting', 'all_update') && !actorCan(actor, 'setting', 'own_update')) {
      return sendForbidden(reply);
    }

    const body = (request.body ?? {}) as {
      user_type_name?: Record<string, string>;
      new_user_name?: string[];
      rm_user_type?: Array<number | string>;
    };

    if (body.user_type_name && typeof body.user_type_name === 'object') {
      for (const [idRaw, name] of Object.entries(body.user_type_name)) {
        const id = Number(idRaw);
        if (Number.isInteger(id) && name.trim()) {
          await renamePermissionRole(db, id, name.trim());
        }
      }
    }

    if (Array.isArray(body.new_user_name)) {
      const adminData = await getAdminPermissionData(db);
      for (const name of body.new_user_name) {
        if (name?.trim()) {
          await insertPermissionRole(db, name.trim(), adminData);
        }
      }
    }

    if (Array.isArray(body.rm_user_type)) {
      for (const raw of body.rm_user_type) {
        const id = Number(raw);
        if (Number.isInteger(id)) {
          await deletePermissionRole(db, id);
        }
      }
    }

    const roles = await listAllPermissionRoles(db);
    return reply.send({
      data: {
        roles: roles
          .filter((role) => role.userType.toLowerCase() !== 'admin')
          .map((role) => ({
            id: role.id,
            userType: role.userType,
            data: role.data,
          })),
      },
    });
  });

  app.post('/settings/theme-color', async (request, reply) => {
    const parsed = z
      .object({ theme_color: z.string().trim().min(1) })
      .safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'theme_color is required' },
      });
    }
    reply.setCookie(THEME_COOKIE, parsed.data.theme_color, {
      path: '/',
      maxAge: 86400 * 7,
      sameSite: 'lax',
      httpOnly: false,
      secure: false,
    });
    return reply.send({
      data: {
        theme_color: parsed.data.theme_color,
        colorModeKey: 'jconnect_color_mode',
      },
    });
  });
}
