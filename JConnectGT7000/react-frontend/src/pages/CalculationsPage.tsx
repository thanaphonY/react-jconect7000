import { SearchOutlined } from '@ant-design/icons'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Button,
  Card,
  Col,
  Form,
  Input,
  InputNumber,
  Modal,
  Row,
  Select,
  Space,
  Table,
  Typography,
} from 'antd'
import { useMemo, useState } from 'react'
import { ApiError, apiRequest } from '../api/client'

type CalcInputForm = {
  var: string
  source: 'device' | 'random'
  deviceId?: number | null
  metricKey?: string
  min?: number
  max?: number
}

type CalcMetric = {
  type?: string
  key?: string
  label?: string
  unit?: string
  datatype?: string
  calc?: {
    formula?: string
    inputs?: Array<Record<string, unknown>>
    trigger?: { mode?: string; intervalSec?: number; cron?: string }
    decimals?: number
    maxAgeSec?: number
  }
}

type CalculationRow = {
  deviceId: number
  deviceName: string
  isActive: boolean
  metric: CalcMetric
}

type CalcDevice = {
  id: number
  name: string
  metrics: Array<{ key?: string; label?: string; type?: string }>
}

type CalculationPageData = {
  calculations: CalculationRow[]
  devices: CalcDevice[]
}

type FormValues = {
  deviceId: number
  key: string
  label?: string
  unit?: string
  datatype: 'float' | 'int'
  formula: string
  inputs: CalcInputForm[]
  triggerMode: 'interval' | 'on_pull' | 'schedule'
  intervalSec?: number
  cron?: string
  decimals?: number
  maxAgeSec?: number | null
}

function formatInput(input: Record<string, unknown>): string {
  const name = String(input.var ?? '?')
  if (input.source === 'random') {
    return `${name} = random[${input.min ?? 0}..${input.max ?? 1}]`
  }
  const devicePrefix =
    input.deviceId !== undefined && input.deviceId !== null && input.deviceId !== ''
      ? `dev${Number(input.deviceId)}:`
      : ''
  return `${name} = ${devicePrefix}${String(input.metricKey ?? '')}`
}

function toFormInputs(raw: Array<Record<string, unknown>> | undefined): CalcInputForm[] {
  if (!raw?.length) {
    return [{ var: 'a', source: 'device', deviceId: null, metricKey: '' }]
  }
  return raw.map((input) => {
    if (input.source === 'random') {
      return {
        var: String(input.var ?? ''),
        source: 'random' as const,
        min: typeof input.min === 'number' ? input.min : Number(input.min) || 0,
        max: typeof input.max === 'number' ? input.max : Number(input.max) || 1,
      }
    }
    return {
      var: String(input.var ?? ''),
      source: 'device' as const,
      deviceId:
        input.deviceId !== undefined && input.deviceId !== null && input.deviceId !== ''
          ? Number(input.deviceId)
          : null,
      metricKey: String(input.metricKey ?? ''),
    }
  })
}

function buildSavePayload(values: FormValues, editing: CalculationRow | null) {
  const inputs = (values.inputs ?? []).map((input) => {
    if (input.source === 'random') {
      return {
        var: input.var,
        source: 'random' as const,
        min: input.min ?? 0,
        max: input.max ?? 1,
      }
    }
    const payload: Record<string, unknown> = {
      var: input.var,
      metricKey: input.metricKey ?? '',
    }
    if (input.deviceId) {
      payload.deviceId = input.deviceId
    }
    return payload
  })

  return {
    deviceId: values.deviceId,
    originalDeviceId: editing?.deviceId ?? null,
    originalKey: editing?.metric.key ?? null,
    key: values.key,
    label: values.label ?? '',
    unit: values.unit ?? '',
    datatype: values.datatype,
    formula: values.formula,
    inputs,
    triggerMode: values.triggerMode,
    intervalSec: values.intervalSec ?? null,
    cron: values.cron ?? '',
    decimals: values.decimals ?? 2,
    maxAgeSec: values.maxAgeSec ?? null,
  }
}

