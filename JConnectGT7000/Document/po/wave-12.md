# Wave 12 PO — Templates and widgets full (every form field) — re-verify after gap fixes

Logged in on both as Supperadmin / 123456.

PHP: http://127.0.0.1/ (opened `/user/login` → `POST /user/authUser`, `/devicetemplate`, `/devicetemplate/add`, `/devicetemplate/edit/27`, `/widgets`, `/widgets/add`, `/widgets/edit/16`)
React: http://127.0.0.1:5173/ (routes `/login`, `/devicetemplate`, `/devicetemplate/new`, `/devicetemplate/27`, `/widgets`, `/widgets/add`, `/widgets/edit/16` return HTTP 200; compared live Vite modules, `/api/v1/device-templates*`, `/api/v1/widgets*`, `/api/v1/dcim-taxonomy`, uploads)

**Method note:** Cursor browser MCP tabs did not stay usable in this verifier session. Comparison used authenticated PHP HTML, React SPA route checks, live Vite-served source for `TemplatesPage` / `TemplateFormPage` / `TemplateDashboardBuilder` / `WidgetsPage` / `WidgetFormPage`, and authenticated `/api/v1` round-trips.

## Exact gaps 1–12 (prior return) — re-check

| # | Gap | PHP result | React result | Verdict |
|---|---|---|---|---|
| 1 | Template list columns | `#`, Name, Protocol, Active, Actions | Same five columns (Device Categories removed) | same |
| 2 | Template list `#` | 1-based row index (first row `1` for STULZ) | `index + 1` | same |
| 3 | Template list Active | Badge Yes/No | Ant `Tag` Yes/No (success/default) | same |
| 4 | Template list actions | Icon-only `bi-pencil-square` / `bi-trash` | Icon-only pencil/trash buttons | same |
| 5 | Template image control | Bare `input type="file" name="image" accept="image/*"` | Bare native file input (no Upload / “Image Upload” chrome) | same |
| 6 | Is Active | Checkbox; unchecked default on add | `Checkbox` + `initialValues.isActive: false` | same |
| 7 | Comm / Device Info rows | Placeholders Key/Value; Remove text | `keyPlaceholder="Key"` / `valuePlaceholder="Value"`; `removeLabel={t('remove')}` → Remove | same |
| 8 | Dashboard configure modal preview | In-modal Live Preview | In-modal `WidgetPreviewContent` under Live Preview | same |
| 9 | Dashboard live preview card | GridStack `#preview` | GridStack `id="preview"` live preview card | same |
| 10 | Widgets list Config column | Name, Type, Config, Actions | Config column with pretty JSON | same |
| 11 | Widgets form chrome | `/widgets/add`, `/widgets/edit/:id`; label Widget Type; Create/Update + Cancel | Dedicated pages; `t('widget_type')` = Widget Type; Create/Update + Cancel | same |
| 12 | Widgets config JSON | Edit may show DB string; list pretty-prints | API returns object; form `normalizeConfigObject` / `configToFormText`; edit shows object JSON | same |

## Comparison (full wave surface)

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| `/devicetemplate` list opens | Device Template list | Same title / list | same |
| Add Template navigation | `/devicetemplate/add` + `bi-plus-lg` | `/devicetemplate/new` (allowed alternate) | same |
| Edit navigation | `/devicetemplate/edit/:id` | `/devicetemplate/:id` (plan route) | same |
| Form tabs | General, Communication, Device Info, Metrics, Dashboard | Same five tabs | same |
| General: Template Name | `name` required text | Same required | same |
| General: Description | `description` textarea rows=3 | Same | same |
| General: Protocol options | 14 values (`modbus_tcp`…`rtsp`) | Same `TEMPLATE_PROTOCOLS` | same |
| General: DCIM Category / Sub-Category | Required selects; “Will be saved as `Cat / Sub`” | Same required + preview text | same |
| General: Category taxonomy | DCIM taxonomy categories/subs | Same via `/api/v1/dcim-taxonomy` | same |
| Communication: RTSP fields | ip, port, rtsp_port, rtsp_url, snapshot_url, transport tcp/udp, username, password | Same keys/types/placeholders | same |
| Communication: protocol seeds | modbus/snmp/mqtt/visual/gateway_endpoint/rtsp defaults | Same `seedCommunicationForProtocol` | same |
| Device Info defaults | Brand, Model, Manufacturer, Serial Number, Firmware/Hardware Version + DCIM inject | Same defaults + DCIM inject | same |
| Metrics tab | `JConnectMetricBuilder` (JSON editable) | Shared `MetricBuilder` (JSON editable) | same |
| Dashboard builder | GridStack + Add Widget + configure modal | GridStack + Add Widget + configure modal | same |
| Dashboard widget types | value_box, gauge, gradient_radial_bar, line/area/bar/donut | Same `DASHBOARD_WIDGET_TYPES` | same |
| Dashboard configure fields | title, metric_key, widget_type, unit, color, gradient_to_color, chart height/min/max/show_legend | Same | same |
| Template image art | `/uploads/device_templates/...` HTTP 200 | `/api/v1/uploads/device-templates/...` HTTP 200 | same |
| Template #27 read-back | name/protocol/dcim/comm/info/metrics/active/image | API/detail match PHP values | same |
| `/widgets` list opens | Widgets table | Widgets table | same |
| Widget field: Name | `name` required text | Same required | same |
| Widget type options set | 25 options (value_box…camera_stream) | Same `PHP_WIDGET_TYPES` | same |
| Widget field: Config JSON | `config` textarea rows=6, default `{}` | Same label/rows/default | same |
| Widget create/read-back (object config) | PHP saves JSON text | `POST/GET /api/v1/widgets` object round-trip; string DB configs normalized to objects | same |
| Menu / Settings link to Templates | Present | Present | same |
| `/api/v1` template & widget routes | n/a | Present under `/api/v1` | same |
| Additive SQL / no Prisma | PHP DB still works | No Prisma dep; no DROP/RENAME migrations for this wave | same |
| dataedge / alarm-engine | Unchanged | No wave-12 edits observed in those trees | same |

## Tests

`cd backend && npm test -- test/wave12-templates.test.ts` — **4 passed** (device-templates + widgets config normalize).

## Recommendation

accept

Lead decision: accept
