import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Button,
  ColorPicker,
  Form,
  Input,
  Modal,
  Space,
  Switch,
  Table,
  Typography,
} from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, apiRequest } from '../api/client'
import { faIconClass } from '../features/devices/urls'

type Category = {
  id: number
  name: string
  description: string
  icon: string
  color: string
  isActive: boolean
  devicesCount: number
  templatesCount: number
}

type FormValues = {
  name: string
  description?: string
  icon?: string
  color?: string
  isActive: boolean
}

export function CategoriesPage() {
  const { t } = useTranslation()
  const { message, modal } = App.useApp()
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<Category | null>(null)
  const [form] = Form.useForm<FormValues>()

  const categories = useQuery({
    queryKey: ['device-categories'],
    queryFn: () => apiRequest<Category[]>('/device-categories'),
  })

  const save = useMutation({
    mutationFn: (values: FormValues) => {
      const body = {
        name: values.name,
        description: values.description ?? '',
        icon: values.icon ?? 'fa-server',
        color: values.color ?? '#007bff',
        isActive: values.isActive,
      }
      if (editing) {
        return apiRequest(`/device-categories/${editing.id}`, { method: 'PATCH', body })
      }
      return apiRequest('/device-categories', { method: 'POST', body })
    },
    onSuccess: async () => {
      setOpen(false)
      message.success(editing ? t('category_updated_successfully') : t('category_added_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['device-categories'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : t('save'))
    },
  })

  const remove = useMutation({
    mutationFn: (id: number) => apiRequest(`/device-categories/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('category_deleted_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['device-categories'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : t('delete'))
    },
  })

  const iconValue = Form.useWatch('icon', form) || 'fa-server'
  const colorValue = Form.useWatch('color', form) || '#007bff'

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={3} style={{ margin: 0 }}>
          <i className="fas fa-layer-group" style={{ marginRight: 8 }} />
          {t('device_categories')}
        </Typography.Title>
        <Button
          type="primary"
          icon={<i className="fas fa-plus" />}
          onClick={() => {
            setEditing(null)
            form.setFieldsValue({ isActive: true, icon: 'fa-server', color: '#007bff', description: '' })
            setOpen(true)
          }}
        >
          {t('add_category')}
        </Button>
      </Space>
      <Table
        rowKey="id"
        loading={categories.isLoading}
        dataSource={categories.data ?? []}
        pagination={false}
        columns={[
          {
            title: t('icon'),
            dataIndex: 'icon',
            width: 80,
            render: (icon: string, row) => (
              <span
                style={{
                  display: 'inline-grid',
                  placeItems: 'center',
                  width: 36,
                  height: 36,
                  borderRadius: 8,
                  background: row.color || '#007bff',
                  color: '#fff',
                }}
              >
                <i className={faIconClass(icon)} />
              </span>
            ),
          },
          { title: t('name'), dataIndex: 'name' },
          { title: t('description'), dataIndex: 'description' },
          {
            title: t('color'),
            dataIndex: 'color',
            render: (color: string) => (
              <Space>
                <span
                  style={{
                    display: 'inline-block',
                    width: 16,
                    height: 16,
                    borderRadius: 4,
                    background: color || '#007bff',
                    border: '1px solid #ddd',
                  }}
                />
                {color || '#007bff'}
              </Space>
            ),
          },
          {
            title: t('active'),
            dataIndex: 'isActive',
            render: (value: boolean) => (value ? t('yes') : t('no')),
          },
          { title: t('devices'), dataIndex: 'devicesCount' },
          { title: t('device_template'), dataIndex: 'templatesCount' },
          {
            title: t('actions'),
            render: (_value, row) => (
              <Space>
                <Button
                  size="small"
                  icon={<i className="fas fa-edit" />}
                  onClick={() => {
                    setEditing(row)
                    form.setFieldsValue(row)
                    setOpen(true)
                  }}
                >
                  {t('edit')}
                </Button>
                <Button
                  size="small"
                  danger
                  icon={<i className="fas fa-trash" />}
                  onClick={() => {
                    modal.confirm({
                      title: t('confirm_delete'),
                      content: row.name,
                      onOk: () => remove.mutateAsync(row.id),
                    })
                  }}
                >
                  {t('delete')}
                </Button>
              </Space>
            ),
          },
        ]}
      />
      <Modal
        title={
          <>
            <i className={`fas ${editing ? 'fa-edit' : 'fa-plus'}`} style={{ marginRight: 8 }} />
            {editing ? t('edit_category') : t('add_category')}
          </>
        }
        open={open}
        onCancel={() => setOpen(false)}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        okText={
          <>
            <i className="fas fa-save" style={{ marginRight: 6 }} />
            {t('save')}
          </>
        }
      >
        <Form form={form} layout="vertical" onFinish={(values) => save.mutate(values)}>
          <Form.Item name="name" label={t('category_name')} rules={[{ required: true }]}>
            <Input placeholder={t('enter_category_name')} />
          </Form.Item>
          <Form.Item name="description" label={t('description')}>
            <Input.TextArea rows={2} placeholder={t('enter_description')} />
          </Form.Item>
          <Form.Item
            name="icon"
            label={t('icon')}
            extra={t('icon_help')}
            rules={[{ required: true }]}
          >
            <Input
              placeholder={t('icon_placeholder')}
              addonBefore={<i className={faIconClass(iconValue)} style={{ color: colorValue }} />}
            />
          </Form.Item>
          <Form.Item name="color" label={t('color')} extra={t('color_help')} hidden>
            <Input />
          </Form.Item>
          <Form.Item label={t('color')} extra={t('color_help')}>
            <Space>
              <ColorPicker
                value={colorValue}
                onChange={(_, hex) => form.setFieldValue('color', hex)}
              />
              <Input
                style={{ width: 140 }}
                value={colorValue}
                onChange={(e) => form.setFieldValue('color', e.target.value)}
                placeholder={t('color_placeholder')}
              />
            </Space>
          </Form.Item>
          <Form.Item name="isActive" label={t('active')} valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </Space>
  )
}
