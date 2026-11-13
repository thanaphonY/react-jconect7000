import {
  Button,
  Card,
  Col,
  ColorPicker,
  Form,
  Input,
  InputNumber,
  Modal,
  Row,
  Select,
  Space,
  Typography,
} from 'antd'
import { GridStack } from 'gridstack'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { DASHBOARD_WIDGET_TYPES, type TemplateDashboardWidget } from './types'
import 'gridstack/dist/gridstack.min.css'

type Props = {
  value: TemplateDashboardWidget[]
  onChange: (next: TemplateDashboardWidget[]) => void
}

function emptyWidget(): TemplateDashboardWidget {
  return {
    title: 'New Widget',
    metric_key: '',
    unit: '',
    type: 'value_box',
    color: '#007bff',
    gradient_to_color: '#4dd0e1',
    x: 0,
    y: 0,
    w: 4,
    h: 2,
    chart_height: 200,
    show_legend: true,
  }
}

function isChartType(type: string) {
  return !['value_box', 'gauge', 'gradient_radial_bar'].includes(type)
}

function colorToHex(value: unknown, fallback: string): string {
  if (typeof value === 'string' && value) return value
  if (value && typeof value === 'object' && 'toHexString' in value) {
    const fn = (value as { toHexString?: () => string }).toHexString
    if (typeof fn === 'function') return fn.call(value)
  }
  return fallback
}

function WidgetPreviewContent({
  title,
  metricKey,
  unit,
  type,
  color,
}: {
  title: string
  metricKey: string
  unit: string
  type: string
  color: string
}) {
  if (type === 'value_box' || type === 'gauge' || type === 'gradient_radial_bar') {
    return (
      <div style={{ color, padding: 8 }}>
        <div style={{ fontWeight: 600 }}>{title || 'Preview'}</div>
        <div style={{ fontSize: type === 'value_box' ? '2rem' : '1.5rem', fontWeight: 600 }}>
          {type === 'value_box' ? `25.5${unit}` : `75${unit}`}
        </div>
        <div style={{ fontSize: 12, opacity: 0.7 }}>{metricKey || 'value'}</div>
      </div>
    )
  }
  return (
    <div style={{ padding: 8 }}>
      <div style={{ fontWeight: 600, color }}>{title || 'Preview'}</div>
      <div style={{ fontSize: 13, marginTop: 8 }}>
        {(type || 'chart').replaceAll('_', ' ')} preview
      </div>
      <div style={{ fontSize: 12, opacity: 0.7, marginTop: 4 }}>{metricKey || 'value'}</div>
    </div>
  )
}

