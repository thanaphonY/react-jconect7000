/** Communication field keys by protocol — from PHP form_add/form_edit commFields. */
export const COMM_FIELDS: Record<string, string[]> = {
  modbus_tcp: ['ip', 'port', 'unit_id'],
  modbus_rtu: ['baudrate', 'parity', 'stop_bits', 'data_bits', 'slave_id', 'port'],
  modbus_router: ['ip', 'port', 'unit_id'],
  pemon: [
    'host', 'username', 'password', 'http_port', 'pull_rate', 'detail_pull_rate',
    'timeout', 'snmp_enable', 'snmp_community', 'snmp_version', 'snmp_port',
    'skip_on_comm_fail', 'config_pull_rate',
  ],
  snmp: [
    'version',
    'community',
    'host',
    'port',
    'max_oids_per_request',
    'batch_delay_ms',
    'security_level',
    'auth_protocol',
    'auth_pass',
    'priv_protocol',
    'priv_pass',
  ],
  mqtt: ['broker_url', 'port', 'username', 'password', 'client_id', 'qos', 'use_tls'],
  bacnet: ['device_id', 'ip', 'port'],
  bacnet_ip: ['device_id', 'ip', 'port'],
  bacnet_mstp: ['device_id', 'ip', 'port'],
  bacnet_router: ['device_id', 'ip', 'port'],
  lorawan: [
    'application_server',
    'device_profile_id',
    'dev_eui',
    'app_eui',
    'app_key',
    'netskey',
    'appskey',
  ],
  rtsp: ['ip', 'port', 'rtsp_port', 'rtsp_url', 'snapshot_url', 'transport', 'username', 'password'],
  restful: ['connection_string', 'timeout'],
  webhook: ['connection_string', 'timeout'],
  visual: [],
  gateway_endpoint: [],
}

export const SNMP_AUTH_PROTOCOLS = ['MD5', 'SHA', 'SHA-224', 'SHA-256', 'SHA-384', 'SHA-512']
export const SNMP_PRIV_PROTOCOLS = ['DES', 'AES', 'AES-192', 'AES-256']
export const SNMP_SECURITY_LEVELS = ['noAuthNoPriv', 'authNoPriv', 'authPriv']

export const RTSP_PLACEHOLDERS: Record<string, string> = {
  ip: '172.16.180.42',
  port: '80',
  rtsp_port: '554',
  rtsp_url: '/Streaming/Channels/102',
  snapshot_url: '/ISAPI/Streaming/channels/101/picture',
}

export function normalizeCommProtocol(protocol: string | null | undefined): string {
  const p = String(protocol || 'modbus_tcp').toLowerCase()
  if (p.startsWith('bacnet')) return p === 'bacnet' ? 'bacnet' : p
  return p
}

export function fieldLabel(field: string): string {
  const special: Record<string, string> = {
    max_oids_per_request: 'Max OIDs Per Request',
    batch_delay_ms: 'Batch Delay (ms)',
    rtsp_url: 'RTSP Path',
    snapshot_url: 'Snapshot Path',
    port: field === 'port' ? 'Port' : 'Port',
    ip: 'IP Address',
    unit_id: 'Unit ID',
    slave_id: 'Slave ID',
    broker_url: 'Broker URL',
    use_tls: 'Use TLS',
    qos: 'QoS',
    client_id: 'Client ID',
    device_id: 'Device ID',
    dev_eui: 'Dev EUI',
    app_eui: 'App EUI',
    app_key: 'App Key',
    netskey: 'NwkSKey',
    appskey: 'AppSKey',
    application_server: 'Application Server',
    device_profile_id: 'Device Profile ID',
    rtsp_port: 'RTSP Port',
    auth_pass: 'Auth Password',
    priv_pass: 'Priv Password',
    auth_protocol: 'Auth Protocol',
    priv_protocol: 'Priv Protocol',
    security_level: 'Security Level',
    connection_string: 'Connection String',
  }
  if (special[field]) return special[field]
  return field.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}
