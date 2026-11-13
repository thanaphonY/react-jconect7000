export type LayoutSummary = {
  id: number
  name: string
  description: string
  backgroundImage?: string | null
  sortOrder: number | null
  createdBy?: number | null
  creator?: string
  isHome?: boolean
}

export type LayoutList = {
  layouts: LayoutSummary[]
  homeLayoutId: number | null
}

export type LayoutItem = {
  id?: number
  widgetId: number | null
  widget_id?: number | null
  widgetType: string
  type?: string
  name?: string
  label?: string
  x: number
  y: number
  w: number
  h: number
  config: Record<string, unknown>
}

export type LayoutDetail = {
  layout: LayoutSummary
  items: LayoutItem[]
}

export type LayoutView = {
  layout: LayoutSummary
  layouts: LayoutSummary[]
  items: LayoutItem[]
  homeLayoutId: number | null
}

export type CatalogWidget = {
  id: number
  name: string
  widgetType: string
  config: Record<string, unknown>
}

export type DeviceOption = {
  id: number
  name: string
}

export type MetricOption = {
  key: string
  label?: string
  unit?: string
  [key: string]: unknown
}

export type GridWidgetState = {
  uid: string
  widgetId: number | null
  widgetType: string
  x: number
  y: number
  w: number
  h: number
  config: Record<string, unknown>
  label: string
}

export type FieldOption = {
  label: string
  value: string
  default?: boolean
}

export type WidgetField = {
  key?: string
  label?: string
  type?: string
  class?: string
  options?: FieldOption[]
  default?: string
  value?: number | string
  min?: number
  max?: number
}

export type LayoutSaveItem = {
  widget_id: number | null
  widget_type: string
  x: number
  y: number
  w: number
  h: number
  config: Record<string, unknown>
}