export function CalculationsPage() {
  const { message, modal } = App.useApp()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<CalculationRow | null>(null)
  const [form] = Form.useForm<FormValues>()
  const triggerMode = Form.useWatch('triggerMode', form)
  const outputDeviceId = Form.useWatch('deviceId', form)
  const inputsWatch = Form.useWatch('inputs', form)

  const page = useQuery({
    queryKey: ['calculations'],
    queryFn: () => apiRequest<CalculationPageData>('/calculations'),
  })

  const devices = page.data?.devices ?? []
  const rows = page.data?.calculations ?? []

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase()
    if (!query) return rows
    return rows.filter((row) => {
      const formula = String(row.metric.calc?.formula ?? '')
      const key = String(row.metric.key ?? '')
      const haystack = `${row.deviceName} ${key} ${formula}`.toLowerCase()
      return haystack.includes(query)
    })
  }, [rows, search])

  const saveMutation = useMutation({
    mutationFn: (values: FormValues) =>
      apiRequest<CalculationRow>('/calculations', {
        method: 'POST',
        body: buildSavePayload(values, editing),
      }),
    onSuccess: async () => {
      message.success('Calculation saved.')
      setOpen(false)
      setEditing(null)
      form.resetFields()
      await queryClient.invalidateQueries({ queryKey: ['calculations'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : 'Failed to save calculation')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (row: CalculationRow) =>
      apiRequest<{ success: boolean }>(
        `/calculations/${row.deviceId}/${encodeURIComponent(row.metric.key ?? '')}`,
        { method: 'DELETE' },
      ),
    onSuccess: async () => {
      message.success('Calculation removed.')
      await queryClient.invalidateQueries({ queryKey: ['calculations'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : 'Failed to delete calculation')
    },
  })

  function openCreate() {
    setEditing(null)
    form.setFieldsValue({
      deviceId: devices[0]?.id,
      key: '',
      label: '',
      unit: '',
      datatype: 'float',
      formula: '',
      inputs: [{ var: 'a', source: 'device', deviceId: null, metricKey: '' }],
      triggerMode: 'interval',
      intervalSec: 30,
      cron: '',
      decimals: 2,
      maxAgeSec: null,
    })
    setOpen(true)
  }

  function openEdit(row: CalculationRow) {
    const metric = row.metric
    const calc = metric.calc ?? {}
    setEditing(row)
    form.setFieldsValue({
      deviceId: row.deviceId,
      key: metric.key ?? '',
      label: metric.label ?? '',
      unit: metric.unit ?? '',
      datatype: metric.datatype === 'int' ? 'int' : 'float',
      formula: calc.formula ?? '',
      inputs: toFormInputs(calc.inputs as Array<Record<string, unknown>> | undefined),
      triggerMode:
        calc.trigger?.mode === 'on_pull' || calc.trigger?.mode === 'schedule'
          ? calc.trigger.mode
          : 'interval',
      intervalSec: calc.trigger?.intervalSec ?? 30,
      cron: calc.trigger?.cron ?? '',
      decimals: calc.decimals ?? 2,
      maxAgeSec: calc.maxAgeSec ?? null,
    })
    setOpen(true)
  }

  function metricOptions(deviceId: number | null | undefined) {
    const resolvedId = deviceId || outputDeviceId
    const device = devices.find((d) => d.id === resolvedId)
    return (device?.metrics ?? [])
      .filter((m) => m?.key)
      .map((m) => ({
        value: m.key as string,
        label: `${m.label ? `${m.label} · ` : ''}${m.key}`,
      }))
  }

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16 }}>
        <div>
          <Typography.Title level={4} style={{ marginBottom: 4 }}>
            Calculate
          </Typography.Title>
          <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
            Create derived metrics from device values or random inputs.
          </Typography.Paragraph>
        </div>
        <Button type="primary" icon={<i className="bi bi-plus-circle" />} onClick={openCreate}>
          New calculation
        </Button>
      </div>

      <Card>
        <Input
          allowClear
          prefix={<SearchOutlined />}
          placeholder="Search device, metric, or formula"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          style={{ marginBottom: 16 }}
        />

        <Table
          rowKey={(row) => `${row.deviceId}-${row.metric.key}`}
          loading={page.isLoading}
          dataSource={filtered}
          pagination={false}
          locale={{ emptyText: 'No calculations yet.' }}
          columns={[
            {
              title: 'Device',
              render: (_: unknown, row: CalculationRow) => (
                <div>
                  <strong>{row.deviceName}</strong>
                  <div style={{ color: 'rgba(0,0,0,0.45)', fontSize: 12 }}>#{row.deviceId}</div>
                </div>
              ),
            },
            {
              title: 'Metric',
              render: (_: unknown, row: CalculationRow) => (
                <div>
                  <code>{row.metric.key ?? '-'}</code>
                  <div style={{ color: 'rgba(0,0,0,0.45)', fontSize: 12 }}>{row.metric.label ?? ''}</div>
                </div>
              ),
            },
            {
              title: 'Formula',
              render: (_: unknown, row: CalculationRow) => (
                <code>{row.metric.calc?.formula ?? '-'}</code>
              ),
            },
            {
              title: 'Inputs',
              render: (_: unknown, row: CalculationRow) => (
                <div>
                  {(row.metric.calc?.inputs ?? []).map((input, index) => (
                    <div key={`${String(input.var)}-${index}`} style={{ fontSize: 12 }}>
                      <code>{formatInput(input)}</code>
                    </div>
                  ))}
                </div>
              ),
            },
            {
              title: 'Trigger',
              render: (_: unknown, row: CalculationRow) => (
                <span
                  className="badge text-bg-success"
                  style={{
                    display: 'inline-block',
                    padding: '0.35em 0.65em',
                    fontSize: '0.75em',
                    fontWeight: 700,
                    lineHeight: 1,
                    color: '#fff',
                    backgroundColor: '#198754',
                    borderRadius: '0.375rem',
                  }}
                >
                  {row.metric.calc?.trigger?.mode ?? 'interval'}
                </span>
              ),
            },
            {
              title: 'Actions',
              align: 'right',
              render: (_: unknown, row: CalculationRow) => (
                <Space>
                  <Button type="primary" ghost size="small" onClick={() => openEdit(row)}>
                    Edit
                  </Button>
                  <Button
                    size="small"
                    danger
                    ghost
                    onClick={() => {
                      modal.confirm({
                        title: 'Remove this calculation?',
                        okText: 'Delete',
                        okButtonProps: { danger: true },
                        onOk: () => deleteMutation.mutateAsync(row),
                      })
                    }}
                  >
                    Delete
                  </Button>
                </Space>
              ),
            },
          ]}
        />
      </Card>

      <Modal
        title="Calculation"
        open={open}
        width={960}
        destroyOnHidden
        okText="Save calculation"
        confirmLoading={saveMutation.isPending}
        onCancel={() => {
          setOpen(false)
          setEditing(null)
        }}
        onOk={() => form.submit()}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={(values) => saveMutation.mutate(values)}
          initialValues={{
            datatype: 'float',
            triggerMode: 'interval',
            intervalSec: 30,
            decimals: 2,
            inputs: [{ var: 'a', source: 'device', deviceId: null, metricKey: '' }],
          }}
        >
          <Row gutter={12}>
            <Col span={8}>
              <Form.Item
                name="deviceId"
                label="Output device"
                rules={[{ required: true, message: 'Output device is required' }]}
              >
                <Select
                  options={devices.map((d) => ({
                    value: d.id,
                    label: `${d.name} (#${d.id})`,
                  }))}
                />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item
                name="key"
                label="Metric key"
                rules={[{ required: true, message: 'Metric key is required' }]}
              >
                <Input />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="label" label="Label">
                <Input />
              </Form.Item>
            </Col>
            <Col span={4}>
              <Form.Item name="unit" label="Unit">
                <Input />
              </Form.Item>
            </Col>
            <Col span={4}>
              <Form.Item name="datatype" label="Datatype">
                <Select
                  options={[
                    { value: 'float', label: 'float' },
                    { value: 'int', label: 'int' },
                  ]}
                />
              </Form.Item>
            </Col>
            <Col span={20}>
              <Form.Item
                name="formula"
                label="Formula"
                extra="Use the variable names defined in Inputs."
                rules={[{ required: true, message: 'Formula is required' }]}
              >
                <Input className="font-monospace" placeholder="(a + b) / 2" style={{ fontFamily: 'monospace' }} />
              </Form.Item>
            </Col>
          </Row>

          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
            <Typography.Text strong>Inputs</Typography.Text>
            <Button
              type="primary"
              ghost
              size="small"
              icon={<i className="bi bi-plus" />}
              onClick={() => {
                const current = form.getFieldValue('inputs') as CalcInputForm[] | undefined
                form.setFieldsValue({
                  inputs: [
                    ...(current ?? []),
                    { var: '', source: 'device', deviceId: null, metricKey: '' },
                  ],
                })
              }}
            >
              Add input
            </Button>
          </div>

          <Form.List name="inputs">
            {(fields, { remove }) => (
              <Space direction="vertical" style={{ width: '100%' }} size={8}>
                {fields.map((field) => {
                  const source = inputsWatch?.[field.name]?.source ?? 'device'
                  const inputDeviceId = inputsWatch?.[field.name]?.deviceId
                  return (
                    <div
                      key={field.key}
                      style={{
                        border: '1px solid #f0f0f0',
                        borderRadius: 8,
                        padding: 12,
                      }}
                    >
                      <Row gutter={8} align="bottom">
                        <Col span={4}>
                          <Form.Item
                            name={[field.name, 'var']}
                            label="Variable"
                            style={{ marginBottom: 0 }}
                          >
                            <Input />
                          </Form.Item>
                        </Col>
                        <Col span={4}>
                          <Form.Item
                            name={[field.name, 'source']}
                            label="Source"
                            style={{ marginBottom: 0 }}
                          >
                            <Select
                              options={[
                                { value: 'device', label: 'Device metric' },
                                { value: 'random', label: 'Random' },
                              ]}
                              onChange={(value) => {
                                const current = [...(form.getFieldValue('inputs') as CalcInputForm[])]
                                current[field.name] =
                                  value === 'random'
                                    ? { var: current[field.name]?.var ?? '', source: 'random', min: 0, max: 1 }
                                    : {
                                        var: current[field.name]?.var ?? '',
                                        source: 'device',
                                        deviceId: null,
                                        metricKey: '',
                                      }
                                form.setFieldsValue({ inputs: current })
                              }}
                            />
                          </Form.Item>
                        </Col>
                        {source === 'random' ? (
                          <>
                            <Col span={6}>
                              <Form.Item
                                name={[field.name, 'min']}
                                label="Minimum"
                                style={{ marginBottom: 0 }}
                              >
                                <InputNumber style={{ width: '100%' }} step="any" />
                              </Form.Item>
                            </Col>
                            <Col span={6}>
                              <Form.Item
                                name={[field.name, 'max']}
                                label="Maximum"
                                style={{ marginBottom: 0 }}
                              >
                                <InputNumber style={{ width: '100%' }} step="any" />
                              </Form.Item>
                            </Col>
                          </>
                        ) : (
                          <>
                            <Col span={6}>
                              <Form.Item
                                name={[field.name, 'deviceId']}
                                label="Device"
                                style={{ marginBottom: 0 }}
                              >
                                <Select
                                  allowClear
                                  placeholder="Same device"
                                  options={devices.map((d) => ({
                                    value: d.id,
                                    label: `${d.name} (#${d.id})`,
                                  }))}
                                  onChange={() => {
                                    const current = [...(form.getFieldValue('inputs') as CalcInputForm[])]
                                    if (current[field.name]) {
                                      current[field.name] = {
                                        ...current[field.name],
                                        metricKey: '',
                                      }
                                      form.setFieldsValue({ inputs: current })
                                    }
                                  }}
                                />
                              </Form.Item>
                            </Col>
                            <Col span={6}>
                              <Form.Item
                                name={[field.name, 'metricKey']}
                                label="Metric"
                                style={{ marginBottom: 0 }}
                              >
                                <Select
                                  options={metricOptions(inputDeviceId)}
                                  placeholder="Select metric"
                                />
                              </Form.Item>
                            </Col>
                          </>
                        )}
                        <Col span={4}>
                          <Button
                            danger
                            ghost
                            block
                            icon={<i className="bi bi-trash" />}
                            onClick={() => remove(field.name)}
                          />
                        </Col>
                      </Row>
                    </div>
                  )
                })}
              </Space>
            )}
          </Form.List>

          <Row gutter={12} style={{ marginTop: 16 }}>
            <Col span={6}>
              <Form.Item name="triggerMode" label="Trigger">
                <Select
                  options={[
                    { value: 'interval', label: 'Interval' },
                    { value: 'on_pull', label: 'On input update' },
                    { value: 'schedule', label: 'Cron schedule' },
                  ]}
                />
              </Form.Item>
            </Col>
            {triggerMode === 'interval' || !triggerMode ? (
              <Col span={6}>
                <Form.Item name="intervalSec" label="Interval (seconds)">
                  <InputNumber min={1} style={{ width: '100%' }} />
                </Form.Item>
              </Col>
            ) : null}
            {triggerMode === 'schedule' ? (
              <Col span={12}>
                <Form.Item name="cron" label="Cron">
                  <Input placeholder="*/5 * * * *" style={{ fontFamily: 'monospace' }} />
                </Form.Item>
              </Col>
            ) : null}
            <Col span={6}>
              <Form.Item name="decimals" label="Decimals">
                <InputNumber min={0} max={8} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="maxAgeSec" label="Max input age (seconds)">
                <InputNumber min={0} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>
    </Space>
  )
}
