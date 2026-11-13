import { Button, ColorPicker, Form, Input, InputNumber, Select, Space, Upload } from 'antd'
import type { Color } from 'antd/es/color-picker'
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { apiFormRequest } from '../../api/client'
import { NAVIGATOR_ICONS, WIDGET_ICON_OPTIONS, normalizeBiIconClass } from './icons'
import { metricLabel, useDeviceMetrics, useDeviceOptions } from './useDevices'
import type { LayoutSummary, MetricOption, WidgetField } from './types'
import { fieldsForWidgetType, mapImageUrl, svgWidgetUrl } from './widgetFieldMap'

type Props = {
  widgetType: string
  values: Record<string, unknown>
  onChange: (next: Record<string, unknown>) => void
  layouts?: LayoutSummary[]
}

function asString(value: unknown, fallback = ''): string {
  if (value === null || value === undefined) return fallback
  if (typeof value === 'object') return fallback
  return String(value)
}

function asArray<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : []
}

function DeviceSelect({
  value,
  onChange,
  label,
}: {
  value: string
  onChange: (v: string) => void
  label: string
}) {
  const devices = useDeviceOptions()
  return (
    <Form.Item label={label}>
      <Select
        showSearch
        optionFilterProp="label"
        allowClear
        value={value || undefined}
        placeholder="-- Select Device --"
        options={(devices.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))}
        onChange={(v) => onChange(v ?? '')}
        loading={devices.isLoading}
        style={{ width: '100%' }}
      />
    </Form.Item>
  )
}

function MetricSelect({
  deviceId,
  value,
  onChange,
  label,
  onMeta,
}: {
  deviceId: string
  value: string
  onChange: (v: string) => void
  label: string
  onMeta?: (m: MetricOption) => void
}) {
  const metrics = useDeviceMetrics(deviceId)
  return (
    <Form.Item label={label}>
      <Select
        showSearch
        optionFilterProp="label"
        allowClear
        disabled={!deviceId}
        value={value || undefined}
        placeholder="-- Select Metric --"
        options={(metrics.data ?? []).map((m) => ({
          value: String(m.key),
          label: metricLabel(m),
          metric: m,
        }))}
        onChange={(v, option) => {
          onChange(v ?? '')
          const opt = option as { metric?: MetricOption } | undefined
          if (opt?.metric && onMeta) onMeta(opt.metric)
        }}
        loading={metrics.isLoading}
        style={{ width: '100%' }}
      />
    </Form.Item>
  )
}

function IconSelect({
  value,
  onChange,
  label,
  options = WIDGET_ICON_OPTIONS,
}: {
  value: string
  onChange: (v: string) => void
  label: string
  options?: { label: string; value: string }[]
}) {
  return (
    <Form.Item label={label}>
      <Select
        showSearch
        optionFilterProp="label"
        value={value || options[0]?.value}
        onChange={onChange}
        style={{ width: '100%' }}
        optionLabelProp="label"
        options={options.map((o) => ({
          value: o.value,
          label: (
            <span>
              <i className={normalizeBiIconClass(o.value)} style={{ marginRight: 8 }} />
              {o.label}
            </span>
          ),
        }))}
      />
    </Form.Item>
  )
}

function WidgetColorField({
  label,
  value,
  onChange,
}: {
  label: string
  value: string
  onChange: (v: string) => void
}) {
  return (
    <Form.Item label={label}>
      <ColorPicker
        value={value || undefined}
        allowClear
        showText
        onChange={(color: Color | null, hex: string) => {
          onChange(color ? hex : '')
        }}
        onClear={() => onChange('')}
      />
    </Form.Item>
  )
}

type SeriesItem = {
  label?: string
  unit?: string
  color?: string
  device_id?: string
  metric_key?: string
  icon?: string
  [key: string]: unknown
}

