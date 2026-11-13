import { useQuery } from '@tanstack/react-query'
import { Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { apiRequest } from '../api/client'
import { dayjs } from '../lib/dayjs'

type EventLog = {
  id: number
  createdAt: string | null
  userName: string
  actionType: string
  entityType: string
  message: string
}

type Page = {
  logs: EventLog[]
  total: number
}

export function EventLogsPage() {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const logs = useQuery({
    queryKey: ['event-logs', page],
    queryFn: () => apiRequest<Page>(`/event-logs?page=${page}&pageSize=10`),
  })

  return (
    <>
      <Typography.Title level={3}>{t('auditlogs')}</Typography.Title>
      <Table
        rowKey="id"
        loading={logs.isLoading}
        dataSource={logs.data?.logs ?? []}
        pagination={{
          current: page,
          pageSize: 10,
          total: logs.data?.total ?? 0,
          onChange: setPage,
        }}
        columns={[
          {
            title: t('date_time'),
            dataIndex: 'createdAt',
            render: (value: string | null) => (value ? dayjs(value).tz('Asia/Bangkok').format('YYYY-MM-DD HH:mm:ss') : ''),
          },
          { title: t('users'), dataIndex: 'userName' },
          {
            title: t('action'),
            dataIndex: 'actionType',
            render: (value: string) => (value ? value.charAt(0).toUpperCase() + value.slice(1) : ''),
          },
          {
            title: t('section'),
            dataIndex: 'entityType',
            render: (value: string) => (value ? value.charAt(0).toUpperCase() + value.slice(1) : ''),
          },
          { title: t('log_message'), dataIndex: 'message' },
        ]}
      />
    </>
  )
}
