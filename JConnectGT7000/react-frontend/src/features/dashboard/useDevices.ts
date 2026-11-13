import { useQuery } from '@tanstack/react-query'
import { apiRequest } from '../../api/client'
import type { DeviceOption, MetricOption } from './types'

type DeviceList = {
  devices: DeviceOption[]
  total: number
}

type MetricsPayload = {
  deviceId: number
  name: string
  metrics: MetricOption[]
}

export function useDeviceOptions() {
  return useQuery({
    queryKey: ['devices', 'dashboard-editor'],
    queryFn: async () => {
      const data = await apiRequest<DeviceList>('/devices?page=1&pageSize=100')
      return data.devices
    },
    staleTime: 60_000,
  })
}

export function useDeviceMetrics(deviceId: string | number | null | undefined) {
  const id = deviceId ? Number(deviceId) : 0
  return useQuery({
    queryKey: ['device-metrics', id],
    enabled: Number.isInteger(id) && id > 0,
    queryFn: async () => {
      const data = await apiRequest<MetricsPayload>(`/devices/${id}/metrics`)
      return Array.isArray(data.metrics) ? data.metrics : []
    },
  })
}

export function metricLabel(m: MetricOption): string {
  return String(m.label || m.key || '')
}
