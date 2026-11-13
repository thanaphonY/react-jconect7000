const mqtt = require('mqtt');
const jp = require('jsonpath');
const { saveDataPoint } = require('../core/dataSaver');
const { json } = require('express');

function registerDeviceToLocalLoRa(device, mqttClients, isReload = false) {
  const config = device.communication_config;
  const broker_url = config.broker_url || "localhost";
  const port = Number(config.port || 1883);

  if (!mqttClients.has(broker_url)) {
    const options = {
      host: broker_url,
      port: port,
      protocol: 'mqtt',
      clientId: config.client_id || `client_${device.id}_${Date.now()}`
    };

    if (config.username && config.password) {
      options.username = config.username;
      options.password = config.password;
    }

    const client = mqtt.connect(options);

    client.on('connect', () => {
      console.log(`[MQTT LoRa] Connected to ${broker_url}`);
    });

    client.on('message', (topic, message) => {
      handleMessage(broker_url, topic, message, mqttClients);
    });

    mqttClients.set(broker_url, { client, devices: [] });
  }


  const clientEntry = mqttClients.get(broker_url);
  if (isReload) {
    console.log("MQTT Reload");
    clientEntry.devices = clientEntry.devices.filter(d => d.id !== device.id);
  }

  clientEntry.devices.push(device);

  const topics = `application/+/device/${config.dev_eui.toLowerCase()}/event/up`;
  clientEntry.client.subscribe(topics);
}

async function handleMessage(broker_url, topic, message, mqttClients) {
  const { devices } = mqttClients.get(broker_url);
  let payload;

  try {
    payload = JSON.parse(message.toString());
  } catch (e) {
    console.error('[MQTT] Invalid JSON payload:', e);
    return;
  }

  const pEui = payload.deviceInfo.devEui;
  for (const device of devices) {
    const communication_config = device.communication_config;
    
    if (payload.deviceInfo?.hasOwnProperty('devEui')) {
      
      const dEui = communication_config.dev_eui.toLowerCase();
      if (pEui == dEui) {
        console.log("deviceEUI", communication_config.dev_eui);
        for (const metric of device.metrics) {
          try {
            let path = metric.json_path;
            if (!path) {
              if (payload.object?.hasOwnProperty(metric.key)) {
                path = `object.${metric.key}`;
              } else if (payload.rxInfo?.[0]?.hasOwnProperty(metric.key)) {
                path = `rxInfo[0].${metric.key}`;
              } else if (payload.hasOwnProperty(metric.key)) {
                path = metric.key;
              }
            }

            const raw = jp.value(payload, '$.' + path);

            if (raw !== undefined) {
              const scale = Number(metric.scale || 1);
              const value = (scale === 0 || scale === 1) ? raw : raw * scale;

              await saveDataPoint(device, [{
                key: metric.key,
                value: parseFloat(value),
                timestamp: new Date()
              }]);

              console.log(`[MQTT] ${device.name} - ${metric.key}: ${value}`);
            }
          } catch (err) {
            console.error(`[MQTT] Error processing ${metric.key} from ${device.name}`, err);
          }

        }
      }
    }
  }
}

// Cleanup function for Local LoRa devices
function cleanupLocalLoRaDevice(deviceId, mqttClients) {
  console.log(`[LoRa] Cleaning up device ${deviceId}...`);
  
  // Remove device from all MQTT clients (same as MQTT cleanup)
  for (const [brokerUrl, clientEntry] of mqttClients.entries()) {
    const deviceIndex = clientEntry.devices.findIndex(d => d.id === deviceId);
    if (deviceIndex !== -1) {
      const device = clientEntry.devices[deviceIndex];
      console.log(`[LoRa] Removing device ${device.name} from broker ${brokerUrl}`);
      
      // Remove device from devices array
      clientEntry.devices.splice(deviceIndex, 1);
      
      // If no devices left for this broker, close connection
      if (clientEntry.devices.length === 0) {
        console.log(`[LoRa] No devices left for broker ${brokerUrl}, closing connection`);
        clientEntry.client.end();
        mqttClients.delete(brokerUrl);
      }
    }
  }
  
  console.log(`[LoRa] Device ${deviceId} cleanup completed`);
}

module.exports = { registerDeviceToLocalLoRa, cleanupLocalLoRaDevice };