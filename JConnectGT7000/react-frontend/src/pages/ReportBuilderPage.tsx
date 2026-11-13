import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Button,
  Card,
  Checkbox,
  Col,
  Form,
  Input,
  Radio,
  Row,
  Select,
  Space,
  Tag,
  Typography,
} from 'antd'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router'
import { apiRequest } from '../api/client'
import {
  DEFAULT_DISPLAY_COLUMNS,
  DISPLAY_OPTIONS,
  TIME_INTERVAL_OPTIONS,
  TIME_RANGE_OPTIONS,
  type DeviceMetricPair,
  type DisplayColumn,
  type ReportTemplate,
} from '../features/reports/types'

type DeviceOption = { id: number; name: string; location: string }
type MetricOption = { key: string; label: string }

type BuilderForm = {
  name: string
  description: string
  dataSource: 'device_data'
  timeRange: string
  timeInterval: string
  display: string[]
  sortOrder: 'asc' | 'desc'
  reportTitle: string
  tableLayout: 'rows' | 'pivot'
  pdfOrientation: 'portrait' | 'landscape'
  pdfTitle: string
  excelSheetName: string
  csvDelimiter: ',' | ';' | 'tab'
}

function reorderList<T>(list: T[], from: number, to: number): T[] {
  if (from < 0 || to < 0 || from === to) return list
  const next = [...list]
  const [moved] = next.splice(from, 1)
  next.splice(to, 0, moved!)
  return next
}

