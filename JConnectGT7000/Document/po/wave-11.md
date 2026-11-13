# Wave 11 PO — Device full (forms, images, card view, metric wizard)

Logged in on both as Supperadmin / 123456.

PHP: http://127.0.0.1/ (opened `/user/login`, `/user/authUser`, `/device`, `/device/add`, `/device/edit/555`, `/device/view/555`; AJAX `/device/get_devices_ajax`)
React: http://127.0.0.1:5173/ (routes `/login`, `/device`, `/device/add`, `/device/edit/555`, `/device/view/555`)

## Comparison (initial verify)

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| `/device` route opens | Devices list | Devices list | same |
| List / Card toggle | `bi-list` List + `bi-grid` Card | Same icons + labels | same |
| Add Device button | `bi-plus` + Add Device → `/device/add` | Same | same |
| List toolbar Device Categories | Not on `/device` list toolbar | Extra **Device Categories** button → `/device_categories` | different |
| Search filter | `Search devices...` (`#deviceSearch`) | Same placeholder (`search_devices`) | same |
| Status filter | All Status / Active / Inactive | Same options | same |
| Template filter | All Templates + AJAX options | Same + API filter-options | same |
| Location filter | All Locations + AJAX options | Same | same |
| List page length | Show 10/25/50/100 entries, default 10 | Same options/default | same |
| List columns | Image, Device Name, Brand, Model, Serial Number, Location, Description, Additional Information, Status, Actions | Same labels (`device_*` / `custom_field`) | same |
| List thumbnails | Template/device image via `/uploads/...` | Same art via `/api/v1/uploads/...` (HTTP 200) | same |
| List hover image preview | `.device-image-table` zoom preview | `DeviceImageThumb` hoverPreview | same |
| List action buttons | `bi-eye`/`bi-pencil`/`bi-trash` **with** View / Edit / Delete text | Icon-only buttons (title tooltip only) | different |
| Card view content | Image (or `bi-box`), name, brand \| model, status badge, custom fields, actions | Same structure | same |
| Card actions | View/Edit/Delete text + icons | Icon-only | different |
| Card paging | 12 per page + pagination | 12 per page + Ant Pagination | same |
| Delete confirm | Confirm delete device | Same i18n confirm | same |
| `/device/add` wizard steps | 1 Select Template → Device Info → Communication → Metrics → Confirm | Same 5 steps | same |
| Add template picker | Template cards with image, name, brand/model, protocol | Same | same |
| Add metrics step | MetricBuilder `readOnly: true` (template metrics); snapshot editable | `MetricBuilder readOnly={!isEdit}` → read-only on add; snapshots editable | same |
| `/device/edit/:id` steps | Device Info → Communication → Metrics → Confirm | Same 4 steps | same |
| Edit image | Shows device or template image; `<input type="file" name="image" accept="image/*">` | Same fallback image; Ant `Upload` + `bi-upload` + label **Image Upload** | different |
| Edit Brand / Model | Disabled text inputs from template | Disabled inputs, same values (e.g. STULZ / CyberCool Indoor on #555) | same |
| Device Name | `name` required text | Same required | same |
| Device Role | Native / Gateway Collector / Gateway Endpoint | Same options (`role_*`) | same |
| Parent Gateway | Shown when endpoint; `-- Select Gateway --`; required | Same behavior/placeholder | same |
| Serial / Location / Description | text / textarea / textarea | Same | same |
| Additional Information editor | Add key/value rows (`add_custom_field`) | `CustomFieldsEditor` same idea | same |
| Custom fields section label | `lang('custom_fields')` (key missing in lang file → literal `custom_fields`) | Label **Additional Information** (`custom_field`) | different |
| Is Active control | Checkbox `is_active` | Ant **Switch** `isActive` | different |
| Communication field keys (modbus_tcp/rtu, snmp, mqtt, bacnet, lorawan, rtsp) | `commFields` keys (`ip`, `unit_id`, `auth_pass`, RTSP paths, etc.) | Same `COMM_FIELDS` keys/labels | same |
| LoRaWAN app server / device profile selects | Dropdowns from LoRa APIs | Same via `/api/v1` LoRa helpers | same |
| Gateway endpoint communication | Direct fields hidden; inherit notice | Same notice + disabled direct fields | same |
| Metric wizard Setup fields | Label, Key, Unit, Enable, Type, Datatype, Pull rate, Scale, Store policy/interval | Present in `MetricBuilder` | same |
| Metric wizard Source / protocol fields | Protocol-specific source mapping + gateway metric map | Same | same |
| Metric wizard Logic (calc) | Inputs, formula, trigger mode interval/on_pull/schedule | Same | same |
| Metric wizard schedule grid | Day/slot duration schedule on triggers | `ScheduleGrid` port | same |
| Metric wizard Alarms / triggers | Normal condition + triggers (severity, channels, cooldown, schedule) | Same (channels checkboxes from notification channels API) | same |
| Metric wizard JSON edit | View/Edit JSON | Same | same |
| Snapshot triggers (RTSP only) | Shown when protocol `rtsp`; enable, source device/metric/trigger, cooldown | `showSnapshots` when `rtsp`; same fields | same |
| Add/Edit save + read-back | PHP saves device JSON fields | `PUT/POST /api/v1/devices` round-trip (location/comms/metrics) verified; PHP list still reads device | same |
| `/device/view/:id` title | View Device | Same (`view_device_page`) | same |
| View image | Device image or template image | Same via `DeviceImageThumb` / API uploads | same |
| View key info fields | Name, Serial Number, Location, Description, **Protocol**, Additional Information | Device Name, **Brand**, **Model**, **Device Template**, Serial, Location, Description, **Device Role**, Additional Information — **no Protocol** | different |
| View Device Data tab | Tab with live charts (`device_view_chart.php`) | Missing | missing |
| View Alarm Log tab | Range/status filters, table, paging, Open full Alarm Logs | Missing | missing |
| View Metrics table columns | Key, Label, Unit, Data Type, Triggers, Status | Label, Key, Unit, Type, Data Type, Active | different |
| View Communication display | Key/value table; secrets masked (`********`) | Raw JSON dump; secrets not masked | different |
| Menu Devices icon | `bi-cpu` | `bi-cpu` | same |
| Sidebar logo asset | `assets/images/image_(5)_1755442027.png` | Same via assets API (prior waves) | same |
| `/api/v1` device routes | n/a | `/api/v1/devices`, filters, upload, metric helpers | same |
| Additive SQL / no Prisma | PHP DB still works | Migrations additive; no Prisma in backend package | same |
| dataedge / alarm-engine | Unchanged for this verify | No wave-11 edits observed in those trees | same |

## Exact gaps (from initial verify)

1. **List/card actions:** restore View / Edit / Delete **text** beside icons (PHP AJAX `actions` HTML).
2. **List toolbar:** remove or match PHP — PHP `/device` has no Device Categories button on the list header.
3. **Is Active:** use checkbox (or equivalent checkbox semantics), not Switch-only, to match PHP `type="checkbox"`.
4. **Edit image control:** match PHP bare `input type=file` labeling/UX (or drop the extra **Image Upload** button chrome if it diverges).
5. **Custom fields heading on form:** align label with PHP-rendered string (today PHP shows missing-key `custom_fields` vs React **Additional Information**).
6. **View key info:** include **Protocol**; align Brand/Model/Template/Role with PHP (PHP info card does not show Brand/Model/Template/Role).
7. **View Device Data tab:** missing charts/data pane.
8. **View Alarm Log tab:** missing filters, table, paging, and “Open full Alarm Logs”.
9. **View Metrics columns:** match Key, Label, Unit, Data Type, Triggers, Status (incl. trigger detail affordance).
10. **View Communication:** key/value rows with secret masking, not unmasked JSON.

## Tests (initial)

`cd backend && npm test -- test/wave11-devices.test.ts` — **5 passed**.

## Recommendation (initial)

return

---

## Re-verify (after gap fixes) — 2026-10-05

Method: PHP session via `POST /user/authUser` (email=`Supperadmin`, password=`123456`); authenticated HTML/AJAX for PHP. React via Playwright (Chrome) login + live UI on `:5173`. Also `/api/v1` device + alarm-logs checks and `wave11-devices` tests.

PHP URLs opened: `/user/login`, `/device`, `/device/add`, `/device/edit/555`, `/device/view/555`, `POST /device/get_devices_ajax`
React URLs opened: `/login`, `/device`, `/device/add`, `/device/edit/555`, `/device/view/555`

### Prior gaps 1–10

| # | Item | PHP result | React result | Verdict |
|---|---|---|---|---|
| 1 | List/card View/Edit/Delete text beside icons | AJAX `actions` HTML and card `device.actions`: icon + View / Edit / Delete | List + card buttons show View / Edit / Delete text with icons | same |
| 2 | No Device Categories on `/device` toolbar | Toolbar: List, Card, Add Device only | Same: List, Card, Add Device — no Device Categories | same |
| 3 | Is Active checkbox not Switch | `<input type="checkbox" name="is_active">` | Ant `Checkbox` (`input[type=checkbox]` present; `.ant-switch` count 0) | same |
| 4 | Edit bare file input for image | `<input type="file" name="image" id="image" accept="image/*">` | Same bare file input (`name=image`, `id=image`, `accept=image/*`); no Image Upload chrome | same |
| 5 | custom_fields heading | Live render: **Custom Fields** (`lang('custom_fields')` → english `Custom Fields`) | Hardcoded literal **`custom_fields`** (snake_case) | different |
| 6 | View Protocol; no Brand/Model/Template/Role on info card | Name, Serial, Location, Description, Protocol (+ custom fields); no Brand/Model/Template/Role | Same info fields; Protocol present; Brand/Model/Template/Role absent | same |
| 7 | View Device Data tab | Tab present; metrics/value/last update/description table | Tab present; same column idea with live latest values | same |
| 8 | View Alarm Log tab | Range/status filters, table, paging, Open full Alarm Logs | Same filters, table, paging, **Open full Alarm Logs** | same |
| 9 | Metrics columns | Key, Label, Unit, Data Type, Triggers, Status | Same six columns (+ trigger modal affordance) | same |
| 10 | Communication key/value + secret masking | Key/Value rows; `community` → `********` | Key/Value table; `community` → `********` | same |

### Spot-check (list / add / edit)

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| Device list loads with filters/paging | Works (22 devices AJAX) | Works (rows + View/Edit/Delete) | same |
| Device add wizard opens | Select Template step | Select Template / template cards | same |
| Device edit wizard opens | 4 steps; brand/model disabled | 4 steps; brand/model disabled; save path intact | same |
| Alarm Log column “Message” | Header **Message** | Header **Massage** (`en.ts` `message: 'Massage'`) | different |
| Alarm Log datetime header | **Date/Time** | **Date Time** | different |

### Plan rules / tests

| Item | Result | Verdict |
|---|---|---|
| Routes under `/api/v1` | devices, metrics/latest, alarm-logs used | same |
| Additive SQL / no Prisma | migrations additive; no Prisma | same |
| dataedge / alarm-engine | not edited for this re-verify | same |
| `npm test -- test/wave11-devices.test.ts` | **5 passed** | same |

### Remaining gaps (must fix for accept)

1. **Form custom_fields heading:** PHP shows **Custom Fields**; React shows literal `custom_fields`. Use the same label as PHP (`lang('custom_fields')` / i18n `Custom Fields`), not snake_case and not “Additional Information”.
2. **Alarm Log “Message” spelling:** React English string is **Massage**; PHP is **Message**.
3. **Alarm Log datetime header:** PHP **Date/Time** vs React **Date Time** (minor; still different).

## Recommendation (prior re-verify)

return

---

## Final re-verify — 2026-10-05 (evening)

Method: PHP via `POST /user/authUser` (email=`Supperadmin`, password=`123456`) + authenticated HTML/AJAX. React via headless Chrome login on `:5173` (live UI). Re-ran `wave11-devices` tests.

PHP URLs opened: `/user/login`, `/user/authUser`, `/device`, `/device/edit/555`, `/device/view/555`, `POST /device/get_devices_ajax`
React URLs opened: `/login`, `/device`, `/device/edit/555`, `/device/view/555`

### Remaining gaps from prior return (must be same for accept)

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| Device form heading Custom Fields | Edit `#555`: `<h6>Custom Fields</h6>` | Edit `#555`: heading **Custom Fields** (no snake_case `custom_fields`) | same |
| Alarm Log column Message | View `#555` table `<th>Message</th>` | Alarm Log tab header **Message** (not Massage) | same |
| Alarm Log datetime header | `<th>Date/Time</th>` | Alarm Log tab header **Date/Time** (not Date Time) | same |

### Prior gaps 1–10 (still same)

| # | Item | PHP result | React result | Verdict |
|---|---|---|---|---|
| 1 | List/card View/Edit/Delete text | AJAX actions include View / Edit / Delete text | List buttons show View / Edit / Delete with icons | same |
| 2 | No Device Categories on `/device` toolbar | List / Card / Add Device only | Same; Device Categories absent | same |
| 3 | Is Active checkbox | `type="checkbox"` | Ant Checkbox (`input[type=checkbox]`=1; `.ant-switch`=0) | same |
| 4 | Edit bare file input | `input type=file name=image id=image accept=image/*` | Same bare file input present | same |
| 5 | Custom Fields form heading | **Custom Fields** | **Custom Fields** | same |
| 6 | View info: Protocol; no Brand/Model/Template/Role | Name, Serial, Location, Description, Protocol, Additional Information | Same fields on Device Information card | same |
| 7 | View Device Data tab | Tab + Metrics/Value/Last update/Description | Same tab + columns | same |
| 8 | View Alarm Log tab | Range/status filters, table, paging, Open full Alarm Logs | Same filters, **Date/Time / Metrics / Severity / Message / Status**, paging, **Open full Alarm Logs** | same |
| 9 | View Metrics columns | Key, Label, Unit, Data Type, Triggers, Status | Same six columns | same |
| 10 | View Communication masking | Key/Value; `community` → `********` | Key/Value; `community` → `********` | same |

### Spot-check / plan rules / tests

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| Device list loads | AJAX list + View/Edit/Delete | Rows + View/Edit/Delete | same |
| Routes under `/api/v1` | n/a | devices / metrics / alarm-logs | same |
| Additive SQL / no Prisma | PHP DB intact | No Prisma; additive migrations | same |
| dataedge / alarm-engine | Unchanged | No wave-11 edits observed | same |
| `npm test -- test/wave11-devices.test.ts` | n/a | **5 passed** | same |

### Remaining gaps

None.

## Recommendation

accept

Lead decision: accept
