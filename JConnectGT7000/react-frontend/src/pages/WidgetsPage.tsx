import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { App, Button, Space, Table, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { ApiError, apiRequest } from '../api/client'

type Widget = {
  id: number
  name: string
  widgetType: string
  config: Record<string, unknown>
}

function prettyConfig(config: unknown): string {
  try {
    if (typeof config === 'string') {
      const parsed = JSON.parse(config) as unknown
      return JSON.stringify(parsed, null, 2)
    }
    return JSON.stringify(config ?? {}, null, 2)
  } catch {
    return String(config ?? '{}')
  }
}

export function WidgetsPage() {
  const { t } = useTranslation()
  const { message, modal } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const widgets = useQuery({
    queryKey: ['widgets'],
    queryFn: () => apiRequest<Widget[]>('/widgets'),
  })

  const remove = useMutation({
    mutationFn: (id: number) => apiRequest(`/widgets/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['widgets'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : t('delete'))
    },
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {t('widgets')}
        </Typography.Title>
        <Button type="primary" onClick={() => navigate('/widgets/add')}>
          {t('add_widget')}
        </Button>
      </Space>
      <Table
        rowKey="id"
        loading={widgets.isLoading}
        dataSource={widgets.data ?? []}
        pagination={false}
        columns={[
          { title: t('name'), dataIndex: 'name' },
          { title: t('type'), dataIndex: 'widgetType' },
          {
            title: t('config'),
            dataIndex: 'config',
            render: (value: unknown) => (
              <pre style={{ margin: 0, fontSize: 12, whiteSpace: 'pre-wrap' }}>
                {prettyConfig(value)}
              </pre>
            ),
          },
          {
            title: t('actions'),
            width: 160,
            render: (_value, row: Widget) => (
              <Space>
                <Button
                  size="small"
                  style={{ background: '#faad14', borderColor: '#faad14', color: '#fff' }}
                  onClick={() => navigate(`/widgets/edit/${row.id}`)}
                >
                  {t('edit')}
                </Button>
                <Button
                  size="small"
                  danger
                  onClick={() => {
                    modal.confirm({
                      title: row.name,
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
    </Space>
  )
}
