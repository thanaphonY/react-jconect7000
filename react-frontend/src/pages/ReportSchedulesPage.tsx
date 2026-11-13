import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Button,
  Checkbox,
  Form,
  Input,
  InputNumber,
  Modal,
  Select,
  Space,
  Table,
  Tag,
  Typography,
} from 'antd'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { apiRequest } from '../api/client'
import { dayjs } from '../lib/dayjs'
import type { ReportSchedule, ReportTemplate } from '../features/reports/types'

type ScheduleForm = {
  templateId: number
  name: string
  scheduleType: 'daily' | 'weekly' | 'monthly'
  scheduleTime: string
  scheduleWeekday: number
  scheduleDay: number
  exportFormat: 'pdf' | 'excel' | 'csv'
  isActive: boolean
}

export function ReportSchedulesPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { message } = App.useApp()
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<ReportSchedule | null>(null)
  const [form] = Form.useForm<ScheduleForm>()
  const scheduleType = Form.useWatch('scheduleType', form)

  const schedules = useQuery({
    queryKey: ['report-schedules'],
    queryFn: () => apiRequest<ReportSchedule[]>('/report-schedules'),
  })
  const templates = useQuery({
    queryKey: ['report-templates'],
    queryFn: () => apiRequest<ReportTemplate[]>('/report-templates'),
  })

  const templateOptions = useMemo(
    () => (templates.data ?? []).map((tpl) => ({ value: tpl.id, label: tpl.name })),
    [templates.data],
  )

  const saveMutation = useMutation({
    mutationFn: async (values: ScheduleForm) => {
      const body = {
        templateId: values.templateId,
        name: values.name,
        scheduleType: values.scheduleType,
        scheduleTime: values.scheduleTime,
        scheduleWeekday: values.scheduleWeekday,
        scheduleDay: values.scheduleDay,
        exportFormat: values.exportFormat,
        isActive: values.isActive,
        timezone: 'Asia/Bangkok',
        recipients: [] as string[],
      }
      if (editing) {
        return apiRequest<ReportSchedule>(`/report-schedules/${editing.id}`, {
          method: 'PUT',
          body,
        })
      }
      return apiRequest<ReportSchedule>('/report-schedules', { method: 'POST', body })
    },
    onSuccess: async () => {
      message.success(t('saved'))
      setOpen(false)
      setEditing(null)
      form.resetFields()
      await queryClient.invalidateQueries({ queryKey: ['report-schedules'] })
    },
    onError: (err: Error) => message.error(err.message),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiRequest<{ ok: boolean }>(`/report-schedules/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('deleted'))
      await queryClient.invalidateQueries({ queryKey: ['report-schedules'] })
    },
    onError: (err: Error) => message.error(err.message),
  })

  const openCreate = () => {
    setEditing(null)
    form.setFieldsValue({
      templateId: undefined,
      name: '',
      scheduleType: 'daily',
      scheduleTime: '08:00',
      scheduleWeekday: 1,
      scheduleDay: 1,
      exportFormat: 'pdf',
      isActive: true,
    })
    setOpen(true)
  }

  const openEdit = (row: ReportSchedule) => {
    setEditing(row)
    form.setFieldsValue({
      templateId: row.templateId,
      name: row.name,
      scheduleType: (row.scheduleType as ScheduleForm['scheduleType']) || 'daily',
      scheduleTime: row.scheduleConfig?.time || '08:00',
      scheduleWeekday: row.scheduleConfig?.weekday ?? 1,
      scheduleDay: row.scheduleConfig?.day ?? 1,
      exportFormat: (row.exportFormat as ScheduleForm['exportFormat']) || 'pdf',
      isActive: row.isActive,
    })
    setOpen(true)
  }

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
        <Typography.Title level={3} style={{ margin: 0 }}>
          <i className="bi bi-clock" style={{ marginRight: 8 }} />
          {t('reports_schedules')}
        </Typography.Title>
        <Space wrap>
          <Button icon={<i className="bi bi-arrow-left" />} onClick={() => navigate('/reports')}>
            {t('back_to_templates')}
          </Button>
          <Button type="primary" icon={<i className="bi bi-plus-lg" />} onClick={openCreate}>
            {t('add_schedule')}
          </Button>
        </Space>
      </Space>
      <Typography.Title level={5} style={{ margin: 0 }}>
        <i className="bi bi-calendar3" style={{ marginRight: 8 }} />
        {t('report_schedules')}
      </Typography.Title>

      <Table
        rowKey="id"
        loading={schedules.isLoading}
        dataSource={schedules.data ?? []}
        locale={{ emptyText: t('no_schedules_yet') }}
        pagination={false}
        columns={[
          { title: t('template'), dataIndex: 'templateName', render: (v: string) => v || '-' },
          { title: t('schedule_name'), dataIndex: 'name' },
          {
            title: t('type'),
            dataIndex: 'scheduleType',
            render: (v: string) => t(v === 'weekly' ? 'weekly' : v === 'monthly' ? 'monthly' : 'daily'),
          },
          {
            title: t('time'),
            render: (_v, row) => row.scheduleConfig?.time || '08:00',
          },
          {
            title: t('format'),
            dataIndex: 'exportFormat',
            render: (v: string) => <Tag>{(v || '').toUpperCase()}</Tag>,
          },
          {
            title: t('next_run'),
            dataIndex: 'nextRunAt',
            render: (v: string | null) =>
              v ? dayjs(v).tz('Asia/Bangkok').format('DD/MM/YYYY HH:mm') : '-',
          },
          {
            title: t('status'),
            dataIndex: 'isActive',
            render: (active: boolean) =>
              active ? <Tag color="success">{t('active')}</Tag> : <Tag>{t('inactive')}</Tag>,
          },
          {
            title: t('actions'),
            width: 120,
            render: (_v, row) => (
              <Space>
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
                    if (window.confirm(t('delete_schedule_confirm', { name: row.name }))) {
                      deleteMutation.mutate(row.id)
                    }
                  }}
                />
              </Space>
            ),
          },
        ]}
      />

      <Modal
        title={editing ? t('edit_schedule_modal') : t('add_schedule_modal')}
        open={open}
        onCancel={() => setOpen(false)}
        onOk={() => form.submit()}
        confirmLoading={saveMutation.isPending}
        okText={t('save_schedule')}
        destroyOnHidden
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={(values) => saveMutation.mutate(values)}
        >
          <Form.Item
            name="templateId"
            label={t('template')}
            rules={[{ required: true, message: t('select_template') }]}
          >
            <Select
              options={templateOptions}
              placeholder={t('select_template')}
              disabled={Boolean(editing)}
            />
          </Form.Item>
          <Form.Item
            name="name"
            label={t('schedule_name')}
            rules={[{ required: true, message: t('schedule_name') }]}
          >
            <Input placeholder={t('schedule_name_placeholder')} />
          </Form.Item>
          <Space style={{ width: '100%' }} size="middle">
            <Form.Item name="scheduleType" label={t('type')} style={{ flex: 1 }}>
              <Select
                options={[
                  { value: 'daily', label: t('daily') },
                  { value: 'weekly', label: t('weekly') },
                  { value: 'monthly', label: t('monthly') },
                ]}
              />
            </Form.Item>
            <Form.Item name="scheduleTime" label={t('time')} style={{ flex: 1 }}>
              <Input type="time" />
            </Form.Item>
          </Space>
          {scheduleType === 'weekly' ? (
            <Form.Item name="scheduleWeekday" label={t('day_of_week')}>
              <Select
                options={[
                  { value: 1, label: t('monday') },
                  { value: 2, label: t('tuesday') },
                  { value: 3, label: t('wednesday') },
                  { value: 4, label: t('thursday') },
                  { value: 5, label: t('friday') },
                  { value: 6, label: t('saturday') },
                  { value: 7, label: t('sunday') },
                ]}
              />
            </Form.Item>
          ) : null}
          {scheduleType === 'monthly' ? (
            <Form.Item name="scheduleDay" label={t('day_of_month')}>
              <InputNumber min={1} max={28} style={{ width: '100%' }} />
            </Form.Item>
          ) : null}
          <Form.Item name="exportFormat" label={t('export_format')}>
            <Select
              options={[
                { value: 'pdf', label: 'PDF' },
                { value: 'excel', label: 'Excel' },
                { value: 'csv', label: 'CSV' },
              ]}
            />
          </Form.Item>
          <Form.Item name="isActive" valuePropName="checked">
            <Checkbox>{t('active')}</Checkbox>
          </Form.Item>
        </Form>
      </Modal>
    </Space>
  )
}
