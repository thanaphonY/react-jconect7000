# Wave 14 PO — Notification create, edit, test send, health check (re-verify after gap fixes)

Logged in on both as Supperadmin / 123456 (PHP `email=Supperadmin`; React `/api/v1/auth/login` accepts the same name).

PHP: http://127.0.0.1/ (opened `/user/login` → `POST /user/authUser`, `/notification_manager`, `/notification_manager/get_channel_data/:id`, `/notification_manager/send_test/:id`, `/notification_manager/get_channel_template/:id`, `/notification_manager/get_devices/:id`, `/notification_manager/get_message_history/:id`, `/notification_manager/health_checks`, `/notification_manager/health_check_preview`, `/notification_manager/health_check_send/:id`)
React: http://127.0.0.1:5173/ (routes `/login`, `/notification_manager`, `/notification_manager/health_checks` return HTTP 200; compared live `/api/v1/notification-channels*`, `/api/v1/notification-health-checks*`, and Vite-served `NotificationsPage` / `NotificationHealthChecksPage`)

**Method note:** Cursor browser MCP tabs did not stay usable in this verifier session. Comparison used authenticated PHP HTML/JSON, React SPA route checks, live page source, and authenticated `/api/v1` round-trips.

Wave 14 scope: notification channel create / edit / test-send and health-check pages/forms. List surface included where it gates those actions.

**Re-verify focus:** all prior `return` gaps must be `same` for accept.

