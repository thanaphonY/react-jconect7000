import { useQuery } from '@tanstack/react-query'
import {
  Button,
  Card,
  Col,
  Modal,
  Row,
  Select,
  Space,
  Table,
  Tabs,
  Tag,
  Typography,
} from 'antd'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { useRealtimeMetrics } from '../Realtime'
import { dayjs } from '../lib/dayjs'
import {
  fetchDevice,
  fetchDeviceAlarmLogs,
  fetchDeviceLatestMetrics,
} from '../features/devices/api'
import { DeviceImageThumb } from '../features/devices/DeviceImageThumb'
import { PemonDashboard } from '../features/devices/PemonDashboard'
import type { Metric, MetricTrigger } from '../features/devices/types'

const SECRET_KEYS = new Set(['password', 'auth_pass', 'priv_pass', 'community'])

function isEnabledFlag(value: unknown): boolean {
  return value === true || value === 'true' || value === 1 || value === '1' || value == null
}

function formatLatestValue(
  latest:
    | {
        value: number | null
        valueText: string | null
        valueBool: boolean | null
        valueJson: unknown
      }
    | undefined,
  datatype: string | undefined,
): { has: boolean; text: string } {
  if (!latest) return { has: false, text: '—' }

  const dt = String(datatype || '').toLowerCase().trim()
  const boolTruth = (v: unknown): boolean | null => {
    if (typeof v === 'boolean') return v
    if (v === 1 || v === '1' || v === 't' || v === 'true' || v === 'TRUE') return true
    if (v === 0 || v === '0' || v === 'f' || v === 'false' || v === 'FALSE') return false
    return null
  }

  if (dt === 'bool' || dt === 'boolean') {
    let b = boolTruth(latest.valueBool)
    if (b === null) b = boolTruth(latest.value)
    if (b === null && latest.valueText) b = boolTruth(latest.valueText)
    if (b === null) return { has: false, text: '—' }
    return { has: true, text: b ? 'True' : 'False' }
  }

  if (dt === 'string') {
    if (latest.valueText != null && latest.valueText !== '') {
      return { has: true, text: String(latest.valueText) }
    }
    if (latest.value != null && latest.value !== ('' as unknown)) {
      return { has: true, text: String(latest.value) }
    }
    return { has: false, text: '—' }
  }

  if (dt === 'json') {
    if (latest.valueJson != null && latest.valueJson !== '') {
      const text =
        typeof latest.valueJson === 'string'
          ? latest.valueJson
          : JSON.stringify(latest.valueJson)
      return { has: true, text }
    }
    if (latest.valueText) return { has: true, text: latest.valueText }
    return { has: false, text: '—' }
  }

  if (latest.value != null && !(typeof latest.value === 'string' && String(latest.value).trim() === '')) {
    if (dt === 'int' || dt === 'int16' || dt === 'uint16' || dt === 'int32' || dt === 'uint32') {
      return { has: true, text: String(Math.trunc(Number(latest.value))) }
    }
    return { has: true, text: Number(latest.value).toFixed(2) }
  }

  if (latest.valueText) return { has: true, text: latest.valueText }
  const b = boolTruth(latest.valueBool)
  if (b !== null) return { has: true, text: b ? 'True' : 'False' }
  return { has: false, text: '—' }
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return '-'
  const d = dayjs(value)
  if (!d.isValid()) return String(value)
  return d.tz('Asia/Bangkok').format('DD/MM/YYYY, HH:mm:ss')
}

function severityColor(severity: string): string {
  const s = String(severity || '').toLowerCase()
  if (s === 'critical' || s === 'high') return 'red'
  if (s === 'warning' || s === 'medium') return 'orange'
  if (s === 'info' || s === 'low') return 'blue'
  return 'default'
}

function statusColor(status: string): string {
  const s = String(status || '').toLowerCase()
  if (s === 'active') return 'red'
  if (s === 'acknowledged') return 'gold'
  if (s === 'resolved') return 'green'
  return 'default'
}

function metricTriggers(m: Metric): MetricTrigger[] {
  if (Array.isArray(m.triggers)) return m.triggers
  if (m.trigger && typeof m.trigger === 'object' && !Array.isArray(m.trigger)) {
    return [m.trigger as MetricTrigger]
  }
  return []
}

