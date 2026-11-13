import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Alert,
  App,
  Breadcrumb,
  Button,
  Card,
  Form,
  Input,
  Modal,
  Space,
  Table,
  Typography,
} from 'antd'
import { useState } from 'react'
import { Link } from 'react-router'
import { ApiError, apiRequest } from '../api/client'
import { ADMINLTE } from '../theme/adminlteTheme'

type Application = {
  id: number
  applicationId: string
  name: string
  description: string
  tenantId: string
  createdAt: string
}

type AddFormValues = {
  name: string
  description?: string
  tenantId: string
}

type EditFormValues = {
  name: string
  description?: string
}

const BTN_SUCCESS = { background: '#198754', borderColor: '#198754', color: '#fff' }
const BTN_WARNING = { background: '#ffc107', borderColor: '#ffc107', color: '#212529' }

export function LorawanPage() {
  const { message, modal } = App.useApp()
  const queryClient = useQueryClient()
  const [addOpen, setAddOpen] = useState(false)
  const [editOpen, setEditOpen] = useState(false)
  const [editing, setEditing] = useState<Application | null>(null)
  const [createSoftError, setCreateSoftError] = useState<string | null>(null)
  const [addForm] = Form.useForm<AddFormValues>()
  const [editForm] = Form.useForm<EditFormValues>()

  const applications = useQuery({
    queryKey: ['lorawan-applications'],
    queryFn: () => apiRequest<Application[]>('/lorawan/applications'),
  })

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
      setCreateSoftError(null)
      setAddOpen(false)
      addForm.resetFields()
      message.success(`Application created: ${created.applicationId}`)
      await queryClient.invalidateQueries({ queryKey: ['lorawan-applications'] })
      await queryClient.invalidateQueries({ queryKey: ['lorawan-app-servers'] })
    },
    onError: (error: unknown) => {
      // PHP silently redisplays the form when ChirpStack is down — stay open, gentle copy.
      const soft =
        error instanceof ApiError && error.code === 'CHIRPSTACK_ERROR'
          ? 'Failed to create application'
          : error instanceof ApiError
            ? error.message
            : 'Failed to create application'
      setCreateSoftError(soft)
    },
  })

  const updateApp = useMutation({
    mutationFn: (values: EditFormValues) => {
      if (!editing) throw new Error('No application selected')
      return apiRequest<Application>(`/lorawan/applications/${editing.applicationId}`, {
        method: 'PUT',
        body: {
          name: values.name,
          description: values.description ?? '',
        },
      })
    },
    onSuccess: async () => {
      setEditOpen(false)
      setEditing(null)
      message.success('Application updated')
      await queryClient.invalidateQueries({ queryKey: ['lorawan-applications'] })
      await queryClient.invalidateQueries({ queryKey: ['lorawan-app-servers'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : 'Update failed')
    },
  })

  const deleteApp = useMutation({
    mutationFn: (applicationId: string) =>
      apiRequest<{ success: boolean }>(`/lorawan/applications/${applicationId}`, {
        method: 'DELETE',
      }),
    onSuccess: async () => {
      message.success('Application deleted')
      await queryClient.invalidateQueries({ queryKey: ['lorawan-applications'] })
      await queryClient.invalidateQueries({ queryKey: ['lorawan-app-servers'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : 'Delete failed')
    },
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <div>
        <Typography.Title level={3} style={{ marginBottom: 4 }}>
          LoRaWAN Management
        </Typography.Title>
        <Breadcrumb
          items={[
            { title: <Link to="/">Home</Link> },
            { title: 'LoRaWAN' },
          ]}
        />
      </div>

      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={4} style={{ margin: 0 }}>
          LoRa Application List
        </Typography.Title>
        <Button
          style={BTN_SUCCESS}
          onClick={() => {
            setCreateSoftError(null)
            addForm.resetFields()
            setAddOpen(true)
          }}
        >
          + Add Application
        </Button>
      </Space>

      <Card
        title="Applications"
        styles={{
          header: {
            background: ADMINLTE.primary,
            color: '#fff',
            borderBottom: 'none',
          },
          body: { padding: 0 },
        }}
      >
        <Table
          rowKey="applicationId"
          loading={applications.isLoading}
          dataSource={applications.data ?? []}
          pagination={false}
          locale={{ emptyText: 'No applications found' }}
          columns={[
            { title: 'Name', dataIndex: 'name' },
            { title: 'Tenant ID', dataIndex: 'tenantId' },
            { title: 'Description', dataIndex: 'description' },
            { title: 'Created', dataIndex: 'createdAt' },
            {
              title: 'Action',
              key: 'action',
              render: (_: unknown, row: Application) => (
                <Space>
                  <Button
                    size="small"
                    style={BTN_WARNING}
                    onClick={() => {
                      setEditing(row)
                      editForm.setFieldsValue({
                        name: row.name,
                        description: row.description,
                      })
                      setEditOpen(true)
                    }}
                  >
                    Edit
                  </Button>
                  <Button
                    size="small"
                    danger
                    onClick={() => {
                      modal.confirm({
                        title: 'Delete this application?',
                        okText: 'Delete',
                        okButtonProps: { danger: true },
                        onOk: () => deleteApp.mutateAsync(row.applicationId),
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
        title="Add Application"
        open={addOpen}
        onCancel={() => {
          setAddOpen(false)
          setCreateSoftError(null)
        }}
        okText="Create"
        okButtonProps={{ style: BTN_SUCCESS }}
        confirmLoading={createApp.isPending}
        onOk={() => addForm.submit()}
        destroyOnHidden
      >
        {createSoftError ? (
          <Alert
            type="warning"
            showIcon
            message={createSoftError}
            style={{ marginBottom: 12 }}
          />
        ) : null}
        <Form
          form={addForm}
          layout="vertical"
          onFinish={(values) => {
            setCreateSoftError(null)
            createApp.mutate(values)
          }}
        >
          <Form.Item
            label="Name"
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
        </Form>
      </Modal>

      <Modal
        title="Edit Application"
        open={editOpen}
        onCancel={() => {
          setEditOpen(false)
          setEditing(null)
        }}
        okText="Save"
        confirmLoading={updateApp.isPending}
        onOk={() => editForm.submit()}
        destroyOnHidden
      >
        <Form
          form={editForm}
          layout="vertical"
          onFinish={(values) => updateApp.mutate(values)}
        >
          <Form.Item
            label="Name"
            name="name"
            rules={[{ required: true, message: 'Name is required' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item label="Description" name="description">
            <Input />
          </Form.Item>
        </Form>
      </Modal>
    </Space>
  )
}
