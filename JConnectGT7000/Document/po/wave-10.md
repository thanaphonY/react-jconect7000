# Wave 10 PO — Dashboard grid editor (re-verify after gap fixes)

Logged in on both as Supperadmin / 123456.

PHP: http://127.0.0.1/ (opened `/dashboard`, `/dashboard/add`, `/dashboard/edit/1`, `/dashboard/view/1`, `/dashboard/edit_layout/1`)
React: http://127.0.0.1:5173/ (opened `/dashboard`, `/dashboard/add`, `/dashboard/edit/1`, `/dashboard/view/1`, `/dashboard/edit_layout/1`, `/dashboard/edit_layout/2`)

Configure panels opened for **power_meter** and **zone_overview** (Home layout). Layout save round-trip re-exercised on Dashboard 2 (Add Widget → Table from catalog → Save → reload; config object intact).

## Prior gaps (re-check)

| Gap | PHP | React after fix | Verdict |
|---|---|---|---|
| List Search | DataTables `Search:` box | `Search:` label + input filters name/description | same |
| Editor `add_widget` label | **+ Add Widget** | i18n `add_widget` = **+ Add Widget** | same |
| Catalog option labels | Widget **name** only (e.g. Alarm Summary, Table) | Name only (`label: w.name`); no `(widget_type)` suffix | same |
| power_meter device label | Live UI label **Device** | Configure shows **Device** | same |
| zone_overview special labels | Card Icon; Show Primary KPI (left); Label/Unit/Device/Metric; Metrics (right side); Alarm Monitor Devices | Same labels on React drawer | same |
| zone_overview `show_primary` options | Yes — show metric / No — show alarm count | Same options; selected **Yes — show metric** | same |
| Config JSON on add+save+reload | Object JSON | Catalog `config` string `"{}"` parsed via `parseWidgetConfig` before spread; Table saved as `{label:"Table"}` with no char-index keys | same |

## Full comparison

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| `/dashboard` route opens | List of layouts | List of layouts | same |
| List columns | Order, Name, Description, Action | Order, Name, Description, Action | same |
| List drag reorder | `bi-grip-vertical` handle; saves order | `bi-grip-vertical` drag handle; PUT `/api/v1/dashboards/sort-order` | same |
| List paging | DataTables 10/25/50/100, default 25 | Ant Table 10/25/50/100, default 25 | same |
| List search filter | `Search:` box present | `Search:` box present; filters rows | same |
| Add Dashboard button | `bi-plus-circle` + Add Dashboard | `bi-plus-circle` + Add Dashboard | same |
| List actions View/Edit/Set Home/Delete | Text buttons; Set Home hidden for current home | Same text actions; Set Home hidden for home | same |
| `/dashboard/add` fields | `name` required; `description` optional; `sort_order` Order + helper | Same labels/types/required; form `sortOrder` (API camelCase) | same |
| `/dashboard/edit/{id}` fields | name/description/`sort_order` read back (Home / "" / 10) | Same values read back | same |
| Add/Edit submit labels | Create / Cancel; Save / Back | Create / Cancel; Save / Back | same |
| `/dashboard/view/{id}` | Breadcrumb + Edit Layout | Breadcrumb + Edit Layout | same |
| `/dashboard/edit_layout/{id}` GridStack | `.grid-stack` canvas with items | `.grid-stack` canvas with items | same |
| Editor toolbar Add Widget label | **+ Add Widget** | **+ Add Widget** | same |
| Editor toolbar Save | Save present | Save present | same |
| Widget toolbar icons | `bi-gear` / `bi-copy` / `bi-trash` | Same three Bootstrap Icon classes | same |
| Add Widget modal title / choose / add | Add Widget to Layout; `-- Choose Widget --`; Add to Layout | Same strings | same |
| Add Widget option labels | Name only (15 widgets) | Name only (15 widgets) | same |
| Shared appearance fields | `show_border`, `bg_color`, `bg_opacity` | Same keys/labels/types | same |
| WidgetFieldMap source parity | Fields in `edit_layout.php` | Ported in `widgetFieldMap.ts` | same |
| Configure power_meter fields | Meter Title, Device, power/connection/voltage/current keys, appearance | Same keys/labels/types | same |
| Configure zone_overview fields | zone map fields + Card Icon / Show Primary KPI / Label/Unit/Device/Metric / Metrics (right side) / Alarm Monitor Devices | Same | same |
| zone_overview show_primary options | Yes — show metric / No — show alarm count | Same | same |
| Icon_select WIDGET_ICON_OPTIONS | 52 `bi-*` classes | Same 52 values/labels in `icons.ts` | same |
| Sidebar logo | `assets/images/image_(5)_1755442027.png` | Same asset via `/api/v1/assets/images/...` | same |
| Menu Dashboard icon | `bi-speedometer` | `bi-speedometer` | same |
| Layout save round-trip | n/a (React exercised) | Add Table + Save + reload: 3 items return on layout | same |
| Saved widget config integrity | Object JSON | No char-index corruption after add from string catalog config | same |
| Catalog types without WidgetFieldMap entry | gauge/camera/navigator/ups/heatmap/cooling/svg not in DB catalog (15 widgets) | Same catalog size | same |
| `/api/v1` dashboards routes | n/a | `/api/v1/dashboards`, sort-order, layout PUT, home, CRUD | same |
| Additive SQL / no Prisma | PHP DB still works | `Database/migrations/001_schema_migrations.sql` only; no Prisma | same |
| dataedge / alarm-engine | Unchanged | No edits by this wave | same |

## Tests

`cd backend && npm test -- test/dashboards.test.ts` — **5 passed**.

## Notes

- Dashboard 2 left with 3 layout items after round-trip verification (Alarm Summary, Table, Value Box Roundtrip Test). Configs are clean objects.
- List action buttons remain text (View/Edit/Set Home/Delete) on both; grip + Add use Bootstrap Icons on both.

## Recommendation

accept

Lead decision: accept
