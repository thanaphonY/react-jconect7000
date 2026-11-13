function parseJsonColumn(value, fallback) {
  if (value == null) return fallback;
  if (Array.isArray(value)) return value;
  if (typeof value === 'object') return value;
  try {
    return JSON.parse(String(value));
  } catch {
    return fallback;
  }
}

function normalizeDeviceRole(value) {
  const role = String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_');

  if (role === 'gateway_endpoint') return 'endpoint';
  if (role === 'gateway_collector') return 'gateway';
  if (role === 'native') return 'native_device';
  return role;
}

function resolveDeviceProtocol(communicationConfig, metrics, config, fallbackProtocol) {
  const role = normalizeDeviceRole(config?.role);
  if (role === 'endpoint') return 'gateway_endpoint';

  return String(
    communicationConfig?.protocol || metrics?.[0]?.protocol || fallbackProtocol || ''
  ).trim().toLowerCase();
}

function normalizeDeviceRow(row) {
  const metrics = parseJsonColumn(row.metrics, []);
  const config = parseJsonColumn(row.config, {});
  const communicationConfig = parseJsonColumn(row.communication_config, {});
  const role = normalizeDeviceRole(config.role);

  if (role === 'endpoint') {
    communicationConfig.protocol = 'gateway_endpoint';
    if (!communicationConfig.source || typeof communicationConfig.source !== 'object') {
      communicationConfig.source = {};
    }

    const configuredGatewayId = Number(communicationConfig.source.gateway_device_id);
    const legacyGatewayId = Number(config.via_gateway);
    if (!Number.isFinite(configuredGatewayId) && Number.isFinite(legacyGatewayId)) {
      communicationConfig.source.gateway_device_id = legacyGatewayId;
    }
  }

  const fallbackProtocol = row.template_protocol || row.protocol || '';

  return {
    ...row,
    siteId: row.site_id ?? null,
    role,
    protocol: resolveDeviceProtocol(communicationConfig, metrics, config, fallbackProtocol),
    communication_config: communicationConfig,
    config,
    metrics,
  };
}

function isGatewayEndpointDevice(device) {
  return normalizeDeviceRole(device?.role || device?.config?.role) === 'endpoint'
    || String(device?.protocol || '').toLowerCase() === 'gateway_endpoint';
}

module.exports = {
  isGatewayEndpointDevice,
  normalizeDeviceRole,
  normalizeDeviceRow,
  parseJsonColumn,
  resolveDeviceProtocol,
};
