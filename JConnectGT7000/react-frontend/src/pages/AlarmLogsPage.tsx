import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Button,
  Card,
  Col,
  DatePicker,
  Form,
  Image,
  Input,
  Modal,
  Row,
  Select,
  Space,
  Table,
  Tag,
  Typography,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import type { Key } from 'react'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { apiRequest } from '../api/client'
import { dayjs } from '../lib/dayjs'

type AlarmLog = {
  id: number
  createdAt: string | null
  deviceName: string
  metricKey: string
  severity: string
  message: string
  status: string
  snapshotPath: string | null
}

type Page = {
  logs: AlarmLog[]
  total: number
  totalPages: number
  page: number
  pageSize: number
}

type Stats = {
  totalAlarms: number
  activeAlarms: number
  severityBreakdown: Array<{ severity: string; count: number }>
  deviceBreakdown: Array<{ deviceName: string; count: number }>
}

type Filters = {
  devices: Array<{ id: number; name: string }>
  severities: string[]
  statuses: string[]
}

type AlarmDetail = {
  alarm: AlarmLog & {
    updatedAt: string | null
    deviceId: number | null
    value: string | null
    note: string | null
    snapshotCameraDeviceId: number | null
  }
  relatedAlarms: Array<{
    id: number
    createdAt: string | null
    severity: string
    status: string
    message: string
  }>
  dispatchHistory: Array<{
    id: number
    channelName: string
    status: string
    error: string | null
    createdAt: string | null
  }>
}

function severityColor(severity: string): string {
  switch ((severity || '').toLowerCase()) {
    case 'critical':
      return 'red'
    case 'major':
    case 'high':
      return 'orange'
    case 'warning':
    case 'medium':
      return 'gold'
    case 'minor':
    case 'low':
      return 'blue'
    default:
      return 'default'
  }
}

function statusColor(status: string): string {
  switch ((status || '').toLowerCase()) {
    case 'active':
      return 'red'
    case 'acknowledged':
      return 'gold'
    case 'resolved':
      return 'green'
    case 'queued':
      return 'blue'
    default:
      return 'default'
  }
}

