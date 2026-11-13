import { useMutation, useQuery } from '@tanstack/react-query'
import {
  App,
  Button,
  Card,
  Checkbox,
  Col,
  Form,
  Input,
  Row,
  Select,
  Space,
  Breadcrumb,
  Typography,
} from 'antd'
import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, apiRequest } from '../api/client'
import {
  fetchDevice,
  fetchGatewayDevices,
  fetchMetricSources,
  fetchNotificationChannels,
  fetchTemplatePreview,
  fetchTemplates,
  saveDevice,
  uploadDeviceImageFile,
} from '../features/devices/api'
import { CommunicationFields } from '../features/devices/CommunicationFields'
import {
  CustomFieldsEditor,
  customFieldsToObject,
  objectToCustomFields,
  type CustomFieldRow,
} from '../features/devices/CustomFieldsEditor'
import { DeviceImageThumb } from '../features/devices/DeviceImageThumb'
import { MetricBuilder } from '../features/devices/MetricBuilder'
import { PemonMetricsBuilder } from '../features/devices/PemonMetricsBuilder'
import type {
  DeviceRole,
  Metric,
  SnapshotTrigger,
  TemplatePreview,
} from '../features/devices/types'
import './device-wizard.css'
import { templateUploadUrl } from '../features/devices/urls'

type InfoValues = {
  name: string
  serialNumber?: string
  location?: string
  siteNodeId?: number | null
  description?: string
  isActive: boolean
  deviceRole: DeviceRole
  gatewayDeviceId?: number | null
}

