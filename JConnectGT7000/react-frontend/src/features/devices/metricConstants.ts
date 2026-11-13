/** Ported from frontend/assets/js/jconnect-metric-builder.js */

export const UNIT_OPTIONS = [
  '',
  '°C',
  '°F',
  '%',
  'ppm',
  'V',
  'A',
  'W',
  'kW',
  'Wh',
  'kWh',
  'Hz',
  'rpm',
  'bar',
  'psi',
  'Pa',
  'kPa',
  'm3/h',
  'L/min',
  'm/s',
  'lux',
  'dB',
]

export const DATATYPE_OPTIONS: Array<[string, string]> = [
  ['float', 'float'],
  ['int', 'int (= int16 for Modbus)'],
  ['number', 'number'],
  ['bool', 'bool'],
  ['string', 'string'],
  ['json', 'json'],
  ['int16', 'int16'],
  ['uint16', 'uint16'],
  ['int32', 'int32'],
  ['uint32', 'uint32'],
  ['float32', 'float32'],
  ['float32_swap', 'float32_swap'],
  ['float32_fullswap', 'float32_fullswap'],
]

export const PROTOCOL_FIELDS: Record<string, string[]> = {
  modbus_tcp: ['address', 'type', 'function_code', 'byte_order', 'length'],
  modbus_rtu: ['address', 'type', 'function_code', 'byte_order', 'length'],
  modbus_router: ['address', 'type', 'function_code', 'byte_order', 'length'],
  snmp: ['oid'],
  pemon: ['oid'],
  mqtt: ['topic', 'json_path'],
  bacnet: ['object_id', 'property_id'],
  bacnet_ip: ['object_id', 'property_id'],
  bacnet_mstp: ['object_id', 'property_id'],
  bacnet_router: ['object_id', 'property_id'],
  restful: ['url', 'json_path'],
  webhook: ['key_path', 'json_path'],
  lorawan: [],
  gateway_endpoint: ['source_kind', 'channel'],
  visual: [],
  rtsp: [],
}

export const TYPE_OPTIONS: Record<string, Array<[string, string]>> = {
  modbus_tcp: [['modbus_read', 'modbus_read']],
  modbus_rtu: [['modbus_read', 'modbus_read']],
  modbus_router: [['modbus_read', 'modbus_read']],
  snmp: [['snmp_get', 'snmp_get']],
  pemon: [['pemon_read', 'pemon_read']],
  mqtt: [['mqtt_sub', 'mqtt_sub']],
  bacnet: [['bacnet_read', 'bacnet_read']],
  bacnet_ip: [['bacnet_read', 'bacnet_read']],
  bacnet_mstp: [['bacnet_read', 'bacnet_read']],
  bacnet_router: [['bacnet_read', 'bacnet_read']],
  restful: [['rest_get', 'rest_get']],
  webhook: [['rest_webhook', 'rest_webhook']],
  gateway_endpoint: [['gateway_mapped', 'gateway_mapped']],
  visual: [
    ['visual_random_number', 'Random number'],
    ['visual_random_string', 'Random string'],
  ],
  rtsp: [
    ['rtsp_probe', 'Stream status'],
    ['rtsp_snapshot_meta', 'Snapshot meta'],
  ],
}

export const DEFAULT_TYPE: Record<string, string> = {
  modbus_tcp: 'modbus_read',
  modbus_rtu: 'modbus_read',
  modbus_router: 'modbus_read',
  snmp: 'snmp_get',
  pemon: 'pemon_read',
  mqtt: 'mqtt_sub',
  bacnet: 'bacnet_read',
  bacnet_ip: 'bacnet_read',
  bacnet_mstp: 'bacnet_read',
  bacnet_router: 'bacnet_read',
  restful: 'rest_get',
  webhook: 'rest_webhook',
  gateway_endpoint: 'gateway_mapped',
  visual: 'visual_random_number',
  rtsp: 'rtsp_probe',
}

export const STORE_POLICIES = [
  ['always', 'Always'],
  ['on_change', 'On change'],
  ['interval', 'Interval'],
  ['none', 'Do not store'],
] as const

export const TRIGGER_OPS = ['', '==', '!=', '>', '>=', '<', '<=']
export const SEVERITIES = ['info', 'warning', 'minor', 'major', 'critical']

export function emptyMetric(protocol: string): Record<string, unknown> {
  const p = protocol || 'modbus_tcp'
  return {
    key: '',
    label: '',
    unit: '',
    enable: true,
    type: DEFAULT_TYPE[p] || 'modbus_read',
    datatype: 'float',
    pull_rate: 10,
    scale: 1,
    store_policy: 'always',
    description: '',
    protocol: p,
    triggers: [],
    normal_condition: [],
  }
}
