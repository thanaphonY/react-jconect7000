/**
 * RTSP camera protocol:
 * - Phase 1: probe connectivity → stream_status metric
 * - Phase 2: single RTSP pull → HLS buffer on disk (1 camera → N viewers)
 * - Phase 3: takeSnapshot without stealing the live RTSP session when possible
 */
const fs = require('fs');
const path = require('path');
const { spawn, execFile } = require('child_process');
const axios = require('axios');
const { saveDataPoint } = require('../core/dataSaver');

const MEDIA_ROOT = (() => {
  if (process.env.MEDIA_ROOT) return process.env.MEDIA_ROOT;
  const candidates = [
    '/var/www/jconnect/media',
    path.join(__dirname, '../../media'),
  ];
  for (const c of candidates) {
    try {
      fs.mkdirSync(c, { recursive: true });
      fs.mkdirSync(path.join(c, 'hls'), { recursive: true });
      fs.mkdirSync(path.join(c, 'snapshots'), { recursive: true });
      return c;
    } catch (_) {
      /* try next */
    }
  }
  return path.join(__dirname, '../../media');
})();
const HLS_ROOT = path.join(MEDIA_ROOT, 'hls');
const SNAPSHOT_ROOT = path.join(MEDIA_ROOT, 'snapshots');
const IDLE_TIMEOUT_MS = Number(process.env.RTSP_IDLE_TIMEOUT_MS || 45_000);
const PROBE_TIMEOUT_MS = Number(process.env.RTSP_PROBE_TIMEOUT_MS || 8_000);
const HLS_SEGMENT_SEC = Number(process.env.RTSP_HLS_SEGMENT_SEC || 2);
const HLS_LIST_SIZE = Number(process.env.RTSP_HLS_LIST_SIZE || 5);
const FFMPEG_BIN = process.env.FFMPEG_PATH || 'ffmpeg';
const FFPROBE_BIN = process.env.FFPROBE_PATH || 'ffprobe';
/** Max snapshot width in pixels (keeps aspect ratio). Default 1280 — good for LINE + disk. */
const SNAPSHOT_MAX_WIDTH = Number(process.env.SNAPSHOT_MAX_WIDTH || 1280);
/** ffmpeg -q:v 2 (best) … 31 (smallest). Default 5 ≈ small but readable. */
const SNAPSHOT_JPEG_Q = Number(process.env.SNAPSHOT_JPEG_QUALITY || 5);

let ffmpegMissingLogged = false;

function spawnFfmpeg(args) {
  return new Promise((resolve, reject) => {
    let settled = false;
    let child;
    try {
      child = spawn(FFMPEG_BIN, args, { stdio: ['ignore', 'ignore', 'pipe'] });
    } catch (e) {
      reject(e);
      return;
    }

    const fail = (err) => {
      if (settled) return;
      settled = true;
      if (err && err.code === 'ENOENT') {
        if (!ffmpegMissingLogged) {
          ffmpegMissingLogged = true;
          console.error(
            `[RTSP] ${FFMPEG_BIN} not found (ENOENT). Install ffmpeg on the server, e.g. sudo apt install -y ffmpeg`
          );
        }
        reject(new Error('ffmpeg_not_installed'));
        return;
      }
      reject(err || new Error('ffmpeg_spawn_failed'));
    };

    const ok = () => {
      if (settled) return;
      settled = true;
      resolve(child);
    };

    child.once('error', fail);
    child.once('spawn', ok);
    // Fallback if 'spawn' is unavailable in older Node
    setTimeout(() => {
      if (!settled && child.pid) ok();
    }, 50);
  });
}

/** deviceId -> { probeTimer, device } */
const probeState = new Map();

/**
 * deviceId -> {
 *   state: 'idle'|'starting'|'running'|'stopping',
 *   process, viewers: Map<viewerId,{lastSeen}>,
 *   idleTimer, device, hlsDir, playlistUrl
 * }
 */
const streams = new Map();

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function metricEnabled(m) {
  const en = m?.enable;
  return en !== false && en !== 'false';
}

function getComm(device) {
  return device?.communication_config && typeof device.communication_config === 'object'
    ? device.communication_config
    : {};
}

