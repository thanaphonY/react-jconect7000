import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Breadcrumb,
  Button,
  Drawer,
  Modal,
  Select,
  Space,
  Spin,
  Typography,
} from 'antd'
import { GridStack } from 'gridstack'
import 'gridstack/dist/gridstack.min.css'
import 'gridstack/dist/gridstack-extra.min.css'
import { useCallback, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { ApiError, apiRequest } from '../../api/client'
import { WidgetConfigFields } from './WidgetConfigFields'
import type {
  CatalogWidget,
  GridWidgetState,
  LayoutDetail,
  LayoutList,
  LayoutSaveItem,
  LayoutSummary,
} from './types'
import './layout-editor.css'

function uid(): string {
  return `uid-${Math.random().toString(36).slice(2, 11)}`
}

/** Parse widget.config when API/DB returns a JSON string — never spread a string (char-index map). */
function parseWidgetConfig(config: unknown): Record<string, unknown> {
  if (config == null) return {}
  if (typeof config === 'string') {
    try {
      const parsed: unknown = JSON.parse(config)
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return { ...(parsed as Record<string, unknown>) }
      }
      return {}
    } catch {
      return {}
    }
  }
  if (typeof config === 'object' && !Array.isArray(config)) {
    return { ...(config as Record<string, unknown>) }
  }
  return {}
}

function itemToState(item: {
  widgetId?: number | null
  widget_id?: number | null
  widgetType?: string
  type?: string
  x: number
  y: number
  w: number
  h: number
  config?: Record<string, unknown> | string
  name?: string
  label?: string
}): GridWidgetState {
  const config = parseWidgetConfig(item.config)
  const widgetType = item.widgetType || item.type || ''
  const label =
    (typeof config.label === 'string' && config.label) ||
    (typeof config.zone_name === 'string' && config.zone_name) ||
    item.label ||
    item.name ||
    widgetType
  return {
    uid: uid(),
    widgetId: item.widgetId ?? item.widget_id ?? null,
    widgetType,
    x: item.x ?? 0,
    y: item.y ?? 0,
    w: item.w || 3,
    h: item.h || 2,
    config,
    label: String(label),
  }
}

function syncNodePosition(grid: GridStack, state: GridWidgetState): GridWidgetState {
  const node = grid.engine.nodes.find((n) => (n.el as HTMLElement | undefined)?.dataset?.uid === state.uid)
  if (!node) return state
  return {
    ...state,
    x: node.x ?? state.x,
    y: node.y ?? state.y,
    w: node.w ?? state.w,
    h: node.h ?? state.h,
  }
}

