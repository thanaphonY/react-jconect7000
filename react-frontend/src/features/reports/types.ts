export type DeviceMetricPair = {
  device_id: number
  device_name?: string
  metric: string
  metric_label?: string
}

export type DisplayColumn = {
  key: string
  label: string
}

export type ReportTemplateConfig = {
  builder_version?: number
  data_source?: string
  filters?: {
    time_range?: string
    time_interval?: string
    display?: string[]
    sort_order?: string
    selected_device_metrics?: DeviceMetricPair[]
    action_type?: boolean
    entity_type?: boolean
    entity?: boolean
  }
  export_inputs?: Array<{ type: string; label?: string; required?: boolean }>
  editor?: {
    report_title?: string
    table_layout?: string
    pdf_orientation?: string
    pdf_title?: string
    excel_sheet_name?: string
    csv_delimiter?: string
    display_columns?: DisplayColumn[]
  }
  template_type?: string
}

export type ReportTemplate = {
  id: number
  name: string
  description: string
  templateType: string
  config: ReportTemplateConfig
  updatedAt: string | null
  createdAt: string | null
}

export type ReportSchedule = {
  id: number
  templateId: number
  templateName: string
  name: string
  isActive: boolean
  scheduleType: string
  scheduleConfig: { time?: string; weekday?: number; day?: number }
  timezone: string
  exportFormat: string
  recipients: string[]
  nextRunAt: string | null
  lastRunAt: string | null
}

export const DEFAULT_DISPLAY_COLUMNS: DisplayColumn[] = [
  { key: 'timestamp', label: 'Time' },
  { key: 'device_name', label: 'Device Name' },
  { key: 'metric_label', label: 'Metric Name' },
  { key: 'device_id', label: 'Device ID' },
  { key: 'key', label: 'Metric (key)' },
  { key: 'value', label: 'Value' },
  { key: 'value_min', label: 'Min' },
  { key: 'value_max', label: 'Max' },
  { key: 'value_avg', label: 'Average' },
  { key: 'value_first', label: 'First' },
  { key: 'value_last', label: 'Last' },
]

export const TIME_RANGE_OPTIONS = [
  { value: 'input_date_range', label: 'Use Input date range (Date Range at Export)' },
  { value: 'last_6h', label: 'Last 6 Hours' },
  { value: 'last_12h', label: 'Last 12 Hours' },
  { value: 'last_24h', label: 'Last 24 Hours' },
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'last_3d', label: 'Last 3 Days' },
  { value: 'last_7d', label: 'Last 7 Days' },
  { value: 'this_week', label: 'This Week' },
  { value: 'last_week', label: 'Last Week' },
  { value: 'this_month', label: 'This Month' },
  { value: 'last_month', label: 'Last Month' },
  { value: 'last_3m', label: 'Last 3 Months' },
]

export const TIME_INTERVAL_OPTIONS = [
  { value: '1m', label: '1 Minute' },
  { value: '5m', label: '5 Minutes' },
  { value: '10m', label: '10 Minutes' },
  { value: '15m', label: '15 Minutes' },
  { value: '30m', label: '30 Minutes' },
  { value: '1h', label: '1 Hour' },
  { value: '4h', label: '4 Hours' },
  { value: '12h', label: '12 Hours' },
  { value: '24h', label: '24 Hours' },
  { value: '1d', label: '1 Day' },
]

export const DISPLAY_OPTIONS = [
  { value: 'min', label: 'Min' },
  { value: 'max', label: 'Max' },
  { value: 'average', label: 'Average' },
  { value: 'first', label: 'First' },
  { value: 'last', label: 'Last' },
]