export function TemplateDashboardBuilder({ value, onChange }: Props) {
  const { t } = useTranslation()
  const builderRef = useRef<HTMLDivElement>(null)
  const previewRef = useRef<HTMLDivElement>(null)
  const gridRef = useRef<GridStack | null>(null)
  const previewGridRef = useRef<GridStack | null>(null)
  const layoutRef = useRef(value)
  const [configIndex, setConfigIndex] = useState<number | null>(null)
  const [form] = Form.useForm()

  useEffect(() => {
    layoutRef.current = value
  }, [value])

  useEffect(() => {
    if (!builderRef.current) return
    const grid = GridStack.init(
      { column: 12, cellHeight: 100, float: true, margin: 6, animate: false },
      builderRef.current,
    )
    gridRef.current = grid

    const onChangeLayout = () => {
      const nodes = grid.getGridItems()
      const next = layoutRef.current.map((widget, index) => {
        const el = nodes.find((n) => Number(n.getAttribute('gs-id')) === index)
        if (!el) return widget
        const node = el.gridstackNode
        if (!node) return widget
        return {
          ...widget,
          x: node.x ?? widget.x ?? 0,
          y: node.y ?? widget.y ?? 0,
          w: node.w ?? widget.w ?? 4,
          h: node.h ?? widget.h ?? 2,
        }
      })
      layoutRef.current = next
      onChange(next)
    }

    grid.on('change', onChangeLayout)
    return () => {
      grid.off('change')
      grid.destroy(false)
      gridRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (!previewRef.current) return
    const grid = GridStack.init(
      {
        column: 12,
        cellHeight: 100,
        float: true,
        margin: 6,
        animate: false,
        disableResize: true,
        disableDrag: true,
      },
      previewRef.current,
    )
    previewGridRef.current = grid
    return () => {
      grid.destroy(false)
      previewGridRef.current = null
    }
  }, [])

  useEffect(() => {
    const grid = gridRef.current
    if (!grid || !builderRef.current) return
    grid.batchUpdate()
    grid.removeAll(false)
    value.forEach((widget, index) => {
      const content = document.createElement('div')
      content.className = 'grid-stack-item-content'
      content.style.cssText =
        'background:#f9f9f9;border:1px dashed #ccc;padding:8px;overflow:hidden;display:flex;flex-direction:column;gap:4px'
      content.innerHTML = `
        <div style="font-weight:600;font-size:13px">${escapeHtml(widget.title || 'Widget')}</div>
        <div style="font-size:12px;color:#666">${escapeHtml(widget.type || 'value_box')} · ${escapeHtml(widget.metric_key || '-')}</div>
        <div style="margin-top:auto;display:flex;gap:4px">
          <button type="button" data-cfg="${index}" class="ant-btn ant-btn-sm">${escapeHtml(t('configure_widget'))}</button>
          <button type="button" data-rm="${index}" class="ant-btn ant-btn-sm ant-btn-dangerous">${escapeHtml(t('remove'))}</button>
        </div>
      `
      grid.addWidget({
        x: widget.x ?? 0,
        y: widget.y ?? 0,
        w: widget.w ?? 4,
        h: widget.h ?? 2,
        id: String(index),
        content: content.outerHTML,
      })
      const item = builderRef.current?.querySelector(`.grid-stack-item[gs-id="${index}"]`)
      item?.querySelector('[data-cfg]')?.addEventListener('click', (e) => {
        e.preventDefault()
        e.stopPropagation()
        openConfig(index)
      })
      item?.querySelector('[data-rm]')?.addEventListener('click', (e) => {
        e.preventDefault()
        e.stopPropagation()
        removeWidget(index)
      })
    })
    grid.batchUpdate(false)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value])

  useEffect(() => {
    const grid = previewGridRef.current
    if (!grid || !previewRef.current) return
    grid.batchUpdate()
    grid.removeAll(false)
    value.forEach((widget, index) => {
      const color = widget.color || '#007bff'
      const title = escapeHtml(widget.title || 'Preview')
      const metric = escapeHtml(widget.metric_key || 'value')
      const unit = escapeHtml(widget.unit || '')
      const type = widget.type || 'value_box'
      let body = ''
      if (type === 'value_box') {
        body = `<div style="color:${escapeHtml(color)};padding:8px"><div style="font-weight:600">${title}</div><div style="font-size:2rem;font-weight:600">25.5${unit}</div><div style="font-size:12px;opacity:.7">${metric}</div></div>`
      } else if (type === 'gauge' || type === 'gradient_radial_bar') {
        body = `<div style="color:${escapeHtml(color)};padding:8px"><div style="font-weight:600">${title}</div><div style="font-size:1.5rem;font-weight:600">75${unit}</div><div style="font-size:12px;opacity:.7">${metric}</div></div>`
      } else {
        body = `<div style="padding:8px"><div style="font-weight:600;color:${escapeHtml(color)}">${title}</div><div style="font-size:13px;margin-top:8px">${escapeHtml(type.replaceAll('_', ' '))} preview</div><div style="font-size:12px;opacity:.7;margin-top:4px">${metric}</div></div>`
      }
      const content = document.createElement('div')
      content.className = 'grid-stack-item-content preview'
      content.style.cssText =
        'background:#fff;border:1px solid #e8e8e8;border-radius:4px;overflow:hidden;height:100%'
      content.innerHTML = body
      grid.addWidget({
        x: widget.x ?? 0,
        y: widget.y ?? 0,
        w: widget.w ?? 4,
        h: widget.h ?? 2,
        id: `preview-${index}`,
        content: content.outerHTML,
        noResize: true,
        noMove: true,
      })
    })
    grid.batchUpdate(false)
  }, [value])

  function openConfig(index: number) {
    const widget = layoutRef.current[index]
    if (!widget) return
    setConfigIndex(index)
    form.setFieldsValue({
      title: widget.title ?? '',
      metric_key: widget.metric_key ?? '',
      unit: widget.unit ?? '',
      type: widget.type ?? 'value_box',
      color: widget.color ?? '#007bff',
      gradient_to_color: widget.gradient_to_color ?? '#4dd0e1',
      chart_height: widget.chart_height ?? 200,
      show_legend: widget.show_legend !== false,
      min: widget.min ?? undefined,
      max: widget.max ?? undefined,
    })
  }

  function removeWidget(index: number) {
    const next = layoutRef.current.filter((_, i) => i !== index)
    layoutRef.current = next
    onChange(next)
  }

  function addWidget() {
    const next = [...layoutRef.current, emptyWidget()]
    layoutRef.current = next
    onChange(next)
  }

  function saveConfig() {
    if (configIndex == null) return
    const values = form.getFieldsValue()
    const color = colorToHex(values.color, '#007bff')
    const gradient = colorToHex(values.gradient_to_color, '#4dd0e1')
    const next = layoutRef.current.map((widget, i) =>
      i === configIndex
        ? {
            ...widget,
            title: values.title,
            metric_key: values.metric_key,
            unit: values.unit,
            type: values.type,
            color,
            gradient_to_color: gradient,
            chart_height: values.chart_height,
            show_legend: values.show_legend,
            min: values.min ?? null,
            max: values.max ?? null,
          }
        : widget,
    )
    layoutRef.current = next
    onChange(next)
    setConfigIndex(null)
  }

  const titleWatch = Form.useWatch('title', form)
  const metricWatch = Form.useWatch('metric_key', form)
  const unitWatch = Form.useWatch('unit', form)
  const typeWatch = Form.useWatch('type', form)
  const colorWatch = Form.useWatch('color', form)
  const modalPreview = useMemo(
    () => ({
      title: String(titleWatch ?? 'Preview'),
      metricKey: String(metricWatch ?? 'value'),
      unit: String(unitWatch ?? ''),
      type: String(typeWatch ?? 'value_box'),
      color: colorToHex(colorWatch, '#007bff'),
    }),
    [titleWatch, metricWatch, unitWatch, typeWatch, colorWatch],
  )

  const widgetType = typeWatch || 'value_box'

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Card
        title={t('dashboard_builder')}
        extra={
          <Button type="primary" onClick={addWidget}>
            {t('add_widget')}
          </Button>
        }
      >
        <div ref={builderRef} className="grid-stack" style={{ minHeight: 400 }} />
      </Card>

      <Card title={t('live_preview')}>
        <div ref={previewRef} className="grid-stack" id="preview" style={{ minHeight: 400 }} />
        {!value.length ? (
          <Typography.Text type="secondary">Select widget type to see preview</Typography.Text>
        ) : null}
      </Card>

      <Modal
        title={t('configure_widget')}
        open={configIndex != null}
        onCancel={() => setConfigIndex(null)}
        onOk={saveConfig}
        width={720}
        destroyOnClose
      >
        <Form form={form} layout="vertical">
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="title" label={t('title')}>
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="metric_key" label={t('metric_key')}>
                <Input />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={12}>
            <Col span={8}>
              <Form.Item name="type" label={t('widget_type')}>
                <Select
                  options={DASHBOARD_WIDGET_TYPES.map((opt) => ({
                    value: opt.value,
                    label: 'label' in opt ? opt.label : t(opt.labelKey),
                  }))}
                />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="unit" label={t('unit')}>
                <Input placeholder="°C, %, W, etc." />
              </Form.Item>
            </Col>
            <Col span={4}>
              <Form.Item name="color" label={t('color')} getValueFromEvent={(c) => c.toHexString()}>
                <ColorPicker />
              </Form.Item>
            </Col>
            <Col span={4}>
              <Form.Item
                name="gradient_to_color"
                label="Gradient To Color"
                getValueFromEvent={(c) => c.toHexString()}
              >
                <ColorPicker />
              </Form.Item>
            </Col>
          </Row>
          {isChartType(widgetType) ? (
            <Row gutter={12}>
              <Col span={6}>
                <Form.Item name="chart_height" label="Height (px)">
                  <InputNumber style={{ width: '100%' }} />
                </Form.Item>
              </Col>
              <Col span={6}>
                <Form.Item name="min" label="Min">
                  <InputNumber style={{ width: '100%' }} />
                </Form.Item>
              </Col>
              <Col span={6}>
                <Form.Item name="max" label="Max">
                  <InputNumber style={{ width: '100%' }} />
                </Form.Item>
              </Col>
              <Col span={6}>
                <Form.Item name="show_legend" label="Show Legend">
                  <Select
                    options={[
                      { value: true, label: t('yes') },
                      { value: false, label: t('no') },
                    ]}
                  />
                </Form.Item>
              </Col>
            </Row>
          ) : null}

          <div style={{ marginTop: 8 }}>
            <Typography.Text strong>{t('live_preview')}</Typography.Text>
            <div
              style={{
                marginTop: 8,
                border: '1px solid #d9d9d9',
                borderRadius: 6,
                padding: 12,
                minHeight: 100,
                background: '#fafafa',
              }}
            >
              <WidgetPreviewContent
                title={modalPreview.title}
                metricKey={modalPreview.metricKey}
                unit={modalPreview.unit}
                type={modalPreview.type}
                color={modalPreview.color}
              />
            </div>
          </div>
        </Form>
      </Modal>
    </Space>
  )
}

function escapeHtml(value: string) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}
