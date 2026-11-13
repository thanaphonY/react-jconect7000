const Bacnet = require('bacstack');
const { saveDataPoint } = require('../core/dataSaver');

const bacnetIntervals = new Map(); // deviceId => Map<metric.key, intervalId>

function fetchBACnetIP(device) {
  if (!device.communication_config?.ip || !device.communication_config?.device_id) {
    console.warn('[BACnet/IP] Missing IP or device_id in config');
    return;
  }

  if (!bacnetIntervals.has(device.id)) {
    bacnetIntervals.set(device.id, new Map());
  }

  const deviceIntervals = bacnetIntervals.get(device.id);
  const client = new Bacnet({ port: device.communication_config.port || 47808 });

  for (const metric of device.metrics) {
    if (metric.type !== 'bacnet_read') continue;

    const oldInterval = deviceIntervals.get(metric.key);
    if (oldInterval) clearInterval(oldInterval);

    const interval = Math.max(Number(metric.pull_rate) || 10, 1);
    var pull_rate = interval*1000;
    const objectId = { type: metric.object_type, instance: metric.object_instance };
    const propertyId = metric.property_id;
    const scale = Number(metric.scale || 1);
    const datatype = metric.datatype || 'float';

    const intervalId = setInterval(() => {
      client.readProperty(device.communication_config.ip, objectId, propertyId, (err, value) => {
        if (err) {
          console.error(`[BACnet/IP] ${device.name} - ${metric.key} ERROR:`, err.message);
          return;
        }

        const rawVal = value?.values?.[0]?.value;
        if (rawVal === undefined) return;

        let val = rawVal;
        if (datatype === 'float') val = parseFloat(val);
        else if (datatype === 'int') val = parseInt(val);
        if (isNaN(val)) return;

        val = (scale === 0 || scale === 1) ? val : val * scale;

        saveDataPoint(device, [{
          key: metric.key,
          value: parseFloat(val),
          timestamp: new Date()
        }]);

        try {
          const { relayGatewayPollToVisualEndpoints } = require('./gateway_endpoint_relay');
          void relayGatewayPollToVisualEndpoints(device, metric, parseFloat(val), new Date()).catch((e) =>
            console.error(`[GW-EP relay] ${device.name}:`, e?.message || e)
          );
        } catch (e) {
          console.error(`[GW-EP relay] ${device.name}:`, e?.message || e);
        }

        console.log(`[BACnet/IP] ${device.name} - ${metric.key}: ${val}`);
      });
    }, pull_rate);

    deviceIntervals.set(metric.key, intervalId);
  }
}

module.exports = { fetchBACnetIP };