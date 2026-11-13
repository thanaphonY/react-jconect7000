import { useMutation, useQuery } from '@tanstack/react-query'
import { App, Button, Form, Input, Select, Space, Typography } from 'antd'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, apiRequest } from '../api/client'
import { PHP_WIDGET_TYPES } from '../features/devicetemplate/types'

type Widget = {
  id: number
  name: string
  widgetType: string
  config: Record<string, unknown> | string
}

type FormValues = {
  name: string
  widgetType: string
  config: string
}

/** Normalize DB string / double-encoded JSON into a pretty object string for the textarea. */
export function configToFormText(config: unknown): string {
  const obj = normalizeConfigObject(config)
  return JSON.stringify(obj, null, 2)
}

export function normalizeConfigObject(config: unknown): Record<string, unknown> {
  if (config == null) return {}
  if (typeof config === 'object' && !Array.isArray(config)) {
    return config as Record<string, unknown>
  }
  if (typeof config === 'string') {
    try {
      const parsed = JSON.parse(config) as unknown
      return normalizeConfigObject(parsed)
    } catch {
      return {}
    }
  }
  return {}
}

function parseConfigInput(raw: string): Record<string, unknown> {
  const parsed = JSON.parse(raw || '{}') as unknown
  if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
    throw new Error('config')
  }
  return parsed as Record<string, unknown>
}

export function WidgetFormPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const params = useParams()
  const widgetId = params.id ? Number(params.id) : null
  const isEdit = widgetId != null && Number.isInteger(widgetId)
  const [form] = Form.useForm<FormValues>()

  const existing = useQuery({
    queryKey: ['widget', widgetId],
    enabled: isEdit,
    queryFn: () => apiRequest<Widget>(`/widgets/${widgetId}`),
  })

  useEffect(() => {
    if (!existing.data) return
    form.setFieldsValue({
      name: existing.data.name,
      widgetType: existing.data.widgetType,
      config: configToFormText(existing.data.config),
    })
  }, [existing.data, form])

  const save = useMutation({
    mutationFn: (values: FormValues) => {
      let config: Record<string, unknown>
      try {
        config = parseConfigInput(values.config)
      } catch {
        throw new ApiError('INVALID', 'Config must be a JSON object')
      }
      const body = { name: values.name, widgetType: values.widgetType, config }
      if (isEdit) {
        return apiRequest(`/widgets/${widgetId}`, { method: 'PATCH', body })
      }
      return apiRequest('/widgets', { method: 'POST', body })
    },
    onSuccess: () => {
      message.success(t('your_action_has_been_completed_successfully'))
      navigate('/widgets')
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : t('save'))
    },
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <div>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {isEdit ? t('edit') : t('add')} {t('widgets')}
        </Typography.Title>
        <Typography.Text type="secondary">
          <Link to="/widgets">{t('widgets')}</Link> / {isEdit ? t('edit') : t('add')}
        </Typography.Text>
      </div>

      <Form
        form={form}
        layout="vertical"
        style={{ maxWidth: 640 }}
        initialValues={{ name: '', widgetType: 'value_box', config: '{}' }}
        onFinish={(values) => save.mutate(values)}
      >
        <Form.Item name="name" label={t('name')} rules={[{ required: true }]}>
          <Input />
        </Form.Item>
        <Form.Item name="widgetType" label={t('widget_type')} rules={[{ required: true }]}>
          <Select options={[...PHP_WIDGET_TYPES]} />
        </Form.Item>
        <Form.Item name="config" label="Widget Config (JSON)">
          <Input.TextArea rows={6} />
        </Form.Item>
        <Space>
          <Button type="primary" htmlType="submit" loading={save.isPending}>
            {isEdit ? t('update') : t('create')}
          </Button>
          <Button onClick={() => navigate('/widgets')}>{t('cancel')}</Button>
        </Space>
      </Form>
    </Space>
  )
}
