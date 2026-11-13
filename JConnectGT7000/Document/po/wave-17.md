# Wave 17 PO — Calculation save

Logged in on both as Supperadmin / 123456 (PHP `email=Supperadmin`; React `/api/v1/auth/login` accepts the same name).

PHP: http://127.0.0.1/ (opened `/calculation`, create modal, `POST /calculation/save`, `POST /calculation/delete`)
React: http://127.0.0.1:5173/ (opened `/login`, `/calculation`; compared live create/edit modal and `/api/v1/calculations` CRUD)

Wave 17 scope: Calculation list plus create / edit / save (and delete as the list action that gates editing).

## Comparison

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| `/calculation` opens | Calculate; subtitle Create derived metrics from device values or random inputs. | Same title and subtitle | same |
| Menu / route | Path `/calculation` (not in Supperadmin sidebar menu) | Same path; not in login menu; AppShell maps `bi-calculator` if shown | same |
| List columns | Device, Metric, Formula, Inputs, Trigger, Actions | Same six columns | same |
| List search | Client filter on device / metric key / formula; placeholder Search device, metric, or formula | Same client filter and placeholder | same |
| List sort / paging | Devices `ORDER BY name ASC`; no DataTable paging | Same device order from API; `pagination={false}` | same |
| Empty list copy | No calculations yet. | Same | same |
| List card chrome | Table wrapped in Bootstrap `card` / `card-body` | No card wrapper around table | missing |
| Seed / live rows | Empty until save; after create shows device name + #id, key, label, formula, inputs, trigger mode | Same row shape via `GET /api/v1/calculations` | same |
| Input display (device) | `var = [devN:]metricKey` | Same `formatInput` | same |
| Input display (random) | `var = random[min..max]` | Same | same |
| Trigger cell | Bootstrap `badge text-bg-success` with mode text | Ant `Badge status="success"` with mode text | different |
| + New calculation | `btn-primary` + `bi-plus-circle` + New calculation | Ant primary + `PlusOutlined` + New calculation | different |
| Edit control | `btn-outline-primary btn-sm` Edit opens modal | Ant small Edit opens modal | different |
| Delete control | `btn-outline-danger btn-sm` Delete; `confirm('Remove this calculation?')` | Ant danger Delete; `Modal.confirm` title Remove this calculation? | same |
| Delete success | Flash Calculation removed. | Toast Calculation removed. | same |
| Modal title | Calculation | Calculation | same |
| Modal Cancel / Save | Cancel; Save calculation | Cancel; Save calculation | same |
| Field: Output device | label Output device; select `device_id`; required; options `name (#id)` | label Output device; Select `deviceId`; required; same option labels | same |
| Field: Metric key | label Metric key; text; required; name `key` | Same label/type/required; RHF `key` | same |
| Field: Label | label Label; text; optional; name `label` | Same | same |
| Field: Unit | label Unit; text; optional; name `unit` | Same | same |
| Field: Datatype | label Datatype; select float/int; default float | Same options and default | same |
| Field: Formula | label Formula; text monospace; required; placeholder `(a + b) / 2`; help Use the variable names defined in Inputs. | Same | same |
| Inputs section | Strong Inputs; Add input (`bi-plus`) | Strong Inputs; Add input (`PlusOutlined`) | different |
| Field: Variable | label Variable; text; default `a` on create; not HTML-required | Same label/default; Form rule required | different |
| Field: Source | label Source; Device metric / Random | Same options | same |
| Field: Device (input) | label Device; Same device + device list; clearable empty = same device | Same placeholder and options | same |
| Field: Metric (input) | label Metric; select metrics for resolved device; placeholder Select metric; not HTML-required | Same options/placeholder; Form rule required | different |
| Field: Minimum / Maximum | Shown when Source=Random; number step any; defaults 0 / 1 | Same | same |
| Input row remove | `btn-outline-danger` with `bi-trash` only (no text) | Danger button text Delete | different |
| Field: Trigger | Interval / On input update / Cron schedule; default Interval | Same options and default | same |
| Field: Interval (seconds) | number min 1; default 30; visible when interval | Same; InputNumber min 1 default 30 | same |
| Field: Cron | text monospace; placeholder `*/5 * * * *`; visible when schedule; server requires non-empty | Same visibility/placeholder; Form rule required for schedule | different |
| Field: Decimals | number 0–8; default 2 | Same | same |
| Field: Max input age (seconds) | number min 0; optional empty | Same; empty omits `maxAgeSec` | same |
| Create defaults | device first option; key/label/unit empty; float; formula empty; one input `{var:a,source:device}`; interval 30; decimals 2 | Same | same |
| Edit load | Fills original device/key, metric fields, inputs, trigger, cron/interval, decimals, maxAge | Same values from list row (verified schedule row: key, label, formula, cron `*/5 * * * *`, decimals 1) | same |
| Save → DB metric shape | `type:calc`, enable, store_policy always, pull_rate, `calc.{formula,inputs,trigger,decimals[,maxAgeSec]}`, triggers [], normal_condition {} | Same shape via `POST /api/v1/calculations` | same |
| Create save read-back | PHP save writes device metrics; React list/API sees row | React create; PHP `/calculation` shows same key/label/formula/inputs/trigger | same |
| Edit save read-back | Updates in place; can move device (remove from original) | Same; duplicate key → 409 Metric key already exists on the selected device. | same |
| Schedule / empty interval_sec pull_rate | Empty `interval_sec` → `pull_rate = 1` | Omitted `intervalSec` → `pull_rate = 30` | different |
| Success string (save) | Calculation saved. | Toast Calculation saved. | same |
| Language strings on page | Hard-coded English (Calculate, list, modal labels) | Same hard-coded English | same |
| Images / logos | No calculation images; plus/trash Bootstrap icons | No calculation images; Ant icons / Delete text | different |
| `/api/v1` routes | n/a | `GET/POST /calculations`, `DELETE /calculations/:deviceId/:key` | same |
| Additive SQL / no Prisma | PHP DB unchanged | No wave-17 migration; no Prisma | same |
| dataedge / alarm-engine | Unchanged | Unchanged; metrics written on `devices` only | same |

## Tests

`cd backend && npm test -- test/wave17-calculations.test.ts` — **5 passed**.

Lead decision: return — fix list card, bi icons, required flags, pull_rate empty→1.
