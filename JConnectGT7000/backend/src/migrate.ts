import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Client } from 'pg';
import { env } from './config.js';
import { splitSqlStatements } from './splitSql.js';

export function migrationsDirectory(): string {
  const here = path.dirname(fileURLToPath(import.meta.url));
  return path.resolve(here, '../../Database/migrations');
}

export async function listMigrationFiles(directory: string): Promise<string[]> {
  const entries = await readdir(directory);
  return entries.filter((name) => name.endsWith('.sql')).sort(compareMigrationNames);
}

function compareMigrationNames(left: string, right: string): number {
  const leftNumber = leadingNumber(left);
  const rightNumber = leadingNumber(right);
  if (leftNumber !== rightNumber) {
    return leftNumber - rightNumber;
  }
  return left.localeCompare(right);
}

function leadingNumber(filename: string): number {
  const match = /^(\d+)/.exec(filename);
  return match ? Number(match[1]) : Number.MAX_SAFE_INTEGER;
}

function isUndefinedTable(err: unknown): boolean {
  return typeof err === 'object' && err !== null && 'code' in err && err.code === '42P01';
}

async function loadAppliedFilenames(client: Client): Promise<Set<string>> {
  try {
    const result = await client.query<{ filename: string }>(
      'SELECT filename FROM schema_migrations',
    );
    return new Set(result.rows.map((row) => row.filename));
  } catch (err) {
    if (isUndefinedTable(err)) {
      return new Set();
    }
    throw err;
  }
}

async function applyMigration(
  client: Client,
  directory: string,
  filename: string,
): Promise<void> {
  const sql = await readFile(path.join(directory, filename), 'utf8');
  const statements = splitSqlStatements(sql);

  await client.query('BEGIN');
  try {
    for (const statement of statements) {
      await client.query(statement);
    }
    await client.query('INSERT INTO schema_migrations (filename) VALUES ($1)', [filename]);
    await client.query('COMMIT');
  } catch (err) {
    await client.query('ROLLBACK');
    throw err;
  }
}

async function main(): Promise<void> {
  const directory = migrationsDirectory();
  const files = await listMigrationFiles(directory);
  const client = new Client({
    connectionString: env.DATABASE_URL,
    application_name: 'jconnect-backend-migrate',
  });

  await client.connect();
  try {
    const applied = await loadAppliedFilenames(client);
    let appliedCount = 0;

    for (const filename of files) {
      if (applied.has(filename)) {
        console.log(`${filename} already applied`);
        continue;
      }
      await applyMigration(client, directory, filename);
      appliedCount += 1;
      console.log(`${filename} applied`);
    }

    console.log(`${appliedCount} migration(s) applied`);
  } finally {
    await client.end();
  }
}

main().catch((err: unknown) => {
  const message = err instanceof Error ? err.message : String(err);
  console.error(message);
  process.exit(1);
});
