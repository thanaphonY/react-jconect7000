# Wave 16 PO — LoRaWAN create/edit through ChirpStack

Logged in on both as Supperadmin / 123456 (PHP `email=Supperadmin`; React `/api/v1/auth/login` accepts the same name).

PHP: http://127.0.0.1/ (opened `/user/login` → `POST /user/authUser`, `/lorawan`, `/lorawan/add_application`, `POST /lorawan/add_application`, `POST /lorawan/edit_application_ajax/:id`, `/lorawan/get_device_profile/:tenantId`)
React: http://127.0.0.1:5173/ (routes `/login`, `/lorawan` return HTTP 200; compared live `/api/v1/lorawan/applications` CRUD and `/api/v1/lorawan/device-profiles`)

**Method note:** Cursor browser MCP tabs did not stay usable in this verifier session. Comparison used authenticated PHP HTML/JSON, React SPA route checks, page source / `LorawanPage.tsx`, and authenticated `/api/v1` round-trips. ChirpStack upstream (`/loraapi/rest` → `:8090`) is offline (404 / connection fail) on both stacks.

Wave 16 scope: LoRaWAN application create / edit forms and ChirpStack proxy behavior (list included where it gates those actions).

## Comparison

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| `/lorawan` opens | LoRaWAN Management; breadcrumb Home / LoRaWAN; LoRa Application List | Same titles and breadcrumb | same |
| Menu / route | Path `/lorawan` (not in Supperadmin sidebar menu) | Same path; not in login menu for this user; AppShell maps `bi-broadcast` if shown | same |
| List columns | Name, Tenant ID, Description, Created, Action | Same five columns | same |
| List rows (seed) | External / Internal / Server / Toll Network Room; same descriptions and tenant UUID | Same four apps, names, descriptions, tenant IDs | same |
| Created column values | `2025-07-14 17:18:16` (and matching :19/:20 offsets) from DB string | `2025-07-14 10:18:16` (UTC via `Date.toISOString()`) | different |
| List search / sort / paging | No search; no DataTable paging; natural DB order | `pagination={false}`; `ORDER BY name ASC` (same visible order for seed) | same |
| Empty list copy | No applications found | Same | same |
| Applications card header | Card header **Applications** (bg-primary) | No card / no Applications header | missing |
| + Add Application control | `btn-success` opens `#addAppModal` | Ant `type="primary"` opens Add modal | different |
| Edit control | `btn-warning` Edit | Ant default small Edit | different |
| Delete control | `btn-danger` link + `confirm('Delete this application?')` → ChirpStack DELETE + local delete | Ant `Modal.confirm` same title → `DELETE /api/v1/lorawan/applications/:id` → ChirpStack then local | same |
| Add modal title | Add Application | Add Application | same |
| Add submit | **Create** (`btn-success`) | Modal `okText="Create"` | same |
| Add Cancel | Cancel | Ant Modal Cancel | same |
| Field: name (add) | label Name; text; required; form name `name` | label Name; text; required; RHF/Antd `name` | same |
| Field: description (add) | label Description; text; optional | Same | same |
| Field: tenant_id (add) | label Tenant ID; text; required; form name `tenant_id` | label Tenant ID; text; required; JSON `tenantId` (same ChirpStack field) | same |
| Standalone `/lorawan/add_application` | Page **Create New Application**; label **Application Name**; Description; Tenant ID; Create Application | No React route; create only via modal with label Name | missing |
| Edit modal title | Edit Application | Edit Application | same |
| Edit submit | **Save** | Modal `okText="Save"` | same |
| Field: name (edit) | label Name; text; required | Same | same |
| Field: description (edit) | label Description; text; optional | Same | same |
| Field: tenant on edit | Not shown; ChirpStack update reuses local `tenant_id` | Same (tenant from existing row) | same |
| Create → ChirpStack payload | `POST applications` body `{ application: { name, description, tenantId } }` then local insert | Same payload via `createChirpstackApplication` then local insert | same |
| Create when ChirpStack down | Modal POST → standalone form re-shown; **no** Failed alert (`$data` not passed to view); list unchanged | `POST /api/v1/lorawan/applications` → **502** `CHIRPSTACK_ERROR` / Invalid ChirpStack JSON; toast error | different |
| Edit → ChirpStack payload | Intended `PUT applications/{id}` with `{ application: { name, description, tenantId } }` then local update | Same ChirpStack PUT + local update by `applicationId` | same |
| Edit save live | `edit_application_ajax` looks up `WHERE id = <UUID from UI>` → DB error; no JSON `{success:true}` | With CS down: **502** `CHIRPSTACK_ERROR` (no local change). Lookup by `application_id` is correct | different |
| Success strings | Flash `Application created: {id}` / `Application updated` / `Application deleted` | Same toast strings | same |
| Device profiles proxy | `GET /lorawan/get_device_profile/:tenantId` → `{status:false,message:"Failed to fetch device profiles"}` when CS down | `GET /api/v1/lorawan/device-profiles?tenantId=` → 502 `{error:{code:"CHIRPSTACK_ERROR",message:"Invalid ChirpStack JSON response"}}` | different |
| ChirpStack base URL / auth | `chirpstack_base_url` = `http://localhost/loraapi/rest/api/` + Bearer API key | `CHIRPSTACK_BASE_URL` default `http://127.0.0.1/loraapi/rest/api` + same Bearer default | same |
| Language strings on page | Hard-coded English (LoRaWAN Management, list, modal labels) | Same hard-coded English (not i18n keys) | same |
| Images / list icons | No app images; Edit/Delete are Bootstrap buttons | No app images; Ant buttons | different |
| `/api/v1` routes | n/a | `/lorawan/applications` list/create/update/delete; `/lorawan/device-profiles` | same |
| Additive SQL / no Prisma | PHP DB unchanged | No wave-16 migration; no Prisma | same |
| dataedge / alarm-engine | Unchanged | Unchanged; ChirpStack called from backend only | same |

## Tests

`cd backend && npm test -- test/wave16-lorawan.test.ts` — **6 passed**.

## Recommendation

return

Lead decision: return — Created times Asia/Bangkok local (not UTC ISO); Applications card header; Add btn-success / Edit btn-warning chrome; standalone `/lorawan/add_application` with Application Name label; ChirpStack-down create soft fail like PHP (no loud 502 toast if PHP silent); device-profile error message match PHP Failed to fetch device profiles.
