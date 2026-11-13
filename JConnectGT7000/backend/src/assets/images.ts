import { createWriteStream } from 'node:fs';
import { access, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { pipeline } from 'node:stream/promises';
import { fileURLToPath } from 'node:url';
import type { Readable } from 'node:stream';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/** backend/src/assets → repo root/frontend/assets/images */
export const IMAGES_DIR = path.resolve(__dirname, '../../../frontend/assets/images');

const ALLOWED_EXT = new Set(['.gif', '.jpg', '.jpeg', '.png', '.ico', '.webp', '.svg', '.pdf']);

export function safeImageBasename(filename: string): string | null {
  const base = path.basename(filename);
  if (!base || base === '.' || base === '..' || base.includes('\0')) {
    return null;
  }
  if (base !== filename.replace(/^.*[/\\]/, '')) {
    return null;
  }
  return base;
}

export function resolveImagePath(filename: string): string | null {
  const base = safeImageBasename(filename);
  if (!base) {
    return null;
  }
  const resolved = path.resolve(IMAGES_DIR, base);
  if (!resolved.startsWith(IMAGES_DIR + path.sep) && resolved !== IMAGES_DIR) {
    return null;
  }
  return resolved;
}

export function contentTypeFor(filename: string): string {
  const ext = path.extname(filename).toLowerCase();
  switch (ext) {
    case '.png':
      return 'image/png';
    case '.jpg':
    case '.jpeg':
      return 'image/jpeg';
    case '.gif':
      return 'image/gif';
    case '.ico':
      return 'image/x-icon';
    case '.webp':
      return 'image/webp';
    case '.svg':
      return 'image/svg+xml';
    case '.pdf':
      return 'application/pdf';
    default:
      return 'application/octet-stream';
  }
}

/** Mirror PHP upload(): `{originalWithoutExt}_{unix}.{ext}` into assets/images. */
export async function saveUploadedImage(
  originalName: string,
  stream: Readable,
  nowSeconds = Math.floor(Date.now() / 1000),
): Promise<string | null> {
  const base = safeImageBasename(originalName);
  if (!base) {
    return null;
  }
  const ext = path.extname(base).toLowerCase();
  if (!ALLOWED_EXT.has(ext)) {
    return null;
  }
  const stem = path.basename(base, ext).replace(/\s+/g, '_').toLowerCase();
  const newname = `${stem}_${nowSeconds}${ext}`;
  const dest = resolveImagePath(newname);
  if (!dest) {
    return null;
  }
  await mkdir(IMAGES_DIR, { recursive: true });
  await pipeline(stream, createWriteStream(dest));
  return newname;
}

export async function imageExists(filename: string): Promise<boolean> {
  const dest = resolveImagePath(filename);
  if (!dest) {
    return false;
  }
  try {
    await access(dest);
    return true;
  } catch {
    return false;
  }
}
