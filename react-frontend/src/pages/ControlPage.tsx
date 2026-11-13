import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Alert,
  App,
  Badge,
  Breadcrumb,
  Button,
  Card,
  Checkbox,
  Col,
  Collapse,
  Form,
  Input,
  InputNumber,
  Modal,
  Row,
  Select,
  Space,
  Switch,
  Table,
  Tabs,
  Tag,
  Typography,
} from 'antd'
import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { ApiError, apiRequest } from '../api/client'
import {
  decodeJson,
  fetchControlBootstrap,
  fetchDeviceContext,
  fetchOverviewStatus,
  ruleActionSummary,
  ruleCondSummary,
  seqSummary,
  type ControlCommand,
  type ControlGroup,
  type ControlRule,
  type ControlSchedule,
  type ControlSequence,
  type ControlSequenceStep,
  type DeviceControlContext,
} from '../features/control/api'

const KIND_ORDER = ['building', 'room', 'system', 'generic'] as const
const KIND_ICONS: Record<string, string> = {
  building: 'bi-building',
  room: 'bi-door-open',
  system: 'bi-diagram-3',
  generic: 'bi-grid',
}

function kindLabelKey(kind: string) {
  if (kind === 'building') return 'control_kind_building'
  if (kind === 'room') return 'control_kind_room'
  if (kind === 'system') return 'control_kind_system'
  return 'control_kind_other'
}

