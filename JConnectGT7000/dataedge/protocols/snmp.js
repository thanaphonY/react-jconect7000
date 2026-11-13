const snmp = require('net-snmp');
const { saveDataPoint } = require('../core/dataSaver');

const snmpIntervals = new Map(); // deviceId => Map<pollGroupKey, intervalId>
const snmpErrorCounters = new Map(); // deviceId => consecutive error count
const snmpConnectionStatus = new Map(); // deviceId => last known status (0 or 1)
const snmpSessions = new Map(); // deviceId => snmp session
const snmpInFlightGroups = new Map(); // deviceId => Set<pollGroupKey>

/** Aligned with gateway_modbus_mapping / UI: disabled only when explicitly false. */
function snmpMetricEnabled(m) {
  if (m?.enable === false || m?.enable === 'false') return false;
  return true;
}

/** Numeric OID only (net-snmp); tolerate leading dot from MIB exports. */
function snmpOidForMetric(m) {
  const raw = m?.oid ?? m?.OID ?? m?.snmp_oid;
  if (raw == null) return '';
  const s = String(raw).trim().replace(/^\./, '');
  return /^\d+(\.\d+)*$/.test(s) ? s : '';
}

function markSnmpGroupState(deviceId, groupKey, busy) {
  if (!snmpInFlightGroups.has(deviceId)) {
    snmpInFlightGroups.set(deviceId, new Set());
  }

  const groupSet = snmpInFlightGroups.get(deviceId);
  if (busy) groupSet.add(groupKey);
  else {
    groupSet.delete(groupKey);
    if (groupSet.size === 0) snmpInFlightGroups.delete(deviceId);
  }
}

function isSnmpGroupBusy(deviceId, groupKey) {
  return snmpInFlightGroups.get(deviceId)?.has(groupKey) === true;
}

function chunkArray(items, size) {
  const chunkSize = Math.max(Number(size) || 1, 1);
  const chunks = [];
  for (let i = 0; i < items.length; i += chunkSize) {
    chunks.push(items.slice(i, i + chunkSize));
  }
  return chunks;
}

function sessionGetAsync(session, oids) {
  return new Promise((resolve, reject) => {
    session.get(oids, (error, varbinds = []) => {
      if (error) reject(error);
      else resolve(varbinds);
    });
  });
}

async function markConnectionFailed(device, timestamp) {
  const prev = snmpErrorCounters.get(device.id) || 0;
  const current = prev + 1;
  snmpErrorCounters.set(device.id, current);

  if (current === 30 && snmpConnectionStatus.get(device.id) !== 0) {
    await saveDataPoint(device, [{
      key: 'Connection',
      value: 0,
      timestamp
    }]);
    snmpConnectionStatus.set(device.id, 0);
    console.warn(`[SNMP] ${device.name} - Connection marked as FAIL`);
  }
}

async function processSnmpVarbind(device, metric, varbind, timestamp) {
  const metricKey = metric.key ?? metric.metricKey ?? metric.metric_key ?? snmpOidForMetric(metric);
  const scale = Number(metric.scale || 1);

  if (!varbind || snmp.isVarbindError(varbind)) {
    const reason = varbind ? snmp.varbindError(varbind) : 'empty response';
    console.error(`[SNMP]${device.id} ${device.name} - ${metricKey} ERROR:`, reason);
    await markConnectionFailed(device, timestamp);
    return;
  }

  const rawValue = varbind.value;
  const value = (scale === 0 || scale === 1) ? rawValue : rawValue * scale;
  const datapoints = [{
    key: metricKey,
    value: parseFloat(value),
    timestamp
  }];

  if (snmpConnectionStatus.get(device.id) !== 1) {
    datapoints.push({
      key: 'Connection',
      value: 1,
      timestamp
    });
    snmpConnectionStatus.set(device.id, 1);
    console.info(`[SNMP] ${device.name} - Connection marked as OK`);
  }

  snmpErrorCounters.set(device.id, 0);
  await saveDataPoint(device, datapoints);
  try {
    const { relayGatewayPollToVisualEndpoints } = require('./gateway_endpoint_relay');
    await relayGatewayPollToVisualEndpoints(device, metric, parseFloat(value), timestamp);
  } catch (e) {
    console.error(`[GW-EP relay] ${device.name}:`, e?.message || e);
  }
  //console.log(`[SNMP] ${device.name} - ${metricKey}: ${value}`);
}