function SeriesListEditor({
  items,
  onChange,
  withColor,
  withIcon,
}: {
  items: SeriesItem[]
  onChange: (items: SeriesItem[]) => void
  withColor?: boolean
  withIcon?: boolean
}) {
  return (
    <Space direction="vertical" style={{ width: '100%' }} size={8}>
      {items.map((item, index) => (
        <div
          key={index}
          style={{ border: '1px solid rgba(255,255,255,0.2)', borderRadius: 6, padding: 8 }}
        >
          <Space wrap style={{ width: '100%' }}>
            <Input
              placeholder="Label"
              value={item.label ?? ''}
              onChange={(e) => {
                const next = [...items]
                next[index] = { ...item, label: e.target.value }
                onChange(next)
              }}
              style={{ width: 140 }}
            />
            <Input
              placeholder="Unit"
              value={item.unit ?? ''}
              onChange={(e) => {
                const next = [...items]
                next[index] = { ...item, unit: e.target.value }
                onChange(next)
              }}
              style={{ width: 80 }}
            />
            {withColor ? (
              <ColorPicker
                value={item.color || '#1677ff'}
                onChange={(_c, hex) => {
                  const next = [...items]
                  next[index] = { ...item, color: hex }
                  onChange(next)
                }}
              />
            ) : null}
            {withIcon ? (
              <Select
                style={{ width: 160 }}
                value={item.icon || 'bi-circle'}
                options={WIDGET_ICON_OPTIONS.map((o) => ({
                  value: o.value,
                  label: (
                    <span>
                      <i className={normalizeBiIconClass(o.value)} style={{ marginRight: 6 }} />
                      {o.label}
                    </span>
                  ),
                }))}
                onChange={(v) => {
                  const next = [...items]
                  next[index] = { ...item, icon: v }
                  onChange(next)
                }}
              />
            ) : null}
            <Button
              danger
              size="small"
              onClick={() => onChange(items.filter((_, i) => i !== index))}
            >
              ×
            </Button>
          </Space>
          <div style={{ marginTop: 8 }}>
            <DeviceSelect
              label="Device"
              value={asString(item.device_id)}
              onChange={(v) => {
                const next = [...items]
                next[index] = { ...item, device_id: v, metric_key: '' }
                onChange(next)
              }}
            />
            <MetricSelect
              label="Metric"
              deviceId={asString(item.device_id)}
              value={asString(item.metric_key)}
              onChange={(v) => {
                const next = [...items]
                next[index] = { ...item, metric_key: v }
                onChange(next)
              }}
              onMeta={(m) => {
                const next = [...items]
                next[index] = {
                  ...item,
                  metric_key: String(m.key),
                  unit: item.unit || asString(m.unit),
                  label: item.label || metricLabel(m),
                }
                onChange(next)
              }}
            />
          </div>
        </div>
      ))}
      <Button type="dashed" block onClick={() => onChange([...items, {}])}>
        + Add
      </Button>
    </Space>
  )
}

type ValueMap = {
  value?: string
  label?: string
  icon_color?: string
  icon_effect?: string
}

function ValueMapsEditor({
  items,
  onChange,
}: {
  items: ValueMap[]
  onChange: (items: ValueMap[]) => void
}) {
  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      {items.map((item, index) => (
        <Space key={index} wrap>
          <Input
            placeholder="Value"
            value={item.value ?? ''}
            onChange={(e) => {
              const next = [...items]
              next[index] = { ...item, value: e.target.value }
              onChange(next)
            }}
            style={{ width: 80 }}
          />
          <Input
            placeholder="Label"
            value={item.label ?? ''}
            onChange={(e) => {
              const next = [...items]
              next[index] = { ...item, label: e.target.value }
              onChange(next)
            }}
            style={{ width: 120 }}
          />
          <ColorPicker
            value={item.icon_color || '#ffffff'}
            onChange={(_c, hex) => {
              const next = [...items]
              next[index] = { ...item, icon_color: hex }
              onChange(next)
            }}
          />
          <Select
            style={{ width: 120 }}
            value={item.icon_effect || 'none'}
            options={[
              { value: 'none', label: 'None' },
              { value: 'blink', label: 'Blink' },
              { value: 'spin', label: 'Spin' },
            ]}
            onChange={(v) => {
              const next = [...items]
              next[index] = { ...item, icon_effect: v }
              onChange(next)
            }}
          />
          <Button danger size="small" onClick={() => onChange(items.filter((_, i) => i !== index))}>
            ×
          </Button>
        </Space>
      ))}
      <Button type="dashed" onClick={() => onChange([...items, { icon_effect: 'none' }])}>
        + Add Value Map
      </Button>
    </Space>
  )
}