export function ControlPage() {
  const { t } = useTranslation()
  const { message, modal } = App.useApp()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const groupParam = params.get('group')
  const tabParam = params.get('tab') ?? 'overview'

  const bootstrap = useQuery({
    queryKey: ['control-bootstrap', groupParam ?? ''],
    queryFn: () => fetchControlBootstrap(groupParam),
  })

  const data = bootstrap.data
  const engineOk = Boolean(data?.engine?.ok)
  const currentGroup = data?.current_group ?? null
  const groupKey = groupParam ?? ''

  const overview = useQuery({
    queryKey: ['control-overview', groupKey],
    queryFn: () => fetchOverviewStatus(groupKey),
    enabled: Boolean(currentGroup) && engineOk,
    refetchInterval: engineOk && currentGroup ? 5000 : false,
  })

  const commandNames = useMemo(() => {
    const map: Record<number, string> = {}
    for (const c of data?.commands ?? []) map[c.id] = c.name
    return map
  }, [data?.commands])

  const sequenceNames = useMemo(() => {
    const map: Record<number, string> = {}
    for (const s of data?.sequences ?? []) map[s.id] = s.name
    return map
  }, [data?.sequences])

  const deviceNames = useMemo(() => {
    const map: Record<number, string> = {}
    for (const d of data?.devices ?? []) map[d.id] = d.name
    return map
  }, [data?.devices])

  const [groupOpen, setGroupOpen] = useState(false)
  const [editingGroup, setEditingGroup] = useState<ControlGroup | null>(null)
  const [groupForm] = Form.useForm()

  const [cmdOpen, setCmdOpen] = useState(false)
  const [editingCmd, setEditingCmd] = useState<ControlCommand | null>(null)
  const [cmdForm] = Form.useForm()
  const [deviceCtx, setDeviceCtx] = useState<DeviceControlContext | null>(null)
  const [writeViaId, setWriteViaId] = useState<number | undefined>()

  const [schOpen, setSchOpen] = useState(false)
  const [editingSch, setEditingSch] = useState<ControlSchedule | null>(null)
  const [schForm] = Form.useForm()

  const [seqOpen, setSeqOpen] = useState(false)
  const [editingSeq, setEditingSeq] = useState<ControlSequence | null>(null)
  const [seqForm] = Form.useForm()
  const [seqSteps, setSeqSteps] = useState<ControlSequenceStep[]>([])

  const [ruleOpen, setRuleOpen] = useState(false)
  const [editingRule, setEditingRule] = useState<ControlRule | null>(null)
  const [ruleForm] = Form.useForm()
  const [ruleConds, setRuleConds] = useState<Array<Record<string, unknown>>>([])
  const [ruleActionKind, setRuleActionKind] = useState<'sequence' | 'command'>('command')
  const [ruleActionCmds, setRuleActionCmds] = useState<Array<{ command_id: number; action: string }>>([])

  function setGroupUrl(group: number | '0' | null, tab?: string) {
    const next = new URLSearchParams()
    if (group === 0 || group === '0') next.set('group', '0')
    else if (group !== null) next.set('group', String(group))
    if (tab) next.set('tab', tab)
    navigate(`/control?${next.toString()}`)
  }

  async function invalidateAll() {
    await queryClient.invalidateQueries({ queryKey: ['control-bootstrap'] })
    await queryClient.invalidateQueries({ queryKey: ['control-overview'] })
  }

  async function loadDeviceCtx(deviceId: number, writeId?: number) {
    if (!deviceId) {
      setDeviceCtx(null)
      return
    }
    try {
      const ctx = await fetchDeviceContext(deviceId, writeId)
      setDeviceCtx(ctx)
    } catch {
      setDeviceCtx(null)
      message.error(t('control_msg_save_failed'))
    }
  }

  useEffect(() => {
    if (!cmdOpen) return
    const deviceId = Number(cmdForm.getFieldValue('device_id') ?? 0)
    if (deviceId > 0) void loadDeviceCtx(deviceId, writeViaId)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cmdOpen, writeViaId])

  const saveGroup = useMutation({
    mutationFn: async (values: { name: string; kind: string; description?: string }) => {
      if (editingGroup?.id) {
        return apiRequest(`/control/groups/${editingGroup.id}`, { method: 'PUT', body: values })
      }
      return apiRequest<{ group?: ControlGroup }>('/control/groups', { method: 'POST', body: values })
    },
    onSuccess: async (res) => {
      setGroupOpen(false)
      message.success(t('control_msg_group_saved'))
      await invalidateAll()
      const newId = (res as { group?: { id?: number } })?.group?.id
      if (newId) setGroupUrl(newId)
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const deleteGroup = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/groups/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('control_msg_group_removed'))
      setGroupUrl(null)
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const saveCommand = useMutation({
    mutationFn: async (values: Record<string, unknown>) => {
      let writeConfig: Record<string, unknown> = {}
      try {
        writeConfig = values.write_config_json
          ? (JSON.parse(String(values.write_config_json)) as Record<string, unknown>)
          : {}
      } catch {
        writeConfig = {}
      }
      const body = {
        name: values.name,
        description: values.description ?? '',
        device_id: Number(values.device_id),
        protocol: values.protocol ?? 'snmp_set',
        write_config: writeConfig,
        on_value: values.on_value,
        off_value: values.off_value,
        feedback_on: values.feedback_on,
        feedback_off: values.feedback_off,
        feedback_on_label: values.feedback_on_label,
        feedback_off_label: values.feedback_off_label,
        feedback_metric_key: values.feedback_metric_key ?? '',
        enabled: Boolean(values.enabled),
        group_id: values.group_id ? Number(values.group_id) : null,
      }
      if (editingCmd?.id) {
        return apiRequest(`/control/commands/${editingCmd.id}`, { method: 'PUT', body })
      }
      return apiRequest('/control/commands', { method: 'POST', body })
    },
    onSuccess: async () => {
      setCmdOpen(false)
      message.success(t('control_msg_command_saved'))
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const deleteCommand = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/commands/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('control_msg_command_removed'))
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const executeCommand = useMutation({
    mutationFn: ({ id, value }: { id: number; value: string }) =>
      apiRequest(`/control/commands/${id}/execute`, { method: 'POST', body: { value } }),
    onSuccess: async () => {
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const autoCommand = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/commands/${id}/auto`, { method: 'POST', body: {} }),
    onSuccess: async () => {
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const saveSchedule = useMutation({
    mutationFn: async (values: Record<string, unknown>) => {
      const body = {
        name: values.name ?? '',
        command_id: Number(values.command_id),
        time: values.time ?? '08:00',
        days: (values.days as number[]) ?? [0, 1, 2, 3, 4, 5, 6],
        action: values.action === 'off' ? 'off' : 'on',
        enabled: Boolean(values.enabled),
        cron: values.cron,
      }
      if (editingSch?.id) {
        return apiRequest(`/control/schedules/${editingSch.id}`, { method: 'PUT', body })
      }
      return apiRequest('/control/schedules', { method: 'POST', body })
    },
    onSuccess: async () => {
      setSchOpen(false)
      message.success(t('control_msg_schedule_saved'))
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const deleteSchedule = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/schedules/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('control_msg_schedule_removed'))
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const toggleSchedule = useMutation({
    mutationFn: (sch: ControlSchedule) =>
      apiRequest(`/control/schedules/${sch.id}`, {
        method: 'PUT',
        body: {
          command_id: sch.command_id,
          name: sch.name,
          cron: sch.cron,
          action: sch.action === 'off' ? 'off' : 'on',
          enabled: !sch.enabled,
        },
      }),
    onSuccess: async () => {
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const saveSequence = useMutation({
    mutationFn: async (values: Record<string, unknown>) => {
      const body = {
        name: values.name,
        steps: seqSteps,
        enabled: Boolean(values.enabled),
        group_id: currentGroup?.id ? currentGroup.id : null,
        loop: Boolean(values.loop),
        loop_count: Number(values.loop_count ?? 0),
        loop_delay_sec: Number(values.loop_delay_sec ?? 0),
      }
      if (editingSeq?.id) {
        return apiRequest(`/control/sequences/${editingSeq.id}`, { method: 'PUT', body })
      }
      return apiRequest('/control/sequences', { method: 'POST', body })
    },
    onSuccess: async () => {
      setSeqOpen(false)
      message.success(t('control_msg_sequence_saved'))
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const deleteSequence = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/sequences/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('control_msg_sequence_removed'))
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const runSequence = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/sequences/${id}/execute`, { method: 'POST', body: {} }),
    onSuccess: async () => invalidateAll(),
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const stopSequence = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/sequences/${id}/stop`, { method: 'POST', body: {} }),
    onSuccess: async () => invalidateAll(),
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const pauseSequence = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/sequences/${id}/pause`, { method: 'POST', body: {} }),
    onSuccess: async () => invalidateAll(),
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const resumeSequence = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/sequences/${id}/resume`, { method: 'POST', body: {} }),
    onSuccess: async () => invalidateAll(),
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const saveRule = useMutation({
    mutationFn: async (values: Record<string, unknown>) => {
      const body = {
        name: values.name,
        conditions: { match: values.match ?? 'all', items: ruleConds },
        action:
          ruleActionKind === 'sequence'
            ? { kind: 'sequence', sequence_id: Number(values.sequence_id ?? 0) }
            : { kind: 'command', commands: ruleActionCmds },
        enabled: Boolean(values.enabled),
        group_id: currentGroup?.id ? currentGroup.id : null,
        cooldown_sec: Number(values.cooldown_sec ?? 300),
      }
      if (editingRule?.id) {
        return apiRequest(`/control/rules/${editingRule.id}`, { method: 'PUT', body })
      }
      return apiRequest('/control/rules', { method: 'POST', body })
    },
    onSuccess: async () => {
      setRuleOpen(false)
      message.success(t('control_msg_rule_saved'))
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  const deleteRule = useMutation({
    mutationFn: (id: number) => apiRequest(`/control/rules/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('control_msg_rule_removed'))
      await invalidateAll()
    },
    onError: (e: unknown) => {
      message.error(e instanceof ApiError ? e.message : t('control_msg_save_failed'))
    },
  })

  function openGroup(g?: ControlGroup) {
    setEditingGroup(g ?? null)
    groupForm.setFieldsValue({
      name: g?.name ?? '',
      kind: g?.kind ?? 'building',
      description: g?.description ?? '',
    })
    setGroupOpen(true)
  }

  function openCommand(cmd?: ControlCommand) {
    setEditingCmd(cmd ?? null)
    const wc = decodeJson<Record<string, unknown>>(cmd?.write_config, {})
    const writeVia =
      typeof wc.gateway_device_id === 'number' ? Number(wc.gateway_device_id) : undefined
    setWriteViaId(writeVia)
    cmdForm.setFieldsValue({
      name: cmd?.name ?? '',
      description: cmd?.description ?? '',
      device_id: cmd?.device_id,
      group_id: cmd?.group_id ?? currentGroup?.id ?? undefined,
      protocol: cmd?.protocol ?? 'snmp_set',
      feedback_metric_key: cmd?.feedback_metric_key ?? '',
      enabled: cmd?.enabled ?? true,
      on_value: wc.on_value ?? '',
      off_value: wc.off_value ?? '',
      feedback_on: Array.isArray(wc.feedback_on) ? wc.feedback_on.join(', ') : '',
      feedback_off: Array.isArray(wc.feedback_off) ? wc.feedback_off.join(', ') : '',
      feedback_on_label: wc.feedback_on_label ?? '',
      feedback_off_label: wc.feedback_off_label ?? '',
      write_config_json: JSON.stringify(wc, null, 2),
      write_target: wc.metric_key ?? '',
    })
    setCmdOpen(true)
    if (cmd?.device_id) void loadDeviceCtx(cmd.device_id, writeVia)
  }

  function openSchedule(sch?: ControlSchedule) {
    setEditingSch(sch ?? null)
    const cron = String(sch?.cron ?? '0 8 * * *')
    const parts = cron.split(/\s+/)
    const minute = parts[0] ?? '0'
    const hour = parts[1] ?? '8'
    const dow = parts[4] ?? '*'
    const time = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
    const days =
      dow === '*'
        ? [0, 1, 2, 3, 4, 5, 6]
        : dow.split(',').map((d) => Number(d)).filter((n) => !Number.isNaN(n))
    schForm.setFieldsValue({
      name: sch?.name ?? '',
      command_id: sch?.command_id,
      time,
      days,
      action: sch?.action === 'off' ? 'off' : 'on',
      enabled: sch?.enabled ?? true,
      cron: sch?.cron,
    })
    setSchOpen(true)
  }

  function openSequence(seq?: ControlSequence) {
    setEditingSeq(seq ?? null)
    const steps = decodeJson<ControlSequenceStep[]>(seq?.steps, [])
    setSeqSteps(
      steps.length
        ? steps
        : [{ command_id: 0, action: 'on', wait: 'none', timeout_sec: 30, delay_sec: 0, delay_unit: 's' }],
    )
    seqForm.setFieldsValue({
      name: seq?.name ?? '',
      enabled: seq?.enabled ?? true,
      loop: Boolean(seq?.loop || seq?.loop_enabled),
      loop_count: seq?.loop_count ?? 0,
      loop_delay_sec: seq?.loop_delay_sec ?? 0,
    })
    setSeqOpen(true)
  }

  function openRule(rule?: ControlRule) {
    setEditingRule(rule ?? null)
    const conds = decodeJson<{ match?: string; items?: Array<Record<string, unknown>> }>(rule?.conditions, {})
    const action = decodeJson<{
      kind?: string
      sequence_id?: number
      commands?: Array<{ command_id: number; action: string }>
    }>(rule?.action, {})
    setRuleConds(Array.isArray(conds.items) ? conds.items : [])
    const kind = String(action.kind ?? '').toLowerCase() === 'sequence' ? 'sequence' : 'command'
    setRuleActionKind(kind)
    setRuleActionCmds(Array.isArray(action.commands) ? action.commands : [])
    ruleForm.setFieldsValue({
      name: rule?.name ?? '',
      match: conds.match === 'any' ? 'any' : 'all',
      enabled: rule?.enabled ?? true,
      cooldown_sec: rule?.cooldown_sec ?? 300,
      sequence_id: action.sequence_id,
      action_kind: kind,
    })
    setRuleOpen(true)
  }

  const dayLabels = t('control_days_short').split(',')

  const groupsByKind = useMemo(() => {
    const map: Record<string, ControlGroup[]> = {}
    for (const g of data?.groups ?? []) {
      let k = String(g.kind ?? 'generic').toLowerCase()
      if (!KIND_ORDER.includes(k as (typeof KIND_ORDER)[number])) k = 'generic'
      map[k] = map[k] ?? []
      map[k].push(g)
    }
    return map
  }, [data?.groups])

  return (
    <>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <div>
          <Typography.Title level={3} style={{ marginBottom: 4 }}>
            {currentGroup ? currentGroup.name : t('control')}
          </Typography.Title>
          {currentGroup ? (
            <Breadcrumb
              items={[
                { title: <Link to="/control">{t('control')}</Link> },
                ...(currentGroup.kind && currentGroup.kind !== 'generic'
                  ? [{ title: t(kindLabelKey(currentGroup.kind)) }]
                  : []),
                { title: currentGroup.description || currentGroup.name },
              ]}
            />
          ) : (
            <>
              <Typography.Paragraph type="secondary" style={{ marginBottom: 4 }}>
                {t('control_pick_group')}
              </Typography.Paragraph>
              <Breadcrumb
                items={[{ title: <Link to="/home">{t('home')}</Link> }, { title: t('control') }]}
              />
            </>
          )}
        </div>
        <Space>
          <Badge
            status={engineOk ? 'success' : 'error'}
            text={engineOk ? t('control_engine_online') : t('control_engine_offline')}
          />
          {currentGroup && currentGroup.id > 0 && data?.can_update ? (
            <Button onClick={() => openGroup(currentGroup)}>{t('control_edit_group')}</Button>
          ) : null}
          {!currentGroup && data?.can_create ? (
            <Button type="primary" disabled={!engineOk} onClick={() => openGroup()}>
              <i className="bi bi-plus-circle" style={{ marginRight: 6 }} />
              {t('control_new_group')}
            </Button>
          ) : null}
        </Space>
      </div>

      {!engineOk ? <Alert type="warning" showIcon message={t('control_engine_hint')} style={{ marginBottom: 16 }} /> : null}

      {!currentGroup ? (
        <>
          {KIND_ORDER.map((kindKey) => {
            const list = groupsByKind[kindKey] ?? []
            if (!list.length) return null
            return (
              <div key={kindKey} style={{ marginBottom: 24 }}>
                <Typography.Title level={5}>
                  <i className={`bi ${KIND_ICONS[kindKey]}`} style={{ marginRight: 8 }} />
                  {t(kindLabelKey(kindKey))}{' '}
                  <Typography.Text type="secondary">({list.length})</Typography.Text>
                </Typography.Title>
                <Row gutter={[16, 16]}>
                  {list.map((g) => {
                    const count = Number(g.command_count ?? 0)
                    let desc = (g.description ?? '').trim()
                    if (desc && desc.toLowerCase() === g.name.toLowerCase()) desc = ''
                    return (
                      <Col key={g.id} xs={24} sm={12} xl={8} xxl={6}>
                        <Card size="small">
                          <Space direction="vertical" style={{ width: '100%' }} size={8}>
                            <Space>
                              <i className={`bi ${KIND_ICONS[kindKey]}`} />
                              <Tag>{t(kindLabelKey(kindKey))}</Tag>
                              <Typography.Text type="secondary">
                                {count > 0
                                  ? t('control_command_count', { count })
                                  : t('control_no_commands_yet')}
                              </Typography.Text>
                            </Space>
                            <Typography.Title level={5} style={{ margin: 0 }}>
                              {g.name}
                            </Typography.Title>
                            <Typography.Paragraph type="secondary" style={{ minHeight: 22, marginBottom: 0 }}>
                              {desc || '\u00a0'}
                            </Typography.Paragraph>
                            <Space>
                              <Button type="primary" size="small" onClick={() => setGroupUrl(g.id)}>
                                {t('control_open')}
                              </Button>
                              {data?.can_update ? (
                                <Button size="small" onClick={() => openGroup(g)}>
                                  {t('control_edit')}
                                </Button>
                              ) : null}
                            </Space>
                          </Space>
                        </Card>
                      </Col>
                    )
                  })}
                </Row>
              </div>
            )
          })}

          {(data?.ungrouped_count ?? 0) > 0 ? (
            <div style={{ marginBottom: 24 }}>
              <Typography.Title level={5}>
                <i className="bi bi-collection" style={{ marginRight: 8 }} />
                {t('control_ungrouped')}
              </Typography.Title>
              <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} xl={8} xxl={6}>
                  <Card size="small">
                    <Typography.Title level={5}>{t('control_ungrouped')}</Typography.Title>
                    <Typography.Paragraph type="secondary">{t('control_ungrouped_desc')}</Typography.Paragraph>
                    <Typography.Text type="secondary">
                      {t('control_command_count', { count: data?.ungrouped_count ?? 0 })}
                    </Typography.Text>
                    <div style={{ marginTop: 12 }}>
                      <Button onClick={() => setGroupUrl(0)}>{t('control_open')}</Button>
                    </div>
                  </Card>
                </Col>
              </Row>
            </div>
          ) : null}

          {engineOk && (data?.groups?.length ?? 0) === 0 && (data?.ungrouped_count ?? 0) === 0 ? (
            <Card>
              <div style={{ textAlign: 'center', padding: 48, color: '#888' }}>
                <div style={{ marginBottom: 8 }}>
                  <i className="bi bi-diagram-3" style={{ fontSize: 32 }} />
                </div>
                {t('control_no_groups')}
              </div>
            </Card>
          ) : null}
        </>
      ) : (
        <Tabs
          activeKey={['overview', 'flow', 'commands', 'schedules', 'sequences', 'rules', 'logs'].includes(tabParam)
            ? tabParam
            : 'overview'}
          onChange={(key) => setGroupUrl(currentGroup.id === 0 ? 0 : currentGroup.id, key)}
          items={[
            {
              key: 'overview',
              label: t('control_tab_overview'),
              children: (
                <>
                  <Row gutter={16} style={{ marginBottom: 16 }}>
                    {[
                      { key: 'on', label: t('control_running') },
                      { key: 'off', label: t('control_stopped') },
                      { key: 'mismatch', label: t('control_checking') },
                      { key: 'disabled', label: t('control_disabled') },
                    ].map((kpi) => (
                      <Col key={kpi.key} xs={12} lg={6}>
                        <Card size="small">
                          <Typography.Text type="secondary">{kpi.label}</Typography.Text>
                          <Typography.Title level={3} style={{ margin: 0 }}>
                            {overview.data?.summary?.[kpi.key] ?? '—'}
                          </Typography.Title>
                        </Card>
                      </Col>
                    ))}
                  </Row>
                  <Row gutter={[16, 16]}>
                    {(overview.data?.items ?? data?.commands ?? []).map((item) => {
                      const row = item as {
                        id: number
                        name: string
                        device_name?: string
                        enabled?: boolean
                        state_label?: string
                        command_label?: string | null
                        card_tone?: string
                      }
                      return (
                        <Col key={row.id} md={12} xl={8}>
                          <Card size="small">
                            <Typography.Title level={5} style={{ marginBottom: 0 }}>
                              {row.name}
                            </Typography.Title>
                            <Typography.Text type="secondary">{row.device_name}</Typography.Text>
                            <div style={{ margin: '12px 0' }}>
                              <Tag>{row.state_label ?? '—'}</Tag>
                              {row.command_label ? (
                                <Typography.Text type="secondary">
                                  {t('control_command')}: {row.command_label}
                                </Typography.Text>
                              ) : null}
                            </div>
                            {data?.can_update ? (
                              <Space>
                                <Button
                                  disabled={!row.enabled}
                                  onClick={() =>
                                    modal.confirm({
                                      title: t('control_send_on', { name: row.name }),
                                      onOk: () => executeCommand.mutateAsync({ id: row.id, value: '1' }),
                                    })
                                  }
                                >
                                  {t('control_on')}
                                </Button>
                                <Button
                                  disabled={!row.enabled}
                                  onClick={() =>
                                    modal.confirm({
                                      title: t('control_send_off', { name: row.name }),
                                      onOk: () => executeCommand.mutateAsync({ id: row.id, value: '0' }),
                                    })
                                  }
                                >
                                  {t('control_off')}
                                </Button>
                                <Button
                                  disabled={!row.enabled}
                                  onClick={() =>
                                    modal.confirm({
                                      title: t('control_return_auto', { name: row.name }),
                                      onOk: () => autoCommand.mutateAsync(row.id),
                                    })
                                  }
                                >
                                  {t('control_auto')}
                                </Button>
                              </Space>
                            ) : null}
                          </Card>
                        </Col>
                      )
                    })}
                    {(data?.commands?.length ?? 0) === 0 ? (
                      <Col span={24}>
                        <Card>
                          <Typography.Text type="secondary">{t('control_no_commands_group')}</Typography.Text>
                        </Card>
                      </Col>
                    ) : null}
                  </Row>
                </>
              ),
            },
            {
              key: 'flow',
              label: t('control_tab_flow'),
              children: (
                <Space direction="vertical" style={{ width: '100%' }} size={16}>
                  <Card>
                    <Typography.Title level={5}>{t('control_priority')}</Typography.Title>
                    <Typography.Paragraph type="secondary">{t('control_priority_hint')}</Typography.Paragraph>
                    <Row gutter={12}>
                      {[
                        { title: t('control_pri_manual'), desc: t('control_pri_manual_desc') },
                        { title: t('control_pri_rule'), desc: t('control_pri_rule_desc') },
                        { title: t('control_pri_seq'), desc: t('control_pri_seq_desc') },
                        { title: t('control_pri_sch'), desc: t('control_pri_sch_desc') },
                      ].map((p, i) => (
                        <Col key={p.title} xs={24} md={6}>
                          <Card size="small" type="inner">
                            <Typography.Text type="secondary">{i + 1}</Typography.Text>
                            <Typography.Title level={5} style={{ marginTop: 4 }}>
                              {p.title}
                            </Typography.Title>
                            <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
                              {p.desc}
                            </Typography.Paragraph>
                          </Card>
                        </Col>
                      ))}
                    </Row>
                  </Card>
                  <Card>
                    <Typography.Title level={5}>{t('control_this_group')}</Typography.Title>
                    <Typography.Paragraph type="secondary">{t('control_this_group_hint')}</Typography.Paragraph>
                    <Typography.Text type="secondary">
                      {t('control_section_rules', { defaultValue: 'Rules' })}: {data?.rules?.length ?? 0} ·{' '}
                      {t('control_section_sequences', { defaultValue: 'Sequences' })}:{' '}
                      {data?.sequences?.length ?? 0} · {t('control_section_schedules', { defaultValue: 'Schedules' })}:{' '}
                      {data?.schedules?.length ?? 0}
                    </Typography.Text>
                  </Card>
                </Space>
              ),
            },
            {
              key: 'commands',
              label: t('control_tab_commands'),
              children: (
                <Card>
                  <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                    {data?.can_create ? (
                      <Button type="primary" disabled={!engineOk} onClick={() => openCommand()}>
                        <i className="bi bi-plus-circle" style={{ marginRight: 6 }} />
                        {t('control_new_command')}
                      </Button>
                    ) : null}
                  </div>
                  <Table
                    rowKey="id"
                    dataSource={data?.commands ?? []}
                    locale={{ emptyText: t('control_no_commands') }}
                    columns={[
                      {
                        title: t('control_name'),
                        render: (_, row: ControlCommand) => (
                          <>
                            <strong>{row.name}</strong>
                            <div>
                              <Typography.Text type="secondary">{row.description}</Typography.Text>
                            </div>
                          </>
                        ),
                      },
                      {
                        title: t('control_device'),
                        render: (_, row) => row.device_name ?? `#${row.device_id}`,
                      },
                      {
                        title: t('control_feedback'),
                        render: (_, row) => <code>{row.feedback_metric_key || '-'}</code>,
                      },
                      {
                        title: t('control_status'),
                        render: (_, row) =>
                          row.enabled ? (
                            <Tag color="success">{t('control_enabled')}</Tag>
                          ) : (
                            <Tag>{t('control_disabled_badge')}</Tag>
                          ),
                      },
                      {
                        title: t('control_actions'),
                        align: 'right',
                        render: (_, row) => (
                          <Space wrap>
                            {data?.can_update ? (
                              <>
                                <Button
                                  size="small"
                                  disabled={!row.enabled}
                                  onClick={() =>
                                    modal.confirm({
                                      title: t('control_send_on', { name: row.name }),
                                      onOk: () => executeCommand.mutateAsync({ id: row.id, value: '1' }),
                                    })
                                  }
                                >
                                  {t('control_on')}
                                </Button>
                                <Button
                                  size="small"
                                  disabled={!row.enabled}
                                  onClick={() =>
                                    modal.confirm({
                                      title: t('control_send_off', { name: row.name }),
                                      onOk: () => executeCommand.mutateAsync({ id: row.id, value: '0' }),
                                    })
                                  }
                                >
                                  {t('control_off')}
                                </Button>
                                <Button size="small" onClick={() => openCommand(row)}>
                                  {t('control_edit')}
                                </Button>
                              </>
                            ) : null}
                            {data?.can_delete ? (
                              <Button
                                size="small"
                                danger
                                onClick={() =>
                                  modal.confirm({
                                    title: t('control_confirm_delete_command'),
                                    onOk: () => deleteCommand.mutateAsync(row.id),
                                  })
                                }
                              >
                                {t('control_delete')}
                              </Button>
                            ) : null}
                          </Space>
                        ),
                      },
                    ]}
                    pagination={false}
                  />
                </Card>
              ),
            },
            {
              key: 'schedules',
              label: t('control_tab_schedules'),
              children: (
                <Card>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12, gap: 12 }}>
                    <Typography.Text type="secondary">{t('control_schedule_hint')}</Typography.Text>
                    {data?.can_create ? (
                      <Button type="primary" disabled={!engineOk} onClick={() => openSchedule()}>
                        {t('control_new_schedule')}
                      </Button>
                    ) : null}
                  </div>
                  <Table
                    rowKey="id"
                    dataSource={data?.schedules ?? []}
                    locale={{ emptyText: t('control_no_schedules') }}
                    pagination={false}
                    columns={[
                      { title: t('control_name'), dataIndex: 'name' },
                      {
                        title: t('control_command'),
                        render: (_, row: ControlSchedule) => (
                          <>
                            {row.command_name ?? `#${row.command_id}`}
                            <div>
                              <Typography.Text type="secondary">{row.device_name}</Typography.Text>
                            </div>
                          </>
                        ),
                      },
                      {
                        title: t('control_when'),
                        render: (_, row) => <code>{row.cron}</code>,
                      },
                      {
                        title: t('control_action'),
                        render: (_, row) => (
                          <Tag>{row.action === 'off' ? t('control_off') : t('control_on')}</Tag>
                        ),
                      },
                      {
                        title: t('control_last_run'),
                        render: (_, row) => row.last_run_at ?? '—',
                      },
                      {
                        title: t('control_status'),
                        render: (_, row) =>
                          row.enabled ? (
                            <Tag color="success">{t('control_enabled')}</Tag>
                          ) : (
                            <Tag>{t('control_disabled_badge')}</Tag>
                          ),
                      },
                      {
                        title: t('control_actions'),
                        align: 'right',
                        render: (_, row) => (
                          <Space wrap>
                            {data?.can_update ? (
                              <>
                                <Button size="small" onClick={() => toggleSchedule.mutate(row)}>
                                  {row.enabled ? t('control_deactivate') : t('control_activate')}
                                </Button>
                                <Button size="small" onClick={() => openSchedule(row)}>
                                  {t('control_edit')}
                                </Button>
                              </>
                            ) : null}
                            {data?.can_delete ? (
                              <Button
                                size="small"
                                danger
                                onClick={() =>
                                  modal.confirm({
                                    title: t('control_confirm_delete_schedule'),
                                    onOk: () => deleteSchedule.mutateAsync(row.id),
                                  })
                                }
                              >
                                {t('control_delete')}
                              </Button>
                            ) : null}
                          </Space>
                        ),
                      },
                    ]}
                  />
                </Card>
              ),
            },
            {
              key: 'sequences',
              label: t('control_tab_sequences'),
              children: (
                <Card>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12, gap: 12 }}>
                    <Typography.Text type="secondary">{t('control_sequence_hint')}</Typography.Text>
                    {data?.can_create ? (
                      <Button type="primary" disabled={!engineOk} onClick={() => openSequence()}>
                        {t('control_new_sequence')}
                      </Button>
                    ) : null}
                  </div>
                  <Table
                    rowKey="id"
                    dataSource={data?.sequences ?? []}
                    locale={{ emptyText: t('control_no_sequences') }}
                    pagination={false}
                    columns={[
                      {
                        title: t('control_name'),
                        render: (_, row: ControlSequence) => (
                          <>
                            <strong>{row.name}</strong>
                            {row.loop || row.loop_enabled ? (
                              <Tag style={{ marginLeft: 8 }}>
                                {t('control_loop')}
                                {(row.loop_count ?? 0) > 0 ? ` ×${row.loop_count}` : ''}
                              </Tag>
                            ) : null}
                          </>
                        ),
                      },
                      {
                        title: t('control_steps'),
                        render: (_, row) => seqSummary(row, commandNames, t),
                      },
                      {
                        title: t('control_last_run'),
                        render: (_, row) => row.last_run_at ?? '—',
                      },
                      {
                        title: t('control_result'),
                        render: (_, row) => {
                          if (row.paused) return <Tag color="warning">{t('control_paused')}</Tag>
                          if (row.running) return <Tag color="processing">{t('control_seq_running')}</Tag>
                          const r = String(row.last_result ?? '').toLowerCase()
                          if (r === 'ok') return <Tag color="success">{t('control_seq_ok')}</Tag>
                          if (r === 'stopped') return <Tag>{t('control_seq_stopped')}</Tag>
                          if (r === 'failed') return <Tag color="error">{t('control_seq_failed')}</Tag>
                          return '—'
                        },
                      },
                      {
                        title: t('control_status'),
                        render: (_, row) =>
                          row.enabled ? (
                            <Tag color="success">{t('control_enabled')}</Tag>
                          ) : (
                            <Tag>{t('control_disabled_badge')}</Tag>
                          ),
                      },
                      {
                        title: t('control_actions'),
                        align: 'right',
                        render: (_, row) => {
                          const running = Boolean(row.running)
                          return (
                            <Space wrap>
                              {data?.can_update ? (
                                <>
                                  {running ? (
                                    <>
                                      {row.paused ? (
                                        <Button size="small" onClick={() => resumeSequence.mutate(row.id)}>
                                          {t('control_resume')}
                                        </Button>
                                      ) : (
                                        <Button
                                          size="small"
                                          onClick={() =>
                                            modal.confirm({
                                              title: t('control_confirm_pause', { name: row.name }),
                                              onOk: () => pauseSequence.mutateAsync(row.id),
                                            })
                                          }
                                        >
                                          {t('control_pause')}
                                        </Button>
                                      )}
                                      <Button
                                        size="small"
                                        onClick={() =>
                                          modal.confirm({
                                            title: t('control_confirm_stop', { name: row.name }),
                                            onOk: () => stopSequence.mutateAsync(row.id),
                                          })
                                        }
                                      >
                                        {t('control_stop')}
                                      </Button>
                                    </>
                                  ) : (
                                    <Button
                                      size="small"
                                      disabled={!row.enabled}
                                      onClick={() =>
                                        modal.confirm({
                                          title: t(
                                            row.loop || row.loop_enabled
                                              ? 'control_confirm_run_loop'
                                              : 'control_confirm_run',
                                            { name: row.name },
                                          ),
                                          onOk: () => runSequence.mutateAsync(row.id),
                                        })
                                      }
                                    >
                                      {t('control_run')}
                                    </Button>
                                  )}
                                  <Button size="small" onClick={() => openSequence(row)}>
                                    {t('control_edit')}
                                  </Button>
                                </>
                              ) : null}
                              {data?.can_delete ? (
                                <Button
                                  size="small"
                                  danger
                                  disabled={running}
                                  onClick={() =>
                                    modal.confirm({
                                      title: t('control_confirm_delete_sequence'),
                                      onOk: () => deleteSequence.mutateAsync(row.id),
                                    })
                                  }
                                >
                                  {t('control_delete')}
                                </Button>
                              ) : null}
                            </Space>
                          )
                        },
                      },
                    ]}
                  />
                </Card>
              ),
            },
            {
              key: 'rules',
              label: t('control_tab_rules'),
              children: (
                <Card>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12, gap: 12 }}>
                    <Typography.Text type="secondary">{t('control_rule_hint')}</Typography.Text>
                    {data?.can_create ? (
                      <Button type="primary" disabled={!engineOk} onClick={() => openRule()}>
                        {t('control_new_rule')}
                      </Button>
                    ) : null}
                  </div>
                  <Table
                    rowKey="id"
                    dataSource={data?.rules ?? []}
                    locale={{ emptyText: t('control_no_rules') }}
                    pagination={false}
                    columns={[
                      { title: t('control_name'), dataIndex: 'name' },
                      {
                        title: t('control_when'),
                        render: (_, row: ControlRule) =>
                          ruleCondSummary(row, commandNames, deviceNames, t),
                      },
                      {
                        title: t('control_then'),
                        render: (_, row) =>
                          ruleActionSummary(row, commandNames, sequenceNames, t),
                      },
                      {
                        title: t('control_last_triggered'),
                        render: (_, row) => row.last_triggered_at ?? row.last_fired_at ?? '—',
                      },
                      {
                        title: t('control_result'),
                        render: (_, row) => row.last_result ?? '—',
                      },
                      {
                        title: t('control_status'),
                        render: (_, row) =>
                          row.enabled ? (
                            <Tag color="success">{t('control_enabled')}</Tag>
                          ) : (
                            <Tag>{t('control_disabled_badge')}</Tag>
                          ),
                      },
                      {
                        title: t('control_actions'),
                        align: 'right',
                        render: (_, row) => (
                          <Space wrap>
                            {data?.can_update ? (
                              <Button size="small" onClick={() => openRule(row)}>
                                {t('control_edit')}
                              </Button>
                            ) : null}
                            {data?.can_delete ? (
                              <Button
                                size="small"
                                danger
                                onClick={() =>
                                  modal.confirm({
                                    title: t('control_confirm_delete_rule'),
                                    onOk: () => deleteRule.mutateAsync(row.id),
                                  })
                                }
                              >
                                {t('control_delete')}
                              </Button>
                            ) : null}
                          </Space>
                        ),
                      },
                    ]}
                  />
                </Card>
              ),
            },
            {
              key: 'logs',
              label: t('control_tab_logs'),
              children: (
                <Card>
                  <Table
                    rowKey={(row, i) => String(row.id ?? i)}
                    dataSource={data?.logs ?? []}
                    locale={{ emptyText: t('control_no_logs') }}
                    pagination={false}
                    columns={[
                      { title: t('control_time'), dataIndex: 'created_at' },
                      { title: t('control_command'), dataIndex: 'command_name' },
                      { title: t('control_device'), dataIndex: 'device_name' },
                      { title: t('control_source'), dataIndex: 'source' },
                      { title: t('control_value'), dataIndex: 'requested_value' },
                      { title: t('control_result'), dataIndex: 'result' },
                      { title: t('control_user'), dataIndex: 'user_name' },
                    ]}
                  />
                </Card>
              ),
            },
          ]}
        />
      )}

      <Modal
        title={t('control_group_modal')}
        open={groupOpen}
        onCancel={() => setGroupOpen(false)}
        onOk={() => groupForm.submit()}
        confirmLoading={saveGroup.isPending}
        okText={t('control_save')}
        cancelText={t('control_cancel')}
        footer={
          <Space style={{ width: '100%', justifyContent: 'space-between' }}>
            <span>
              {editingGroup?.id && data?.can_delete ? (
                <Button
                  danger
                  onClick={() =>
                    modal.confirm({
                      title: t('control_confirm_delete_group'),
                      onOk: async () => {
                        await deleteGroup.mutateAsync(editingGroup.id)
                        setGroupOpen(false)
                      },
                    })
                  }
                >
                  {t('control_delete')}
                </Button>
              ) : null}
            </span>
            <Space>
              <Button onClick={() => setGroupOpen(false)}>{t('control_cancel')}</Button>
              <Button type="primary" loading={saveGroup.isPending} onClick={() => groupForm.submit()}>
                {t('control_save')}
              </Button>
            </Space>
          </Space>
        }
      >
        <Form form={groupForm} layout="vertical" onFinish={(v) => saveGroup.mutate(v)}>
          <Form.Item name="name" label={t('control_name')} rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item name="kind" label={t('control_kind')} initialValue="building">
            <Select
              options={[
                { value: 'building', label: t('control_kind_building') },
                { value: 'room', label: t('control_kind_room') },
                { value: 'system', label: t('control_kind_system') },
                { value: 'generic', label: t('control_kind_other') },
              ]}
            />
          </Form.Item>
          <Form.Item name="description" label={t('control_description')}>
            <Input />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title={t('control_command_modal')}
        open={cmdOpen}
        onCancel={() => setCmdOpen(false)}
        onOk={() => cmdForm.submit()}
        confirmLoading={saveCommand.isPending}
        okText={t('control_save')}
        cancelText={t('control_cancel')}
        width={720}
      >
        <Form
          form={cmdForm}
          layout="vertical"
          onFinish={(v) => saveCommand.mutate(v)}
          onValuesChange={(changed) => {
            if ('device_id' in changed) {
              setWriteViaId(undefined)
              void loadDeviceCtx(Number(changed.device_id ?? 0))
            }
          }}
        >
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="name" label={t('control_name')} rules={[{ required: true }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="group_id" label={t('control_group')}>
                <Select
                  allowClear
                  placeholder={t('control_ungrouped')}
                  options={(data?.groups ?? []).map((g) => ({
                    value: g.id,
                    label: `${g.name} (${t(kindLabelKey(String(g.kind ?? 'generic')))})`,
                  }))}
                />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="device_id" label={t('control_device')} rules={[{ required: true }]} extra={t('control_device_hint')}>
                <Select
                  showSearch
                  optionFilterProp="label"
                  placeholder={t('control_select_device')}
                  options={(data?.devices ?? []).map((d) => ({ value: d.id, label: d.name }))}
                />
              </Form.Item>
            </Col>
            <Col span={24}>
              <Form.Item name="description" label={t('control_description')}>
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item label={t('control_write_via')} extra={t('control_write_via_hint')}>
                <Select
                  allowClear
                  placeholder={t('control_write_via_same')}
                  value={writeViaId}
                  onChange={(v) => {
                    setWriteViaId(v)
                    const deviceId = Number(cmdForm.getFieldValue('device_id') ?? 0)
                    void loadDeviceCtx(deviceId, v)
                  }}
                  options={(data?.devices ?? []).map((d) => ({ value: d.id, label: d.name }))}
                />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="write_target" label={t('control_write_actuator')} extra={t('control_write_hint')}>
                <Select
                  placeholder={deviceCtx ? t('control_select_actuator') : t('control_select_device_first')}
                  options={(deviceCtx?.write_targets ?? []).map((wt) => ({
                    value: wt.key,
                    label: `${wt.label} (${wt.hint ?? wt.key})`,
                  }))}
                  onChange={(key) => {
                    const target = deviceCtx?.write_targets.find((w) => w.key === key)
                    if (!target) return
                    cmdForm.setFieldsValue({
                      protocol: target.protocol,
                      on_value: target.write_config.on_value ?? '',
                      off_value: target.write_config.off_value ?? '',
                      write_config_json: JSON.stringify(target.write_config, null, 2),
                    })
                  }}
                />
              </Form.Item>
            </Col>
            <Form.Item name="protocol" hidden>
              <Input />
            </Form.Item>
            <Col span={12}>
              <Form.Item
                name="feedback_metric_key"
                label={t('control_feedback_metric')}
                extra={t('control_feedback_metric_hint')}
              >
                <Select
                  allowClear
                  placeholder={deviceCtx ? t('control_optional_status') : t('control_select_device_first')}
                  options={(deviceCtx?.feedback_metrics ?? []).map((m) => ({
                    value: m.key,
                    label: m.label || m.key,
                  }))}
                />
              </Form.Item>
            </Col>
            <Col span={24}>
              <Card size="small" title={t('control_set_value')} style={{ marginBottom: 12 }}>
                <Row gutter={12}>
                  <Col span={12}>
                    <Form.Item name="on_value" label={t('control_on')}>
                      <Input placeholder="e.g. 3" />
                    </Form.Item>
                  </Col>
                  <Col span={12}>
                    <Form.Item name="off_value" label={t('control_off')}>
                      <Input placeholder="e.g. 0" />
                    </Form.Item>
                  </Col>
                </Row>
                <Typography.Text type="secondary">{t('control_write_values_hint')}</Typography.Text>
              </Card>
            </Col>
            <Col span={24}>
              <Card size="small" title={t('control_feedback_map')} style={{ marginBottom: 12 }}>
                <Row gutter={12}>
                  <Col span={12}>
                    <Form.Item name="feedback_on" label={t('control_on_when')}>
                      <Input placeholder="e.g. 1, 3" />
                    </Form.Item>
                  </Col>
                  <Col span={12}>
                    <Form.Item name="feedback_on_label" label={t('control_on_label')}>
                      <Input placeholder={t('control_on')} />
                    </Form.Item>
                  </Col>
                  <Col span={12}>
                    <Form.Item name="feedback_off" label={t('control_off_when')}>
                      <Input placeholder="e.g. 0, 1" />
                    </Form.Item>
                  </Col>
                  <Col span={12}>
                    <Form.Item name="feedback_off_label" label={t('control_off_label')}>
                      <Input placeholder={t('control_off')} />
                    </Form.Item>
                  </Col>
                </Row>
                <Typography.Text type="secondary">{t('control_feedback_hint')}</Typography.Text>
              </Card>
            </Col>
            <Col span={24}>
              <Collapse
                items={[
                  {
                    key: 'adv',
                    label: t('control_advanced_json'),
                    children: (
                      <Form.Item name="write_config_json" label={t('control_write_config')}>
                        <Input.TextArea rows={5} className="font-monospace" />
                      </Form.Item>
                    ),
                  },
                ]}
              />
            </Col>
            <Col span={24}>
              <Form.Item name="enabled" label={t('control_enabled')} valuePropName="checked" initialValue>
                <Switch />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>

      <Modal
        title={t('control_schedule_modal')}
        open={schOpen}
        onCancel={() => setSchOpen(false)}
        onOk={() => schForm.submit()}
        confirmLoading={saveSchedule.isPending}
        okText={t('control_save')}
        cancelText={t('control_cancel')}
      >
        <Form form={schForm} layout="vertical" onFinish={(v) => saveSchedule.mutate(v)}>
          <Form.Item name="name" label={t('control_name')}>
            <Input placeholder="e.g. CRAC 01 weekday start" />
          </Form.Item>
          <Form.Item name="command_id" label={t('control_command')} rules={[{ required: true }]}>
            <Select
              placeholder={t('control_select_command')}
              options={(data?.commands ?? []).map((c) => ({ value: c.id, label: c.name }))}
            />
          </Form.Item>
          <Form.Item name="time" label={t('control_time_bkk')} initialValue="08:00" rules={[{ required: true }]}>
            <Input type="time" />
          </Form.Item>
          <Form.Item name="action" label={t('control_action')} initialValue="on">
            <Select
              options={[
                { value: 'on', label: t('control_on') },
                { value: 'off', label: t('control_off') },
              ]}
            />
          </Form.Item>
          <Form.Item name="days" label={t('control_days')} initialValue={[0, 1, 2, 3, 4, 5, 6]}>
            <Checkbox.Group
              options={dayLabels.map((label, i) => ({ label, value: i }))}
            />
          </Form.Item>
          <Form.Item name="enabled" label={t('control_enabled')} valuePropName="checked" initialValue>
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title={t('control_sequence_modal')}
        open={seqOpen}
        onCancel={() => setSeqOpen(false)}
        onOk={() => seqForm.submit()}
        confirmLoading={saveSequence.isPending}
        okText={t('control_save')}
        cancelText={t('control_cancel')}
        width={720}
      >
        <Form form={seqForm} layout="vertical" onFinish={(v) => saveSequence.mutate(v)}>
          <Form.Item name="name" label={t('control_name')} rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
            <Typography.Text>{t('control_steps')}</Typography.Text>
            <Button
              size="small"
              onClick={() =>
                setSeqSteps((prev) => [
                  ...prev,
                  { command_id: 0, action: 'on', wait: 'none', timeout_sec: 30, delay_sec: 0, delay_unit: 's' },
                ])
              }
            >
              {t('control_add_step')}
            </Button>
          </div>
          <Space direction="vertical" style={{ width: '100%', marginBottom: 12 }}>
            {seqSteps.map((step, idx) => (
              <Card key={idx} size="small">
                <Row gutter={8}>
                  <Col span={8}>
                    <Select
                      style={{ width: '100%' }}
                      placeholder={t('control_select_command')}
                      value={step.command_id || undefined}
                      options={(data?.commands ?? []).map((c) => ({ value: c.id, label: c.name }))}
                      onChange={(v) =>
                        setSeqSteps((prev) => prev.map((s, i) => (i === idx ? { ...s, command_id: v } : s)))
                      }
                    />
                  </Col>
                  <Col span={4}>
                    <Select
                      style={{ width: '100%' }}
                      value={step.action ?? 'on'}
                      options={[
                        { value: 'on', label: t('control_on') },
                        { value: 'off', label: t('control_off') },
                      ]}
                      onChange={(v) =>
                        setSeqSteps((prev) => prev.map((s, i) => (i === idx ? { ...s, action: v } : s)))
                      }
                    />
                  </Col>
                  <Col span={4}>
                    <Select
                      style={{ width: '100%' }}
                      value={step.wait ?? 'none'}
                      options={[
                        { value: 'none', label: t('control_none') },
                        { value: 'on', label: t('control_until_on') },
                        { value: 'off', label: t('control_until_off') },
                      ]}
                      onChange={(v) =>
                        setSeqSteps((prev) => prev.map((s, i) => (i === idx ? { ...s, wait: v } : s)))
                      }
                    />
                  </Col>
                  <Col span={4}>
                    <InputNumber
                      style={{ width: '100%' }}
                      min={0}
                      placeholder={t('control_timeout_s')}
                      value={step.timeout_sec ?? 30}
                      onChange={(v) =>
                        setSeqSteps((prev) =>
                          prev.map((s, i) => (i === idx ? { ...s, timeout_sec: Number(v ?? 0) } : s)),
                        )
                      }
                    />
                  </Col>
                  <Col span={4}>
                    <Button danger onClick={() => setSeqSteps((prev) => prev.filter((_, i) => i !== idx))}>
                      {t('control_remove')}
                    </Button>
                  </Col>
                </Row>
              </Card>
            ))}
          </Space>
          <Typography.Paragraph type="secondary">{t('control_seq_wait_hint')}</Typography.Paragraph>
          <Form.Item name="loop" valuePropName="checked">
            <Checkbox>{t('control_loop_checkbox')}</Checkbox>
          </Form.Item>
          <Form.Item noStyle shouldUpdate={(prev, cur) => prev.loop !== cur.loop}>
            {({ getFieldValue }) =>
              getFieldValue('loop') ? (
                <Row gutter={12}>
                  <Col span={12}>
                    <Form.Item
                      name="loop_count"
                      label={t('control_repeat_count')}
                      extra={t('control_loop_until_hint')}
                    >
                      <InputNumber min={0} max={9999} style={{ width: '100%' }} />
                    </Form.Item>
                  </Col>
                  <Col span={12}>
                    <Form.Item name="loop_delay_sec" label={t('control_delay_restart')}>
                      <InputNumber min={0} max={86400} style={{ width: '100%' }} addonAfter={t('control_sec')} />
                    </Form.Item>
                  </Col>
                </Row>
              ) : null
            }
          </Form.Item>
          <Form.Item name="enabled" label={t('control_enabled')} valuePropName="checked" initialValue>
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title={t('control_rule_modal')}
        open={ruleOpen}
        onCancel={() => setRuleOpen(false)}
        onOk={() => ruleForm.submit()}
        confirmLoading={saveRule.isPending}
        okText={t('control_save')}
        cancelText={t('control_cancel')}
        width={720}
      >
        <Form
          form={ruleForm}
          layout="vertical"
          onFinish={(v) => saveRule.mutate(v)}
          onValuesChange={(changed) => {
            if ('action_kind' in changed) {
              setRuleActionKind(changed.action_kind === 'sequence' ? 'sequence' : 'command')
            }
          }}
        >
          <Form.Item name="name" label={t('control_name')} rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
            <Typography.Text>{t('control_when')}</Typography.Text>
            <Button
              size="small"
              onClick={() =>
                setRuleConds((prev) => [
                  ...prev,
                  { type: 'command_status', command_id: 0, state: 'off' },
                ])
              }
            >
              {t('control_add_condition')}
            </Button>
          </div>
          <Form.Item name="match" initialValue="all">
            <Select
              style={{ maxWidth: 260, marginBottom: 8 }}
              options={[
                { value: 'all', label: t('control_match_all_opt') },
                { value: 'any', label: t('control_match_any_opt') },
              ]}
            />
          </Form.Item>
          <Space direction="vertical" style={{ width: '100%', marginBottom: 12 }}>
            {ruleConds.map((cond, idx) => (
              <Card key={idx} size="small">
                <Row gutter={8}>
                  <Col span={6}>
                    <Select
                      style={{ width: '100%' }}
                      value={String(cond.type ?? 'command_status')}
                      options={[
                        { value: 'command_status', label: t('control_command_status') },
                        { value: 'alarm', label: t('control_active_alarm') },
                        { value: 'metric', label: t('control_metric_value') },
                      ]}
                      onChange={(v) =>
                        setRuleConds((prev) =>
                          prev.map((c, i) => (i === idx ? { ...c, type: v } : c)),
                        )
                      }
                    />
                  </Col>
                  {String(cond.type) === 'command_status' || !cond.type ? (
                    <>
                      <Col span={8}>
                        <Select
                          style={{ width: '100%' }}
                          placeholder={t('control_select_command')}
                          value={Number(cond.command_id) || undefined}
                          options={(data?.commands ?? []).map((c) => ({ value: c.id, label: c.name }))}
                          onChange={(v) =>
                            setRuleConds((prev) =>
                              prev.map((c, i) => (i === idx ? { ...c, command_id: v } : c)),
                            )
                          }
                        />
                      </Col>
                      <Col span={6}>
                        <Select
                          style={{ width: '100%' }}
                          value={String(cond.state ?? 'off')}
                          options={[
                            { value: 'on', label: t('control_on') },
                            { value: 'off', label: t('control_off') },
                          ]}
                          onChange={(v) =>
                            setRuleConds((prev) =>
                              prev.map((c, i) => (i === idx ? { ...c, state: v } : c)),
                            )
                          }
                        />
                      </Col>
                    </>
                  ) : (
                    <>
                      <Col span={6}>
                        <Select
                          style={{ width: '100%' }}
                          placeholder={t('control_select_device')}
                          value={Number(cond.device_id) || undefined}
                          options={(data?.devices ?? []).map((d) => ({ value: d.id, label: d.name }))}
                          onChange={(v) =>
                            setRuleConds((prev) =>
                              prev.map((c, i) => (i === idx ? { ...c, device_id: v } : c)),
                            )
                          }
                        />
                      </Col>
                      {String(cond.type) === 'metric' ? (
                        <>
                          <Col span={4}>
                            <Input
                              placeholder="key"
                              value={String(cond.key ?? '')}
                              onChange={(e) =>
                                setRuleConds((prev) =>
                                  prev.map((c, i) => (i === idx ? { ...c, key: e.target.value } : c)),
                                )
                              }
                            />
                          </Col>
                          <Col span={3}>
                            <Select
                              style={{ width: '100%' }}
                              value={String(cond.op ?? '>')}
                              options={['>', '>=', '<', '<=', '==', '!='].map((op) => ({
                                value: op,
                                label: op,
                              }))}
                              onChange={(v) =>
                                setRuleConds((prev) =>
                                  prev.map((c, i) => (i === idx ? { ...c, op: v } : c)),
                                )
                              }
                            />
                          </Col>
                          <Col span={3}>
                            <Input
                              value={String(cond.value ?? '')}
                              onChange={(e) =>
                                setRuleConds((prev) =>
                                  prev.map((c, i) => (i === idx ? { ...c, value: e.target.value } : c)),
                                )
                              }
                            />
                          </Col>
                        </>
                      ) : (
                        <Col span={8}>
                          <Input
                            placeholder={t('control_metric_key_opt')}
                            value={String(cond.metric_key ?? '')}
                            onChange={(e) =>
                              setRuleConds((prev) =>
                                prev.map((c, i) =>
                                  i === idx ? { ...c, metric_key: e.target.value } : c,
                                ),
                              )
                            }
                          />
                        </Col>
                      )}
                    </>
                  )}
                  <Col span={2}>
                    <Button danger onClick={() => setRuleConds((prev) => prev.filter((_, i) => i !== idx))}>
                      {t('control_remove')}
                    </Button>
                  </Col>
                </Row>
              </Card>
            ))}
          </Space>
          <Typography.Paragraph type="secondary">{t('control_rule_examples')}</Typography.Paragraph>
          <Form.Item name="action_kind" label={t('control_then')} initialValue="command">
            <Select
              options={[
                { value: 'command', label: t('control_commands_plural') },
                { value: 'sequence', label: t('control_sequence') },
              ]}
            />
          </Form.Item>
          {ruleActionKind === 'sequence' ? (
            <Form.Item name="sequence_id" label={t('control_sequence')} rules={[{ required: true }]}>
              <Select
                placeholder={t('control_select_sequence')}
                options={(data?.sequences ?? []).map((s) => ({ value: s.id, label: s.name }))}
              />
            </Form.Item>
          ) : (
            <>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
                <Typography.Text>{t('control_trig_commands')}</Typography.Text>
                <Button
                  size="small"
                  onClick={() =>
                    setRuleActionCmds((prev) => [...prev, { command_id: 0, action: 'on' }])
                  }
                >
                  {t('control_add_command')}
                </Button>
              </div>
              <Space direction="vertical" style={{ width: '100%', marginBottom: 12 }}>
                {ruleActionCmds.map((cmd, idx) => (
                  <Row key={idx} gutter={8}>
                    <Col span={14}>
                      <Select
                        style={{ width: '100%' }}
                        value={cmd.command_id || undefined}
                        options={(data?.commands ?? []).map((c) => ({ value: c.id, label: c.name }))}
                        onChange={(v) =>
                          setRuleActionCmds((prev) =>
                            prev.map((c, i) => (i === idx ? { ...c, command_id: v } : c)),
                          )
                        }
                      />
                    </Col>
                    <Col span={6}>
                      <Select
                        style={{ width: '100%' }}
                        value={cmd.action}
                        options={[
                          { value: 'on', label: t('control_on') },
                          { value: 'off', label: t('control_off') },
                        ]}
                        onChange={(v) =>
                          setRuleActionCmds((prev) =>
                            prev.map((c, i) => (i === idx ? { ...c, action: v } : c)),
                          )
                        }
                      />
                    </Col>
                    <Col span={4}>
                      <Button
                        danger
                        onClick={() => setRuleActionCmds((prev) => prev.filter((_, i) => i !== idx))}
                      >
                        {t('control_remove')}
                      </Button>
                    </Col>
                  </Row>
                ))}
              </Space>
            </>
          )}
          <Form.Item
            name="cooldown_sec"
            label={t('control_cooldown_after')}
            extra={t('control_cooldown_hint')}
            initialValue={300}
          >
            <InputNumber min={0} max={86400} style={{ width: '100%' }} addonAfter={t('control_sec')} />
          </Form.Item>
          <Form.Item name="enabled" label={t('control_enabled')} valuePropName="checked" initialValue>
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </>
  )
}
