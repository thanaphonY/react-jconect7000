const ModbusRTU = require('modbus-serial');
const db = require('../config/dbConfig');
const { saveDataPoint } = require('../core/dataSaver');

const modbusIntervals = new Map(); // deviceId => Map<metric.key, intervalId>
const connectionPool = new Map(); // ip:port => { client, lastUsed, deviceCount }
const requestQueue = new Map(); // ip:port => Promise queue

// Connection status monitoring (inspired by SNMP)
const modbusErrorCounters = new Map(); // deviceId => consecutive error count
const modbusConnectionStatus = new Map(); // deviceId => last known status (0 or 1)
const modbusLastSuccessTime = new Map(); // deviceId => last successful read timestamp
const modbusLastConnectionWrite = new Map(); // deviceId => last Connection metric write timestamp (ms)
const modbusLastConnectionValue = new Map(); // deviceId => last emitted Connection value

// Queue management for preventing race conditions
class ModbusQueue {
  constructor() {
    this.queue = [];
    this.processing = false;
  }

  async add(task) {
    return new Promise((resolve, reject) => {
      this.queue.push({ task, resolve, reject });
      this.process();
    });
  }

  async process() {
    if (this.processing || this.queue.length === 0) return;
    
    this.processing = true;
    
    while (this.queue.length > 0) {
      const { task, resolve, reject } = this.queue.shift();
      
      try {
        const result = await task();
        resolve(result);
      } catch (error) {
        reject(error);
      }
      
      // Small delay between requests to prevent overwhelming the device
      if (this.queue.length > 0) {
        await new Promise(resolve => setTimeout(resolve, 50));
      }
    }
    
    this.processing = false;
  }
}

function poolKey(ip, port) {
  return `${ip}:${Number(port) || 502}`;
}

function closePooledClient(client) {
  try {
    if (client && typeof client.close === 'function') client.close();
  } catch (_e) { /* already closed */ }
}

function getMetricByKey(device, key) {
  return (device.metrics || []).find((m) => {
    const metricKey = m.key ?? m.metricKey ?? m.metric_key ?? m.id;
    return metricKey != null && String(metricKey) === String(key);
  });
}

function getDisconnectGraceMs(device) {
  const config = device.communication_config || {};
  if (Number(config.disconnect_grace_ms) > 0) {
    return Number(config.disconnect_grace_ms);
  }

  // Fallback for devices without explicit config: be conservative to avoid false disconnect flapping.
  const defaultGraceMs = 90000;

  const metrics = Array.isArray(device.metrics) ? device.metrics : [];
  const pollRatesMs = metrics
    .filter((m) => m?.type === 'modbus_read' && m?.enable !== false && m?.enable !== 'false')
    .map((m) => Math.max(Number(m.pull_rate) || 10, 1) * 1000);

  const minPollRateMs = pollRatesMs.length > 0 ? Math.min(...pollRatesMs) : 10000;
  return Math.max(minPollRateMs * 3, defaultGraceMs);
}

function shouldEmitConnectionMetric(device, value, timestamp = new Date()) {
  const metric = getMetricByKey(device, 'Connection');
  const storePolicy = metric?.store_policy || 'always';
  const intervalMinutes = Number(metric?.interval_minutes);
  const deviceId = device.id;
  const currentValue = Number(value);
  const currentTime = timestamp.getTime();
  const lastValue = modbusLastConnectionValue.get(deviceId);
  const lastWrite = modbusLastConnectionWrite.get(deviceId) || 0;

  if (storePolicy === 'none') return false;
  if (storePolicy === 'on_change') return lastValue !== currentValue;
  if (storePolicy === 'interval' && Number.isFinite(intervalMinutes) && intervalMinutes > 0) {
    return !lastWrite || currentTime - lastWrite >= intervalMinutes * 60000;
  }
  return true;
}

function rememberConnectionMetricEmission(deviceId, value, timestamp) {
  modbusLastConnectionWrite.set(deviceId, timestamp.getTime());
  modbusLastConnectionValue.set(deviceId, Number(value));
}

