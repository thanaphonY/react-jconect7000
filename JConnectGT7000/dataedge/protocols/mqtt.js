const mqtt = require('mqtt');
const jp = require('jsonpath');
const { saveDataPoint } = require('../core/dataSaver');

function registerDeviceToMQTT(device, mqttClients, isReload = false) {


  const config = device.communication_config;
  const broker_url = config.broker_url;
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
      console.log(`[MQTT] Connected to ${broker_url}`);
    });

    client.on('message', (topic, message) => {
      handleMessage(broker_url, topic, message, mqttClients);
    });

    mqttClients.set(broker_url, { client, devices: [] });
  }

  const clientEntry = mqttClients.get(broker_url);
  if (isReload) {
    console.log("MQTT Reload" );
    clientEntry.devices = clientEntry.devices.filter(d => d.id !== device.id);
  }

  clientEntry.devices.push(device);

  const topics = [...new Set(device.metrics.map(m => m.topic))];
  topics.forEach(topic => clientEntry.client.subscribe(topic));
  console.log(
    `[MQTT] registered device id=${device.id} name="${device.name}" broker=${broker_url}:${port} topics=[${topics.join(', ')}]`
  );
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

  for (const device of devices) {
    for (const metric of device.metrics) {
      const regex = new RegExp(metric.topic.replace('+', '[^/]+').replace('#', '.*'));
      if (regex.test(topic)) {
        try {
          const raw = jp.value(payload, '$.' + metric.json_path);
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

// Cleanup function for MQTT devices
function cleanupMQTTDevice(deviceId, mqttClients) {
  console.log(`[MQTT] Cleaning up device ${deviceId}...`);
  
  // Remove device from all MQTT clients
  for (const [brokerUrl, clientEntry] of mqttClients.entries()) {
    const deviceIndex = clientEntry.devices.findIndex(d => d.id === deviceId);
    if (deviceIndex !== -1) {
      const device = clientEntry.devices[deviceIndex];
      console.log(`[MQTT] Removing device ${device.name} from broker ${brokerUrl}`);
      
      // Remove device from devices array
      clientEntry.devices.splice(deviceIndex, 1);
      
      // If no devices left for this broker, close connection
      if (clientEntry.devices.length === 0) {
        console.log(`[MQTT] No devices left for broker ${brokerUrl}, closing connection`);
        clientEntry.client.end();
        mqttClients.delete(brokerUrl);
      }
    }
  }
  
  console.log(`[MQTT] Device ${deviceId} cleanup completed`);
}

module.exports = { registerDeviceToMQTT, cleanupMQTTDevice };