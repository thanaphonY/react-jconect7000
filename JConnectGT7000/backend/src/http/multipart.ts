import type { FastifyRequest } from 'fastify';
import type { Readable } from 'node:stream';
import { saveUploadedImage } from '../assets/images.js';

export type MultipartFields = Record<string, string>;

export interface ParsedMultipart {
  fields: MultipartFields;
  files: Record<string, { filename: string; stream: Readable }>;
}

function isMultipart(request: FastifyRequest): boolean {
  const type = request.headers['content-type'] ?? '';
  return type.includes('multipart/form-data');
}

export async function parseBodyFields(request: FastifyRequest): Promise<{
  fields: MultipartFields;
  files: Record<string, { filename: string; mimetype: string; toBuffer: () => Promise<Buffer> }>;
  isMultipart: boolean;
}> {
  if (!isMultipart(request)) {
    const body = (request.body ?? {}) as Record<string, unknown>;
    const fields: MultipartFields = {};
    for (const [key, value] of Object.entries(body)) {
      if (value === undefined || value === null) continue;
      if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        fields[key] = String(value);
      } else if (Array.isArray(value)) {
        fields[key] = JSON.stringify(value);
      } else {
        fields[key] = JSON.stringify(value);
      }
    }
    return { fields, files: {}, isMultipart: false };
  }

  const fields: MultipartFields = {};
  const files: Record<string, { filename: string; mimetype: string; toBuffer: () => Promise<Buffer> }> =
    {};

  const parts = request.parts();
  for await (const part of parts) {
    if (part.type === 'file') {
      const buffer = await part.toBuffer();
      files[part.fieldname] = {
        filename: part.filename,
        mimetype: part.mimetype,
        toBuffer: async () => buffer,
      };
    } else {
      fields[part.fieldname] = String(part.value ?? '');
    }
  }
  return { fields, files, isMultipart: true };
}

export async function resolveUploadedFilename(
  files: Record<string, { filename: string; toBuffer: () => Promise<Buffer> }>,
  fieldName: string,
  fileOld?: string,
  fallback = 'user.png',
): Promise<string> {
  const file = files[fieldName];
  if (file?.filename) {
    const { Readable } = await import('node:stream');
    const buf = await file.toBuffer();
    const saved = await saveUploadedImage(file.filename, Readable.from(buf));
    if (saved) {
      return saved;
    }
  }
  if (fileOld && fileOld.trim()) {
    return fileOld.trim();
  }
  return fallback;
}
