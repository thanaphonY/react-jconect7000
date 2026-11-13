# Wave 15 PO — Report builder, schedules, new export (re-verify after gap fixes)

Logged in on both as Supperadmin / 123456 (PHP `email=Supperadmin`; React `POST /api/v1/auth/login` accepts the same name).

PHP: http://127.0.0.1/ (opened `/user/login` → `POST /user/authUser`, `/reports`, `/reports/builder`, `/reports/builder/15`, `/reports/run/15`, `/reports/schedules`, `/reports/files`, plus `POST /reports/fetch_data`)
React: http://127.0.0.1:5173/ (routes `/login`, `/reports`, `/reports/builder`, `/reports/builder/:id`, `/reports/run/:id`, `/reports/schedules`, `/reports/files` return HTTP 200; compared live `/api/v1/report-templates*`, `/api/v1/report-schedules*`, `/api/v1/report-files*`)

**Method note:** Cursor browser MCP tabs did not stay usable in this verifier session (`browser_navigate` / tab list kept failing). Comparison used authenticated PHP HTML/JSON, React SPA route checks, page source (Bootstrap Icons + form defaults), and authenticated `/api/v1` round-trips.

Wave 15 scope: report template builder (create/edit), run + new export, schedules CRUD, and files/export list used after save-to-file-manager.