function ensureLeadingSlash(p) {
  const s = String(p || '').trim();
  if (!s) return '';
  if (/^https?:\/\//i.test(s) || /^rtsp:\/\//i.test(s)) return s;
  return s.startsWith('/') ? s : `/${s}`;
}

/**
 * Build RTSP URL:
 *   rtsp://user:pass@ip:rtsp_port/Streaming/Channels/102
 * Also accepts legacy full rtsp_url values.
 */
function buildRtspUrl(device) {
  const c = getComm(device);
  const raw = String(c.rtsp_url || '').trim();
  const user = String(c.username || '').trim();
  const pass = String(c.password || '');
  const ip = String(c.ip || c.host || '').trim();

  // Legacy: full rtsp:// URL already provided
  if (/^rtsp:\/\//i.test(raw)) {
    let url = raw;
    if (user && !/@/.test(url.replace(/^rtsp:\/\//i, ''))) {
      const auth = `${encodeURIComponent(user)}:${encodeURIComponent(pass)}@`;
      url = url.replace(/^(rtsp:\/\/)/i, `$1${auth}`);
    }
    return url;
  }

  if (!ip) return '';
  const rtspPort = Number(c.rtsp_port) || 554;
  const streamPath = ensureLeadingSlash(raw || '/Streaming/Channels/101');
  const auth =
    user !== ''
      ? `${encodeURIComponent(user)}:${encodeURIComponent(pass)}@`
      : '';
  return `rtsp://${auth}${ip}:${rtspPort}${streamPath}`;
}

/**
 * Build HTTP snapshot URL from IP + REST port + path:
 *   http://user:pass@ip:port/ISAPI/Streaming/channels/101/picture
 * Also accepts legacy full http(s) snapshot_url.
 * @param {{ includeAuth?: boolean }} [opts]
 */
function buildSnapshotHttpUrl(device, opts = {}) {
  const includeAuth = opts.includeAuth !== false;
  const c = getComm(device);
  const raw = String(c.snapshot_url || '').trim();
  if (!raw) return '';
  if (/^https?:\/\//i.test(raw)) {
    if (!includeAuth) {
      return raw.replace(/^(https?:\/\/)([^/@]+@)/i, '$1');
    }
    return raw;
  }

  const ip = String(c.ip || c.host || '').trim();
  if (!ip) return '';
  const port = Number(c.port) || 80;
  const pathPart = ensureLeadingSlash(raw);
  const user = String(c.username || '').trim();
  const pass = String(c.password || '');
  const auth =
    includeAuth && user !== ''
      ? `${encodeURIComponent(user)}:${encodeURIComponent(pass)}@`
      : '';
  const scheme = Number(port) === 443 ? 'https' : 'http';
  return `${scheme}://${auth}${ip}:${port}${pathPart}`;
}

function transportFlag(device) {
  const t = String(getComm(device).transport || 'tcp').toLowerCase();
  return t === 'udp' ? 'udp' : 'tcp';
}

function hlsDirFor(deviceId) {
  return path.join(HLS_ROOT, String(deviceId));
}

function snapshotDirFor(deviceId) {
  return path.join(SNAPSHOT_ROOT, String(deviceId));
}

function publicHlsUrl(deviceId) {
  return `/media/hls/${deviceId}/index.m3u8`;
}

function runExecFile(bin, args, timeoutMs) {
  return new Promise((resolve) => {
    execFile(
      bin,
      args,
      { timeout: timeoutMs, killSignal: 'SIGKILL', maxBuffer: 2 * 1024 * 1024 },
      (err, stdout, stderr) => {
        resolve({
          ok: !err,
          code: err?.code ?? 0,
          stdout: String(stdout || ''),
          stderr: String(stderr || ''),
          error: err ? err.message : null,
        });
      }
    );
  });
}

/**
 * Probe RTSP reachability via ffprobe (falls back to ffmpeg).
 * Returns { ok, detail }
 */
async function probe(device) {
  const url = buildRtspUrl(device);
  if (!url) return { ok: false, detail: 'missing_rtsp_url' };

  const transport = transportFlag(device);
  const common = [
    '-rtsp_transport',
    transport,
    '-timeout',
    String(PROBE_TIMEOUT_MS * 1000),
    '-i',
    url,
  ];

  let result = await runExecFile(
    FFPROBE_BIN,
    [
      '-v',
      'error',
      ...common,
      '-show_entries',
      'stream=codec_type',
      '-of',
      'csv=p=0',
    ],
    PROBE_TIMEOUT_MS + 2000
  );

  if (!result.ok) {
    result = await runExecFile(
      FFMPEG_BIN,
      ['-hide_banner', '-loglevel', 'error', ...common, '-t', '1', '-f', 'null', '-'],
      PROBE_TIMEOUT_MS + 2000
    );
  }

  return {
    ok: result.ok,
    detail: result.ok ? 'online' : result.stderr || result.error || 'offline',
  };
}

function clearHlsDir(deviceId) {
  const dir = hlsDirFor(deviceId);
  try {
    if (fs.existsSync(dir)) {
      for (const f of fs.readdirSync(dir)) {
        fs.unlinkSync(path.join(dir, f));
      }
    }
  } catch (e) {
    console.warn(`[RTSP] clear HLS dir ${deviceId}:`, e.message);
  }
}

function getOrCreateStream(deviceId) {
  if (!streams.has(deviceId)) {
    streams.set(deviceId, {
      state: 'idle',
      process: null,
      viewers: new Map(),
      idleTimer: null,
      device: null,
      hlsDir: hlsDirFor(deviceId),
      playlistUrl: publicHlsUrl(deviceId),
      startedAt: null,
    });
  }
  return streams.get(deviceId);
}

function cancelIdleTimer(st) {
  if (st.idleTimer) {
    clearTimeout(st.idleTimer);
    st.idleTimer = null;
  }
}

function scheduleIdleStop(deviceId) {
  const st = streams.get(deviceId);
  if (!st) return;
  cancelIdleTimer(st);
  st.idleTimer = setTimeout(() => {
    const cur = streams.get(deviceId);
    if (!cur) return;
    if (cur.viewers.size === 0) {
      console.log(`[RTSP] idle timeout → stop stream device=${deviceId}`);
      stopStream(deviceId).catch((e) => console.warn('[RTSP] stopStream:', e.message));
    }
  }, IDLE_TIMEOUT_MS);
}

async function startFfmpegHls(device) {
  const deviceId = device.id;
  const st = getOrCreateStream(deviceId);
  if (st.state === 'running' || st.state === 'starting') {
    return st;
  }

  const url = buildRtspUrl(device);
  if (!url) throw new Error('missing_rtsp_url');

  st.state = 'starting';
  st.device = device;
  ensureDir(st.hlsDir);
  clearHlsDir(deviceId);

  const transport = transportFlag(device);
  const playlist = path.join(st.hlsDir, 'index.m3u8');
  const segmentPattern = path.join(st.hlsDir, 'seg_%03d.ts');

  const args = [
    '-hide_banner',
    '-loglevel',
    'warning',
    '-rtsp_transport',
    transport,
    '-i',
    url,
    '-an',
    // Re-encode to browser-safe H.264 (camera main streams are often H.265 / odd profiles)
    '-c:v',
    'libx264',
    '-preset',
    'ultrafast',
    '-tune',
    'zerolatency',
    '-pix_fmt',
    'yuv420p',
    '-profile:v',
    'baseline',
    '-level',
    '3.1',
    '-g',
    '30',
    '-keyint_min',
    '30',
    '-sc_threshold',
    '0',
    '-f',
    'hls',
    '-hls_time',
    String(HLS_SEGMENT_SEC),
    '-hls_list_size',
    String(HLS_LIST_SIZE),
    '-hls_flags',
    'delete_segments+append_list+omit_endlist+independent_segments',
    '-hls_segment_filename',
    segmentPattern,
    playlist,
  ];

  console.log(`[RTSP] starting HLS pull device=${deviceId} transport=${transport}`);
  let child;
  try {
    child = await spawnFfmpeg(args);
  } catch (e) {
    st.process = null;
    st.state = 'idle';
    throw e;
  }
  st.process = child;
  st.startedAt = Date.now();

  let stderrBuf = '';
  child.stderr.on('data', (chunk) => {
    stderrBuf += chunk.toString();
    if (stderrBuf.length > 4000) stderrBuf = stderrBuf.slice(-2000);
  });

  child.on('exit', (code, signal) => {
    console.warn(
      `[RTSP] ffmpeg exited device=${deviceId} code=${code} signal=${signal} stderr=${stderrBuf.slice(-500)}`
    );
    const cur = streams.get(deviceId);
    if (cur && cur.process === child) {
      cur.process = null;
      cur.state = 'idle';
    }
  });

  // Wait briefly for playlist to appear
  const deadline = Date.now() + 12_000;
  while (Date.now() < deadline) {
    if (fs.existsSync(playlist)) {
      st.state = 'running';
      return st;
    }
    if (!st.process || st.process.killed) {
      st.state = 'idle';
      throw new Error(`ffmpeg_failed: ${stderrBuf.slice(-300) || 'exited'}`);
    }
    await new Promise((r) => setTimeout(r, 400));
  }

  if (fs.existsSync(playlist)) {
    st.state = 'running';
    return st;
  }

  try {
    child.kill('SIGTERM');
  } catch (_) {
    /* ignore */
  }
  st.process = null;
  st.state = 'idle';
  throw new Error(`hls_playlist_timeout: ${stderrBuf.slice(-300)}`);
}

async function stopStream(deviceId) {
  const st = streams.get(deviceId);
  if (!st) return;
  cancelIdleTimer(st);
  st.state = 'stopping';
  st.viewers.clear();
  if (st.process && !st.process.killed) {
    try {
      st.process.kill('SIGTERM');
    } catch (_) {
      /* ignore */
    }
    setTimeout(() => {
      try {
        if (st.process && !st.process.killed) st.process.kill('SIGKILL');
      } catch (_) {
        /* ignore */
      }
    }, 2000);
  }
  st.process = null;
  st.state = 'idle';
  clearHlsDir(deviceId);
}

/**
 * Register a viewer. Starts single ffmpeg pull if idle.
 * @returns {{ ok, state, playlistUrl, viewerId, viewers }}
 */
async function registerViewer(device, viewerId) {
  const deviceId = device.id;
  const st = getOrCreateStream(deviceId);
  st.device = device;
  const vid = String(viewerId || `v_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`);
  cancelIdleTimer(st);
  st.viewers.set(vid, { lastSeen: Date.now() });

  if (st.state !== 'running') {
    await startFfmpegHls(device);
  }

  return {
    ok: true,
    state: st.state,
    playlistUrl: st.playlistUrl,
    viewerId: vid,
    viewers: st.viewers.size,
  };
}

function heartbeatViewer(deviceId, viewerId) {
  const st = streams.get(deviceId);
  if (!st) return { ok: false, error: 'no_stream' };
  const vid = String(viewerId || '');
  if (!vid || !st.viewers.has(vid)) {
    return { ok: false, error: 'unknown_viewer', state: st.state, viewers: st.viewers.size };
  }
  st.viewers.set(vid, { lastSeen: Date.now() });
  cancelIdleTimer(st);
  return { ok: true, state: st.state, playlistUrl: st.playlistUrl, viewers: st.viewers.size };
}

function unregisterViewer(deviceId, viewerId) {
  const st = streams.get(deviceId);
  if (!st) return { ok: true, state: 'idle', viewers: 0 };
  if (viewerId) st.viewers.delete(String(viewerId));
  if (st.viewers.size === 0) scheduleIdleStop(deviceId);
  return { ok: true, state: st.state, viewers: st.viewers.size };
}

function getStreamInfo(deviceId) {
  const st = streams.get(deviceId);
  if (!st) {
    return {
      state: 'idle',
      viewers: 0,
      playlistUrl: publicHlsUrl(deviceId),
    };
  }
  return {
    state: st.state,
    viewers: st.viewers.size,
    playlistUrl: st.playlistUrl,
    startedAt: st.startedAt,
  };
}

function isStreamRunning(deviceId) {
  const st = streams.get(deviceId);
  return !!(st && st.state === 'running' && st.process);
}

function snapshotResizeOpts(device) {
  const c = getComm(device);
  const maxW = Math.max(
    320,
    Number(c.snapshot_max_width) || SNAPSHOT_MAX_WIDTH || 1280
  );
  let q = Number(c.snapshot_jpeg_quality);
  if (!Number.isFinite(q)) q = SNAPSHOT_JPEG_Q;
  q = Math.min(31, Math.max(2, Math.round(q)));
  return { maxW, q };
}

/** ffmpeg args: scale down if wider than maxW, then JPEG quality */
function snapshotEncodeArgs(device) {
  const { maxW, q } = snapshotResizeOpts(device);
  return [
    '-vf',
    `scale='min(${maxW},iw)':-2`,
    '-frames:v',
    '1',
    '-q:v',
    String(q),
  ];
}

/** Re-encode an existing JPEG to a smaller file (used after HTTP snapshot download). */
async function compressSnapshotJpeg(srcPath, destPath, device) {
  const tmpPath = destPath + '.tmp.jpg';
  const result = await runExecFile(
    FFMPEG_BIN,
    ['-hide_banner', '-loglevel', 'error', '-i', srcPath, ...snapshotEncodeArgs(device), '-y', tmpPath],
    12_000
  );
  if (!result.ok || !fs.existsSync(tmpPath)) {
    try { fs.unlinkSync(tmpPath); } catch (_) { /* ignore */ }
    if (result.error && /ENOENT|not found/i.test(String(result.error))) {
      throw new Error('ffmpeg_not_installed');
    }
    // Keep original if compress fails
    if (srcPath !== destPath && fs.existsSync(srcPath)) {
      fs.renameSync(srcPath, destPath);
    }
    console.warn('[RTSP] compressSnapshotJpeg failed, keeping original:', result.stderr || result.error);
    return destPath;
  }
  if (srcPath !== destPath && fs.existsSync(srcPath)) {
    try { fs.unlinkSync(srcPath); } catch (_) { /* ignore */ }
  }
  fs.renameSync(tmpPath, destPath);
  return destPath;
}

async function snapshotFromHttp(outPath, device) {
  const c = getComm(device);
  const urlNoAuth = buildSnapshotHttpUrl(device, { includeAuth: false });
  const urlWithAuth = buildSnapshotHttpUrl(device, { includeAuth: true });
  if (!urlNoAuth) throw new Error('missing_snapshot_http_url');

  const user = String(c.username || '').trim();
  const pass = String(c.password || '');
  const rawPath = outPath + '.raw.jpg';

  // 1) axios + Basic auth (works for some cameras)
  try {
    const res = await axios.get(urlNoAuth, {
      responseType: 'arraybuffer',
      timeout: 10_000,
      auth: user ? { username: user, password: pass } : undefined,
      validateStatus: (s) => s >= 200 && s < 300,
    });
    fs.writeFileSync(rawPath, Buffer.from(res.data));
    try {
      await compressSnapshotJpeg(rawPath, outPath, device);
    } finally {
      try { if (fs.existsSync(rawPath)) fs.unlinkSync(rawPath); } catch (_) { /* ignore */ }
    }
    return outPath;
  } catch (e) {
    console.warn(
      `[RTSP] HTTP snapshot axios failed (${e.response?.status || e.message}); trying ffmpeg (digest)`
    );
  }

  // 2) ffmpeg often handles Digest auth (Hikvision ISAPI)
  const result = await runExecFile(
    FFMPEG_BIN,
    [
      '-hide_banner',
      '-loglevel',
      'error',
      '-i',
      urlWithAuth,
      ...snapshotEncodeArgs(device),
      '-y',
      outPath,
    ],
    15_000
  );
  if (!result.ok || !fs.existsSync(outPath)) {
    if (result.error && /ENOENT|not found/i.test(String(result.error))) {
      throw new Error('ffmpeg_not_installed');
    }
    throw new Error(result.stderr || result.error || 'snapshot_http_failed');
  }
  return outPath;
}

async function snapshotFromRtsp(device, outPath) {
  const url = buildRtspUrl(device);
  if (!url) throw new Error('missing_rtsp_url');
  const transport = transportFlag(device);
  const result = await runExecFile(
    FFMPEG_BIN,
    [
      '-hide_banner',
      '-loglevel',
      'error',
      '-rtsp_transport',
      transport,
      '-i',
      url,
      ...snapshotEncodeArgs(device),
      '-y',
      outPath,
    ],
    15_000
  );
  if (!result.ok || !fs.existsSync(outPath)) {
    if (result.error && /ENOENT|not found/i.test(String(result.error))) {
      throw new Error('ffmpeg_not_installed');
    }
    throw new Error(result.stderr || result.error || 'snapshot_rtsp_failed');
  }
  return outPath;
}

async function snapshotFromHls(deviceId, outPath, device) {
  const playlist = path.join(hlsDirFor(deviceId), 'index.m3u8');
  if (!fs.existsSync(playlist)) throw new Error('hls_playlist_missing');
  const result = await runExecFile(
    FFMPEG_BIN,
    [
      '-hide_banner',
      '-loglevel',
      'error',
      '-i',
      playlist,
      ...snapshotEncodeArgs(device),
      '-y',
      outPath,
    ],
    12_000
  );
  if (!result.ok || !fs.existsSync(outPath)) {
    if (result.error && /ENOENT|not found/i.test(String(result.error))) {
      throw new Error('ffmpeg_not_installed');
    }
    throw new Error(result.stderr || result.error || 'snapshot_hls_failed');
  }
  return outPath;
}

/**
 * Take a JPEG snapshot. Prefer HTTP snapshot_url, then live HLS, then RTSP (only if idle).
 * @returns {{ ok, path, publicPath, method }}
 */
async function takeSnapshot(device) {
  const deviceId = device.id;
  const dir = snapshotDirFor(deviceId);
  ensureDir(dir);
  const stamp = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  const c = getComm(device);

  // Prefix: last IP octet (e.g. 192.168.1.42 → "42") or sanitised device name
  const ip = String(c.ip || c.host || '').trim();
  const lastOctet = ip.match(/(\d+)$/)?.[1];
  const nameBase = String(device.name || '').trim().replace(/[^a-zA-Z0-9ก-๙\-_]/g, '_').slice(0, 24);
  const prefix = lastOctet ? `ip${lastOctet}` : (nameBase || `cam${deviceId}`);

  const name = `${prefix}_${stamp.getFullYear()}${pad(stamp.getMonth() + 1)}${pad(stamp.getDate())}_${pad(stamp.getHours())}${pad(stamp.getMinutes())}${pad(stamp.getSeconds())}.jpg`;
  const outPath = path.join(dir, name);
  const publicPath = `/media/snapshots/${deviceId}/${name}`;

  let method = null;
  const attempts = [];
  if (c.snapshot_url && String(c.snapshot_url).trim()) attempts.push('http');
  if (isStreamRunning(deviceId)) attempts.push('hls');
  attempts.push('rtsp');

  let lastError = null;
  for (const tryMethod of attempts) {
    try {
      method = tryMethod;
      if (tryMethod === 'http') {
        await snapshotFromHttp(outPath, device);
      } else if (tryMethod === 'hls') {
        await snapshotFromHls(deviceId, outPath, device);
      } else {
        await snapshotFromRtsp(device, outPath);
      }
      lastError = null;
      break;
    } catch (e) {
      lastError = e;
      console.warn(
        `[RTSP] takeSnapshot device=${deviceId} method=${tryMethod} failed: ${e.message}`
      );
    }
  }

  if (lastError) {
    console.error(`[RTSP] takeSnapshot device=${deviceId} all methods failed:`, lastError.message);
    return { ok: false, error: lastError.message, method };
  }

  // Update snapshot meta metrics if present
  const nowIso = stamp.toISOString();
  const points = [];
  for (const m of device.metrics || []) {
    if (!metricEnabled(m)) continue;
    if (m.type === 'rtsp_snapshot_meta' || m.key === 'last_snapshot_at') {
      points.push({ key: m.key || 'last_snapshot_at', value: nowIso, timestamp: stamp });
    }
    if (m.key === 'last_snapshot_path') {
      points.push({ key: 'last_snapshot_path', value: publicPath, timestamp: stamp });
    }
  }
  if (points.length) {
    try {
      await saveDataPoint(device, points);
    } catch (e) {
      console.warn('[RTSP] snapshot meta save:', e.message);
    }
  }

  return { ok: true, path: outPath, publicPath, method };
}

async function writeProbeStatus(device, online) {
  const stamp = new Date();
  const points = [];
  for (const m of device.metrics || []) {
    if (!metricEnabled(m)) continue;
    if (m.type === 'rtsp_probe' || m.key === 'stream_status') {
      const dt = String(m.datatype || 'int').toLowerCase();
      let value = online ? 1 : 0;
      if (dt === 'string' || dt === 'bool') {
        value = online ? (dt === 'bool' ? true : 'online') : dt === 'bool' ? false : 'offline';
      }
      points.push({ key: m.key || 'stream_status', value, timestamp: stamp });
    }
  }
  if (!points.length) {
    // Still emit a default stream_status so dashboards can bind even without template metrics
    points.push({ key: 'stream_status', value: online ? 1 : 0, timestamp: stamp });
  }
  await saveDataPoint(device, points);
}

function fetchRtsp(device) {
  cleanupRtspDevice(device.id);
  probeState.set(device.id, { device });

  const probeMetrics = (device.metrics || []).filter(
    (m) => metricEnabled(m) && (m.type === 'rtsp_probe' || m.key === 'stream_status')
  );
  const intervalSec = Math.max(
    Number(probeMetrics[0]?.pull_rate) || Number(getComm(device).probe_interval) || 60,
    15
  );

  console.log(
    `[RTSP] probe schedule device id=${device.id} name="${device.name}" every ${intervalSec}s`
  );

  const tick = async () => {
    const state = probeState.get(device.id);
    if (!state) return;
    try {
      const result = await probe(state.device || device);
      await writeProbeStatus(state.device || device, result.ok);
    } catch (e) {
      console.error(`[RTSP] probe error device=${device.id}:`, e.message);
      try {
        await writeProbeStatus(state.device || device, false);
      } catch (_) {
        /* ignore */
      }
    }
  };

  tick();
  const timer = setInterval(tick, intervalSec * 1000);
  probeState.set(device.id, { device, probeTimer: timer });
}

function cleanupRtspDevice(deviceId) {
  const st = probeState.get(deviceId);
  if (st?.probeTimer) clearInterval(st.probeTimer);
  probeState.delete(deviceId);
  stopStream(deviceId).catch(() => {});
}

function updateRtspDeviceRef(device) {
  const st = probeState.get(device.id);
  if (st) st.device = device;
  const stream = streams.get(device.id);
  if (stream) stream.device = device;
}

// Expire stale viewers periodically
setInterval(() => {
  const now = Date.now();
  for (const [deviceId, st] of streams.entries()) {
    for (const [vid, info] of st.viewers.entries()) {
      if (now - (info.lastSeen || 0) > IDLE_TIMEOUT_MS * 2) {
        st.viewers.delete(vid);
      }
    }
    if (st.viewers.size === 0 && st.state === 'running') {
      scheduleIdleStop(deviceId);
    }
  }
}, 15_000);

try {
  ensureDir(HLS_ROOT);
  ensureDir(SNAPSHOT_ROOT);
} catch (e) {
  console.warn('[RTSP] media root not writable yet:', MEDIA_ROOT, e.message);
}

module.exports = {
  fetchRtsp,
  cleanupRtspDevice,
  updateRtspDeviceRef,
  probe,
  registerViewer,
  heartbeatViewer,
  unregisterViewer,
  stopStream,
  getStreamInfo,
  isStreamRunning,
  takeSnapshot,
  buildRtspUrl,
  buildSnapshotHttpUrl,
  MEDIA_ROOT,
  HLS_ROOT,
  SNAPSHOT_ROOT,
};
