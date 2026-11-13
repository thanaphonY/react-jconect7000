import {
  Button,
  Card,
  Checkbox,
  Col,
  Input,
  InputNumber,
  Row,
  Select,
  Space,
  Switch,
  Tabs,
  Typography,
} from 'antd'
import { useMemo, useState } from 'react'
import {
  DATATYPE_OPTIONS,
  DEFAULT_TYPE,
  PROTOCOL_FIELDS,
  SEVERITIES,
  STORE_POLICIES,
  TRIGGER_OPS,
  TYPE_OPTIONS,
  UNIT_OPTIONS,
  emptyMetric,
} from './metricConstants'
import { ScheduleGrid } from './ScheduleGrid'
import type {
  CalcInput,
  Metric,
  MetricSourceDevice,
  MetricTrigger,
  NotificationChannel,
  SnapshotTrigger,
} from './types'
import './metricBuilder.css'

type Props = {
  protocol: string
  metrics: Metric[]
  onChange: (metrics: Metric[]) => void
  readOnly?: boolean
  snapshotTriggers: SnapshotTrigger[]
  onSnapshotChange: (triggers: SnapshotTrigger[]) => void
  snapshotReadOnly?: boolean
  metricSources?: MetricSourceDevice[]
  gatewayMetricKeys?: string[]
  deviceRole?: string
  channels?: NotificationChannel[]
}

function asObj(m: Metric): Record<string, unknown> {
  return m as Record<string, unknown>
}

function normalConditionObj(metric: Metric): Record<string, unknown> {
  if (Array.isArray(metric.normal_condition)) {
    return (metric.normal_condition[0] as Record<string, unknown>) || {}
  }
  return (metric.normal_condition as Record<string, unknown>) || {}
}

function ChannelCheckboxes({
  channels,
  selected,
  onChange,
  disabled,
}: {
  channels: NotificationChannel[]
  selected: Array<string | number> | undefined
  onChange: (ids: number[]) => void
  disabled?: boolean
}) {
  const selectedIds = (selected || []).map(Number)
  if (!channels.length) {
    return <Typography.Text type="secondary">No notification channels configured.</Typography.Text>
  }
  return (
    <div className="jmb-channel-grid">
      {channels.map((ch) => {
        const id = Number(ch.id)
        const inactive = ch.isActive === false
        return (
          <Checkbox
            key={id}
            disabled={disabled}
            checked={selectedIds.includes(id)}
            onChange={(e) => {
              const next = e.target.checked
                ? Array.from(new Set([...selectedIds, id]))
                : selectedIds.filter((v) => v !== id)
              onChange(next)
            }}
          >
            {ch.name || `Channel #${id}`}
            {inactive ? <small style={{ marginLeft: 4 }}>(Inactive)</small> : null}
          </Checkbox>
        )
      })}
    </div>
  )
}