async function hydrateConnectionState(deviceId) {
  if (modbusLastConnectionValue.has(deviceId)) return;

  try {
    const res = await db.query(
      `SELECT value, value_bool, "timestamp"
         FROM device_data
        WHERE device_id = $1 AND key = $2
        ORDER BY "timestamp" DESC
        LIMIT 1`,
      [deviceId, 'Connection']
    );

    const row = res.rows?.[0];
    if (!row) return;

    const numericValue =
      typeof row.value_bool === 'boolean'
        ? (row.value_bool ? 1 : 0)
        : Number(row.value);

    if (Number.isFinite(numericValue)) {
      modbusLastConnectionValue.set(deviceId, numericValue);
    }

    const ts = new Date(row.timestamp);
    if (!Number.isNaN(ts.getTime())) {
      modbusLastConnectionWrite.set(deviceId, ts.getTime());
    }
  } catch (err) {
    console.warn(`[Modbus] Failed to hydrate Connection state for device ${deviceId}:`, err.message);
  }
}

// Connection pool management
async function getOrCreateConnection(ip, port = 502, timeout = 2000) {
  const key = poolKey(ip, port);
  const existing = connectionPool.get(key);

  if (existing?.client?.isOpen) {
    existing.lastUsed = Date.now();
    existing.deviceCount++;
    return existing.client;
  }

  if (existing) {
    console.warn(`[Modbus] Replacing dead connection pool for ${key}`);
    closePooledClient(existing.client);
    connectionPool.delete(key);
    requestQueue.delete(key);
  }

  console.log(`[Modbus] Creating new connection pool for ${key}`);
  const client = new ModbusRTU();
  client._poolKey = key;
  client.setTimeout(timeout);

  try {
    await client.connectTCP(ip, { port: Number(port) || 502 });

    connectionPool.set(key, {
      client,
      lastUsed: Date.now(),
      deviceCount: 1
    });

    requestQueue.set(key, new ModbusQueue());

    return client;
  } catch (err) {
    closePooledClient(client);
    console.error(`[Modbus] Failed to create connection pool for ${key}:`, err.message);
    throw err;
  }
}

function releaseConnection(ip, port = 502) {
  const key = `${ip}:${port}`;
  
  if (connectionPool.has(key)) {
    const conn = connectionPool.get(key);
    conn.deviceCount--;
    
    if (conn.deviceCount <= 0) {
      console.log(`[Modbus] Closing connection pool for ${ip}:${port}`);
      conn.client.close();
      connectionPool.delete(key);
      requestQueue.delete(key);
    }
  }
}

// Cleanup unused connections
setInterval(() => {
  const now = Date.now();
  const TIMEOUT = 5 * 60 * 1000; // 5 minutes
  
  for (const [key, conn] of connectionPool.entries()) {
    if (conn.deviceCount <= 0 && (now - conn.lastUsed) > TIMEOUT) {
      console.log(`[Modbus] Cleaning up unused connection: ${key}`);
      conn.client.close();
      connectionPool.delete(key);
      requestQueue.delete(key);
    }
  }
}, 60000);

function bufferToValue(buffer, datatype) {
  const dt = String(datatype || 'int16').toLowerCase();
  switch (dt) {
    case 'bool':
    case 'boolean':
      return buffer.readUInt16BE(0) !== 0;
    case 'int':
    case 'int16': return buffer.readInt16BE(0);
    case 'uint':
    case 'uint16': return buffer.readUInt16BE(0);
    case 'int32': return buffer.readInt32BE(0);
    case 'uint32': return buffer.readUInt32BE(0);
    case 'float':
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
      console.warn(`[Modbus] Unsupported datatype: ${datatype}`);
      return null;
  }
}

