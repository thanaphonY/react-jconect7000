# PEMON Metrics/OID helper

Open Add Device, choose an SNMP template, fill Device Info and Communication, then open Metrics → สร้าง PEMON Metrics/OID. Select the OIDs and polling interval (5–3600 whole seconds). Adding only edits the wizard draft. Review/edit generated metrics, then use the normal Confirm/Save flow. Existing metrics and their triggers remain unchanged; duplicate keys or numeric OIDs (including leading-dot notation and legacy OID aliases) are skipped. The same helper is available when editing an SNMP device. Gateway endpoints do not expose this helper.

This is a 17-metric starter mapping, not a full PEMON MIB or automatic device discovery. Numeric OIDs and multipliers are traced to `PEMON Test/data/last-snmp.json` (`decoded.mapped`) and `last-snmp-ui-compare.json` (`checks`). The fixtures come from different observations: validate against the actual device/firmware. No device address, credentials, live readings or notification channels are copied into the helper.

Excluded: text System Name (the current dataedge SNMP reader uses parseFloat), unverified OIDs, and zero-only examples where a multiplier cannot be established. Power units are left blank because the comparison fixture confirms a scaled value but does not specify a unit. No alarm thresholds or relays are generated.

The helper produces existing `snmp_get` metric fields and uses the normal device save API. It does not introduce a protocol engine, contact hardware, run SNMP SET, modify dataedge, or alter the database schema. Existing read-only server settings remain in effect.

Verification (Node 22):

```sh
node --test test/pemon.test.mjs
npm run build
```

The tests read the original fixture files without modifying them, verify every multiplier and OID, and check duplicate handling and preservation of existing metrics. Browser checks confirmed 57 existing template metrics become 74 after adding the 17 mappings, and a repeated attempt exposes 0 available entries. No test device was saved or polled.
