import type { FastifyInstance } from 'fastify';
import { z } from 'zod';
import { requireUserId } from '../auth/requireUser.js';
import type { Queryable } from '../db.js';
import {
  deleteCalculation,
  listCalculationPage,
  saveCalculation,
} from '../repositories/calculations.js';

const saveBody = z.object({
  deviceId: z.coerce.number().int().positive(),
  originalDeviceId: z.coerce.number().int().nonnegative().optional().nullable(),
  originalKey: z.string().optional().nullable(),
  key: z.string().trim().min(1),
  label: z.string().optional().nullable(),
  unit: z.string().optional().nullable(),
  datatype: z.enum(['float', 'int']).optional().nullable(),
  formula: z.string().trim().min(1),
  inputs: z.array(z.record(z.string(), z.unknown())).min(1),
  triggerMode: z.enum(['interval', 'on_pull', 'schedule']).optional().nullable(),
  intervalSec: z.coerce.number().int().positive().optional().nullable(),
  cron: z.string().optional().nullable(),
  decimals: z.coerce.number().int().min(0).max(8).optional().nullable(),
  maxAgeSec: z.union([z.coerce.number(), z.string(), z.null()]).optional().nullable(),
});

export async function registerCalculationRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/calculations', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listCalculationPage(db) });
  });

  app.post('/calculations', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const parsed = saveBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: {
          code: 'INVALID_INPUT',
          message: parsed.error.issues[0]?.message ?? 'Invalid input',
        },
      });
    }

    const result = await saveCalculation(db, parsed.data);
    if ('error' in result) {
      const status =
        result.code === 'NOT_FOUND'
          ? 404
          : result.code === 'DUPLICATE_KEY'
            ? 409
            : 400;
      return reply.code(status).send({ error: { code: result.code, message: result.error } });
    }
    return reply.code(200).send({ data: result.calculation });
  });

  app.delete('/calculations/:deviceId/:key', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const params = request.params as { deviceId: string; key: string };
    const deviceId = Number(params.deviceId);
    const key = decodeURIComponent(params.key ?? '');
    if (!Number.isFinite(deviceId) || deviceId <= 0 || !key.trim()) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Device and metric key are required.' },
      });
    }

    const result = await deleteCalculation(db, deviceId, key);
    if ('error' in result) {
      const status = result.code === 'NOT_FOUND' ? 404 : 400;
      return reply.code(status).send({ error: { code: result.code, message: result.error } });
    }
    return reply.code(200).send({ data: result });
  });
}
