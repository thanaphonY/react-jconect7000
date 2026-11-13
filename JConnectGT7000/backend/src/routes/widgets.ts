import type { FastifyInstance } from 'fastify';
import { z } from 'zod';
import { requireUserId } from '../auth/requireUser.js';
import type { Queryable } from '../db.js';
import { listSeries } from '../repositories/series.js';
import { deleteWidget, findWidget, insertWidget, listWidgets, updateWidget } from '../repositories/widgets.js';

const widgetBody = z.object({
  name: z.string().trim().min(1),
  widgetType: z.string().trim().min(1),
  config: z.record(z.string(), z.unknown()).optional(),
});

export async function registerWidgetRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/widgets', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listWidgets(db) });
  });

  app.get('/widgets/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const widget = await findWidget(db, id);
    if (!widget) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Widget not found' } });
    }
    return reply.code(200).send({ data: widget });
  });

  app.post('/widgets', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const parsed = widgetBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({ error: { code: 'INVALID', message: 'Name and widget type are required' } });
    }
    const created = await insertWidget(db, { ...parsed.data, config: parsed.data.config ?? {} });
    return reply.code(201).send({ data: created });
  });

  app.patch('/widgets/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const parsed = widgetBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({ error: { code: 'INVALID', message: 'Name and widget type are required' } });
    }
    const id = Number((request.params as { id: string }).id);
    const updated = await updateWidget(db, id, { ...parsed.data, config: parsed.data.config ?? {} });
    if (!updated) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Widget not found' } });
    }
    return reply.code(200).send({ data: updated });
  });

  app.delete('/widgets/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    const result = await deleteWidget(db, id);
    if (result === 'missing') {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Widget not found' } });
    }
    if (result === 'in_use') {
      return reply.code(409).send({ error: { code: 'IN_USE', message: 'Widget is used by a dashboard' } });
    }
    return reply.code(200).send({ data: { id } });
  });

  app.get('/series', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const query = request.query as { key?: string; limit?: string };
    const key = (query.key ?? 'temperature').trim() || 'temperature';
    const requested = Number(query.limit ?? 200);
    const limit = Number.isFinite(requested) ? Math.min(Math.max(requested, 1), 500) : 200;
    const points = await listSeries(db, key, limit);
    return reply.code(200).send({ data: { key, points } });
  });
}
