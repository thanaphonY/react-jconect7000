import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { App, Button, Popconfirm, Space, Table, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { apiRequest } from '../api/client'
import { dayjs } from '../lib/dayjs'
import type { ReportTemplate } from '../features/reports/types'

export function ReportsPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { message } = App.useApp()
  const queryClient = useQueryClient()

  const templates = useQuery({
    queryKey: ['report-templates'],
    queryFn: () => apiRequest<ReportTemplate[]>('/report-templates'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiRequest<{ ok: boolean }>(`/report-templates/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('deleted'))
      await queryClient.invalidateQueries({ queryKey: ['report-templates'] })
    },
    onError: (err: Error) => message.error(err.message),
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {t('reports')}
        </Typography.Title>
        <Space wrap>
          <Button icon={<i className="bi bi-clock" />} onClick={() => navigate('/reports/schedules')}>
            {t('manage_schedules')}
          </Button>
          <Button
            icon={<i className="bi bi-folder2-open" />}
            onClick={() => navigate('/reports/files')}
          >
            {t('file_manager')}
          </Button>
          <Button
            type="primary"
            icon={<i className="bi bi-plus-lg" />}
            onClick={() => navigate('/reports/builder')}
          >
            {t('create_new_template')}
          </Button>
        </Space>
      </Space>
      <Table
        rowKey="id"
        loading={templates.isLoading}
        dataSource={templates.data ?? []}
        locale={{ emptyText: t('no_templates_yet') }}
        pagination={false}
        title={() => (
          <span>
            <i className="bi bi-file-earmark-text" style={{ marginRight: 8 }} />
            {t('reports_templates')}
          </span>
        )}
        columns={[
          { title: t('name'), dataIndex: 'name' },
          {
            title: t('description'),
            dataIndex: 'description',
            render: (value: string) => value || '-',
          },
          {
            title: t('updated_at'),
            dataIndex: 'updatedAt',
            render: (value: string | null) =>
              value ? dayjs(value).tz('Asia/Bangkok').format('DD/MM/YYYY HH:mm') : '-',
          },
          {
            title: t('actions'),
            width: 180,
            render: (_value, row) => (
              <Space>
                <Button
                  size="small"
                  icon={<i className="bi bi-play" />}
                  title={t('run_report')}
                  onClick={() => navigate(`/reports/run/${row.id}`)}
                />
                <Button
                  size="small"
                  icon={<i className="bi bi-pencil" />}
                  title={t('edit')}
                  onClick={() => navigate(`/reports/builder/${row.id}`)}
                />
                <Popconfirm
                  title={t('delete_template_confirm', { name: row.name })}
                  onConfirm={() => deleteMutation.mutate(row.id)}
                >
                  <Button size="small" danger icon={<i className="bi bi-trash" />} title={t('delete')} />
                </Popconfirm>
              </Space>
            ),
          },
        ]}
      />
    </Space>
  )
}
