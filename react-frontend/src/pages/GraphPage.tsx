import { useQuery } from '@tanstack/react-query'
import { Card, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { apiRequest } from '../api/client'
import { DeviceSeriesChart, type SeriesPoint } from '../features/graph'

type Series = {
  key: string
  points: SeriesPoint[]
}

export function GraphPage() {
  const { t } = useTranslation()
  const series = useQuery({
    queryKey: ['series', 'temperature'],
    queryFn: () => apiRequest<Series>('/series?key=temperature&limit=200'),
  })

  return (
    <Card>
      <Typography.Title level={3} style={{ marginTop: 0 }}>
        {t('graph')}
      </Typography.Title>
      <DeviceSeriesChart title={t('temperature')} points={series.data?.points ?? []} />
    </Card>
  )
}
