import { apiRequest } from '../../api/client'

export type ControlEngine = { ok: boolean; message: string }

export type ControlGroup = {
  id: number
  name: string
  kind?: string
  description?: string
  command_count?: number
}

export type ControlDevice = { id: number; name: string }

export type ControlCommand = {
  id: number
  name: string
  description?: string
  device_id: number
  device_name?: string
  protocol?: string
  write_config?: Record<string, unknown>
  feedback_metric_key?: string | null
  enabled?: boolean
  group_id?: number | null
  hold_source?: string | null
}

export type ControlSchedule = {
  id: number
  name: string
  command_id: number
  command_name?: string
  device_name?: string
  cron?: string
  action?: string
  enabled?: boolean
  last_run_at?: string | null
}

export type ControlSequenceStep = {
  command_id: number
  action?: string
  wait?: string
  timeout_sec?: number
  delay_sec?: number
  delay_unit?: string
}

export type ControlSequence = {
  id: number
  name: string
  steps?: ControlSequenceStep[] | string
  enabled?: boolean
  group_id?: number | null
  loop?: boolean
  loop_enabled?: boolean
  loop_count?: number
  loop_delay_sec?: number
  running?: boolean
  paused?: boolean
  last_run_at?: string | null
  last_result?: string | null
}

export type ControlRule = {
  id: number
  name: string
  conditions?: Record<string, unknown> | string
  action?: Record<string, unknown> | string
  enabled?: boolean
  group_id?: number | null
  cooldown_sec?: number
  last_triggered_at?: string | null
  last_fired_at?: string | null
  last_result?: string | null
}

export type ControlLog = {
  id?: number
  created_at?: string
  command_name?: string
  device_name?: string
  source?: string
  requested_value?: string
  result?: string
  user_name?: string
  sequence_name?: string
  rule_name?: string
}

export type ControlBootstrap = {
  engine: ControlEngine
  groups: ControlGroup[]
  ungrouped_count: number
  current_group: ControlGroup | null
  devices: ControlDevice[]
  commands: ControlCommand[]
  logs: ControlLog[]
  schedules: ControlSchedule[]
  sequences: ControlSequence[]
  rules: ControlRule[]
  can_create: boolean
  can_update: boolean
  can_delete: boolean
}

export type OverviewStatus = {
  items: Array<{
    id: number
    name: string
    device_name?: string
    enabled?: boolean
    state?: string
    state_label?: string
    command_state?: string | null
    command_label?: string | null
    card_tone?: string
    hold_source?: string | null
    value_display?: string
    stale?: boolean
  }>
  summary: Record<string, number>
  sequences: unknown[]
  rules: unknown[]
}

export type DeviceControlContext = {
  id: number
  name: string
  role?: string
  protocol?: string
  gateway?: { id: number; name: string } | null
  write_via?: { id: number; name: string; cross_device?: boolean }
  default_write_via_id?: number
  feedback_metrics: Array<{ key: string; label: string; type?: string }>
  write_targets: Array<{
    key: string
    label: string
    hint?: string
    protocol: string
    write_config: Record<string, unknown>
  }>
}

export function fetchControlBootstrap(group: string | null) {
  const qs = group !== null && group !== undefined && group !== '' ? `?group=${encodeURIComponent(group)}` : ''
  return apiRequest<ControlBootstrap>(`/control/bootstrap${qs}`)
}

export function fetchOverviewStatus(group: string) {
  return apiRequest<OverviewStatus>(`/control/overview-status?group=${encodeURIComponent(group)}`)
}

export function fetchDeviceContext(deviceId: number, writeDeviceId?: number) {
  const qs = writeDeviceId ? `?write_device_id=${writeDeviceId}` : ''
  return apiRequest<DeviceControlContext>(`/control/devices/${deviceId}/context${qs}`)
}

export function decodeJson<T>(raw: unknown, fallback: T): T {
  if (raw && typeof raw === 'object') return raw as T
  if (typeof raw === 'string' && raw !== '') {
    try {
      return JSON.parse(raw) as T
    } catch {
      return fallback
    }
  }
  return fallback
}

export function seqSummary(
  seq: ControlSequence,
  commandNames: Record<number, string>,
  t: (k: string) => string,
): string {
  const steps = decodeJson<ControlSequenceStep[]>(seq.steps, [])
  if (!Array.isArray(steps) || !steps.length) return t('control_no_steps')
  const parts: string[] = []
  for (const s of steps) {
    const cid = Number(s.command_id ?? 0)
    const name = commandNames[cid] ?? `#${cid}`
    const action = s.action === 'off' ? t('control_off') : t('control_on')
    let bit = `${name} ${action}`
    const wait = String(s.wait ?? 'none').toLowerCase()
    if (wait === 'on' || wait === 'off') {
      bit += ` · ${t('control_wait')} ${wait === 'off' ? t('control_off') : t('control_on')}`
    }
    parts.push(bit)
  }
  return parts.join(' → ')
}

export function ruleCondSummary(
  rule: ControlRule,
  commandNames: Record<number, string>,
  deviceNames: Record<number, string>,
  t: (k: string) => string,
): string {
  const c = decodeJson<{ match?: string; items?: Array<Record<string, unknown>> }>(rule.conditions, {})
  const items = Array.isArray(c.items) ? c.items : []
  if (!items.length) return t('control_no_conditions')
  const match = String(c.match ?? 'all').toLowerCase() === 'any' ? 'Any' : 'All'
  const parts: string[] = []
  for (const item of items) {
    const type = String(item.type ?? '').toLowerCase()
    if (type === 'command_status') {
      const cid = Number(item.command_id ?? 0)
      const state = String(item.state ?? 'off').toLowerCase() === 'on' ? t('control_on') : t('control_off')
      parts.push(`${commandNames[cid] ?? `#${cid}`} is ${state}`)
    } else if (type === 'alarm') {
      const did = Number(item.device_id ?? 0)
      const dname = deviceNames[did] ?? `device #${did}`
      const key = String(item.metric_key ?? '').trim()
      parts.push(key ? `alarm ${dname} · ${key}` : `any alarm on ${dname}`)
    } else if (type === 'metric') {
      const did = Number(item.device_id ?? 0)
      const dname = deviceNames[did] ?? `device #${did}`
      parts.push(`${dname} ${item.key ?? item.metric_key ?? ''} ${item.op ?? '>'} ${item.value ?? ''}`)
    }
  }
  return `${match}: ${parts.length ? parts.join('; ') : t('control_no_conditions')}`
}

export function ruleActionSummary(
  rule: ControlRule,
  commandNames: Record<number, string>,
  sequenceNames: Record<number, string>,
  t: (k: string) => string,
): string {
  const a = decodeJson<{ kind?: string; type?: string; sequence_id?: number; commands?: Array<Record<string, unknown>> }>(
    rule.action,
    {},
  )
  const kind = String(a.kind ?? a.type ?? '').toLowerCase()
  if (kind === 'sequence') {
    const sid = Number(a.sequence_id ?? 0)
    return `${t('control_sequence')} ${sequenceNames[sid] ?? `#${sid}`}`
  }
  const cmds = Array.isArray(a.commands) ? a.commands : []
  const parts: string[] = []
  for (const c of cmds) {
    const cid = Number(c.command_id ?? 0)
    const action = String(c.action ?? 'on').toLowerCase() === 'off' ? t('control_off') : t('control_on')
    parts.push(`${commandNames[cid] ?? `#${cid}`} ${action}`)
  }
  return parts.length ? parts.join(', ') : t('control_no_action')
}
