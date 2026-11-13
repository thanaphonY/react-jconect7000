import type { FastifyReply, FastifyRequest } from 'fastify';
import type { Queryable } from '../db.js';
import {
  canModuleMethod,
  canReadModule,
  isAdminUserType,
  parsePermissionData,
  type MenuItem,
} from './menu.js';
import { requestUserId, sendUnauthorized } from './requestUser.js';
import { findPermissionData, findUserById, type AuthUser } from '../repositories/users.js';

export type PermissionData = NonNullable<ReturnType<typeof parsePermissionData>>;

export interface Actor {
  user: AuthUser;
  permission: PermissionData | null;
  isAdmin: boolean;
}

export async function resolveActor(
  request: FastifyRequest,
  reply: FastifyReply,
  db: Queryable,
  secret: string,
): Promise<Actor | null> {
  const userId = requestUserId(request, secret);
  if (userId === null) {
    void sendUnauthorized(reply);
    return null;
  }
  const user = await findUserById(db, userId);
  if (!user) {
    void sendUnauthorized(reply);
    return null;
  }
  const isAdmin = isAdminUserType(user.userType);
  const permission = isAdmin ? null : await findPermissionData(db, user.userType);
  return { user, permission, isAdmin };
}

export function sendForbidden(reply: FastifyReply, message = 'You do not have permission to access') {
  return reply.code(403).send({
    error: { code: 'FORBIDDEN', message },
  });
}

export function actorCanRead(actor: Actor, moduleName: string): boolean {
  return actor.isAdmin || canReadModule(actor.permission, moduleName);
}

export function actorCan(actor: Actor, moduleName: string, method: string): boolean {
  return actor.isAdmin || canModuleMethod(actor.permission, moduleName, method);
}

export function actorHasAllRead(actor: Actor, moduleName: string): boolean {
  return actor.isAdmin || canModuleMethod(actor.permission, moduleName, 'all_read');
}

export type { MenuItem };
