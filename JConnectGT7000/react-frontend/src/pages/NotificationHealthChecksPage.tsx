import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Button,
  Card,
  Checkbox,
  Col,
  Form,
  Input,
  InputNumber,
  Modal,
  Row,
  Select,
  Space,
  Table,
  Tabs,
  Tag,
  TimePicker,
  Typography,
} from 'antd'
import dayjs, { type Dayjs } from 'dayjs'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { apiRequest } from '../api/client'

type ChannelOption = {
  id: number
  name: string
  type: string
}

type HealthCheck = {
  id: number
  name: string
  isActive: boolean
  scheduleType: string
  scheduleTime: string
  scheduleWeekday: number
  scheduleDay: number
  channelIds: number[]
  messageTemplate: string
  nextRunLabel: string
  lastRunLabel: string
}

type HealthPayload = {
  checks: HealthCheck[]
  channels: ChannelOption[]
  defaultTemplate: string
}

type HealthFormValues = {
  id?: number
  name: string
  scheduleType: 'daily' | 'weekly' | 'monthly'
  scheduleTime: Dayjs
  scheduleWeekday?: number
  scheduleDay?: number
  isActive: boolean
  channelIds: number[]
  messageTemplate: string
}

export function NotificationHealthChecksPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [form] = Form.useForm<HealthFormValues>()
  const scheduleType = Form.useWatch('scheduleType', form)
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<HealthCheck | null>(null)
  const [preview, setPreview] = useState('')

  const data = useQuery({
    queryKey: ['notification-health-checks'],
    queryFn: () => apiRequest<HealthPayload>('/notification-health-checks'),
  })

  const saveMutation = useMutation({
    mutationFn: (values: HealthFormValues) =>
      apiRequest<{ success: boolean; id: number; message: string }>('/notification-health-checks', {
        method: 'POST',
        body: {
          id: values.id ?? 0,
          name: values.name,
          isActive: values.isActive,
          scheduleType: values.scheduleType,
          scheduleTime: values.scheduleTime.format('HH:mm'),
          scheduleWeekday: values.scheduleWeekday ?? 1,
          scheduleDay: values.scheduleDay ?? 1,
          channelIds: values.channelIds ?? [],
          messageTemplate: values.messageTemplate ?? '',
        },
      }),
    onSuccess: async (res) => {
      if (!res.success) {
        message.error(res.message || t('save_failed'))
        return
      }
      message.success(t('saved'))
      setOpen(false)
      setEditing(null)
      await queryClient.invalidateQueries({ queryKey: ['notification-health-checks'] })
    },
    onError: (err: Error) => message.error(err.message),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) =>
      apiRequest(`/notification-health-checks/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['notification-health-checks'] })
    },
    onError: (err: Error) => message.error(err.message),
  })

  const previewMutation = useMutation({
    mutationFn: async () => {
      const values = await form.validateFields()
      return apiRequest<{ success: boolean; message: string }>('/notification-health-checks/preview', {
        method: 'POST',
        body: {
          name: values.name,
          isActive: values.isActive,
          scheduleType: values.scheduleType,
          scheduleTime: values.scheduleTime.format('HH:mm'),
          scheduleWeekday: values.scheduleWeekday ?? 1,
          scheduleDay: values.scheduleDay ?? 1,
          channelIds: values.channelIds ?? [],
          messageTemplate: values.messageTemplate ?? '',
        },
      })
    },
    onSuccess: (res) => setPreview(res.message || ''),
    onError: (err: Error) => message.error(err.message),
  })

  const sendMutation = useMutation({
    mutationFn: (id: number) =>
      apiRequest<{ success: boolean }>(`/notification-health-checks/${id}/send`, {
        method: 'POST',
        body: {},
      }),
    onSuccess: (res) => {
      message[res.success ? 'success' : 'error'](res.success ? t('sent') : t('send_failed'))
    },
    onError: (err: Error) => message.error(err.message),
  })

  const fillForm = (check?: HealthCheck | null) => {
    const defaults = data.data?.defaultTemplate ?? '{name}\n{datetime}\nStatus: {status}\n\n{alarms}'
    form.setFieldsValue({
      id: check?.id,
      name: check?.name ?? '',
      scheduleType: (check?.scheduleType as HealthFormValues['scheduleType']) ?? 'daily',
      scheduleTime: dayjs(check?.scheduleTime ?? '08:00', 'HH:mm'),
      scheduleWeekday: check?.scheduleWeekday ?? 1,
      scheduleDay: check?.scheduleDay ?? 1,
      isActive: check?.isActive !== false,
      channelIds: check?.channelIds ?? [],
      messageTemplate: check?.messageTemplate || defaults,
    })
    setPreview('')
  }

  const openAdd = () => {
    setEditing(null)
    fillForm(null)
    setOpen(true)
  }

  const openEdit = (check: HealthCheck) => {
    setEditing(check)
    fillForm(check)
    setOpen(true)
  }

  const scheduleLabel = (check: HealthCheck) =>
    `${check.scheduleType.charAt(0).toUpperCase()}${check.scheduleType.slice(1)} ${check.scheduleTime}`

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={4} style={{ margin: 0 }}>
          <i className="bi bi-heart-pulse" style={{ marginRight: 8 }} />
          {t('health_check')}
        </Typography.Title>
        <Button type="primary" icon={<i className="bi bi-plus-circle" />} onClick={openAdd}>
          {t('add_health_check')}
        </Button>
      </Space>

      <Tabs
        activeKey="health"
        onChange={(key) => {
          if (key === 'channels') navigate('/notification_manager')
        }}
        items={[
          { key: 'channels', label: t('notification_channels_tab') },
          { key: 'health', label: t('health_check') },
        ]}
      />

      <Card>
        {!data.isLoading && !(data.data?.checks.length) ? (
          <Typography.Text type="secondary">{t('no_health_checks')}</Typography.Text>
        ) : (
          <Table
            rowKey="id"
            loading={data.isLoading}
            dataSource={data.data?.checks ?? []}
            pagination={false}
            columns={[
              { title: t('name'), dataIndex: 'name' },
              {
                title: t('schedule'),
                render: (_: unknown, row: HealthCheck) => scheduleLabel(row),
              },
              { title: t('next_run'), dataIndex: 'nextRunLabel' },
              { title: t('last_run'), dataIndex: 'lastRunLabel' },
              {
                title: t('status'),
                dataIndex: 'isActive',
                render: (value: boolean) => (
                  <Tag color={value ? 'success' : 'default'}>
                    {value ? 'Active' : 'Inactive'}
                  </Tag>
                ),
              },
              {
                title: t('action'),
                width: 160,
                render: (_: unknown, row: HealthCheck) => (
                  <Space>
                    <Button
                      size="small"
                      icon={<i className="bi bi-send" />}
                      title={t('send_now')}
                      loading={sendMutation.isPending}
                      onClick={() => sendMutation.mutate(row.id)}
                    />
                    <Button
                      size="small"
                      icon={<i className="bi bi-pencil" />}
                      title={t('edit')}
                      onClick={() => openEdit(row)}
                    />
                    <Button
                      size="small"
                      danger
                      icon={<i className="bi bi-trash" />}
                      title={t('delete')}
                      onClick={() => {
                        Modal.confirm({
                          title: t('delete_health_confirm', { name: row.name }),
                          onOk: () => deleteMutation.mutateAsync(row.id),
                        })
                      }}
                    />
                  </Space>
                ),
              },
            ]}
          />
        )}
      </Card>

      <Modal
        title={editing ? t('edit_health_check') : t('add_health_check')}
        open={open}
        onCancel={() => setOpen(false)}
        width={720}
        destroyOnClose
        footer={[
          <Button key="preview" onClick={() => previewMutation.mutate()} loading={previewMutation.isPending}>
            {t('preview_message')}
          </Button>,
          <Button key="save" type="primary" onClick={() => form.submit()} loading={saveMutation.isPending}>
            {t('save')}
          </Button>,
        ]}
      >
        <Form form={form} layout="vertical" onFinish={(values) => saveMutation.mutate(values)}>
          <Form.Item name="id" hidden>
            <InputNumber />
          </Form.Item>
          <Form.Item name="name" label={t('name')} rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Row gutter={12}>
            <Col span={8}>
              <Form.Item name="scheduleType" label={t('schedule')}>
                <Select
                  options={[
                    { value: 'daily', label: t('daily') },
                    { value: 'weekly', label: t('weekly') },
                    { value: 'monthly', label: t('monthly') },
                  ]}
                />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="scheduleTime" label={t('time')}>
                <TimePicker format="HH:mm" style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={8} style={{ display: 'flex', alignItems: 'end' }}>
              <Form.Item name="isActive" valuePropName="checked">
                <Checkbox>{t('active')}</Checkbox>
              </Form.Item>
            </Col>
          </Row>
          {scheduleType === 'weekly' ? (
            <Form.Item name="scheduleWeekday" label={t('day_of_week')}>
              <Select
                options={[1, 2, 3, 4, 5, 6, 7].map((d) => ({
                  value: d,
                  label: t(`weekday_${d}` as 'weekday_1'),
                }))}
              />
            </Form.Item>
          ) : null}
          {scheduleType === 'monthly' ? (
            <Form.Item name="scheduleDay" label={t('day_of_month')}>
              <InputNumber min={1} max={28} style={{ width: '100%' }} />
            </Form.Item>
          ) : null}
          <Form.Item name="channelIds" label={t('channels')}>
            {(data.data?.channels.length ?? 0) === 0 ? (
              <Typography.Text type="secondary">{t('no_channels')}</Typography.Text>
            ) : (
              <Checkbox.Group style={{ width: '100%' }}>
                <Space direction="vertical">
                  {(data.data?.channels ?? []).map((channel) => (
                    <Checkbox key={channel.id} value={channel.id}>
                      {channel.name} ({channel.type.toUpperCase()})
                    </Checkbox>
                  ))}
                </Space>
              </Checkbox.Group>
            )}
          </Form.Item>
          <Form.Item
            label={
              <Space style={{ width: '100%', justifyContent: 'space-between' }}>
                <span>{t('message_template')}</span>
                <Button
                  type="link"
                  size="small"
                  style={{ padding: 0 }}
                  onClick={() =>
                    form.setFieldValue(
                      'messageTemplate',
                      data.data?.defaultTemplate ?? '{name}\n{datetime}\nStatus: {status}\n\n{alarms}',
                    )
                  }
                >
                  {t('use_default_template')}
                </Button>
              </Space>
            }
            name="messageTemplate"
            extra={
              <>
                {'{name} {datetime} {status} {alarms_count} {alarms}'}
                <br />
                {t('metric_placeholder_hint')}
              </>
            }
          >
            <Input.TextArea rows={8} style={{ fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace' }} />
          </Form.Item>
          {preview ? (
            <pre
              style={{
                background: 'rgba(0,0,0,0.04)',
                padding: 12,
                borderRadius: 6,
                whiteSpace: 'pre-wrap',
              }}
            >
              {preview}
            </pre>
          ) : null}
        </Form>
      </Modal>
    </Space>
  )
}