export function DeviceFormPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const params = useParams()
  const deviceId = params.id ? Number(params.id) : null
  const isEdit = deviceId !== null
  const [step, setStep] = useState(0)
  const [templateFilters, setTemplateFilters] = useState({
    name: '', category: '', protocol: '', brand: '', model: '',
  })
  function setTemplateFilter(key: keyof typeof templateFilters, value: string) {
    setTemplateFilters(current => ({ ...current, [key]: value }))
  }
  const [templateId, setTemplateId] = useState<number | null>(null)
  const [preview, setPreview] = useState<TemplatePreview | null>(null)
  const [commConfig, setCommConfig] = useState<Record<string, unknown>>({})
  const [metrics, setMetrics] = useState<Metric[]>([])
  const [pemonGenerated, setPemonGenerated] = useState(false)
  const [snapshotTriggers, setSnapshotTriggers] = useState<SnapshotTrigger[]>([])
  const [customRows, setCustomRows] = useState<CustomFieldRow[]>([])
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const [infoForm] = Form.useForm<InfoValues>()

  const templates = useQuery({
    queryKey: ['device-templates'],
    queryFn: fetchTemplates,
    enabled: !isEdit,
  })

  const device = useQuery({
    queryKey: ['device', deviceId],
    enabled: isEdit,
    queryFn: () => fetchDevice(deviceId!),
  })

  const sites = useQuery({
    queryKey: ['site-nodes'],
    queryFn: () => apiRequest<Array<{ id: number; parentId: number | null; name: string; nodeType: string }>>('/site-nodes'),
  })
  const siteOptions = useMemo(() => {
    const nodes = sites.data ?? []
    const byId = new Map(nodes.map(node => [node.id, node]))
    return nodes.map(node => {
      const path = [node.name]
      const visited = new Set([node.id])
      let parent = node.parentId
      while (parent != null && !visited.has(parent)) {
        const ancestor = byId.get(parent)
        if (!ancestor) break
        visited.add(parent)
        path.unshift(ancestor.name)
        parent = ancestor.parentId
      }
      return { value: node.id, label: path.join(' › '), nodeType: node.nodeType }
    }).sort((a, b) => a.label.localeCompare(b.label, 'th'))
  }, [sites.data])

  const gateways = useQuery({
    queryKey: ['device-gateways'],
    queryFn: fetchGatewayDevices,
  })

  const metricSources = useQuery({
    queryKey: ['device-metric-sources'],
    queryFn: fetchMetricSources,
  })

  const channels = useQuery({
    queryKey: ['notification-channels'],
    queryFn: fetchNotificationChannels,
  })

  const deviceRole = Form.useWatch('deviceRole', infoForm) || 'native_device'
  const gatewayDeviceId = Form.useWatch('gatewayDeviceId', infoForm)
  const protocol = preview?.protocol || device.data?.protocol || 'modbus_tcp'

  useEffect(() => {
    if (!device.data || !isEdit) return
    const cfg = (device.data.config || {}) as Record<string, unknown>
    const role = (device.data.deviceRole ||
      cfg.role ||
      'native_device') as DeviceRole
    const via =
      device.data.gatewayDeviceId ??
      (typeof cfg.via_gateway === 'number' ? cfg.via_gateway : null)
    setTemplateId(device.data.deviceTemplateId)
    setCommConfig(
      (device.data.communicationConfig as Record<string, unknown>) || {},
    )
    setMetrics(Array.isArray(device.data.metrics) ? device.data.metrics : [])
    const snaps =
      device.data.snapshotTriggers ||
      (Array.isArray(cfg.snapshot_triggers) ? (cfg.snapshot_triggers as SnapshotTrigger[]) : [])
    setSnapshotTriggers(snaps)
    const custom =
      (cfg.custom_fields as Record<string, unknown> | undefined) ||
      device.data.customFields ||
      {}
    setCustomRows(objectToCustomFields(custom))
    infoForm.setFieldsValue({
      name: device.data.name,
      serialNumber: device.data.serialNumber,
      location: device.data.location,
      siteNodeId: device.data.siteNodeId,
      description: device.data.description,
      isActive: device.data.isActive,
      deviceRole: role,
      gatewayDeviceId: via,
    })
    void fetchTemplatePreview(device.data.deviceTemplateId).then((p) => setPreview(p))
  }, [device.data, isEdit, infoForm])

  async function selectTemplate(id: number) {
    setTemplateId(id)
    setPemonGenerated(false)
    const p = await fetchTemplatePreview(id)
    setPreview(p)
    setCommConfig({ ...(p.communicationConfig || {}) })
    setMetrics(Array.isArray(p.metrics) ? structuredClone(p.metrics) : [])
    const info = p.deviceInformation || {}
    const suggested = String(info.role || '').toLowerCase()
    let role: DeviceRole = 'native_device'
    if (String(p.protocol || '').toLowerCase() === 'gateway_endpoint') role = 'endpoint'
    else if (suggested === 'gateway') role = 'gateway'
    infoForm.setFieldsValue({
      deviceRole: role,
      isActive: true,
    })
    const reserved = new Set([
      'Brand',
      'Model',
      'role',
      'domain',
      'pue_role',
      'Device Type',
      'dcim_category',
      'dcim_sub_category',
    ])
    const rows = Object.entries(info)
      .filter(([k]) => p.protocol === 'pemon' ? !['Brand', 'Model', 'role'].includes(k) : !reserved.has(k))
      .map(([key, value]) => ({ key, value: value == null ? '' : String(value) }))
    setCustomRows(rows)
  }

  const brand = String(
    preview?.brand ||
      preview?.deviceInformation?.Brand ||
      device.data?.brand ||
      '',
  )
  const model = String(
    preview?.model ||
      preview?.deviceInformation?.Model ||
      device.data?.model ||
      '',
  )

  const gatewayMetricKeys = useMemo(() => {
    if (!gatewayDeviceId) return []
    const src = metricSources.data?.find((d) => d.id === Number(gatewayDeviceId))
    return (src?.metrics || []).map((m) => m.key).filter(Boolean)
  }, [gatewayDeviceId, metricSources.data])

  const steps = isEdit
    ? [
        { title: 'Information' },
        { title: t('communication') },
        { title: t('metrics') },
        { title: t('confirm') },
      ]
    : [
        { title: 'Select Template' },
        { title: 'Information' },
        { title: t('communication') },
        { title: t('metrics') },
        { title: t('confirm') },
      ]

  const infoStepIndex = isEdit ? 0 : 1
  const commStepIndex = isEdit ? 1 : 2
  const metricsStepIndex = isEdit ? 2 : 3
  const confirmStepIndex = isEdit ? 3 : 4

  const save = useMutation({
    mutationFn: async () => {
      const info = await infoForm.validateFields()
      if (!templateId) throw new Error(t('plase_select_device_template'))
      if (info.deviceRole === 'endpoint' && !info.gatewayDeviceId) {
        throw new Error('Parent Gateway is required for Gateway Endpoint')
      }
      let communicationConfig = { ...commConfig }
      if (info.deviceRole === 'endpoint') {
        communicationConfig = {
          protocol: 'gateway_endpoint',
          source: {
            gateway_device_id: Number(info.gatewayDeviceId),
          },
        }
      }
      let imageName: string | null | undefined
      if (imageFile) {
        imageName = await uploadDeviceImageFile(imageFile)
      }
      const body = {
        name: info.name,
        deviceTemplateId: templateId,
        serialNumber: info.serialNumber ?? '',
        location: info.location ?? '',
        ...(sites.isSuccess && info.siteNodeId !== undefined ? { siteNodeId: info.siteNodeId } : {}),
        description: info.description ?? '',
        isActive: info.isActive,
        deviceRole: info.deviceRole,
        gatewayDeviceId: info.deviceRole === 'endpoint' ? Number(info.gatewayDeviceId) : null,
        communicationConfig,
        metrics,
        customFields: customFieldsToObject(customRows),
        snapshotTriggers,
        ...(imageName ? { image: imageName } : {}),
      }
      return saveDevice(deviceId, body)
    },
    onSuccess: (saved) => {
      message.success(t('save'))
      navigate(`/device/view/${saved.id}`, { replace: true })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError || error instanceof Error ? error.message : t('save'))
    },
  })

  async function goNext() {
    if (!isEdit && step === 0) {
      if (!templateId) {
        message.error(t('plase_select_device_template'))
        return
      }
      setStep(1)
      return
    }
    if (step === infoStepIndex) {
      try {
        await infoForm.validateFields()
        setStep(commStepIndex)
      } catch {
        /* form errors */
      }
      return
    }
    if (step === commStepIndex) {
      setStep(metricsStepIndex)
      return
    }
    if (step === metricsStepIndex) {
      setStep(confirmStepIndex)
    }
  }

  function goBack() {
    setStep((s) => Math.max(0, s - 1))
  }

  const availableTemplates = (templates.data ?? []).filter(template => template.isActive !== false)
  const matches = (value: unknown, query: string) =>
    String(value ?? '').toLowerCase().includes(query.trim().toLowerCase())
  const visibleTemplates = availableTemplates.filter(template =>
    matches(template.name, templateFilters.name) &&
    matches(template.deviceInformation?.Brand ?? template.brand, templateFilters.brand) &&
    matches(template.deviceInformation?.Model ?? template.model, templateFilters.model) &&
    (!templateFilters.category || template.categoryName === templateFilters.category) &&
    (!templateFilters.protocol || template.protocol === templateFilters.protocol),
  )

  const currentImage =
    imagePreview ||
    (device.data
      ? undefined
      : templateId
        ? templateUploadUrl(preview?.image || preview?.imageUrl || null) || undefined
        : undefined)

  return (
    <div className="device-wizard">
      <Typography.Title level={3} style={{ margin: 0 }}>
        {isEdit ? t('edit_device_page') : t('add_device')}
      </Typography.Title>
      <Breadcrumb items={[{ title: <Link to="/home">Home</Link> }, { title: <Link to="/device">Devices</Link> }, { title: isEdit ? 'Edit' : 'Add' }]} />
      <Card className="device-wizard-card" title={
        <nav className="device-wizard-steps" aria-label="Device setup steps">
          {steps.map(({title}, index) => (
            <Button key={index} type={index === step ? 'primary' : 'link'}
              aria-current={index === step ? 'step' : undefined}
              disabled={index > step} onClick={() => setStep(index)}>
              {index + 1}. {title}
            </Button>
          ))}
        </nav>
      }>

      {!isEdit && step === 0 && (
        <>
          <div className="device-template-filters">
            <Input aria-label="Search by name" placeholder="Search by name..." allowClear
              value={templateFilters.name} onChange={event => setTemplateFilter('name', event.target.value)} />
            <Select aria-label="Category" placeholder="All Categories" allowClear
              value={templateFilters.category || undefined}
              options={[...new Set(availableTemplates.flatMap(template => template.categoryName ? [template.categoryName] : []))].sort().map(value => ({ value }))}
              onChange={value => setTemplateFilter('category', value ?? '')} />
            <Select aria-label="Protocol" placeholder="All Protocols" allowClear
              value={templateFilters.protocol || undefined}
              options={[...new Set(availableTemplates.map(template => template.protocol))].sort().map(value => ({ value, label: value.toUpperCase() }))}
              onChange={value => setTemplateFilter('protocol', value ?? '')} />
            <Input aria-label="Brand" placeholder="Brand" allowClear
              value={templateFilters.brand} onChange={event => setTemplateFilter('brand', event.target.value)} />
            <Input aria-label="Model" placeholder="Model" allowClear
              value={templateFilters.model} onChange={event => setTemplateFilter('model', event.target.value)} />
          </div>
          {templates.isError && <Typography.Text type="danger">Unable to load device templates.</Typography.Text>}
          {templates.isLoading && <Typography.Text>Loading templates…</Typography.Text>}
          {!templates.isLoading && !templates.isError && visibleTemplates.length === 0 && (
            <Typography.Text type="secondary">No matching templates.</Typography.Text>
          )}
          <Row gutter={[24, 16]}>
            {visibleTemplates.map(template => (
              <Col key={template.id} xs={24} sm={12} lg={8}>
                <button type="button" className="device-template-option"
                  aria-pressed={templateId === template.id}
                  onClick={() => void selectTemplate(template.id)}>
                  <span className="device-template-image">
                    <DeviceImageThumb imageUrl={template.imageUrl} templateImage={template.image} size={144} />
                  </span>
                  <span className="device-template-details">
                    <span>Name : {template.name}</span>
                    <span>Brand : {String(template.deviceInformation?.Brand ?? template.brand ?? '')}</span>
                    <span>Model : {String(template.deviceInformation?.Model ?? template.model ?? '')}</span>
                    {template.categoryName && <span className="device-template-category">{template.categoryName}</span>}
                    <span className="device-template-protocol">Protocol : {template.protocol.toUpperCase()}</span>
                  </span>
                </button>
              </Col>
            ))}
          </Row>
        </>
      )}

      <div hidden={step !== infoStepIndex}>
        <Typography.Title level={5}>Information</Typography.Title>
        <Form form={infoForm} layout="vertical" initialValues={{ isActive: true, deviceRole: 'native_device' }}>
          {isEdit && (
            <div style={{ marginBottom: 24 }}>
              <div style={{ marginBottom: 8 }}>
                <DeviceImageThumb
                  imageUrl={imagePreview || device.data?.imageUrl}
                  image={device.data?.image}
                  templateImage={device.data?.templateImage || preview?.image}
                  size={160}
                />
              </div>
              <input
                type="file"
                name="image"
                id="image"
                accept="image/*"
                className="ant-input"
                style={{ display: 'block', maxWidth: 420, padding: '4px 11px' }}
                onChange={(e) => {
                  const file = e.target.files?.[0] ?? null
                  setImageFile(file)
                  if (imagePreview) URL.revokeObjectURL(imagePreview)
                  setImagePreview(file ? URL.createObjectURL(file) : null)
                }}
              />
            </div>
          )}
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item label={t('brand')}>
                <Input value={brand} disabled />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item label={t('model')}>
                <Input value={model} disabled />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="name" label={t('device_name')} rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="deviceRole" label={t('device_role')}>
                <Select
                  options={[
                    { value: 'native_device', label: t('role_native') },
                    { value: 'gateway', label: t('role_gateway') },
                    { value: 'endpoint', label: t('role_endpoint') },
                  ]}
                />
              </Form.Item>
            </Col>
            {deviceRole === 'endpoint' && (
              <Col span={12}>
                <Form.Item
                  name="gatewayDeviceId"
                  label={t('parent_gateway')}
                  rules={[{ required: true, message: t('parent_gateway_required') }]}
                >
                  <Select
                    placeholder={t('select_gateway')}
                    options={(gateways.data ?? []).map((g) => ({
                      value: g.id,
                      label: `${g.name} (#${g.id})`,
                    }))}
                    showSearch
                    optionFilterProp="label"
                  />
                </Form.Item>
              </Col>
            )}
          </Row>
          <Form.Item name="serialNumber" label={t('serial_number')}>
            <Input />
          </Form.Item>
          <Form.Item name="siteNodeId" label="Site node" normalize={value => value ?? null}
            extra={sites.isError ? 'Unable to load Site Management nodes' : `${siteOptions.length} locations · type to search`}>
            <Select showSearch allowClear loading={sites.isLoading} disabled={sites.isError}
              placeholder="Search by district or path..." options={siteOptions}
              filterOption={(input, option) =>
                `${option?.label ?? ''} ${option?.nodeType ?? ''}`.toLowerCase().includes(input.trim().toLowerCase())}
            />
          </Form.Item>
          <Form.Item name="location" label={t('location')}>
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item name="description" label={t('description')}>
            <Input.TextArea rows={3} />
          </Form.Item>
          <div style={{ marginBottom: 24 }}>
            <Typography.Title level={5} style={{ marginTop: 0, marginBottom: 8 }}>
              Custom Fields
            </Typography.Title>
            <CustomFieldsEditor
              value={customRows}
              onChange={setCustomRows}
              addLabel={t('add_custom_field')}
            />
          </div>
          <Form.Item name="isActive" valuePropName="checked" style={{ marginBottom: 0 }}>
            <Checkbox>{t('is_active')}</Checkbox>
          </Form.Item>
        </Form>
      </div>

      {step === commStepIndex && (
        <Card title={t('communication_configuration')}>
          <CommunicationFields
            protocol={protocol}
            value={commConfig}
            onChange={setCommConfig}
            endpointNotice={deviceRole === 'endpoint'}
            disabled={deviceRole === 'endpoint'}
          />
        </Card>
      )}

      {step === metricsStepIndex && (
        <Space direction="vertical" size={16} style={{width:'100%'}}>
        {protocol.toLowerCase() === 'snmp' && deviceRole !== 'endpoint' && (
          <PemonMetricsBuilder metrics={metrics} onApply={next=>{setMetrics(next);setPemonGenerated(true)}}/>
        )}
        <MetricBuilder
          protocol={protocol}
          metrics={metrics}
          onChange={setMetrics}
          readOnly={!isEdit && !pemonGenerated}
          snapshotTriggers={snapshotTriggers}
          onSnapshotChange={setSnapshotTriggers}
          snapshotReadOnly={false}
          metricSources={metricSources.data ?? []}
          gatewayMetricKeys={gatewayMetricKeys}
          deviceRole={deviceRole}
          channels={channels.data ?? []}
        />
        </Space>
      )}

      {step === confirmStepIndex && (
        <Card title={t('confirm_device_config')}>
          <Typography.Paragraph>{t('recheck_message')}</Typography.Paragraph>
          <Row gutter={[16, 8]}>
            <Col span={12}>
              <Typography.Text type="secondary">{t('device_template')}</Typography.Text>
              <div>{preview?.name || device.data?.templateName || '-'}</div>
            </Col>
            <Col span={12}>
              <Typography.Text type="secondary">{t('protocol')}</Typography.Text>
              <div>{protocol}</div>
            </Col>
            <Col span={12}>
              <Typography.Text type="secondary">{t('device_name')}</Typography.Text>
              <div>{infoForm.getFieldValue('name')}</div>
            </Col>
            <Col span={12}>
              <Typography.Text type="secondary">{t('device_role')}</Typography.Text>
              <div>{infoForm.getFieldValue('deviceRole')}</div>
            </Col>
            <Col span={12}>
              <Typography.Text type="secondary">{t('serial_number')}</Typography.Text>
              <div>{infoForm.getFieldValue('serialNumber') || '-'}</div>
            </Col>
            <Col span={12}>
              <Typography.Text type="secondary">{t('location')}</Typography.Text>
              <div>{siteOptions.find(option => option.value === infoForm.getFieldValue('siteNodeId'))?.label}</div>
              <div>{infoForm.getFieldValue('location') || '-'}</div>
            </Col>
            <Col span={12}>
              <Typography.Text type="secondary">{t('status')}</Typography.Text>
              <div>
                {infoForm.getFieldValue('isActive') ? t('status_active') : t('status_inactive')}
              </div>
            </Col>
            <Col span={24}>
              <Typography.Text type="secondary">{t('communication_config')}</Typography.Text>
              <pre style={{ background: '#fafafa', padding: 12, borderRadius: 8, maxHeight: 200, overflow: 'auto' }}>
                {JSON.stringify(
                  deviceRole === 'endpoint'
                    ? {
                        protocol: 'gateway_endpoint',
                        source: { gateway_device_id: infoForm.getFieldValue('gatewayDeviceId') },
                      }
                    : commConfig,
                  null,
                  2,
                )}
              </pre>
            </Col>
            <Col span={24}>
              <Typography.Text type="secondary">{t('metrics')}</Typography.Text>
              <div>
                {metrics.length} metrics · {snapshotTriggers.length} snapshot triggers
              </div>
            </Col>
            {(imagePreview || currentImage) && (
              <Col span={24}>
                <DeviceImageThumb
                  imageUrl={imagePreview || undefined}
                  image={device.data?.image}
                  templateImage={preview?.image}
                  size={120}
                />
              </Col>
            )}
          </Row>
        </Card>
      )}

      <Space className="device-wizard-footer" style={{ width: '100%', justifyContent: 'space-between' }}>
        <Button onClick={() => (step === 0 ? navigate('/device') : goBack())}>
          {step === 0 ? t('devices') : t('back')}
        </Button>
        <Space>
          {step < confirmStepIndex ? (
            <Button type="primary" onClick={() => void goNext()}>
              {t('next')}
            </Button>
          ) : (
            <Button type="primary" loading={save.isPending} onClick={() => save.mutate()}>
              {isEdit ? t('update_device') : t('save_device')}
            </Button>
          )}
        </Space>
      </Space>
      </Card>
    </div>
  )
}
