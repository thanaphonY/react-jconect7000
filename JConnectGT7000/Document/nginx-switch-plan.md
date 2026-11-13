# Nginx switch plan

PHP stays on port 80. This plan is the cutover, not the cutover itself. Do not point `/` at the React build until the remaining pages below are done. `dataedge` and `alarm-engine` stay separate processes.

## Migrated

These React routes match the PHP screens that were compared:

| PHP | React |
|---|---|
| `/user/login`, logout, menu, English/Thai | `/login`, Sign Out, sidebar, language |
| `/home`, `/dashboard` | `/home`, `/dashboard` |
| `/device`, `/device/device_categories`, `/devicetemplate` | `/device`, `/device_categories`, `/devicetemplate` |
| `/widgets`, `/graph` | `/widgets`, `/graph` |
| `/alarmlogs`, `/eventlogs`, `/notification_manager` | `/alarmlogs`, `/eventlogs`, `/notification_manager` |
| `/reports`, `/reports/files`, `/reports/schedules`, `/reports/builder`, `/reports/run/:id` | `/reports`, `/reports/files`, `/reports/schedules`, `/reports/builder`, `/reports/run/:id` |
| `/lorawan`, `/calculation`, `/control` | `/lorawan`, `/calculation`, `/control` |

API is `/api/v1` on port 4000. During development Vite on port 5173 proxies `/api` there.

## Still on PHP

- Users, profile, and settings
- Forgot password and dark mode
- Device metric wizard, images, and card view
- Dashboard grid editor
- Alarm acknowledge, resolve, and export
- Notification channel create, edit, test send, and health checks
- Report builder, schedules, and creating a new export (implemented in React; pending wave-15 verify)
- LoRaWAN create and edit through ChirpStack
- Saving a calculation
- Control commands, schedules, sequences, and rules (control-logic is offline on both apps today)
- Phase 2: 3D visualization, asset management, site management

## Cutover

1. Build the webapp and serve it as static files.
2. Keep Nginx on port 80. Send `/api/` to the backend on port 4000.
3. Leave `/controlapi/` pointed at control-logic on port 3006, the same hop PHP uses.
4. Keep the PHP app reachable on an internal path until the remaining pages are migrated. Do not remove the PHP containers in this step.
5. Confirm an existing user can sign in, open Home, and download a report file before removing the PHP default site.
