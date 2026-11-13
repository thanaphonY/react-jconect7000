import bcrypt from 'bcryptjs';

/** PHP password_hash() may emit $2y$. bcryptjs compares the $2a$ form. */
export function normalizePhpHash(hash: string): string {
  if (hash.startsWith('$2y$')) {
    return `$2a$${hash.slice(4)}`;
  }
  return hash;
}

export async function verifyPassword(plain: string, hash: string | null): Promise<boolean> {
  if (!hash) {
    return false;
  }
  return bcrypt.compare(plain, normalizePhpHash(hash));
}

/** Match PHP password_hash(..., PASSWORD_DEFAULT) cost 10. */
export async function hashPassword(plain: string): Promise<string> {
  const hash = await bcrypt.hash(plain, 10);
  return hash.replace(/^\$2a\$/, '$2y$');
}
