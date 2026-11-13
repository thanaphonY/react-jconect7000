const express = require('express');
const router = express.Router();
const { getDeviceById } = require('../core/dataFetcher');
const { get } = require('lodash');
const { saveDataPoint } = require('../core/dataSaver');

// Optional shared-secret guard. When DATAEDGE_WEBHOOK_TOKEN is set, push requests
// must carry it via `x-webhook-token` header or `?token=`. Unset = open (back-compat).
const WEBHOOK_TOKEN = String(process.env.DATAEDGE_WEBHOOK_TOKEN || '');

router.post('/:deviceId', async (req, res) => {
  if (WEBHOOK_TOKEN) {
    const provided = req.headers['x-webhook-token'] || req.query.token;
    if (provided !== WEBHOOK_TOKEN) {
      return res.status(401).json({ error: 'unauthorized' });
    }
  }

  const deviceId = parseInt(req.params.deviceId);
  const device = getDeviceById(deviceId);

  if (!device) {
    return res.status(404).json({ error: 'Device not found' });
  }

  const metrics = device.metrics.filter(m => m.type === 'rest_webhook');

  const dataPoints = [];
  for (const metric of metrics) {
    const rawValue = get(req.body, metric.json_path);
    if (rawValue === undefined) continue;

    let value = rawValue;
    if (metric.datatype === 'float') value = parseFloat(rawValue);
    else if (metric.datatype === 'int') value = parseInt(rawValue);
    if (isNaN(value)) continue;

    const scale = Number(metric.scale || 1);
    const scaled = (scale === 0 || scale === 1) ? value : value * scale;

    dataPoints.push({
      key: metric.key,
      value: parseFloat(scaled),
      timestamp: new Date()
    });
  }

  if (dataPoints.length > 0) {
    await saveDataPoint(device, dataPoints);
    console.log(`[Webhook] ${device.name} received:`, dataPoints);
    return res.status(200).json({ success: true, received: dataPoints.length });
  } else {
    return res.status(400).json({ error: 'No valid metrics in payload' });
  }
});

module.exports = router;