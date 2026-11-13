# Wave 8 PO

PHP: http://127.0.0.1/ still answers login with HTTP 200.
React: http://127.0.0.1:5173/ still serves the app. API stays on port 4000.

Logged in on both as Supperadmin / 123456 during waves 3 through 7. This wave compares the migrated set with what is still on PHP and records the switch plan. PHP was not turned off.

| Function | PHP result | React result | Verdict |
|---|---|---|---|
| Migrated pages | Login, home, dashboard, devices, categories, templates, widgets, graph, alarm logs, event logs, notifications, reports, report files, LoRaWAN applications, calculations, and control health | The same screens, compared in waves 1 through 7 | same |
| Remaining pages | Users, profile, settings, device metric wizard, report builder, notification editing, LoRaWAN create, calculation save, and control commands are still the PHP screens | Those screens are not in the React app | same |
| Switch plan | PHP remains the site on port 80 | `Document/nginx-switch-plan.md` lists migrated routes, remaining pages, and the Nginx cutover. It does not change the current server | same |
| Stack rules | PHP on port 80 still opens | No Phase 2 work. No Prisma. `dataedge` and `alarm-engine` were not edited | same |

## Tests

`cd backend && npm test` — 10 passed.
`cd backend && npm run typecheck` passed.
`cd webapp && npx tsc -b` passed.

## Recommendation

accept

Lead decision: accept
