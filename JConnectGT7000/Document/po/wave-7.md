# Wave 7 PO

PHP: http://127.0.0.1/lorawan, http://127.0.0.1/calculation, http://127.0.0.1/control
React: http://127.0.0.1:5173/lorawan, http://127.0.0.1:5173/calculation, http://127.0.0.1:5173/control

Logged in on both as Supperadmin / 123456.

| Function | PHP result | React result | Verdict |
|---|---|---|---|
| LoRaWAN applications | Four local applications: External Network Room, Internal Network Room, Server Room, Toll Server Room. Descriptions match the stored rows | The same four names and descriptions | same |
| Calculations | The list says No calculations yet | The list says No calculations yet | same |
| Control service | The page shows control-logic offline. New group is disabled. Health is the existing `/controlapi/health` call, which is not JSON | The page shows control-logic offline. The API calls the same `/controlapi/health` URL and reports offline | same |
| Stack rules | PHP on port 80 still opens Control | No ChirpStack client and no new protocol code. `dataedge` and `alarm-engine` were not edited | same |

Creating a LoRaWAN application, saving a calculation, and sending a control command stay on the existing services. Those write paths were not copied into the new API.

## Tests

`cd backend && npm test` — 10 passed.
`cd backend && npm run typecheck` passed.
`cd webapp && npx tsc -b` passed.

## Recommendation

accept

Lead decision: accept