// Queued Modbus read function with connection status monitoring
async function queuedModbusRead(client, unitId, fc, address, length, deviceId) {
  const key = client._poolKey;
  const queue = requestQueue.get(key);

  if (!queue) {
    throw new Error(`No queue found for connection ${key || '(missing _poolKey)'}`);
  }

  const functionCode = Number(fc);
  const start = Number(address);
  const qty = Number(length) || 1;

  return queue.add(async () => {
    client.setID(Number(unitId) || 1);

    switch (functionCode) {
      case 1:
        return await client.readCoils(start, qty);
      case 2:
        return await client.readDiscreteInputs(start, qty);
      case 3:
        return await client.readHoldingRegisters(start, qty);
      case 4:
        return await client.readInputRegisters(start, qty);
      default:
        throw new Error(`Unsupported function code ${fc}`);
    }
  });
}

/** Decode raw modbus-serial response (shared with gateway_endpoint). */
function decodeModbusReadResult(data, fc, length, datatype, scale) {
  let value;
  let scaled;
  if (fc === 1 || fc === 2) {
    value = data.data[0] ? 1 : 0;
    scaled = value;
  } else {
    const raw = Buffer.alloc(length * 2);
    data.data.forEach((val, i) => raw.writeUInt16BE(val, i * 2));
    value = bufferToValue(raw, datatype);
    if (value === null) return null;
    scaled = scale === 0 || scale === 1 ? value : value * scale;
  }
  const numScaled = typeof scaled === 'number' && !Number.isInteger(scaled) ? Number(Number(scaled).toFixed(4)) : scaled;
  return { value, scaled: numScaled };
}

/**
 * One-shot read using gateway device's Modbus TCP config (same connection pool as fetchModbusTCP).
 * @param {object} gatewayRow - { id, communication_config }
 * @param {object} metricLike - { function_code, address, length, datatype, scale }
 */
async function readModbusUsingGateway(gatewayRow, metricLike) {
  const config = gatewayRow.communication_config || {};
  const ip = config.ip || config.host;
  if (!ip) throw new Error('gateway communication_config.ip (or host) required');
  const port = Number(config.port || 502);
  const timeout = Number(config.timeout || 2000);
  const unitId = Number(config.unit_id || 1);
  const client = await getOrCreateConnection(ip, port, timeout);
  const fc = Number(metricLike.function_code || 3);
  const address = Number(metricLike.address);
  const length = Number(metricLike.length || 1);
  const datatype = metricLike.datatype || 'int16';
  const scale = Number(metricLike.scale || 1);
  const data = await queuedModbusRead(client, unitId, fc, address, length, gatewayRow.id);
  return decodeModbusReadResult(data, fc, length, datatype, scale);
}

// Connection status monitoring functions
async function handleModbusError(device, metric, error) {
  const deviceId = device.id;
  const timestamp = new Date();
  const fc = Number(metric.function_code || 3);
  const address = Number(metric.address);
  const length = Number(metric.length || 1);
  const datatype = metric.datatype || 'int16';
  
  console.error(
    `[Modbus] ${device.name} - ${metric.key} ERROR ` +
    `(fc=${fc}, address=${address}, length=${length}, datatype=${datatype}):`,
    error.message
  );

  // Increment error counter
  const prev = modbusErrorCounters.get(deviceId) || 0;
  const current = prev + 1;
  modbusErrorCounters.set(deviceId, current);
  console.warn(
    `[Modbus] ${device.name} - ${metric.key} increased error counter ${prev} -> ${current} ` +
    `(fc=${fc}, address=${address}, length=${length}, datatype=${datatype})`
  );

  // Mark connection as failed after 5 consecutive errors
  if (current === 30 && modbusConnectionStatus.get(deviceId) !== 0) {
    const lastSuccess = modbusLastSuccessTime.get(deviceId);
    const graceMs = getDisconnectGraceMs(device);
    const msSinceLastSuccess = lastSuccess ? timestamp.getTime() - new Date(lastSuccess).getTime() : Infinity;

    if (msSinceLastSuccess < graceMs) {
      console.warn(
        `[Modbus] ${device.name} - suppressing FAIL from metric ${metric.key} because last success was ${msSinceLastSuccess}ms ago (< ${graceMs}ms grace)`
      );
      return;
    }

    console.warn(
      `[Modbus] ${device.name} - Connection will be marked as FAIL by metric ${metric.key} ` +
      `(fc=${fc}, address=${address}, length=${length}, datatype=${datatype}) ` +
      `after ${current} consecutive errors`
    );
    if (shouldEmitConnectionMetric(device, 0, timestamp)) {
      await saveDataPoint(device, [{
        key: "Connection",
        value: 0,
        timestamp
      }]);
      rememberConnectionMetricEmission(deviceId, 0, timestamp);
    }
    modbusConnectionStatus.set(deviceId, 0);
    console.warn(`[Modbus] ${device.name} - Connection marked as FAIL (${current} consecutive errors)`);
  }
}

