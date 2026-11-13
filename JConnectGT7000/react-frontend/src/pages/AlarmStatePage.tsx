import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { App, Button, Card, Space, Table, Tag, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { apiRequest } from '../api/client'
import { dayjs } from '../lib/dayjs'

type ActiveAlarm = {
  deviceId: number | null
  deviceName: string
  metricKey: string
  metricLabel: string
  message: string
  severity: string
  lastTriggered: string | null
  acknowledged: boolean
  acknowledgedBy: string | null
  acknowledgedAt: string | null
}

function severityColor(severity: string): string {
  switch ((severity || '').toLowerCase()) {
    case 'critical':
      return 'red'
    case 'major':
    case 'high':
      return 'orange'
    case 'warning':
    case 'medium':
      return 'gold'
    case 'minor':
    case 'low':
      return 'blue'
    default:
      return 'default'
  }
}

export function AlarmStatePage() {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const queryClient = useQueryClient()

  const alarms = useQuery({
    queryKey: ['alarms-active'],
    queryFn: () => apiRequest<ActiveAlarm[]>('/alarms/active'),
    refetchInterval: 15_000,
  })

  const acknowledge = useMutation({
    mutationFn: (row: ActiveAlarm) =>
      apiRequest(`/alarms/active/${row.deviceId}/${encodeURIComponent(row.metricKey)}/acknowledge`, {
        method: 'POST',
        body: {},
      }),
    onSuccess: async () => {
      message.success(t('alarm_acknowledged_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['alarms-active'] })
    },
    onError: (err: Error) => message.error(err.message),
  })

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={4} style={{ margin: 0 }}>
          <i className="bi bi-exclamation-circle" style={{ marginRight: 8 }} />
          {t('active_alarms')}
        </Typography.Title>
        <Link to="/alarmlogs">{t('view_all_alarm_logs')}</Link>
      </Space>

      <Card>
        <Table
          rowKey={(row) => `${row.deviceId}-${row.metricKey}`}
          loading={alarms.isLoading}
          dataSource={alarms.data ?? []}
          pagination={false}
          columns={[
            { title: t('device_name'), dataIndex: 'deviceName' },
            { title: t('metric'), dataIndex: 'metricLabel' },
            { title: t('message'), dataIndex: 'message' },
            {
              title: t('severity'),
              dataIndex: 'severity',
              render: (value: string) => <Tag color={severityColor(value)}>{value}</Tag>,
            },
            {
              title: t('last_triggered'),
              dataIndex: 'lastTriggered',
              render: (value: string | null) =>
                value ? dayjs(value).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss') : '-',
            },
            {
              title: t('acknowledged'),
              render: (_value, row) =>
                row.acknowledged ? (
                  <span>
                    {t('acknowledged_by')} {row.acknowledgedBy ?? ''}{' '}
                    {row.acknowledgedAt
                      ? dayjs(row.acknowledgedAt).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss')
                      : ''}
                  </span>
                ) : (
                  <Button
                    size="small"
                    type="default"
                    icon={<i className="bi bi-check-circle" />}
                    loading={acknowledge.isPending}
                    onClick={() => acknowledge.mutate(row)}
                    disabled={row.deviceId === null}
                  >
                    {t('acknowledge')}
                  </Button>
                ),
            },
          ]}
        />
      </Card>
    </Space>
  )
}
