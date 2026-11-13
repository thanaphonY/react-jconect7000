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
  Typography,
} from 'antd'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { apiRequest } from '../api/client'
import { dayjs } from '../lib/dayjs'

type ChannelType = 'email' | 'line' | 'line_oa' | 'telegram' | 'webhook'

type Channel = {
  id: number
  name: string
  type: ChannelType | string
  isActive: boolean
  description: string
  messageTemplate?: string
  config?: Record<string, unknown>
  devicesCount: number
}

type Summary = {
  totalChannels: number
  activeChannels: number
  totalDevices: number
  todayAlarms: number
  channels: Channel[]
}

type ChannelDevice = {
  deviceId: number
  deviceName: string
  description: string
  location: string
  metricLabel: string
  metricUnit: string
  triggerOperator: string
  triggerValue: string
  clearOperator: string
  clearValue: string
  severity: string
  isEnabled: boolean
}

type ChannelMessage = {
  id: number
  status: string
  sentAt: string | null
  message: string
  deviceName: string
}

type ChannelFormValues = {
  name: string
  type?: ChannelType
  description?: string
  messageTemplate?: string
  isActive: boolean
  config: Record<string, string | number | boolean | undefined>
}

/** PHP index_enhanced hard-codes Thai recipient hint */
const RECIPIENT_EMAIL_HINT =
  'ใส่หลาย email โดยใช้เครื่องหมายจุลภาค (,) หรือขึ้นบรรทัดใหม่ เช่น: user1@example.com, user2@example.com'

function channelIcon(type: string): string {
  switch (type) {
    case 'email':
      return 'bi-envelope'
    case 'line':
    case 'line_oa':
      return 'bi-chat-dots'
    case 'telegram':
      return 'bi-telegram'
    case 'webhook':
      return 'bi-link-45deg'
    default:
      return 'bi-bell'
  }
}

function severityColor(severity: string): string {
  switch ((severity || '').toLowerCase()) {
    case 'critical':
      return 'red'
    case 'warning':
      return 'gold'
    case 'info':
      return 'blue'
    default:
      return 'default'
  }
}

