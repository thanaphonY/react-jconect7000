// index.js
require('dotenv').config();

const express = require('express');
const WebSocket = require('ws');

const {
  getBufferChunk,
  hasBuffer,
  clearBuffer,
  bufferLength
} = require('../shared/ws-bus');

const {
  WS_AUTH_DISABLED,
  tokenFromRequest,
  verifyToken,
  canAccessSite,
} = require('./auth');

// Guard against a single client subscribing to an unbounded number of metrics.
const MAX_SUBS_PER_CLIENT = Number(process.env.DATAEDGE_MAX_SUBS_PER_CLIENT || 500);

/** Must match gen3/web `NEXT_PUBLIC_DATAEDGE_*` defaults (urls.ts). */
const httpPort = Number(process.env.DATAEDGE_HTTP_PORT || process.env.PORT || 3002);
const wsPort = Number(process.env.DATAEDGE_WS_PORT || process.env.WS_PORT || 3003);

const wss = new WebSocket.Server({ port: wsPort });
console.log(`[WS] WebSocket server listening on port ${wsPort} (set DATAEDGE_WS_PORT to override)`);

const app = express();

const webhookRoutes = require('./routes/webhook');
app.use('/webhook', express.json(), webhookRoutes);

const streamRoutes = require('./routes/stream');
app.use('/stream', express.json(), streamRoutes);


app.get('/', (req, res) => {
  res.send('Hello World from Backend!');
});

// Inspect which devices the collector has loaded (after add device, id should appear here or after sync).
app.get('/collector/devices', (req, res) => {
  const devices = getAllDevices().map((d) => ({
    id: d.id,
    name: d.name,
    protocol: d.protocol || null,
  }));
  res.json({ count: devices.length, devices });
});

app.post('/collector/sync', express.json(), async (req, res) => {
  try {
    await syncDevicesFromDb();
    const devices = getAllDevices().map((d) => ({
      id: d.id,
      name: d.name,
      protocol: d.protocol || null,
    }));
    res.json({ ok: true, count: devices.length, devices });
  } catch (e) {
    res.status(500).json({ ok: false, error: e.message });
  }
});

app.listen(httpPort, () => {
  console.log(`[HTTP] dataedge listening on http://localhost:${httpPort} (DATAEDGE_HTTP_PORT / PORT)`);
});

const {
  fetchAllDevices,
  reloadDeviceById,
  getAllDevices,
  getDeviceById,
  syncDevicesFromDb,
} = require('./core/dataFetcher');
const { listenForDeviceUpdates } = require('./config/dbNotify');
const { startTrapListener } = require('./protocols/trap');
const TriggerChecker = require('./utils/triggerChecker');

async function run() {
  console.log('[Collector] Starting data collection...');
  
  // Check and fix triggers if needed
  console.log('[Collector] Checking hot reload triggers...');
  const triggerResult = await TriggerChecker.checkAndFix();
  if (!triggerResult.success) {
    console.warn('[Collector] Warning: Trigger setup issues detected:', triggerResult.error);
  }
  
  await fetchAllDevices();
  console.log('[Collector] fetchAllDevices() completed — protocol pollers/subscribers are active.');
  startTrapListener();
  console.log('[Collector] Listening for device config updates...');
  listenForDeviceUpdates(async (deviceId) => {
    console.log(`[Collector] Reloading config for device ${deviceId}`);
    await reloadDeviceById(deviceId);
  });

  await syncDevicesFromDb().catch((e) =>
    console.error('[Collector] post-start DB sync:', e.message)
  );

  const syncMs = Number(process.env.DEVICE_SYNC_INTERVAL_MS ?? 60_000);
  if (syncMs > 0) {
    setInterval(() => {
      syncDevicesFromDb().catch((e) =>
        console.error('[Collector] periodic DB sync error:', e.message)
      );
    }, syncMs);
    console.log(
      `[Collector] Periodic DB sync every ${syncMs}ms (DEVICE_SYNC_INTERVAL_MS=0 to disable)`
    );
  }
}

run();

// เก็บ subscriptions ของแต่ละ client
const clients = new Map(); // Map<ws, Set<device_id|metric_key>>

// buffer สำหรับ batch broadcast
let buffer = [];
const MAX_BUFFER_SIZE = 5000;
const MAX_BATCH_SIZE = 2000;