type ColorRange = { from?: string; to?: string; color?: string }

function ColorRangesEditor({
  items,
  onChange,
}: {
  items: ColorRange[]
  onChange: (items: ColorRange[]) => void
}) {
  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <div>Color Ranges</div>
      {items.map((item, index) => (
        <Space key={index}>
          <Input
            placeholder="From"
            value={item.from ?? ''}
            onChange={(e) => {
              const next = [...items]
              next[index] = { ...item, from: e.target.value }
              onChange(next)
            }}
            style={{ width: 80 }}
          />
          <Input
            placeholder="To"
            value={item.to ?? ''}
            onChange={(e) => {
              const next = [...items]
              next[index] = { ...item, to: e.target.value }
              onChange(next)
            }}
            style={{ width: 80 }}
          />
          <ColorPicker
            value={item.color || '#52c41a'}
            onChange={(_c, hex) => {
              const next = [...items]
              next[index] = { ...item, color: hex }
              onChange(next)
            }}
          />
          <Button danger size="small" onClick={() => onChange(items.filter((_, i) => i !== index))}>
            ×
          </Button>
        </Space>
      ))}
      <Button type="dashed" onClick={() => onChange([...items, {}])}>
        + Add Range
      </Button>
    </Space>
  )
}

type NavigatorItem = {
  layout_id?: string
  label?: string
  icon?: string
  color?: string
}

type MapItem = {
  x?: number
  y?: number
  label?: string
  device_id?: string
  metric_key?: string
  unit?: string
  show?: string
  url?: string
}

type SvgBinding = {
  id?: string
  mode?: string
  op?: string
  match_value?: string
  match_from?: string
  match_to?: string
  active_color?: string
  inactive_color?: string
  text_template?: string
  precision?: string
  opacity_on?: string
  opacity_off?: string
  class_name?: string
  device_id?: string
  metric_key?: string
  unit?: string
}

function setKey(
  values: Record<string, unknown>,
  onChange: (next: Record<string, unknown>) => void,
  key: string,
  value: unknown,
) {
  onChange({ ...values, [key]: value })
}

function FieldRenderer({
  field,
  values,
  onChange,
}: {
  field: WidgetField
  values: Record<string, unknown>
  onChange: (next: Record<string, unknown>) => void
}) {
  const key = field.key!
  const value = asString(values[key], field.default ? String(field.default) : '')

  if (field.type === 'hidden' || field.type === 'value_maps') return null

  if (key === 'device_id' || key === 'device_id1' || key === 'device_id2' || key === 'device_id3') {
    return (
      <DeviceSelect
        label={field.label || key}
        value={value}
        onChange={(v) => {
          const next = { ...values, [key]: v }
          if (key === 'device_id') next.metric_key = ''
          if (key === 'device_id1') next.metric_key1 = ''
          if (key === 'device_id2') next.metric_key2 = ''
          if (key === 'device_id3') next.metric_key3 = ''
          onChange(next)
        }}
      />
    )
  }

  if (
    key === 'metric_key' ||
    key === 'metric_key1' ||
    key === 'metric_key2' ||
    key === 'metric_key3' ||
    key === 'temp_key' ||
    key === 'hum_key'
  ) {
    const deviceKey =
      key === 'metric_key'
        ? 'device_id'
        : key === 'metric_key1'
          ? 'device_id1'
          : key === 'metric_key2'
            ? 'device_id2'
            : key === 'metric_key3'
              ? 'device_id3'
              : 'device_id'
    return (
      <MetricSelect
        label={field.label || key}
        deviceId={asString(values[deviceKey])}
        value={value}
        onChange={(v) => setKey(values, onChange, key, v)}
        onMeta={(m) => {
          const next = { ...values, [key]: String(m.key) }
          if (key === 'metric_key' && !values.unit) next.unit = asString(m.unit)
          if (key === 'metric_key1' && !values.unit1) next.unit1 = asString(m.unit)
          if (key === 'metric_key2' && !values.unit2) next.unit2 = asString(m.unit)
          if (key === 'metric_key3' && !values.unit3) next.unit3 = asString(m.unit)
          onChange(next)
        }}
      />
    )
  }

  if (field.type === 'icon_select') {
    return (
      <IconSelect
        label={field.label || key}
        value={value || field.default || 'bi-circle'}
        options={field.options || WIDGET_ICON_OPTIONS}
        onChange={(v) => setKey(values, onChange, key, v)}
      />
    )
  }

  if (field.type === 'widget_color') {
    return (
      <WidgetColorField
        label={field.label || key}
        value={value}
        onChange={(v) => setKey(values, onChange, key, v)}
      />
    )
  }

  if (field.type === 'select') {
    return (
      <Form.Item label={field.label || key}>
        <Select
          value={value || field.options?.find((o) => o.default)?.value}
          options={(field.options || []).map((o) => ({ value: o.value, label: o.label }))}
          onChange={(v) => setKey(values, onChange, key, v)}
          style={{ width: '100%' }}
        />
      </Form.Item>
    )
  }

  if (field.type === 'number') {
    return (
      <Form.Item label={field.label || key}>
        <InputNumber
          style={{ width: '100%' }}
          value={value === '' ? undefined : Number(value)}
          min={field.min}
          max={field.max}
          onChange={(v) => setKey(values, onChange, key, v === null || v === undefined ? '' : String(v))}
        />
      </Form.Item>
    )
  }

  return (
    <Form.Item label={field.label || key}>
      <Input value={value} onChange={(e) => setKey(values, onChange, key, e.target.value)} />
    </Form.Item>
  )
}

