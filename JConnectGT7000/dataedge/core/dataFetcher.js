const db = require('../config/dbConfig');
const { registerDeviceToMQTT, cleanupMQTTDevice } = require('../protocols/mqtt');
const { fetchSNMP, cleanupSNMPDevice } = require('../protocols/snmp');
const { fetchModbusTCP, cleanupDevice: cleanupModbusTCP } = require('../protocols/modbus_tcp');
const { fetchModbusRTU, cleanupModbusRTUDevice } = require('../protocols/modbus_rtu');
const { fetchREST, cleanupRESTDevice } = require('../protocols/restful');
const { registerDeviceToLocalLoRa, cleanupLocalLoRaDevice } = require('../protocols/local_lora');
const { fetchModbusRouter, cleanupModbusRouterDevice } = require('../protocols/modbus_router');
const { fetchBACnetIP } = require('../protocols/bacnet_ip');
const { fetchBACnetMSTP } = require('../protocols/bacnet_mstp');
const { fetchGatewayEndpoint, cleanupGatewayEndpoint } = require('../protocols/gateway_endpoint');
const { fetchVisual, cleanupVisualDevice } = require('../protocols/visual');
const { fetchCalc, cleanupCalcDevice } = require('../protocols/calc');
const { fetchRtsp, cleanupRtspDevice } = require('../protocols/rtsp');
const {
  isGatewayEndpointDevice,
  normalizeDeviceRow,
} = require('./deviceRouting');
const { rebuildSnapshotIndex } = require('./snapshotTriggers');


const mqttClients = new Map();
let allDevices = [];

/** Short hint for logs (host, IP, broker, etc.) */
function deviceEndpointHint(device) {
  const c = device.communication_config;
  if (!c || typeof c !== 'object') return '';
  const parts = [];
  if (c.host) parts.push(`host=${c.host}`);
  if (c.ip) parts.push(`tcp=${c.ip}:${c.port ?? 502}`);
  if (c.broker) parts.push(`broker=${c.broker}`);
  if (c.url && device.protocol === 'restful') parts.push(`url=${c.url}`);
  if (c.rtsp_url && device.protocol === 'rtsp') parts.push(`rtsp=${c.rtsp_url}`);
  if (isGatewayEndpointDevice(device) && c.source?.gateway_device_id != null) {
    parts.push(`gateway_id=${c.source.gateway_device_id}`);
  }
  return parts.length ? ` ${parts.join(' ')}` : '';
}

function logStartDeviceHandler(device, context = 'fetchAllDevices') {
  const proto = device.protocol || 'unknown';
  console.log(
    `[Collector] ${context} → start id=${device.id} name="${device.name}" protocol=${proto}${deviceEndpointHint(device)}`
  );
}

async function fetchAllDevices() {
  const result = await db.query(
    `SELECT d.id, d.name, d.communication_config, d.metrics, d.config,
            t.protocol AS template_protocol
     FROM devices d
     LEFT JOIN device_templates t ON t.id = d.device_template_id
     WHERE d.is_active = true AND d.is_deleted = false`
  );

  const devices = result.rows.map(normalizeDeviceRow);

  allDevices = devices;

  const modbusRTUDevices = [];
  console.log(
    `[Collector] Loaded ${devices.length} active device(s):`,
    devices.map((d) => `${d.name}[${d.protocol || '?'}]#${d.id}`).join(', ') || '(none)'
  );

  for (const device of devices) {
    logStartDeviceHandler(device, 'fetchAllDevices');
    if (device.protocol === 'mqtt') {
      registerDeviceToMQTT(device, mqttClients);
    } else if (device.protocol === 'snmp') {
      fetchSNMP(device);
    } else if (device.protocol === 'modbus_tcp') {
      fetchModbusTCP(device);
    } else if (device.protocol === 'modbus_router') {
      fetchModbusRouter(device);
    } else if (device.protocol === 'modbus_rtu' || device.communication_config?.type === 'rtu') {
      modbusRTUDevices.push(device);
    } else if (device.protocol === 'bacnet_ip') {
      fetchBACnetIP(device);
    } else if (device.protocol === 'bacnet_mstp' || device.protocol === 'bacnet_router') {
      fetchBACnetMSTP(device);
    } else if (device.protocol === 'restful') {
      fetchREST(device);
    } else if (device.protocol === 'lorawan') {
      registerDeviceToLocalLoRa(device, mqttClients);
    } else if (device.protocol === 'gateway_endpoint') {
      fetchGatewayEndpoint(device);
    } else if (device.protocol === 'visual') {
      fetchVisual(device);
    } else if (device.protocol === 'rtsp') {
      fetchRtsp(device);
    } else if (device.protocol === 'calc') {
      // calc metrics are registered below for every device
    } else {
      console.warn(
        `[Collector] id=${device.id} name="${device.name}" — no handler for protocol="${device.protocol}" (skipped)`
      );
    }

    // Derived (calc) metrics may live on any device regardless of its protocol.
    fetchCalc(device);
  }

  if (modbusRTUDevices.length > 0) {
    console.log(
      `[Collector] fetchAllDevices → Modbus RTU batch (${modbusRTUDevices.length}):`,
      modbusRTUDevices.map((d) => `"${d.name}"#${d.id}`).join(', ')
    );
    fetchModbusRTU(modbusRTUDevices);
  }

  rebuildSnapshotIndex(allDevices);
}

