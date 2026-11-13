import type { FastifyInstance, FastifyReply } from 'fastify';
import { z } from 'zod';
import {
  actorCan,
  resolveActor,
  sendForbidden,
  type Actor,
} from '../auth/actor.js';
import { requestUserId, sendUnauthorized } from '../auth/requestUser.js';
import type { Queryable } from '../db.js';
import {
  deleteLayout,
  findLayout,
  findUserHomeLayoutId,
  insertLayout,
  layoutItems,
  listLayouts,
  replaceLayoutItems,
  resolveHomeLayout,
  setUserHome,
  updateLayout,
  updateSortOrders,
  type LayoutItemInput,
  type LayoutSummary,
} from '../repositories/dashboards.js';

const layoutBody = z.object({
  name: z.string().trim().min(1),
  description: z.string().optional().default(''),
  sortOrder: z.number().int().nullable().optional(),
  sort_order: z.number().int().nullable().optional(),
});

const sortOrderBody = z.object({
  order: z.array(z.coerce.number().int().positive()).min(1),
});

const layoutItemBody = z.object({
  widget_id: z.union([z.number().int(), z.null()]).optional(),
  widgetId: z.union([z.number().int(), z.null()]).optional(),
  widget_type: z.string().optional(),
  widgetType: z.string().optional(),
  type: z.string().optional(),
  x: z.coerce.number().int(),
  y: z.coerce.number().int(),
  w: z.coerce.number().int().positive(),
  h: z.coerce.number().int().positive(),
  config: z.record(z.string(), z.unknown()).optional().default({}),
});

const layoutReplaceBody = z.object({
  items: z.array(layoutItemBody),
});

function canOwnOrAll(
  actor: Actor,
  ownMethod: string,
  allMethod: string,
  createdBy: number | null,
): boolean {
  if (actor.isAdmin) return true;
  if (actorCan(actor, 'dashboard', allMethod)) return true;
  if (actorCan(actor, 'dashboard', ownMethod) && createdBy === actor.user.id) return true;
  return false;
}

function canReadLayout(actor: Actor, layout: LayoutSummary): boolean {
  return canOwnOrAll(actor, 'own_read', 'all_read', layout.createdBy);
}

function filterReadable(actor: Actor, layouts: LayoutSummary[]): LayoutSummary[] {
  if (actor.isAdmin || actorCan(actor, 'dashboard', 'all_read')) {
    return layouts;
  }
  if (actorCan(actor, 'dashboard', 'own_read')) {
    return layouts.filter((layout) => layout.createdBy === actor.user.id);
  }
  return [];
}

function parseLayoutMeta(body: unknown) {
  const parsed = layoutBody.safeParse(body ?? {});
  if (!parsed.success) return null;
  return {
    name: parsed.data.name,
    description: parsed.data.description,
    sortOrder: parsed.data.sortOrder ?? parsed.data.sort_order ?? null,
  };
}

function toItemInput(raw: z.infer<typeof layoutItemBody>): LayoutItemInput {
  const widgetId = raw.widget_id ?? raw.widgetId ?? null;
  const widgetType = raw.widget_type ?? raw.widgetType ?? raw.type ?? '';
  return {
    widgetId,
    widgetType,
    x: raw.x,
    y: raw.y,
    w: raw.w,
    h: raw.h,
    config: raw.config ?? {},
  };
}

async function homePayload(db: Queryable, userId: number, layoutId?: number) {
  const layout = await resolveHomeLayout(db, userId, layoutId);
  if (!layout) {
    return null;
  }
  const homeLayoutId = await findUserHomeLayoutId(db, userId);
  const [layouts, items] = await Promise.all([
    listLayouts(db, homeLayoutId),
    layoutItems(db, layout.id),
  ]);
  return {
    layout,
    layouts,
    items,
    homeLayoutId: homeLayoutId ?? layouts[0]?.id ?? null,
  };
}

async function updateMetaHandler(
  request: { params: unknown; body: unknown },
  reply: FastifyReply,
  db: Queryable,
  authSecret: string,
) {
  const actor = await resolveActor(request as never, reply, db, authSecret);
  if (!actor) return;
  const id = Number((request.params as { id: string }).id);
  const meta = parseLayoutMeta(request.body);
  if (!Number.isInteger(id) || !meta) {
    return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Name is required' } });
  }
  const existing = await findLayout(db, id);
  if (!existing) {
    return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Dashboard layout not found' } });
  }
  if (!canOwnOrAll(actor, 'own_update', 'all_update', existing.createdBy)) {
    return sendForbidden(reply);
  }
  await updateLayout(db, id, {
    name: meta.name,
    description: meta.description,
    sortOrder: meta.sortOrder ?? existing.sortOrder,
  });
  const homeLayoutId = await findUserHomeLayoutId(db, actor.user.id);
  return reply.send({ data: await findLayout(db, id, homeLayoutId) });
}

