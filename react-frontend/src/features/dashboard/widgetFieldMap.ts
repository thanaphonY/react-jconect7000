import { WIDGET_ICON_OPTIONS } from './icons'
import type { WidgetField } from './types'

const SHOW_BORDER_FIELD: WidgetField = {
  key: 'show_border',
  label: 'Show Border',
  type: 'select',
  class: 'col-sm-6',
  options: [
    { label: 'Show', value: '1', default: true },
    { label: 'Hide', value: '0' },
  ],
}

const WIDGET_BG_COLOR_FIELD: WidgetField = {
  key: 'bg_color',
  label: 'Background Color',
  type: 'widget_color',
  class: 'col-sm-6',
}

const WIDGET_BG_OPACITY_FIELD: WidgetField = {
  key: 'bg_opacity',
  label: 'Background Opacity (%)',
  type: 'number',
  class: 'col-sm-6',
  value: 100,
  min: 0,
  max: 100,
}

function withShowBorder(fields: WidgetField[]): WidgetField[] {
  if (fields.some((f) => f?.key === 'show_border')) return fields
  return fields.concat(SHOW_BORDER_FIELD)
}

function withWidgetAppearance(fields: WidgetField[]): WidgetField[] {
  let next = withShowBorder(fields)
  if (!next.some((f) => f?.key === 'bg_color')) next = next.concat(WIDGET_BG_COLOR_FIELD)
  if (!next.some((f) => f?.key === 'bg_opacity')) next = next.concat(WIDGET_BG_OPACITY_FIELD)
  return next
}

