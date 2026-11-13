/**
 * Legacy name: mapping is protocol-agnostic (see ../../shared/gatewayVisualMapping.js).
 * Kept for backward-compatible requires; new code should use gateway_visual_mapping or shared module.
 */
const visual = require("../../shared/gatewayVisualMapping");

function defaultFunctionCodeForMappedKind(kind) {
  const k = String(kind || "").toLowerCase();
  if (k.includes("digital_output") || k.includes("coil") || /\bdo\b/.test(k)) return 1;
  if (k.includes("digital_input") || k.includes("discrete") || /\bdi\b/.test(k)) return 2;
  return 3;
}

function modbusReadSpecFromMetric(metric) {
  return {
    function_code: Number(metric?.function_code ?? 3),
    address: Number(metric?.address),
    length: Number(metric?.length ?? 1),
  };
}

function modbusSpecsComparableEqual(a, b) {
  if (!a || !b) return false;
  const fc1 = Number(a.function_code ?? 3);
  const fc2 = Number(b.function_code ?? 3);
  const ad1 = Number(a.address);
  const ad2 = Number(b.address);
  const len1 = Number(a.length ?? 1);
  const len2 = Number(b.length ?? 1);
  return fc1 === fc2 && ad1 === ad2 && len1 === len2;
}

/** @returns gateway poll metric row or null (any supported type, not only Modbus). */
function findGatewayModbusMetric(gateway, channel, kind) {
  return visual.findVisualSourceMetricForChannel(
    Array.isArray(gateway?.metrics) ? gateway.metrics : [],
    channel,
    kind
  );
}

module.exports = {
  ...visual,
  endpointMetricKey: visual.gatewayMetricKey,
  defaultFunctionCodeForMappedKind,
  findGatewayModbusMetric,
  modbusReadSpecFromMetric,
  modbusSpecsComparableEqual,
};
