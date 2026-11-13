# Wave 2 PO

PHP: http://127.0.0.1/home and http://127.0.0.1/dashboard
React: http://127.0.0.1:5173/home and http://127.0.0.1:5173/dashboard

Logged in on both as Supperadmin / 123456.

| Function | PHP result | React result | Verdict |
|---|---|---|---|
| Open the saved home layout | Home shows Alarm Summary (Total 0, Critical 0, Warning 0, Info 0), Zone Overview Card (0, Active Alarms, No metrics configured), Rack01 power meter (Active Power -- kW, phases R S T), and Active Alarms with no rows | The same three widgets and the same empty Active Alarms table. Headers are Device Name, Metric, Massage, Severity, Timestamp, Acknowledge | same |
| Switch saved layouts | The home menu lists Home, Dashboard 2, and Dashboard 3 | The home selector lists those same three names | same |
| Dashboard layout list | Order, name, description, and actions View, Edit, Set Home, Delete, plus Add Dashboard. Home is the current home, so it has no Set Home | The same three rows and the same actions. Home has no Set Home. Dashboard 2 and Dashboard 3 do | same |
| Add and delete a layout | PHP can create and delete a layout | Creating "Wave2 check" added it to the list. Deleting it left Home, Dashboard 2, and Dashboard 3, and the home layout stayed Home | same |
| Stack rules | PHP on port 80 still opens Home | Routes are under `/api/v1`. No new columns. No Prisma. `dataedge` and `alarm-engine` were not edited | same |

Live meter charts and the visual layout editor are later waves. The saved widgets on Home are visible now.

## Tests

`cd backend && npm test` — 10 passed.
`cd backend && npm run typecheck` passed.
`cd webapp && npx tsc -b` passed.

## Recommendation

accept

Lead decision: accept
