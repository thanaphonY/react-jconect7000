export type DeviceRole = 'native_device' | 'gateway' | 'endpoint'

export type DeviceSummary = {
  id: number
  name: string
  serialNumber: string
  location: string
  description: string
  isActive: boolean
  deviceTemplateId: number
  templateName: string
  brand: string
  model: string
  image?: string | null
  templateImage?: string | null
  imageUrl?: string | null
  customFields?: Record<string, string> | null
  protocol?: string
  categoryName?: string
}

export type DeviceList = {
  devices: DeviceSummary[]
  total: number
  page: number
  pageSize: number
}

export type DeviceFilters = {
  locations: string[]
  templates: string[]
  categories?: string[]
}

export type DeviceDetail = DeviceSummary & {
  siteNodeId?: number | null
  communicationConfig: Record<string, unknown>
  metrics: Metric[]
  config: DeviceConfig
  categoryId: number | null
  snapshotTriggers?: SnapshotTrigger[]
  deviceRole?: DeviceRole
  gatewayDeviceId?: number | null
}

export type DeviceConfig = {
  role?: DeviceRole
  via_gateway?: number | null
  custom_fields?: Record<string, string>
  snapshot_triggers?: SnapshotTrigger[]
  [key: string]: unknown
}

export type TemplateOption = {
  id: number
  name: string
  description?: string
  protocol: string
  categoryId?: number | null
  categoryName?: string
  isActive?: boolean
  image?: string | null
  imageUrl?: string | null
  brand?: string
  model?: string
  deviceInformation?: Record<string, unknown>
  communicationConfig?: Record<string, unknown>
  metrics?: Metric[]
}

export type TemplatePreview = TemplateOption & {
  deviceInformation: Record<string, unknown>
  communicationConfig: Record<string, unknown>
  metrics: Metric[]
}

export type Metric = {
  key?: string
  label?: string
  unit?: string
  enable?: boolean
  type?: string
  datatype?: string
  pull_rate?: number | string
  scale?: number | string
  store_policy?: string
  interval_minutes?: number | string
  description?: string
  protocol?: string
  address?: number | string
  function_code?: number | string
  byte_order?: string
  length?: number | string
  oid?: string
  topic?: string
  json_path?: string
  object_id?: string | number
  property_id?: string | number
  url?: string
  key_path?: string
  source_kind?: string
  channel?: string
  source?: { gateway_metric_key?: string; gateway_device_id?: number }
  visual?: Record<string, unknown>
  calc?: Record<string, unknown>
  triggers?: MetricTrigger[]
  normal_condition?: NormalCondition | NormalCondition[]
  [key: string]: unknown
}

export type MetricTrigger = {
  enable?: boolean
  operator?: string
  value?: string | number
  message?: string
  severity?: string
  repeat_interval?: number | string
  clear_condition?: { operator?: string; value?: string | number; message?: string }
  duration?: Array<{ days?: number[]; start?: string; end?: string }>
  channels?: Array<string | number>
  [key: string]: unknown
}

export type NormalCondition = {
  operator?: string
  value?: string | number
  message?: string
  channels?: Array<string | number>
}

export type SnapshotTrigger = {
  enable?: boolean
  source_device_id?: number | string | null
  source_metric_key?: string
  source_trigger_index?: number | string | null
  cooldown_sec?: number | string
}

export type MetricSourceDevice = {
  id: number
  name: string
  metrics?: Array<{ key: string; label?: string; triggers?: MetricTrigger[] }>
}

export type GatewayDevice = {
  id: number
  name: string
}

export type NotificationChannel = {
  id: number
  name: string
  type?: string
  isActive?: boolean
  description?: string
}

export type CalcInput = {
  var?: string
  source?: 'device' | 'random' | string
  deviceId?: number | string
  metricKey?: string
  min?: number | string
  max?: number | string
}

export type LorawanAppServer = {
  id: string | number
  name: string
  tenantId?: string
  applicationId?: string
}

export type LorawanDeviceProfile = {
  id: string
  name: string
}

export type DeviceSaveBody = {
  name: string
  deviceTemplateId: number
  serialNumber: string
  location: string
  description: string
  isActive: boolean
  deviceRole?: DeviceRole
  gatewayDeviceId?: number | null
  communicationConfig?: Record<string, unknown>
  metrics?: Metric[]
  customFields?: Record<string, string>
  snapshotTriggers?: SnapshotTrigger[]
  image?: string | null
}