async function handleModbusSuccess(device, metric, value, scaled) {
  const deviceId = device.id;
  const timestamp = new Date();
  const datatype = String(metric.datatype || '').toLowerCase();
  const isBoolMetric = datatype === 'bool' || datatype === 'boolean' || typeof scaled === 'boolean';
  const prevErrorCount = modbusErrorCounters.get(deviceId) || 0;
  
  const datapoints = [{
    key: metric.key,
    value: isBoolMetric
      ? Boolean(scaled)
      : ((scaled === 0 || scaled === 1) ? parseFloat(scaled) : parseFloat(scaled).toFixed(2)),
    timestamp
  }];

  // Reset error counter and check if connection recovered
  const prevStatus = modbusConnectionStatus.get(deviceId);
  if (prevErrorCount > 0) {
    console.info(
      `[Modbus] ${device.name} - ${metric.key} read success, resetting error counter ${prevErrorCount} -> 0`
    );
  }
  modbusErrorCounters.set(deviceId, 0);
  modbusLastSuccessTime.set(deviceId, timestamp);

  // If connection was previously failed, mark as recovered
  if (prevStatus !== 1) {
    if (shouldEmitConnectionMetric(device, 1, timestamp)) {
      datapoints.push({
        key: "Connection",
        value: 1,
        timestamp
      });
      rememberConnectionMetricEmission(deviceId, 1, timestamp);
    }
    modbusConnectionStatus.set(deviceId, 1);
    console.info(`[Modbus] ${device.name} - Connection marked as OK (recovered)`);
  } else {
    if (shouldEmitConnectionMetric(device, 1, timestamp)) {
      datapoints.push({
        key: "Connection",
        value: 1,
        timestamp
      });
      rememberConnectionMetricEmission(deviceId, 1, timestamp);
    }
    modbusConnectionStatus.set(deviceId, 1);
  }

  await saveDataPoint(device, datapoints);
  try {
    const { relayGatewayPollToVisualEndpoints } = require('./gateway_endpoint_relay');
    await relayGatewayPollToVisualEndpoints(device, metric, scaled, timestamp);
  } catch (err) {
    console.error(`[GW-EP relay] ${device.name}:`, err.message);
  }
  //console.log(`[Modbus] ${device.name} - ${metric.key}: ${value}`);
}