export function ReportBuilderPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const params = useParams<{ id?: string }>()
  const templateId = params.id ? Number(params.id) : null
  const [form] = Form.useForm<BuilderForm>()
  const [selectedPairs, setSelectedPairs] = useState<DeviceMetricPair[]>([])
  const [displayColumns, setDisplayColumns] = useState<DisplayColumn[]>(DEFAULT_DISPLAY_COLUMNS)
  const [pickDeviceId, setPickDeviceId] = useState<number | undefined>()
  const [pickMetric, setPickMetric] = useState<string | undefined>()
  const [addColumnKey, setAddColumnKey] = useState(DEFAULT_DISPLAY_COLUMNS[0]?.key)
  const [dragPairKey, setDragPairKey] = useState<string | null>(null)
  const [dragColIndex, setDragColIndex] = useState<number | null>(null)

  const existing = useQuery({
    queryKey: ['report-templates', templateId],
    enabled: Boolean(templateId),
    queryFn: () => apiRequest<ReportTemplate>(`/report-templates/${templateId}`),
  })

  const devices = useQuery({
    queryKey: ['report-lookups-devices'],
    queryFn: () => apiRequest<DeviceOption[]>('/report-templates/lookups/devices'),
  })

  const metrics = useQuery({
    queryKey: ['report-lookups-metrics', pickDeviceId],
    enabled: Boolean(pickDeviceId),
    queryFn: () =>
      apiRequest<MetricOption[]>(`/report-templates/lookups/metrics-by-device/${pickDeviceId}`),
  })

  useEffect(() => {
    if (!existing.data) {
      form.setFieldsValue({
        name: '',
        description: '',
        dataSource: 'device_data',
        timeRange: 'last_24h',
        timeInterval: '1h',
        display: [],
        sortOrder: 'desc',
        reportTitle: '',
        tableLayout: 'rows',
        pdfOrientation: 'portrait',
        pdfTitle: '',
        excelSheetName: 'Sheet1',
        csvDelimiter: ',',
      })
      return
    }
    const cfg = existing.data.config ?? {}
    const filters = cfg.filters ?? {}
    const editor = cfg.editor ?? {}
    form.setFieldsValue({
      name: existing.data.name,
      description: existing.data.description,
      dataSource: 'device_data',
      timeRange: filters.time_range || 'last_24h',
      timeInterval: filters.time_interval || '1h',
      display: Array.isArray(filters.display) ? filters.display : [],
      sortOrder: filters.sort_order === 'asc' ? 'asc' : 'desc',
      reportTitle: editor.report_title || '',
      tableLayout: editor.table_layout === 'pivot' ? 'pivot' : 'rows',
      pdfOrientation: editor.pdf_orientation === 'landscape' ? 'landscape' : 'portrait',
      pdfTitle: editor.pdf_title || '',
      excelSheetName: editor.excel_sheet_name || 'Sheet1',
      csvDelimiter: (editor.csv_delimiter as BuilderForm['csvDelimiter']) || ',',
    })
    setSelectedPairs(filters.selected_device_metrics ?? [])
    setDisplayColumns(
      editor.display_columns?.length ? editor.display_columns : DEFAULT_DISPLAY_COLUMNS,
    )
  }, [existing.data, form])

  const deviceMetricsOptions = useMemo(() => metrics.data ?? [], [metrics.data])

  const saveMutation = useMutation({
    mutationFn: async (values: BuilderForm) => {
      const body = {
        name: values.name,
        description: values.description,
        dataSource: values.dataSource,
        filters: {
          time_range: values.timeRange,
          time_interval: values.timeInterval,
          display: values.display,
          sort_order: values.sortOrder,
          selected_device_metrics: selectedPairs,
        },
        exportInputs: [] as Array<{ type: string; label: string; required: boolean }>,
        editor: {
          report_title: values.reportTitle,
          table_layout: values.tableLayout,
          pdf_orientation: values.pdfOrientation,
          pdf_title: values.pdfTitle,
          excel_sheet_name: values.excelSheetName,
          csv_delimiter: values.csvDelimiter,
          display_columns: displayColumns,
        },
      }
      if (templateId) {
        return apiRequest<ReportTemplate>(`/report-templates/${templateId}`, {
          method: 'PUT',
          body,
        })
      }
      return apiRequest<ReportTemplate>('/report-templates', { method: 'POST', body })
    },
    onSuccess: async () => {
      message.success(t('saved'))
      await queryClient.invalidateQueries({ queryKey: ['report-templates'] })
      navigate('/reports')
    },
    onError: (err: Error) => message.error(err.message),
  })

  const addPair = () => {
    if (!pickDeviceId || !pickMetric) return
    const device = devices.data?.find((d) => d.id === pickDeviceId)
    const metric = deviceMetricsOptions.find((m) => m.key === pickMetric)
    if (selectedPairs.some((p) => p.device_id === pickDeviceId && p.metric === pickMetric)) return
    setSelectedPairs((prev) => [
      ...prev,
      {
        device_id: pickDeviceId,
        device_name: device?.name || '',
        metric: pickMetric,
        metric_label: metric?.label || pickMetric,
      },
    ])
  }

  const addColumn = () => {
    if (!addColumnKey) return
    if (displayColumns.some((c) => c.key === addColumnKey)) return
    const def = DEFAULT_DISPLAY_COLUMNS.find((c) => c.key === addColumnKey)
    setDisplayColumns((prev) => [...prev, { key: addColumnKey, label: def?.label || addColumnKey }])
  }

  const onDropPair = useCallback(
    (targetKey: string) => {
      if (!dragPairKey || dragPairKey === targetKey) {
        setDragPairKey(null)
        return
      }
      setSelectedPairs((prev) => {
        const from = prev.findIndex((p) => `${p.device_id}-${p.metric}` === dragPairKey)
        const to = prev.findIndex((p) => `${p.device_id}-${p.metric}` === targetKey)
        return reorderList(prev, from, to)
      })
      setDragPairKey(null)
    },
    [dragPairKey],
  )

  const onDropColumn = useCallback(
    (targetIndex: number) => {
      if (dragColIndex === null || dragColIndex === targetIndex) {
        setDragColIndex(null)
        return
      }
      setDisplayColumns((prev) => reorderList(prev, dragColIndex, targetIndex))
      setDragColIndex(null)
    },
    [dragColIndex],
  )

  const metricPlaceholder = pickDeviceId ? '-- Select Metric --' : '-- Select Device first --'

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Typography.Title level={3} style={{ margin: 0 }}>
        {templateId ? t('edit_template') : t('create_report_template')}
      </Typography.Title>

      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => saveMutation.mutate(values)}
        disabled={Boolean(templateId) && existing.isLoading}
      >
        <Card
          title={
            <span>
              <i className="bi bi-info-circle" style={{ marginRight: 8 }} />
              {t('template_information')}
            </span>
          }
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Row gutter={16}>
            <Col xs={24} md={12}>
              <Form.Item
                name="name"
                label={t('template_name')}
                rules={[{ required: true, message: t('template_name') }]}
              >
                <Input maxLength={255} placeholder={t('template_name_placeholder')} />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item name="description" label={t('description')}>
                <Input placeholder={t('brief_description')} />
              </Form.Item>
            </Col>
          </Row>
        </Card>

        <Card
          title={
            <span>
              <i className="bi bi-database" style={{ marginRight: 8 }} />
              {t('data_source')}
            </span>
          }
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Typography.Paragraph type="secondary" style={{ marginBottom: 12 }}>
            {t('select_data_source')}
          </Typography.Paragraph>
          <Form.Item name="dataSource" style={{ marginBottom: 0 }}>
            <Radio.Group>
              <Radio value="device_data">
                <span>
                  <i className="bi bi-cpu" style={{ color: '#1677ff', marginRight: 6 }} />
                  Device Data
                </span>
                <Typography.Text type="secondary" style={{ display: 'block', fontSize: 12 }}>
                  {t('device_data_helper')}
                </Typography.Text>
              </Radio>
            </Radio.Group>
          </Form.Item>
        </Card>

        <Card
          title={
            <span>
              <i className="bi bi-funnel" style={{ marginRight: 8 }} />
              Filters used in Template
            </span>
          }
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Typography.Paragraph type="secondary">
            Select Device then select Metric for that device. Click Add to add to list (if not added =
            use all devices/all Metrics)
          </Typography.Paragraph>
          <Space wrap align="end" style={{ marginBottom: 12 }}>
            <div>
              <div style={{ marginBottom: 4 }}>Device</div>
              <Select
                style={{ minWidth: 220 }}
                placeholder="-- Select Device --"
                value={pickDeviceId}
                onChange={(v) => {
                  setPickDeviceId(v)
                  setPickMetric(undefined)
                }}
                options={(devices.data ?? []).map((d) => ({
                  value: d.id,
                  label: `${d.name}${d.location ? ` - ${d.location}` : ''}`,
                }))}
                allowClear
              />
            </div>
            <div>
              <div style={{ marginBottom: 4 }}>Metric (for selected Device)</div>
              <Select
                style={{ minWidth: 220 }}
                placeholder={metricPlaceholder}
                disabled={!pickDeviceId}
                value={pickMetric}
                onChange={setPickMetric}
                options={deviceMetricsOptions.map((m) => ({ value: m.key, label: m.label }))}
                allowClear
              />
            </div>
            <Button type="primary" icon={<i className="bi bi-plus-lg" />} onClick={addPair}>
              Add
            </Button>
          </Space>
          <Space wrap>
            {selectedPairs.map((p) => {
              const key = `${p.device_id}-${p.metric}`
              return (
                <span
                  key={key}
                  draggable
                  onDragStart={() => setDragPairKey(key)}
                  onDragOver={(e) => e.preventDefault()}
                  onDrop={() => onDropPair(key)}
                  style={{ cursor: 'grab', display: 'inline-block' }}
                >
                  <Tag
                    closable
                    color="blue"
                    onClose={() =>
                      setSelectedPairs((prev) =>
                        prev.filter((x) => !(x.device_id === p.device_id && x.metric === p.metric)),
                      )
                    }
                    style={{ userSelect: 'none', paddingInline: 8 }}
                  >
                    <i
                      className="bi bi-grip-vertical"
                      style={{ marginRight: 4, opacity: 0.9 }}
                      title="Drag to reorder"
                    />
                    {(p.device_name || `ID ${p.device_id}`) + ' - ' + (p.metric_label || p.metric)}
                  </Tag>
                </span>
              )
            })}
          </Space>
        </Card>

        <Card
          title={
            <span>
              <i className="bi bi-gear" style={{ marginRight: 8 }} />
              {t('data_processing')}
            </span>
          }
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Row gutter={16}>
            <Col xs={24} md={8}>
              <Form.Item name="timeRange" label={t('time_range')}>
                <Select options={TIME_RANGE_OPTIONS} />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item name="timeInterval" label={t('time_interval')}>
                <Select options={TIME_INTERVAL_OPTIONS} />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item name="display" label={t('display')}>
                <Checkbox.Group options={DISPLAY_OPTIONS} />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item name="sortOrder" label="Sort Order">
                <Select
                  options={[
                    { value: 'desc', label: 'Newest first (DESC)' },
                    { value: 'asc', label: 'Oldest first (ASC)' },
                  ]}
                />
              </Form.Item>
            </Col>
          </Row>
        </Card>

        <Card
          title={
            <span>
              <i className="bi bi-pencil-square" style={{ marginRight: 8 }} />
              Editor for display and Export
            </span>
          }
          size="small"
          style={{ marginBottom: 16 }}
        >
          <Row gutter={16}>
            <Col xs={24} md={12}>
              <Form.Item name="reportTitle" label="Report title (shown on preview and PDF)">
                <Input placeholder="e.g. Device Data Report" />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item name="tableLayout" label="Table layout">
                <Select
                  options={[
                    { value: 'rows', label: 'Rows per Device-Metric (long format)' },
                    { value: 'pivot', label: 'Pivot by time (Metric as columns)' },
                  ]}
                />
              </Form.Item>
            </Col>
          </Row>

          <Typography.Text strong>Display columns (reorderable)</Typography.Text>
          <div style={{ marginTop: 8, marginBottom: 12 }}>
            {displayColumns.map((col, index) => (
              <div
                key={`${col.key}-${index}`}
                draggable
                onDragStart={() => setDragColIndex(index)}
                onDragOver={(e) => e.preventDefault()}
                onDrop={() => onDropColumn(index)}
                style={{ marginBottom: 8 }}
              >
                <Space wrap style={{ alignItems: 'center' }}>
                  <span style={{ cursor: 'grab', userSelect: 'none', padding: '0 4px' }} title="Drag to reorder">
                    <i className="bi bi-grip-vertical" />
                  </span>
                  <Input value={col.key} disabled style={{ width: 140 }} />
                  <Input
                    value={col.label}
                    style={{ width: 200 }}
                    onChange={(e) => {
                      const label = e.target.value
                      setDisplayColumns((prev) =>
                        prev.map((c, i) => (i === index ? { ...c, label } : c)),
                      )
                    }}
                  />
                  <Button
                    size="small"
                    danger
                    icon={<i className="bi bi-dash-lg" />}
                    onClick={() => setDisplayColumns((prev) => prev.filter((_, i) => i !== index))}
                  />
                </Space>
              </div>
            ))}
            <Space>
              <Select
                style={{ width: 220 }}
                value={addColumnKey}
                onChange={setAddColumnKey}
                options={DEFAULT_DISPLAY_COLUMNS.map((c) => ({
                  value: c.key,
                  label: `${c.label} (${c.key})`,
                }))}
              />
              <Button icon={<i className="bi bi-plus" />} onClick={addColumn}>
                Add
              </Button>
            </Space>
          </div>

          <Row gutter={16}>
            <Col xs={24} md={8}>
              <Form.Item name="pdfOrientation" label="PDF">
                <Select
                  options={[
                    { value: 'portrait', label: 'Portrait' },
                    { value: 'landscape', label: 'Landscape' },
                  ]}
                />
              </Form.Item>
              <Form.Item name="pdfTitle">
                <Input placeholder="PDF title (optional)" />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item name="excelSheetName" label="Excel – Sheet name">
                <Input placeholder="Sheet1" />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item name="csvDelimiter" label="CSV – Field delimiter">
                <Select
                  options={[
                    { value: ',', label: 'Comma' },
                    { value: ';', label: 'Semicolon' },
                    { value: 'tab', label: 'Tab' },
                  ]}
                />
              </Form.Item>
            </Col>
          </Row>
        </Card>

        <Space>
          <Button
            type="primary"
            htmlType="submit"
            loading={saveMutation.isPending}
            icon={<i className="bi bi-check-lg" />}
          >
            Save Template
          </Button>
          <Button onClick={() => navigate('/reports')}>Cancel</Button>
        </Space>
      </Form>
    </Space>
  )
}
