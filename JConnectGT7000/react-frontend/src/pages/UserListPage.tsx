import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Button,
  Card,
  Col,
  Form,
  Input,
  Modal,
  Row,
  Select,
  Space,
  Table,
  Tag,
  Typography,
  Upload,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useMemo, useState, type Key } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, apiFormRequest, apiRequest, downloadFile } from '../api/client'
import { assetImageUrl } from '../lib/assets'

type UserRow = {
  id: number
  name: string
  email: string
  userType: string
  status: string | null
  profilePic: string | null
  createDate: string | null
}

type UserList = {
  items: UserRow[]
  total: number
  page: number
  pageSize: number
}

type Stats = {
  totalUsers: number
  activeUsers: number
  pendingUsers: number
  userTypes: number
}

type UserFormValues = {
  name: string
  email: string
  user_type: string
  password?: string
  currentpassword?: string
  confirmPassword?: string
}

export function UserListPage() {
  const { t } = useTranslation()
  const { message, modal } = App.useApp()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [userType, setUserType] = useState('all')
  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [applied, setApplied] = useState({ userType: 'all', status: 'all', search: '' })
  const [open, setOpen] = useState(false)
  const [inviteOpen, setInviteOpen] = useState(false)
  const [inviteEmails, setInviteEmails] = useState('')
  const [editing, setEditing] = useState<UserRow | null>(null)
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<string>(assetImageUrl('user.png'))
  const [selectedKeys, setSelectedKeys] = useState<Key[]>([])
  const [form] = Form.useForm<UserFormValues>()

  const stats = useQuery({
    queryKey: ['users-statistics'],
    queryFn: () => apiRequest<Stats>('/users/statistics'),
  })
  const types = useQuery({
    queryKey: ['users-types'],
    queryFn: () => apiRequest<{ userTypes: string[] }>('/users/types'),
  })
  const users = useQuery({
    queryKey: ['users', page, pageSize, applied],
    queryFn: () => {
      const params = new URLSearchParams({
        page: String(page),
        pageSize: String(pageSize),
      })
      if (applied.userType && applied.userType !== 'all') params.set('user_type', applied.userType)
      if (applied.status && applied.status !== 'all') params.set('status', applied.status)
      if (applied.search) params.set('search', applied.search)
      return apiRequest<UserList>(`/users?${params.toString()}`)
    },
  })

  const save = useMutation({
    mutationFn: async (values: UserFormValues) => {
      const fd = new FormData()
      fd.append('name', values.name)
      fd.append('email', values.email)
      fd.append('user_type', values.user_type)
      if (editing) {
        if (values.currentpassword) fd.append('currentpassword', values.currentpassword)
        if (values.password) fd.append('password', values.password)
        if (values.confirmPassword) fd.append('confirmPassword', values.confirmPassword)
        fd.append('fileOld', editing.profilePic || 'user.png')
        if (file) fd.append('profile_pic', file)
        return apiFormRequest(`/users/${editing.id}`, fd, { method: 'PUT' })
      }
      fd.append('password', values.password || '')
      fd.append('fileOld', 'user.png')
      if (file) fd.append('profile_pic', file)
      return apiFormRequest('/users', fd, { method: 'POST' })
    },
    onSuccess: async () => {
      message.success(
        editing ? t('your_data_updated_successfully') : t('your_data_added_successfully'),
      )
      setOpen(false)
      setEditing(null)
      setFile(null)
      await queryClient.invalidateQueries({ queryKey: ['users'] })
      await queryClient.invalidateQueries({ queryKey: ['users-statistics'] })
    },
    onError: (error: unknown) => {
      if (error instanceof ApiError) {
        if (error.code === 'EMAIL_EXISTS') message.error(t('this_email_already_registered_with_us'))
        else if (error.code === 'INVALID_CURRENT_PASSWORD')
          message.error(t('enter_valid_current_password'))
        else if (error.code === 'PASSWORD_MISMATCH')
          message.error(t('password_and_confirm_password_should_be_same'))
        else message.error(error.message)
      } else {
        message.error(String(error))
      }
    },
  })

  const remove = useMutation({
    mutationFn: (id: number) => apiRequest(`/users/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('your_action_has_been_completed_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['users'] })
      await queryClient.invalidateQueries({ queryKey: ['users-statistics'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : String(error))
    },
  })

  const bulkDelete = useMutation({
    mutationFn: async (ids: number[]) => {
      // Prefer bulk endpoint when available; otherwise soft-delete each id.
      try {
        await apiRequest('/users/bulk-delete', {
          method: 'POST',
          body: { ids },
        })
      } catch {
        for (const id of ids) {
          await apiRequest(`/users/${id}`, { method: 'DELETE' })
        }
      }
    },
    onSuccess: async () => {
      message.success(t('your_action_has_been_completed_successfully'))
      setSelectedKeys([])
      await queryClient.invalidateQueries({ queryKey: ['users'] })
      await queryClient.invalidateQueries({ queryKey: ['users-statistics'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : String(error))
    },
  })

  const invite = useMutation({
    mutationFn: (emails: string) =>
      apiRequest('/users/invite', { method: 'POST', body: { emails } }),
    onSuccess: async () => {
      message.success(t('your_action_has_been_completed_successfully'))
      setInviteOpen(false)
      setInviteEmails('')
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : String(error))
    },
  })

  const columns: ColumnsType<UserRow> = useMemo(
    () => [
      {
        title: t('name'),
        dataIndex: 'name',
        render: (name: string, row) => (
          <Space>
            <img
              src={assetImageUrl(row.profilePic, 'user.png')}
              alt=""
              width={32}
              height={32}
              style={{ borderRadius: '50%', objectFit: 'cover' }}
            />
            {name}
          </Space>
        ),
      },
      { title: t('email'), dataIndex: 'email' },
      { title: 'User Type', dataIndex: 'userType' },
      {
        title: t('status'),
        dataIndex: 'status',
        render: (value: string | null) => {
          const color =
            value === 'active' ? 'success' : value === 'pending' ? 'warning' : 'default'
          return <Tag color={color}>{value || '-'}</Tag>
        },
      },
      { title: t('created_date'), dataIndex: 'createDate' },
      {
        title: t('actions'),
        key: 'actions',
        width: 120,
        render: (_, row) => (
          <Space>
            <Button
              type="link"
              size="small"
              title={t('edit')}
              onClick={() => {
                setEditing(row)
                setFile(null)
                setPreview(assetImageUrl(row.profilePic, 'user.png'))
                form.setFieldsValue({
                  name: row.name,
                  email: row.email,
                  user_type: row.userType,
                  password: '',
                  currentpassword: '',
                  confirmPassword: '',
                })
                setOpen(true)
              }}
            >
              <i className="bi bi-pencil" />
            </Button>
            <Button
              type="link"
              size="small"
              danger
              title={t('delete')}
              onClick={() => {
                modal.confirm({
                  title: t('delete'),
                  content: row.email,
                  onOk: () => remove.mutateAsync(row.id),
                })
              }}
            >
              <i className="bi bi-trash" />
            </Button>
          </Space>
        ),
      },
    ],
    [form, modal, remove, t],
  )

  const statCards = [
    {
      title: t('total_users'),
      value: stats.data?.totalUsers ?? '-',
      icon: 'bi-people',
      color: '#1677ff',
    },
    {
      title: t('active_users'),
      value: stats.data?.activeUsers ?? '-',
      icon: 'bi-person-check',
      color: '#52c41a',
    },
    {
      title: t('pending_users'),
      value: stats.data?.pendingUsers ?? '-',
      icon: 'bi-person-clock',
      color: '#faad14',
    },
    {
      title: t('user_types'),
      value: stats.data?.userTypes ?? '-',
      icon: 'bi-person-badge',
      color: '#13c2c2',
    },
  ]

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
        <Typography.Title level={3} style={{ margin: 0 }}>
          <i className="bi bi-people" style={{ marginRight: 8 }} />
          {t('users')}
        </Typography.Title>
        <Space wrap>
          <Button
            type="primary"
            icon={<i className="bi bi-plus-circle" />}
            onClick={() => {
              setEditing(null)
              setFile(null)
              setPreview(assetImageUrl('user.png'))
              form.resetFields()
              form.setFieldsValue({ user_type: types.data?.userTypes[0] })
              setOpen(true)
            }}
          >
            {t('add_user')}
          </Button>
          <Button
            icon={<i className="bi bi-envelope-plus" />}
            onClick={() => {
              setInviteEmails('')
              setInviteOpen(true)
            }}
          >
            {t('invite_users')}
          </Button>
        </Space>
      </Space>

      <Row gutter={16}>
        {statCards.map((card) => (
          <Col xs={24} sm={12} md={6} key={card.title} style={{ marginBottom: 16 }}>
            <Card styles={{ body: { background: card.color, color: '#fff' } }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <div>{card.title}</div>
                  <Typography.Title level={3} style={{ color: '#fff', margin: 0 }}>
                    {card.value}
                  </Typography.Title>
                </div>
                <i className={`bi ${card.icon}`} style={{ fontSize: 36, opacity: 0.85 }} />
              </div>
            </Card>
          </Col>
        ))}
      </Row>

      <Card
        title={
          <span>
            <i className="bi bi-funnel" style={{ marginRight: 8 }} />
            {t('filters')}
          </span>
        }
      >
        <Space wrap>
          <div>
            <div style={{ marginBottom: 4, fontSize: 14 }}>User Type</div>
            <Select
              style={{ width: 160 }}
              value={userType}
              onChange={setUserType}
              options={[
                { value: 'all', label: t('all_types') },
                ...(types.data?.userTypes.map((u) => ({ value: u, label: u })) ?? []),
              ]}
            />
          </div>
          <div>
            <div style={{ marginBottom: 4, fontSize: 14 }}>{t('status')}</div>
            <Select
              style={{ width: 160 }}
              value={status}
              onChange={setStatus}
              options={[
                { value: 'all', label: t('all_statuses') },
                { value: 'active', label: t('active') },
                { value: 'pending', label: t('pending') },
                { value: 'inactive', label: t('inactive') },
              ]}
            />
          </div>
          <div>
            <div style={{ marginBottom: 4, fontSize: 14 }}>{t('search')}</div>
            <Input
              style={{ width: 220 }}
              placeholder={t('search')}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              allowClear
            />
          </div>
          <Button
            type="primary"
            icon={<i className="bi bi-search" />}
            style={{ alignSelf: 'end' }}
            onClick={() => {
              setPage(1)
              setApplied({ userType, status, search })
            }}
          />
        </Space>
      </Card>

      <Card
        title={t('users_list')}
        extra={
          <Space wrap>
            <Button
              type="primary"
              ghost
              icon={<i className="bi bi-file-earmark-excel" />}
              onClick={() => {
                void downloadFile('/users/export?format=excel', 'users.xlsx').catch(
                  (error: unknown) => {
                    message.error(error instanceof ApiError ? error.message : String(error))
                  },
                )
              }}
            >
              {t('export_excel')}
            </Button>
            {selectedKeys.length > 0 ? (
              <Button
                danger
                icon={<i className="bi bi-trash" />}
                loading={bulkDelete.isPending}
                onClick={() => {
                  modal.confirm({
                    title: t('delete_selected'),
                    content: String(selectedKeys.length),
                    onOk: () => bulkDelete.mutateAsync(selectedKeys.map(Number)),
                  })
                }}
              >
                {t('delete_selected')} ({selectedKeys.length})
              </Button>
            ) : null}
          </Space>
        }
      >
        <Table
          rowKey="id"
          loading={users.isLoading}
          dataSource={users.data?.items ?? []}
          columns={columns}
          rowSelection={{
            selectedRowKeys: selectedKeys,
            onChange: setSelectedKeys,
          }}
          pagination={{
            current: page,
            pageSize,
            total: users.data?.total ?? 0,
            showSizeChanger: true,
            pageSizeOptions: [10, 20, 25, 30, 40, 50],
            onChange: (p, ps) => {
              setPage(p)
              setPageSize(ps)
            },
          }}
        />
      </Card>

      <Modal
        title={editing ? t('edit') : t('add_user')}
        open={open}
        onCancel={() => setOpen(false)}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        destroyOnClose
        width={640}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={(values) => {
            if (editing && values.password && values.password !== values.confirmPassword) {
              message.error(t('password_and_confirm_password_should_be_same'))
              return
            }
            save.mutate(values)
          }}
        >
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="name" label={t('name')} rules={[{ required: true }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="email"
                label={t('email')}
                rules={[{ required: true, type: 'email' }]}
              >
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="user_type" label={t('user_type')} rules={[{ required: true }]}>
                <Select
                  options={(types.data?.userTypes ?? []).map((u) => ({ value: u, label: u }))}
                />
              </Form.Item>
            </Col>
            {editing ? (
              <>
                <Col span={24}>
                  <Form.Item name="currentpassword" label={t('current_password')}>
                    <Input.Password />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item name="password" label={t('NewPassword')}>
                    <Input.Password />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item name="confirmPassword" label={t('ConfirmPassword')}>
                    <Input.Password />
                  </Form.Item>
                </Col>
              </>
            ) : (
              <Col span={24}>
                <Form.Item name="password" label={t('Password')} rules={[{ required: true }]}>
                  <Input.Password />
                </Form.Item>
              </Col>
            )}
            <Col span={24}>
              <Form.Item label={t('image_upload')}>
                <div style={{ marginBottom: 8 }}>
                  <img
                    src={preview}
                    alt=""
                    width={120}
                    style={{ borderRadius: 4, objectFit: 'cover' }}
                  />
                </div>
                <Upload
                  accept="image/*"
                  showUploadList={false}
                  beforeUpload={(uploadFile) => {
                    setFile(uploadFile)
                    setPreview(URL.createObjectURL(uploadFile))
                    return false
                  }}
                >
                  <Button icon={<i className="bi bi-upload" />}>{t('image_upload')}</Button>
                </Upload>
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>

      <Modal
        title={t('invite_people')}
        open={inviteOpen}
        onCancel={() => setInviteOpen(false)}
        onOk={() => {
          if (!inviteEmails.trim()) {
            message.error(t('email'))
            return
          }
          invite.mutate(inviteEmails.trim())
        }}
        confirmLoading={invite.isPending}
        okText={t('send')}
        destroyOnClose
      >
        <Typography.Paragraph type="secondary">
          {t('invite_emails_hint')}
        </Typography.Paragraph>
        <Input.TextArea
          rows={5}
          value={inviteEmails}
          onChange={(e) => setInviteEmails(e.target.value)}
          placeholder={t('email')}
        />
      </Modal>
    </Space>
  )
}