async function reloadDeviceById(deviceId) {
  // Query device without is_active filter to get current status
  const result = await db.query(
    `SELECT d.id, d.name, d.communication_config, d.metrics, d.config, d.is_active, d.is_deleted,
            t.protocol AS template_protocol
     FROM devices d
     LEFT JOIN device_templates t ON t.id = d.device_template_id
     WHERE d.id = $1`,
    [deviceId]
  );
  if (result.rowCount === 0) {
    console.log(`[Collector] Device ${deviceId} not found, cleaning up...`);
    await cleanupDeviceById(deviceId);
    return;
  }

  const device = result.rows[0];
  
  // Check if device is inactive or deleted
  if (!device.is_active || device.is_deleted) {
    console.log(`[Collector] Device ${device.name} (ID: ${deviceId}) is inactive or deleted, cleaning up...`);
    await cleanupDeviceById(deviceId);
    return;
  }

  Object.assign(device, normalizeDeviceRow(device));

  // Remove device from allDevices array if exists
  const idx = allDevices.findIndex(d => d.id === device.id);
  if (idx >= 0) {
    console.log(`[Collector] Removing existing device ${device.name} from allDevices`);
    allDevices.splice(idx, 1);
  }

  // Add device to allDevices array
  allDevices.push(device);
  console.log(`[Collector] Added device ${device.name} to allDevices`);

  // Cleanup existing connections before reloading
  if (device.protocol === 'modbus_tcp') {
    cleanupModbusTCP(deviceId);
  } else if (device.protocol === 'modbus_router') {
    cleanupModbusRouterDevice(deviceId);
  } else if (device.protocol === 'snmp') {
    cleanupSNMPDevice(deviceId);
  } else if (device.protocol === 'gateway_endpoint') {
    cleanupGatewayEndpoint(deviceId);
  } else if (device.protocol === 'visual') {
    cleanupVisualDevice(deviceId);
  } else if (device.protocol === 'restful') {
    cleanupRESTDevice(deviceId);
  } else if (device.protocol === 'rtsp') {
    cleanupRtspDevice(deviceId);
  }
  // Always clear calc schedules before re-registering (calc can live on any device).
  cleanupCalcDevice(deviceId);

  // Start new protocol handler
  logStartDeviceHandler(device, 'reloadDeviceById');
  if (device.protocol === 'mqtt') {
    registerDeviceToMQTT(device, mqttClients, true);
  } else if (device.protocol === 'snmp') {
    fetchSNMP(device);
  } else if (device.protocol === 'modbus_tcp') {
    fetchModbusTCP(device);
  } else if (device.protocol === 'modbus_router') {
    fetchModbusRouter(device);
  } else if (device.protocol === 'modbus_rtu' || device.communication_config?.type === 'rtu') {
    fetchModbusRTU([device]);
  } else if (device.protocol === 'bacnet_ip') {
    fetchBACnetIP(device);
  } else if (device.protocol === 'bacnet_mstp' || device.protocol === 'bacnet_router') {
    fetchBACnetMSTP(device);
  } else if (device.protocol === 'restful') {
    fetchREST(device);
  } else if(device.protocol === 'lorawan'){
     registerDeviceToLocalLoRa(device, mqttClients);
  } else if (device.protocol === 'gateway_endpoint') {
    fetchGatewayEndpoint(device);
  } else if (device.protocol === 'visual') {
    fetchVisual(device);
  } else if (device.protocol === 'rtsp') {
    fetchRtsp(device);
  } else if (device.protocol === 'calc') {
    // calc metrics are registered below
  } else {
    console.warn(
      `[Collector] reload id=${device.id} name="${device.name}" — no handler for protocol="${device.protocol}"`
    );
  }

  // (Re)register derived (calc) metrics for this device.
  fetchCalc(device);

  rebuildSnapshotIndex(allDevices);
  console.log(`[Collector] Successfully reloaded device ${device.name} (ID: ${deviceId})`);
}

