import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { App, Button, DatePicker, Form, Select, Space, Table, Tag, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { apiRequest, downloadFile } from '../api/client'
import { dayjs } from '../lib/dayjs'
import type { ReportTemplate } from '../features/reports/types'

type ReportFile = {
  id: number
  templateId: number | null
  templateName: string
  fileName: string
  exportFormat: string
  fileSize: number
  exportedAt: string | null
  scheduleId: number | null
}

type Page = {
  files: ReportFile[]
  total: number
}

type Filters = {
  templateId?: number
  format?: string
  dateFrom?: string
  dateTo?: string
}

function formatFileSizeKb(bytes: number): string {
  return `${(Number(bytes || 0) / 1024).toFixed(1)} KB`
}

export function ReportFilesPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { message } = App.useApp()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [filters, setFilters] = useState<Filters>({})
  const [form] = Form.useForm()

  const templates = useQuery({
    queryKey: ['report-templates'],
    queryFn: () => apiRequest<ReportTemplate[]>('/report-templates'),
  })

  const queryString = new URLSearchParams({
    page: String(page),
    pageSize: '15',
  })
  if (filters.templateId) queryString.set('templateId', String(filters.templateId))
  if (filters.format) queryString.set('format', filters.format)
  if (filters.dateFrom) queryString.set('dateFrom', filters.dateFrom)
  if (filters.dateTo) queryString.set('dateTo', filters.dateTo)

  const files = useQuery({
    queryKey: ['report-files', page, filters],
    queryFn: () => apiRequest<Page>(`/report-files?${queryString.toString()}`),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiRequest<{ ok: boolean }>(`/report-files/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('deleted'))
      await queryClient.invalidateQueries({ queryKey: ['report-files'] })
    },
    onError: (err: Error) => message.error(err.message),
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
        <Typography.Title level={3} style={{ margin: 0 }}>
          <i className="bi bi-folder2-open" style={{ marginRight: 8 }} />
          {t('report_files')}
        </Typography.Title>
        <Button onClick={() => navigate('/reports')}>{t('back_to_templates')}</Button>
      </Space>

      <Typography.Title level={5} style={{ margin: 0 }}>
        <i className="bi bi-download" style={{ marginRight: 8 }} />
        {t('export_files')}
      </Typography.Title>

      <Form
        form={form}
        layout="inline"
        onFinish={(values) => {
          setPage(1)
          setFilters({
            templateId: values.templateId,
            format: values.format,
            dateFrom: values.dateFrom ? values.dateFrom.format('YYYY-MM-DD') : undefined,
            dateTo: values.dateTo ? values.dateTo.format('YYYY-MM-DD') : undefined,
          })
        }}
      >
        <Form.Item name="templateId" label={t('report')}>
          <Select
            allowClear
            style={{ minWidth: 180 }}
            placeholder={t('all')}
            options={(templates.data ?? []).map((tpl) => ({ value: tpl.id, label: tpl.name }))}
          />
        </Form.Item>
        <Form.Item name="format" label={t('format')}>
          <Select
            allowClear
            style={{ minWidth: 120 }}
            placeholder={t('all')}
            options={[
              { value: 'pdf', label: 'PDF' },
              { value: 'excel', label: 'Excel' },
              { value: 'csv', label: 'CSV' },
            ]}
          />
        </Form.Item>
        <Form.Item name="dateFrom" label={t('from')}>
          <DatePicker />
        </Form.Item>
        <Form.Item name="dateTo" label={t('to')}>
          <DatePicker />
        </Form.Item>
        <Form.Item>
          <Button type="primary" htmlType="submit" icon={<i className="bi bi-search" />}>
            {t('filter')}
          </Button>
        </Form.Item>
      </Form>

      <Table
        rowKey="id"
        loading={files.isLoading}
        dataSource={files.data?.files ?? []}
        locale={{ emptyText: t('no_files_yet') }}
        pagination={{
          current: page,
          pageSize: 15,
          total: files.data?.total ?? 0,
          onChange: setPage,
        }}
        columns={[
          { title: t('report'), dataIndex: 'templateName' },
          { title: t('file_name'), dataIndex: 'fileName' },
          {
            title: t('format'),
            dataIndex: 'exportFormat',
            render: (value: string) => <Tag>{(value || '').toUpperCase()}</Tag>,
          },
          {
            title: t('size'),
            dataIndex: 'fileSize',
            render: (value: number) => formatFileSizeKb(value),
          },
          {
            title: t('exported_at'),
            dataIndex: 'exportedAt',
            render: (value: string | null) =>
              value ? dayjs(value).tz('Asia/Bangkok').format('DD/MM/YYYY HH:mm') : '',
          },
          {
            title: t('source'),
            dataIndex: 'scheduleId',
            render: (scheduleId: number | null) =>
              scheduleId ? t('scheduled') : t('manual'),
          },
          {
            title: t('action'),
            render: (_value, row) => (
              <Space>
                <Button
                  size="small"
                  icon={<i className="bi bi-download" />}
                  title={t('download')}
                  onClick={() => downloadFile(`/report-files/${row.id}`, row.fileName)}
                />
                <Button
                  size="small"
                  danger
                  icon={<i className="bi bi-trash" />}
                  title={t('delete')}
                  onClick={() => {
                    if (window.confirm(t('delete_file_confirm', { name: row.fileName }))) {
                      deleteMutation.mutate(row.id)
                    }
                  }}
                />
              </Space>
            ),
          },
        ]}
      />
    </Space>
  )
}
