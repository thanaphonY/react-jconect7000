import {LiveWidget} from '../LiveWidget'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { App, Breadcrumb, Button, Card, Select, Space, Spin, Table, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { apiRequest } from '../api/client'
import { dayjs } from '../lib/dayjs'
import type { LayoutItem, LayoutView } from '../features/dashboard/types'

type Alarm = {
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

function alarmBucket(severity: string): 'critical' | 'warning' | 'info' {
  const key = severity.trim().toLowerCase()
  if (key === 'critical' || key === 'major' || key === 'high') return 'critical'
  if (key === 'info' || key === 'minor' || key === 'low') return 'info'
  return 'warning'
}

function countSeverity(alarms: Alarm[], severity: 'critical' | 'warning' | 'info'): number {
  return alarms.filter((alarm) => alarmBucket(alarm.severity) === severity).length
}

function WidgetCard({item}: {item: LayoutItem;alarms: Alarm[]}){return <LiveWidget item={item}/>;}

function widgetLabel(item: LayoutItem): string {
  const zoneName = item.config.zone_name
  const label = item.config.label
  if ((item.widgetType === 'zone_overview' || item.type === 'zone_overview') && typeof zoneName === 'string' && zoneName) {
    return zoneName
  }
  if (typeof label === 'string' && label) return label
  return item.name || item.label || item.widgetType || ''
}

export function DashboardViewPage() {
  const { id } = useParams()
  const layoutId = id
  const { t } = useTranslation()
  const { message } = App.useApp()
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const view = useQuery({
    queryKey: ['dashboard-view', layoutId ?? 'home'],
    queryFn: () =>
      apiRequest<LayoutView>(layoutId ? `/home/${layoutId}` : '/home'),
  })

  const alarms = useQuery({
    queryKey: ['alarms-active'],
    queryFn: () => apiRequest<Alarm[]>('/alarms/active'),
  })
  const acknowledge = useMutation({
    mutationFn: (row: Alarm) =>
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
  const alarmRows = alarms.data ?? []

  if (view.isLoading) {
    return (
      <div style={{ display: 'grid', placeItems: 'center', minHeight: 240 }}>
        <Spin />
      </div>
    )
  }

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <Typography.Title level={4} style={{ margin: 0 }}>
            {t('dashboard')}
          </Typography.Title>
          <Breadcrumb
            items={[
              { title: <Link to="/home">{t('home')}</Link> },
              { title: <Link to="/dashboard">{t('dashboard')}</Link> },
              { title: view.data?.layout.name },
            ]}
          />
        </div>
        <Space>
          {view.data?.layout.id ? (
            <Button
              type="primary"
              icon={<i className="bi bi-plus-circle" />}
              onClick={() => navigate(`/dashboard/edit_layout/${view.data!.layout.id}`)}
            >
              {t('edit_layout')}
            </Button>
          ) : null}
          <Select
            style={{ minWidth: 180 }}
            value={view.data?.layout.id}
            options={(view.data?.layouts ?? []).map((layout) => ({
              value: layout.id,
              label: layout.name,
            }))}
            onChange={(nextId) => navigate(`/dashboard/view/${nextId}`)}
          />
        </Space>
      </Space>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(16, minmax(0,1fr))', gridAutoRows:40, gap:10 }}>
        {(view.data?.items ?? []).map((item) => (
          <Card
            key={item.id}
            aria-label={widgetLabel(item)}
            className="dashboard-widget"
            styles={{body:{padding:8,height:"100%",overflow:"auto"}}}
            style={{ gridColumn: `${(item.x??0)+1} / span ${Math.min(Math.max(item.w,1),16)}`,gridRow:`${(item.y??0)+1} / span ${Math.max(item.h??4,1)}`,border:item.config.show_border==='0'?'none':undefined,background:typeof item.config.bg_color==='string'&&/^#[0-9a-f]{6}$/i.test(item.config.bg_color)?item.config.bg_color:undefined }}
          >
            <WidgetCard item={item} alarms={alarmRows} />
          </Card>
        ))}
      </div>

      <Card
        title={
          <Space>
            <span>{t('active_alarms')}</span>
            <span>{alarmRows.length}</span>
          </Space>
        }
      >
        <Table
          rowKey={(row) => `${row.deviceId}-${row.metricKey}`}
          dataSource={alarmRows}
          pagination={false}
          columns={[
            { title: t('device_name'), dataIndex: 'deviceName' },
            { title: t('metric'), dataIndex: 'metricLabel' },
            { title: t('message'), dataIndex: 'message' },
            { title: t('severity'), dataIndex: 'severity' },
            {
              title: t('last_triggered'),
              dataIndex: 'lastTriggered',
              render: (value: string | null) =>
                value ? dayjs(value).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss') : '',
            },
            {
              title: t('acknowledged'),
              render: (_value, row) =>
                row.acknowledged
                  ? `${t('acknowledged_by')} ${row.acknowledgedBy ?? ''} at ${
                      row.acknowledgedAt
                        ? dayjs(row.acknowledgedAt).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss')
                        : ''
                    }`
                  : (
                    <Button
                      size="small"
                      icon={<i className="bi bi-check-circle" />}
                      loading={acknowledge.isPending}
                      disabled={row.deviceId === null}
                      onClick={() => acknowledge.mutate(row)}
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