// Cleanup function for inactive/deleted devices
async function cleanupDeviceById(deviceId) {
  console.log(`[Collector] Cleaning up device ${deviceId}...`);
  
  // Remove from allDevices array
  const idx = allDevices.findIndex(d => d.id === deviceId);
  if (idx >= 0) {
    const device = allDevices[idx];
    console.log(`[Collector] Removing device ${device.name} from allDevices`);
    allDevices.splice(idx, 1);
    
    // Protocol-specific cleanup
    if (device.protocol === 'modbus_tcp') {
      cleanupModbusTCP(deviceId);
    } else if (device.protocol === 'modbus_router') {
      cleanupModbusRouterDevice(deviceId);
    } else if (device.protocol === 'snmp') {
      cleanupSNMPDevice(deviceId);
    } else if (device.protocol === 'mqtt') {
      cleanupMQTTDevice(deviceId, mqttClients);
    } else if (device.protocol === 'modbus_rtu' || device.communication_config?.type === 'rtu') {
      cleanupModbusRTUDevice(deviceId);
    } else if (device.protocol === 'bacnet_ip' || device.protocol === 'bacnet_mstp' || device.protocol === 'bacnet_router') {
      // no interval cleanup exported in v1 bacnet modules (handled by process lifecycle)
    } else if (device.protocol === 'restful') {
      cleanupRESTDevice(deviceId);
    } else if (device.protocol === 'lorawan') {
      cleanupLocalLoRaDevice(deviceId, mqttClients);
    } else if (device.protocol === 'gateway_endpoint') {
      cleanupGatewayEndpoint(deviceId);
    } else if (device.protocol === 'visual') {
      cleanupVisualDevice(deviceId);
    } else if (device.protocol === 'rtsp') {
      cleanupRtspDevice(deviceId);
    }
    cleanupCalcDevice(deviceId);
  }

  rebuildSnapshotIndex(allDevices);
  console.log(`[Collector] Device ${deviceId} cleanup completed`);
}

// Get device status for monitoring
function getDeviceStatus(deviceId) {
  const device = allDevices.find(d => d.id === deviceId);
  return {
    exists: !!device,
    active: !!device,
    protocol: device?.protocol || null,
    name: device?.name || null
  };
}

function getAllDevices() {
  return allDevices;
}

/** Look up a currently-loaded device by id (used by webhook route + WS site check). */
function getDeviceById(deviceId) {
  const id = Number(deviceId);
  return allDevices.find((d) => Number(d.id) === id) || null;
}

/**
 * Pick up new devices (INSERT) or drop inactive ones when LISTEN/NOTIFY is unavailable.
 * Does not replace full fetchAllDevices — avoids duplicate pollers on existing devices.
 */
async function syncDevicesFromDb() {
  const result = await db.query(
    'SELECT id FROM devices WHERE is_active = true AND is_deleted = false'
  );
  const dbIds = new Set(result.rows.map((r) => r.id));
  const current = getAllDevices();
  const currentIds = new Set(current.map((d) => d.id));

  for (const id of dbIds) {
    if (!currentIds.has(id)) {
      console.log(`[Collector] DB sync: new active device id=${id} → reload`);
      await reloadDeviceById(id);
    }
  }
  for (const d of current) {
    if (!dbIds.has(d.id)) {
      console.log(`[Collector] DB sync: device id=${d.id} inactive/removed in DB → cleanup`);
      await cleanupDeviceById(d.id);
    }
  }
}

module.exports = {
  fetchAllDevices,
  reloadDeviceById,
  getAllDevices,
  getDeviceById,
  cleanupDeviceById,
  getDeviceStatus,
  syncDevicesFromDb,
};