export function WidgetConfigFields({ widgetType, values, onChange, layouts = [] }: Props) {
  const { t } = useTranslation()
  const fields = useMemo(() => fieldsForWidgetType(widgetType), [widgetType])

  const skipKeys = useMemo(() => {
    const skip = new Set<string>()
    if (widgetType === 'map_view') {
      skip.add('background')
      skip.add('show_border')
    }
    if (widgetType === 'svg_widget') {
      skip.add('svg_file')
      skip.add('show_border')
    }
    return skip
  }, [widgetType])

  return (
    <Form layout="vertical" size="small">
      {fields
        .filter((f) => f.key && !skipKeys.has(f.key))
        .map((field) => (
          <FieldRenderer
            key={field.key}
            field={field}
            values={values}
            onChange={onChange}
          />
        ))}

      {widgetType === 'value_box' || widgetType === 'zone_overview' ? (
        <Form.Item label="Value Maps">
          <ValueMapsEditor
            items={asArray<ValueMap>(values.value_maps)}
            onChange={(items) => setKey(values, onChange, 'value_maps', items)}
          />
        </Form.Item>
      ) : null}

      {widgetType === 'gauge' || widgetType === 'level_bar' ? (
        <ColorRangesEditor
          items={asArray<ColorRange>(values.color_ranges)}
          onChange={(items) => setKey(values, onChange, 'color_ranges', items)}
        />
      ) : null}

      {['line_chart', 'line_chart_dynamic', 'bar_chart', 'table'].includes(widgetType) ? (
        <Form.Item label="Metrics / Series">
          <SeriesListEditor
            items={asArray<SeriesItem>(values.items)}
            withColor={widgetType !== 'table'}
            onChange={(items) => setKey(values, onChange, 'items', items)}
          />
        </Form.Item>
      ) : null}

      {widgetType === 'status_grid' ? (
        <>
          <Form.Item label="Columns">
            <InputNumber
              min={1}
              max={6}
              style={{ width: '100%' }}
              value={Number(values.columns ?? 3)}
              onChange={(v) => setKey(values, onChange, 'columns', String(v ?? 3))}
            />
          </Form.Item>
          <Form.Item label="Status Items">
            <SeriesListEditor
              items={asArray<SeriesItem>(values.items)}
              onChange={(items) => setKey(values, onChange, 'items', items)}
            />
          </Form.Item>
        </>
      ) : null}

      {widgetType === 'navigator' ? (
        <>
          <Form.Item label="Columns">
            <InputNumber
              min={1}
              max={6}
              style={{ width: '100%' }}
              value={Number(values.columns ?? 2)}
              onChange={(v) => setKey(values, onChange, 'columns', String(v ?? 2))}
            />
          </Form.Item>
          <Form.Item label="Dashboards">
            <Space direction="vertical" style={{ width: '100%' }}>
              {asArray<NavigatorItem>(values.items).map((item, index) => (
                <div
                  key={index}
                  style={{ border: '1px solid rgba(255,255,255,0.2)', borderRadius: 6, padding: 8 }}
                >
                  <Form.Item label="Dashboard">
                    <Select
                      style={{ width: '100%' }}
                      value={item.layout_id || undefined}
                      options={layouts.map((l) => ({ value: String(l.id), label: l.name }))}
                      onChange={(v) => {
                        const next = [...asArray<NavigatorItem>(values.items)]
                        const layout = layouts.find((l) => String(l.id) === v)
                        next[index] = {
                          ...item,
                          layout_id: v,
                          label: item.label || layout?.name || '',
                        }
                        setKey(values, onChange, 'items', next)
                      }}
                    />
                  </Form.Item>
                  <Form.Item label="Label">
                    <Input
                      value={item.label ?? ''}
                      onChange={(e) => {
                        const next = [...asArray<NavigatorItem>(values.items)]
                        next[index] = { ...item, label: e.target.value }
                        setKey(values, onChange, 'items', next)
                      }}
                    />
                  </Form.Item>
                  <IconSelect
                    label="Icon"
                    value={item.icon || 'bi-grid-1x2'}
                    options={NAVIGATOR_ICONS}
                    onChange={(v) => {
                      const next = [...asArray<NavigatorItem>(values.items)]
                      next[index] = { ...item, icon: v }
                      setKey(values, onChange, 'items', next)
                    }}
                  />
                  <WidgetColorField
                    label="Color"
                    value={item.color || '#0d6efd'}
                    onChange={(v) => {
                      const next = [...asArray<NavigatorItem>(values.items)]
                      next[index] = { ...item, color: v }
                      setKey(values, onChange, 'items', next)
                    }}
                  />
                  <Button
                    danger
                    size="small"
                    onClick={() =>
                      setKey(
                        values,
                        onChange,
                        'items',
                        asArray<NavigatorItem>(values.items).filter((_, i) => i !== index),
                      )
                    }
                  >
                    {t('remove')}
                  </Button>
                </div>
              ))}
              <Button
                type="dashed"
                onClick={() =>
                  setKey(values, onChange, 'items', [
                    ...asArray<NavigatorItem>(values.items),
                    { icon: 'bi-grid-1x2', color: '#0d6efd' },
                  ])
                }
              >
                + Add Dashboard
              </Button>
            </Space>
          </Form.Item>
        </>
      ) : null}

      {widgetType === 'heatmap' ? (
        <>
          <Form.Item label="Columns">
            <InputNumber
              min={1}
              max={12}
              style={{ width: '100%' }}
              value={Number(values.columns ?? 4)}
              onChange={(v) => setKey(values, onChange, 'columns', String(v ?? 4))}
            />
          </Form.Item>
          <Form.Item label="Warn Threshold">
            <Input
              value={asString(values.warn_threshold, '25')}
              onChange={(e) => setKey(values, onChange, 'warn_threshold', e.target.value)}
            />
          </Form.Item>
          <Form.Item label="Alarm Threshold">
            <Input
              value={asString(values.alarm_threshold, '30')}
              onChange={(e) => setKey(values, onChange, 'alarm_threshold', e.target.value)}
            />
          </Form.Item>
          <Form.Item label="Unit">
            <Input
              value={asString(values.unit, '°C')}
              onChange={(e) => setKey(values, onChange, 'unit', e.target.value)}
            />
          </Form.Item>
          <Form.Item label="Cells">
            <SeriesListEditor
              items={asArray<SeriesItem>(values.items)}
              onChange={(items) => setKey(values, onChange, 'items', items)}
            />
          </Form.Item>
        </>
      ) : null}

      {widgetType === 'zone_overview' ? (
        <>
          <IconSelect
            label="Card Icon"
            value={asString(values.icon, 'bi-building')}
            onChange={(v) => setKey(values, onChange, 'icon', v)}
          />
          <Form.Item label="Show Primary KPI (left)">
            <Select
              value={asString(values.show_primary, '1')}
              options={[
                { value: '1', label: 'Yes — show metric' },
                { value: '0', label: 'No — show alarm count' },
              ]}
              onChange={(v) => setKey(values, onChange, 'show_primary', v)}
              style={{ width: '100%' }}
            />
          </Form.Item>
          <Form.Item label="Label">
            <Input
              value={asString(values.primary_label)}
              onChange={(e) => setKey(values, onChange, 'primary_label', e.target.value)}
            />
          </Form.Item>
          <Form.Item label="Unit">
            <Input
              value={asString(values.primary_unit)}
              onChange={(e) => setKey(values, onChange, 'primary_unit', e.target.value)}
            />
          </Form.Item>
          <DeviceSelect
            label="Device"
            value={asString(values.primary_device_id)}
            onChange={(v) =>
              onChange({ ...values, primary_device_id: v, primary_metric_key: '' })
            }
          />
          <MetricSelect
            label="Metric"
            deviceId={asString(values.primary_device_id)}
            value={asString(values.primary_metric_key)}
            onChange={(v) => setKey(values, onChange, 'primary_metric_key', v)}
          />
          <Form.Item label="Metrics (right side)">
            <SeriesListEditor
              items={asArray<SeriesItem>(values.items)}
              withIcon
              onChange={(items) => setKey(values, onChange, 'items', items)}
            />
          </Form.Item>
          <Form.Item label="Alarm Monitor Devices">
            <Space direction="vertical" style={{ width: '100%' }}>
              {asArray<{ device_id?: string }>(values.alarm_devices).map((item, index) => (
                <Space key={index}>
                  <div style={{ minWidth: 220 }}>
                    <DeviceSelect
                      label="Device"
                      value={asString(item.device_id)}
                      onChange={(v) => {
                        const next = [...asArray<{ device_id?: string }>(values.alarm_devices)]
                        next[index] = { device_id: v }
                        setKey(values, onChange, 'alarm_devices', next)
                      }}
                    />
                  </div>
                  <Button
                    danger
                    onClick={() =>
                      setKey(
                        values,
                        onChange,
                        'alarm_devices',
                        asArray(values.alarm_devices).filter((_, i) => i !== index),
                      )
                    }
                  >
                    ×
                  </Button>
                </Space>
              ))}
              <Button
                type="dashed"
                onClick={() =>
                  setKey(values, onChange, 'alarm_devices', [
                    ...asArray(values.alarm_devices),
                    {},
                  ])
                }
              >
                + Add Alarm Device
              </Button>
            </Space>
          </Form.Item>
        </>
      ) : null}

      {widgetType === 'map_view' ? (
        <>
          <Form.Item label="Map Image">
            <Upload
              accept=".jpg,.jpeg,.png,.bmp,.tif,.tiff,image/*"
              maxCount={1}
              showUploadList={false}
              customRequest={async ({ file, onSuccess, onError }) => {
                try {
                  const form = new FormData()
                  form.append('image', file as File)
                  const res = await apiFormRequest<{ filename: string; path: string }>(
                    '/uploads/map-image',
                    form,
                  )
                  onChange({ ...values, background: res.filename })
                  onSuccess?.(res)
                } catch (err) {
                  onError?.(err as Error)
                }
              }}
            >
              <Button icon={<i className="bi bi-upload" />}>Upload Map Image</Button>
            </Upload>
            {values.background ? (
              <img
                src={mapImageUrl(asString(values.background))}
                alt="map"
                style={{ display: 'block', maxWidth: '100%', marginTop: 8, borderRadius: 4 }}
              />
            ) : null}
          </Form.Item>
          <Form.Item label="Show Border">
            <Select
              value={asString(values.show_border, '1')}
              options={[
                { value: '1', label: 'Show' },
                { value: '0', label: 'Hide' },
              ]}
              onChange={(v) => setKey(values, onChange, 'show_border', v)}
              style={{ width: '100%' }}
            />
          </Form.Item>
          <Form.Item label="Map Markers">
            <Space direction="vertical" style={{ width: '100%' }}>
              {asArray<MapItem>(values.items).map((item, index) => (
                <div
                  key={index}
                  style={{ border: '1px solid rgba(255,255,255,0.2)', borderRadius: 6, padding: 8 }}
                >
                  <Space wrap>
                    <InputNumber
                      placeholder="X"
                      value={item.x}
                      onChange={(v) => {
                        const next = [...asArray<MapItem>(values.items)]
                        next[index] = { ...item, x: Number(v ?? 0) }
                        setKey(values, onChange, 'items', next)
                      }}
                    />
                    <InputNumber
                      placeholder="Y"
                      value={item.y}
                      onChange={(v) => {
                        const next = [...asArray<MapItem>(values.items)]
                        next[index] = { ...item, y: Number(v ?? 0) }
                        setKey(values, onChange, 'items', next)
                      }}
                    />
                    <Input
                      placeholder="Label"
                      value={item.label ?? ''}
                      onChange={(e) => {
                        const next = [...asArray<MapItem>(values.items)]
                        next[index] = { ...item, label: e.target.value }
                        setKey(values, onChange, 'items', next)
                      }}
                    />
                    <Select
                      style={{ width: 120 }}
                      value={item.show || 'label'}
                      options={[
                        { value: 'label', label: 'Label' },
                        { value: 'point', label: 'Point' },
                      ]}
                      onChange={(v) => {
                        const next = [...asArray<MapItem>(values.items)]
                        next[index] = { ...item, show: v }
                        setKey(values, onChange, 'items', next)
                      }}
                    />
                    <Button
                      danger
                      size="small"
                      onClick={() =>
                        setKey(
                          values,
                          onChange,
                          'items',
                          asArray<MapItem>(values.items).filter((_, i) => i !== index),
                        )
                      }
                    >
                      ×
                    </Button>
                  </Space>
                  <DeviceSelect
                    label="Device"
                    value={asString(item.device_id)}
                    onChange={(v) => {
                      const next = [...asArray<MapItem>(values.items)]
                      next[index] = { ...item, device_id: v, metric_key: '' }
                      setKey(values, onChange, 'items', next)
                    }}
                  />
                  <MetricSelect
                    label="Metric"
                    deviceId={asString(item.device_id)}
                    value={asString(item.metric_key)}
                    onChange={(v) => {
                      const next = [...asArray<MapItem>(values.items)]
                      next[index] = { ...item, metric_key: v }
                      setKey(values, onChange, 'items', next)
                    }}
                  />
                  <Form.Item label="Unit">
                    <Input
                      value={item.unit ?? ''}
                      onChange={(e) => {
                        const next = [...asArray<MapItem>(values.items)]
                        next[index] = { ...item, unit: e.target.value }
                        setKey(values, onChange, 'items', next)
                      }}
                    />
                  </Form.Item>
                  <Form.Item label="URL">
                    <Input
                      value={item.url ?? ''}
                      onChange={(e) => {
                        const next = [...asArray<MapItem>(values.items)]
                        next[index] = { ...item, url: e.target.value }
                        setKey(values, onChange, 'items', next)
                      }}
                    />
                  </Form.Item>
                </div>
              ))}
              <Button
                type="dashed"
                onClick={() =>
                  setKey(values, onChange, 'items', [
                    ...asArray<MapItem>(values.items),
                    { x: 50, y: 50, show: 'label' },
                  ])
                }
              >
                + Add Marker
              </Button>
            </Space>
          </Form.Item>
        </>
      ) : null}

      {widgetType === 'svg_widget' ? (
        <>
          <Form.Item label="SVG File">
            <Upload
              accept=".svg,image/svg+xml"
              maxCount={1}
              showUploadList={false}
              customRequest={async ({ file, onSuccess, onError }) => {
                try {
                  const form = new FormData()
                  form.append('svg', file as File)
                  const res = await apiFormRequest<{ filename: string; path: string }>(
                    '/uploads/svg-widget',
                    form,
                  )
                  onChange({ ...values, svg_file: res.filename })
                  onSuccess?.(res)
                } catch (err) {
                  onError?.(err as Error)
                }
              }}
            >
              <Button icon={<i className="bi bi-upload" />}>Upload SVG</Button>
            </Upload>
            {values.svg_file ? (
              <div style={{ marginTop: 8 }}>
                <a href={svgWidgetUrl(asString(values.svg_file))} target="_blank" rel="noreferrer">
                  {asString(values.svg_file)}
                </a>
              </div>
            ) : null}
          </Form.Item>
          <Form.Item label="Show Border">
            <Select
              value={asString(values.show_border, '1')}
              options={[
                { value: '1', label: 'Show' },
                { value: '0', label: 'Hide' },
              ]}
              onChange={(v) => setKey(values, onChange, 'show_border', v)}
              style={{ width: '100%' }}
            />
          </Form.Item>
          <Form.Item label="Bindings">
            <Space direction="vertical" style={{ width: '100%' }}>
              {asArray<SvgBinding>(values.bindings).map((item, index) => (
                <div
                  key={index}
                  style={{ border: '1px solid rgba(255,255,255,0.2)', borderRadius: 6, padding: 8 }}
                >
                  <Space wrap>
                    <Input
                      placeholder="Element ID"
                      value={item.id ?? ''}
                      onChange={(e) => {
                        const next = [...asArray<SvgBinding>(values.bindings)]
                        next[index] = { ...item, id: e.target.value }
                        setKey(values, onChange, 'bindings', next)
                      }}
                      style={{ width: 140 }}
                    />
                    <Select
                      style={{ width: 120 }}
                      value={item.mode || 'fill'}
                      options={[
                        'fill',
                        'stroke',
                        'show',
                        'text',
                        'blink',
                        'spin',
                        'opacity',
                        'class',
                      ].map((v) => ({ value: v, label: v }))}
                      onChange={(v) => {
                        const next = [...asArray<SvgBinding>(values.bindings)]
                        next[index] = { ...item, mode: v }
                        setKey(values, onChange, 'bindings', next)
                      }}
                    />
                    <Select
                      style={{ width: 100 }}
                      value={item.op || 'eq'}
                      options={[
                        { value: 'eq', label: '=' },
                        { value: 'neq', label: '≠' },
                        { value: 'gt', label: '>' },
                        { value: 'gte', label: '≥' },
                        { value: 'lt', label: '<' },
                        { value: 'lte', label: '≤' },
                        { value: 'between', label: 'between' },
                        { value: 'in', label: 'in' },
                        { value: 'always', label: 'always' },
                      ]}
                      onChange={(v) => {
                        const next = [...asArray<SvgBinding>(values.bindings)]
                        next[index] = { ...item, op: v }
                        setKey(values, onChange, 'bindings', next)
                      }}
                    />
                    <Input
                      placeholder="Match"
                      value={item.match_value ?? ''}
                      onChange={(e) => {
                        const next = [...asArray<SvgBinding>(values.bindings)]
                        next[index] = { ...item, match_value: e.target.value }
                        setKey(values, onChange, 'bindings', next)
                      }}
                      style={{ width: 80 }}
                    />
                    <Button
                      danger
                      size="small"
                      onClick={() =>
                        setKey(
                          values,
                          onChange,
                          'bindings',
                          asArray<SvgBinding>(values.bindings).filter((_, i) => i !== index),
                        )
                      }
                    >
                      ×
                    </Button>
                  </Space>
                  <DeviceSelect
                    label="Device"
                    value={asString(item.device_id)}
                    onChange={(v) => {
                      const next = [...asArray<SvgBinding>(values.bindings)]
                      next[index] = { ...item, device_id: v, metric_key: '' }
                      setKey(values, onChange, 'bindings', next)
                    }}
                  />
                  <MetricSelect
                    label="Metric"
                    deviceId={asString(item.device_id)}
                    value={asString(item.metric_key)}
                    onChange={(v) => {
                      const next = [...asArray<SvgBinding>(values.bindings)]
                      next[index] = { ...item, metric_key: v }
                      setKey(values, onChange, 'bindings', next)
                    }}
                  />
                </div>
              ))}
              <Button
                type="dashed"
                onClick={() =>
                  setKey(values, onChange, 'bindings', [
                    ...asArray<SvgBinding>(values.bindings),
                    { mode: 'fill', op: 'eq', match_value: '1' },
                  ])
                }
              >
                + Add Binding
              </Button>
            </Space>
          </Form.Item>
        </>
      ) : null}
    </Form>
  )
}
