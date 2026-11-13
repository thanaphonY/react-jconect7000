import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { App, Breadcrumb, Button, Form, Input, InputNumber, Space, Typography } from 'antd'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, apiRequest } from '../api/client'
import type { LayoutSummary } from '../features/dashboard/types'

type FormValues = {
  name: string
  description?: string
  sortOrder?: number | null
}

export function DashboardFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const layoutId = Number(id)
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [form] = Form.useForm<FormValues>()

  const existing = useQuery({
    queryKey: ['dashboard-meta', layoutId],
    enabled: isEdit && Number.isInteger(layoutId),
    queryFn: async () => {
      const data = await apiRequest<{ layout: LayoutSummary }>(`/dashboards/${layoutId}`)
      return data.layout
    },
  })

  useEffect(() => {
    if (!existing.data) return
    form.setFieldsValue({
      name: existing.data.name,
      description: existing.data.description,
      sortOrder: existing.data.sortOrder,
    })
  }, [existing.data, form])

  const save = useMutation({
    mutationFn: async (values: FormValues) => {
      const body = {
        name: values.name,
        description: values.description ?? '',
        sortOrder: values.sortOrder ?? null,
      }
      if (isEdit) {
        return apiRequest(`/dashboards/${layoutId}`, { method: 'PUT', body })
      }
      return apiRequest<LayoutSummary>('/dashboards', { method: 'POST', body })
    },
    onSuccess: async (data) => {
      message.success(isEdit ? t('layout_updated_successfully') : t('layout_created_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['dashboards'] })
      if (isEdit) {
        navigate('/dashboard')
      } else {
        const created = data as LayoutSummary
        navigate(created?.id ? `/dashboard/edit_layout/${created.id}` : '/dashboard')
      }
    },
    onError: (error: unknown) => {
      message.error(
        error instanceof ApiError
          ? error.message
          : isEdit
            ? t('failed_to_update_layout')
            : t('failed_to_create_layout'),
      )
    },
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%', maxWidth: 640 }}>
      <div>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {t('add_news_dashboard_page')}
        </Typography.Title>
        <Breadcrumb
          items={[
            { title: <Link to="/home">{t('home')}</Link> },
            { title: <Link to="/dashboard">{t('dashboard')}</Link> },
            { title: isEdit ? t('edit') : t('add_dashboard') },
          ]}
        />
      </div>
      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => save.mutate(values)}
        initialValues={{ sortOrder: null }}
      >
        <Form.Item name="name" label={t('layout_name')} rules={[{ required: true }]}>
          <Input />
        </Form.Item>
        <Form.Item
          name="description"
          label={isEdit ? t('description') : t('description_optional')}
        >
          <Input.TextArea rows={3} />
        </Form.Item>
        <Form.Item
          name="sortOrder"
          label="Order"
          extra="Lower number appears first in dashboard lists and the top dropdown."
        >
          <InputNumber style={{ width: '100%' }} min={0} step={1} placeholder="0" />
        </Form.Item>
        <Space>
          <Button type="primary" htmlType="submit" loading={save.isPending}>
            {isEdit ? t('save') : t('create')}
          </Button>
          <Button onClick={() => navigate('/dashboard')}>
            {isEdit ? t('back') : t('cancel')}
          </Button>
        </Space>
      </Form>
    </Space>
  )
}