/** Ported from PHP edit_layout.php WidgetFieldMap (before appearance merge). */
const RAW_WIDGET_FIELD_MAP: Record<string, WidgetField[]> = {
  value_box: [
    { key: 'label', label: 'Label', type: 'text', class: 'col-sm-12' },
    { key: 'device_id', label: 'Device ID', type: 'select' },
    { key: 'metric_key', label: 'Metric Key', type: 'select' },
    { key: 'unit', label: 'Unit', type: 'text' },
    {
      key: 'show_icon',
      label: 'Show Icon',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: 'Hide', value: '0', default: true },
        { label: 'Show (left side)', value: '1' },
      ],
    },
    {
      key: 'icon',
      label: 'Icon',
      type: 'icon_select',
      class: 'col-sm-6',
      default: 'bi-circle',
      options: WIDGET_ICON_OPTIONS,
    },
    { key: 'link_url', label: 'Click URL', type: 'text', class: 'col-sm-12' },
    { key: 'value_maps', label: 'Value Maps', type: 'value_maps', class: 'col-sm-12' },
  ],
  temp_hum_sensor: [
    { key: 'label', label: 'Label', type: 'text' },
    { key: 'device_id', label: 'Device', type: 'select' },
    {},
    { key: 'temp_key', label: 'Temperure Metric', type: 'select' },
    { key: 'hum_key', label: 'Humidity Metric', type: 'select' },
  ],
  ratio: [
    { key: 'label', label: 'Label', type: 'text', class: 'col-sm-12' },
    {},
    { key: 'label1', label: 'Label 1', type: 'text' },
    { key: 'device_id1', label: 'Device ID 1', type: 'select' },
    { key: 'metric_key1', label: 'Metric Key 1', type: 'select' },
    { key: 'unit1', label: 'Unit1', type: 'text' },
    {},
    { key: 'label2', label: 'Label 2', type: 'text' },
    { key: 'device_id2', label: 'Device ID 2', type: 'select' },
    { key: 'metric_key2', label: 'Metric Key 2', type: 'select' },
    { key: 'unit2', label: 'Unit2', type: 'text' },
  ],
  gauge: [
    { key: 'label', label: 'Label', type: 'text' },
    { key: 'device_id', label: 'Device ID', type: 'select' },
    { key: 'metric_key', label: 'Metric Key', type: 'select' },
    { key: 'unit', label: 'Unit', type: 'text' },
    { key: 'min', label: 'Min', type: 'number' },
    { key: 'max', label: 'Max', type: 'number' },
  ],
  level_bar: [
    { key: 'label', label: 'Label', type: 'text' },
    { key: 'device_id', label: 'Device ID', type: 'select' },
    { key: 'metric_key', label: 'Metric Key', type: 'select' },
    { key: 'unit', label: 'Unit', type: 'text' },
    { key: 'min', label: 'Min', type: 'number' },
    { key: 'max', label: 'Max', type: 'number' },
    {
      key: 'orientation',
      label: 'Orientation',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: 'Vertical', value: 'vertical', default: true },
        { label: 'Horizontal', value: 'horizontal' },
      ],
    },
  ],
  camera_stream: [
    { key: 'label', label: 'Label', type: 'text', class: 'col-sm-12' },
    { key: 'device_id', label: 'Camera Device', type: 'select' },
  ],
  line_chart: [
    { key: 'label', label: 'Chart Label', type: 'text', class: 'col-sm-12' },
    {
      key: 'show_border',
      label: 'Show Border',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: 'Show', value: '1', default: true },
        { label: 'Hide', value: '0' },
      ],
    },
    { key: 'bg_color', label: 'Background Color', type: 'widget_color', class: 'col-sm-6' },
    {
      key: 'bg_opacity',
      label: 'Background Opacity (%)',
      type: 'number',
      class: 'col-sm-6',
      value: 100,
      min: 0,
      max: 100,
    },
    { key: 'yTitle', label: 'Y-Axis Title', type: 'text', class: 'col-sm-6' },
    { key: 'yUnit', label: 'Y-Axis Unit', type: 'text', class: 'col-sm-6' },
    { key: 'yMin', label: 'Y-Axis Min', type: 'number', class: 'col-sm-6' },
    { key: 'yMax', label: 'Y-Axis Max', type: 'number', class: 'col-sm-6' },
    { key: 'xTitle', label: 'X-Axis Title', type: 'text', class: 'col-sm-6' },
  ],
  bar_chart: [
    { key: 'label', label: 'Chart Label', type: 'text', class: 'col-sm-12' },
    {
      key: 'show_border',
      label: 'Show Border',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: 'Show', value: '1', default: true },
        { label: 'Hide', value: '0' },
      ],
    },
    { key: 'bg_color', label: 'Background Color', type: 'widget_color', class: 'col-sm-6' },
    {
      key: 'bg_opacity',
      label: 'Background Opacity (%)',
      type: 'number',
      class: 'col-sm-6',
      value: 100,
      min: 0,
      max: 100,
    },
    { key: 'yTitle', label: 'Y-Axis Title', type: 'text', class: 'col-sm-6' },
    { key: 'yUnit', label: 'Y-Axis Unit', type: 'text', class: 'col-sm-6' },
    { key: 'yMin', label: 'Y-Axis Min', type: 'number', class: 'col-sm-6' },
    { key: 'yMax', label: 'Y-Axis Max', type: 'number', class: 'col-sm-6' },
    {
      key: 'sort_order',
      label: 'Sort Order',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: 'Descending', value: 'desc', default: true },
        { label: 'Ascending', value: 'asc' },
        { label: 'As Added', value: 'none' },
      ],
    },
    {
      key: 'orientation',
      label: 'Orientation',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: 'Horizontal', value: 'horizontal', default: true },
        { label: 'Vertical', value: 'vertical' },
      ],
    },
    { key: 'limit', label: 'Max Bars', type: 'number', class: 'col-sm-6' },
    { key: 'setpoint_value', label: 'Set Point', type: 'number', class: 'col-sm-6' },
    { key: 'setpoint_color', label: 'Set Point Line Color', type: 'widget_color', class: 'col-sm-6' },
    { key: 'bar_color_over', label: 'Over Set Point Color', type: 'widget_color', class: 'col-sm-6' },
    { key: 'bar_color_under', label: 'Under Set Point Color', type: 'widget_color', class: 'col-sm-6' },
  ],
  line_chart_dynamic: [
    { key: 'label', label: 'Chart Label', type: 'text', class: 'col-sm-12' },
    { key: 'yTitle', label: 'Y-Axis Title', type: 'text', class: 'col-sm-6' },
    { key: 'yUnit', label: 'Y-Axis Unit', type: 'text', class: 'col-sm-6' },
    { key: 'yMin', label: 'Y-Axis Min', type: 'number', class: 'col-sm-6' },
    { key: 'yMax', label: 'Y-Axis Max', type: 'number', class: 'col-sm-6' },
    { key: 'xTitle', label: 'X-Axis Title', type: 'text', class: 'col-sm-6' },
    {},
    {
      key: 'time_range',
      label: 'Time Range',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: 'Last 6 Hours', value: '6h' },
        { label: 'Last 12 Hours', value: '12h' },
        { label: 'Last 24 Hours', value: '24h', default: true },
        { label: 'Last 7 Days', value: '7d' },
        { label: 'Last 30 Days', value: '30d' },
      ],
    },
    {
      key: 'time_interval',
      label: 'Time Interval',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: '1 Minute', value: '1m' },
        { label: '5 Minutes', value: '5m' },
        { label: '10 Minutes', value: '10m' },
        { label: '15 Minutes', value: '15m' },
        { label: '30 Minutes', value: '30m' },
        { label: '1 Hour', value: '1h', default: true },
        { label: '4 Hours', value: '4h' },
        { label: '12 Hours', value: '12h' },
        { label: '24 Hours', value: '24h' },
        { label: '1 Day', value: '1d' },
      ],
    },
    { key: 'limit', label: 'Limit Data Point', type: 'number', class: 'col-sm-6', value: 24 },
    {
      key: 'display',
      label: 'Display',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: 'Min', value: 'min' },
        { label: 'Max', value: 'max' },
        { label: 'Average', value: 'avg', default: true },
        { label: 'First', value: 'first' },
        { label: 'Last', value: 'last' },
      ],
    },
    { key: 'chart_height_pct', label: 'Chart Height (%)', type: 'number', class: 'col-sm-6', value: 60 },
  ],
  table: [{ key: 'label', label: 'Table Label', type: 'text', class: 'col-sm-12' }],
  status_grid: [
    { key: 'label', label: 'Grid Label', type: 'text', class: 'col-sm-12' },
    { key: 'link_url', label: 'Click URL', type: 'text', class: 'col-sm-12' },
  ],
  navigator: [{ key: 'label', label: 'Title', type: 'text', class: 'col-sm-12' }],
  alarm_summary: [
    { key: 'label', label: 'Title', type: 'text', class: 'col-sm-12' },
    {
      key: 'show_total',
      label: 'Show Total',
      type: 'select',
      class: 'col-sm-6',
      options: [
        { label: 'Show', value: '1', default: true },
        { label: 'Hide', value: '0' },
      ],
    },
  ],
  ups_panel: [
    { key: 'label', label: 'Panel Title', type: 'text', class: 'col-sm-12' },
    { key: 'device_id', label: 'UPS Device', type: 'select' },
    { key: 'capacity_key', label: 'Battery Capacity Key', type: 'text', class: 'col-sm-6' },
    { key: 'voltage_key', label: 'Battery Voltage Key', type: 'text', class: 'col-sm-6' },
    { key: 'load_r_key', label: 'Load R Key', type: 'text', class: 'col-sm-4' },
    { key: 'load_s_key', label: 'Load S Key', type: 'text', class: 'col-sm-4' },
    { key: 'load_t_key', label: 'Load T Key', type: 'text', class: 'col-sm-4' },
    { key: 'mode_key', label: 'Output Mode Key', type: 'text', class: 'col-sm-6' },
    { key: 'runtime_key', label: 'Runtime Key', type: 'text', class: 'col-sm-6' },
    { key: 'connection_key', label: 'Connection Key', type: 'text', class: 'col-sm-6' },
  ],
  power_meter: [
    { key: 'label', label: 'Meter Title', type: 'text', class: 'col-sm-12' },
    { key: 'device_id', label: 'Device', type: 'select' },
    { key: 'power_key', label: 'Active Power Key', type: 'text', class: 'col-sm-6' },
    { key: 'connection_key', label: 'Connection Key', type: 'text', class: 'col-sm-6' },
    { key: 'voltage_r_key', label: 'Voltage R Key', type: 'text', class: 'col-sm-4' },
    { key: 'voltage_s_key', label: 'Voltage S Key', type: 'text', class: 'col-sm-4' },
    { key: 'voltage_t_key', label: 'Voltage T Key', type: 'text', class: 'col-sm-4' },
    { key: 'current_r_key', label: 'Current R Key', type: 'text', class: 'col-sm-4' },
    { key: 'current_s_key', label: 'Current S Key', type: 'text', class: 'col-sm-4' },
    { key: 'current_t_key', label: 'Current T Key', type: 'text', class: 'col-sm-4' },
  ],
  heatmap: [{ key: 'label', label: 'Heatmap Title', type: 'text', class: 'col-sm-12' }],
  cooling_panel: [
    { key: 'label', label: 'Panel Title', type: 'text', class: 'col-sm-12' },
    { key: 'device_id', label: 'Cooling Device', type: 'select' },
    { key: 'connection_key', label: 'Connection Key', type: 'text', class: 'col-sm-6' },
    { key: 'running_key', label: 'Running Status Key', type: 'text', class: 'col-sm-6' },
    { key: 'alarm_key', label: 'Alarm Key', type: 'text', class: 'col-sm-6' },
    { key: 'running_on', label: 'Running Label', type: 'text', class: 'col-sm-6' },
    { key: 'running_off', label: 'Stopped Label', type: 'text', class: 'col-sm-6' },
    { key: 'alarm_on', label: 'Alarm Label', type: 'text', class: 'col-sm-6' },
    { key: 'alarm_off', label: 'Normal Label', type: 'text', class: 'col-sm-6' },
    { key: 'supply_temp_key', label: 'Supply Temp Key', type: 'text', class: 'col-sm-4' },
    { key: 'return_temp_key', label: 'Return Temp Key', type: 'text', class: 'col-sm-4' },
    { key: 'setpoint_key', label: 'Setpoint Key', type: 'text', class: 'col-sm-4' },
  ],
  zone_overview: [
    { key: 'zone_name', label: 'Card Name (Building/Room)', type: 'text', class: 'col-sm-12' },
    { key: 'zone_subtitle', label: 'Subtitle / Footer', type: 'text', class: 'col-sm-12' },
    { key: 'link_url', label: 'Click URL (link to dashboard)', type: 'text', class: 'col-sm-12' },
    { key: 'value_maps', label: 'Value Maps', type: 'value_maps', class: 'col-sm-12' },
  ],
  svg_widget: [
    { key: 'label', label: 'Label', type: 'text', class: 'col-sm-12' },
    { key: 'svg_file', label: 'SVG File', type: 'hidden', class: 'd-none' },
  ],
  map_view: [{ key: 'background', label: 'Background Image Path', type: 'hidden', class: 'd-none' }],
  value_box2: [
    { key: 'label', label: 'Label', type: 'text', class: 'col-sm-6' },
    { key: 'spector', label: 'Spector', type: 'text', class: 'col-sm-6' },
    {},
    { key: 'label1', label: 'Label 1', type: 'text' },
    { key: 'device_id1', label: 'Device ID 1', type: 'select' },
    { key: 'metric_key1', label: 'Metric Key 1', type: 'select' },
    { key: 'unit1', label: 'Unit1', type: 'text' },
    {},
    { key: 'label2', label: 'Label 2', type: 'text' },
    { key: 'device_id2', label: 'Device ID 2', type: 'select' },
    { key: 'metric_key2', label: 'Metric Key 2', type: 'select' },
    { key: 'unit2', label: 'Unit2', type: 'text' },
  ],
  value_box3: [
    { key: 'label', label: 'Label', type: 'text', class: 'col-sm-6' },
    { key: 'spector', label: 'Spector', type: 'text', class: 'col-sm-6' },
    { key: 'label1', label: 'Label 1', type: 'text' },
    { key: 'device_id1', label: 'Device ID 1', type: 'select' },
    { key: 'metric_key1', label: 'Metric Key 1', type: 'select' },
    { key: 'unit1', label: 'Unit1', type: 'text' },
    {},
    { key: 'label2', label: 'Label 2', type: 'text' },
    { key: 'device_id2', label: 'Device ID 2', type: 'select' },
    { key: 'metric_key2', label: 'Metric Key 2', type: 'select' },
    { key: 'unit2', label: 'Unit2', type: 'text' },
    {},
    { key: 'label3', label: 'Label 3', type: 'text' },
    { key: 'device_id3', label: 'Device ID 3', type: 'select' },
    { key: 'metric_key3', label: 'Metric Key 3', type: 'select' },
    { key: 'unit3', label: 'Unit3', type: 'text' },
  ],
}

