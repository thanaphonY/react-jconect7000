import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { App, Button, Space, Table, Tag, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { ApiError } from '../api/client'
import { deleteTemplate, fetchTemplates } from '../features/devicetemplate/api'
import type { TemplateSummary } from '../features/devicetemplate/types'

export function TemplatesPage() {
  const { t } = useTranslation()
  const { message, modal } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const templates = useQuery({
    queryKey: ['device-templates'],
    queryFn: fetchTemplates,
  })

  const remove = useMutation({
    mutationFn: (id: number) => deleteTemplate(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['device-templates'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : t('delete'))
    },
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {t('device_template')}
        </Typography.Title>
        <Button type="primary" onClick={() => navigate('/devicetemplate/new')}>
          {t('add_template')}
        </Button>
      </Space>
      <Table<TemplateSummary>
        rowKey="id"
        loading={templates.isLoading}
        dataSource={templates.data ?? []}
        pagination={false}
        columns={[
          {
            title: '#',
            width: 70,
            render: (_value, _row, index) => index + 1,
          },
          { title: t('name'), dataIndex: 'name' },
          { title: t('protocol'), dataIndex: 'protocol' },
          {
            title: t('active'),
            dataIndex: 'isActive',
            render: (value: boolean) =>
              value ? (
                <Tag color="success">{t('yes')}</Tag>
              ) : (
                <Tag color="default">{t('no')}</Tag>
              ),
          },
          {
            title: t('actions'),
            width: 120,
            render: (_value, row) => (
              <Space size={4}>
                <Button
                  size="small"
                  style={{ background: '#faad14', borderColor: '#faad14', color: '#fff' }}
                  title={t('edit')}
                  onClick={() => navigate(`/devicetemplate/${row.id}`)}
                >
                  <i className="bi bi-pencil-square" />
                </Button>
                <Button
                  size="small"
                  danger
                  type="primary"
                  title={t('delete')}
                  onClick={() => {
                    modal.confirm({
                      title: row.name,
                      onOk: () => remove.mutateAsync(row.id),
                    })
                  }}
                >
                  <i className="bi bi-trash" />
                </Button>
              </Space>
            ),
          },
        ]}
      />
    </Space>
  )
}
