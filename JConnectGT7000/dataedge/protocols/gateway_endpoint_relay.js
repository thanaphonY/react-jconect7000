/**
 * When a gateway (collector) device saves a poll metric, mirror the value onto
 * gateway_endpoint "visual" devices whose gateway_mapped metric points at that gateway
 * metric key (source.gateway_metric_key; legacy channel/kind resolved as fallback).
 */
const { saveDataPoint } = require('../core/dataSaver');
const {
  metricEnabled,
  gatewayMetricKey,
  resolveGatewayKeyForEndpointMetric,
} = require('../../shared/gatewayVisualMapping');
const { isGatewayEndpointDevice } = require('../core/deviceRouting');

function getAllDevicesLazy() {
  try {
    const { getAllDevices } = require('../core/dataFetcher');
    return getAllDevices() || [];
  } catch {
    return [];
  }
}

function gatewayDeviceIdFromEndpoint(endpoint) {
  const id = Number(endpoint.communication_config?.source?.gateway_device_id);
  return Number.isFinite(id) ? id : null;
}

function endpointMappedMetricKey(m) {
  if (m == null) return "";
  return String(m.key ?? m.metricKey ?? m.metric_key ?? "").trim();
}

function formatStoredValue(scaled) {
  if (scaled === 0 || scaled === 1) return parseFloat(scaled);
  return parseFloat(parseFloat(scaled).toFixed(2));
}

/**
 * @param {object} gatewayDevice - in-memory device from dataFetcher (any field protocol)
 * @param {object} gatewayMetric - metric row that was just read (modbus_read, snmp_get, bacnet_read, …)
 * @param {number} value - scaled / numeric value to mirror
 * @param {Date} [timestamp]
 */
async function relayGatewayPollToVisualEndpoints(gatewayDevice, gatewayMetric, value, timestamp) {
  if (!gatewayDevice || !gatewayMetric) return;
  const gk = gatewayMetricKey(gatewayMetric);
  if (!gk) return;

  const t = timestamp || new Date();
  const numericValue = formatStoredValue(value);
  const all = getAllDevicesLazy();
  const gwMetrics = Array.isArray(gatewayDevice.metrics) ? gatewayDevice.metrics : [];

  for (const ep of all) {
    if (!isGatewayEndpointDevice(ep)) continue;
    if (gatewayDeviceIdFromEndpoint(ep) !== gatewayDevice.id) continue;

    for (const m of ep.metrics || []) {
      if (String(m.type) !== "gateway_mapped") continue;
      if (!metricEnabled(m)) continue;
      const mk = endpointMappedMetricKey(m);
      if (!mk) continue;

      const resolvedGk = resolveGatewayKeyForEndpointMetric(gwMetrics, m);
      if (!resolvedGk || resolvedGk !== gk) continue;

      await saveDataPoint(ep, [{ key: mk, value: numericValue, timestamp: t }]);
    }
  }
}

/** @deprecated use relayGatewayPollToVisualEndpoints */
async function relayModbusReadToGatewayEndpoints(gatewayDevice, modbusMetric, scaled, timestamp) {
  return relayGatewayPollToVisualEndpoints(gatewayDevice, modbusMetric, scaled, timestamp);
}

module.exports = { relayGatewayPollToVisualEndpoints, relayModbusReadToGatewayEndpoints };
