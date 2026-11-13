/**
 * Visual device (gateway_endpoint) ↔ collector (role=gateway) mapping.
 * Resolves gateway_mapped { channel, kind } to a concrete gateway poll metric key
 * for Modbus, SNMP, BACnet, REST, etc. — not Modbus-specific.
 *
 * Web UI copy (keep in sync): gen3/web/src/lib/gateway-visual-mapping.ts
 */

const POLL_TYPES = new Set(["modbus_read", "snmp_get", "snmp_walk", "bacnet_read", "rest_get"]);

function metricEnabled(m) {
  if (m?.enable === false || m?.enable === "false") return false;
  return true;
}

/** Canonical metric key on gateway device (DB / WS). */
function gatewayMetricKey(m) {
  if (m == null) return "";
  return String(m.key ?? m.metricKey ?? m.metric_key ?? "").trim();
}

function pollTypeNorm(m) {
  return String(m?.type || "").toLowerCase();
}

function listVisualSourceMetrics(metrics) {
  const arr = Array.isArray(metrics) ? metrics : [];
  return arr.filter((m) => m && POLL_TYPES.has(pollTypeNorm(m)) && metricEnabled(m));
}

/**
 * Pick the gateway metric that supplies data for a gateway_mapped channel/kind.
 * Priority: source.channel → modbus address → 1-based order in poll list → source.kind match.
 */
function findVisualSourceMetricForChannel(metrics, channel, kind) {
  const sources = listVisualSourceMetrics(metrics);
  const ch = Number(channel);
  if (!Number.isFinite(ch)) return null;

  const bySourceCh = sources.find((m) => Number(m?.source?.channel) === ch);
  if (bySourceCh) return bySourceCh;

  const modbusReads = sources.filter((m) => pollTypeNorm(m) === "modbus_read");
  const byAddr = modbusReads.find((m) => Number(m.address) === ch);
  if (byAddr) return byAddr;

  if (ch >= 1 && ch <= sources.length) return sources[ch - 1];

  const kn = String(kind || "").toLowerCase();
  const byKind = sources.find((m) => String(m?.source?.kind || "").toLowerCase() === kn);
  return byKind || null;
}

function resolveVisualSourceMetricKey(metrics, channel, kind) {
  const m = findVisualSourceMetricForChannel(metrics, channel, kind);
  return m ? gatewayMetricKey(m) || null : null;
}

/**
 * Explicit gateway metric key stored on an endpoint `gateway_mapped` metric.
 * This is the primary relation between a visual endpoint metric and its source
 * gateway poll metric (replaces channel/kind resolution). Channel/kind are kept
 * only as a legacy fallback for rows saved before this field existed.
 */
function endpointGatewaySourceKey(endpointMetric) {
  const src =
    endpointMetric && typeof endpointMetric.source === "object" && endpointMetric.source
      ? endpointMetric.source
      : {};
  const k = src.gateway_metric_key ?? src.metric_key ?? src.gatewayMetricKey;
  return k == null ? "" : String(k).trim();
}

/**
 * Resolve which gateway metric key feeds an endpoint `gateway_mapped` metric.
 * Priority: explicit source.gateway_metric_key → legacy channel/kind resolution.
 * @param {object[]} gatewayMetrics - parent gateway poll metrics
 * @param {object} endpointMetric - endpoint gateway_mapped metric row
 */
function resolveGatewayKeyForEndpointMetric(gatewayMetrics, endpointMetric) {
  const explicit = endpointGatewaySourceKey(endpointMetric);
  if (explicit) return explicit;
  const src =
    endpointMetric && typeof endpointMetric.source === "object" && endpointMetric.source
      ? endpointMetric.source
      : {};
  return resolveVisualSourceMetricKey(gatewayMetrics, src.channel, src.kind);
}

/** Slot index for mappable UI (same resolution order as findVisualSourceMetricForChannel). */
function canonicalChannelForVisualSource(m, indexOneBased) {
  const ch = Number(m?.source?.channel);
  if (Number.isFinite(ch)) return ch;
  if (pollTypeNorm(m) === "modbus_read") {
    const addr = Number(m?.address);
    if (Number.isFinite(addr)) return addr;
  }
  return indexOneBased;
}

module.exports = {
  POLL_TYPES,
  metricEnabled,
  gatewayMetricKey,
  listVisualSourceMetrics,
  findVisualSourceMetricForChannel,
  resolveVisualSourceMetricKey,
  endpointGatewaySourceKey,
  resolveGatewayKeyForEndpointMetric,
  canonicalChannelForVisualSource,
};
