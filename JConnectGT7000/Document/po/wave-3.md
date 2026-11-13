# Wave 3 PO

PHP: http://127.0.0.1/device, http://127.0.0.1/device/device_categories, http://127.0.0.1/devicetemplate
React: http://127.0.0.1:5173/device, http://127.0.0.1:5173/device_categories, http://127.0.0.1:5173/devicetemplate

Logged in on both as Supperadmin / 123456.

| Function | PHP result | React result | Verdict |
|---|---|---|---|
| Device list | 22 devices. First page, name order: CRAC 1, CRAC 2, Packet Power Gateway GW04, then the power cords. Brand and model come from the template. Status Inactive. Search, status, template, and location filters. Page size 10 | The same first ten names, brands, models, and Inactive status. The same filters and page size. Search for a saved device finds that row | same |
| Open device form, save, read back | Add and edit save name, template, serial, location, description, and active, then the list shows the saved row | Add Device saved "Wave3 browser" on template STULZ CyberCool Indoor, serial W3-UI, location Lab UI. Reload of the edit page read location Lab UI 2 after the update. The list showed those values. Delete removed the row. Original devices remain | same |
| Category list | 20 categories, including Access Control, CRAC, PDU, UPS, and the DCIM categories. Add, edit, delete | The same 20 names and descriptions. Add Category saved "Wave3 category" and it appeared in the table. Delete removed it and the count returned to 20 | same |
| Template list | 18 templates, newest first: STULZ CyberCool Indoor snmp Yes, E300 snmp Yes, through ALLTEC-LR-S-HT mqtt No. Add, edit, delete | The same 18 names, protocols, and Yes/No flags in the same order. Add Template saved "Wave3 template" as modbus_tcp, category Access Control, active Yes, and the list read that row back. Delete removed it and the count returned to 18 | same |
| Stack rules | PHP on port 80 still opens Devices | Routes are under `/api/v1`. Device delete is soft (`is_deleted`). Updates do not wipe metrics or communication config. No Prisma. `dataedge` and `alarm-engine` were not edited | same |

The PHP device form also has a protocol metric wizard, image upload, and card view. Those editors stay on PHP for this wave. React save does not overwrite the JSON those screens store. Live charts are wave 4.

## Tests

`cd backend && npm test` — 10 passed.
`cd backend && npm run typecheck` passed.
`cd webapp && npx tsc -b` passed.

## Recommendation

accept

Lead decision: accept
