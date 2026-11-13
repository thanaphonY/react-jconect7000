const path = require("path");

require("dotenv").config({
  path: process.env.ALARM_ENGINE_ENV_PATH || path.join(__dirname, ".env"),
});

module.exports = {
  pg: {
    host: process.env.PGHOST || process.env.PG_HOST || "localhost",
    port: Number(process.env.PGPORT || process.env.PG_PORT || 5432),
    user: process.env.PGUSER || process.env.PG_USER || "jconnect",
    password: process.env.PGPASSWORD || process.env.PG_PASSWORD || "P@ssword-001",
    database: process.env.PGDATABASE || process.env.PG_DATABASE || "jconnect",
  },
  alarmWorkerPort: Number(process.env.ALARM_WORKER_PORT || 3004),
};
