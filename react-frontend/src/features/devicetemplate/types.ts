import type { Metric } from '../devices/types'

export type DcimTaxonomyEntry = {
  category: string
  domain: string
  pue_role: string
  subs: string[]
}

export type TemplateSummary = {
  id: number
  name: string
  description: string
  protocol: string
  categoryId: number | null
  categoryName: string
  isActive: boolean
  image?: string | null
  imageUrl?: string | null
}

export type TemplateDetail = TemplateSummary & {
  communicationConfig: Record<string, unknown>
  deviceInformation: Record<string, unknown>
  metrics: Metric[]
  defaultDashboardLayout: TemplateDashboardWidget[]
  selectedDcimCategory: string
  selectedDcimSubCategory: string
  dcimCategory: string
  dcimSubCategory: string
}

export type TemplateDashboardWidget = {
  title?: string
  metric_key?: string
  unit?: string
  type?: string
  color?: string
  gradient_to_color?: string
  chart_height?: number
  show_legend?: boolean
  min?: number | null
  max?: number | null
  x?: number
  y?: number
  w?: number
  h?: number
  [key: string]: unknown
}

export type TemplateSaveBody = {
  name: string
  description: string
  protocol: string
  dcimCategory: string
  dcimSubCategory: string
  communicationConfig: Record<string, unknown>
  deviceInformation: Record<string, unknown>
  metrics: Metric[]
  defaultDashboardLayout: TemplateDashboardWidget[]
  isActive: boolean
  image?: string | null
}

export const TEMPLATE_PROTOCOLS = [
  'modbus_tcp',
  'modbus_rtu',
  'modbus_router',
  'snmp',
  'mqtt',
  'bacnet_ip',
  'bacnet_mstp',
  'bacnet_router',
  'restful',
  'webhook',
  'lorawan',
  'gateway_endpoint',
  'visual',
  'rtsp',
] as const

/** Match PHP widgets/views/form_addedit.php (no line_chart_dynamic). */
export const PHP_WIDGET_TYPES = [
  { value: 'value_box', label: 'Value Box' },
  { value: 'gauge', label: 'Gauge' },
  { value: 'level_bar', label: 'Level Bar' },
  { value: 'navigator', label: 'Navigator' },
  { value: 'line_chart', label: 'Line Chart' },
  { value: 'bar_chart', label: 'Bar Chart' },
  { value: 'table', label: 'Table' },
  { value: 'status_grid', label: 'Status Grid' },
  { value: 'alarm_summary', label: 'Alarm Summary' },
  { value: 'ups_panel', label: 'UPS Panel' },
  { value: 'power_meter', label: 'Power Meter' },
  { value: 'heatmap', label: 'Temperature Heatmap' },
  { value: 'cooling_panel', label: 'Cooling Panel' },
  { value: 'zone_overview', label: 'Zone Overview Card' },
  { value: 'map_view', label: 'Map view' },
  { value: 'svg_widget', label: 'SVG Widget' },
  { value: 'ratio', label: 'Ratio' },
  { value: 'temp_hum_sensor', label: 'Temperature and Humidity' },
  { value: 'value_box2', label: 'Value Box 2 Value' },
  { value: 'value_box3', label: 'Value Box 3 Value' },
  { value: 'status_box', label: 'Status Box' },
  { value: 'status_box2', label: 'Status Box 2' },
  { value: 'status_box3', label: 'Status Box 3' },
  { value: 'point', label: 'Point' },
  { value: 'camera_stream', label: 'Camera Stream (RTSP/HLS)' },
] as const

export const DASHBOARD_WIDGET_TYPES = [
  { value: 'value_box', labelKey: 'value_box' },
  { value: 'gauge', labelKey: 'gauge' },
  { value: 'gradient_radial_bar', label: 'Gradient Radial Bar' },
  { value: 'line_chart', labelKey: 'line_chart' },
  { value: 'area_chart', labelKey: 'area_chart' },
  { value: 'bar_chart', labelKey: 'bar_chart' },
  { value: 'donut_chart', labelKey: 'donut_chart' },
] as const

export const DEFAULT_DEVICE_INFO: Record<string, string> = {
  Brand: '',
  Model: '',
  Manufacturer: '',
  'Serial Number': '',
  'Firmware Version': '',
  'Hardware Version': '',
}