export function MetricBuilder({
  protocol,
  metrics,
  onChange,
  readOnly = false,
  snapshotTriggers,
  onSnapshotChange,
  snapshotReadOnly = false,
  metricSources = [],
  gatewayMetricKeys = [],
  deviceRole,
  channels = [],
}: Props) {
  const [selected, setSelected] = useState(0)
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState('all')
  const [jsonText, setJsonText] = useState('')
  const [jsonError, setJsonError] = useState<string | null>(null)
  const [showJson, setShowJson] = useState(false)

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    return metrics
      .map((metric, index) => ({ metric, index }))
      .filter(({ metric }) => {
        if (filter === 'active' && metric.enable === false) return false
        if (filter === 'inactive' && metric.enable !== false) return false
        if (filter === 'triggers' && !(Array.isArray(metric.triggers) && metric.triggers.length))
          return false
        if (!q) return true
        return (
          String(metric.label || '')
            .toLowerCase()
            .includes(q) ||
          String(metric.key || '')
            .toLowerCase()
            .includes(q) ||
          String(metric.type || '')
            .toLowerCase()
            .includes(q)
        )
      })
  }, [metrics, search, filter])

  const current = metrics[selected] ?? null
  const protoFields = PROTOCOL_FIELDS[protocol] || []
  const typeOpts = [...(TYPE_OPTIONS[protocol] || []), ['calc', 'calc'] as [string, string]]
  const showSnapshots = String(protocol).toLowerCase() === 'rtsp'

  function updateMetric(index: number, patch: Record<string, unknown>) {
    if (readOnly) return
    const next = metrics.map((m, i) => (i === index ? { ...m, ...patch } : m))
    onChange(next)
  }

  function setPath(index: number, key: string, value: unknown) {
    updateMetric(index, { [key]: value })
  }

  function addMetric(calc = false) {
    if (readOnly) return
    const base = emptyMetric(protocol)
    if (calc) {
      base.type = 'calc'
      base.calc = {
        formula: '',
        inputs: [{ var: 'a', source: 'device', deviceId: '', metricKey: '' }],
        trigger: { mode: 'interval', intervalSec: 30 },
        decimals: 2,
      }
    }
    onChange([...metrics, base as Metric])
    setSelected(metrics.length)
  }

  function removeMetric(index: number) {
    if (readOnly) return
    const next = metrics.filter((_, i) => i !== index)
    onChange(next)
    setSelected(Math.max(0, Math.min(selected, next.length - 1)))
  }

  function duplicateMetric(index: number) {
    if (readOnly) return
    const copy = JSON.parse(JSON.stringify(metrics[index])) as Metric
    copy.key = `${copy.key || 'metric'}_copy`
    copy.label = `${copy.label || 'Metric'} (copy)`
    const next = [...metrics]
    next.splice(index + 1, 0, copy)
    onChange(next)
    setSelected(index + 1)
  }

  function openJson() {
    setJsonText(JSON.stringify(metrics, null, 2))
    setJsonError(null)
    setShowJson(true)
  }

  function applyJson() {
    try {
      const parsed = JSON.parse(jsonText) as unknown
      if (!Array.isArray(parsed)) throw new Error('Metrics must be a JSON array')
      onChange(parsed as Metric[])
      setJsonError(null)
      setShowJson(false)
      setSelected(0)
    } catch (err) {
      setJsonError(err instanceof Error ? err.message : 'Invalid JSON')
    }
  }

  function updateTrigger(metricIndex: number, triggerIndex: number, patch: Partial<MetricTrigger>) {
    const metric = metrics[metricIndex]
    const triggers = [...(metric.triggers || [])]
    triggers[triggerIndex] = { ...triggers[triggerIndex], ...patch }
    updateMetric(metricIndex, { triggers })
  }

  function addTrigger(metricIndex: number) {
    const metric = metrics[metricIndex]
    const triggers = [
      ...(metric.triggers || []),
      {
        enable: true,
        operator: '>',
        value: '',
        message: '',
        severity: 'warning',
        clear_condition: { operator: '', value: '', message: '' },
        channels: [],
        duration: [],
      },
    ]
    updateMetric(metricIndex, { triggers })
  }

  function removeTrigger(metricIndex: number, triggerIndex: number) {
    const metric = metrics[metricIndex]
    const triggers = (metric.triggers || []).filter((_, i) => i !== triggerIndex)
    updateMetric(metricIndex, { triggers })
  }

  function updateSnapshot(index: number, patch: Partial<SnapshotTrigger>) {
    if (snapshotReadOnly) return
    const next = snapshotTriggers.map((t, i) => (i === index ? { ...t, ...patch } : t))
    onSnapshotChange(next)
  }

  function calcInputs(metric: Metric): CalcInput[] {
    const calc = (metric.calc || {}) as { inputs?: CalcInput[] }
    return Array.isArray(calc.inputs) ? calc.inputs : []
  }

  function setCalc(metricIndex: number, patch: Record<string, unknown>) {
    const metric = metrics[metricIndex]
    setPath(metricIndex, 'calc', { ...(metric.calc || {}), ...patch })
  }

  function updateCalcInput(metricIndex: number, inputIndex: number, patch: Partial<CalcInput>) {
    const inputs = [...calcInputs(metrics[metricIndex])]
    inputs[inputIndex] = { ...inputs[inputIndex], ...patch }
    setCalc(metricIndex, { inputs })
  }

  function metricOptionsForDevice(deviceId: string | number | undefined) {
    if (deviceId === '' || deviceId == null) {
      return metrics
        .map((m) => ({ value: String(m.key || ''), label: m.label || m.key || '' }))
        .filter((o) => o.value)
    }
    const source = metricSources.find((d) => d.id === Number(deviceId))
    return (source?.metrics || []).map((m) => ({
      value: m.key,
      label: m.label || m.key,
    }))
  }

  const sourceDeviceOptions = metricSources.map((d) => ({
    value: String(d.id),
    label: `${d.name} (#${d.id})`,
  }))

  const thisDeviceOptions = [
    { value: '', label: 'This device' },
    ...sourceDeviceOptions,
  ]

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
        <Space>
          <Typography.Text strong>Metrics definition</Typography.Text>
          <Typography.Text type="secondary">
            {metrics.length} metrics ·{' '}
            {metrics.filter((m) => m.enable !== false).length} active ·{' '}
            {metrics.reduce((n, m) => n + (m.triggers?.length || 0), 0)} triggers
            {readOnly ? ' · Read-only from template' : ''}
          </Typography.Text>
        </Space>
        <Space wrap>
          {!readOnly && (
            <>
              <Button size="small" icon={<i className="bi bi-plus" />} onClick={() => addMetric(false)}>
                Add metric
              </Button>
              <Button
                size="small"
                icon={<i className="bi bi-calculator" />}
                onClick={() => addMetric(true)}
              >
                Calculate
              </Button>
            </>
          )}
          <Button size="small" icon={<i className="bi bi-braces" />} onClick={openJson}>
            {readOnly ? 'View JSON' : 'Edit JSON'}
          </Button>
        </Space>
      </Space>

      {showJson && (
        <Card size="small" title="Metrics JSON">
          <Input.TextArea
            rows={14}
            value={jsonText}
            readOnly={readOnly}
            onChange={(e) => setJsonText(e.target.value)}
            style={{ fontFamily: 'monospace' }}
          />
          {jsonError && <Typography.Text type="danger">{jsonError}</Typography.Text>}
          <Space style={{ marginTop: 8 }}>
            {!readOnly && (
              <Button type="primary" onClick={applyJson}>
                Apply JSON
              </Button>
            )}
            <Button onClick={() => setShowJson(false)}>Close</Button>
          </Space>
        </Card>
      )}

      <Row gutter={16}>
        <Col xs={24} md={8}>
          <Space direction="vertical" style={{ width: '100%' }} size={8}>
            <Input
              prefix={<i className="bi bi-search" />}
              placeholder="Search metrics"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              allowClear
            />
            <Select
              style={{ width: '100%' }}
              value={filter}
              onChange={setFilter}
              options={[
                { value: 'all', label: 'All metrics' },
                { value: 'active', label: 'Active only' },
                { value: 'inactive', label: 'Inactive only' },
                { value: 'triggers', label: 'With triggers' },
              ]}
            />
            <div
              style={{
                maxHeight: 420,
                overflow: 'auto',
                border: '1px solid #f0f0f0',
                borderRadius: 8,
              }}
            >
              {filtered.map(({ metric, index }) => (
                <button
                  key={index}
                  type="button"
                  onClick={() => setSelected(index)}
                  style={{
                    display: 'block',
                    width: '100%',
                    textAlign: 'left',
                    padding: '10px 12px',
                    border: 'none',
                    borderBottom: '1px solid #f0f0f0',
                    background: selected === index ? '#e6f4ff' : '#fff',
                    cursor: 'pointer',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                    <strong>{metric.label || metric.key || 'Unnamed metric'}</strong>
                    <span
                      style={{
                        width: 8,
                        height: 8,
                        borderRadius: '50%',
                        marginTop: 6,
                        background: metric.enable === false ? '#bbb' : '#52c41a',
                      }}
                    />
                  </div>
                  <small style={{ color: '#666' }}>{metric.key || 'no key'}</small>
                </button>
              ))}
              {!filtered.length && <div style={{ padding: 16, color: '#999' }}>No metrics</div>}
            </div>
          </Space>
        </Col>
        <Col xs={24} md={16}>
          {current ? (
            <Card
              size="small"
              title={current.label || current.key || `Metric #${selected + 1}`}
              extra={
                !readOnly ? (
                  <Space>
                    <Button
                      size="small"
                      icon={<i className="bi bi-copy" />}
                      onClick={() => duplicateMetric(selected)}
                    />
                    <Button
                      size="small"
                      danger
                      icon={<i className="bi bi-trash" />}
                      onClick={() => removeMetric(selected)}
                    />
                  </Space>
                ) : null
              }
            >
              <Tabs
                items={[
                  {
                    key: 'setup',
                    label: (
                      <span>
                        <i className="bi bi-sliders" style={{ marginRight: 6 }} />
                        Setup
                      </span>
                    ),
                    children: (
                      <Row gutter={[12, 12]}>
                        <Col span={12}>
                          <Typography.Text>Label</Typography.Text>
                          <Input
                            disabled={readOnly}
                            value={String(current.label ?? '')}
                            onChange={(e) => setPath(selected, 'label', e.target.value)}
                          />
                        </Col>
                        <Col span={12}>
                          <Typography.Text>Key</Typography.Text>
                          <Input
                            disabled={readOnly}
                            value={String(current.key ?? '')}
                            onChange={(e) => setPath(selected, 'key', e.target.value)}
                          />
                        </Col>
                        <Col span={12}>
                          <Typography.Text>Unit</Typography.Text>
                          <Select
                            disabled={readOnly}
                            style={{ width: '100%' }}
                            showSearch
                            allowClear
                            value={current.unit || undefined}
                            onChange={(v) => setPath(selected, 'unit', v ?? '')}
                            options={UNIT_OPTIONS.filter(Boolean).map((u) => ({
                              value: u,
                              label: u,
                            }))}
                          />
                        </Col>
                        <Col span={12}>
                          <Typography.Text>Enable</Typography.Text>
                          <div>
                            <Switch
                              disabled={readOnly}
                              checked={current.enable !== false}
                              onChange={(v) => setPath(selected, 'enable', v)}
                            />
                          </div>
                        </Col>
                        <Col span={12}>
                          <Typography.Text>Type</Typography.Text>
                          <Select
                            disabled={readOnly}
                            style={{ width: '100%' }}
                            value={String(current.type || DEFAULT_TYPE[protocol] || '')}
                            onChange={(v) => setPath(selected, 'type', v)}
                            options={typeOpts.map(([v, l]) => ({ value: v, label: l }))}
                          />
                        </Col>
                        <Col span={12}>
                          <Typography.Text>Datatype</Typography.Text>
                          <Select
                            disabled={readOnly}
                            style={{ width: '100%' }}
                            value={String(current.datatype || 'float')}
                            onChange={(v) => setPath(selected, 'datatype', v)}
                            options={DATATYPE_OPTIONS.map(([v, l]) => ({ value: v, label: l }))}
                          />
                        </Col>
                        <Col span={12}>
                          <Typography.Text>Pull rate (seconds)</Typography.Text>
                          <InputNumber
                            disabled={readOnly}
                            style={{ width: '100%' }}
                            min={0}
                            value={Number(current.pull_rate ?? 10)}
                            onChange={(v) => setPath(selected, 'pull_rate', v ?? 10)}
                          />
                        </Col>
                        <Col span={12}>
                          <Typography.Text>Scale</Typography.Text>
                          <InputNumber
                            disabled={readOnly}
                            style={{ width: '100%' }}
                            value={Number(current.scale ?? 1)}
                            onChange={(v) => setPath(selected, 'scale', v ?? 1)}
                          />
                        </Col>
                        <Col span={12}>
                          <Typography.Text>Store policy</Typography.Text>
                          <Select
                            disabled={readOnly}
                            style={{ width: '100%' }}
                            value={String(current.store_policy || 'always')}
                            onChange={(v) => setPath(selected, 'store_policy', v)}
                            options={STORE_POLICIES.map(([v, l]) => ({ value: v, label: l }))}
                          />
                        </Col>
                        {current.store_policy === 'interval' && (
                          <Col span={12}>
                            <Typography.Text>Store interval (minutes)</Typography.Text>
                            <InputNumber
                              disabled={readOnly}
                              style={{ width: '100%' }}
                              min={1}
                              value={Number(current.interval_minutes ?? 5)}
                              onChange={(v) => setPath(selected, 'interval_minutes', v ?? 5)}
                            />
                          </Col>
                        )}
                      </Row>
                    ),
                  },
                  {
                    key: 'source',
                    label: (
                      <span>
                        <i className="bi bi-link-45deg" style={{ marginRight: 6 }} />
                        Source
                      </span>
                    ),
                    children: (
                      <Row gutter={[12, 12]}>
                        {deviceRole === 'endpoint' ? (
                          <Col span={24}>
                            <Typography.Text>Gateway metric key</Typography.Text>
                            <Select
                              disabled={readOnly}
                              style={{ width: '100%' }}
                              value={
                                (current.source as { gateway_metric_key?: string } | undefined)
                                  ?.gateway_metric_key || undefined
                              }
                              onChange={(v) =>
                                setPath(selected, 'source', {
                                  ...(typeof current.source === 'object' && current.source
                                    ? current.source
                                    : {}),
                                  gateway_metric_key: v,
                                })
                              }
                              options={gatewayMetricKeys.map((k) => ({ value: k, label: k }))}
                              allowClear
                              showSearch
                            />
                          </Col>
                        ) : (
                          protoFields
                            .filter((f) => f !== 'type')
                            .map((field) => (
                              <Col span={12} key={field}>
                                <Typography.Text>{field.replace(/_/g, ' ')}</Typography.Text>
                                {field === 'function_code' ? (
                                  <Select
                                    disabled={readOnly}
                                    style={{ width: '100%' }}
                                    value={String(asObj(current)[field] ?? '3')}
                                    onChange={(v) => setPath(selected, field, Number(v))}
                                    options={[
                                      { value: '1', label: '1 Read Coils' },
                                      { value: '2', label: '2 Read Discrete Inputs' },
                                      { value: '3', label: '3 Read Holding Registers' },
                                      { value: '4', label: '4 Read Input Registers' },
                                    ]}
                                  />
                                ) : field === 'byte_order' ? (
                                  <Select
                                    disabled={readOnly}
                                    style={{ width: '100%' }}
                                    value={String(asObj(current)[field] ?? 'BE')}
                                    onChange={(v) => setPath(selected, field, v)}
                                    options={[
                                      { value: 'BE', label: 'Big endian' },
                                      { value: 'LE', label: 'Little endian' },
                                    ]}
                                  />
                                ) : (
                                  <Input
                                    disabled={readOnly}
                                    value={
                                      asObj(current)[field] == null
                                        ? ''
                                        : String(asObj(current)[field])
                                    }
                                    onChange={(e) => setPath(selected, field, e.target.value)}
                                  />
                                )}
                              </Col>
                            ))
                        )}
                        {protocol === 'visual' && String(current.type).includes('number') && (
                          <>
                            <Col span={8}>
                              <Typography.Text>Min</Typography.Text>
                              <InputNumber
                                disabled={readOnly}
                                style={{ width: '100%' }}
                                value={Number((current.visual as { min?: number })?.min ?? 0)}
                                onChange={(v) =>
                                  setPath(selected, 'visual', {
                                    ...(current.visual || {}),
                                    min: v,
                                  })
                                }
                              />
                            </Col>
                            <Col span={8}>
                              <Typography.Text>Max</Typography.Text>
                              <InputNumber
                                disabled={readOnly}
                                style={{ width: '100%' }}
                                value={Number((current.visual as { max?: number })?.max ?? 100)}
                                onChange={(v) =>
                                  setPath(selected, 'visual', {
                                    ...(current.visual || {}),
                                    max: v,
                                  })
                                }
                              />
                            </Col>
                            <Col span={8}>
                              <Typography.Text>Decimals</Typography.Text>
                              <InputNumber
                                disabled={readOnly}
                                style={{ width: '100%' }}
                                value={Number(
                                  (current.visual as { decimals?: number })?.decimals ?? 2,
                                )}
                                onChange={(v) =>
                                  setPath(selected, 'visual', {
                                    ...(current.visual || {}),
                                    decimals: v,
                                  })
                                }
                              />
                            </Col>
                          </>
                        )}
                        {!protoFields.length &&
                          deviceRole !== 'endpoint' &&
                          protocol !== 'visual' && (
                            <Col span={24}>
                              <Typography.Text type="secondary">
                                No protocol source fields for {protocol}. Use JSON editor for
                                extras.
                              </Typography.Text>
                            </Col>
                          )}
                      </Row>
                    ),
                  },
                  {
                    key: 'calc',
                    label: (
                      <span>
                        <i className="bi bi-calculator" style={{ marginRight: 6 }} />
                        Logic
                      </span>
                    ),
                    children:
                      current.type === 'calc' ? (
                        <Space direction="vertical" style={{ width: '100%' }} size={12}>
                          <div>
                            <Typography.Text>Formula</Typography.Text>
                            <Input
                              disabled={readOnly}
                              placeholder="(a + b) / 2"
                              value={String(
                                (current.calc as { formula?: string })?.formula ?? '',
                              )}
                              onChange={(e) => setCalc(selected, { formula: e.target.value })}
                            />
                            <div className="jmb-help">
                              Use input variables in the formula, for example (a + b) / 2.
                            </div>
                          </div>
                          <Space style={{ width: '100%', justifyContent: 'space-between' }}>
                            <Typography.Text strong>Inputs (variable → source)</Typography.Text>
                            {!readOnly && (
                              <Button
                                size="small"
                                icon={<i className="bi bi-plus" />}
                                onClick={() =>
                                  setCalc(selected, {
                                    inputs: [
                                      ...calcInputs(current),
                                      { var: '', source: 'device', deviceId: '', metricKey: '' },
                                    ],
                                  })
                                }
                              >
                                Add input
                              </Button>
                            )}
                          </Space>
                          {calcInputs(current).map((input, ii) => {
                            const source = input.source === 'random' ? 'random' : 'device'
                            return (
                              <Card key={ii} size="small">
                                <Row gutter={[8, 8]} align="bottom">
                                  <Col span={4}>
                                    <Typography.Text>Variable</Typography.Text>
                                    <Input
                                      disabled={readOnly}
                                      placeholder="a"
                                      value={String(input.var ?? '')}
                                      onChange={(e) =>
                                        updateCalcInput(selected, ii, { var: e.target.value })
                                      }
                                    />
                                  </Col>
                                  <Col span={4}>
                                    <Typography.Text>Source</Typography.Text>
                                    <Select
                                      disabled={readOnly}
                                      style={{ width: '100%' }}
                                      value={source}
                                      onChange={(v) =>
                                        updateCalcInput(selected, ii, { source: v })
                                      }
                                      options={[
                                        { value: 'device', label: 'Device metric' },
                                        { value: 'random', label: 'Random number' },
                                      ]}
                                    />
                                  </Col>
                                  {source === 'random' ? (
                                    <>
                                      <Col span={6}>
                                        <Typography.Text>Minimum</Typography.Text>
                                        <InputNumber
                                          disabled={readOnly}
                                          style={{ width: '100%' }}
                                          value={Number(input.min ?? 0)}
                                          onChange={(v) =>
                                            updateCalcInput(selected, ii, { min: v ?? 0 })
                                          }
                                        />
                                      </Col>
                                      <Col span={6}>
                                        <Typography.Text>Maximum</Typography.Text>
                                        <InputNumber
                                          disabled={readOnly}
                                          style={{ width: '100%' }}
                                          value={Number(input.max ?? 1)}
                                          onChange={(v) =>
                                            updateCalcInput(selected, ii, { max: v ?? 1 })
                                          }
                                        />
                                      </Col>
                                    </>
                                  ) : (
                                    <>
                                      <Col span={6}>
                                        <Typography.Text>Device</Typography.Text>
                                        <Select
                                          disabled={readOnly}
                                          style={{ width: '100%' }}
                                          value={
                                            input.deviceId == null || input.deviceId === ''
                                              ? ''
                                              : String(input.deviceId)
                                          }
                                          onChange={(v) =>
                                            updateCalcInput(selected, ii, {
                                              deviceId: v,
                                              metricKey: '',
                                            })
                                          }
                                          options={thisDeviceOptions}
                                          showSearch
                                          optionFilterProp="label"
                                        />
                                      </Col>
                                      <Col span={6}>
                                        <Typography.Text>Metric</Typography.Text>
                                        <Select
                                          disabled={readOnly}
                                          style={{ width: '100%' }}
                                          value={input.metricKey || undefined}
                                          onChange={(v) =>
                                            updateCalcInput(selected, ii, { metricKey: v })
                                          }
                                          options={metricOptionsForDevice(input.deviceId)}
                                          showSearch
                                          allowClear
                                        />
                                      </Col>
                                    </>
                                  )}
                                  <Col span={4}>
                                    {!readOnly && (
                                      <Button
                                        danger
                                        block
                                        icon={<i className="bi bi-trash" />}
                                        onClick={() =>
                                          setCalc(selected, {
                                            inputs: calcInputs(current).filter(
                                              (_, i) => i !== ii,
                                            ),
                                          })
                                        }
                                      />
                                    )}
                                  </Col>
                                </Row>
                              </Card>
                            )
                          })}
                          <Row gutter={[12, 12]}>
                            <Col span={8}>
                              <Typography.Text>Trigger mode</Typography.Text>
                              <Select
                                disabled={readOnly}
                                style={{ width: '100%' }}
                                value={
                                  (current.calc as { trigger?: { mode?: string } })?.trigger
                                    ?.mode || 'interval'
                                }
                                onChange={(v) =>
                                  setCalc(selected, {
                                    trigger: {
                                      ...((current.calc as { trigger?: object })?.trigger || {}),
                                      mode: v,
                                    },
                                  })
                                }
                                options={[
                                  { value: 'interval', label: 'Interval' },
                                  { value: 'on_pull', label: 'On metric pull' },
                                  { value: 'schedule', label: 'Schedule (cron)' },
                                ]}
                              />
                            </Col>
                            {(
                              (current.calc as { trigger?: { mode?: string } })?.trigger?.mode ||
                              'interval'
                            ) === 'interval' && (
                              <Col span={8}>
                                <Typography.Text>Interval (seconds)</Typography.Text>
                                <InputNumber
                                  disabled={readOnly}
                                  style={{ width: '100%' }}
                                  value={Number(
                                    (current.calc as { trigger?: { intervalSec?: number } })
                                      ?.trigger?.intervalSec ?? 30,
                                  )}
                                  onChange={(v) =>
                                    setCalc(selected, {
                                      trigger: {
                                        ...((current.calc as { trigger?: object })?.trigger ||
                                          {}),
                                        intervalSec: v,
                                      },
                                    })
                                  }
                                />
                              </Col>
                            )}
                            {(current.calc as { trigger?: { mode?: string } })?.trigger?.mode ===
                              'schedule' && (
                              <Col span={16}>
                                <Typography.Text>Cron</Typography.Text>
                                <Input
                                  disabled={readOnly}
                                  placeholder="*/5 * * * *"
                                  value={String(
                                    (current.calc as { trigger?: { cron?: string } })?.trigger
                                      ?.cron ?? '',
                                  )}
                                  onChange={(e) =>
                                    setCalc(selected, {
                                      trigger: {
                                        ...((current.calc as { trigger?: object })?.trigger ||
                                          {}),
                                        cron: e.target.value,
                                      },
                                    })
                                  }
                                />
                              </Col>
                            )}
                            <Col span={8}>
                              <Typography.Text>Decimals</Typography.Text>
                              <InputNumber
                                disabled={readOnly}
                                style={{ width: '100%' }}
                                min={0}
                                max={8}
                                value={Number(
                                  (current.calc as { decimals?: number })?.decimals ?? 2,
                                )}
                                onChange={(v) => setCalc(selected, { decimals: v })}
                              />
                            </Col>
                            <Col span={8}>
                              <Typography.Text>Max input age (seconds)</Typography.Text>
                              <InputNumber
                                disabled={readOnly}
                                style={{ width: '100%' }}
                                value={
                                  (current.calc as { maxAgeSec?: number })?.maxAgeSec == null
                                    ? undefined
                                    : Number(
                                        (current.calc as { maxAgeSec?: number }).maxAgeSec,
                                      )
                                }
                                onChange={(v) => setCalc(selected, { maxAgeSec: v })}
                              />
                            </Col>
                          </Row>
                        </Space>
                      ) : (
                        <Typography.Text type="secondary">
                          Set Type to <code>calc</code> to edit calculation fields.
                        </Typography.Text>
                      ),
                  },
                  {
                    key: 'alarms',
                    label: (
                      <span>
                        <i className="bi bi-bell" style={{ marginRight: 6 }} />
                        Alarms
                      </span>
                    ),
                    children: (
                      <Space direction="vertical" style={{ width: '100%' }} size={12}>
                        <Typography.Text strong>Normal condition</Typography.Text>
                        <Row gutter={12}>
                          <Col span={8}>
                            <Select
                              disabled={readOnly}
                              style={{ width: '100%' }}
                              placeholder="Operator"
                              value={normalConditionObj(current).operator as string | undefined}
                              onChange={(v) =>
                                setPath(selected, 'normal_condition', {
                                  ...normalConditionObj(current),
                                  operator: v,
                                })
                              }
                              options={TRIGGER_OPS.filter(Boolean).map((o) => ({
                                value: o,
                                label: o,
                              }))}
                              allowClear
                            />
                          </Col>
                          <Col span={8}>
                            <Input
                              disabled={readOnly}
                              placeholder="Value"
                              value={String(normalConditionObj(current).value ?? '')}
                              onChange={(e) =>
                                setPath(selected, 'normal_condition', {
                                  ...normalConditionObj(current),
                                  value: e.target.value,
                                })
                              }
                            />
                          </Col>
                          <Col span={8}>
                            <Input
                              disabled={readOnly}
                              placeholder="Message"
                              value={String(normalConditionObj(current).message ?? '')}
                              onChange={(e) =>
                                setPath(selected, 'normal_condition', {
                                  ...normalConditionObj(current),
                                  message: e.target.value,
                                })
                              }
                            />
                          </Col>
                        </Row>
                        <div>
                          <Typography.Text strong>
                            Notify channels when value returns to normal
                          </Typography.Text>
                          <div style={{ marginTop: 8 }}>
                            <ChannelCheckboxes
                              channels={channels}
                              selected={
                                normalConditionObj(current).channels as
                                  | Array<string | number>
                                  | undefined
                              }
                              disabled={readOnly}
                              onChange={(ids) =>
                                setPath(selected, 'normal_condition', {
                                  ...normalConditionObj(current),
                                  channels: ids,
                                })
                              }
                            />
                          </div>
                        </div>

                        <Space style={{ width: '100%', justifyContent: 'space-between' }}>
                          <Typography.Text strong>Triggers</Typography.Text>
                          {!readOnly && (
                            <Button
                              size="small"
                              icon={<i className="bi bi-plus" />}
                              onClick={() => addTrigger(selected)}
                            >
                              Add trigger
                            </Button>
                          )}
                        </Space>
                        {(current.triggers || []).map((trigger, ti) => (
                          <Card
                            key={ti}
                            size="small"
                            title={`Trigger #${ti + 1}`}
                            extra={
                              !readOnly ? (
                                <Button
                                  size="small"
                                  danger
                                  icon={<i className="bi bi-trash" />}
                                  onClick={() => removeTrigger(selected, ti)}
                                />
                              ) : null
                            }
                          >
                            <Row gutter={[8, 8]}>
                              <Col span={6}>
                                <Switch
                                  disabled={readOnly}
                                  checked={trigger.enable !== false}
                                  onChange={(v) => updateTrigger(selected, ti, { enable: v })}
                                />{' '}
                                Enable
                              </Col>
                              <Col span={6}>
                                <Select
                                  disabled={readOnly}
                                  style={{ width: '100%' }}
                                  value={trigger.operator || '>'}
                                  onChange={(v) => updateTrigger(selected, ti, { operator: v })}
                                  options={TRIGGER_OPS.filter(Boolean).map((o) => ({
                                    value: o,
                                    label: o,
                                  }))}
                                />
                              </Col>
                              <Col span={6}>
                                <Input
                                  disabled={readOnly}
                                  placeholder="Value"
                                  value={String(trigger.value ?? '')}
                                  onChange={(e) =>
                                    updateTrigger(selected, ti, { value: e.target.value })
                                  }
                                />
                              </Col>
                              <Col span={6}>
                                <Select
                                  disabled={readOnly}
                                  style={{ width: '100%' }}
                                  value={trigger.severity || 'warning'}
                                  onChange={(v) => updateTrigger(selected, ti, { severity: v })}
                                  options={SEVERITIES.map((s) => ({ value: s, label: s }))}
                                />
                              </Col>
                              <Col span={12}>
                                <Input
                                  disabled={readOnly}
                                  placeholder="Message"
                                  value={String(trigger.message ?? '')}
                                  onChange={(e) =>
                                    updateTrigger(selected, ti, { message: e.target.value })
                                  }
                                />
                              </Col>
                              <Col span={12}>
                                <InputNumber
                                  disabled={readOnly}
                                  style={{ width: '100%' }}
                                  placeholder="Repeat interval (sec)"
                                  value={
                                    trigger.repeat_interval == null
                                      ? undefined
                                      : Number(trigger.repeat_interval)
                                  }
                                  onChange={(v) =>
                                    updateTrigger(selected, ti, {
                                      repeat_interval: v ?? undefined,
                                    })
                                  }
                                />
                              </Col>
                              <Col span={8}>
                                <Select
                                  disabled={readOnly}
                                  style={{ width: '100%' }}
                                  placeholder="Clear op"
                                  value={trigger.clear_condition?.operator}
                                  onChange={(v) =>
                                    updateTrigger(selected, ti, {
                                      clear_condition: {
                                        ...(trigger.clear_condition || {}),
                                        operator: v,
                                      },
                                    })
                                  }
                                  options={TRIGGER_OPS.filter(Boolean).map((o) => ({
                                    value: o,
                                    label: o,
                                  }))}
                                  allowClear
                                />
                              </Col>
                              <Col span={8}>
                                <Input
                                  disabled={readOnly}
                                  placeholder="Clear value"
                                  value={String(trigger.clear_condition?.value ?? '')}
                                  onChange={(e) =>
                                    updateTrigger(selected, ti, {
                                      clear_condition: {
                                        ...(trigger.clear_condition || {}),
                                        value: e.target.value,
                                      },
                                    })
                                  }
                                />
                              </Col>
                              <Col span={8}>
                                <Input
                                  disabled={readOnly}
                                  placeholder="Clear message"
                                  value={String(trigger.clear_condition?.message ?? '')}
                                  onChange={(e) =>
                                    updateTrigger(selected, ti, {
                                      clear_condition: {
                                        ...(trigger.clear_condition || {}),
                                        message: e.target.value,
                                      },
                                    })
                                  }
                                />
                              </Col>
                              <Col span={24}>
                                <ScheduleGrid
                                  windows={
                                    Array.isArray(trigger.duration) ? trigger.duration : []
                                  }
                                  readOnly={readOnly}
                                  onChange={(duration) =>
                                    updateTrigger(selected, ti, { duration })
                                  }
                                />
                              </Col>
                              <Col span={24}>
                                <Typography.Text strong>Notify channels</Typography.Text>
                                <div style={{ marginTop: 8 }}>
                                  <ChannelCheckboxes
                                    channels={channels}
                                    selected={trigger.channels}
                                    disabled={readOnly}
                                    onChange={(ids) =>
                                      updateTrigger(selected, ti, { channels: ids })
                                    }
                                  />
                                </div>
                              </Col>
                            </Row>
                          </Card>
                        ))}
                        {!(current.triggers || []).length && (
                          <Typography.Text type="secondary">
                            No alarm triggers configured.
                          </Typography.Text>
                        )}
                      </Space>
                    ),
                  },
                  {
                    key: 'notes',
                    label: (
                      <span>
                        <i className="bi bi-card-text" style={{ marginRight: 6 }} />
                        Notes
                      </span>
                    ),
                    children: (
                      <Input.TextArea
                        disabled={readOnly}
                        rows={4}
                        value={String(current.description ?? '')}
                        onChange={(e) => setPath(selected, 'description', e.target.value)}
                      />
                    ),
                  },
                ]}
              />
            </Card>
          ) : (
            <Typography.Text type="secondary">Select a metric</Typography.Text>
          )}
        </Col>
      </Row>

      {showSnapshots && (
        <Card
          size="small"
          title="Snapshot triggers"
          extra={
            !snapshotReadOnly ? (
              <Button
                size="small"
                icon={<i className="bi bi-plus" />}
                onClick={() =>
                  onSnapshotChange([
                    ...snapshotTriggers,
                    {
                      enable: true,
                      source_device_id: '',
                      source_metric_key: '',
                      source_trigger_index: '',
                      cooldown_sec: 30,
                    },
                  ])
                }
              >
                Add snapshot trigger
              </Button>
            ) : null
          }
        >
          <div className="jmb-help" style={{ marginBottom: 12 }}>
            Captures JPEG when the source alarm Rule matches or its Clear condition matches
            (rising edge), respecting enable + schedule.
          </div>
          {!snapshotTriggers.length && (
            <Typography.Text type="secondary">No snapshot triggers configured.</Typography.Text>
          )}
          <Space direction="vertical" style={{ width: '100%' }} size={12}>
            {snapshotTriggers.map((rule, index) => {
              const sourceId = Number(rule.source_device_id)
              const source = metricSources.find((d) => d.id === sourceId)
              const metricOpts = (source?.metrics || []).map((m) => ({
                value: m.key,
                label: m.label || m.key,
              }))
              const metric = (source?.metrics || []).find((m) => m.key === rule.source_metric_key)
              const triggerOpts = (metric?.triggers || []).map((_, ti) => ({
                value: String(ti),
                label: `Trigger #${ti + 1}`,
              }))
              return (
                <Card
                  key={index}
                  size="small"
                  title={`Snapshot #${index + 1}`}
                  extra={
                    !snapshotReadOnly ? (
                      <Button
                        size="small"
                        danger
                        icon={<i className="bi bi-trash" />}
                        onClick={() =>
                          onSnapshotChange(snapshotTriggers.filter((_, i) => i !== index))
                        }
                      />
                    ) : null
                  }
                >
                  <Row gutter={[8, 8]}>
                    <Col span={4}>
                      <Switch
                        disabled={snapshotReadOnly}
                        checked={rule.enable !== false}
                        onChange={(v) => updateSnapshot(index, { enable: v })}
                      />{' '}
                      Active
                    </Col>
                    <Col span={8}>
                      <Select
                        disabled={snapshotReadOnly}
                        style={{ width: '100%' }}
                        placeholder="Source device"
                        value={rule.source_device_id ? String(rule.source_device_id) : undefined}
                        onChange={(v) =>
                          updateSnapshot(index, {
                            source_device_id: v,
                            source_metric_key: '',
                            source_trigger_index: '',
                          })
                        }
                        options={sourceDeviceOptions}
                        showSearch
                        allowClear
                      />
                    </Col>
                    <Col span={6}>
                      <Select
                        disabled={snapshotReadOnly}
                        style={{ width: '100%' }}
                        placeholder="Source metric"
                        value={rule.source_metric_key || undefined}
                        onChange={(v) =>
                          updateSnapshot(index, {
                            source_metric_key: v,
                            source_trigger_index: '',
                          })
                        }
                        options={metricOpts}
                        showSearch
                        allowClear
                      />
                    </Col>
                    <Col span={6}>
                      <Select
                        disabled={snapshotReadOnly}
                        style={{ width: '100%' }}
                        placeholder="Source alarm trigger"
                        value={
                          rule.source_trigger_index === '' || rule.source_trigger_index == null
                            ? undefined
                            : String(rule.source_trigger_index)
                        }
                        onChange={(v) => updateSnapshot(index, { source_trigger_index: v })}
                        options={triggerOpts}
                        allowClear
                      />
                    </Col>
                    <Col span={6}>
                      <InputNumber
                        disabled={snapshotReadOnly}
                        style={{ width: '100%' }}
                        min={1}
                        addonBefore="Cooldown (sec)"
                        value={Number(rule.cooldown_sec ?? 30)}
                        onChange={(v) => updateSnapshot(index, { cooldown_sec: v ?? 30 })}
                      />
                    </Col>
                  </Row>
                </Card>
              )
            })}
          </Space>
        </Card>
      )}
    </Space>
  )
}