async function fetchModbusTCP(device) {
  const config = device.communication_config || {};
  const ip = config.ip || config.host;
  const port = Number(config.port || 502);
  const timeout = Number(config.timeout || 2000);
  const unitId = Number(config.unit_id || 1);

  await hydrateConnectionState(device.id);

  if (!ip) {
    console.error(`[Modbus] ${device.name} missing communication_config.ip (or host)`);
    return;
  }

  console.log(`[Modbus] ${device.name} connecting to ${ip}:${port} (Unit ID: ${unitId})...`);

  let client;
  try {
    client = await getOrCreateConnection(ip, port, timeout);
    
    // Initial connection success
    {
      const timestamp = new Date();
      if (shouldEmitConnectionMetric(device, 1, timestamp)) {
        await saveDataPoint(device, [{
          key: "Connection",
          value: 1,
          timestamp
        }]);
        rememberConnectionMetricEmission(device.id, 1, timestamp);
      }
    }
    modbusConnectionStatus.set(device.id, 1);
    modbusErrorCounters.set(device.id, 0);
    modbusLastSuccessTime.set(device.id, new Date());
    console.log(`[Modbus] ${device.name} - Connection marked as OK (initial connection)`);
    
  } catch (err) {
    console.error(`[Modbus] ${device.name} connection failed:`, err.message);
    {
      const timestamp = new Date();
      if (shouldEmitConnectionMetric(device, 0, timestamp)) {
        await saveDataPoint(device, [{
          key: "Connection",
          value: 0,
          timestamp
        }]);
        rememberConnectionMetricEmission(device.id, 0, timestamp);
      }
    }
    modbusConnectionStatus.set(device.id, 0);
    modbusErrorCounters.set(device.id, 5); // Start with 5 errors
    return;
  }

  if (!modbusIntervals.has(device.id)) {
    modbusIntervals.set(device.id, new Map());
  }

  const deviceIntervals = modbusIntervals.get(device.id);

  for (const metric of device.metrics || []) {
    if (metric.type !== 'modbus_read') continue;
    if (metric.enable === false || metric.enable === 'false') continue;

    const metricKey = metric.key ?? metric.metricKey ?? metric.metric_key ?? '';
    if (String(metricKey) === 'Connection') {
      console.log(`[Modbus] ${device.name} - skipping polled metric "Connection"; reserved for computed link status`);
      continue;
    }

    const address = Number(metric.address);
    if (!Number.isFinite(address)) {
      // e.g. synthetic "Connection" row — TCP status is written separately
      continue;
    }

    //console.log(`[Modbus] ${device.name} - ${metric.key} (${metric.function_code})`);

    const oldInterval = deviceIntervals.get(metricKey);
    if (oldInterval) clearInterval(oldInterval);

    const interval = Math.max(Number(metric.pull_rate) || 10, 1);
    const pull_rate = interval * 1000;
    const fc = Number(metric.function_code || 3);
    const length = Number(metric.length || 1);
    const datatype = metric.datatype || 'int16';
    const scale = Number(metric.scale || 1);

    const intervalId = setInterval(async () => {
      try {
        // Use queued read to prevent race conditions
        const data = await queuedModbusRead(client, unitId, fc, address, length, device.id);

        const decoded = decodeModbusReadResult(data, fc, length, datatype, scale);
        if (!decoded) return;
        const { value, scaled } = decoded;

        //console.log(`[MODBUS_TCP] Device name:${device.name}`, `key:${metric.key}`, `scale:${scale}`, "scaled:", scaled, "raw:", value);

        await handleModbusSuccess(device, metric, value, scaled);
        
      } catch (err) {
        await handleModbusError(device, metric, err);
      }
    }, pull_rate);

    deviceIntervals.set(metricKey, intervalId);
  }
}

// Cleanup function for when device is removed
function cleanupDevice(deviceId) {
  if (modbusIntervals.has(deviceId)) {
    const deviceIntervals = modbusIntervals.get(deviceId);
    for (const intervalId of deviceIntervals.values()) {
      clearInterval(intervalId);
    }
    modbusIntervals.delete(deviceId);
  }
  
  // Cleanup connection status tracking
  modbusErrorCounters.delete(deviceId);
  modbusConnectionStatus.delete(deviceId);
  modbusLastSuccessTime.delete(deviceId);
  modbusLastConnectionWrite.delete(deviceId);
  modbusLastConnectionValue.delete(deviceId);
}

// Get connection status for monitoring
function getConnectionStatus(deviceId) {
  return {
    status: modbusConnectionStatus.get(deviceId) || 0,
    errorCount: modbusErrorCounters.get(deviceId) || 0,
    lastSuccess: modbusLastSuccessTime.get(deviceId) || null
  };
}

module.exports = {
  fetchModbusTCP,
  cleanupDevice,
  getConnectionStatus,
  readModbusUsingGateway,
  decodeModbusReadResult,
  bufferToValue,
};