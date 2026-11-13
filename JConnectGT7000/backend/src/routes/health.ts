import type { FastifyInstance } from 'fastify';
import type { Queryable } from '../db.js';
import { pingDatabase } from '../repositories/health.js';

export async function registerHealthRoutes(
  app: FastifyInstance,
  pool: Queryable,
): Promise<void> {
  app.get('/health', async (_request, reply) => {
    try {
      await pingDatabase(pool);
      return reply.code(200).send({ data: { status: 'ok' } });
    } catch (err) {
      app.log.error({ err }, 'database health check failed');
      return reply.code(503).send({
        error: {
          code: 'DB_UNAVAILABLE',
          message: 'Database is unavailable',
        },
      });
    }
  });
}
