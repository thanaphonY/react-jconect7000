const express = require('express');
const { getDeviceById } = require('../core/dataFetcher');
const {
  registerViewer,
  heartbeatViewer,
  unregisterViewer,
  getStreamInfo,
  takeSnapshot,
  probe,
} = require('../protocols/rtsp');

const router = express.Router();

function requireRtspDevice(req, res) {
  const deviceId = Number(req.params.deviceId);
  if (!Number.isFinite(deviceId)) {
    res.status(400).json({ ok: false, error: 'invalid_device_id' });
    return null;
  }
  const device = getDeviceById(deviceId);
  if (!device) {
    res.status(404).json({ ok: false, error: 'device_not_loaded' });
    return null;
  }
  if (String(device.protocol || '').toLowerCase() !== 'rtsp') {
    res.status(400).json({ ok: false, error: 'not_rtsp_device', protocol: device.protocol });
    return null;
  }
  return device;
}

/** POST /stream/:deviceId/watch  body: { viewerId? } */
router.post('/:deviceId/watch', async (req, res) => {
  const device = requireRtspDevice(req, res);
  if (!device) return;
  try {
    const result = await registerViewer(device, req.body?.viewerId);
    res.json(result);
  } catch (e) {
    console.error('[RTSP] watch error:', e.message);
    res.status(500).json({ ok: false, error: e.message });
  }
});

/** POST /stream/:deviceId/heartbeat  body: { viewerId } */
router.post('/:deviceId/heartbeat', (req, res) => {
  const device = requireRtspDevice(req, res);
  if (!device) return;
  const result = heartbeatViewer(device.id, req.body?.viewerId);
  res.status(result.ok ? 200 : 400).json(result);
});

/** POST /stream/:deviceId/unwatch  body: { viewerId } */
router.post('/:deviceId/unwatch', (req, res) => {
  const device = requireRtspDevice(req, res);
  if (!device) return;
  const result = unregisterViewer(device.id, req.body?.viewerId);
  res.json(result);
});

/** GET /stream/:deviceId/status */
router.get('/:deviceId/status', (req, res) => {
  const device = requireRtspDevice(req, res);
  if (!device) return;
  res.json({ ok: true, ...getStreamInfo(device.id) });
});

/** POST /stream/:deviceId/snapshot */
router.post('/:deviceId/snapshot', async (req, res) => {
  const device = requireRtspDevice(req, res);
  if (!device) return;
  try {
    const result = await takeSnapshot(device);
    res.status(result.ok ? 200 : 500).json(result);
  } catch (e) {
    res.status(500).json({ ok: false, error: e.message });
  }
});

/** POST /stream/:deviceId/probe */
router.post('/:deviceId/probe', async (req, res) => {
  const device = requireRtspDevice(req, res);
  if (!device) return;
  try {
    const result = await probe(device);
    res.json({ ok: true, ...result });
  } catch (e) {
    res.status(500).json({ ok: false, error: e.message });
  }
});

module.exports = router;
