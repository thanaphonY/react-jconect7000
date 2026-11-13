# Wave 13 PO — Alarms acknowledge, resolve, export, status (re-verify after gap fixes)

Logged in on both as Supperadmin / 123456 (PHP `email=Supperadmin`; React `/api/v1/auth/login` accepts the same name).

PHP: http://127.0.0.1/ (opened `/user/login` → `POST /user/authUser`, `/alarmlogs`, `/alarmlogs/export_preview?range=last_30_days`, `/alarmlogs/export_data?format=csv|excel|pdf`, `/alarmstate`, `/alarmstate/get_json`, `/` home dashboard acknowledge)
React: http://127.0.0.1:5173/ (routes `/login`, `/alarmlogs`, `/alarmlogs/export`, `/alarmstate`, `/home` return HTTP 200; compared live `/api/v1/alarm-logs*`, `/api/v1/alarms/active`, export endpoints, and Vite-served `AlarmLogsPage` / `AlarmLogsExportPage` / `AlarmStatePage` / `HomePage`)

**Method note:** Cursor browser MCP tabs did not stay usable in this verifier session. Comparison used authenticated PHP HTML/JSON, React SPA route checks, live page/API source, and authenticated `/api/v1` round-trips.

**Re-verify focus:** Lead return gaps from prior PO (Apply chrome, View details gate, note persistence, bulk UI, Severity Levels label, humanized filter badges, real xlsx/pdf, home Acknowledge confirm). PHP `/alarmstate` HTTP 500 (missing view) is excluded when React data matches `get_json`.

## Comparison

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| `/alarmlogs` opens | Alarm Logs page; stats Total/Active/Levels/Devices = 0 | Same title; stats 0/0/0/0 via `/api/v1/alarm-logs/statistics` | same |
| List empty state | “No data available” | Ant Table empty / No data | same |
| List columns | checkbox, Date Time, Device, Key, Alarm Level, Message (+ snapshot thumb) | Same set (row selection + Date Time, Device, Key, Alarm Level, Message + snapshot) | same |
| Date/time format | `YYYY-MM-DD HH:mm:ss` Asia/Bangkok | Same | same |
| Filters: date range | all / today / yesterday / last_7_days / last_30_days (default) / custom + start/end date | Same values and default | same |
| Filters: device / severity / status / search | All Devices / All Severities / All Statuses; search placeholder devices/metrics/messages; Apply on Enter or button | Same options/defaults; Apply on Enter or button | same |
| Apply control chrome | Icon-only `bi-search` button (no Apply text) | Icon-only primary button with `bi-search` (no Apply text) | same |
| Page size options | 10, 20, 25, 30, 40, 50 (default 10) | Same `pageSizeOptions` | same |
| Stats cards + icons | Total Alarms, Active Alarms, Alarm Levels, Devices; `bi-exclamation-triangle` / `bi-exclamation-circle` / `bi-layers` / `bi-cpu`; blue/red/yellow/cyan | Same labels, icons, colors | same |
| Preview Export | `Preview Export` → `/alarmlogs/export_preview?...` | `Preview Export` → `/alarmlogs/export?...` | same |
| View details link | Only when message length &gt; 50 | Only when message length &gt; 50 | same |
| Detail modal sections | Alarm Information, Device Information, Related Alarms, Notification History; Close; Acknowledge if active; Resolve if acknowledged | Same sections and status-gated footer buttons | same |
| Acknowledge form | Note (Optional) textarea; not required | Same optional `note` | same |
| Acknowledge save / read-back | Updates `status` + event log only; does **not** write `alarm_logs.note` | Updates `status` + event_logs metadata only; does **not** write `alarm_logs.note` | same |
| Resolve form | Resolution Note textarea `required` | Same required `resolution_note` | same |
| Resolve save / read-back | Updates `status` + event log only; does **not** write `alarm_logs.note` | Updates `status` + event_logs metadata only; does **not** write `alarm_logs.note` | same |
| Bulk acknowledge / resolve UI | Dropdown “Acknowledge Selected” / “Resolve Selected” is HTML-commented (not shown); modals/JS exist | Bulk buttons hidden (not shown); APIs remain | same |
| Bulk note fields | Optional note (ack); required resolution note (resolve) — UI not shown | Same field rules on API when used | same |
| `/alarmlogs/export` / export preview opens | Export Preview - Alarm Logs; Back to Alarm Logs; Total Records / Active Alarms / **Severity Levels** / Devices | Same layout; third card label **Severity Levels** (`t('severity_levels')`) | same |
| Export applied filters | Badges with humanized keys (`Range`, …) | Tags with humanized keys (`Range`, `Device filter`, … via `humanizeFilterKey`) | same |
| Export options | Export CSV, Export Excel, Preview PDF, Download PDF (`bi-file-earmark-csv` / excel / eye / download) | Same four actions and icons | same |
| Export CSV | `text/csv`; headers Date Time, Device, Metric, Severity, Message; `.csv` | Same headers/content-type; `.csv` | same |
| Export Excel | Real OOXML `.xlsx` (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`) | Real OOXML `.xlsx` (ExcelJS); same content-type | same |
| Export PDF preview/download | Real TCPDF `application/pdf` (`.pdf`) | Real PDFKit `application/pdf` (`.pdf`) | same |
| Export data preview table | Date Time (split date/time), Device, Metric, Severity, Message | Same columns and split date/time | same |
| `/alarmstate` page open | HTTP 500 — missing view `alarmstate/index` | `/alarmstate` Active Alarms table (HTTP 200) | same |
| `/alarmstate/get_json` / `/api/v1/alarms/active` | `status: success`, `data: []` | `data: []` | same |
| Home/dashboard acknowledge | Active Alarms table: device, metric, message, severity, last_triggered, Acknowledge button; `confirm("Acknowledge this alarm?")` then POST `/alarmstate/acknowledge/:device/:metric` | Same columns and Acknowledge action via `POST /api/v1/alarms/active/:deviceId/:metricKey/acknowledge`; `window.confirm(t('acknowledge_this_alarm_confirm'))` = “Acknowledge this alarm?” | same |
| Language strings (EN) on alarmlogs | Hard-coded English matches keys (Alarm Logs, Filters, Preview Export, …); column Key | i18n `metric_key` = Key; most labels match | same |
| Menu / icons | Alarm Logs menu; Bootstrap icons on cards/buttons | Same menu path `/alarmlogs`; same bi-* icons on cards/buttons | same |
| Snapshot art | Thumb + enlarge modal when `snapshot_path` set | Ant `Image` from `snapshotPath` | same |
| `/api/v1` routes | n/a | `/alarm-logs`, `/alarm-logs/statistics`, `/alarm-logs/filters`, `/alarm-logs/export-preview`, `/alarm-logs/export`, `/alarm-logs/:id`, acknowledge/resolve/bulk, `/alarms/active` (+ acknowledge) | same |
| Additive SQL / no Prisma | PHP DB still works | `002_alarm_logs_wave13.sql` ADD COLUMN IF NOT EXISTS only; no Prisma dependency | same |
| dataedge / alarm-engine | Unchanged for this feature path | Wave 13 alarm APIs live in `backend`; no protocol code moved into `backend` | same |

## Tests

`cd backend && npm test -- test/wave13-alarms.test.ts` — **5 passed** (includes note non-persistence and real xlsx/pdf content-type asserts).

## Recommendation

accept

Lead decision: accept
