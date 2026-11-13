const ModbusRTU = require('modbus-serial');
const { saveDataPoint } = require('../core/dataSaver');

const clientsByPort = new Map(); // serial_port => { client, devices, intervalId }

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
      console.warn(`[RTU] Unsupported datatype: ${datatype}`);
      return null;
  }
}

function groupDevicesByPort(devices) {
  const portGroups = new Map();
  for (const device of devices) {
    if (device.metrics.some(m => m.type === 'modbus_read') && device.communication_config.type === 'rtu') {
      const port = device.communication_config.serial_port;
      if (!portGroups.has(port)) portGroups.set(port, []);
      portGroups.get(port).push(device);
    }
  }
  return portGroups;
}

async function fetchModbusRTU(devices) {
  const groups = groupDevicesByPort(devices);

  for (const [port, deviceList] of groups.entries()) {
    const client = new ModbusRTU();
    const sampleDevice = deviceList[0].communication_config;
    try {
      await client.connectRTUBuffered(port, {
        baudRate: Number(sampleDevice.baud_rate || 9600),
        dataBits: Number(sampleDevice.data_bits || 8),
        stopBits: Number(sampleDevice.stop_bits || 1),
        parity: sampleDevice.parity || 'none',
      });
      client.setTimeout(Number(sampleDevice.timeout || 2000));
    } catch (err) {
      console.error(`[RTU] Cannot open ${port}:`, err.message);
      continue;
    }

    const intervalId = setInterval(async () => {
      for (const device of deviceList) {
        try {
          client.setID(Number(device.communication_config.unit_id || 1));
          for (const metric of device.metrics) {
            if (metric.type !== 'modbus_read') continue;

            const fc = metric.function_code || 3;
            const address = metric.address;
            const length = metric.length;
            const datatype = metric.datatype || 'int16';
            const scale = Number(metric.scale || 1);

            let data;
            if (fc === 3) {
              data = await client.readHoldingRegisters(address, length);
            } else if (fc === 4) {
              data = await client.readInputRegisters(address, length);
            } else {
              console.warn(`[RTU] Unsupported function code ${fc} for ${metric.key}`);
              continue;
            }

            const raw = Buffer.alloc(length * 2);
            data.data.forEach((val, i) => raw.writeUInt16BE(val, i * 2));

            const value = bufferToValue(raw, datatype);
            if (value === null) continue;

            const scaled = (scale === 0 || scale === 1) ? value : value * scale;
            console.log("[MODBUS_RTU] RAW READ",value);
            await saveDataPoint(device, [{
              key: metric.key,
              value: (scale === 0 || scale === 1) ? parseFloat(scaled) : parseFloat(scaled).toFixed(2),
              timestamp: new Date()
            }]);

            try {
              const { relayGatewayPollToVisualEndpoints } = require('./gateway_endpoint_relay');
              const num = (scale === 0 || scale === 1) ? parseFloat(scaled) : parseFloat(parseFloat(scaled).toFixed(2));
              await relayGatewayPollToVisualEndpoints(device, metric, num, new Date());
            } catch (e) {
              console.error(`[GW-EP relay] ${device.name}:`, e?.message || e);
            }

            console.log(`[RTU] ${device.name} - ${metric.key}: ${scaled}`);
          }
        } catch (err) {
          console.error(`[RTU] ${device.name} ERROR:`, err.message);
        }
      }
    }, 3000); // polling loop across devices every 3s per port

    clientsByPort.set(port, { client, devices: deviceList, intervalId });
  }
}

// Cleanup function for Modbus RTU devices
function cleanupModbusRTUDevice(deviceId) {
  console.log(`[RTU] Cleaning up device ${deviceId}...`);
  
  // Find and remove device from all port groups
  for (const [port, clientEntry] of clientsByPort.entries()) {
    const deviceIndex = clientEntry.devices.findIndex(d => d.id === deviceId);
    if (deviceIndex !== -1) {
      const device = clientEntry.devices[deviceIndex];
      console.log(`[RTU] Removing device ${device.name} from port ${port}`);
      
      // Remove device from devices array
      clientEntry.devices.splice(deviceIndex, 1);
      
      // If no devices left for this port, close connection and clear interval
      if (clientEntry.devices.length === 0) {
        console.log(`[RTU] No devices left for port ${port}, closing connection`);
        clearInterval(clientEntry.intervalId);
        clientEntry.client.close();
        clientsByPort.delete(port);
      }
    }
  }
  
  console.log(`[RTU] Device ${deviceId} cleanup completed`);
}

module.exports = { fetchModbusRTU, cleanupModbusRTUDevice };