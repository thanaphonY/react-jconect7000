import { createReadStream } from 'node:fs';
import { access } from 'node:fs/promises';
import type { FastifyInstance } from 'fastify';
import type { Queryable } from '../db.js';
import { getBranding } from '../repositories/settings.js';
import { contentTypeFor, resolveImagePath, safeImageBasename } from '../assets/images.js';

export async function registerBrandingRoutes(app: FastifyInstance, db: Queryable): Promise<void> {
  app.get('/branding', async (_request, reply) => {
    const branding = await getBranding(db);
    return reply.code(200).send({ data: branding });
  });
}

export async function registerAssetRoutes(app: FastifyInstance): Promise<void> {
  app.get('/assets/images/:filename', async (request, reply) => {
    const raw = (request.params as { filename: string }).filename;
    if (raw.includes('..') || raw.includes('/') || raw.includes('\\') || raw.includes('%')) {
      return reply.code(400).send({
        error: { code: 'INVALID_FILENAME', message: 'Invalid filename' },
      });
    }
    const base = safeImageBasename(raw);
    if (!base || base !== raw) {
      return reply.code(400).send({
        error: { code: 'INVALID_FILENAME', message: 'Invalid filename' },
      });
    }
    const filePath = resolveImagePath(base);
    if (!filePath) {
      return reply.code(400).send({
        error: { code: 'INVALID_FILENAME', message: 'Invalid filename' },
      });
    }
    try {
      await access(filePath);
    } catch {
      return reply.code(404).send({
        error: { code: 'NOT_FOUND', message: 'Image not found' },
      });
    }
    reply.header('Content-Type', contentTypeFor(base));
    reply.header('Cache-Control', 'public, max-age=86400');
    return reply.send(createReadStream(filePath));
  });
}
