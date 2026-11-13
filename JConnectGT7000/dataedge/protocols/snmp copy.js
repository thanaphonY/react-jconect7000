const snmp = require('net-snmp');
const { saveDataPoint } = require('../core/dataSaver');

const snmpIntervals = new Map(); // deviceId => Map<metric.key, intervalId>
const snmpErrorCounters = new Map(); // deviceId => consecutive error count
const snmpConnectionStatus = new Map(); // deviceId => last known status (0 or 1)

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

function fetchSNMP(device) {
  const config = device.communication_config || {};
  const list = Array.isArray(device.metrics) ? device.metrics : [];
  const pollable = list.filter((m) => snmpMetricEnabled(m) && snmpOidForMetric(m));
  const metricCount = pollable.length;
  console.log(
    `[SNMP] polling device id=${device.id} name="${device.name}" host=${config.host || '?'} metrics=${metricCount}` +
      (metricCount === 0 && list.length > 0
        ? ` (skipped ${list.length} row(s): need numeric OID and enable≠false)`
        : list.length === 0
          ? ' (device.metrics empty in DB)'
          : '')
  );
  const { host, community, version, timeout = 2000 } = config;
  if (!host) {
    console.warn(`[SNMP] id=${device.id} name="${device.name}" — no host in communication_config, skipping`);
    return;
  }
  if (metricCount === 0) return;

  //console.log('[SNMP] Fetching SNMP config:', device);
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

  if (!snmpIntervals.has(device.id)) {
    snmpIntervals.set(device.id, new Map());
  }

  const deviceIntervals = snmpIntervals.get(device.id);

  for (const metric of device.metrics) {
    if (!snmpMetricEnabled(metric)) continue;

    const oid = snmpOidForMetric(metric);
    if (!oid) {
      console.warn(
        `[SNMP] Skipping ${device.name} - ${metric.key ?? metric.metricKey ?? '?'} due to missing/invalid OID:`,
        metric.oid ?? metric.OID ?? metric.snmp_oid
      );
      continue;
    }

    const metricKey = metric.key ?? metric.metricKey ?? metric.metric_key ?? oid;
    const oldInterval = deviceIntervals.get(metricKey);
    if (oldInterval) clearInterval(oldInterval);

    const interval = Math.max(Number(metric.pull_rate) || 10, 1);
    const pull_rate = interval * 1000;

    const intervalId = setInterval(() => {
      session.get([oid], async (error, varbinds) => {
        const scale = Number(metric.scale || 1);
        const timestamp = new Date();

        if (error) {
          console.error(`[SNMP]${device.id} ${device.name} - ${metricKey} ERROR:`, error.message);

          const prev = snmpErrorCounters.get(device.id) || 0;
          const current = prev + 1;
          snmpErrorCounters.set(device.id, current);

          // เมื่อเกิด error 5 ครั้งติดกัน และยังไม่ส่ง Connection: 0
          if (current === 30 && snmpConnectionStatus.get(device.id) !== 0) {
            await saveDataPoint(device, [{
              key: "Connection",
              value: 0,
              timestamp
            }]);
            snmpConnectionStatus.set(device.id, 0);
            console.warn(`[SNMP] ${device.name} - Connection marked as FAIL`);
          }

        } else {
          // ดึงค่าปกติ
          const rawValue = varbinds[0]?.value;
          const value = (scale === 0 || scale === 1) ? rawValue : rawValue * scale;
          const datapoints = [{
            key: metricKey,
            value: parseFloat(value),
            timestamp
          }];

          // ถ้าจากเดิม connection fail → กลับมา OK
          if (snmpConnectionStatus.get(device.id) !== 1) {
            datapoints.push({
              key: "Connection",
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
          console.log(`[SNMP] ${device.name} - ${metricKey}: ${value}`);
        }
      });
    }, pull_rate);

    deviceIntervals.set(metricKey, intervalId);
  }
}

// Cleanup function for SNMP devices
function cleanupSNMPDevice(deviceId) {
  console.log(`[SNMP] Cleaning up device ${deviceId}...`);
  
  // Clear all intervals for this device
  if (snmpIntervals.has(deviceId)) {
    const deviceIntervals = snmpIntervals.get(deviceId);
    for (const intervalId of deviceIntervals.values()) {
      clearInterval(intervalId);
      console.log(`[SNMP] Cleared interval for device ${deviceId}`);
    }
    snmpIntervals.delete(deviceId);
    console.log(`[SNMP] Removed device ${deviceId} from snmpIntervals`);
  }
  
  // Clear connection status tracking
  snmpErrorCounters.delete(deviceId);
  snmpConnectionStatus.delete(deviceId);
  
  console.log(`[SNMP] Device ${deviceId} cleanup completed`);
}

module.exports = { fetchSNMP, cleanupSNMPDevice };
