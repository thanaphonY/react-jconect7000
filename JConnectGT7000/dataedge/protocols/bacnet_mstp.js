// NOTE: BACnet MS/TP requires a gateway or external service (Python BAC0, BACRouter, etc.)
const axios = require('axios');
const { saveDataPoint } = require('../core/dataSaver');
const { get } = require('lodash');

const mstpIntervals = new Map(); // deviceId => Map<metric.key, intervalId>

function fetchBACnetMSTP(device) {
  if (!mstpIntervals.has(device.id)) {
    mstpIntervals.set(device.id, new Map());
  }

  const deviceIntervals = mstpIntervals.get(device.id);

  for (const metric of device.metrics) {
    if (metric.type !== 'bacnet_read') continue;

    const oldInterval = deviceIntervals.get(metric.key);
    if (oldInterval) clearInterval(oldInterval);

    const interval = Math.max(Number(metric.pull_rate) || 10, 1);
    const proxy_url = device.communication_config?.proxy_url;
    const scale = Number(metric.scale || 1);
    const datatype = metric.datatype || 'float';

    const payload = {
      device_id: device.communication_config.device_id,
      object_type: metric.object_type,
      object_instance: metric.object_instance,
      property_id: metric.property_id
    };

    const intervalId = setInterval(async () => {
      try {
        const response = await axios.post(proxy_url, payload, { timeout: 3000 });
        const raw = get(response.data, 'value');
        if (raw === undefined) return;

        let val = raw;
        if (datatype === 'float') val = parseFloat(raw);
        else if (datatype === 'int') val = parseInt(raw);
        if (isNaN(val)) return;

        val = (scale === 0 || scale === 1) ? val : val * scale;

        await saveDataPoint(device, [{
          key: metric.key,
          value: parseFloat(val),
          timestamp: new Date()
        }]);

        try {
          const { relayGatewayPollToVisualEndpoints } = require('./gateway_endpoint_relay');
          await relayGatewayPollToVisualEndpoints(device, metric, parseFloat(val), new Date());
        } catch (e) {
          console.error(`[GW-EP relay] ${device.name}:`, e?.message || e);
        }

        console.log(`[BACnet/MSTP] ${device.name} - ${metric.key}: ${val}`);
      } catch (err) {
        console.error(`[BACnet/MSTP] ${device.name} ERROR:`, err.message);
      }
    }, interval);

    deviceIntervals.set(metric.key, intervalId);
  }
}

module.exports = { fetchBACnetMSTP };