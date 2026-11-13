/**
 * gateway_endpoint: visual / logical device — no field polling here.
 * Values arrive from the linked collector (role=gateway) when any of its poll metrics
 * is saved; gateway_endpoint_relay mirrors matching gateway_mapped points.
 *
 * communication_config: { protocol: "gateway_endpoint", source: { gateway_device_id }, connection_path?: [...] }
 * metrics: type "gateway_mapped", source: { channel, kind, gateway_device_id? }
 */
const db = require("../config/dbConfig");

function parseJson(v, fallback) {
  if (v == null) return fallback;
  if (Array.isArray(v)) return v;
  if (typeof v === "object") return v;
  try {
    return JSON.parse(String(v));
  } catch {
    return fallback;
  }
}

async function loadGatewayRow(gatewayId) {
  const r = await db.query(
    `SELECT id, name, communication_config, metrics, is_active, is_deleted
     FROM devices WHERE id = $1`,
    [gatewayId]
  );
  if (!r.rowCount) return null;
  const row = r.rows[0];
  if (!row.is_active || row.is_deleted) return null;
  row.metrics = parseJson(row.metrics, []);
  row.communication_config = parseJson(row.communication_config, {});
  const proto = row.communication_config?.protocol || row.metrics?.[0]?.protocol;
  row.protocol = proto;
  return row;
}

/** Register-only: no intervals on the visual device. */
async function fetchGatewayEndpoint(endpoint) {
  const comm = endpoint.communication_config || {};
  const gatewayId = Number(comm.source?.gateway_device_id);
  if (!Number.isFinite(gatewayId)) {
    console.error(`[GW-EP] ${endpoint.name} (#${endpoint.id}) missing communication_config.source.gateway_device_id`);
    return;
  }

  const gateway = await loadGatewayRow(gatewayId);
  if (!gateway) {
    console.error(`[GW-EP] gateway id=${gatewayId} not found or inactive for endpoint ${endpoint.name} (#${endpoint.id})`);
    return;
  }

  const gwProto = String(gateway.protocol || "").toLowerCase() || "unknown";
  console.log(
    `[GW-EP] visual "${endpoint.name}" (#${endpoint.id}) → collector "${gateway.name}" (#${gateway.id}, protocol=${gwProto}); values mirrored from gateway poll metrics`
  );
}

function cleanupGatewayEndpoint(_deviceId) {}

module.exports = { fetchGatewayEndpoint, cleanupGatewayEndpoint };