export function DashboardEditLayoutPage() {
  const { id } = useParams()
  const layoutId = Number(id)
  const { t } = useTranslation()
  const { message } = App.useApp()
  const queryClient = useQueryClient()
  const gridRef = useRef<HTMLDivElement>(null)
  const gridInstance = useRef<GridStack | null>(null)
  const widgetsRef = useRef<GridWidgetState[]>([])
  const [widgets, setWidgets] = useState<GridWidgetState[]>([])
  const [ready, setReady] = useState(false)
  const [addOpen, setAddOpen] = useState(false)
  const [selectedWidgetId, setSelectedWidgetId] = useState<number | null>(null)
  const [configUid, setConfigUid] = useState<string | null>(null)
  const [draftConfig, setDraftConfig] = useState<Record<string, unknown>>({})

  const detail = useQuery({
    queryKey: ['dashboard', layoutId],
    enabled: Number.isInteger(layoutId),
    queryFn: () => apiRequest<LayoutDetail>(`/dashboards/${layoutId}`),
  })

  const layouts = useQuery({
    queryKey: ['dashboards'],
    queryFn: () => apiRequest<LayoutList>('/dashboards'),
  })

  const catalog = useQuery({
    queryKey: ['widgets'],
    queryFn: () => apiRequest<CatalogWidget[]>('/widgets'),
  })

  const layoutList: LayoutSummary[] = layouts.data?.layouts ?? []

  useLayoutEffect(() => {
    widgetsRef.current = widgets
  }, [widgets])

  const configuring = useMemo(
    () => widgets.find((w) => w.uid === configUid) ?? null,
    [widgets, configUid],
  )

  const saveLayout = useMutation({
    mutationFn: async (items: LayoutSaveItem[]) =>
      apiRequest<LayoutDetail>(`/dashboards/${layoutId}/layout`, {
        method: 'PUT',
        body: { items },
      }),
    onSuccess: async () => {
      message.success(t('layout_saved_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['dashboard', layoutId] })
      await queryClient.invalidateQueries({ queryKey: ['dashboard-view'] })
    },
    onError: (error: unknown) => {
      message.error(error instanceof ApiError ? error.message : t('failed_to_save_layout'))
    },
  })

  const collectItems = useCallback((): LayoutSaveItem[] => {
    const grid = gridInstance.current
    const current = widgetsRef.current.map((w) => (grid ? syncNodePosition(grid, w) : w))
    return current.map((w) => ({
      widget_id: w.widgetId,
      widget_type: w.widgetType,
      x: w.x,
      y: w.y,
      w: w.w,
      h: w.h,
      config: w.config,
    }))
  }, [])

  const handleSave = useCallback(() => {
    saveLayout.mutate(collectItems())
  }, [collectItems, saveLayout])

  // Preserve unsaved edits on query refetch; hydrate only when switching dashboards.
  const loaded = Boolean(detail.data)
  useLayoutEffect(() => {
    if (!detail.data || !gridRef.current) return
    const grid = GridStack.init(
      { column: 16, cellHeight: 50, float: true, margin: 5, auto: false,
        draggable: { cancel: 'button, input, select, textarea, a' } }, gridRef.current,
    )
    gridInstance.current = grid
    const initial = detail.data.items.map(itemToState)
    widgetsRef.current = initial
    setWidgets(initial)
    grid.on('dragstop resizestop', () => {
      const next = widgetsRef.current.map((w) => syncNodePosition(grid, w))
      widgetsRef.current = next
      setWidgets(next)
    })
    return () => { grid.destroy(false); gridInstance.current = null }
    // Query refetches must not discard local edits.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [layoutId, loaded])

  useLayoutEffect(() => {
    const grid = gridInstance.current
    if (!grid) return
    grid.batchUpdate()
    widgets.forEach((w) => {
      const el = gridRef.current?.querySelector<HTMLElement>(`[data-uid="${w.uid}"]`)
      if (el && !grid.engine.nodes.some((node) => node.el === el)) {
        grid.makeWidget(el, { x: w.x, y: w.y, w: w.w, h: w.h })
      }
    })
    grid.batchUpdate(false)
    setReady(true)
  }, [widgets])

  const addWidgetState = useCallback((state: GridWidgetState) => {
    const next = [...widgetsRef.current, state]
    widgetsRef.current = next
    setWidgets(next)
  }, [])

  const removeWidget = useCallback((widgetUid: string) => {
    const grid = gridInstance.current
    const el = gridRef.current?.querySelector(`[data-uid="${widgetUid}"]`) as HTMLElement | null
    if (el && grid) grid.removeWidget(el, false)
    setWidgets((prev) => {
      const next = prev.filter((w) => w.uid !== widgetUid)
      widgetsRef.current = next
      return next
    })
    if (configUid === widgetUid) setConfigUid(null)
  }, [configUid])

  const duplicateWidget = useCallback((widgetUid: string) => {
    const grid = gridInstance.current
    const source = widgetsRef.current.find((w) => w.uid === widgetUid)
    if (!source) return
    const synced = grid ? syncNodePosition(grid, source) : source
    const copy: GridWidgetState = {
      ...synced,
      uid: uid(),
      x: Math.min(synced.x + 1, 16 - synced.w),
      y: synced.y + 1,
      config: structuredClone(synced.config),
    }
    addWidgetState(copy)
  }, [addWidgetState])

  const openConfigure = useCallback((widgetUid: string) => {
    const target = widgetsRef.current.find((w) => w.uid === widgetUid)
    if (!target) return
    setConfigUid(widgetUid)
    setDraftConfig(structuredClone(target.config))
  }, [])

  const applyConfig = useCallback(
    (andSave: boolean) => {
      if (!configUid) return
      const grid = gridInstance.current
      const next = widgetsRef.current.map((w) => {
        const synced = grid ? syncNodePosition(grid, w) : w
        if (synced.uid !== configUid) return synced
        const label =
          (typeof draftConfig.label === 'string' && draftConfig.label) ||
          (typeof draftConfig.zone_name === 'string' && draftConfig.zone_name) ||
          synced.label
        return { ...synced, config: structuredClone(draftConfig), label: String(label) }
      })
      widgetsRef.current = next
      setWidgets(next)
      if (andSave) {
        setConfigUid(null)
        saveLayout.mutate(
          next.map((w) => ({
            widget_id: w.widgetId,
            widget_type: w.widgetType,
            x: w.x,
            y: w.y,
            w: w.w,
            h: w.h,
            config: w.config,
          })),
        )
      }
    },
    [configUid, draftConfig, saveLayout],
  )

  const onAddFromCatalog = useCallback(async () => {
    if (!selectedWidgetId) {
      message.warning(t('please_select_a_widget'))
      return
    }
    try {
      const widget =
        catalog.data?.find((w) => w.id === selectedWidgetId) ||
        (await apiRequest<CatalogWidget>(`/widgets/${selectedWidgetId}`))
      const cfg = parseWidgetConfig(widget.config)
      // PHP catalog additions start at 3 × 2.
      const [dw, dh] = [3, 2]
      const y = gridInstance.current?.getRow?.() ?? 0
      const state: GridWidgetState = {
        uid: uid(),
        widgetId: widget.id,
        widgetType: widget.widgetType,
        x: 0,
        y,
        w: dw,
        h: dh,
        config: {
          ...cfg,
          label: (typeof cfg.label === 'string' && cfg.label) || widget.name,
        },
        label: (typeof cfg.label === 'string' && cfg.label) || widget.name,
      }
      addWidgetState(state)
      setAddOpen(false)
      setSelectedWidgetId(null)
    } catch (error) {
      message.error(error instanceof ApiError ? error.message : t('failed_to_load_widget'))
    }
  }, [addWidgetState, catalog.data, message, selectedWidgetId, t])

  if (detail.isLoading) {
    return (
      <div style={{ display: 'grid', placeItems: 'center', minHeight: 240 }}>
        <Spin />
      </div>
    )
  }

  if (detail.isError || !detail.data) {
    return <Typography.Text type="danger">{t('layout_not_found')}</Typography.Text>
  }

  const layout = detail.data.layout

  return (
    <div className="php-layout-editor">
      <div className="layout-editor-heading">
        <div>
          <Typography.Title level={3} className="layout-editor-title" style={{ margin: 0 }}>
            {t('edit_layout')} {layout.name}
          </Typography.Title>
          <Breadcrumb
            items={[
              { title: <Link to="/home">{t('home')}</Link> },
              { title: <Link to="/dashboard">{t('dashboard')}</Link> },
              { title: <Link to={`/dashboard/view/${layout.id}`}>{layout.name}</Link> },
              { title: t('edit') },
            ]}
          />
        </div>
        <Space>
          <Button className="layout-editor-add" type="primary" onClick={() => setAddOpen(true)}>
            {t('add_widget')}
          </Button>
          <Button type="primary" loading={saveLayout.isPending} onClick={handleSave}>
            {t('save')}
          </Button>
        </Space>
      </div>

      <div className="layout-editor-container">
      <div className="layout-editor-scroll">
      <div
        className="dashboard-layout-canvas grid-stack"
        ref={gridRef}
        style={{
          backgroundImage: layout.backgroundImage ? `url(${JSON.stringify(layout.backgroundImage)})` : undefined,
          opacity: ready ? 1 : 0.6,
        }}
      >
        {widgets.map((w) => (
          <div
            key={w.uid}
            className="grid-stack-item"
            data-uid={w.uid}
            gs-x={w.x}
            gs-y={w.y}
            gs-w={w.w}
            gs-h={w.h}
          >
            <div
              className="grid-stack-item-content"
            >
              <div className="layout-widget-caption">
                <strong>{t('widget')}</strong>
                <div className="text-muted" style={{ fontSize: 12, marginBottom: 4 }}>
                  {w.label}
                </div>
                <div style={{ fontSize: 12, opacity: 0.65 }}>
                  ID: {w.widgetId ?? 0}
                </div>
              </div>
              <div className="layout-widget-actions">
                <Button
                  size="small"
                  className="layout-widget-configure"
                  aria-label={t('configure_button')}
                  title={t('configure_button')}
                  icon={<i className="bi bi-gear" />}
                  onClick={() => openConfigure(w.uid)}
                />
                <Button
                  size="small"
                  className="layout-widget-duplicate"
                  aria-label={t('duplicate')}
                  title={t('duplicate')}
                  icon={<i className="bi bi-copy" />}
                  onClick={() => duplicateWidget(w.uid)}
                />
                <Button
                  size="small"
                  danger
                  className="layout-widget-remove"
                  aria-label={t('remove')}
                  title={t('remove')}
                  icon={<i className="bi bi-trash" />}
                  onClick={() => removeWidget(w.uid)}
                />
              </div>
            </div>
          </div>
        ))}
      </div>
      </div>
      </div>

      <Modal
        className="layout-editor-add-modal"
        width={500}
        title={t('add_widget_to_layout')}
        open={addOpen}
        onCancel={() => setAddOpen(false)}
        onOk={() => void onAddFromCatalog()}
        okText={t('add_to_layout')}
      >
        <Select
          style={{ width: '100%' }}
          placeholder={t('choose_widget')}
          value={selectedWidgetId ?? undefined}
          options={(catalog.data ?? []).map((w) => ({
            value: w.id,
            label: w.name,
          }))}
          onChange={(v) => setSelectedWidgetId(v)}
          showSearch
          optionFilterProp="label"
        />
      </Modal>

      <Drawer
        rootClassName="layout-editor-config"
        mask={false}
        title={t('configure_widget')}
        open={Boolean(configuring)}
        onClose={() => setConfigUid(null)}
        width="min(770px, 100vw)"
        styles={{ body: { background: '#212529', color: '#dee2e6' }, header: { background: '#212529', color: '#dee2e6' }, footer: { background: '#212529' } }}
        footer={
          <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
            <Button type="primary" onClick={() => applyConfig(false)}>
              {t('apply')}
            </Button>
            <Button className="layout-editor-add" type="primary" loading={saveLayout.isPending} onClick={() => applyConfig(true)}>
              {t('save')}
            </Button>
          </Space>
        }
      >
        {configuring ? (
          <Space direction="vertical" style={{ width: '100%' }} size={16}>
            <Typography.Text style={{ color: '#fff' }}>
              {configuring.widgetType}
            </Typography.Text>
            <WidgetConfigFields
              widgetType={configuring.widgetType}
              values={draftConfig}
              onChange={setDraftConfig}
              layouts={layoutList}
            />
            <div
              style={{
                border: '1px solid #444',
                borderRadius: 6,
                padding: 12,
                background: '#fff',
                color: '#000',
              }}
            >
              <Typography.Text type="secondary">{t('widget_preview')}</Typography.Text>
              <div style={{ marginTop: 8 }}>
                {(typeof draftConfig.label === 'string' && draftConfig.label) ||
                  configuring.label ||
                  configuring.widgetType}
              </div>
            </div>
          </Space>
        ) : null}
      </Drawer>
    </div>
  )
}
