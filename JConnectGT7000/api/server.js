const http = require('node:http');
const crypto = require('node:crypto');
const { Pool } = require('pg');

const PORT = Number(process.env.PORT || 8080);
let pool = null;
let lastCleanup = 0;

function getPool() {
  if (!pool) {
    pool = new Pool({
      host: process.env.DB_HOST || 'timescaledb',
      port: Number(process.env.DB_PORT || 5432),
      user: process.env.DB_USER || 'jconnect',
      password: process.env.DB_PASSWORD || 'P@ssword-001',
      database: process.env.DB_NAME || 'jconnect',
      max: 10,
    });
  }
  return pool;
}

function send(res, status, body) {
  const payload = JSON.stringify(body);
  res.writeHead(status, {
    'content-type': 'application/json; charset=utf-8',
    'content-length': Buffer.byteLength(payload),
  });
  res.end(payload);
}

function fail(res, status, code) {
  send(res, status, { error: { code } });
}

function clientIp(req) {
  const real = req.headers['x-real-ip'];
  if (typeof real === 'string' && real !== '') return real.slice(0, 64);
  return (req.socket.remoteAddress || '').slice(0, 64);
}

function intParam(value) {
  if (value == null || value === '') return null;
  if (!/^\d+$/.test(value)) return false;
  return Number(value);
}

async function query(text, params) {
  try {
    return await getPool().query(text, params);
  } catch (err) {
    if (err && (err.code === 'ECONNREFUSED' || err.code === '57P01' || err.code === '08006' || err.code === '53300')) {
      const wrapped = new Error('database');
      wrapped.database = true;
      throw wrapped;
    }
    if (err && err.code && String(err.code).startsWith('08')) {
      const wrapped = new Error('database');
      wrapped.database = true;
      throw wrapped;
    }
    throw err;
  }
}

function hashToken(token) {
  return crypto.createHash('sha256').update(token).digest('hex');
}

function bearer(req) {
  const header = req.headers.authorization || '';
  const match = /^Bearer (.+)$/.exec(header);
  return match ? match[1].trim() : '';
}

async function loadClient(token) {
  if (!token) return null;
  const result = await query(
    `SELECT id, site_node_id, active, expires_at, rate_limit_per_minute
     FROM api_clients WHERE token_hash = $1`,
    [hashToken(token)],
  );
  return result.rows[0] || null;
}

function clientUsable(row) {
  if (!row || row.active !== true) return false;
  if (row.expires_at && new Date(row.expires_at).getTime() <= Date.now()) return false;
  return true;
}

async function loadNodes() {
  const result = await query('SELECT id, parent_id, name, node_type FROM site_nodes');
  const byId = new Map();
  for (const row of result.rows) byId.set(Number(row.id), row);
  return byId;
}

function descendants(byId, rootId) {
  const children = new Map();
  for (const node of byId.values()) {
    const parent = node.parent_id == null ? 0 : Number(node.parent_id);
    if (!children.has(parent)) children.set(parent, []);
    children.get(parent).push(Number(node.id));
  }
  const out = new Set();
  const stack = [rootId];
  while (stack.length) {
    const id = stack.pop();
    if (out.has(id) || !byId.has(id)) continue;
    out.add(id);
    for (const child of children.get(id) || []) stack.push(child);
  }
  return out;
}

function scopeSet(byId, client) {
  if (client.site_node_id == null) return new Set(byId.keys());
  if (!byId.has(Number(client.site_node_id))) return null;
  return descendants(byId, Number(client.site_node_id));
}

function zoneName(byId, siteId) {
  let current = byId.get(siteId);
  let guard = 0;
  while (current && guard < 30) {
    if (current.node_type === 'Region') return current.name;
    current = current.parent_id == null ? null : byId.get(Number(current.parent_id));
    guard += 1;
  }
  return null;
}