function fetchSNMP(device) {
  const config = device.communication_config || {};
  const list = Array.isArray(device.metrics) ? device.metrics : [];
  const pollable = list.filter((m) => snmpMetricEnabled(m) && snmpOidForMetric(m));
  const metricCount = pollable.length;
  const maxOidsPerRequest = Math.max(Number(config.max_oids_per_request || config.batch_size) || 1, 1);
  const batchDelayMs = Math.max(Number(config.batch_delay_ms) || 50, 0);
  console.log(
    `[SNMP] polling device id=${device.id} name="${device.name}" host=${config.host || '?'} metrics=${metricCount} maxOidsPerRequest=${maxOidsPerRequest}` +
      (metricCount === 0 && list.length > 0
        ? ` (skipped ${list.length} row(s): need numeric OID and enable!=false)`
        : list.length === 0
          ? ' (device.metrics empty in DB)'
          : '')
  );
  const { host, community, version, timeout = 2000 } = config;
  if (!host) {
    console.warn(`[SNMP] id=${device.id} name="${device.name}" - no host in communication_config, skipping`);
    return;
  }
  if (metricCount === 0) return;

  let session;
  if (version === '3') {
    const securityLevel = config.security_level || 'noAuthNoPriv';
    const user = {
      name: config.username || 'snmpuser',
      level: securityLevel,
    };

    if (securityLevel === 'authNoPriv' || securityLevel === 'authPriv') {
      user.authProtocol = snmp.AuthProtocols[config.auth_protocol?.toUpperCase()] || snmp.AuthProtocols.sha;
      user.authKey = config.auth_pass;
    }

    if (securityLevel === 'authPriv') {
      user.privProtocol = snmp.PrivProtocols[config.priv_protocol?.toUpperCase()] || snmp.PrivProtocols.aes;
      user.privKey = config.priv_pass;
    }

    session = snmp.createV3Session(host, user, { timeout: Number(timeout) });
  } else {
    const snmpVersion = version === '2c' ? snmp.Version2c : snmp.Version1;
    session = snmp.createSession(host, community, {
      version: snmpVersion,
      timeout: Number(timeout)
    });
  }

  snmpSessions.set(device.id, session);

  if (!snmpIntervals.has(device.id)) {
    snmpIntervals.set(device.id, new Map());
  }

  const deviceIntervals = snmpIntervals.get(device.id);
  const metricsByInterval = new Map();

  for (const metric of list) {
    if (!snmpMetricEnabled(metric)) continue;

    const oid = snmpOidForMetric(metric);
    if (!oid) {
      console.warn(
        `[SNMP] Skipping ${device.name} - ${metric.key ?? metric.metricKey ?? '?'} due to missing/invalid OID:`,
        metric.oid ?? metric.OID ?? metric.snmp_oid
      );
      continue;
    }

    const intervalSec = Math.max(Number(metric.pull_rate) || 10, 1);
    if (!metricsByInterval.has(intervalSec)) {
      metricsByInterval.set(intervalSec, []);
    }
    metricsByInterval.get(intervalSec).push({ metric, oid });
  }

  for (const [intervalSec, items] of metricsByInterval.entries()) {
    const pullRate = intervalSec * 1000;
    const groupKey = `interval:${intervalSec}`;
    const oldInterval = deviceIntervals.get(groupKey);
    if (oldInterval) clearInterval(oldInterval);

    const pollGroup = () => {
      if (isSnmpGroupBusy(device.id, groupKey)) {
        console.warn(`[SNMP] ${device.name} - skip overlapping poll group ${groupKey}`);
        return;
      }

      markSnmpGroupState(device.id, groupKey, true);
      const timestamp = new Date();
      const itemChunks = chunkArray(items, maxOidsPerRequest);

      (async () => {
        try {
          for (let chunkIndex = 0; chunkIndex < itemChunks.length; chunkIndex += 1) {
            const chunkItems = itemChunks[chunkIndex];
            const oids = chunkItems.map((item) => item.oid);
            const varbinds = await sessionGetAsync(session, oids);

            for (let i = 0; i < chunkItems.length; i += 1) {
              await processSnmpVarbind(device, chunkItems[i].metric, varbinds[i], timestamp);
            }

            if (batchDelayMs > 0 && chunkIndex < itemChunks.length - 1) {
              await new Promise((resolve) => setTimeout(resolve, batchDelayMs));
            }
          }
        } catch (error) {
          console.error(`[SNMP]${device.id} ${device.name} - ${groupKey} ERROR:`, error.message);
          await markConnectionFailed(device, timestamp);
        } finally {
          markSnmpGroupState(device.id, groupKey, false);
        }
      })();
    };

    pollGroup();
    const intervalId = setInterval(pollGroup, pullRate);
    deviceIntervals.set(groupKey, intervalId);
  }
}

// Cleanup function for SNMP devices
function cleanupSNMPDevice(deviceId) {
  console.log(`[SNMP] Cleaning up device ${deviceId}...`);

  if (snmpIntervals.has(deviceId)) {
    const deviceIntervals = snmpIntervals.get(deviceId);
    for (const intervalId of deviceIntervals.values()) {
      clearInterval(intervalId);
      console.log(`[SNMP] Cleared interval for device ${deviceId}`);
    }
    snmpIntervals.delete(deviceId);
    console.log(`[SNMP] Removed device ${deviceId} from snmpIntervals`);
  }

  snmpErrorCounters.delete(deviceId);
  snmpConnectionStatus.delete(deviceId);
  snmpInFlightGroups.delete(deviceId);

  const session = snmpSessions.get(deviceId);
  if (session) {
    try {
      session.close();
    } catch (e) {
      console.warn(`[SNMP] Failed closing session for device ${deviceId}:`, e?.message || e);
    }
    snmpSessions.delete(deviceId);
  }

  console.log(`[SNMP] Device ${deviceId} cleanup completed`);
}

module.exports = { fetchSNMP, cleanupSNMPDevice };
