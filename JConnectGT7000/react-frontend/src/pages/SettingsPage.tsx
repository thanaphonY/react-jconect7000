import {EmailTemplatesPanel} from './SystemPages'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Button,
  Checkbox,
  Form,
  Input,
  Radio,
  Select,
  Space,
  Table,
  Tabs,
  Typography,
  Upload,
} from 'antd'
import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { ApiError, apiFormRequest, apiRequest } from '../api/client'
import { assetImageUrl } from '../lib/assets'
import { TemplatesPage } from './TemplatesPage'

type SettingsMap = Record<string, string>

type PermissionMethods = Record<string, string>
type PermissionModules = Record<string, PermissionMethods>
type Role = {
  id: number
  userType: string
  data: PermissionModules | null
}

const PERM_KEYS = [
  'own_create',
  'own_read',
  'own_update',
  'own_delete',
  'all_read',
  'all_update',
  'all_delete',
] as const

function flagOn(value: unknown): boolean {
  return value === '1' || value === 1 || value === true
}

function GeneralTab({ settings }: { settings: SettingsMap | undefined }) {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const queryClient = useQueryClient()
  const [form] = Form.useForm()
  const [logoFile, setLogoFile] = useState<File | null>(null)
  const [logoPreview, setLogoPreview] = useState(assetImageUrl(settings?.logo || 'logo.png', 'logo.png'))

  const types = useQuery({
    queryKey: ['users-types'],
    queryFn: () => apiRequest<{ userTypes: string[] }>('/users/types'),
  })

  useEffect(() => {
    if (!settings) return
    let userTypes: string[] = []
    try {
      const parsed = JSON.parse(settings.user_type || '[]') as unknown
      if (Array.isArray(parsed)) userTypes = parsed.map(String)
    } catch {
      userTypes = []
    }
    form.setFieldsValue({
      website: settings.website,
      register_allowed: settings.register_allowed === '1',
      admin_approval: settings.admin_approval === '1',
      user_type: userTypes,
    })
    setLogoPreview(assetImageUrl(settings.logo || 'logo.png', 'logo.png'))
    setLogoFile(null)
  }, [settings, form])

  const save = useMutation({
    mutationFn: async (values: {
      website: string
      register_allowed: boolean
      admin_approval: boolean
      user_type: string[]
    }) => {
      const fd = new FormData()
      fd.append('website', values.website)
      fd.append('register_allowed', values.register_allowed ? '1' : '0')
      fd.append('admin_approval', values.admin_approval ? '1' : '0')
      fd.append('user_type', JSON.stringify(values.user_type || []))
      fd.append('fileOldlogo', settings?.logo || '')
      if (logoFile) fd.append('logo', logoFile)
      return apiFormRequest<SettingsMap>('/settings/general', fd, { method: 'PUT' })
    },
    onSuccess: async () => {
      message.success(t('your_data_updated_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['settings'] })
      await queryClient.invalidateQueries({ queryKey: ['branding'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : String(error))
    },
  })

  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={(values) => save.mutate(values)}
      style={{ maxWidth: 720 }}
    >
      <Typography.Title level={5}>{t('generalSetting')}</Typography.Title>
      <Form.Item name="website" label={`${t('title')} *`} rules={[{ required: true }]}>
        <Input />
      </Form.Item>
      <Form.Item label={t('logo')}>
        <div style={{ marginBottom: 8 }}>
          <img src={logoPreview} alt="logo" style={{ maxHeight: 100, cursor: 'pointer' }} />
        </div>
        <Upload
          accept="image/*"
          showUploadList={false}
          beforeUpload={(file) => {
            setLogoFile(file)
            setLogoPreview(URL.createObjectURL(file))
            return false
          }}
        >
          <Button icon={<i className="bi bi-upload" />}>{t('logo')}</Button>
        </Upload>
      </Form.Item>
      <Typography.Title level={5}>{t('registration_setting')}</Typography.Title>
      <Form.Item name="register_allowed" valuePropName="checked">
        <Checkbox>{t('allow_signup')}</Checkbox>
      </Form.Item>
      <Form.Item name="admin_approval" valuePropName="checked">
        <Checkbox>{t('admin_approval')}</Checkbox>
      </Form.Item>
      <Form.Item
        name="user_type"
        label={`${t('default_user_role')} *`}
        rules={[{ required: true }]}
      >
        <Select
          mode="multiple"
          options={(types.data?.userTypes ?? []).map((u) => ({ value: u, label: u }))}
        />
      </Form.Item>
      <Button type="primary" htmlType="submit" loading={save.isPending}>
        {t('save')}
      </Button>
    </Form>
  )
}

function EmailTab({ settings }: { settings: SettingsMap | undefined }) {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const queryClient = useQueryClient()
  const [form] = Form.useForm()
  const mailSetting = Form.useWatch('mail_setting', form)

  useEffect(() => {
    if (!settings) return
    form.setFieldsValue({
      mail_setting: settings.mail_setting || 'php_mailer',
      company_name: settings.company_name || '',
      SMTP_EMAIL: settings.SMTP_EMAIL || '',
      HOST: settings.HOST || '',
      PORT: settings.PORT || '',
      SMTP_SECURE: settings.SMTP_SECURE || '',
      SMTP_PASSWORD: settings.SMTP_PASSWORD || '',
      EMAIL: settings.EMAIL || '',
    })
  }, [settings, form])

  const save = useMutation({
    mutationFn: async (values: Record<string, string>) => {
      const fd = new FormData()
      for (const [key, value] of Object.entries(values)) {
        fd.append(key, value ?? '')
      }
      return apiFormRequest<SettingsMap>('/settings/email', fd, { method: 'PUT' })
    },
    onSuccess: async () => {
      message.success(t('your_data_updated_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['settings'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : String(error))
    },
  })

  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={(values) => save.mutate(values)}
      style={{ maxWidth: 720 }}
    >
      <Typography.Title level={5}>{t('emailServerSetting')}</Typography.Title>
      <Form.Item name="mail_setting" label={t('mail_setting')}>
        <Radio.Group>
          <Radio value="php_mailer">{t('smtp')}</Radio>
          <Radio value="simple_mail">{t('server_default')}</Radio>
        </Radio.Group>
      </Form.Item>
      <Form.Item name="company_name" label={t('company_name')}>
        <Input />
      </Form.Item>
      {mailSetting === 'php_mailer' ? (
        <>
          <Form.Item name="SMTP_EMAIL" label={t('smtp_email')}>
            <Input type="email" />
          </Form.Item>
          <Form.Item name="HOST" label={t('smtp_host')}>
            <Input />
          </Form.Item>
          <Form.Item name="PORT" label={t('smtp_port')}>
            <Input />
          </Form.Item>
          <Form.Item name="SMTP_SECURE" label={t('smtp_secure')}>
            <Input />
          </Form.Item>
          <Form.Item name="SMTP_PASSWORD" label={t('smtp_password')}>
            <Input.Password />
          </Form.Item>
        </>
      ) : (
        <Form.Item name="EMAIL" label={t('email')}>
          <Input type="email" />
        </Form.Item>
      )}
      <Button type="primary" htmlType="submit" loading={save.isPending}>
        {t('save')}
      </Button>
    </Form>
  )
}

function PermissionTab() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const queryClient = useQueryClient()
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [draft, setDraft] = useState<Record<number, PermissionModules>>({})
  const [roleNames, setRoleNames] = useState<Record<number, string>>({})
  const [newRoles, setNewRoles] = useState<string[]>([])
  const [removed, setRemoved] = useState<number[]>([])

  const permissions = useQuery({
    queryKey: ['settings-permissions'],
    queryFn: () => apiRequest<{ roles: Role[] }>('/settings/permissions'),
  })

  useEffect(() => {
    if (!permissions.data?.roles.length) return
    const names: Record<number, string> = {}
    const nextDraft: Record<number, PermissionModules> = {}
    for (const role of permissions.data.roles) {
      names[role.id] = role.userType
      nextDraft[role.id] = structuredClone(role.data || {})
    }
    setRoleNames(names)
    setDraft(nextDraft)
    setSelectedId((prev) => prev ?? permissions.data.roles[0]?.id ?? null)
    setNewRoles([])
    setRemoved([])
  }, [permissions.data])

  const visibleRoles = useMemo(
    () => (permissions.data?.roles ?? []).filter((r) => !removed.includes(r.id)),
    [permissions.data, removed],
  )

  const selected = visibleRoles.find((r) => r.id === selectedId) ?? visibleRoles[0]
  const modules = selected ? Object.keys(draft[selected.id] || {}) : []

  const savePermissions = useMutation({
    mutationFn: async () => {
      const data: Record<string, PermissionModules> = {}
      for (const role of visibleRoles) {
        const key = (roleNames[role.id] || role.userType).replace(/ /g, '_SPACE_')
        const modulesMap = draft[role.id] || {}
        const out: PermissionModules = {}
        for (const [mod, methods] of Object.entries(modulesMap)) {
          const modKey = mod.replace(/ /g, '_SPACE_')
          out[modKey] = {}
          for (const pk of PERM_KEYS) {
            if (flagOn(methods[pk])) out[modKey][pk] = '1'
          }
        }
        data[key] = out
      }
      return apiRequest('/settings/permissions', { method: 'PUT', body: { data } })
    },
    onSuccess: async () => {
      message.success(t('your_data_updated_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['settings-permissions'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : String(error))
    },
  })

  const saveRoles = useMutation({
    mutationFn: async () => {
      const user_type_name: Record<string, string> = {}
      for (const role of visibleRoles) {
        user_type_name[String(role.id)] = roleNames[role.id] || role.userType
      }
      return apiRequest('/settings/roles', {
        method: 'PUT',
        body: {
          user_type_name,
          new_user_name: newRoles.filter(Boolean),
          rm_user_type: removed,
        },
      })
    },
    onSuccess: async () => {
      message.success(t('your_data_updated_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['settings-permissions'] })
      await queryClient.invalidateQueries({ queryKey: ['users-types'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : String(error))
    },
  })

  function toggle(moduleName: string, key: string, checked: boolean) {
    if (!selected) return
    setDraft((prev) => {
      const roleDraft = { ...(prev[selected.id] || {}) }
      const methods = { ...(roleDraft[moduleName] || {}) }
      methods[key] = checked ? '1' : '0'
      roleDraft[moduleName] = methods
      return { ...prev, [selected.id]: roleDraft }
    })
  }

  return (
    <Space align="start" size={24} style={{ width: '100%' }} wrap>
      <div style={{ minWidth: 260 }}>
        <Space style={{ width: '100%', justifyContent: 'space-between', marginBottom: 8 }}>
          <Typography.Title level={5} style={{ margin: 0 }}>
            {t('user_role')}
          </Typography.Title>
          <Button
            size="small"
            type="primary"
            icon={<i className="bi bi-plus-circle-fill" />}
            onClick={() => setNewRoles((prev) => [...prev, ''])}
          />
        </Space>
        {visibleRoles.map((role) => (
          <div
            key={role.id}
            style={{
              display: 'flex',
              gap: 8,
              alignItems: 'center',
              marginBottom: 8,
              padding: 8,
              background: selected?.id === role.id ? 'rgba(22,119,255,0.08)' : undefined,
              cursor: 'pointer',
            }}
            onClick={() => setSelectedId(role.id)}
          >
            <Input
              value={roleNames[role.id] ?? role.userType}
              onChange={(e) =>
                setRoleNames((prev) => ({ ...prev, [role.id]: e.target.value }))
              }
              onClick={(e) => e.stopPropagation()}
            />
            <Button
              size="small"
              danger
              icon={<i className="bi bi-x-circle-fill" />}
              onClick={(e) => {
                e.stopPropagation()
                setRemoved((prev) => [...prev, role.id])
                if (selectedId === role.id) setSelectedId(null)
              }}
            />
          </div>
        ))}
        {newRoles.map((name, index) => (
          <div key={`new-${index}`} style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
            <Input
              placeholder={t('enter_user_roles')}
              value={name}
              onChange={(e) =>
                setNewRoles((prev) => prev.map((v, i) => (i === index ? e.target.value : v)))
              }
            />
            <Button
              size="small"
              danger
              onClick={() => setNewRoles((prev) => prev.filter((_, i) => i !== index))}
            >
              <i className="bi bi-x-circle-fill" />
            </Button>
          </div>
        ))}
        <Button type="primary" size="small" loading={saveRoles.isPending} onClick={() => saveRoles.mutate()}>
          {t('save_role')}
        </Button>
      </div>

      <div style={{ flex: 1, minWidth: 480 }}>
        <Typography.Title level={5}>
          {t('permissions_for')}: {selected ? roleNames[selected.id] || selected.userType : ''}
        </Typography.Title>
        <Table
          size="small"
          pagination={false}
          rowKey={(row) => row.module}
          dataSource={modules.map((moduleName) => ({ module: moduleName }))}
          columns={[
            {
              title: t('modules'),
              dataIndex: 'module',
              render: (mod: string) => mod.replace(/_/g, ' '),
            },
            {
              title: t('create'),
              render: (_, row) => (
                <Space direction="vertical" size={0}>
                  <Checkbox
                    checked={flagOn(draft[selected!.id]?.[row.module]?.own_create)}
                    onChange={(e) => toggle(row.module, 'own_create', e.target.checked)}
                  >
                    {t('own_entries')}
                  </Checkbox>
                </Space>
              ),
            },
            {
              title: t('read'),
              render: (_, row) => (
                <Space direction="vertical" size={0}>
                  <Checkbox
                    checked={flagOn(draft[selected!.id]?.[row.module]?.own_read)}
                    onChange={(e) => toggle(row.module, 'own_read', e.target.checked)}
                  >
                    {t('own_entries')}
                  </Checkbox>
                  <Checkbox
                    checked={flagOn(draft[selected!.id]?.[row.module]?.all_read)}
                    onChange={(e) => toggle(row.module, 'all_read', e.target.checked)}
                  >
                    {t('all_entries')}
                  </Checkbox>
                </Space>
              ),
            },
            {
              title: t('update'),
              render: (_, row) => (
                <Space direction="vertical" size={0}>
                  <Checkbox
                    checked={flagOn(draft[selected!.id]?.[row.module]?.own_update)}
                    onChange={(e) => toggle(row.module, 'own_update', e.target.checked)}
                  >
                    {t('own_entries')}
                  </Checkbox>
                  <Checkbox
                    checked={flagOn(draft[selected!.id]?.[row.module]?.all_update)}
                    onChange={(e) => toggle(row.module, 'all_update', e.target.checked)}
                  >
                    {t('all_entries')}
                  </Checkbox>
                </Space>
              ),
            },
            {
              title: t('delete'),
              render: (_, row) => (
                <Space direction="vertical" size={0}>
                  <Checkbox
                    checked={flagOn(draft[selected!.id]?.[row.module]?.own_delete)}
                    onChange={(e) => toggle(row.module, 'own_delete', e.target.checked)}
                  >
                    {t('own_entries')}
                  </Checkbox>
                  <Checkbox
                    checked={flagOn(draft[selected!.id]?.[row.module]?.all_delete)}
                    onChange={(e) => toggle(row.module, 'all_delete', e.target.checked)}
                  >
                    {t('all_entries')}
                  </Checkbox>
                </Space>
              ),
            },
          ]}
        />
        <Button
          type="primary"
          style={{ marginTop: 12 }}
          loading={savePermissions.isPending}
          onClick={() => savePermissions.mutate()}
          disabled={!selected}
        >
          {t('save_permission')}
        </Button>
      </div>
    </Space>
  )
}

export function SettingsPage() {
  const { t } = useTranslation()
  const settings = useQuery({
    queryKey: ['settings'],
    queryFn: () => apiRequest<SettingsMap>('/settings'),
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Typography.Title level={3} style={{ margin: 0 }}>
        {t('settings')}
      </Typography.Title>
      <Tabs
        items={[
          {key:'backup',label:<Link to='/config_backup'>Backup / Restore</Link>},
          {key:'retention',label:<Link to='/retention'>Data retention</Link>},
          {
            key: 'general',
            label: (
              <span>
                <i className="fa fa-cogs" style={{ marginRight: 6 }} />
                {t('general')}
              </span>
            ),
            children: <GeneralTab settings={settings.data} />,
          },
          {
            key: 'email',
            label: (
              <span>
                <i className="fa fa-envelope" style={{ marginRight: 6 }} />
                {t('email')}
              </span>
            ),
            children: <EmailTab settings={settings.data} />,
          },
          {
            key: 'permission',
            label: (
              <span>
                <i className="fa fa-indent" style={{ marginRight: 6 }} />
                {t('permission')}
              </span>
            ),
            children: <PermissionTab />,
          },
          {
            key: 'templates',
            label: (
              <span>
                <i className="fa fa-puzzle-piece" style={{ marginRight: 6 }} />
                {t('templates')}
              </span>
            ),
            children: <EmailTemplatesPanel/>,

          },
        ]}
      />
    </Space>
  )
}