function siteOf(byId, nodeId) {
  let current = byId.get(nodeId);
  let guard = 0;
  while (current && guard < 30) {
    if (current.node_type === 'Site') return current;
    current = current.parent_id == null ? null : byId.get(Number(current.parent_id));
    guard += 1;
  }
  return null;
}

function deviceInfo(row) {
  return {
    id: Number(row.id),
    name: row.name,
    serial_number: row.serial_number,
    location: row.location,
    is_active: row.is_active === true,
  };
}

function dataValue(row) {
  if (row.value !== null) return row.value;
  if (row.value_text !== null) return row.value_text;
  if (row.value_bool !== null) return row.value_bool;
  return row.value_json;
}

async function devicesForSites(siteIds, byId) {
  if (!siteIds.length) return [];
  const allowed = new Set();
  for (const siteId of siteIds) {
    for (const id of descendants(byId, siteId)) allowed.add(id);
  }
  const result = await query(
    `SELECT id, name, serial_number, location, is_active, site_node_id
     FROM devices
     WHERE is_deleted IS NOT TRUE AND site_node_id = ANY($1::int[])
     ORDER BY name, id`,
    [Array.from(allowed)],
  );
  return result.rows;
}

async function alarmCounts(deviceIds) {
  const counts = new Map();
  if (!deviceIds.length) return counts;
  const exists = await query(`SELECT to_regclass('public.alarm_state') AS name`);
  if (!exists.rows[0].name) return counts;
  const result = await query(
    `SELECT device_id, COUNT(*)::int AS n
     FROM alarm_state
     WHERE active = true AND device_id = ANY($1::int[])
     GROUP BY device_id`,
    [deviceIds],
  );
  for (const row of result.rows) counts.set(Number(row.device_id), Number(row.n));
  return counts;
}

async function latestData(deviceIds) {
  const grouped = new Map();
  if (!deviceIds.length) return grouped;
  const result = await query(
    `SELECT DISTINCT ON (device_id, key)
        device_id, key, value, value_text, value_bool, value_json,
        to_char(timestamp AT TIME ZONE 'Asia/Bangkok', 'YYYY-MM-DD HH24:MI:SS') AS timestamp
     FROM device_data
     WHERE device_id = ANY($1::int[])
     ORDER BY device_id, key, timestamp DESC`,
    [deviceIds],
  );
  for (const row of result.rows) {
    const id = Number(row.device_id);
    if (!grouped.has(id)) grouped.set(id, []);
    grouped.get(id).push({ key: row.key, value: dataValue(row), timestamp: row.timestamp });
  }
  return grouped;
}

function sitePayload(site, byId, devices, alarms, withZone) {
  const mine = devices.filter((device) => {
    const owner = siteOf(byId, Number(device.site_node_id));
    return owner && Number(owner.id) === Number(site.id);
  });
  let alarmActive = 0;
  const infos = mine.map((device) => {
    alarmActive += alarms.get(Number(device.id)) || 0;
    return deviceInfo(device);
  });
  const payload = {
    site_id: Number(site.id),
    site_name: site.name,
    alarm_active: alarmActive,
    devices: infos,
  };
  if (withZone) payload.zone_name = zoneName(byId, Number(site.id));
  return payload;
}

