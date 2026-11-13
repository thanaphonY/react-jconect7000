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
  Tabs,
  Typography,
} from 'antd'
import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError } from '../api/client'
import { fetchMetricSources, fetchNotificationChannels } from '../features/devices/api'
import {
  CustomFieldsEditor,
  customFieldsToObject,
  objectToCustomFields,
  type CustomFieldRow,
} from '../features/devices/CustomFieldsEditor'
import { MetricBuilder } from '../features/devices/MetricBuilder'
import type { Metric } from '../features/devices/types'
import { templateUploadUrl } from '../features/devices/urls'
import {
  fetchDcimTaxonomy,
  fetchTemplate,
  saveTemplate,
  uploadTemplateImage,
} from '../features/devicetemplate/api'
import {
  seedCommunicationForProtocol,
  TemplateCommunicationEditor,
} from '../features/devicetemplate/TemplateCommunicationEditor'
import { TemplateDashboardBuilder } from '../features/devicetemplate/TemplateDashboardBuilder'
import {
  DEFAULT_DEVICE_INFO,
  TEMPLATE_PROTOCOLS,
  type TemplateDashboardWidget,
} from '../features/devicetemplate/types'

type GeneralValues = {
  name: string
  description?: string
  protocol: string
  dcimCategory: string
  dcimSubCategory: string
  isActive: boolean
}

function injectDcimKeys(
  rows: CustomFieldRow[],
  taxonomy: { category: string; sub: string; domain: string; pue_role: string } | null,
): CustomFieldRow[] {
  const obj = customFieldsToObject(rows)
  if (taxonomy) {
    obj.domain = taxonomy.domain
    obj.pue_role = taxonomy.pue_role
    obj.dcim_category = taxonomy.category
    obj.dcim_sub_category = taxonomy.sub
    const label = `${taxonomy.category} / ${taxonomy.sub}`.toLowerCase()
    if (label.includes('ups')) {
      if (!obj['Device Type']) obj['Device Type'] = 'UPS'
      if (!('Power Rating' in obj)) obj['Power Rating'] = ''
      if (!('Battery Type' in obj)) obj['Battery Type'] = ''
    } else if (label.includes('temperature') || label.includes('humidity')) {
      if (!obj['Device Type']) obj['Device Type'] = 'Sensor'
      if (!('Measurement Range' in obj)) obj['Measurement Range'] = ''
      if (!('Accuracy' in obj)) obj['Accuracy'] = ''
    } else if (label.includes('power')) {
      if (!obj['Device Type']) obj['Device Type'] = 'Power Meter'
      if (!('Voltage Rating' in obj)) obj['Voltage Rating'] = ''
      if (!('Current Rating' in obj)) obj['Current Rating'] = ''
    }
  }
  return objectToCustomFields(obj)
}

