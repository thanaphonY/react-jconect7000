# JConnect React + Go migration: verified initial foundation

Date: 10 November 2026 (Asia/Bangkok).

## Work completed

New code is in `go-backend/` and `react-frontend/` within the existing JConnect project. Existing PHP, Fastify, Docker Compose, Nginx and other service source files were preserved. Existing working-tree edits and deleted `webapp/` files were not restored or overwritten.

- Go Fiber v2 + GORM PostgreSQL driver + godotenv.
- Required `.env.example`, `internal/config/config.go`, `internal/database/postgres.go` created.
- Password loaded only from DB_PASSWORD, with local .env ignored by Git and file mode 0600. No secret values in this report or source bundle.
- DSN safely encodes reserved characters. Pool: 5 open / 2 idle, 30-minute connection lifetime, 5-minute idle lifetime. Connection timeout: 5 seconds.
- Go connections default to read-only. No AutoMigrate or schema-changing command is called.
- `cmd/inspect` performs a real Ping and checks database identity/read-only setting.
- `cmd/schema` reads tables, columns, constraints, indexes, views/materialized views, function signatures, custom function definitions, triggers, hypertables and data-presence evidence.
- 41 persistence models generated from live metadata, including nullability, explicit names, primary/composite keys and 17 verified foreign-key associations. Numeric values use strings to preserve precision; JSONB uses GORM datatypes.JSON. Automatic timestamp writes are disabled.
- `scripts/generate_models.py` regenerates models from the inspected schema snapshot. No names are inferred from PHP alone.
- React + Vite + TypeScript connection page calls `/api/health`; Vite proxies `/api` to Go at 127.0.0.1:3000. No database credentials in React.

## Live database findings

Connected to existing `jconnect` as `jconnect` on 127.0.0.1:5433.

| Item | Verified result |
|---|---|
| PostgreSQL | 14.10 |
| TimescaleDB | 2.13.1 |
| Public tables | 41 |
| Columns | 340 |
| Constraints | 63 |
| Foreign keys | 17 |
| Indexes | 72 |
| Public views | 1 |
| Public function signatures | 96, including extension functions |
| Custom function definitions | 7 |
| Hypertables | 1: device_data, compression enabled |
| Tables containing data | 35; checked with EXISTS, without exporting row values |

`device_data` has a check requiring exactly one value field. `device_latest` uses composite primary key `(device_id, key)`. `user_site_scopes` uses composite primary key `(ia_users_id, site_node_id)`. These are captured in the live schema rather than replaced with assumed ID columns.

## Actual test results

| Test | Result |
|---|---|
| TCP access to existing PostgreSQL port | PASS |
| PostgreSQL authentication and SELECT 1 | PASS |
| Go GORM connection and pool Ping | PASS |
| Go live schema inspector | PASS |
| DSN encoding for reserved characters and read-only options | PASS |
| SELECT LIMIT 1 using each of 41 GORM models with association preloading | PASS; empty tables checked for valid queries, with no invented rows |
| `JCONNECT_INTEGRATION=1 go test ./... -count=1` | PASS |
| `go vet ./...` | PASS |
| Go HTTP GET /api/health | PASS: database connected, read-only |
| React TypeScript check and Vite production build | PASS with installed Node 22.23.4 |
| React getHealth client through Vite proxy to Go and existing DB | PASS, real HTTP requests |
| React browser rendering | PASS: observed Database connected and read-only |
| Unmigrated API route | PASS: 404, does not report success |
| Deliberately incorrect DB_PORT=1 | Expected failure: connection refused, exit 1 |
| Git ignore for local .env | PASS |

The first npm attempt stalled; it was stopped and retried with bounded timeouts. The escalation shell used Node 20.18.0, which is below Vite's supported range; final TypeScript/build and Vite server checks used the installed Node 22.23.4 explicitly. `.nvmrc` and package engines record the supported version.

## PHP and service migration coverage

- Inspected/indexed 39 PHP controllers, 39 PHP models and 169 PHP view/template files. Templates and partials are not necessarily separate routable pages.
- Recorded 1,643 SQL/Query Builder evidence lines across controllers, models and helpers. This is a static inventory, not proof of business-logic equivalence or an exact count of executed SQL queries.
- PHP table references are mapped to confirmed live table names; dynamic SQL and unresolved references remain labeled for manual review.
- Actual legacy gaps include references to `d_entity`, `logger` and control tables absent from public schema. Do not create those tables or silently substitute unrelated tables. Some unresolved candidates in automated extraction are aliases/comments, not actual missing tables.

| Requirement | Current status |
|---|---|
| Connect Go to existing DB | Implemented and tested |
| Inspect live DB and create GORM models | Initial implementation and live scan verification complete |
| Port PHP handlers/services/repositories | Pending; query inventory and schema map prepared |
| Connect React to Go | Connection foundation verified; only health endpoint implemented |
| Recreate every PHP page | Pending; 169 view/template entries tracked as unverified |
| Replace PHP calls from other services | Pending; caller dependencies identified, no callers changed |
| Login/logout and CRUD tests | Not implemented/tested in Go; no production-data writes performed |
| Every page and service contract testing | Pending |

## Related services and contracts to preserve

- Fastify backend connects to the same application data model via DATABASE_URL; runtime target was not changed. It already has /api/v1 APIs, but Go has not replaced them.
- dataedge reads device configurations, writes telemetry and sends WebSocket updates. Existing source has WS authentication commented out; its JWT expectation also differs from Fastify's token format. Preserve collector and database access while designing compatible auth/site scope.
- alarm-engine reads alarm/notification state and dispatches notifications. Do not invoke send-test or worker jobs during read-only inspection because they may send messages or write state.
- reports/report-scheduler.js calls PHP `/reports/run_schedules` and `/notification_manager/run_health_checks`. Go equivalents and contract tests are still required before changing these URLs.
- public api reads shared device/site/API-client tables and may update API usage counters. It was inspected, not invoked or reconfigured.
- PHP and Fastify control logic call CONTROL_LOGIC_URL, default `/controlapi` routed externally toward port 3006. That service is an additional dependency; its source/runtime is not confirmed by this phase.
- PHP LoRaWAN integration also depends on ChirpStack REST routing. Camera/RTSP/HLS and media URL mappings need contract verification.

## Commands

From `go-backend/`:

```
go run ./cmd/inspect
go run ./cmd/schema -out audit/database-schema.json
python3 scripts/generate_models.py
gofmt -w internal/models
JCONNECT_INTEGRATION=1 go test ./... -count=1
go run ./cmd/server
```

From `react-frontend/`, use Node 22.23.4 (or another supported Node version):

```
npm install
npm run build
npm run dev
```

Development architecture: browser http://127.0.0.1:5173 -> Vite /api proxy -> Go http://127.0.0.1:3000 -> existing TimescaleDB 127.0.0.1:5433. Vite proxy is development-only; no production proxy/Nginx changes were made.

No full-migration completion claim is made. Remaining PHP business logic, authentication/authorization, page parity, background jobs and service contracts must be implemented and verified before cutover.