## Comparison

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| `/notification_manager` opens | Notification Manager; tabs Channels / Health Check; Add Channel | Same title, tabs, Add Channel → editor modal | same |
| `/notification_manager/health_checks` opens | Health Check list + Add Health Check modal | Same route and modal | same |
| Menu / route | Menu Notification → `/notification_manager`; `bi-bell` | Same path; shell maps `bi-bell` | same |
| Stats cards | Total Channels 2, Active Channels 1, Connected Devices 22, Today's Alarms 0; colors primary/success/info/warning | Same four metrics and values via `/notification-channels/summary` | same |
| Stats icons | `bi-bell` / `bi-check-circle` / `bi-cpu` / `bi-exclamation-triangle` | Same Bootstrap Icons on stats cards | same |
| Channel list columns | #, Name (+ description), Type, Status, Connected Devices, Action | Same column set | same |
| Channel rows (seed) | LINE Test Group `LINE_OA` Active 0 devices; Email `EMAIL` Inactive 0 devices | Same two channels, types, active flags, device counts | same |
| Inactive status label | Hard-coded **Inactive** on list badge | Hard-coded **Inactive** (not Deactive) | same |
| List search / paging | DataTable Search + page size 10/25/50/100 | `Input.Search` + Ant pagination `pageSizeOptions` 10/25/50/100 | same |
| Row action icons | `bi-pencil` / `bi-play-circle` / `bi-clock-history` / `bi-trash` | Same `bi-*` icons on action buttons | same |
| Add Channel modal title | Add Channel | `t('add_channel')` → Add Channel | same |
| Edit Channel modal title | Edit Channel | Hard-coded **Edit Channel** | same |
| Save control | Button **Save Channel** | Modal `okText="Save Channel"` | same |
| Field: name | label Name *; text; required | Same required text | same |
| Field: type | label Type *; select required; options: empty “Select Type”, email, line_oa, telegram | Required select; `allowClear` + placeholder Select Type; options email / line_oa / telegram only on create | same |
| Field: description | textarea rows=2 | Same | same |
| Field: message_template | textarea rows=3; placeholder lists `{DEVICE_NAME}`…`{SEVERITY}` | Same placeholders via `message_template_placeholders` | same |
| Field: is_active | checkbox Active; default checked | Same checkbox; default true | same |
| Email config fields | smtp_host, smtp_port, smtp_secure (false/true STARTTLS/SSL), smtp_user, smtp_pass, from, subject (default Alarm Notification), to textarea | Same names, types, secure options, subject default | same |
| Email recipient hint | Hard-coded Thai hint under Recipient Email | Same Thai `RECIPIENT_EMAIL_HINT` | same |
| LINE OA config fields | access_token, to (User/Group ID); not HTML-required | Same names; no required rules | same |
| Telegram config fields | bot_token, chat_id; not HTML-required | Same names; no required rules | same |
| Webhook type in create UI | Not offered in enhanced type select (only email / line_oa / telegram) | Not offered on create; legacy webhook/line only when editing those types | same |
| Type lock on edit | Type disabled after load | `typeLocked` disables type select | same |
| Create / edit save read-back | AJAX create/update writes `notification_channels`; values readable via `get_channel_data` | `POST/PUT /api/v1/notification-channels`; PHP read-back matched React create/update (name/type/is_active/template/description/config); soft-delete OK | same |
| Delete confirm | `confirmDelete` soft-delete | Ant `Modal.confirm` soft-delete via API | same |
| Test Channel modal | Test Message textarea default `Test notification from JConnect System`; Message Template Preview; Send Test / Cancel | Same default message, preview card, Send Test | same |
| Test-send path | `POST .../send_test/:id` → alarm worker `http://localhost:3004/api/send-test` | `POST /api/v1/notification-channels/:id/test-send` → same alarm-engine URL | same |
| Test-send when engine down | `success:false`, error **Failed to connect to alarm worker service** | Same `success:false` and same error string (channel/type echo match) | same |
| Template preview content | Raw channel `message_template` (e.g. `{{message}}` / `{{date_time}}`) | Same raw template string from `/template` | same |
| Connected Devices modal columns | Device, Description, Location, Metric, Threshold, Clear Threshold, Severity, Status | Same headers including **Device** | same |
| Connected Devices empty | No devices connected to this channel | Same empty copy | same |
| Message history data | INNER JOIN `alarm_logs`+`devices` → empty for channel 29 (“No message history found”) | Same INNER JOIN; empty list / emptyText | same |
| Health list columns | Name, Schedule, Next run, Last run, Status, Action | Same | same |
| Health row (seed) | Daily Report; Daily 08:00; next `06/10/2026 08:00`; last `05/10/2026 08:00`; Active | Same labels/values from API | same |
| Health header icon | `bi-heart-pulse` | Same `bi-heart-pulse` | same |
| Health form: name | text required | Same | same |
| Health form: schedule_type | daily / weekly / monthly | Same | same |
| Health form: schedule_time | `type=time` default 08:00 | TimePicker HH:mm default 08:00 | same |
| Health form: is_active | checkbox default checked | Same | same |
| Health form: schedule_weekday | 1–7 weekday labels when weekly | Same | same |
| Health form: schedule_day | number 1–28 when monthly | Same | same |
| Health form: channel_ids | checkboxes `name (TYPE)` for each channel | Same Checkbox.Group | same |
| Health form: message_template | monospace textarea; Use default template; placeholders `{name} {datetime} {status} {alarms_count} {alarms}` + metric hint | Same controls and placeholder help | same |
| Health default template | `{name}\n{datetime}\nStatus: {status}\n\n{alarms}` | Same `defaultTemplate` from API | same |
| Health preview | Preview message → rendered text (metric placeholders resolved) | Same preview API result (content matched live) | same |
| Health save | POST `health_check_save` create/update | POST `/api/v1/notification-health-checks` | same |
| Health send now | Direct channel drivers in PHP model (LINE push succeeded live: `success:true`) | Direct channel delivery mirroring PHP `_send_channel` (LINE push succeeded live: `success:true`) | same |
| Health delete confirm | confirm with name | Ant confirm with name | same |
| Language strings (EN) on wave pages | Mix of lang keys + hard-coded English (Inactive, Edit Channel, Save Channel, Thai email hint) | Matching chrome strings and i18n keys on wave pages | same |
| `/api/v1` routes | n/a | `/notification-channels` (+ summary, CRUD, devices, messages, template, test-send), `/notification-health-checks` (+ preview, send, delete) | same |
| Additive SQL / no Prisma | PHP DB unchanged | No wave-14 migration required; no Prisma in backend/webapp | same |
| dataedge / alarm-engine | Unchanged; channel test uses alarm worker HTTP; health uses PHP `_send_channel` | Channel test calls alarm-engine HTTP only; health uses direct channel send (mirrors PHP); no edits to `dataedge` / `alarm-engine` | same |

## Tests

`cd backend && npm test -- test/wave14-notifications.test.ts` — **6 passed**.

## Recommendation

accept

Lead decision: accept