export function TemplateFormPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const params = useParams()
  const templateId = params.id && params.id !== 'new' ? Number(params.id) : null
  const isEdit = templateId != null && Number.isInteger(templateId)

  const [form] = Form.useForm<GeneralValues>()
  const [commConfig, setCommConfig] = useState<Record<string, unknown>>({})
  const [infoRows, setInfoRows] = useState<CustomFieldRow[]>(
    objectToCustomFields(DEFAULT_DEVICE_INFO),
  )
  const [metrics, setMetrics] = useState<Metric[]>([])
  const [layout, setLayout] = useState<TemplateDashboardWidget[]>([])
  const [imageName, setImageName] = useState<string | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const [activeTab, setActiveTab] = useState('general')

  const taxonomy = useQuery({
    queryKey: ['dcim-taxonomy'],
    queryFn: fetchDcimTaxonomy,
  })
  const metricSources = useQuery({
    queryKey: ['device-metric-sources'],
    queryFn: fetchMetricSources,
  })
  const channels = useQuery({
    queryKey: ['notification-channels'],
    queryFn: fetchNotificationChannels,
  })
  const existing = useQuery({
    queryKey: ['device-template', templateId],
    enabled: isEdit,
    queryFn: () => fetchTemplate(templateId!),
  })

  const protocol = Form.useWatch('protocol', form) || ''
  const dcimCategory = Form.useWatch('dcimCategory', form) || ''
  const dcimSubCategory = Form.useWatch('dcimSubCategory', form) || ''

  const subOptions = useMemo(() => {
    const entry = (taxonomy.data ?? []).find((e) => e.category === dcimCategory)
    return entry?.subs ?? []
  }, [taxonomy.data, dcimCategory])

  const selectedTaxonomy = useMemo(() => {
    const entry = (taxonomy.data ?? []).find((e) => e.category === dcimCategory)
    if (!entry || !dcimSubCategory) return null
    return {
      category: entry.category,
      sub: dcimSubCategory,
      domain: entry.domain,
      pue_role: entry.pue_role,
    }
  }, [taxonomy.data, dcimCategory, dcimSubCategory])

  useEffect(() => {
    if (!existing.data) return
    const data = existing.data
    form.setFieldsValue({
      name: data.name,
      description: data.description,
      protocol: data.protocol,
      dcimCategory: data.selectedDcimCategory || data.dcimCategory,
      dcimSubCategory: data.selectedDcimSubCategory || data.dcimSubCategory,
      isActive: data.isActive,
    })
    setCommConfig(
      data.communicationConfig && typeof data.communicationConfig === 'object'
        ? (data.communicationConfig as Record<string, unknown>)
        : {},
    )
    const info =
      data.deviceInformation && Object.keys(data.deviceInformation).length
        ? data.deviceInformation
        : DEFAULT_DEVICE_INFO
    setInfoRows(objectToCustomFields(info))
    setMetrics(Array.isArray(data.metrics) ? data.metrics : [])
    setLayout(Array.isArray(data.defaultDashboardLayout) ? data.defaultDashboardLayout : [])
    setImageName(data.image ?? null)
    setImagePreview(data.imageUrl ?? templateUploadUrl(data.image))
  }, [existing.data, form])

  useEffect(() => {
    if (!taxonomy.data?.length) return
    const currentCat = form.getFieldValue('dcimCategory')
    if (!currentCat) {
      const first = taxonomy.data[0]!
      form.setFieldsValue({
        dcimCategory: first.category,
        dcimSubCategory: first.subs[0],
      })
    }
  }, [taxonomy.data, form])

  useEffect(() => {
    if (!selectedTaxonomy) return
    setInfoRows((rows) => injectDcimKeys(rows, selectedTaxonomy))
  }, [selectedTaxonomy?.category, selectedTaxonomy?.sub])

  const save = useMutation({
    mutationFn: async (values: GeneralValues) => {
      return saveTemplate(isEdit ? templateId : null, {
        name: values.name,
        description: values.description ?? '',
        protocol: values.protocol,
        dcimCategory: values.dcimCategory,
        dcimSubCategory: values.dcimSubCategory,
        communicationConfig: commConfig,
        deviceInformation: customFieldsToObject(infoRows),
        metrics,
        defaultDashboardLayout: layout,
        isActive: values.isActive,
        image: imageName,
      })
    },
    onSuccess: () => {
      message.success(t('your_action_has_been_completed_successfully'))
      navigate('/devicetemplate')
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : t('save'))
    },
  })

  async function onProtocolChange(nextProtocol: string) {
    form.setFieldValue('protocol', nextProtocol)
    setCommConfig((prev) => seedCommunicationForProtocol(nextProtocol, prev))
  }

  async function onCategoryChange(nextCategory: string) {
    const entry = (taxonomy.data ?? []).find((e) => e.category === nextCategory)
    form.setFieldsValue({
      dcimCategory: nextCategory,
      dcimSubCategory: entry?.subs[0] ?? '',
    })
  }

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <div>
          <Typography.Title level={3} style={{ margin: 0 }}>
            {isEdit ? t('edit') : t('add')} {t('device_template')}
          </Typography.Title>
          <Typography.Text type="secondary">
            <Link to="/home">{t('home')}</Link> /{' '}
            <Link to="/devicetemplate">{t('device_template')}</Link> /{' '}
            {isEdit ? t('edit') : t('add')}
          </Typography.Text>
        </div>
        <Button onClick={() => navigate('/devicetemplate')}>{t('back')}</Button>
      </Space>

      <Form
        form={form}
        layout="vertical"
        initialValues={{ isActive: false }}
        onFinish={(values) => save.mutate(values)}
      >
        <Tabs
          activeKey={activeTab}
          onChange={setActiveTab}
          items={[
            {
              key: 'general',
              label: t('general'),
              children: (
                <Card title={t('general_information')}>
                  <Form.Item label={t('image')}>
                    {imagePreview ? (
                      <div style={{ marginBottom: 8 }}>
                        <img src={imagePreview} alt="template" style={{ maxWidth: 200 }} />
                      </div>
                    ) : null}
                    <input
                      type="file"
                      name="image"
                      accept="image/*"
                      onChange={async (e) => {
                        const file = e.target.files?.[0]
                        if (!file) return
                        try {
                          const filename = await uploadTemplateImage(file)
                          setImageName(filename)
                          setImagePreview(URL.createObjectURL(file))
                          message.success(t('image'))
                        } catch (error) {
                          message.error(
                            error instanceof ApiError ? error.message : t('upload_failed'),
                          )
                        }
                      }}
                    />
                  </Form.Item>
                  <Form.Item name="name" label={t('template_name')} rules={[{ required: true }]}>
                    <Input />
                  </Form.Item>
                  <Form.Item name="description" label={t('description')}>
                    <Input.TextArea rows={3} />
                  </Form.Item>
                  <Form.Item name="protocol" label={t('protocol')} rules={[{ required: true }]}>
                    <Select
                      placeholder={t('select_protocol')}
                      onChange={onProtocolChange}
                      options={TEMPLATE_PROTOCOLS.map((p) => ({
                        value: p,
                        label: p.replaceAll('_', ' ').toUpperCase(),
                      }))}
                    />
                  </Form.Item>
                  <Row gutter={12}>
                    <Col span={12}>
                      <Form.Item
                        name="dcimCategory"
                        label={t('category')}
                        rules={[{ required: true }]}
                      >
                        <Select
                          onChange={onCategoryChange}
                          options={(taxonomy.data ?? []).map((entry) => ({
                            value: entry.category,
                            label: entry.category,
                          }))}
                        />
                      </Form.Item>
                    </Col>
                    <Col span={12}>
                      <Form.Item
                        name="dcimSubCategory"
                        label="Sub-Category"
                        rules={[{ required: true }]}
                        extra={
                          dcimCategory && dcimSubCategory ? (
                            <span>
                              Will be saved as{' '}
                              <code>
                                {dcimCategory} / {dcimSubCategory}
                              </code>
                            </span>
                          ) : null
                        }
                      >
                        <Select
                          options={subOptions.map((sub) => ({ value: sub, label: sub }))}
                        />
                      </Form.Item>
                    </Col>
                  </Row>
                </Card>
              ),
            },
            {
              key: 'communication',
              label: t('communication'),
              children: (
                <Card title={t('communication_configuration')}>
                  <Typography.Paragraph type="secondary">
                    {t('communication_config')}
                  </Typography.Paragraph>
                  <TemplateCommunicationEditor
                    protocol={protocol}
                    value={commConfig}
                    onChange={setCommConfig}
                    addLabel={t('add_field')}
                    removeLabel={t('remove')}
                  />
                </Card>
              ),
            },
            {
              key: 'device',
              label: t('device_info'),
              children: (
                <Card title={t('device_information')}>
                  <CustomFieldsEditor
                    value={infoRows}
                    onChange={setInfoRows}
                    addLabel={t('add_field')}
                    keyPlaceholder="Key"
                    valuePlaceholder="Value"
                    removeLabel={t('remove')}
                  />
                </Card>
              ),
            },
            {
              key: 'metrics',
              label: t('metrics'),
              children: (
                <Card title={t('metrics_definition')}>
                  <MetricBuilder
                    protocol={protocol || 'modbus_tcp'}
                    metrics={metrics}
                    onChange={setMetrics}
                    snapshotTriggers={[]}
                    onSnapshotChange={() => undefined}
                    snapshotReadOnly
                    metricSources={metricSources.data ?? []}
                    channels={channels.data ?? []}
                  />
                </Card>
              ),
            },
            {
              key: 'dashboard',
              label: t('dashboard'),
              children: <TemplateDashboardBuilder value={layout} onChange={setLayout} />,
            },
          ]}
        />

        <Card style={{ marginTop: 16 }}>
          <Form.Item name="isActive" valuePropName="checked" style={{ marginBottom: 12 }}>
            <Checkbox>{t('is_active')}</Checkbox>
          </Form.Item>
          <Button type="primary" htmlType="submit" loading={save.isPending} icon={<i className="bi bi-save" />}>
            {t('save')}
          </Button>
        </Card>
      </Form>
    </Space>
  )
}
