# Existing JConnect database: initial read-only foundation

Run from this directory so godotenv loads the local `.env`. Configure `DB_PASSWORD` only in environment or the ignored `.env`, never in source or React.

```
go run ./cmd/inspect
go run ./cmd/server
```

`GET http://127.0.0.1:3000/api/health` pings the existing database. Pool maximum: 5 open / 2 idle. All connections default to read-only. No migration is run. Bind address is local loopback only.

Generated models come from the live public schema snapshot, not SQL dumps. They explicitly map table/column names, nullable values and existing primary keys. JSONB maps to GORM datatypes.JSON; numeric values use strings to avoid floating-point loss. Associations are generated only from confirmed live foreign keys, including self references. JSON field output is disabled for these persistence structs: handlers must use explicit safe response DTOs.

This is the initial database foundation, NOT a completed PHP migration. PHP handlers, login/logout, CRUD, report jobs and page parity have not yet been implemented or verified. Existing services/callers remain unchanged. No writes or CRUD tests may run against existing production data.

Full metadata: `go run ./cmd/schema -out audit/database-schema.json`. Regenerate models: `python3 scripts/generate_models.py`, then `gofmt -w internal/models`.
