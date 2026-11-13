import { apiFormRequest, apiRequest } from '../../api/client'
import type {
  DeviceDetail,
  DeviceFilters,
  DeviceList,
  DeviceSaveBody,
  GatewayDevice,
  LorawanAppServer,
  LorawanDeviceProfile,
  MetricSourceDevice,
  NotificationChannel,
  TemplateOption,
  TemplatePreview,
} from './types'

export async function fetchDeviceFilters(): Promise<DeviceFilters> {
  try {
    return await apiRequest<DeviceFilters>('/devices/filters')
  } catch {
    return apiRequest<DeviceFilters>('/devices/filter-options')
  }
}

export async function fetchDevices(params: {
  search?: string
  status?: string
  template?: string
  location?: string
  page: number
  pageSize: number
}): Promise<DeviceList> {
  const qs = new URLSearchParams({
    page: String(params.page),
    pageSize: String(params.pageSize),
    length: String(params.pageSize),
  })
  if (params.search) qs.set('search', params.search)
  if (params.status) qs.set('status', params.status)
  if (params.template) qs.set('template', params.template)
  if (params.location) qs.set('location', params.location)
  return apiRequest<DeviceList>(`/devices?${qs.toString()}`)
}

export async function fetchDevice(id: number): Promise<DeviceDetail> {
  return apiRequest<DeviceDetail>(`/devices/${id}`)
}

export async function fetchTemplates(): Promise<TemplateOption[]> {
  return apiRequest<TemplateOption[]>('/device-templates')
}

export async function fetchTemplatePreview(id: number): Promise<TemplatePreview> {
  try {
    return await apiRequest<TemplatePreview>(`/device-templates/${id}/preview`)
  } catch {
    const base = await apiRequest<TemplateOption & Partial<TemplatePreview>>(`/device-templates/${id}`)
    return {
      ...base,
      deviceInformation: base.deviceInformation ?? {},
      communicationConfig: base.communicationConfig ?? {},
      metrics: Array.isArray(base.metrics) ? base.metrics : [],
    }
  }
}

export async function fetchMetricSources(): Promise<MetricSourceDevice[]> {
  try {
    const data = await apiRequest<{ devices: MetricSourceDevice[]; gateways?: GatewayDevice[] } | MetricSourceDevice[]>(
      '/devices/metric-sources',
    )
    if (Array.isArray(data)) return data
    return data.devices ?? []
  } catch {
    return []
  }
}

export async function fetchGatewayDevices(): Promise<GatewayDevice[]> {
  try {
    const data = await apiRequest<{ devices: MetricSourceDevice[]; gateways?: GatewayDevice[] } | MetricSourceDevice[]>(
      '/devices/metric-sources',
    )
    if (Array.isArray(data)) {
      return data.map((d) => ({ id: d.id, name: d.name }))
    }
    if (data.gateways?.length) return data.gateways
    return (data.devices ?? []).map((d) => ({ id: d.id, name: d.name }))
  } catch {
    return []
  }
}

export async function saveDevice(id: number | null, body: DeviceSaveBody): Promise<DeviceDetail> {
  if (id) {
    return apiRequest<DeviceDetail>(`/devices/${id}`, { method: 'PATCH', body })
  }
  return apiRequest<DeviceDetail>('/devices', { method: 'POST', body })
}

export async function uploadDeviceImageFile(file: File): Promise<string> {
  const fd = new FormData()
  fd.append('image', file)
  const uploaded = await apiFormRequest<{ filename: string; path: string; image: string }>(
    '/uploads/devices',
    fd,
  )
  return uploaded.filename || uploaded.image
}

export async function uploadDeviceImage(id: number, file: File): Promise<DeviceDetail> {
  const filename = await uploadDeviceImageFile(file)
  const existing = await fetchDevice(id)
  return apiRequest<DeviceDetail>(`/devices/${id}`, {
    method: 'PATCH',
    body: {
      name: existing.name,
      deviceTemplateId: existing.deviceTemplateId,
      serialNumber: existing.serialNumber,
      location: existing.location,
      description: existing.description,
      isActive: existing.isActive,
      image: filename,
    },
  })
}

export async function deleteDevice(id: number): Promise<void> {
  await apiRequest(`/devices/${id}`, { method: 'DELETE' })
}

export async function fetchNotificationChannels(): Promise<NotificationChannel[]> {
  try {
    const data = await apiRequest<NotificationChannel[] | { channels: NotificationChannel[] }>(
      '/notification-channels',
    )
    if (Array.isArray(data)) return data
    return data.channels ?? []
  } catch {
    return []
  }
}

/** LoRaWAN application servers — maps existing /lorawan/applications when present. */
export async function fetchLorawanAppServers(): Promise<LorawanAppServer[]> {
  try {
    const data = await apiRequest<
      | LorawanAppServer[]
      | Array<{ applicationId?: string; id?: string | number; name?: string; tenantId?: string; tenant_id?: string }>
    >('/lorawan/applications')
    const rows = Array.isArray(data) ? data : []
    return rows.map((row) => {
      const r = row as {
        id?: string | number
        applicationId?: string
        name?: string
        tenantId?: string
        tenant_id?: string
      }
      return {
        id: r.id ?? r.applicationId ?? '',
        name: r.name ?? String(r.id ?? r.applicationId ?? ''),
        tenantId: r.tenantId ?? r.tenant_id,
        applicationId: r.applicationId,
      }
    })
  } catch {
    return []
  }
}

/** Device profiles by tenant — tries wave-16 paths; returns [] if API not ready. */
export async function fetchLorawanDeviceProfiles(
  tenantId: string,
): Promise<LorawanDeviceProfile[]> {
  if (!tenantId) return []
  const paths = [
    `/lorawan/device-profiles?tenantId=${encodeURIComponent(tenantId)}`,
    `/lorawan/applications/device-profiles?tenantId=${encodeURIComponent(tenantId)}`,
  ]
  for (const path of paths) {
    try {
      const data = await apiRequest<
        | LorawanDeviceProfile[]
        | { result?: LorawanDeviceProfile[]; profiles?: LorawanDeviceProfile[] }
      >(path)
      if (Array.isArray(data)) return data
      if (Array.isArray(data.result)) return data.result
      if (Array.isArray(data.profiles)) return data.profiles
    } catch {
      /* try next */
    }
  }
  return []
}

export async function fetchDeviceLatestMetrics(
  id: number,
): Promise<{
  deviceId: number
  latest: Array<{
    key: string
    value: number | null
    valueText: string | null
    valueBool: boolean | null
    valueJson: unknown
    timestamp: string | null
  }>
}> {
  return apiRequest(`/devices/${id}/metrics/latest`)
}

export async function fetchDeviceAlarmLogs(params: {
  deviceId: number
  page: number
  pageSize?: number
  range?: string
  statusFilter?: string
}): Promise<{
  logs: Array<{
    id: number
    createdAt: string | null
    metricKey: string
    severity: string
    message: string
    status: string
  }>
  total: number
  totalPages: number
  page: number
  pageSize: number
}> {
  const qs = new URLSearchParams({
    page: String(params.page),
    pageSize: String(params.pageSize ?? 10),
    range: params.range ?? 'last_30_days',
    device_filter: String(params.deviceId),
    severity_filter: 'all',
    status_filter: params.statusFilter ?? 'all',
    search: '',
  })
  return apiRequest(`/alarm-logs?${qs.toString()}`)
}