export const WidgetFieldMap: Record<string, WidgetField[]> = Object.fromEntries(
  Object.entries(RAW_WIDGET_FIELD_MAP).map(([type, fields]) => [type, withWidgetAppearance(fields)]),
)

export function fieldsForWidgetType(widgetType: string): WidgetField[] {
  const raw = RAW_WIDGET_FIELD_MAP[widgetType]
  if (raw) return (WidgetFieldMap[widgetType] || []).filter((f) => Boolean(f.key))
  // Catalog types without a PHP WidgetFieldMap entry still get shared appearance fields.
  return withWidgetAppearance([]).filter((f) => Boolean(f.key))
}

export function defaultFieldValue(field: WidgetField, config: Record<string, unknown>): string {
  const key = field.key
  if (!key) return ''
  const existing = config[key]
  if (existing !== undefined && existing !== null && typeof existing !== 'object') {
    return String(existing)
  }
  if (field.default !== undefined) return String(field.default)
  if (field.value !== undefined) return String(field.value)
  const optDefault = field.options?.find((o) => o.default)
  if (optDefault) return optDefault.value
  return ''
}

export const DEFAULT_WIDGET_SIZES: Record<string, [number, number]> = {
  heatmap: [8, 4],
  bar_chart: [8, 4],
  zone_overview: [4, 3],
  status_grid: [6, 3],
  ups_panel: [4, 4],
  power_meter: [4, 3],
  cooling_panel: [4, 3],
  alarm_summary: [3, 2],
  navigator: [8, 2],
}

export function defaultSizeFor(
  widgetType: string,
  config: Record<string, unknown>,
): [number, number] {
  if (widgetType === 'level_bar' && config.orientation === 'horizontal') return [4, 2]
  if (widgetType === 'level_bar') return [2, 4]
  return DEFAULT_WIDGET_SIZES[widgetType] || [3, 2]
}

export function mapImageUrl(background: string | null | undefined): string {
  if (!background) return ''
  if (background.startsWith('http') || background.startsWith('/api/')) return background
  const name = background.replace(/^.*\//, '')
  return `/api/v1/uploads/maps/${encodeURIComponent(name)}`
}

export function svgWidgetUrl(svgFile: string | null | undefined): string {
  if (!svgFile) return ''
  if (svgFile.startsWith('http') || svgFile.startsWith('/api/')) return svgFile
  const name = svgFile.replace(/^.*\//, '')
  return `/api/v1/uploads/mimics/${encodeURIComponent(name)}`
}