## Comparison

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| `/reports` opens | Reports; Templates list; Manage Schedules / File Manager / Create New Template | Same title and three actions → `/reports/schedules`, `/reports/files`, `/reports/builder` | same |
| Template list columns | Name, Description, Updated At, Actions | Same | same |
| Seed template row | Temperature Report; description `-`; updated `03/10/2026 16:24` | Same name/description/updated via `/report-templates` (Bangkok `DD/MM/YYYY HH:mm`) | same |
| Template row action icons | `bi-play` / `bi-pencil` / `bi-trash` | Same `bi-*` icon buttons | same |
| Header button icons | `bi-clock` / `bi-folder2-open` / `bi-plus-lg` | Same `bi-*` on Manage Schedules / File Manager / Create | same |
| Templates card icon | `bi-file-earmark-text` | Same | same |
| `/reports/builder` opens | Create Report Template form | Same title `create_report_template` | same |
| `/reports/builder/:id` opens | Edit Template for id 15 (Temperature Report) | Same route loads template 15 | same |
| Field: name | label Template Name *; text; required; maxlength 255; placeholder | Same required text + maxlength + placeholder | same |
| Field: description | label Description; text; placeholder brief description | Same | same |
| Field: data_source | radio `device_data` only; Device Data + helper + `bi-cpu` | Same radio; `select_data_source` / `device_data_helper` + `bi-cpu` | same |
| Field: Device - Metric pickers | Device select + Metric select (disabled placeholder `-- Select Device first --`) + Add | Same flow; placeholder switches to `-- Select Metric --` after device chosen | same |
| Selected device-metric chips | Removable badges; drag-reorder via `bi-grip-vertical` | Removable Tags; HTML5 drag + `bi-grip-vertical` | same |
| Field: time_range | select; options input_date_range…last_3m; default last_24h | Same options/default via `TIME_RANGE_OPTIONS` | same |
| Field: time_interval | select 1m…1d; default 1h | Same | same |
| Field: display[] | checkboxes min/max/average/first/last; **create default unchecked** | Checkbox.Group; create default `[]` | same |
| Field: sort_order | select desc/asc; Newest/Oldest first; default desc | Same | same |
| Field: editor report_title | text; placeholder e.g. Device Data Report | Same | same |
| Field: editor table_layout | rows / pivot | Same | same |
| Field: display_columns | key (readonly) + label; drag reorder with grip; add from default set | key disabled + label; drag + `bi-grip-vertical`; same default set | same |
| Field: pdf_orientation / pdf_title | portrait/landscape + optional title | Same | same |
| Field: excel_sheet_name | text default Sheet1 | Same | same |
| Field: csv_delimiter | `,` / `;` / `tab` | Same | same |
| Export Inputs section | Commented out in PHP UI | Always saves `exportInputs: []` | same |
| Save Template / Cancel | Save Template (`bi-check-lg`) + Cancel | Same labels + `bi-check-lg` | same |
| Builder section icons | `bi-info-circle` / `bi-database` / `bi-funnel` / `bi-gear` / `bi-pencil-square` | Same section title icons | same |
| Create/edit save read-back | AJAX save writes `report_templates`; builder shows saved values | `POST/PUT /api/v1/report-templates`; template 15 filters/display/editor match PHP | same |
| `/reports/run/:id` opens | Template name title; Template filters; Data Processing; Enter data for Export | Same sections for Temperature Report | same |
| Run filter chips (tpl 15) | TH1–TH5 Temperature badges | Same pairs from config | same |
| Run processing summary | Last 24 Hours / 1 Hour / Min, Max, Average | Same labels from option maps | same |
| Run export controls | Fetch data; after fetch: Export Excel/CSV, Preview PDF, Export PDF, Save to File Manager | Same control set after fetch | same |
| Run export button icons | `bi-database` / excel / csv / eye / pdf | Same Bootstrap Icons | same |
| Run / new export (fetch) | `POST /reports/fetch_data` tpl 15 → `success:true`, **80 rows** | `POST /api/v1/report-templates/15/fetch` → **80 rows**, same device/key/timestamp set as PHP | same |
| Run / new export (file) | PHP fetch/export path works for seed template | `POST .../export` csv/excel/pdf HTTP 200; `saveToFileManager` returns fileId + downloadable file | same |
| `/reports/schedules` opens | Manage Schedules; table; Add Schedule | Same title, table, Add Schedule | same |
| Schedule list columns | Template, Schedule Name, Type, Time, Format, Next Run, Status, Actions | Same | same |
| Schedule seed rows | 3 weekly rows for Temperature Report (pdf/excel/csv @ 15:15; next `10/10/2026 15:15`; Active) | Same three via `/report-schedules` (next Bangkok `10/10/2026 15:15`) | same |
| Schedule row actions | `bi-pencil` / `bi-trash` icon buttons | Same `bi-*` icon buttons | same |
| Schedule header icons | `bi-clock` / `bi-calendar3` / `bi-plus-lg` | Same | same |
| Field: template_id | select required; Select Template; disabled on edit | Same required Select; disabled on edit | same |
| Field: name | text required; placeholder | Same | same |
| Field: schedule_type | daily / weekly / monthly; default daily | Same | same |
| Field: schedule_time | `type=time` default 08:00 | Same | same |
| Field: schedule_weekday | Mon–Sun when weekly; default 1 | Same | same |
| Field: schedule_day | number 1–28 when monthly; default 1 | Same | same |
| Field: export_format | pdf / excel / csv; default pdf | Same | same |
| Field: is_active | checkbox Active; default checked | Same | same |
| Field: recipients | Hidden empty in PHP (textarea commented out) | Always `recipients: []` | same |
| Schedule modal chrome | Add/Edit titles; Close + Save Schedule | Same titles; Ant okText Save Schedule | same |
| Schedule create read-back | PHP list shows React-created schedule | `POST /api/v1/report-schedules` then PHP schedules HTML includes row; delete OK | same |
| Schedule delete | confirm `delete_schedule_confirm` | `window.confirm` same key | same |
| `/reports/files` opens | Report Files; Export Files card; filters | Same page title; filters present | same |
| Files filters | template_id, format (pdf/excel/csv), date_from, date_to, Filter | Same fields via Ant Form + `bi-search` | same |
| Files columns | Report, File Name, Format, Size, Exported At, Source, Action | Same; Action via `t('action')` | same |
| Files size display | `number_format(bytes/1024, 1) KB` (e.g. 93.4 KB) | Same KB formatting | same |
| Files format display | Uppercase badge (PDF) | Uppercase Tag | same |
| Files first page / total | 15 rows page 1; total 27; first `test_20261003_161119.pdf` @ `03/10/2026 16:11` Manual | Same total/names/order/times via API | same |
| Files row actions | `bi-download` / `bi-trash` | Same `bi-*` icon buttons | same |
| Files header icons | `bi-folder2-open` / `bi-download` / `bi-search` | Same | same |
| Language strings (EN) on wave pages | Lang keys + hard-coded English builder/run chrome | Matching i18n keys and same hard-coded builder/run strings | same |
| `/api/v1` routes | n/a | `/report-templates` (+ lookups, CRUD, fetch, export), `/report-schedules` CRUD, `/report-files` | same |
| Additive SQL / no Prisma | PHP DB unchanged for this wave | No wave-15 migration; no Prisma in backend/webapp | same |
| dataedge / alarm-engine | Unchanged | Not edited | same |

## Tests

`cd backend && npm test -- test/wave15-reports.test.ts` — **5 passed** (includes live GROUP BY expression check: reuse `$1`/`$2` in `GROUP BY`, not distinct placeholders that trigger Postgres `42803`).

Live check: `POST /api/v1/report-templates/15/fetch` → `count: 80`, row set identical to PHP `POST /reports/fetch_data` for template 15.

## Recommendation

accept

Lead decision: accept
