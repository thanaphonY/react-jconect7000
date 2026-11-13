import { createReadStream } from 'node:fs';
import { Readable } from 'node:stream';
import type { FastifyInstance } from 'fastify';
import { requireUserId } from '../auth/requireUser.js';
import {
  contentTypeFor,
  resolveUploadPath,
  saveDeviceImage,
  saveDeviceTemplateImage,
  saveMapImage,
  saveSvgWidget,
  uploadFileExists,
  type UploadSubdir,
} from '../assets/uploads.js';
import { parseBodyFields } from '../http/multipart.js';

function invalidFilename(reply: { code: (n: number) => { send: (b: unknown) => unknown } }) {
  return reply.code(400).send({
    error: { code: 'INVALID_FILENAME', message: 'Invalid filename' },
  });
}

async function serveUpload(
  reply: {
    code: (n: number) => { send: (b: unknown) => unknown };
    header: (k: string, v: string) => void;
    send: (payload: unknown) => unknown;
  },
  subdir: UploadSubdir,
  raw: string,
) {
  if (raw.includes('..') || raw.includes('/') || raw.includes('\\') || raw.includes('%')) {
    return invalidFilename(reply);
  }
  const filePath = resolveUploadPath(subdir, raw);
  if (!filePath) {
    return invalidFilename(reply);
  }
  if (!(await uploadFileExists(subdir, raw))) {
    return reply.code(404).send({
      error: { code: 'NOT_FOUND', message: 'File not found' },
    });
  }
  reply.header('Content-Type', contentTypeFor(raw));
  reply.header('Cache-Control', 'public, max-age=86400');
  return reply.send(createReadStream(filePath));
}

export async function registerUploadRoutes(
  app: FastifyInstance,
  authSecret: string,
): Promise<void> {
  app.post('/uploads/map-image', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const { files } = await parseBodyFields(request);
    const file = files.image ?? files.file;
    if (!file) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'No file uploaded' },
      });
    }
    const buffer = await file.toBuffer();
    if (buffer.length > 10 * 1024 * 1024) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'File size too large. Maximum size is 10MB' },
      });
    }
    const saved = await saveMapImage(file.filename, Readable.from(buffer), file.mimetype);
    if ('error' in saved) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: saved.error } });
    }
    return reply.code(201).send({
      data: {
        filename: saved.filename,
        path: `/api/v1/uploads/maps/${saved.filename}`,
      },
    });
  });

  app.post('/uploads/svg-widget', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const { files } = await parseBodyFields(request);
    const file = files.svg ?? files.file;
    if (!file) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'No file uploaded' },
      });
    }
    const buffer = await file.toBuffer();
    if (buffer.length > 2 * 1024 * 1024) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'File size too large. Maximum size is 2MB' },
      });
    }
    const saved = await saveSvgWidget(file.filename, Readable.from(buffer), file.mimetype, buffer);
    if ('error' in saved) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: saved.error } });
    }
    return reply.code(201).send({
      data: {
        filename: saved.filename,
        path: `/api/v1/uploads/mimics/${saved.filename}`,
      },
    });
  });

  app.post('/uploads/devices', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const { files } = await parseBodyFields(request);
    const file = files.image ?? files.file;
    if (!file) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'No file uploaded' },
      });
    }
    const buffer = await file.toBuffer();
    if (buffer.length > 2 * 1024 * 1024) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'File size too large. Maximum size is 2MB' },
      });
    }
    const saved = await saveDeviceImage(file.filename, Readable.from(buffer), file.mimetype);
    if ('error' in saved) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: saved.error } });
    }
    return reply.code(201).send({
      data: {
        filename: saved.filename,
        path: `/api/v1/uploads/devices/${saved.filename}`,
        image: saved.filename,
      },
    });
  });

  app.post('/uploads/device-templates', async (request, reply) => {
    if (requireUserId(request, reply, authSecret) === null) return;
    const { files } = await parseBodyFields(request);
    const file = files.image ?? files.file;
    if (!file) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'No file uploaded' },
      });
    }
    const buffer = await file.toBuffer();
    if (buffer.length > 2 * 1024 * 1024) {
      return reply.code(400).send({
        error: { code: 'INVALID_INPUT', message: 'File size too large. Maximum size is 2MB' },
      });
    }
    const saved = await saveDeviceTemplateImage(file.filename, Readable.from(buffer), file.mimetype);
    if ('error' in saved) {
      return reply.code(400).send({ error: { code: 'INVALID_INPUT', message: saved.error } });
    }
    return reply.code(201).send({
      data: {
        filename: saved.filename,
        path: `/api/v1/uploads/device-templates/${saved.filename}`,
        image: saved.filename,
      },
    });
  });

  app.get('/uploads/maps/:file', async (request, reply) => {
    const raw = (request.params as { file: string }).file;
    return serveUpload(reply, 'maps', raw);
  });

  app.get('/uploads/mimics/:file', async (request, reply) => {
    const raw = (request.params as { file: string }).file;
    return serveUpload(reply, 'mimics', raw);
  });

  app.get('/uploads/devices/:file', async (request, reply) => {
    const raw = (request.params as { file: string }).file;
    return serveUpload(reply, 'devices', raw);
  });

  app.get('/uploads/device-templates/:file', async (request, reply) => {
    const raw = (request.params as { file: string }).file;
    return serveUpload(reply, 'device_templates', raw);
  });
}
