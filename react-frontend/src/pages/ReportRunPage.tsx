import { useMutation, useQuery } from '@tanstack/react-query'
import {
  App,
  Button,
  Card,
  Checkbox,
  Col,
  DatePicker,
  Form,
  Row,
  Select,
  Space,
  Table,
  Tag,
  Typography,
} from 'antd'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router'
import { apiRequest, downloadFile, getAccessToken } from '../api/client'
import {
  DISPLAY_OPTIONS,
  TIME_INTERVAL_OPTIONS,
  TIME_RANGE_OPTIONS,
  type ReportTemplate,
} from '../features/reports/types'

type FetchResult = {
  rows: Record<string, unknown>[]
  count: number
  dataSource: string
}

type DeviceOption = { id: number; name: string; location: string }

function formatFormValue(value: unknown, month = false): string | undefined {
  if (value == null || value === '') return undefined
  if (typeof value === 'string') return value
  if (typeof value === 'object' && value !== null && 'format' in value) {
    const fmt = (value as { format: (f: string) => string }).format
    return month ? fmt('YYYY-MM') : fmt('YYYY-MM-DD')
  }
  return String(value)
}

export function ReportRunPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const templateId = Number(id)
  const [form] = Form.useForm()
  const [rows, setRows] = useState<Record<string, unknown>[]>([])
  const [fetched, setFetched] = useState(false)
  const [saveToFileManager, setSaveToFileManager] = useState(false)

  const template = useQuery({
    queryKey: ['report-templates', templateId],
    enabled: Number.isFinite(templateId),
    queryFn: () => apiRequest<ReportTemplate>(`/report-templates/${templateId}`),
  })

  const devices = useQuery({
    queryKey: ['report-lookups-devices'],
    queryFn: () => apiRequest<DeviceOption[]>('/report-templates/lookups/devices'),
  })

  const config = template.data?.config ?? {}
  const filtersConfig = config.filters ?? {}
  const editor = config.editor ?? {}
  const exportInputs = config.export_inputs ?? []
  const selectedPairs = filtersConfig.selected_device_metrics ?? []
  const timeRangeVal = filtersConfig.time_range || 'last_24h'
  const timeIntervalVal = filtersConfig.time_interval || '1h'
  const displayVals = filtersConfig.display?.length
    ? filtersConfig.display
    : ['min', 'max', 'average']
  const displayColumns = editor.display_columns ?? []
  const reportTitle = editor.report_title || template.data?.name || ''

  const hasDateRangeInput = exportInputs.some((i) => i.type === 'date_range')
  const showDateRangeForProcessing =
    (config.data_source === 'device_data' || config.data_source === 'alarm_logs') &&
    timeRangeVal === 'input_date_range' &&
    !hasDateRangeInput

  const columns = useMemo(() => {
    if (!rows.length) return []
    if (displayColumns.length) {
      return displayColumns
        .filter((c) => c.key in rows[0]!)
        .map((c) => ({
          title: c.label || c.key,
          dataIndex: c.key,
          key: c.key,
          ellipsis: true,
        }))
    }
    return Object.keys(rows[0]!).map((key) => ({
      title: key,
      dataIndex: key,
      key,
      ellipsis: true,
    }))
  }, [rows, displayColumns])

  const buildFilters = (values: Record<string, unknown>) => {
    const filters: Record<string, unknown> = {}
    const start = formatFormValue(values.start_date)
    const end = formatFormValue(values.end_date)
    const date = formatFormValue(values.date)
    const month = formatFormValue(values.month, true)
    if (start) filters.start_date = start
    if (end) filters.end_date = end
    if (date) filters.date = date
    if (month) filters.month = month
    if (values.year) filters.year = String(values.year)
    if (values.device_id) filters.device_id = values.device_id
    if (values.metric) filters.metric = values.metric
    if (values.room) filters.room = values.room
    if (values.action_type) filters.action_type = values.action_type
    if (values.entity_type) filters.entity_type = values.entity_type
    return filters
  }

  const fetchMutation = useMutation({
    mutationFn: async (values: Record<string, unknown>) =>
      apiRequest<FetchResult>(`/report-templates/${templateId}/fetch`, {
        method: 'POST',
        body: { filters: buildFilters(values) },
      }),
    onSuccess: (data) => {
      setRows(data.rows)
      setFetched(true)
      message.success(`${t('get_data')}: ${data.count}`)
    },
    onError: (err: Error) => message.error(err.message),
  })

  async function exportBinary(
    format: 'csv' | 'excel' | 'pdf',
    values: Record<string, unknown>,
    preview = false,
  ) {
    const headers = new Headers({ 'Content-Type': 'application/json' })
    const token = getAccessToken()
    if (token) headers.set('Authorization', `Bearer ${token}`)
    const response = await fetch(`/api/v1/report-templates/${templateId}/export`, {
      method: 'POST',
      credentials: 'include',
      headers,
      body: JSON.stringify({
        format,
        saveToFileManager: false,
        preview,
        filters: buildFilters(values),
      }),
    })
    if (!response.ok) {
      let msg = 'Export failed'
      try {
        const err = (await response.json()) as { error?: { message?: string } }
        msg = err.error?.message || msg
      } catch {
        // ignore
      }
      throw new Error(msg)
    }
    const blob = await response.blob()
    const disposition = response.headers.get('Content-Disposition') || ''
    const match = /filename="([^"]+)"/.exec(disposition)
    const fileName = match?.[1] || `report.${format === 'excel' ? 'xls' : format}`
    if (preview && format === 'pdf') {
      const url = URL.createObjectURL(blob)
      window.open(url, '_blank', 'noopener,noreferrer')
      return
    }
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = fileName
    link.click()
    URL.revokeObjectURL(url)
  }

  const exportMutation = useMutation({
    mutationFn: async (args: {
      format: 'csv' | 'excel' | 'pdf'
      preview?: boolean
      values: Record<string, unknown>
    }) => {
      if (saveToFileManager && !args.preview) {
        const data = await apiRequest<{ fileId: number; fileName: string }>(
          `/report-templates/${templateId}/export`,
          {
            method: 'POST',
            body: {
              format: args.format,
              saveToFileManager: true,
              filters: buildFilters(args.values),
            },
          },
        )
        await downloadFile(`/report-files/${data.fileId}`, data.fileName)
        return data.fileName
      }
      await exportBinary(args.format, args.values, Boolean(args.preview))
      return args.format
    },
    onSuccess: () => message.success(t('saved')),
    onError: (err: Error) => message.error(err.message),
  })

  const timeRangeLabel =
    TIME_RANGE_OPTIONS.find((o) => o.value === timeRangeVal)?.label || timeRangeVal
  const timeIntervalLabel =
    TIME_INTERVAL_OPTIONS.find((o) => o.value === timeIntervalVal)?.label || timeIntervalVal
  const displayLabel = displayVals
    .map((v) => DISPLAY_OPTIONS.find((o) => o.value === v)?.label || v)
    .join(', ')

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {template.data?.name || t('reports_run')}
        </Typography.Title>
        <Button onClick={() => navigate('/reports')}>{t('back_to_templates')}</Button>
      </Space>

      <Card
        title={
          <span>
            <i className="bi bi-funnel" style={{ marginRight: 8 }} />
            {t('template_filters')}
          </span>
        }
        size="small"
      >
        {selectedPairs.length ? (
          <>
            <Typography.Paragraph type="secondary" style={{ marginBottom: 8 }}>
              {t('devices_metrics_used')}
            </Typography.Paragraph>
            <Space wrap>
              {selectedPairs.map((p) => (
                <Tag key={`${p.device_id}-${p.metric}`}>
                  {(p.device_name || `ID ${p.device_id}`) + ' – ' + (p.metric_label || p.metric)}
                </Tag>
              ))}
            </Space>
          </>
        ) : (
          <Typography.Text type="secondary">{t('use_all_devices_metrics')}</Typography.Text>
        )}
      </Card>

      <Card
        title={
          <span>
            <i className="bi bi-gear" style={{ marginRight: 8 }} />
            {t('data_processing')}
          </span>
        }
        size="small"
      >
        <Row gutter={16}>
          <Col xs={24} md={8}>
            <Typography.Text type="secondary">{t('time_range')}: </Typography.Text>
            <Typography.Text strong>{timeRangeLabel}</Typography.Text>
          </Col>
          <Col xs={24} md={8}>
            <Typography.Text type="secondary">{t('time_interval')}: </Typography.Text>
            <Typography.Text strong>{timeIntervalLabel}</Typography.Text>
          </Col>
          <Col xs={24} md={8}>
            <Typography.Text type="secondary">{t('display')}: </Typography.Text>
            <Typography.Text strong>{displayLabel}</Typography.Text>
          </Col>
        </Row>
      </Card>

      <Card
        title={
          <span>
            <i className="bi bi-input-cursor" style={{ marginRight: 8 }} />
            Enter data for Export
          </span>
        }
        size="small"
      >
        <Form form={form} layout="vertical" onFinish={(values) => fetchMutation.mutate(values)}>
          {showDateRangeForProcessing ? (
            <Row gutter={12} style={{ marginBottom: 12 }}>
              <Col xs={24} md={8}>
                <Form.Item
                  name="start_date"
                  label="Date range (for Data processing) – Start"
                  rules={[{ required: true }]}
                >
                  <DatePicker style={{ width: '100%' }} />
                </Form.Item>
              </Col>
              <Col xs={24} md={8}>
                <Form.Item name="end_date" label="End" rules={[{ required: true }]}>
                  <DatePicker style={{ width: '100%' }} />
                </Form.Item>
              </Col>
            </Row>
          ) : null}

          <Row gutter={16}>
            {exportInputs.map((input, index) => {
              const type = input.type
              const label = input.label || type
              const required = Boolean(input.required)
              if (type === 'date') {
                return (
                  <Col xs={24} md={8} key={`${type}-${index}`}>
                    <Form.Item name="date" label={label} rules={required ? [{ required: true }] : []}>
                      <DatePicker style={{ width: '100%' }} />
                    </Form.Item>
                  </Col>
                )
              }
              if (type === 'month') {
                return (
                  <Col xs={24} md={8} key={`${type}-${index}`}>
                    <Form.Item name="month" label={label} rules={required ? [{ required: true }] : []}>
                      <DatePicker picker="month" style={{ width: '100%' }} />
                    </Form.Item>
                  </Col>
                )
              }
              if (type === 'year') {
                const year = new Date().getFullYear()
                return (
                  <Col xs={24} md={8} key={`${type}-${index}`}>
                    <Form.Item name="year" label={label} rules={required ? [{ required: true }] : []}>
                      <Select
                        options={Array.from({ length: 11 }, (_, i) => ({
                          value: String(year - i),
                          label: String(year - i + 543),
                        }))}
                        allowClear
                      />
                    </Form.Item>
                  </Col>
                )
              }
              if (type === 'date_range') {
                return (
                  <Col xs={24} md={12} key={`${type}-${index}`}>
                    <Form.Item label={label} required={required}>
                      <Space>
                        <Form.Item
                          name="start_date"
                          noStyle
                          rules={required ? [{ required: true }] : []}
                        >
                          <DatePicker placeholder="Start" />
                        </Form.Item>
                        <Form.Item
                          name="end_date"
                          noStyle
                          rules={required ? [{ required: true }] : []}
                        >
                          <DatePicker placeholder="End" />
                        </Form.Item>
                      </Space>
                    </Form.Item>
                  </Col>
                )
              }
              if (type === 'device') {
                return (
                  <Col xs={24} md={8} key={`${type}-${index}`}>
                    <Form.Item
                      name="device_id"
                      label={label}
                      rules={required ? [{ required: true }] : []}
                    >
                      <Select
                        allowClear
                        options={(devices.data ?? []).map((d) => ({
                          value: d.id,
                          label: `${d.name}${d.location ? ` - ${d.location}` : ''}`,
                        }))}
                      />
                    </Form.Item>
                  </Col>
                )
              }
              return null
            })}
          </Row>

          <Space wrap>
            <Button
              type="primary"
              htmlType="submit"
              loading={fetchMutation.isPending}
              icon={<i className="bi bi-database" />}
            >
              Fetch data
            </Button>
            {fetched ? (
              <>
                <Button
                  icon={<i className="bi bi-file-earmark-excel" />}
                  onClick={() =>
                    exportMutation.mutate({ format: 'excel', values: form.getFieldsValue() })
                  }
                  loading={exportMutation.isPending}
                >
                  Export Excel
                </Button>
                <Button
                  icon={<i className="bi bi-filetype-csv" />}
                  onClick={() =>
                    exportMutation.mutate({ format: 'csv', values: form.getFieldsValue() })
                  }
                  loading={exportMutation.isPending}
                >
                  Export CSV
                </Button>
                <Button
                  icon={<i className="bi bi-eye" />}
                  onClick={() =>
                    exportMutation.mutate({
                      format: 'pdf',
                      preview: true,
                      values: form.getFieldsValue(),
                    })
                  }
                  loading={exportMutation.isPending}
                >
                  Preview PDF
                </Button>
                <Button
                  danger
                  icon={<i className="bi bi-file-earmark-pdf" />}
                  onClick={() =>
                    exportMutation.mutate({ format: 'pdf', values: form.getFieldsValue() })
                  }
                  loading={exportMutation.isPending}
                >
                  Export PDF
                </Button>
                <Checkbox
                  checked={saveToFileManager}
                  onChange={(e) => setSaveToFileManager(e.target.checked)}
                >
                  {t('save_to_file_manager')}
                </Checkbox>
              </>
            ) : null}
          </Space>
        </Form>
      </Card>

      {fetched ? (
        <Card
          title={
            <span>
              <i className="bi bi-table" style={{ marginRight: 8 }} />
              {reportTitle || 'Sample data'}
            </span>
          }
          size="small"
          styles={{ header: { background: '#52c41a', color: '#fff' } }}
        >
          <Table
            size="small"
            rowKey={(_r, i) => String(i)}
            dataSource={rows}
            columns={columns}
            scroll={{ x: true }}
            pagination={{ pageSize: 50 }}
          />
        </Card>
      ) : null}
    </Space>
  )
}
