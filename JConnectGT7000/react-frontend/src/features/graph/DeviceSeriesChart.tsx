import * as echarts from 'echarts'
import { useEffect, useRef } from 'react'
import { dayjs } from '../../lib/dayjs'

export type SeriesPoint = {
  timestamp: string
  value: number | null
}

export function DeviceSeriesChart({ title, points }: { title: string; points: SeriesPoint[] }) {
  const host = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const node = host.current
    if (!node) return
    const chart = echarts.init(node)
    chart.setOption({
      title: { text: title, left: 8 },
      tooltip: { trigger: 'axis' },
      grid: { left: 48, right: 16, top: 48, bottom: 72 },
      xAxis: {
        type: 'category',
        data: points.map((point) => dayjs(point.timestamp).tz('Asia/Bangkok').format('MM-DD HH:mm')),
        axisLabel: { rotate: 45 },
      },
      yAxis: { type: 'value', scale: true },
      series: [{ type: 'line', name: title, data: points.map((point) => point.value), showSymbol: false }],
    })
    const onResize = () => chart.resize()
    window.addEventListener('resize', onResize)
    return () => {
      window.removeEventListener('resize', onResize)
      chart.dispose()
    }
  }, [points, title])

  return <div ref={host} style={{ width: '100%', height: 400 }} />
}
