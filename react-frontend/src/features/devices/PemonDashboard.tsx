import { useQuery } from '@tanstack/react-query'
import { Alert, Card, Col, Progress, Row, Statistic, Typography } from 'antd'
import { useMemo } from 'react'
import { SeriesWidget } from '../../SeriesWidget'
import { dayjs } from '../../lib/dayjs'
import { useRealtimeMetrics } from '../../Realtime'
import { fetchDeviceLatestMetrics } from './api'

function PemonHistory({ deviceId, keys }: { deviceId: number; keys: string[] }) {
  const signature = JSON.stringify(keys)
  const config = useMemo(() => ({
    time_range: '24h',
    time_interval: '15m',
    items: (JSON.parse(signature) as string[]).map((key) => ({ device_id: deviceId, metric_key: key })),
  }), [deviceId, signature])
  return keys.length ? <SeriesWidget config={config} /> : null
}

export function PemonDashboard({ deviceId }: { deviceId: number }) {
  const latest = useQuery({
    queryKey: ['device-metrics-latest', deviceId],
    queryFn: () => fetchDeviceLatestMetrics(deviceId),
    refetchInterval: 5000,
  })
  const readings = latest.data?.latest ?? []
  useRealtimeMetrics(deviceId, readings.map(({ key }) => key))
  const values = (pattern: RegExp) =>
    readings.flatMap(({ key, value }) => value != null && pattern.test(key) ? [{ key, value }] : [])
  const dc = values(/^dc\.string\d+\.voltage_v$/)
  const ac = values(/^ac\.sys\d+\.line\d+\.voltage_v$/)
  const lastUpdate = readings.reduce((latest, row) => Math.max(latest, Date.parse(row.timestamp ?? '') || 0), 0)
  const contacts = readings.filter(({ key }) => /^io\.sys\d+\.(in|out)\d+$/.test(key))
  const environment = readings.filter(({ key, value }) =>
    value != null && /^io\.sys\d+\.(temp\d+_c|hum\d+_pct)$/.test(key),
  )
  const temp = readings.find(({ key }) => key === 'io.sys1.temp1_c')?.value
  const humidity = readings.find(({ key }) => key === 'io.sys1.hum1_pct')?.value
  const average = (items: typeof dc) =>
    items.length ? items.reduce((sum, item) => sum + item.value, 0) / items.length : undefined

  if (latest.isPending) return <Typography.Text>กำลังโหลดค่า PEMON…</Typography.Text>
  if (latest.isError) return <Alert type="error" message="โหลดค่า PEMON ไม่สำเร็จ" />

  if (!readings.length) return <Alert type="info" message="ยังไม่มีค่าที่อ่านจาก PEMON" />

  return (
    <>
      <Typography.Paragraph type="secondary">
        Last update {lastUpdate ? dayjs(lastUpdate).tz('Asia/Bangkok').format('DD/MM/YYYY HH:mm:ss') : '—'}
      </Typography.Paragraph>
      <Row gutter={[12, 12]}>
        {[
          { title: 'DC String Avg', value: average(dc), unit: 'V', precision: 1 },
          { title: 'AC Line Avg', value: average(ac), unit: 'V', precision: 0 },
          { title: 'Temperature', value: temp, unit: '°C', precision: 1 },
          { title: 'Humidity', value: humidity, unit: '%', precision: 0 },
        ].map(({ title, value, unit, precision }) => (
          <Col key={title} xs={12} lg={6}>
            <Card size="small">
              <Statistic title={title} value={value ?? '—'} suffix={value == null ? undefined : unit} precision={precision} />
            </Card>
          </Col>
        ))}
      </Row>
      <Row gutter={[12, 12]} style={{ marginTop: 12 }}>
        {[
          { title: 'DC · String voltage', items: dc, max: 60 },
          { title: 'AC · Line voltage', items: ac, max: 260 },
        ].map(({ title, items, max }) => (
          <Col key={title} xs={24} md={12}>
            <Card title={title} size="small">
              {items.map((item) => (
                <div key={item.key}>
                  {item.key} · {item.value} V
                  <Progress percent={Math.max(0, Math.min(100, item.value / Math.max(max, ...items.map(({ value }) => value)) * 100))} showInfo={false} />
                </div>
              ))}
              <PemonHistory deviceId={deviceId} keys={items.map(({ key }) => key)} />
            </Card>
          </Col>
        ))}
        <Col xs={24}>
          <Card title="IO · Environment & dry contacts" size="small">
            {contacts.map(({ key, value, valueBool, valueText }) => (
              <Typography.Paragraph key={key}>
                {key} · {String(valueBool ?? value ?? valueText ?? '—')}
              </Typography.Paragraph>
            ))}
            <PemonHistory deviceId={deviceId} keys={environment.map(({ key }) => key)} />
            {!contacts.length && !environment.length && <Typography.Text type="secondary">ยังไม่มีข้อมูล IO</Typography.Text>}
          </Card>
        </Col>
      </Row>
    </>
  )
}
