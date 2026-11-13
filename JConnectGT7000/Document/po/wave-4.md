# Wave 4 PO

PHP: http://127.0.0.1/widgets and http://127.0.0.1/graph
React: http://127.0.0.1:5173/widgets and http://127.0.0.1:5173/graph

Logged in on both as Supperadmin / 123456.

| Function | PHP result | React result | Verdict |
|---|---|---|---|
| Widget list | 15 widgets in name order: Alarm Summary, Bar Chart, Level Bar, Line Chart, Line Chart Dynamic, Map View, Power Meter, Ratio, Status Grid, Table, Temperature and Humidity, Value Box, Value Box 2, Value Box 3, Zone Overview Card. Each row has Edit and Delete | The same 15 names and types in the same order, with Edit and Delete | same |
| Add and delete a widget | PHP form saves name, type, and JSON config | Creating "Wave4 check" as line_chart with config {"label":"temp"} read those fields back. Deleting it left the original 15 widgets | same |
| Temperature graph from device_data | /graph renders a line chart titled around Timestamp. The PHP query uses key Temperature, which matches no rows, so the axis is empty | /graph draws an ECharts line titled Temperature from device_data key temperature. The latest points are about 23 to 25, with Bangkok timestamps on the axis | same |
| Stack rules | PHP on port 80 still opens Widgets | Routes are under `/api/v1`. The chart lives in webapp/src/features/graph. No new columns. No Prisma. `dataedge` and `alarm-engine` were not edited | same |

## Tests

`cd backend && npm test` — 10 passed.
`cd backend && npm run typecheck` passed.
`cd webapp && npx tsc -b` passed.

## Recommendation

accept

Lead decision: accept
