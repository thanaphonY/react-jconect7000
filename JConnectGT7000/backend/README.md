# JConnect backend

Phase 1 HTTP API. Fastify on port 4000, prefix `/api/v1`. Requires Node.js 22 or newer.

## Install

```bash
cd backend
npm install
```

Copy `backend/.env.example` to `backend/.env` and set `DATABASE_URL`. The local database is `jconnect` on `127.0.0.1:5432`. If the password contains `@`, URL-encode it as `%40`.

## Migrate

```bash
npm run migrate
```

Applies `Database/migrations/*.sql` in numeric order. Each file runs in a transaction, then its filename is stored in `schema_migrations`. Files already recorded are skipped, so a second run changes nothing.

## Run

```bash
npm run dev
```

`npm start` runs the same server without file watching. Health check: `GET http://127.0.0.1:4000/api/v1/health`.

## Test

```bash
npm test
npm run typecheck
```
