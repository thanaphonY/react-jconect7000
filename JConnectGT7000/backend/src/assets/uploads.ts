import { createWriteStream } from 'node:fs';
import { access, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { pipeline } from 'node:stream/promises';
import { fileURLToPath } from 'node:url';
import { randomBytes } from 'node:crypto';
import type { Readable } from 'node:stream';
import { contentTypeFor, safeImageBasename } from './images.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/** backend/src/assets → repo root/frontend/uploads */
export const UPLOADS_ROOT = path.resolve(__dirname, '../../../frontend/uploads');
export const MAPS_DIR = path.join(UPLOADS_ROOT, 'maps');
export const MIMICS_DIR = path.join(UPLOADS_ROOT, 'mimics');
export const DEVICES_DIR = path.join(UPLOADS_ROOT, 'devices');
export const DEVICE_TEMPLATES_DIR = path.join(UPLOADS_ROOT, 'device_templates');

export type UploadSubdir = 'maps' | 'mimics' | 'devices' | 'device_templates';

const MAP_EXT = new Set(['.jpg', '.jpeg', '.png', '.gif']);
const MAP_MIME = new Set(['image/jpeg', 'image/jpg', 'image/png', 'image/gif']);
const DEVICE_EXT = new Set(['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg']);
const DEVICE_MIME = new Set([
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/gif',
  'image/webp',
  'image/svg+xml',
  'application/octet-stream',
  '',
]);

function dirFor(subdir: UploadSubdir): string {
  switch (subdir) {
    case 'maps':
      return MAPS_DIR;
    case 'mimics':
      return MIMICS_DIR;
    case 'devices':
      return DEVICES_DIR;
    case 'device_templates':
      return DEVICE_TEMPLATES_DIR;
  }
}

export function safeUploadBasename(filename: string): string | null {
  return safeImageBasename(filename);
}

export function resolveUploadPath(subdir: UploadSubdir, filename: string): string | null {
  const base = safeUploadBasename(filename);
  if (!base) {
    return null;
  }
  const dir = dirFor(subdir);
  const resolved = path.resolve(dir, base);
  if (!resolved.startsWith(dir + path.sep) && resolved !== dir) {
    return null;
  }
  return resolved;
}

export { contentTypeFor };

export async function uploadFileExists(subdir: UploadSubdir, filename: string): Promise<boolean> {
  const dest = resolveUploadPath(subdir, filename);
  if (!dest) return false;
  try {
    await access(dest);
    return true;
  } catch {
    return false;
  }
}

export async function saveMapImage(
  originalName: string,
  stream: Readable,
  mimetype: string,
): Promise<{ filename: string } | { error: string }> {
  const base = safeUploadBasename(originalName);
  if (!base) {
    return { error: 'Invalid filename' };
  }
  const ext = path.extname(base).toLowerCase();
  if (!MAP_EXT.has(ext) || (mimetype && !MAP_MIME.has(mimetype.toLowerCase()))) {
    return { error: 'Invalid file type. Only JPG, PNG, and GIF are allowed' };
  }
  const filename = `map_${randomBytes(8).toString('hex')}${ext === '.jpeg' ? '.jpg' : ext}`;
  const dest = resolveUploadPath('maps', filename);
  if (!dest) {
    return { error: 'Invalid filename' };
  }
  await mkdir(MAPS_DIR, { recursive: true });
  await pipeline(stream, createWriteStream(dest));
  return { filename };
}

export async function saveSvgWidget(
  originalName: string,
  stream: Readable,
  mimetype: string,
  buffer: Buffer,
): Promise<{ filename: string } | { error: string }> {
  const base = safeUploadBasename(originalName) ?? 'svg-widget.svg';
  const ext = path.extname(base).toLowerCase();
  const allowedMime = new Set(['image/svg+xml', 'text/xml', 'application/xml', 'application/octet-stream', '']);
  if (ext !== '.svg' || (mimetype && !allowedMime.has(mimetype.toLowerCase()))) {
    return { error: 'Invalid file type. Only SVG is allowed' };
  }
  const text = buffer.toString('utf8');
  if (!/<svg[\s>]/i.test(text)) {
    return { error: 'Uploaded file is not a valid SVG' };
  }
  const stem = path
    .basename(base, ext)
    .replace(/[^a-zA-Z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
  const filename = `${stem || 'svg-widget'}-${randomBytes(6).toString('hex')}.svg`;
  const dest = resolveUploadPath('mimics', filename);
  if (!dest) {
    return { error: 'Invalid filename' };
  }
  await mkdir(MIMICS_DIR, { recursive: true });
  await pipeline(stream, createWriteStream(dest));
  return { filename };
}

/** Mirror PHP device upload: encrypt_name into frontend/uploads/devices/. */
export async function saveDeviceImage(
  originalName: string,
  stream: Readable,
  mimetype: string,
): Promise<{ filename: string } | { error: string }> {
  const base = safeUploadBasename(originalName);
  if (!base) {
    return { error: 'Invalid filename' };
  }
  const ext = path.extname(base).toLowerCase();
  if (!DEVICE_EXT.has(ext) || (mimetype && !DEVICE_MIME.has(mimetype.toLowerCase()))) {
    return { error: 'Invalid file type. Only JPG, PNG, GIF, WEBP, and SVG are allowed' };
  }
  const filename = `${randomBytes(16).toString('hex')}${ext === '.jpeg' ? '.jpg' : ext}`;
  const dest = resolveUploadPath('devices', filename);
  if (!dest) {
    return { error: 'Invalid filename' };
  }
  await mkdir(DEVICES_DIR, { recursive: true });
  await pipeline(stream, createWriteStream(dest));
  return { filename };
}

/** Mirror PHP Devicetemplate::uploadImage into frontend/uploads/device_templates/. */
export async function saveDeviceTemplateImage(
  originalName: string,
  stream: Readable,
  mimetype: string,
): Promise<{ filename: string } | { error: string }> {
  const base = safeUploadBasename(originalName);
  if (!base) {
    return { error: 'Invalid filename' };
  }
  const ext = path.extname(base).toLowerCase();
  if (!DEVICE_EXT.has(ext) || (mimetype && !DEVICE_MIME.has(mimetype.toLowerCase()))) {
    return { error: 'Invalid file type. Only JPG, PNG, GIF, WEBP, and SVG are allowed' };
  }
  const filename = `${randomBytes(16).toString('hex')}${ext === '.jpeg' ? '.jpg' : ext}`;
  const dest = resolveUploadPath('device_templates', filename);
  if (!dest) {
    return { error: 'Invalid filename' };
  }
  await mkdir(DEVICE_TEMPLATES_DIR, { recursive: true });
  await pipeline(stream, createWriteStream(dest));
  return { filename };
}
