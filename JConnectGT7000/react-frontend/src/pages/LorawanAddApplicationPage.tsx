import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Alert, App, Breadcrumb, Button, Form, Input, Space, Typography } from 'antd'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { ApiError, apiRequest } from '../api/client'

type AddFormValues = {
  name: string
  description?: string
  tenantId: string
}

type Application = {
  applicationId: string
}

const BTN_SUCCESS = { background: '#198754', borderColor: '#198754', color: '#fff' }

/** Standalone create page — mirrors PHP `/lorawan/add_application`. */
export function LorawanAddApplicationPage() {
  const { message } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [form] = Form.useForm<AddFormValues>()
  const [softError, setSoftError] = useState<string | null>(null)

  const createApp = useMutation({
    mutationFn: (values: AddFormValues) =>
      apiRequest<Application>('/lorawan/applications', {
        method: 'POST',
        body: {
          name: values.name,
          description: values.description ?? '',
          tenantId: values.tenantId,
        },
      }),
    onSuccess: async (created) => {
      setSoftError(null)
      message.success(`Application created: ${created.applicationId}`)
      await queryClient.invalidateQueries({ queryKey: ['lorawan-applications'] })
      await queryClient.invalidateQueries({ queryKey: ['lorawan-app-servers'] })
      navigate('/lorawan')
    },
    onError: (error: unknown) => {
      // PHP re-shows the form; prefer gentle copy over alarming 502 toast.
      const soft =
        error instanceof ApiError && error.code === 'CHIRPSTACK_ERROR'
          ? 'Failed to create application'
          : error instanceof ApiError
            ? error.message
            : 'Failed to create application'
      setSoftError(soft)
    },
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%', maxWidth: 560 }}>
      <div>
        <Typography.Title level={3} style={{ marginBottom: 4 }}>
          Create New Application
        </Typography.Title>
        <Breadcrumb
          items={[
            { title: <Link to="/">Home</Link> },
            { title: <Link to="/lorawan">LoRaWAN</Link> },
            { title: 'Add Application' },
          ]}
        />
      </div>

      {softError ? <Alert type="error" showIcon message={softError} /> : null}

      <Form
        form={form}
        layout="vertical"
        onFinish={(values) => {
          setSoftError(null)
          createApp.mutate(values)
        }}
      >
        <Form.Item
          label="Application Name"
          name="name"
          rules={[{ required: true, message: 'Application Name is required' }]}
        >
          <Input />
        </Form.Item>
        <Form.Item label="Description" name="description">
          <Input />
        </Form.Item>
        <Form.Item
          label="Tenant ID"
          name="tenantId"
          rules={[{ required: true, message: 'Tenant ID is required' }]}
        >
          <Input />
        </Form.Item>
        <Button
          htmlType="submit"
          style={BTN_SUCCESS}
          loading={createApp.isPending}
        >
          Create Application
        </Button>
      </Form>
    </Space>
  )
}
