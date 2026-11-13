// Live metric evaluation → alarm_engine (shared logic stays in gen3/alarm-engine)
const path = require('path');
const pool = require('../config/dbConfig');
const alarmEngine = require(path.join(__dirname, '..', '..', 'alarm-engine', 'alarm_engine'));

const db = {
  query: (text, params) => pool.query(text, params),
};

async function evaluateLive(deviceId, metricKey, value) {
  const { rows: devices } = await db.query('SELECT * FROM devices WHERE id = $1', [deviceId]);
  if (!devices.length) return console.error('Device not found');
  const device = devices[0];

  const metrics = Array.isArray(device.metrics) ? device.metrics : JSON.parse(device.metrics || '[]');

  const metric = metrics.find((m) => {
    const k = m.key ?? m.metricKey ?? m.metric_key ?? m.id;
    return k != null && String(k) === String(metricKey);
  });
  if (!metric) return;// console.error('Metric not found', metricKey, deviceId);

  await alarmEngine.evaluate(device, metric, value);
  //console.log('Evaluation complete\n---------');
}

module.exports = { evaluateLive };
