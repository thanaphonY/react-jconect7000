# Wave 5 PO

PHP: http://127.0.0.1/alarmlogs, http://127.0.0.1/eventlogs, http://127.0.0.1/notification_manager
React: http://127.0.0.1:5173/alarmlogs, http://127.0.0.1:5173/eventlogs, http://127.0.0.1:5173/notification_manager

Logged in on both as Supperadmin / 123456.

| Function | PHP result | React result | Verdict |
|---|---|---|---|
| Alarm status and alarm log list | Total Alarms 0, Active Alarms 0. The log table has no rows | Active Alarms: 0. The log table says No data | same |
| Event log list | Newest first, page size 10. First rows: 2026-10-05 14:04:22 Supperadmin Login User "logged in successfully"; 14:04:03 Logout; 14:02:26 Login; 13:50:41 Login; 13:50:31 Admin Logout | The same times, users, actions, sections, and messages | same |
| Notification channels | Two channels: LINE Test Group, LINE_OA, Active, 0 devices, description For Test. Email, EMAIL, Inactive, 0 devices | The same two channels, types, Active/Inactive status, descriptions, and connected-device counts | same |
| Stack rules | PHP on port 80 still opens these pages | Routes are under `/api/v1`. No new columns. No Prisma. `dataedge` and `alarm-engine` were not edited. Test send was not called | same |

Export, acknowledge, and channel test-send stay on PHP. The lists and status match the database.

## Tests

`cd backend && npm test` — 10 passed.
`cd backend && npm run typecheck` passed.
`cd webapp && npx tsc -b` passed.

## Recommendation

accept

Lead decision: accept
