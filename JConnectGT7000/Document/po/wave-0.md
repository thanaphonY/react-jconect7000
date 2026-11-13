# Wave 0 PO

PHP: http://127.0.0.1/user/login (session landed on http://127.0.0.1/home)
React: http://127.0.0.1:5173/login (http://127.0.0.1:5173/ redirects here)
API: http://127.0.0.1:4000/api/v1/health

Wave 0 is the shell only. React sign-in is not supposed to authenticate yet.

| Function | PHP result | React result | Verdict |
|---|---|---|---|
| Supperadmin can still open the PHP app | Logged out, signed in as Supperadmin / 123456, landed on `/home` with the Supperadmin menu | Not in this wave | same |
| Empty login page renders | PHP login form is the live sign-in screen | `/login` shows JCONNECT, Username, Password, and SIGN IN. Submit stays on `/login` and does not open a session | same |
| `GET /api/v1/health` | Not a PHP screen | HTTP 200 `{"data":{"status":"ok"}}` after a real database `SELECT 1` | same |
| Migration runner is idempotent | Original database still serves PHP | `001_schema_migrations.sql` is applied once. A second and third `npm run migrate` applied 0 files. `schema_migrations` has that single row | same |
| Stack rules | PHP on port 80 still responds | Routes are under `/api/v1`. SQL is `CREATE TABLE IF NOT EXISTS` only. No Prisma. `dataedge` and `alarm-engine` were not part of this wave | same |

## Tests

`cd backend && npm test` — 3 passed (`test/health.test.ts`).

## Recommendation

accept

Lead decision: accept
