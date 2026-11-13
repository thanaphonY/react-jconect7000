const ModbusRTU = require('modbus-serial');
const { saveDataPoint } = require('../core/dataSaver');

// Router/Converter RTU <-> TCP mode
// Key difference from modbus_tcp:
// - Prefer per-device TCP connection (avoid shared pool across multiple unit IDs)
// - Serialize requests per device with configurable inter-request delay

const deviceIntervals = new Map(); // deviceId => Map<metric.key, intervalId>
const deviceClients = new Map(); // deviceId => client
const requestQueues = new Map(); // deviceId => Promise chain

function bufferToValue(buffer, datatype) {
  switch (datatype) {
    case 'int16': return buffer.readInt16BE(0);
    case 'uint16': return buffer.readUInt16BE(0);
    case 'int32': return buffer.readInt32BE(0);
    case 'uint32': return buffer.readUInt32BE(0);
    case 'float32': return buffer.readFloatBE(0);
    case 'float32_swap': {
      const swapped = Buffer.from([buffer[2], buffer[3], buffer[0], buffer[1]]);
      return swapped.readFloatBE(0);
    }
    case 'float32_fullswap': {
      const swapped = Buffer.from([buffer[3], buffer[2], buffer[1], buffer[0]]);
      return swapped.readFloatBE(0);
    }
    default:
      console.warn(`[ModbusRouter] Unsupported datatype: ${datatype}`);
      return null;
  }
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function enqueue(deviceId, fn) {
  const prev = requestQueues.get(deviceId) || Promise.resolve();
  const next = prev.then(fn, fn);
  requestQueues.set(deviceId, next.catch(() => {}));
  return next;
}

async function getOrCreateClient(device) {
  if (deviceClients.has(device.id)) return deviceClients.get(device.id);

  const config = device.communication_config || {};
  const ip = config.ip;
  const port = Number(config.port || 502);
  const timeout = Number(config.timeout || 2000);

  const client = new ModbusRTU();
  await client.connectTCP(ip, { port });
  client.setTimeout(timeout);
  deviceClients.set(device.id, client);
  return client;
}

async function queuedRead(device, metric) {
  const config = device.communication_config || {};
  const unitId = Number(config.unit_id || 1);
  const delay = Math.max(Number(config.inter_request_delay_ms || 150), 0);

  const fc = metric.function_code || 3;
  const address = metric.address;
  const length = metric.length;

  const client = await getOrCreateClient(device);

  return enqueue(device.id, async () => {
    client.setID(unitId);
    if (delay) await sleep(delay);

    let out;
    switch (fc) {
      case 1:
        out = await client.readCoils(address, length);
        break;
      case 2:
        out = await client.readDiscreteInputs(address, length);
        break;
      case 3:
        out = await client.readHoldingRegisters(address, length);
        break;
      case 4:
        out = await client.readInputRegisters(address, length);
        break;
      default:
        throw new Error(`Unsupported function code ${fc}`);
    }

    if (delay) await sleep(delay);
    return out;
  });
}

async function fetchModbusRouter(device) {
  const config = device.communication_config || {};
  if (!config.ip) {
    console.warn('[ModbusRouter] Missing ip in communication_config');
    return;
  }

  if (!deviceIntervals.has(device.id)) deviceIntervals.set(device.id, new Map());
  const intervals = deviceIntervals.get(device.id);

  // Ensure connection early (and fail fast)
  try {
    await getOrCreateClient(device);
    await saveDataPoint(device, [{ key: "Connection", value: 1, timestamp: new Date() }]);
  } catch (e) {
    await saveDataPoint(device, [{ key: "Connection", value: 0, timestamp: new Date() }]);
    console.error(`[ModbusRouter] ${device.name} connect failed:`, e?.message || e);
    return;
  }

  for (const metric of device.metrics || []) {
    if (metric.type !== 'modbus_read') continue;

    const old = intervals.get(metric.key);
    if (old) clearInterval(old);

    const intervalSec = Math.max(Number(metric.pull_rate) || 10, 1);
    const pullMs = intervalSec * 1000;

    const datatype = metric.datatype || 'int16';
    const scale = Number(metric.scale || 1);

    const intervalId = setInterval(async () => {
      try {
        const data = await queuedRead(device, metric);

        let value;
        let scaled;
        const fc = metric.function_code || 3;
        const length = metric.length;

        if (fc === 1 || fc === 2) {
          value = data.data[0] ? 1 : 0;
          scaled = value;
        } else {
          const raw = Buffer.alloc(length * 2);
          data.data.forEach((val, i) => raw.writeUInt16BE(val, i * 2));
          value = bufferToValue(raw, datatype);
          if (value == null) return;
          scaled = (scale === 0 || scale === 1) ? value : value * scale;
        }

        await saveDataPoint(device, [{
          key: metric.key,
          value: parseFloat(scaled),
          timestamp: new Date()
        }]);

        try {
          const { relayGatewayPollToVisualEndpoints } = require('./gateway_endpoint_relay');
          await relayGatewayPollToVisualEndpoints(device, metric, parseFloat(scaled), new Date());
        } catch (e) {
          console.error(`[GW-EP relay] ${device.name}:`, e?.message || e);
        }
      } catch (err) {
        console.error(`[ModbusRouter] ${device.name} - ${metric.key} ERROR:`, err?.message || err);
      }
    }, pullMs);

    intervals.set(metric.key, intervalId);
  }
}

function cleanupModbusRouterDevice(deviceId) {
  if (deviceIntervals.has(deviceId)) {
    const intervals = deviceIntervals.get(deviceId);
    for (const id of intervals.values()) clearInterval(id);
    deviceIntervals.delete(deviceId);
  }
  requestQueues.delete(deviceId);
  if (deviceClients.has(deviceId)) {
    try { deviceClients.get(deviceId).close(); } catch {}
    deviceClients.delete(deviceId);
  }
}

module.exports = { fetchModbusRouter, cleanupModbusRouterDevice };

