import type { FastifyInstance } from 'fastify';
import { z } from 'zod';
import { requireUserId } from '../auth/requireUser.js';
import type { Queryable } from '../db.js';
import {
  createChannel,
  DEFAULT_HEALTH_TEMPLATE,
  deleteHealthCheck,
  deliverHealthCheck,
  getChannelById,
  getHealthCheck,
  getNotificationSummary,
  listChannelDevices,
  listChannelMessages,
  listChannels,
  listHealthChecks,
  previewChannelTemplate,
  renderHealthCheck,
  saveHealthCheck,
  sendTestViaAlarmEngine,
  softDeleteChannel,
  updateChannel,
  writeHealthCheckLog,
} from '../repositories/notifications.js';

const channelBody = z.object({
  name: z.string().trim().min(1),
  type: z.enum(['email', 'line', 'line_oa', 'telegram', 'webhook']),
  isActive: z.boolean().optional().default(true),
  messageTemplate: z.string().optional().nullable().default(''),
  description: z.string().optional().nullable().default(''),
  config: z.record(z.string(), z.unknown()).optional().default({}),
});

const testSendBody = z.object({
  message: z.string().optional().nullable(),
});

const healthBody = z.object({
  id: z.coerce.number().int().nonnegative().optional().nullable(),
  name: z.string().trim().min(1),
  isActive: z.boolean().optional().default(true),
  scheduleType: z.enum(['daily', 'weekly', 'monthly']).optional().default('daily'),
  scheduleTime: z.string().optional().default('08:00'),
  scheduleWeekday: z.coerce.number().int().min(1).max(7).optional().default(1),
  scheduleDay: z.coerce.number().int().min(1).max(28).optional().default(1),
  channelIds: z.array(z.coerce.number().int().positive()).optional().default([]),
  messageTemplate: z.string().optional().nullable().default(''),
});

export async function registerNotificationRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/notification-channels', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listChannels(db) });
  });

  app.get('/notification-channels/summary', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await getNotificationSummary(db) });
  });

  app.get('/notification-channels/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid channel id' } });
    }
    const channel = await getChannelById(db, id);
    if (!channel) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Channel not found' } });
    }
    return reply.code(200).send({ data: channel });
  });

  app.post('/notification-channels', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const parsed = channelBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const created = await createChannel(db, {
      name: parsed.data.name,
      type: parsed.data.type,
      isActive: parsed.data.isActive,
      messageTemplate: parsed.data.messageTemplate ?? '',
      description: parsed.data.description ?? '',
      config: parsed.data.config ?? {},
    });
    return reply.code(201).send({ data: created });
  });

  app.put('/notification-channels/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid channel id' } });
    }
    const parsed = channelBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const updated = await updateChannel(db, id, {
      name: parsed.data.name,
      type: parsed.data.type,
      isActive: parsed.data.isActive,
      messageTemplate: parsed.data.messageTemplate ?? '',
      description: parsed.data.description ?? '',
      config: parsed.data.config ?? {},
    });
    if (!updated) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Channel not found' } });
    }
    return reply.code(200).send({ data: updated });
  });

  app.delete('/notification-channels/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid channel id' } });
    }
    const ok = await softDeleteChannel(db, id);
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Channel not found' } });
    }
    return reply.code(200).send({ data: { success: true } });
  });

  app.get('/notification-channels/:id/devices', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid channel id' } });
    }
    return reply.code(200).send({ data: await listChannelDevices(db, id) });
  });

  app.get('/notification-channels/:id/messages', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid channel id' } });
    }
    return reply.code(200).send({ data: await listChannelMessages(db, id) });
  });

  app.get('/notification-channels/:id/template', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid channel id' } });
    }
    const channel = await getChannelById(db, id);
    if (!channel) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Channel not found' } });
    }
    return reply.code(200).send({
      data: { preview: previewChannelTemplate(channel.messageTemplate) },
    });
  });

  app.post('/notification-channels/:id/test-send', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid channel id' } });
    }
    const channel = await getChannelById(db, id);
    if (!channel) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Channel not found' } });
    }
    const parsed = testSendBody.safeParse(request.body ?? {});
    const message =
      parsed.success && parsed.data.message?.trim()
        ? parsed.data.message.trim()
        : 'Test notification from JConnect System';
    try {
      const result = await sendTestViaAlarmEngine(id, message);
      if (!result.success) {
        return reply.code(200).send({
          data: {
            success: false,
            error: result.error || 'Failed to send test message',
            channel: result.channel ?? channel.name,
            type: result.type ?? channel.type,
          },
        });
      }
      return reply.code(200).send({
        data: {
          success: true,
          message: result.message || 'Test message sent successfully',
          channel: result.channel ?? channel.name,
          type: result.type ?? channel.type,
        },
      });
    } catch {
      return reply.code(200).send({
        data: {
          success: false,
          error: 'Failed to connect to alarm worker service',
          channel: channel.name,
          type: channel.type,
        },
      });
    }
  });

  app.get('/notification-health-checks', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({
      data: {
        checks: await listHealthChecks(db),
        channels: await listChannels(db),
        defaultTemplate: DEFAULT_HEALTH_TEMPLATE,
      },
    });
  });

  app.post('/notification-health-checks', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const parsed = healthBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Name is required' },
      });
    }
    const id = await saveHealthCheck(db, {
      id: parsed.data.id ?? 0,
      name: parsed.data.name,
      isActive: parsed.data.isActive,
      scheduleType: parsed.data.scheduleType,
      scheduleTime: parsed.data.scheduleTime,
      scheduleWeekday: parsed.data.scheduleWeekday,
      scheduleDay: parsed.data.scheduleDay,
      channelIds: parsed.data.channelIds,
      messageTemplate: parsed.data.messageTemplate ?? '',
    });
    if (!id) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Name is required' },
      });
    }
    return reply.code(200).send({ data: { success: true, id, message: 'Saved' } });
  });

  app.delete('/notification-health-checks/:id', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid id' } });
    }
    const ok = await deleteHealthCheck(db, id);
    if (!ok) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Not found' } });
    }
    return reply.code(200).send({ data: { success: true } });
  });

  app.post('/notification-health-checks/preview', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const parsed = healthBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }
    const rendered = await renderHealthCheck(db, {
      name: parsed.data.name,
      messageTemplate: parsed.data.messageTemplate?.trim() || DEFAULT_HEALTH_TEMPLATE,
      timezone: 'Asia/Bangkok',
    });
    return reply.code(200).send({ data: { success: true, ...rendered } });
  });

  app.post('/notification-health-checks/:id/send', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const id = Number((request.params as { id: string }).id);
    if (!Number.isFinite(id)) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Invalid id' } });
    }
    const row = await getHealthCheck(db, id);
    if (!row) {
      return reply.code(404).send({ error: { code: 'NOT_FOUND', message: 'Not found' } });
    }
    const rendered = await renderHealthCheck(db, row);
    const channels = await deliverHealthCheck(db, row, rendered);
    const ok = await writeHealthCheckLog(db, row.id, rendered, channels);
    return reply.code(200).send({
      data: {
        success: ok,
        message: rendered.message,
        channels,
      },
    });
  });
}