// Client connect
wss.on('connection', (ws, req) => {
  //const ip = req.headers['x-real-ip'] || req.socket.remoteAddress;

  // ── Auth: verify JWT from ?token= (same secret/claims as backend) ──────────
 /** const user = verifyToken(tokenFromRequest(req));
  if (!user && !WS_AUTH_DISABLED) {
    console.warn(`[WS] Rejected unauthenticated client from ${ip}`);
    try {
      ws.send(JSON.stringify({ type: 'error', code: 'unauthorized', message: 'invalid or missing token' }));
    } catch (_e) {  }
    ws.close(4401, 'unauthorized');
    return;
  } 
  **/
  console.log('[WS] Client connected');
  ws.isAlive = true;
  //ws._user = user || null; // null only when auth disabled (dev)
  clients.set(ws, new Set());

  const ip = req.headers['x-real-ip'] || req.socket.remoteAddress;
  console.log('[WS] Client connected from', ip);
  //console.log(`[WS] Client connected from ${ip}${user ? ` (sub=${user.sub})` : ' (auth disabled)'}`);

  // รับข้อความจาก client
  ws.on('message', (message) => {
    let data;
    try {
      data = JSON.parse(message);
    } catch (err) {
      console.warn('[WS] Invalid message', err);
      return;
    }

    // handle subscribe (enforce site scope + cap)
    if (data.type === 'subscribe' && Array.isArray(data.metrics)) {
      const set = clients.get(ws) || new Set();
      let denied = 0;
      for (const m of data.metrics) {
        if (!m || m.device_id == null || !m.metric_key) continue;
        if (set.size >= MAX_SUBS_PER_CLIENT) {
          try {
            ws.send(JSON.stringify({ type: 'error', code: 'subscription_limit', message: `max ${MAX_SUBS_PER_CLIENT} subscriptions` }));
          } catch (_e) { /* ignore */ }
          break;
        }
        // Site enforcement: only when a user is present (auth enabled).
        if (ws._user) {
          const device = getDeviceById(m.device_id);
          const siteId = device ? device.siteId : null;
          if (!canAccessSite(ws._user, siteId)) {
            denied += 1;
            continue;
          }
        }
        set.add(`${m.device_id}|${m.metric_key}`);
      }
      clients.set(ws, set);
      if (denied > 0) {
        try {
          ws.send(JSON.stringify({ type: 'error', code: 'forbidden_site', message: `${denied} subscription(s) denied by site scope` }));
        } catch (_e) { /* ignore */ }
      }
    }

    // handle unsubscribe
    if (data.type === 'unsubscribe' && Array.isArray(data.metrics)) {
      const set = clients.get(ws);
      if (set) {
        data.metrics.forEach((m) => {
          set.delete(`${m.device_id}|${m.metric_key}`);
        });
      }
    }

    if (data.type === 'unsubscribe_all') {
      clients.set(ws, new Set());
    }
  });

  ws.on('close', () => {
    clients.delete(ws);
    console.log('[WS] Client disconnected');
  });

  ws.on('pong', () => {
    ws.isAlive = true;
  });
});

// Heartbeat
setInterval(() => {
  wss.clients.forEach((ws) => {
    if (ws.isAlive === false) return ws.terminate();
    ws.isAlive = false;
    ws.ping();
  });
}, 10000);

// Batch broadcast loop
setInterval(() => {
  //console.log(`[WS] Buffer present: ${hasBuffer()}, size: ${bufferLength()}`);
  if (!hasBuffer()) return;

  const chunk = getBufferChunk(500);

  for (const [ws, subs] of clients.entries()) {
    if (ws.readyState !== WebSocket.OPEN) continue;

    const match = chunk.filter(m => subs.has(`${m.device_id}|${m.metric_key}`));
    if (match.length > 0) {
      ws.send(JSON.stringify(match));
    }
  }
}, 1000);


function hasAnyClient() {
  for (const subs of clients.values()) {
    if (subs.size > 0) return true;
  }
  return false;
}

// เคลียร์ buffer ถ้าไม่มีใคร subscribe
setInterval(() => {
  if (!hasAnyClient() && hasBuffer()) {
    clearBuffer();
    console.log('[WS] Buffer cleared — no subscribers');
  }
}, 5000);