export function AlarmLogsPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [range, setRange] = useState('last_30_days')
  const [startDate, setStartDate] = useState<string | null>(null)
  const [endDate, setEndDate] = useState<string | null>(null)
  const [deviceFilter, setDeviceFilter] = useState('all')
  const [severityFilter, setSeverityFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [applied, setApplied] = useState({
    range: 'last_30_days',
    startDate: null as string | null,
    endDate: null as string | null,
    deviceFilter: 'all',
    severityFilter: 'all',
    statusFilter: 'all',
    search: '',
  })
  const [selectedKeys, setSelectedKeys] = useState<Key[]>([])
  const [detailId, setDetailId] = useState<number | null>(null)
  const [ackOpen, setAckOpen] = useState(false)
  const [resolveOpen, setResolveOpen] = useState(false)
  const [ackForm] = Form.useForm<{ note?: string }>()
  const [resolveForm] = Form.useForm<{ resolution_note: string }>()

  const queryParams = useMemo(() => {
    const params = new URLSearchParams({
      page: String(page),
      pageSize: String(pageSize),
      range: applied.range,
    })
    if (applied.startDate) params.set('startDate', applied.startDate)
    if (applied.endDate) params.set('endDate', applied.endDate)
    if (applied.deviceFilter !== 'all') params.set('device_filter', applied.deviceFilter)
    if (applied.severityFilter !== 'all') params.set('severity_filter', applied.severityFilter)
    if (applied.statusFilter !== 'all') params.set('status_filter', applied.statusFilter)
    if (applied.search) params.set('search', applied.search)
    return params
  }, [page, pageSize, applied])

  const stats = useQuery({
    queryKey: ['alarm-logs-statistics'],
    queryFn: () => apiRequest<Stats>('/alarm-logs/statistics'),
  })
  const filterOptions = useQuery({
    queryKey: ['alarm-logs-filters'],
    queryFn: () => apiRequest<Filters>('/alarm-logs/filters'),
  })
  const logs = useQuery({
    queryKey: ['alarm-logs', queryParams.toString()],
    queryFn: () => apiRequest<Page>(`/alarm-logs?${queryParams.toString()}`),
  })
  const detail = useQuery({
    queryKey: ['alarm-log-detail', detailId],
    queryFn: () => apiRequest<AlarmDetail>(`/alarm-logs/${detailId}`),
    enabled: detailId !== null,
  })

  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: ['alarm-logs'] })
    await queryClient.invalidateQueries({ queryKey: ['alarm-logs-statistics'] })
    await queryClient.invalidateQueries({ queryKey: ['alarms-active'] })
    if (detailId !== null) {
      await queryClient.invalidateQueries({ queryKey: ['alarm-log-detail', detailId] })
    }
  }

  const acknowledge = useMutation({
    mutationFn: (body: { alarm_id: number; note?: string }) =>
      apiRequest('/alarm-logs/acknowledge', { method: 'POST', body }),
    onSuccess: async () => {
      message.success(t('alarm_acknowledged_successfully'))
      setAckOpen(false)
      setDetailId(null)
      ackForm.resetFields()
      await invalidate()
    },
    onError: (err: Error) => message.error(err.message),
  })

  const resolve = useMutation({
    mutationFn: (body: { alarm_id: number; resolution_note: string }) =>
      apiRequest('/alarm-logs/resolve', { method: 'POST', body }),
    onSuccess: async () => {
      message.success(t('alarm_resolved_successfully'))
      setResolveOpen(false)
      setDetailId(null)
      resolveForm.resetFields()
      await invalidate()
    },
    onError: (err: Error) => message.error(err.message),
  })

  const applyFilters = () => {
    setPage(1)
    setApplied({
      range,
      startDate,
      endDate,
      deviceFilter,
      severityFilter,
      statusFilter,
      search,
    })
  }

  const exportPreviewParams = () => {
    const params = new URLSearchParams({ range: applied.range })
    if (applied.startDate) params.set('startDate', applied.startDate)
    if (applied.endDate) params.set('endDate', applied.endDate)
    if (applied.deviceFilter !== 'all') params.set('device_filter', applied.deviceFilter)
    if (applied.severityFilter !== 'all') params.set('severity_filter', applied.severityFilter)
    if (applied.statusFilter !== 'all') params.set('status_filter', applied.statusFilter)
    if (applied.search) params.set('search', applied.search)
    return params.toString()
  }

  const columns: ColumnsType<AlarmLog> = [
    {
      title: t('date_time'),
      dataIndex: 'createdAt',
      render: (value: string | null) =>
        value ? dayjs(value).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss') : '-',
    },
    { title: t('device'), dataIndex: 'deviceName', render: (v: string) => v || t('unknown') },
    { title: t('metric_key'), dataIndex: 'metricKey' },
    {
      title: t('alarm_level'),
      dataIndex: 'severity',
      render: (value: string) => <Tag color={severityColor(value)}>{value || t('unknown')}</Tag>,
    },
    {
      title: t('log_message'),
      dataIndex: 'message',
      render: (_value, row) => {
        const msg = row.message || '-'
        const short = msg.length > 50 ? `${msg.slice(0, 50)}...` : msg
        return (
          <Space direction="vertical" size={4}>
            <span>{short}</span>
            {row.snapshotPath ? (
              <Image src={row.snapshotPath} height={72} style={{ maxWidth: 140, objectFit: 'cover' }} />
            ) : null}
            {msg.length > 50 ? (
              <Button type="link" size="small" style={{ padding: 0 }} onClick={() => setDetailId(row.id)}>
                {t('view_details')}
              </Button>
            ) : null}
          </Space>
        )
      },
    },
  ]

  const alarm = detail.data?.alarm

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Typography.Title level={4} style={{ margin: 0 }}>
        <i className="bi bi-exclamation-triangle" style={{ marginRight: 8 }} />
        {t('alarm_logs')}
      </Typography.Title>

      <Row gutter={16}>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#0d6efd', color: '#fff' }} styles={{ body: { padding: 16 } }}>
            <Space style={{ width: '100%', justifyContent: 'space-between' }}>
              <div>
                <div>{t('total_alarms')}</div>
                <Typography.Title level={3} style={{ color: '#fff', margin: 0 }}>
                  {stats.data?.totalAlarms ?? '-'}
                </Typography.Title>
              </div>
              <i className="bi bi-exclamation-triangle" style={{ fontSize: 32 }} />
            </Space>
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#dc3545', color: '#fff' }} styles={{ body: { padding: 16 } }}>
            <Space style={{ width: '100%', justifyContent: 'space-between' }}>
              <div>
                <div>{t('active_alarms')}</div>
                <Typography.Title level={3} style={{ color: '#fff', margin: 0 }}>
                  {stats.data?.activeAlarms ?? '-'}
                </Typography.Title>
              </div>
              <i className="bi bi-exclamation-circle" style={{ fontSize: 32 }} />
            </Space>
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#ffc107', color: '#212529' }} styles={{ body: { padding: 16 } }}>
            <Space style={{ width: '100%', justifyContent: 'space-between' }}>
              <div>
                <div>{t('alarm_levels')}</div>
                <Typography.Title level={3} style={{ margin: 0 }}>
                  {stats.data?.severityBreakdown.length ?? '-'}
                </Typography.Title>
              </div>
              <i className="bi bi-layers" style={{ fontSize: 32 }} />
            </Space>
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#0dcaf0', color: '#212529' }} styles={{ body: { padding: 16 } }}>
            <Space style={{ width: '100%', justifyContent: 'space-between' }}>
              <div>
                <div>{t('devices')}</div>
                <Typography.Title level={3} style={{ margin: 0 }}>
                  {stats.data?.deviceBreakdown.length ?? '-'}
                </Typography.Title>
              </div>
              <i className="bi bi-cpu" style={{ fontSize: 32 }} />
            </Space>
          </Card>
        </Col>
      </Row>

      <Card
        title={
          <span>
            <i className="bi bi-funnel" style={{ marginRight: 8 }} />
            {t('filters')}
          </span>
        }
      >
        <Row gutter={[12, 12]}>
          <Col xs={24} md={6}>
            <Typography.Text>{t('date_range')}</Typography.Text>
            <Select
              style={{ width: '100%' }}
              value={range}
              onChange={setRange}
              options={[
                { value: 'all', label: t('all') },
                { value: 'today', label: t('today') },
                { value: 'yesterday', label: t('yesterday') },
                { value: 'last_7_days', label: t('last_7_days') },
                { value: 'last_30_days', label: t('last_30_days') },
                { value: 'custom', label: t('custom') },
              ]}
            />
          </Col>
          {range === 'custom' ? (
            <>
              <Col xs={12} md={4}>
                <Typography.Text>{t('start_date')}</Typography.Text>
                <DatePicker
                  style={{ width: '100%' }}
                  value={startDate ? dayjs(startDate) : null}
                  onChange={(d) => setStartDate(d ? d.format('YYYY-MM-DD') : null)}
                />
              </Col>
              <Col xs={12} md={4}>
                <Typography.Text>{t('end_date')}</Typography.Text>
                <DatePicker
                  style={{ width: '100%' }}
                  value={endDate ? dayjs(endDate) : null}
                  onChange={(d) => setEndDate(d ? d.format('YYYY-MM-DD') : null)}
                />
              </Col>
            </>
          ) : null}
          <Col xs={24} md={4}>
            <Typography.Text>{t('device')}</Typography.Text>
            <Select
              style={{ width: '100%' }}
              value={deviceFilter}
              onChange={setDeviceFilter}
              options={[
                { value: 'all', label: t('all_devices') },
                ...(filterOptions.data?.devices.map((d) => ({ value: String(d.id), label: d.name })) ?? []),
              ]}
            />
          </Col>
          <Col xs={24} md={4}>
            <Typography.Text>{t('alarm_level')}</Typography.Text>
            <Select
              style={{ width: '100%' }}
              value={severityFilter}
              onChange={setSeverityFilter}
              options={[
                { value: 'all', label: t('all_severities') },
                ...(filterOptions.data?.severities.map((s) => ({
                  value: s,
                  label: s.charAt(0).toUpperCase() + s.slice(1),
                })) ?? []),
              ]}
            />
          </Col>
          <Col xs={24} md={4}>
            <Typography.Text>{t('status')}</Typography.Text>
            <Select
              style={{ width: '100%' }}
              value={statusFilter}
              onChange={setStatusFilter}
              options={[
                { value: 'all', label: t('all_statuses') },
                ...(filterOptions.data?.statuses.map((s) => ({
                  value: s,
                  label: s.charAt(0).toUpperCase() + s.slice(1),
                })) ?? []),
              ]}
            />
          </Col>
          <Col xs={24} md={6}>
            <Typography.Text>{t('search')}</Typography.Text>
            <Input
              value={search}
              placeholder={t('search_alarms_placeholder')}
              onChange={(e) => setSearch(e.target.value)}
              onPressEnter={applyFilters}
            />
          </Col>
          <Col xs={24} md={2} style={{ display: 'flex', alignItems: 'end' }}>
            <Button type="primary" icon={<i className="bi bi-search" />} onClick={applyFilters} block />
          </Col>
        </Row>
      </Card>

      <Card
        title={
          <span>
            <i className="bi bi-list-ul" style={{ marginRight: 8 }} />
            {t('alarm_logs')}
          </span>
        }
        extra={
          <Space wrap>
            <Button
              type="primary"
              ghost
              icon={<i className="bi bi-eye" />}
              onClick={() => navigate(`/alarmlogs/export?${exportPreviewParams()}`)}
            >
              {t('preview_export')}
            </Button>
            <Link to="/alarmstate">{t('active_alarms')}</Link>
          </Space>
        }
      >
        <Table
          rowKey="id"
          loading={logs.isLoading}
          dataSource={logs.data?.logs ?? []}
          rowSelection={{
            selectedRowKeys: selectedKeys,
            onChange: setSelectedKeys,
          }}
          pagination={{
            current: page,
            pageSize,
            total: logs.data?.total ?? 0,
            showSizeChanger: true,
            pageSizeOptions: [10, 20, 25, 30, 40, 50],
            onChange: (p, ps) => {
              setPage(p)
              setPageSize(ps)
            },
          }}
          columns={columns}
        />
      </Card>

      <Modal
        open={detailId !== null}
        title={
          <span>
            <i className="bi bi-exclamation-triangle" style={{ color: '#faad14', marginRight: 8 }} />
            {t('alarm_details')}
          </span>
        }
        onCancel={() => setDetailId(null)}
        width={960}
        footer={[
          <Button key="close" onClick={() => setDetailId(null)}>
            {t('close')}
          </Button>,
          alarm?.status === 'active' ? (
            <Button
              key="ack"
              type="primary"
              style={{ background: '#faad14', borderColor: '#faad14' }}
              icon={<i className="bi bi-check-circle" />}
              onClick={() => {
                ackForm.resetFields()
                setAckOpen(true)
              }}
            >
              {t('acknowledge')}
            </Button>
          ) : null,
          alarm?.status === 'acknowledged' ? (
            <Button
              key="resolve"
              type="primary"
              style={{ background: '#52c41a', borderColor: '#52c41a' }}
              icon={<i className="bi bi-check-circle-fill" />}
              onClick={() => {
                resolveForm.resetFields()
                setResolveOpen(true)
              }}
            >
              {t('resolve')}
            </Button>
          ) : null,
        ]}
      >
        {alarm ? (
          <Row gutter={16}>
            <Col span={12}>
              <Card size="small" title={<span><i className="bi bi-info-circle" /> {t('alarm_information')}</span>}>
                <p><strong>ID:</strong> {alarm.id}</p>
                <p>
                  <strong>{t('created')}:</strong>{' '}
                  {alarm.createdAt
                    ? dayjs(alarm.createdAt).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss')
                    : '-'}
                </p>
                <p>
                  <strong>{t('updated')}:</strong>{' '}
                  {alarm.updatedAt
                    ? dayjs(alarm.updatedAt).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss')
                    : '-'}
                </p>
                <p><strong>{t('metric')}:</strong> <code>{alarm.metricKey}</code></p>
                <p><strong>{t('value')}:</strong> <code>{alarm.value ?? '-'}</code></p>
                <p>
                  <strong>{t('severity')}:</strong>{' '}
                  <Tag color={severityColor(alarm.severity)}>{alarm.severity}</Tag>
                </p>
                <p>
                  <strong>{t('status')}:</strong>{' '}
                  <Tag color={statusColor(alarm.status)}>{alarm.status}</Tag>
                </p>
                <p><strong>{t('message')}:</strong> {alarm.message}</p>
                {alarm.note ? <p><strong>{t('note')}:</strong> {alarm.note}</p> : null}
                {alarm.snapshotPath ? (
                  <p>
                    <strong>{t('snapshot')}:</strong>
                    <br />
                    <Image src={alarm.snapshotPath} style={{ maxHeight: 120 }} />
                  </p>
                ) : null}
              </Card>
            </Col>
            <Col span={12}>
              <Card size="small" title={<span><i className="bi bi-cpu" /> {t('device_information')}</span>}>
                {alarm.deviceName ? (
                  <>
                    <p><strong>{t('name')}:</strong> {alarm.deviceName}</p>
                    <p><strong>ID:</strong> {alarm.deviceId}</p>
                  </>
                ) : (
                  <Typography.Text type="secondary">{t('no_device_information')}</Typography.Text>
                )}
              </Card>
              <Card
                size="small"
                style={{ marginTop: 12 }}
                title={<span><i className="bi bi-clock-history" /> {t('related_alarms')}</span>}
              >
                {(detail.data?.relatedAlarms.length ?? 0) === 0 ? (
                  <Typography.Text type="secondary">{t('no_related_alarms')}</Typography.Text>
                ) : (
                  detail.data?.relatedAlarms.map((r) => (
                    <div key={r.id} style={{ marginBottom: 8 }}>
                      <Typography.Text type="secondary">
                        {r.createdAt
                          ? dayjs(r.createdAt).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss')
                          : '-'}
                      </Typography.Text>
                      <div>
                        <Tag color={severityColor(r.severity)}>{r.severity}</Tag>
                        <Tag color={statusColor(r.status)}>{r.status}</Tag>
                        <Button type="link" size="small" onClick={() => setDetailId(r.id)}>
                          <i className="bi bi-eye" />
                        </Button>
                      </div>
                    </div>
                  ))
                )}
              </Card>
              <Card
                size="small"
                style={{ marginTop: 12 }}
                title={<span><i className="bi bi-send" /> {t('notification_history')}</span>}
              >
                {(detail.data?.dispatchHistory.length ?? 0) === 0 ? (
                  <Typography.Text type="secondary">{t('no_notification_history')}</Typography.Text>
                ) : (
                  detail.data?.dispatchHistory.map((d) => (
                    <div key={d.id} style={{ marginBottom: 8 }}>
                      <strong>{d.channelName || t('unknown')}</strong>
                      <div>
                        <Typography.Text type="secondary">
                          {d.createdAt
                            ? dayjs(d.createdAt).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss')
                            : '-'}
                        </Typography.Text>{' '}
                        <Tag color={d.status === 'success' ? 'green' : 'red'}>{d.status}</Tag>
                      </div>
                      {d.error ? <Typography.Text type="danger">{d.error}</Typography.Text> : null}
                    </div>
                  ))
                )}
              </Card>
            </Col>
          </Row>
        ) : null}
      </Modal>

      <Modal
        open={ackOpen}
        title={
          <span>
            <i className="bi bi-check-circle" style={{ color: '#faad14', marginRight: 8 }} />
            {t('acknowledge_alarm')}
          </span>
        }
        onCancel={() => setAckOpen(false)}
        onOk={() => {
          const values = ackForm.getFieldsValue()
          if (detailId === null) return
          acknowledge.mutate({ alarm_id: detailId, note: values.note })
        }}
        confirmLoading={acknowledge.isPending}
        okText={t('acknowledge')}
        okButtonProps={{ style: { background: '#faad14', borderColor: '#faad14' } }}
      >
        <Form form={ackForm} layout="vertical">
          <Form.Item name="note" label={t('note_optional')}>
            <Input.TextArea rows={3} placeholder={t('acknowledge_note_placeholder')} />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        open={resolveOpen}
        title={
          <span>
            <i className="bi bi-check-circle-fill" style={{ color: '#52c41a', marginRight: 8 }} />
            {t('resolve_alarm')}
          </span>
        }
        onCancel={() => setResolveOpen(false)}
        onOk={async () => {
          const values = await resolveForm.validateFields()
          if (detailId === null) return
          resolve.mutate({ alarm_id: detailId, resolution_note: values.resolution_note })
        }}
        confirmLoading={resolve.isPending}
        okText={t('resolve')}
        okButtonProps={{ style: { background: '#52c41a', borderColor: '#52c41a' } }}
      >
        <Form form={resolveForm} layout="vertical">
          <Form.Item
            name="resolution_note"
            label={t('resolution_note')}
            rules={[{ required: true, message: t('resolution_note_required') }]}
          >
            <Input.TextArea rows={3} placeholder={t('resolve_note_placeholder')} />
          </Form.Item>
        </Form>
      </Modal>
    </Space>
  )
}
