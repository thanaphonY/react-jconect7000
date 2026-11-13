const path = require("path");

require("dotenv").config({
  path: process.env.ALARM_ENGINE_ENV_PATH || path.join(__dirname, ".env"),
});

/** Resolve pg-connection-string whether npm hoists it or keeps it under `pg/`. */
function parsePgConn(str) {
  let mod;
  try {
    mod = require("pg-connection-string");
  } catch {
    mod = require(require.resolve("pg-connection-string", { paths: [require.resolve("pg")] }));
  }
  return mod.parse(str);
}

const SCRAM_HINT =
  "PostgreSQL SCRAM needs a non-empty password. Set PGPASSWORD, or use DATABASE_URL like postgresql://USER:PASSWORD@HOST:PORT/DB (URL-encode special characters in USER/PASSWORD). " +
  "Note: an empty password in .env is ignored by node-postgres when using discrete PG* fields.";

/**
 * PostgreSQL for alarm_engine + worker (index.js) — same DB as gen3/backend Prisma.
 * Prefer DATABASE_URL; otherwise PGHOST/PGPORT/PGUSER/PGPASSWORD/PGDATABASE
 * (also accepts PG_HOST, PG_PORT, PG_USER, PG_PASSWORD, PG_DATABASE).
 */
function buildPgConfig() {
  const url =
    process.env.DATABASE_URL ||
    process.env.ALARM_ENGINE_DATABASE_URL ||
    process.env.POSTGRES_URL ||
    "";
  const trimmed = String(url).trim();

  const envPassword = String(
    process.env.PGPASSWORD ??
      process.env.POSTGRES_PASSWORD ??
      process.env.PG_PASSWORD ??
      ""
  );

  if (trimmed) {
    let p;
    try {
      p = parsePgConn(trimmed);
    } catch (e) {
      throw new Error(`Invalid DATABASE_URL: ${e.message || e}`);
    }

    let password =
      p.password != null && String(p.password).length > 0 ? String(p.password) : envPassword;

    if (!password) {
      throw new Error(`gen3/alarm-engine: ${SCRAM_HINT}`);
    }

    const out = {
      host: p.host || undefined,
      port: p.port ? Number(p.port) : undefined,
      database: p.database || undefined,
      user: p.user || undefined,
      // Must be non-empty string: node-postgres `val("password")` treats "" as missing → null → SCRAM error.
      password,
    };
    if (p.ssl != null) out.ssl = p.ssl;
    return out;
  }

  const host =
    process.env.PGHOST ||
    process.env.POSTGRES_HOST ||
    process.env.PG_HOST ||
    "localhost";
  const port = Number(
    process.env.PGPORT || process.env.POSTGRES_PORT || process.env.PG_PORT || 5432
  );
  const user =
    process.env.PGUSER || process.env.POSTGRES_USER || process.env.PG_USER || "postgres";
  const password = envPassword;
  const database =
    process.env.PGDATABASE ||
    process.env.POSTGRES_DB ||
    process.env.PG_DATABASE ||
    "postgres";

  if (!password) {
    throw new Error(`gen3/alarm-engine: ${SCRAM_HINT}`);
  }

  return { host, port, user, password, database };
}

module.exports = {
  pg: buildPgConfig(),
  /** HTTP port for index.js worker (POST /api/send-test + queue loop). */
  alarmWorkerPort: Number(process.env.ALARM_WORKER_PORT || 3004),
};