function DeviceDataPane({
  deviceId,
  metrics,
  protocol,
}: {
  deviceId: number
  metrics: Metric[]
  protocol: string
}) {
  const { t } = useTranslation()
  const enabledMetrics = useMemo(
    () =>
      metrics.filter((m) => {
        if (!m?.key) return false
        return isEnabledFlag(m.enable)
      }),
    [metrics],
  )

  const latest = useQuery({
    queryKey: ['device-metrics-latest', deviceId],
    queryFn: () => fetchDeviceLatestMetrics(deviceId),
    refetchInterval: 5000,
    enabled: enabledMetrics.length > 0,
  })

  const latestMap = useMemo(() => {
    type LatestRow = {
      key: string
      value: number | null
      valueText: string | null
      valueBool: boolean | null
      valueJson: unknown
      timestamp: string | null
    }
    const map = new Map<string, LatestRow>()
    for (const row of latest.data?.latest ?? []) {
      map.set(row.key, row)
    }
    return map
  }, [latest.data])

  const displayedMetrics = enabledMetrics.flatMap((metric) => {
    if (!metric.key || protocol.toLowerCase() !== 'pemon' || !/\{[nmk]\}/.test(metric.key)) return [metric]
    const pattern = metric.key
      .split(/(\{[nmk]\})/)
      .map((part) => /^\{[nmk]\}$/.test(part) ? '[0-9]+' : part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
      .join('')
    const matcher = new RegExp(`^${pattern}$`)
    const matching = [...latestMap.keys()].filter((key) => matcher.test(key))
    return matching.length ? matching.map((key) => ({ ...metric, key })) : [metric]
  })

  useRealtimeMetrics(deviceId, displayedMetrics.flatMap(({ key }) => key && !/\{[nmk]\}/.test(key) ? [key] : []))

  const isRtsp = protocol === 'rtsp'

  if (!enabledMetrics.length && !isRtsp) {
    return <Typography.Text type="secondary">{t('no_data_found')}</Typography.Text>
  }

  return (
    <Table
      rowKey={(row) => String(row.key)}
      size="small"
      pagination={false}
      loading={latest.isLoading}
      dataSource={displayedMetrics}
      locale={{ emptyText: t('no_data_found') }}
      columns={[
        {
          title: t('metrics'),
          render: (_v, row) => (
            <Typography.Text strong>{row.label || row.key}</Typography.Text>
          ),
        },
        {
          title: t('value'),
          align: 'right',
          render: (_v, row) => {
            const formatted = formatLatestValue(latestMap.get(String(row.key)), row.datatype)
            const unit = row.unit || ''
            return (
              <span>
                <Typography.Text strong>{formatted.text}</Typography.Text>
                {unit && unit !== '-' && formatted.has ? (
                  <Typography.Text type="secondary" style={{ marginLeft: 4, fontSize: 12 }}>
                    {unit}
                  </Typography.Text>
                ) : null}
              </span>
            )
          },
        },
        {
          title: t('last_update'),
          align: 'center',
          render: (_v, row) => {
            const ts = latestMap.get(String(row.key))?.timestamp
            return (
              <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                {ts ? formatDateTime(ts) : '—'}
              </Typography.Text>
            )
          },
        },
        {
          title: t('description'),
          render: (_v, row) => {
            const desc = String(row.description || '').trim()
            return desc ? (
              <Typography.Text type="secondary" style={{ fontSize: 13 }} ellipsis={{ tooltip: desc }}>
                {desc}
              </Typography.Text>
            ) : (
              <Typography.Text type="secondary">—</Typography.Text>
            )
          },
        },
      ]}
    />
  )
}

function DeviceAlarmLogPane({ deviceId }: { deviceId: number }) {
  const { t } = useTranslation()
  const [range, setRange] = useState('last_30_days')
  const [statusFilter, setStatusFilter] = useState('all')
  const [page, setPage] = useState(1)

  const alarms = useQuery({
    queryKey: ['device-alarm-logs', deviceId, range, statusFilter, page],
    refetchInterval: 5000,
    queryFn: () =>
      fetchDeviceAlarmLogs({
        deviceId,
        page,
        pageSize: 10,
        range,
        statusFilter,
      }),
  })

  const totalPages = Math.max(1, alarms.data?.totalPages ?? 1)
  const total = alarms.data?.total ?? 0

  return (
    <Space direction="vertical" size={12} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
        <Space wrap>
          <Select
            size="small"
            style={{ width: 'auto', minWidth: 140 }}
            value={range}
            onChange={(v) => {
              setPage(1)
              setRange(v)
            }}
            options={[
              { value: 'all', label: t('all') },
              { value: 'today', label: t('today') },
              { value: 'last_7_days', label: t('last_7_days') },
              { value: 'last_30_days', label: t('last_30_days') },
            ]}
          />
          <Select
            size="small"
            style={{ width: 'auto', minWidth: 140 }}
            value={statusFilter}
            onChange={(v) => {
              setPage(1)
              setStatusFilter(v)
            }}
            options={[
              { value: 'all', label: t('all_status') },
              { value: 'active', label: t('status_active') },
              { value: 'acknowledged', label: t('acknowledged') },
              { value: 'resolved', label: t('resolved') },
            ]}
          />
        </Space>
        <Link to={`/alarmlogs?device_filter=${deviceId}`} target="_blank" rel="noreferrer">
          <Button size="small">{t('view_all_alarm_logs')}</Button>
        </Link>
      </Space>

      <Table
        rowKey="id"
        size="small"
        loading={alarms.isLoading}
        dataSource={alarms.data?.logs ?? []}
        pagination={false}
        locale={{ emptyText: t('no_data_found') }}
        columns={[
          {
            // Device PHP lang: Date/Time (not global en date_time "Date Time")
            title: 'Date/Time',
            dataIndex: 'createdAt',
            render: (v: string | null) => formatDateTime(v),
          },
          {
            title: t('metrics'),
            dataIndex: 'metricKey',
            render: (v: string) => v || '-',
          },
          {
            title: t('severity'),
            dataIndex: 'severity',
            render: (v: string) => <Tag color={severityColor(v)}>{v || '-'}</Tag>,
          },
          {
            // Device PHP lang: Message; keep global en message as Massage for alarmlogs parity
            title: 'Message',
            dataIndex: 'message',
            ellipsis: true,
            render: (v: string) => {
              const msg = v || '-'
              return msg.length > 80 ? `${msg.slice(0, 80)}...` : msg
            },
          },
          {
            title: t('status'),
            dataIndex: 'status',
            render: (v: string) => <Tag color={statusColor(v)}>{v || '-'}</Tag>,
          },
        ]}
      />

      <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
        <Typography.Text type="secondary">
          {(t('page_of') || 'Page {page} / {pages} ({total} records)')
            .replace('{page}', String(page))
            .replace('{pages}', String(totalPages))
            .replace('{total}', String(total))}
        </Typography.Text>
        <Space.Compact>
          <Button size="small" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            {t('previous_page')}
          </Button>
          <Button
            size="small"
            disabled={page >= totalPages}
            onClick={() => setPage((p) => p + 1)}
          >
            {t('next_page')}
          </Button>
        </Space.Compact>
      </Space>
    </Space>
  )
}

function DeviceMetricsPane({ metrics }: { metrics: Metric[] }) {
  const { t } = useTranslation()
  const [triggerModal, setTriggerModal] = useState<{
    key: string
    label: string
    triggers: MetricTrigger[]
  } | null>(null)

  const rows = metrics.filter((m) => m?.key)

  if (!rows.length) {
    return <Typography.Text type="secondary">{t('no_data_found')}</Typography.Text>
  }

  return (
    <>
      <Table
        rowKey={(row, index) => String(row.key || index)}
        size="small"
        pagination={false}
        dataSource={rows}
        columns={[
          {
            title: t('key'),
            dataIndex: 'key',
          },
          {
            title: t('label'),
            render: (_v, row) => row.label || row.key,
          },
          {
            title: t('unit'),
            dataIndex: 'unit',
            render: (v: string | undefined) => v || '-',
          },
          {
            title: t('data_type'),
            dataIndex: 'datatype',
            render: (v: string | undefined) => v || '-',
          },
          {
            title: t('triggers'),
            render: (_v, row) => {
              const triggers = metricTriggers(row)
              const triggerCount = triggers.length
              if (!triggerCount) {
                return <Typography.Text type="secondary">—</Typography.Text>
              }
              const activeTriggerCount = triggers.filter((tr) => isEnabledFlag(tr.enable)).length
              return (
                <Button
                  type="link"
                  style={{ padding: 0, height: 'auto' }}
                  onClick={() =>
                    setTriggerModal({
                      key: String(row.key),
                      label: String(row.label || row.key),
                      triggers,
                    })
                  }
                >
                  <Space size={4}>
                    <Tag>{triggerCount}</Tag>
                    {activeTriggerCount > 0 ? (
                      <Tag color="gold">
                        {activeTriggerCount} {t('status_active')}
                      </Tag>
                    ) : null}
                  </Space>
                </Button>
              )
            },
          },
          {
            title: t('status'),
            render: (_v, row) =>
              isEnabledFlag(row.enable) ? (
                <Tag color="success">{t('status_active')}</Tag>
              ) : (
                <Tag>{t('status_inactive')}</Tag>
              ),
          },
        ]}
      />

      <Modal
        open={!!triggerModal}
        title={`${t('trigger')} · ${triggerModal?.label || triggerModal?.key || ''}`}
        onCancel={() => setTriggerModal(null)}
        footer={[
          <Button key="close" onClick={() => setTriggerModal(null)}>
            {t('close')}
          </Button>,
        ]}
        width={720}
      >
        {!triggerModal?.triggers.length ? (
          <Typography.Text type="secondary">—</Typography.Text>
        ) : (
          <Space direction="vertical" size={12} style={{ width: '100%' }}>
            {triggerModal.triggers.map((trigger, index) => {
              const clear =
                trigger.clear_condition && typeof trigger.clear_condition === 'object'
                  ? trigger.clear_condition
                  : {}
              const active = isEnabledFlag(trigger.enable)
              return (
                <Card key={index} size="small">
                  <Space style={{ width: '100%', justifyContent: 'space-between' }}>
                    <Typography.Text strong>
                      {t('trigger')} {index + 1}
                    </Typography.Text>
                    <Tag color={active ? 'gold' : 'default'}>
                      {active ? t('status_active') : t('status_inactive')}
                    </Tag>
                  </Space>
                  <Row gutter={[12, 8]} style={{ marginTop: 8 }}>
                    <Col xs={24} md={6}>
                      <Typography.Text type="secondary">{t('trigger_operator')}</Typography.Text>
                      <div>{trigger.operator || '—'}</div>
                    </Col>
                    <Col xs={24} md={6}>
                      <Typography.Text type="secondary">{t('trigger_value')}</Typography.Text>
                      <div>
                        {trigger.value != null && trigger.value !== '' ? String(trigger.value) : '—'}
                      </div>
                    </Col>
                    <Col xs={24} md={12}>
                      <Typography.Text type="secondary">{t('trigger_message')}</Typography.Text>
                      <div>{trigger.message || '—'}</div>
                    </Col>
                    <Col xs={24} md={6}>
                      <Typography.Text type="secondary">
                        {t('trigger_clear_condition')} · {t('trigger_operator')}
                      </Typography.Text>
                      <div>{clear.operator || '—'}</div>
                    </Col>
                    <Col xs={24} md={6}>
                      <Typography.Text type="secondary">
                        {t('trigger_clear_condition')} · {t('trigger_value')}
                      </Typography.Text>
                      <div>
                        {clear.value != null && clear.value !== '' ? String(clear.value) : '—'}
                      </div>
                    </Col>
                    <Col xs={24} md={12}>
                      <Typography.Text type="secondary">
                        {t('trigger_clear_condition')} · {t('trigger_message')}
                      </Typography.Text>
                      <div>{clear.message || '—'}</div>
                    </Col>
                    <Col xs={24} md={6}>
                      <Typography.Text type="secondary">{t('trigger_severity')}</Typography.Text>
                      <div>{trigger.severity || '—'}</div>
                    </Col>
                  </Row>
                </Card>
              )
            })}
          </Space>
        )}
      </Modal>
    </>
  )
}

function DeviceCommunicationPane({
  protocol,
  communicationConfig,
}: {
  protocol: string
  communicationConfig: Record<string, unknown>
}) {
  const { t } = useTranslation()
  const entries = Object.entries(communicationConfig || {})

  return (
    <Space direction="vertical" size={12} style={{ width: '100%' }}>
      <Typography.Paragraph style={{ marginBottom: 0 }}>
        <Typography.Text strong>{t('protocol')}:</Typography.Text>{' '}
        {(protocol || '-').toUpperCase()}
      </Typography.Paragraph>
      {!entries.length ? (
        <Typography.Text type="secondary">{t('no_data_found')}</Typography.Text>
      ) : (
        <Table
          rowKey={(row) => row.key}
          size="small"
          pagination={false}
          dataSource={entries.map(([key, value]) => {
            let displayVal: string
            if (value !== null && typeof value === 'object') {
              displayVal = JSON.stringify(value)
            } else {
              displayVal = value == null ? '' : String(value)
            }
            if (SECRET_KEYS.has(String(key).toLowerCase()) && displayVal !== '') {
              displayVal = '********'
            }
            return { key, value: displayVal !== '' ? displayVal : '-' }
          })}
          columns={[
            {
              title: t('key'),
              dataIndex: 'key',
              render: (v: string) => <Typography.Text strong>{v}</Typography.Text>,
            },
            { title: t('value'), dataIndex: 'value' },
          ]}
        />
      )}
    </Space>
  )
}

export function DeviceViewPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const params = useParams()
  const deviceId = Number(params.id)

  const device = useQuery({
    queryKey: ['device', deviceId],
    enabled: Number.isInteger(deviceId),
    queryFn: () => fetchDevice(deviceId),
  })

  const data = device.data
  const config = (data?.config || {}) as Record<string, unknown>
  const customFields =
    (config.custom_fields as Record<string, string> | undefined) || data?.customFields || {}
  const metrics = Array.isArray(data?.metrics) ? (data.metrics as Metric[]) : []
  const protocol = String(
    data?.protocol ||
      (data?.communicationConfig as { protocol?: string } | undefined)?.protocol ||
      '',
  ).toLowerCase()

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {t('view_device_page')}
        </Typography.Title>
        <Space>
          <Button onClick={() => navigate('/device')}>{t('back')}</Button>
          {data && (
            <Button
              type="primary"
              icon={<i className="bi bi-pencil" />}
              onClick={() => navigate(`/device/edit/${data.id}`)}
            >
              {t('edit')}
            </Button>
          )}
        </Space>
      </Space>

      <Card title={t('device_information')} loading={device.isLoading}>
        <Row gutter={16}>
          <Col xs={24} md={6}>
            <DeviceImageThumb
              imageUrl={data?.imageUrl}
              image={data?.image}
              templateImage={data?.templateImage}
              size={200}
            />
          </Col>
          <Col xs={24} md={12}>
            <p>
              <strong>{t('name')} :</strong> {data?.name || '-'}
            </p>
            <p>
              <strong>{t('serial_number')} : </strong> {data?.serialNumber || '-'}
            </p>
            <p>
              <strong>{t('location')} : </strong>{' '}
              {(data?.location || '-').split('\n').map((line, i) => (
                <span key={i}>
                  {i > 0 ? <br /> : null}
                  {line}
                </span>
              ))}
            </p>
            <p>
              <strong>{t('description')} : </strong>{' '}
              {(data?.description || '-').split('\n').map((line, i) => (
                <span key={i}>
                  {i > 0 ? <br /> : null}
                  {line}
                </span>
              ))}
            </p>
            <p>
              <strong>{t('protocol')} :</strong> {(protocol || '-').toUpperCase()}
            </p>
            {Object.keys(customFields).length > 0 && (
              <div style={{ marginTop: 16, maxWidth: 420 }}>
                <Typography.Title level={5} style={{ marginBottom: 8 }}>
                  <i className="bi bi-tags" style={{ marginRight: 6 }} />
                  {t('custom_field')}
                </Typography.Title>
                {Object.entries(customFields).map(([k, v]) => (
                  <div
                    key={k}
                    style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      padding: '4px 0',
                      borderBottom: '1px solid #eee',
                    }}
                  >
                    <strong style={{ minWidth: 120 }}>{k}:</strong>
                    <span style={{ flex: 1, textAlign: 'right' }}>{v}</span>
                  </div>
                ))}
              </div>
            )}
          </Col>
        </Row>
      </Card>

      <Card styles={{ body: { paddingTop: 8 } }}>
        <Tabs
          key={data && protocol.toLowerCase() === 'pemon' ? `pemon-${data.id}` : 'device'}
          items={[
            ...(data && protocol.toLowerCase() === 'pemon' ? [{
              key: 'pemon-dashboard',
              label: 'Dashboard',
              children: <PemonDashboard deviceId={data.id} />,
            }] : []),
            {
              key: 'device-data',
              label: t('device_data'),
              children: data ? (
                <DeviceDataPane deviceId={data.id} metrics={metrics} protocol={protocol} />
              ) : null,
            },
            {
              key: 'alarm-log',
              label: t('alarm_logs'),
              children: data ? <DeviceAlarmLogPane deviceId={data.id} /> : null,
            },
            {
              key: 'metrics',
              label: t('metrics'),
              children: <DeviceMetricsPane metrics={metrics} />,
            },
            {
              key: 'communication',
              label: t('communication'),
              children: (
                <DeviceCommunicationPane
                  protocol={protocol}
                  communicationConfig={
                    (data?.communicationConfig as Record<string, unknown>) || {}
                  }
                />
              ),
            },
          ]}
        />
      </Card>
    </Space>
  )
}