export function NotificationsPage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [form] = Form.useForm<ChannelFormValues>()
  const channelType = Form.useWatch('type', form)

  const [editorOpen, setEditorOpen] = useState(false)
  const [editing, setEditing] = useState<Channel | null>(null)
  const [typeLocked, setTypeLocked] = useState(false)
  const [devicesOpen, setDevicesOpen] = useState(false)
  const [devicesChannelId, setDevicesChannelId] = useState<number | null>(null)
  const [historyOpen, setHistoryOpen] = useState(false)
  const [historyChannelId, setHistoryChannelId] = useState<number | null>(null)
  const [testOpen, setTestOpen] = useState(false)
  const [testChannelId, setTestChannelId] = useState<number | null>(null)
  const [testMessage, setTestMessage] = useState('Test notification from JConnect System')
  const [templatePreview, setTemplatePreview] = useState('')
  const [listSearch, setListSearch] = useState('')

  const summary = useQuery({
    queryKey: ['notification-channels-summary'],
    queryFn: () => apiRequest<Summary>('/notification-channels/summary'),
  })

  const devices = useQuery({
    queryKey: ['notification-channel-devices', devicesChannelId],
    queryFn: () => apiRequest<ChannelDevice[]>(`/notification-channels/${devicesChannelId}/devices`),
    enabled: devicesChannelId != null && devicesOpen,
  })

  const messages = useQuery({
    queryKey: ['notification-channel-messages', historyChannelId],
    queryFn: () => apiRequest<ChannelMessage[]>(`/notification-channels/${historyChannelId}/messages`),
    enabled: historyChannelId != null && historyOpen,
  })

  const saveMutation = useMutation({
    mutationFn: (values: ChannelFormValues) => {
      const body = {
        name: values.name,
        type: values.type,
        isActive: values.isActive,
        description: values.description ?? '',
        messageTemplate: values.messageTemplate ?? '',
        config: values.config ?? {},
      }
      if (editing) {
        return apiRequest<Channel>(`/notification-channels/${editing.id}`, {
          method: 'PUT',
          body,
        })
      }
      return apiRequest<Channel>('/notification-channels', { method: 'POST', body })
    },
    onSuccess: async () => {
      message.success(editing ? t('channel_updated_successfully') : t('channel_created_successfully'))
      setEditorOpen(false)
      setEditing(null)
      await queryClient.invalidateQueries({ queryKey: ['notification-channels-summary'] })
      await queryClient.invalidateQueries({ queryKey: ['notification-channels'] })
    },
    onError: (err: Error) => message.error(err.message),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) =>
      apiRequest(`/notification-channels/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('channel_deleted_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['notification-channels-summary'] })
      await queryClient.invalidateQueries({ queryKey: ['notification-channels'] })
    },
    onError: (err: Error) => message.error(err.message),
  })

  const testMutation = useMutation({
    mutationFn: () =>
      apiRequest<{ success: boolean; message?: string; error?: string }>(
        `/notification-channels/${testChannelId}/test-send`,
        { method: 'POST', body: { message: testMessage } },
      ),
    onSuccess: (data) => {
      if (data.success) {
        message.success(t('test_message_sent_successfully'))
        setTestOpen(false)
      } else {
        message.error(`${t('failed_to_send_test_message')}: ${data.error || t('unknown')}`)
      }
    },
    onError: (err: Error) => message.error(err.message),
  })

  const openAdd = () => {
    setEditing(null)
    setTypeLocked(false)
    form.resetFields()
    form.setFieldsValue({
      name: '',
      type: undefined,
      description: '',
      messageTemplate: '',
      isActive: true,
      config: {
        smtp_secure: 'false',
        subject: 'Alarm Notification',
      },
    })
    setEditorOpen(true)
  }

  const openEdit = async (row: Channel) => {
    try {
      const channel = await apiRequest<Channel>(`/notification-channels/${row.id}`)
      setEditing(channel)
      setTypeLocked(true)
      const config = { ...(channel.config ?? {}) } as Record<string, string | number | boolean | undefined>
      if (channel.type === 'email') {
        if (config.recipient && !config.to) config.to = String(config.recipient)
        if (config.username && !config.smtp_user) config.smtp_user = String(config.username)
        if (config.password && !config.smtp_pass) config.smtp_pass = String(config.password)
        if (config.host && !config.smtp_host) config.smtp_host = String(config.host)
        if (config.port && !config.smtp_port) config.smtp_port = Number(config.port)
        if (config.secure != null && config.smtp_secure == null) {
          config.smtp_secure = String(config.secure)
        }
      }
      form.setFieldsValue({
        name: channel.name,
        type: channel.type as ChannelType,
        description: channel.description,
        messageTemplate: channel.messageTemplate ?? '',
        isActive: channel.isActive,
        config,
      })
      setEditorOpen(true)
    } catch (err) {
      message.error(err instanceof Error ? err.message : t('error_loading_channel'))
    }
  }

  const openTest = async (id: number) => {
    setTestChannelId(id)
    setTestMessage('Test notification from JConnect System')
    setTemplatePreview('')
    setTestOpen(true)
    try {
      const data = await apiRequest<{ preview: string }>(`/notification-channels/${id}/template`)
      setTemplatePreview(data.preview)
    } catch {
      setTemplatePreview('')
    }
  }

  const configFields = useMemo(() => {
    if (channelType === 'email') {
      return (
        <>
          <Row gutter={12}>
            <Col span={8}>
              <Form.Item name={['config', 'smtp_host']} label={t('smtp_host')}>
                <Input placeholder="smtp.gmail.com" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name={['config', 'smtp_port']} label={t('smtp_port')}>
                <InputNumber style={{ width: '100%' }} placeholder="587" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name={['config', 'smtp_secure']} label={t('smtp_secure')}>
                <Select
                  options={[
                    { value: 'false', label: t('smtp_secure_no') },
                    { value: 'true', label: t('smtp_secure_yes') },
                  ]}
                />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name={['config', 'smtp_user']} label={t('smtp_username')}>
                <Input type="email" placeholder="your@email.com" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name={['config', 'smtp_pass']} label={t('smtp_password')}>
                <Input.Password />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name={['config', 'from']} label={t('from_email')}>
                <Input type="email" placeholder="sender@email.com" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name={['config', 'subject']} label={t('email_subject')}>
                <Input placeholder="Alarm Notification" />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name={['config', 'to']} label={t('recipient_email')} extra={RECIPIENT_EMAIL_HINT}>
            <Input.TextArea rows={3} placeholder={'recipient1@email.com\nrecipient2@email.com'} />
          </Form.Item>
        </>
      )
    }
    if (channelType === 'line' || channelType === 'line_oa') {
      return (
        <>
          <Form.Item name={['config', 'access_token']} label={t('access_token')}>
            <Input placeholder="Your LINE channel access token" />
          </Form.Item>
          <Form.Item name={['config', 'to']} label={t('line_to')}>
            <Input placeholder="Target user ID or group ID" />
          </Form.Item>
        </>
      )
    }
    if (channelType === 'telegram') {
      return (
        <>
          <Form.Item name={['config', 'bot_token']} label={t('bot_token')}>
            <Input placeholder="Your bot token" />
          </Form.Item>
          <Form.Item name={['config', 'chat_id']} label={t('chat_id')}>
            <Input placeholder="Target chat ID" />
          </Form.Item>
        </>
      )
    }
    // webhook / unknown: no create UI fields (PHP enhanced select omits webhook)
    return null
  }, [channelType, t])

  const channels = summary.data?.channels ?? []
  const filteredChannels = useMemo(() => {
    const q = listSearch.trim().toLowerCase()
    if (!q) return channels
    return channels.filter((c) => {
      const hay = `${c.name} ${c.description} ${c.type} ${c.isActive ? 'active' : 'inactive'}`.toLowerCase()
      return hay.includes(q)
    })
  }, [channels, listSearch])

  const typeOptions = useMemo(() => {
    const base = [
      { value: 'email', label: t('email') },
      { value: 'line_oa', label: t('line_oa') },
      { value: 'telegram', label: t('telegram') },
    ]
    // Keep legacy types visible when editing existing channels
    if (editing && (editing.type === 'line' || editing.type === 'webhook')) {
      return [
        ...base,
        editing.type === 'line'
          ? { value: 'line', label: t('line') }
          : { value: 'webhook', label: t('webhook') },
      ]
    }
    return base
  }, [editing, t])

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={4} style={{ margin: 0 }}>
          {t('notification_manager')}
        </Typography.Title>
        <Button type="primary" icon={<i className="bi bi-plus-circle" />} onClick={openAdd}>
          {t('add_channel')}
        </Button>
      </Space>

      <Tabs
        activeKey="channels"
        onChange={(key) => {
          if (key === 'health') navigate('/notification_manager/health_checks')
        }}
        items={[
          { key: 'channels', label: t('notification_channels_tab') },
          { key: 'health', label: t('health_check') },
        ]}
      />

      <Row gutter={16}>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#1677ff', color: '#fff' }}>
            <Space style={{ width: '100%', justifyContent: 'space-between' }}>
              <div>
                <Typography.Title level={3} style={{ color: '#fff', margin: 0 }}>
                  {summary.data?.totalChannels ?? 0}
                </Typography.Title>
                <div>{t('total_channels')}</div>
              </div>
              <i className="bi bi-bell" style={{ fontSize: 32 }} />
            </Space>
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#52c41a', color: '#fff' }}>
            <Space style={{ width: '100%', justifyContent: 'space-between' }}>
              <div>
                <Typography.Title level={3} style={{ color: '#fff', margin: 0 }}>
                  {summary.data?.activeChannels ?? 0}
                </Typography.Title>
                <div>{t('active_channels')}</div>
              </div>
              <i className="bi bi-check-circle" style={{ fontSize: 32 }} />
            </Space>
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#13c2c2', color: '#fff' }}>
            <Space style={{ width: '100%', justifyContent: 'space-between' }}>
              <div>
                <Typography.Title level={3} style={{ color: '#fff', margin: 0 }}>
                  {summary.data?.totalDevices ?? 0}
                </Typography.Title>
                <div>{t('connected_devices')}</div>
              </div>
              <i className="bi bi-cpu" style={{ fontSize: 32 }} />
            </Space>
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#faad14', color: '#fff' }}>
            <Space style={{ width: '100%', justifyContent: 'space-between' }}>
              <div>
                <Typography.Title level={3} style={{ color: '#fff', margin: 0 }}>
                  {summary.data?.todayAlarms ?? 0}
                </Typography.Title>
                <div>{t('todays_alarms')}</div>
              </div>
              <i className="bi bi-exclamation-triangle" style={{ fontSize: 32 }} />
            </Space>
          </Card>
        </Col>
      </Row>

      <Card>
        <Space style={{ width: '100%', justifyContent: 'flex-end', marginBottom: 12 }}>
          <Input.Search
            allowClear
            placeholder="Search"
            style={{ width: 220 }}
            value={listSearch}
            onChange={(e) => setListSearch(e.target.value)}
          />
        </Space>
        <Table
          rowKey="id"
          loading={summary.isLoading}
          dataSource={filteredChannels}
          pagination={{
            defaultPageSize: 10,
            showSizeChanger: true,
            pageSizeOptions: [10, 25, 50, 100],
          }}
          columns={[
            {
              title: '#',
              width: 60,
              render: (_: unknown, __: Channel, index: number) => index + 1,
            },
            {
              title: t('name'),
              dataIndex: 'name',
              render: (value: string, row) => (
                <>
                  <strong>{value}</strong>
                  {row.description ? (
                    <>
                      <br />
                      <Typography.Text type="secondary">{row.description}</Typography.Text>
                    </>
                  ) : null}
                </>
              ),
            },
            {
              title: t('type'),
              dataIndex: 'type',
              render: (value: string) => (
                <Tag color="blue">
                  <i className={`bi ${channelIcon(value)}`} style={{ marginRight: 4 }} />
                  {String(value).toUpperCase()}
                </Tag>
              ),
            },
            {
              title: t('status'),
              dataIndex: 'isActive',
              render: (value: boolean) => (
                <Tag color={value ? 'success' : 'default'}>
                  <i
                    className={`bi bi-${value ? 'check-circle' : 'x-circle'}`}
                    style={{ marginRight: 4 }}
                  />
                  {value ? 'Active' : 'Inactive'}
                </Tag>
              ),
            },
            {
              title: t('connected_devices'),
              dataIndex: 'devicesCount',
              render: (value: number, row) => (
                <Space>
                  <Tag color="blue">
                    {value} {t('devices_count_label')}
                  </Tag>
                  <Button
                    size="small"
                    icon={<i className="bi bi-eye" />}
                    onClick={() => {
                      setDevicesChannelId(row.id)
                      setDevicesOpen(true)
                    }}
                  />
                </Space>
              ),
            },
            {
              title: t('action'),
              width: 220,
              render: (_: unknown, row: Channel) => (
                <Space>
                  <Button
                    size="small"
                    type="primary"
                    ghost
                    icon={<i className="bi bi-pencil" />}
                    onClick={() => void openEdit(row)}
                    title={t('edit')}
                  />
                  <Button
                    size="small"
                    icon={<i className="bi bi-play-circle" />}
                    onClick={() => void openTest(row.id)}
                    title={t('test_channel')}
                  />
                  <Button
                    size="small"
                    icon={<i className="bi bi-clock-history" />}
                    onClick={() => {
                      setHistoryChannelId(row.id)
                      setHistoryOpen(true)
                    }}
                    title={t('message_history')}
                  />
                  <Button
                    size="small"
                    danger
                    icon={<i className="bi bi-trash" />}
                    onClick={() => {
                      Modal.confirm({
                        title: t('delete_channel_confirm', { name: row.name }),
                        onOk: () => deleteMutation.mutateAsync(row.id),
                      })
                    }}
                    title={t('delete')}
                  />
                </Space>
              ),
            },
          ]}
        />
      </Card>

      <Modal
        title={editing ? 'Edit Channel' : t('add_channel')}
        open={editorOpen}
        onCancel={() => setEditorOpen(false)}
        onOk={() => form.submit()}
        okText="Save Channel"
        confirmLoading={saveMutation.isPending}
        width={720}
        destroyOnClose
      >
        <Form form={form} layout="vertical" onFinish={(values) => saveMutation.mutate(values)}>
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="name" label={`${t('name')} *`} rules={[{ required: true }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="type" label={`${t('type')} *`} rules={[{ required: true }]}>
                <Select
                  disabled={typeLocked}
                  allowClear
                  placeholder="Select Type"
                  options={typeOptions}
                />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="description" label={t('description')}>
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item
            name="messageTemplate"
            label={t('message_template')}
            extra={t('message_template_placeholders')}
          >
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item name="isActive" valuePropName="checked">
            <Checkbox>{t('active')}</Checkbox>
          </Form.Item>
          {configFields}
        </Form>
      </Modal>

      <Modal
        title={t('connected_devices')}
        open={devicesOpen}
        onCancel={() => setDevicesOpen(false)}
        footer={null}
        width={900}
      >
        <Table
          rowKey={(row) => `${row.deviceId}-${row.metricLabel}-${row.triggerValue}`}
          loading={devices.isLoading}
          dataSource={devices.data ?? []}
          pagination={false}
          locale={{ emptyText: t('no_devices_connected') }}
          columns={[
            { title: 'Device', dataIndex: 'deviceName' },
            { title: t('description'), dataIndex: 'description' },
            { title: t('location'), dataIndex: 'location' },
            {
              title: t('metric'),
              render: (_: unknown, row: ChannelDevice) =>
                `${row.metricLabel}${row.metricUnit ? ` (${row.metricUnit})` : ''}`,
            },
            {
              title: t('threshold'),
              render: (_: unknown, row: ChannelDevice) => `${row.triggerOperator} ${row.triggerValue}`,
            },
            {
              title: t('clear_threshold'),
              render: (_: unknown, row: ChannelDevice) =>
                row.clearValue
                  ? `${row.clearOperator ? `${row.clearOperator} ` : ''}${row.clearValue}`
                  : '-',
            },
            {
              title: t('severity'),
              dataIndex: 'severity',
              render: (value: string) => <Tag color={severityColor(value)}>{value}</Tag>,
            },
            {
              title: t('status'),
              dataIndex: 'isEnabled',
              render: (value: boolean) => (
                <Typography.Text type={value ? 'success' : 'danger'}>
                  {value ? 'Active' : 'Inactive'}
                </Typography.Text>
              ),
            },
          ]}
        />
      </Modal>

      <Modal
        title={t('message_history')}
        open={historyOpen}
        onCancel={() => setHistoryOpen(false)}
        footer={null}
        width={900}
      >
        <Table
          rowKey="id"
          loading={messages.isLoading}
          dataSource={messages.data ?? []}
          pagination={false}
          locale={{ emptyText: t('no_message_history') }}
          columns={[
            {
              title: t('time'),
              dataIndex: 'sentAt',
              render: (value: string | null) =>
                value ? dayjs(value).tz('Asia/Bangkok').format('MMM DD, HH:mm:ss') : '-',
            },
            { title: 'Device', dataIndex: 'deviceName' },
            { title: t('log_message'), dataIndex: 'message' },
            {
              title: t('status'),
              dataIndex: 'status',
              render: (value: string) => (
                <Typography.Text type={value === 'sent' ? 'success' : 'danger'}>
                  <i
                    className={`bi bi-${value === 'sent' ? 'check-circle' : 'x-circle'}`}
                    style={{ marginRight: 4 }}
                  />
                  {value}
                </Typography.Text>
              ),
            },
          ]}
        />
      </Modal>

      <Modal
        title={t('test_channel')}
        open={testOpen}
        onCancel={() => setTestOpen(false)}
        onOk={() => testMutation.mutate()}
        okText={t('send_test')}
        confirmLoading={testMutation.isPending}
      >
        <Space direction="vertical" style={{ width: '100%' }} size={12}>
          <div>
            <Typography.Text>{t('test_message')}</Typography.Text>
            <Input.TextArea
              rows={3}
              value={testMessage}
              onChange={(e) => setTestMessage(e.target.value)}
              placeholder={t('enter_test_message')}
            />
          </div>
          <div>
            <Typography.Text>{t('message_template_preview')}</Typography.Text>
            <Card size="small" style={{ background: '#e6f4ff', marginTop: 8 }}>
              {templatePreview || '-'}
            </Card>
          </div>
        </Space>
      </Modal>
    </Space>
  )
}
