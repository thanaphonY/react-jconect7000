import type { FastifyInstance } from 'fastify';
import { z } from 'zod';
import { requireUserId } from '../auth/requireUser.js';
import type { Queryable } from '../db.js';
import {
  createLorawanApplication,
  deleteLorawanApplication,
  fetchLorawanDeviceProfiles,
  listLorawanApplications,
  updateLorawanApplication,
} from '../repositories/lorawan.js';

const createBody = z.object({
  name: z.string().trim().min(1),
  description: z.string().optional().nullable().default(''),
  tenantId: z.string().trim().min(1),
});

const updateBody = z.object({
  name: z.string().trim().min(1),
  description: z.string().optional().nullable().default(''),
});

export async function registerLorawanRoutes(
  app: FastifyInstance,
  db: Queryable,
  authSecret: string,
): Promise<void> {
  app.get('/lorawan/applications', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    return reply.code(200).send({ data: await listLorawanApplications(db) });
  });

  app.post('/lorawan/applications', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const parsed = createBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }

    const result = await createLorawanApplication(db, {
      name: parsed.data.name,
      description: parsed.data.description ?? '',
      tenantId: parsed.data.tenantId,
    });

    if ('error' in result) {
      const status = result.code === 'CHIRPSTACK_ERROR' ? 502 : 500;
      return reply.code(status).send({ error: { code: result.code, message: result.error } });
    }
    return reply.code(201).send({ data: result.application });
  });

  app.put('/lorawan/applications/:applicationId', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const applicationId = (request.params as { applicationId: string }).applicationId;
    if (!applicationId) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Application id required' } });
    }

    const parsed = updateBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: parsed.error.issues[0]?.message ?? 'Invalid input' },
      });
    }

    const result = await updateLorawanApplication(db, applicationId, {
      name: parsed.data.name,
      description: parsed.data.description ?? '',
    });

    if ('error' in result) {
      const status =
        result.code === 'NOT_FOUND' ? 404 : result.code === 'CHIRPSTACK_ERROR' ? 502 : 500;
      return reply.code(status).send({ error: { code: result.code, message: result.error } });
    }
    return reply.code(200).send({ data: result.application });
  });

  app.delete('/lorawan/applications/:applicationId', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const applicationId = (request.params as { applicationId: string }).applicationId;
    if (!applicationId) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: 'Application id required' } });
    }

    const result = await deleteLorawanApplication(db, applicationId);
    if ('error' in result) {
      const status =
        result.code === 'NOT_FOUND' ? 404 : result.code === 'CHIRPSTACK_ERROR' ? 502 : 500;
      return reply.code(status).send({ error: { code: result.code, message: result.error } });
    }
    return reply.code(200).send({ data: { success: true } });
  });

  app.get('/lorawan/device-profiles', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const tenantId = String((request.query as { tenantId?: string }).tenantId ?? '').trim();
    if (!tenantId) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'Tenant ID is required' },
      });
    }

    const result = await fetchLorawanDeviceProfiles(tenantId);
    if ('error' in result) {
      return reply.code(502).send({ error: { code: result.code, message: result.error } });
    }
    return reply.code(200).send({
      data: {
        result: result.profiles,
        totalCount: result.totalCount,
        tenantId,
      },
    });
  });
}