async function logRequest(entry) {
  await query(
    `INSERT INTO api_request_logs
      (api_client_id, method, path, zone_id, site_id, device_id, status_code, error_code, client_ip)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)`,
    [
      entry.apiClientId,
      entry.method,
      entry.path,
      entry.zoneId,
      entry.siteId,
      entry.deviceId,
      entry.statusCode,
      entry.errorCode,
      entry.clientIp,
    ],
  );
  if (entry.apiClientId) {
    await query(
      `INSERT INTO api_request_counts (api_client_id, period, period_start, request_count)
       SELECT $1, period, period_start, 1
       FROM (VALUES
         ('hour', (date_trunc('hour', now() AT TIME ZONE 'Asia/Bangkok') AT TIME ZONE 'Asia/Bangkok')),
         ('day', (date_trunc('day', now() AT TIME ZONE 'Asia/Bangkok') AT TIME ZONE 'Asia/Bangkok')),
         ('month', (date_trunc('month', now() AT TIME ZONE 'Asia/Bangkok') AT TIME ZONE 'Asia/Bangkok'))
       ) AS buckets(period, period_start)
       ON CONFLICT (api_client_id, period, period_start)
       DO UPDATE SET request_count = api_request_counts.request_count + 1`,
      [entry.apiClientId],
    );
  }
  if (Date.now() - lastCleanup > 24 * 60 * 60 * 1000) {
    lastCleanup = Date.now();
    await query(`DELETE FROM api_request_logs WHERE created_at < now() - interval '30 days'`);
    await query(`DELETE FROM api_request_counts WHERE period = 'hour' AND period_start < now() - interval '48 hours'`);
    await query(`DELETE FROM api_request_counts WHERE period = 'day' AND period_start < (date_trunc('month', now() AT TIME ZONE 'Asia/Bangkok') AT TIME ZONE 'Asia/Bangkok')`);
    await query(`DELETE FROM api_request_counts WHERE period = 'month' AND period_start < now() - interval '12 months'`);
  }
}

async function touchUsed(clientId) {
  await query(
    `UPDATE api_clients
     SET last_used_at = now()
     WHERE id = $1 AND (last_used_at IS NULL OR last_used_at < now() - interval '1 minute')`,
    [clientId],
  );
}

async function overLimit(client) {
  const result = await query(
    `SELECT COUNT(*)::int AS n
     FROM api_request_logs
     WHERE api_client_id = $1 AND created_at > now() - interval '1 minute'`,
    [client.id],
  );
  return result.rows[0].n >= Number(client.rate_limit_per_minute);
}