export async function registerDashboardRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/home', async (request, reply) => {
    const userId = requestUserId(request, authSecret);
    if (!userId) {
      return sendUnauthorized(reply);
    }
    const data = await homePayload(db, userId);
    if (!data) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'No dashboard layout' } });
    }
    return reply.send({ data });
  });

  app.get('/home/:layoutId', async (request, reply) => {
    const userId = requestUserId(request, authSecret);
    if (!userId) {
      return sendUnauthorized(reply);
    }
    const layoutId = Number((request.params as { layoutId: string }).layoutId);
    if (!Number.isInteger(layoutId)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Layout id is required' } });
    }
    const data = await homePayload(db, userId, layoutId);
    if (!data) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Dashboard layout not found' } });
    }
    return reply.send({ data });
  });

  app.get('/dashboards/home', async (request, reply) => {
    const userId = requestUserId(request, authSecret);
    if (!userId) {
      return sendUnauthorized(reply);
    }
    const data = await homePayload(db, userId);
    if (!data) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'No dashboard layout' } });
    }
    return reply.send({ data: { layout: data.layout, items: data.items } });
  });

  // Static path before /dashboards/:id
  app.put('/dashboards/sort-order', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'dashboard', 'all_update') && !actorCan(actor, 'dashboard', 'own_update') && !actor.isAdmin) {
      return sendForbidden(reply);
    }
    const parsed = sortOrderBody.safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'order must be a non-empty array of layout ids' },
      });
    }
    const updated = await updateSortOrders(db, parsed.data.order);
    if (updated === 0) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Invalid order payload' },
      });
    }
    const homeLayoutId = await findUserHomeLayoutId(db, actor.user.id);
    return reply.send({ data: { layouts: await listLayouts(db, homeLayoutId) } });
  });

  app.get('/dashboards', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'dashboard', 'own_read') && !actorCan(actor, 'dashboard', 'all_read') && !actor.isAdmin) {
      return sendForbidden(reply);
    }
    const homeLayoutId = await findUserHomeLayoutId(db, actor.user.id);
    const layouts = filterReadable(actor, await listLayouts(db, homeLayoutId));
    return reply.send({
      data: {
        layouts,
        homeLayoutId: homeLayoutId ?? layouts[0]?.id ?? null,
      },
    });
  });

  app.get('/dashboards/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isInteger(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Layout id is required' } });
    }
    const homeLayoutId = await findUserHomeLayoutId(db, actor.user.id);
    const layout = await findLayout(db, id, homeLayoutId);
    if (!layout) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Dashboard layout not found' } });
    }
    if (!canReadLayout(actor, layout)) {
      return sendForbidden(reply);
    }
    const items = await layoutItems(db, id);
    return reply.send({ data: { layout, items } });
  });

  app.post('/dashboards', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    if (!actorCan(actor, 'dashboard', 'own_create') && !actorCan(actor, 'dashboard', 'all_create') && !actor.isAdmin) {
      return sendForbidden(reply);
    }
    const meta = parseLayoutMeta(request.body);
    if (!meta) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Name is required' } });
    }
    const id = await insertLayout(db, {
      name: meta.name,
      description: meta.description,
      createdBy: actor.user.id,
      sortOrder: meta.sortOrder,
    });
    const homeLayoutId = await findUserHomeLayoutId(db, actor.user.id);
    return reply.code(201).send({ data: await findLayout(db, id, homeLayoutId) });
  });

  app.put('/dashboards/:id', async (request, reply) => {
    return updateMetaHandler(request, reply, db, authSecret);
  });

  app.patch('/dashboards/:id', async (request, reply) => {
    return updateMetaHandler(request, reply, db, authSecret);
  });

  // Keep wave 2 POST update for compatibility
  app.post('/dashboards/:id', async (request, reply) => {
    return updateMetaHandler(request, reply, db, authSecret);
  });

  app.delete('/dashboards/:id', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    const id = Number((request.params as { id: string }).id);
    const existing = await findLayout(db, id);
    if (!existing) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Dashboard layout not found' } });
    }
    if (!canOwnOrAll(actor, 'own_delete', 'all_delete', existing.createdBy)) {
      return sendForbidden(reply);
    }
    await deleteLayout(db, id);
    return reply.send({ data: { ok: true } });
  });

  app.post('/dashboards/:id/home', async (request, reply) => {
    const userId = requestUserId(request, authSecret);
    if (!userId) {
      return sendUnauthorized(reply);
    }
    const id = Number((request.params as { id: string }).id);
    const existing = await findLayout(db, id);
    if (!existing) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Dashboard layout not found' } });
    }
    await setUserHome(db, userId, id);
    return reply.send({ data: { homeLayoutId: id } });
  });

  app.put('/dashboards/:id/layout', async (request, reply) => {
    const actor = await resolveActor(request, reply, db, authSecret);
    if (!actor) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isInteger(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Layout id is required' } });
    }
    const existing = await findLayout(db, id);
    if (!existing) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Dashboard layout not found' } });
    }
    if (!canOwnOrAll(actor, 'own_update', 'all_update', existing.createdBy)) {
      return sendForbidden(reply);
    }
    const parsed = layoutReplaceBody.safeParse(request.body ?? {});
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'items array is required' },
      });
    }
    const items = parsed.data.items.map(toItemInput);
    await replaceLayoutItems(db, id, items);
    const homeLayoutId = await findUserHomeLayoutId(db, actor.user.id);
    return reply.send({
      data: {
        layout: await findLayout(db, id, homeLayoutId),
        items: await layoutItems(db, id),
      },
    });
  });
}
