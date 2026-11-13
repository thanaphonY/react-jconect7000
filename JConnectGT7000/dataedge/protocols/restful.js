const axios = require('axios');
const { get } = require('lodash');
const { saveDataPoint } = require('../core/dataSaver');

const restIntervals = new Map(); // deviceId => Map<metric.key, intervalId>

function parseValueFromResponse(response, path, datatype, scale) {
  const raw = get(response.data, path);
  if (raw === undefined) return null;

  let value = raw;
  if (datatype === 'float') value = parseFloat(raw);
  else if (datatype === 'int') value = parseInt(raw);
  if (isNaN(value)) return null;

  return (scale === 0 || scale === 1) ? value : value * scale;
}

function fetchREST(device) {
  if (!restIntervals.has(device.id)) {
    restIntervals.set(device.id, new Map());
  }

  const deviceIntervals = restIntervals.get(device.id);
  const restMetrics = device.metrics.filter((m) => m.type === 'rest_get');
  console.log(
    `[REST] polling device id=${device.id} name="${device.name}" rest_get metrics=${restMetrics.length}`
  );

  for (const metric of device.metrics) {
    if (metric.type !== 'rest_get') continue;

    const oldInterval = deviceIntervals.get(metric.key);
    if (oldInterval) clearInterval(oldInterval);

    const interval = Math.max(Number(metric.pull_rate) || 10, 1);
    var pull_rate = interval*1000;
    const method = (metric.method || 'GET').toUpperCase();
    const url = metric.url;
    const jsonPath = metric.json_path;
    const datatype = metric.datatype || 'float';
    const scale = Number(metric.scale || 1);

    const intervalId = setInterval(async () => {
      try {
        const response = await axios({ method, url, timeout: 3000 });
        const val = parseValueFromResponse(response, jsonPath, datatype, scale);
        if (val === null) {
          console.warn(`[REST] ${device.name} - ${metric.key} JSON path failed: ${jsonPath}`);
          return;
        }

        await saveDataPoint(device, [{
          key: metric.key,
          value: val,
          timestamp: new Date()
        }]);

        try {
          const { relayGatewayPollToVisualEndpoints } = require('./gateway_endpoint_relay');
          await relayGatewayPollToVisualEndpoints(device, metric, val, new Date());
        } catch (e) {
          console.error(`[GW-EP relay] ${device.name}:`, e?.message || e);
        }

        console.log(`[REST] ${device.name} - ${metric.key}: ${val}`);
      } catch (err) {
        console.error(`[REST] ${device.name} - ${metric.key} ERROR:`, err.message);
      }
    }, pull_rate);

    deviceIntervals.set(metric.key, intervalId);
  }
}

// Cleanup function for RESTful devices
function cleanupRESTDevice(deviceId) {
  console.log(`[REST] Cleaning up device ${deviceId}...`);
  
  // Clear all intervals for this device
  if (restIntervals.has(deviceId)) {
    const deviceIntervals = restIntervals.get(deviceId);
    for (const intervalId of deviceIntervals.values()) {
      clearInterval(intervalId);
      console.log(`[REST] Cleared interval for device ${deviceId}`);
    }
    restIntervals.delete(deviceId);
    console.log(`[REST] Removed device ${deviceId} from restIntervals`);
  }
  
  console.log(`[REST] Device ${deviceId} cleanup completed`);
}

module.exports = { fetchREST, cleanupRESTDevice };