async function handleData(req, res, url) {
  const path = url.pathname;
  const ip = clientIp(req);
  const zoneRaw = url.searchParams.get('zone');
  const siteRaw = url.searchParams.get('site');
  const deviceRaw = url.searchParams.get('device');
  const zoneId = intParam(zoneRaw);
  const siteId = intParam(siteRaw);
  const deviceId = intParam(deviceRaw);
  const base = {
    method: 'GET',
    path,
    zoneId: typeof zoneId === 'number' ? zoneId : null,
    siteId: typeof siteId === 'number' ? siteId : null,
    deviceId: typeof deviceId === 'number' ? deviceId : null,
    clientIp: ip,
  };

  const token = bearer(req);
  let client = null;
  try {
    client = token ? await loadClient(token) : null;
  } catch (err) {
    if (err.database) return fail(res, 503, 'database_unavailable');
    return fail(res, 500, 'Internal server error');
  }

  async function finish(status, code, body, used) {
    try {
      await logRequest({
        ...base,
        apiClientId: client && clientUsable(client) ? client.id : null,
        statusCode: status,
        errorCode: code,
      });
      if (used && client) await touchUsed(client.id);
    } catch (err) {
      if (err.database) return fail(res, 503, 'database_unavailable');
      return fail(res, 500, 'Internal server error');
    }
    if (code) return fail(res, status, code);
    return send(res, status, body);
  }

  if (!client || !clientUsable(client)) return finish(401, 'unauthorized');
  if (zoneId === false || siteId === false || deviceId === false) return finish(400, 'invalid_request');
  if (path.endsWith('/node') && zoneId != null && siteId != null) return finish(400, 'invalid_request');
  if (path.endsWith('/device') && siteId == null && deviceId == null) return finish(400, 'invalid_request');
  if (path.endsWith('/device') && siteId != null && deviceId != null) return finish(400, 'invalid_request');

  try {
    if (await overLimit(client)) return finish(429, 'rate_limited');
    const byId = await loadNodes();
    const scope = scopeSet(byId, client);
    if (!scope) return finish(401, 'unauthorized');

    if (path.endsWith('/node')) {
      let sites = Array.from(byId.values()).filter((node) => node.node_type === 'Site' && scope.has(Number(node.id)));
      if (zoneId != null) {
        const zone = byId.get(zoneId);
        if (!zone || zone.node_type !== 'Region') return finish(404, 'not_found');
        if (!scope.has(zoneId)) return finish(403, 'forbidden');
        const under = descendants(byId, zoneId);
        sites = sites.filter((site) => under.has(Number(site.id)));
      }
      if (siteId != null) {
        const site = byId.get(siteId);
        if (!site || site.node_type !== 'Site') return finish(404, 'not_found');
        if (!scope.has(siteId)) return finish(403, 'forbidden');
        sites = [site];
      }
      const rows = await devicesForSites(sites.map((site) => Number(site.id)), byId);
      const alarms = await alarmCounts(rows.map((row) => Number(row.id)));
      const withZone = zoneId == null && siteId == null;
      return finish(200, null, {
        sites: sites.map((site) => sitePayload(site, byId, rows, alarms, withZone)),
      }, true);
    }

    if (path.endsWith('/device') && siteId != null) {
      const site = byId.get(siteId);
      if (!site || site.node_type !== 'Site') return finish(404, 'not_found');
      if (!scope.has(siteId)) return finish(403, 'forbidden');
      const rows = await devicesForSites([siteId], byId);
      const ids = rows.map((row) => Number(row.id));
      const alarms = await alarmCounts(ids);
      const latest = await latestData(ids);
      let alarmActive = 0;
      const devices = rows.map((row) => {
        const count = alarms.get(Number(row.id)) || 0;
        alarmActive += count;
        return { info: deviceInfo(row), alarm_active: count, data: latest.get(Number(row.id)) || [] };
      });
      return finish(200, null, { site_name: site.name, alarm_active: alarmActive, devices }, true);
    }

    const found = await query(
      `SELECT id, name, serial_number, location, is_active, site_node_id
       FROM devices WHERE id = $1 AND is_deleted IS NOT TRUE`,
      [deviceId],
    );
    const device = found.rows[0];
    if (!device) return finish(404, 'not_found');
    if (device.site_node_id == null || !scope.has(Number(device.site_node_id))) return finish(403, 'forbidden');
    const site = siteOf(byId, Number(device.site_node_id));
    const alarms = await alarmCounts([Number(device.id)]);
    const latest = await latestData([Number(device.id)]);
    return finish(200, null, {
      site_name: site ? site.name : null,
      alarm_active: alarms.get(Number(device.id)) || 0,
      info: deviceInfo(device),
      data: latest.get(Number(device.id)) || [],
    }, true);
  } catch (err) {
    if (err.database) return fail(res, 503, 'database_unavailable');
    return fail(res, 500, 'Internal server error');
  }
}

const server = http.createServer(async (req, res) => {
  let url;
  try {
    url = new URL(req.url, 'http://localhost');
  } catch (err) {
    return fail(res, 400, 'invalid_request');
  }
  if (url.pathname === '/api/public/v1') {
    if (req.method !== 'GET') return fail(res, 405, 'method_not_allowed');
    return send(res, 200, { status: 'ok' });
  }
  if (url.pathname !== '/api/public/v1/node' && url.pathname !== '/api/public/v1/device') {
    return fail(res, 404, 'not_found');
  }
  if (req.method !== 'GET') {
    try {
      const token = bearer(req);
      const client = token ? await loadClient(token) : null;
      await logRequest({
        apiClientId: client && clientUsable(client) ? client.id : null,
        method: req.method,
        path: url.pathname,
        zoneId: null,
        siteId: null,
        deviceId: null,
        statusCode: 405,
        errorCode: 'method_not_allowed',
        clientIp: clientIp(req),
      });
    } catch (err) {
      if (err.database) return fail(res, 503, 'database_unavailable');
    }
    return fail(res, 405, 'method_not_allowed');
  }
  return handleData(req, res, url);
});

server.listen(PORT, '0.0.0.0');
