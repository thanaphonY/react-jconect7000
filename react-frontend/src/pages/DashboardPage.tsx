import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { App, Button, Input, Space, Table, Typography } from 'antd'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { ApiError, apiRequest } from '../api/client'
import type { LayoutList, LayoutSummary } from '../features/dashboard/types'

export function DashboardPage() {
  const { t } = useTranslation()
  const { message, modal } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [rows, setRows] = useState<LayoutSummary[]>([])
  const [dragId, setDragId] = useState<number | null>(null)
  const [search, setSearch] = useState('')

  const layouts = useQuery({
    queryKey: ['dashboards'],
    queryFn: () => apiRequest<LayoutList>('/dashboards'),
  })

  useEffect(() => {
    setRows(layouts.data?.layouts ?? [])
  }, [layouts.data?.layouts])

  const homeId = layouts.data?.homeLayoutId

  function refresh() {
    return queryClient.invalidateQueries({ queryKey: ['dashboards'] })
  }

  const setHome = useMutation({
    mutationFn: (id: number) => apiRequest(`/dashboards/${id}/home`, { method: 'POST' }),
    onSuccess: async () => {
      message.success(t('layout_set_as_home'))
      await refresh()
      await queryClient.invalidateQueries({ queryKey: ['dashboard-view'] })
    },
  })

  const remove = useMutation({
    mutationFn: (id: number) => apiRequest(`/dashboards/${id}`, { method: 'DELETE' }),
    onSuccess: async () => {
      message.success(t('layout_deleted_successfully'))
      await refresh()
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : t('failed_to_delete_layout'))
    },
  })

  const reorder = useMutation({
    mutationFn: (order: number[]) =>
      apiRequest<LayoutList>('/dashboards/sort-order', {
        method: 'PUT',
        body: { order },
      }),
    onSuccess: async (data) => {
      setRows(data.layouts)
      message.success('Saved order')
      await queryClient.invalidateQueries({ queryKey: ['dashboards'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : 'Unable to save order')
      void refresh()
    },
  })

  const onDrop = useCallback(
    (targetId: number) => {
      if (dragId === null || dragId === targetId) {
        setDragId(null)
        return
      }
      const from = rows.findIndex((r) => r.id === dragId)
      const to = rows.findIndex((r) => r.id === targetId)
      if (from < 0 || to < 0) {
        setDragId(null)
        return
      }
      const next = [...rows]
      const [moved] = next.splice(from, 1)
      next.splice(to, 0, moved)
      setRows(next)
      setDragId(null)
      reorder.mutate(next.map((r) => r.id))
    },
    [dragId, reorder, rows],
  )

  const filteredRows = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return rows
    return rows.filter((r) => {
      const name = (r.name ?? '').toLowerCase()
      const description = (r.description ?? '').toLowerCase()
      return name.includes(q) || description.includes(q)
    })
  }, [rows, search])

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Space style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {t('dashboard')}
        </Typography.Title>
        <Button
          type="primary"
          icon={<i className="bi bi-plus-circle" />}
          onClick={() => navigate('/dashboard/add')}
        >
          {t('add_dashboard')}
        </Button>
      </Space>
      <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
        <span>{t('search')}:</span>
        <Input
          allowClear
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          style={{ width: 220 }}
          placeholder={t('search')}
        />
      </Space>
      <Table
        rowKey="id"
        loading={layouts.isLoading}
        dataSource={filteredRows}
        pagination={{ pageSize: 25, showSizeChanger: true, pageSizeOptions: [10, 25, 50, 100] }}
        locale={{ emptyText: t('no_layout_found') }}
        columns={[
          {
            title: 'Order',
            width: 90,
            align: 'center',
            render: (_value, row, index) => (
              <span
                draggable
                onDragStart={() => setDragId(row.id)}
                onDragOver={(e) => e.preventDefault()}
                onDrop={() => onDrop(row.id)}
                style={{ cursor: 'grab', userSelect: 'none', display: 'inline-flex', gap: 6 }}
                title="Drag to reorder"
              >
                <i className="bi bi-grip-vertical" />
                <span>{index + 1}</span>
              </span>
            ),
          },
          { title: t('name'), dataIndex: 'name' },
          { title: t('description'), dataIndex: 'description' },
          {
            title: t('action'),
            render: (_value, row) => (
              <Space wrap>
                <Button size="small" onClick={() => navigate(`/dashboard/view/${row.id}`)}>
                  {t('view')}
                </Button>
                <Button
                  size="small"
                  type="primary"
                  onClick={() => navigate(`/dashboard/edit/${row.id}`)}
                >
                  {t('edit')}
                </Button>
                {row.id !== homeId ? (
                  <Button size="small" onClick={() => setHome.mutate(row.id)}>
                    {t('sethome')}
                  </Button>
                ) : null}
                <Button
                  size="small"
                  danger
                  onClick={() => {
                    modal.confirm({
                      title: t('confirm_delete_layout'),
                      content: row.name,
                      okText: t('yes'),
                      cancelText: t('no'),
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
