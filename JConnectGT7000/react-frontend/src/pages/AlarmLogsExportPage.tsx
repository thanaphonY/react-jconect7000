import { useQuery } from '@tanstack/react-query'
import { Button, Card, Col, Row, Space, Table, Tag, Typography } from 'antd'
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router'
import { apiRequest, downloadFile, openAuthenticatedBlob } from '../api/client'
import { dayjs } from '../lib/dayjs'

type AlarmLog = {
  id: number
  createdAt: string | null
  deviceName: string
  metricKey: string
  severity: string
  message: string
  status: string
}

type Preview = {
  logs: AlarmLog[]
  filters: Record<string, string | null | undefined>
  stats: {
    totalRecords: number
    activeAlarms: number
    severityLevels: number
    devices: number
  }
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

/** Match PHP export_preview: ucfirst(str_replace('_', ' ', $key)) plus camelCase. */
function humanizeFilterKey(key: string): string {
  const spaced = key
    .replace(/_/g, ' ')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .toLowerCase()
  return spaced.charAt(0).toUpperCase() + spaced.slice(1)
}

export function AlarmLogsExportPage() {
  const { t } = useTranslation()
  const [params] = useSearchParams()
  const query = useMemo(() => params.toString(), [params])

  const preview = useQuery({
    queryKey: ['alarm-logs-export-preview', query],
    queryFn: () => apiRequest<Preview>(`/alarm-logs/export-preview?${query}`),
  })

  const download = async (format: string, downloadFlag = false) => {
    const qs = new URLSearchParams(query)
    qs.set('format', format)
    if (downloadFlag) qs.set('download', '1')
    const stamp = dayjs().tz('Asia/Bangkok').format('YYYY-MM-DD_HH-mm-ss')
    const ext = format === 'excel' ? 'xlsx' : format === 'pdf' ? 'pdf' : 'csv'
    await downloadFile(`/alarm-logs/export?${qs.toString()}`, `alarm_logs_${stamp}.${ext}`)
  }

  const openPdf = () => {
    const qs = new URLSearchParams(query)
    qs.set('format', 'pdf')
    void openAuthenticatedBlob(`/alarm-logs/export?${qs.toString()}`)
  }

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={4} style={{ margin: 0 }}>
          <i className="bi bi-file-earmark-text" style={{ marginRight: 8 }} />
          {t('export_preview')} - {t('alarm_logs')}
        </Typography.Title>
        <Link to="/alarmlogs">
          <Button icon={<i className="bi bi-arrow-left" />}>{t('back_to_alarm_logs')}</Button>
        </Link>
      </Space>

      <Row gutter={16}>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#0d6efd', color: '#fff' }}>
            <div>{t('total_records')}</div>
            <Typography.Title level={3} style={{ color: '#fff', margin: 0 }}>
              {preview.data?.stats.totalRecords ?? '-'}
            </Typography.Title>
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#dc3545', color: '#fff' }}>
            <div>{t('active_alarms')}</div>
            <Typography.Title level={3} style={{ color: '#fff', margin: 0 }}>
              {preview.data?.stats.activeAlarms ?? '-'}
            </Typography.Title>
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#ffc107' }}>
            <div>{t('severity_levels')}</div>
            <Typography.Title level={3} style={{ margin: 0 }}>
              {preview.data?.stats.severityLevels ?? '-'}
            </Typography.Title>
          </Card>
        </Col>
        <Col xs={24} sm={12} md={6}>
          <Card style={{ background: '#0dcaf0' }}>
            <div>{t('devices')}</div>
            <Typography.Title level={3} style={{ margin: 0 }}>
              {preview.data?.stats.devices ?? '-'}
            </Typography.Title>
          </Card>
        </Col>
      </Row>

      <Card title={<span><i className="bi bi-funnel" /> {t('applied_filters')}</span>}>
        {preview.data?.filters ? (
          <Space wrap>
            {Object.entries(preview.data.filters)
              .filter(([, v]) => v && v !== 'all')
              .map(([k, v]) => (
                <Tag key={k} color="blue">
                  <i className="bi bi-tag-fill" /> {humanizeFilterKey(k)}: {String(v)}
                </Tag>
              ))}
          </Space>
        ) : (
          <Typography.Text type="secondary">{t('no_filters_applied')}</Typography.Text>
        )}
      </Card>

      <Card title={<span><i className="bi bi-download" /> {t('export_options')}</span>}>
        <Space wrap>
          <Button type="primary" style={{ background: '#198754' }} icon={<i className="bi bi-file-earmark-csv" />} onClick={() => void download('csv')}>
            {t('export_csv')}
          </Button>
          <Button type="primary" icon={<i className="bi bi-file-earmark-excel" />} onClick={() => void download('excel')}>
            {t('export_excel')}
          </Button>
          <Button danger icon={<i className="bi bi-eye" />} onClick={openPdf}>
            {t('preview_pdf')}
          </Button>
          <Button danger ghost icon={<i className="bi bi-download" />} onClick={() => void download('pdf', true)}>
            {t('download_pdf')}
          </Button>
        </Space>
      </Card>

      <Card title={<span><i className="bi bi-table" /> {t('data_preview')}</span>}>
        <Table
          rowKey="id"
          loading={preview.isLoading}
          dataSource={preview.data?.logs ?? []}
          pagination={{ pageSize: 50 }}
          columns={[
            {
              title: t('date_time'),
              dataIndex: 'createdAt',
              render: (value: string | null) =>
                value ? (
                  <div>
                    <div>{dayjs(value).tz('Asia/Bangkok').format('DD/MM/YYYY')}</div>
                    <Typography.Text type="secondary">
                      {dayjs(value).tz('Asia/Bangkok').format('HH:mm:ss')}
                    </Typography.Text>
                  </div>
                ) : (
                  '-'
                ),
            },
            { title: t('device'), dataIndex: 'deviceName' },
            {
              title: t('metric'),
              dataIndex: 'metricKey',
              render: (v: string) => <code>{v}</code>,
            },
            {
              title: t('severity'),
              dataIndex: 'severity',
              render: (v: string) => <Tag color={severityColor(v)}>{v}</Tag>,
            },
            { title: t('message'), dataIndex: 'message' },
          ]}
        />
      </Card>
    </Space>
  )
}
