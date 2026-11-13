import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  App,
  Badge,
  Button,
  Card,
  Col,
  Input,
  Pagination,
  Row,
  Select,
  Space,
  Table,
  Typography,
} from 'antd'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { deleteDevice, fetchDeviceFilters, fetchDevices } from '../features/devices/api'
import { DeviceImageThumb } from '../features/devices/DeviceImageThumb'
import type { DeviceSummary } from '../features/devices/types'
import '../features/devices/metricBuilder.css'

function customFieldEntries(row: DeviceSummary): Array<[string, string]> {
  const fields = row.customFields
  if (!fields || typeof fields !== 'object') return []
  return Object.entries(fields).map(([k, v]) => [k, String(v)])
}

function customFieldSummary(row: DeviceSummary): string {
  return customFieldEntries(row)
    .slice(0, 3)
    .map(([k, v]) => `${k}: ${v}`)
    .join(', ')
}

export function DevicesPage() {
  const { t } = useTranslation()
  const { modal, message } = App.useApp()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [view, setView] = useState<'list' | 'card'>('list')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [template, setTemplate] = useState('')
  const [location, setLocation] = useState('')
  const [page, setPage] = useState(1)
  const [listPageSize, setListPageSize] = useState(10)
  const [cardPageSize] = useState(12)

  const pageSize = view === 'card' ? cardPageSize : listPageSize

  const filters = useQuery({
    queryKey: ['device-filters'],
    queryFn: fetchDeviceFilters,
  })

  const devices = useQuery({
    queryKey: ['devices', search, status, template, location, page, pageSize, view],
    queryFn: () =>
      fetchDevices({
        search,
        status,
        template,
        location,
        page,
        pageSize,
      }),
  })

  const remove = useMutation({
    mutationFn: deleteDevice,
    onSuccess: async () => {
      message.success(t('device_deleted_successfully'))
      await queryClient.invalidateQueries({ queryKey: ['devices'] })
    },
    onError: () => {
      message.error(t('error_deleting_device'))
    },
  })

  const rows = devices.data?.devices ?? []
  const total = devices.data?.total ?? 0

  const pageLengthOptions = useMemo(
    () => [
      { value: 10, label: 'Show 10 entries' },
      { value: 25, label: 'Show 25 entries' },
      { value: 50, label: 'Show 50 entries' },
      { value: 100, label: 'Show 100 entries' },
    ],
    [],
  )

  function confirmDelete(row: DeviceSummary) {
    modal.confirm({
      title: t('confirm_delete_device'),
      content: row.name,
      onOk: () => remove.mutateAsync(row.id),
    })
  }

  function actionButtons(row: DeviceSummary) {
    return (
      <Space.Compact className='actionButtons'>
        <Button
          size="small"
          icon={<i className="bi bi-eye" />}
          onClick={() => navigate(`/device/view/${row.id}`)}
        >
          {t('view')}
        </Button>
        <Button
          size="small"
          icon={<i className="bi bi-pencil" />}
          onClick={() => navigate(`/device/edit/${row.id}`)}
        >
          {t('edit')}
        </Button>
        <Button
          size="small"
          danger
          icon={<i className="bi bi-trash" />}
          onClick={() => confirmDelete(row)}
        >
          {t('delete')}
        </Button>
      </Space.Compact>
    )
  }

  return (
    <Space direction="vertical" className='metricBuilder'>
      <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {t('devices')}
        </Typography.Title>
        <Space wrap>
          <Button.Group>
            <Button
              type={view === 'list' ? 'primary' : 'default'}
              icon={<i className="bi bi-list" />}
              onClick={() => {
                setView('list')
                setPage(1)
              }}
            >
              {t('list')}
            </Button>
            <Button
              type={view === 'card' ? 'primary' : 'default'}
              icon={<i className="bi bi-grid" />}
              onClick={() => {
                setView('card')
                setPage(1)
              }}
            >
              {t('card')}
            </Button>
          </Button.Group>
          <Button
            type="primary"
            icon={<i className="bi bi-plus" />}
            onClick={() => navigate('/device/add')}
          >
            {t('add_device')}
          </Button>
        </Space>
      </Space>

      <Space wrap>
        {view === 'list' && (
          <Select
            style={{ width: 180 }}
            value={listPageSize}
            onChange={(value) => {
              setPage(1)
              setListPageSize(value)
            }}
            options={pageLengthOptions}
          />
        )}
        <Input.Search
          allowClear
          placeholder={t('search_devices')}
          style={{ width: 240 }}
          onSearch={(value) => {
            setPage(1)
            setSearch(value)
          }}
        />
        <Select
          style={{ width: 160 }}
          value={status}
          onChange={(value) => {
            setPage(1)
            setStatus(value)
          }}
          options={[
            { value: '', label: t('all_status') },
            { value: 'active', label: t('status_active') },
            { value: 'inactive', label: t('status_inactive') },
          ]}
        />
        <Select
          style={{ width: 200 }}
          value={template}
          onChange={(value) => {
            setPage(1)
            setTemplate(value)
          }}
          options={[
            { value: '', label: t('all_templates') },
            ...(filters.data?.templates ?? []).map((name) => ({ value: name, label: name })),
          ]}
        />
        <Select
          style={{ width: 200 }}
          value={location}
          onChange={(value) => {
            setPage(1)
            setLocation(value)
          }}
          options={[
            { value: '', label: t('all_locations') },
            ...(filters.data?.locations ?? []).map((name) => ({ value: name, label: name })),
          ]}
        />
      </Space>

      {view === 'list' ? (
        <Table
          rowKey="id"
          loading={devices.isLoading}
          className='devicesTable'
          dataSource={rows}
          locale={{ emptyText: t('no_data_found') }}
          pagination={{
            current: page,
            pageSize: listPageSize,
            total,
            onChange: (p, ps) => {
              setPage(p)
              if (ps) setListPageSize(ps)
            },
          }}
          columns={[
            {
              title: t('image'),
              width: 72,
              render: (_v, row) => (
                <DeviceImageThumb
                  imageUrl={row.imageUrl}
                  image={row.image}
                  templateImage={row.templateImage}
                  size={40}
                  hoverPreview
                />
              ),
            },
            { title: t('device_name'), dataIndex: 'name' },
            {
              title: t('device_brand'),
              dataIndex: 'brand',
              render: (v: string) => v || '-',
            },
            {
              title: t('device_model'),
              dataIndex: 'model',
              render: (v: string) => v || '-',
            },
            { title: t('serial_number'), dataIndex: 'serialNumber' },
            { title: t('device_location'), dataIndex: 'location' },
            {
              title: t('device_description'),
              dataIndex: 'description',
              ellipsis: true,
            },
            {
              title: t('custom_field'),
              render: (_v, row) => customFieldSummary(row) || '-',
            },
            {
              title: t('device_status'),
              dataIndex: 'isActive',
              render: (value: boolean) =>
                value ? (
                  <Badge status="success" text={t('status_active')} />
                ) : (
                  <Badge status="default" text={t('status_inactive')} />
                ),
            },
            { title: t('actions'), render: (_v, row) => actionButtons(row) },
          ]}
        />
      ) : (
        <>
          {!rows.length && !devices.isLoading ? (
            <Typography.Paragraph type="secondary" style={{ textAlign: 'center' }}>
              {t('no_data_found')}
            </Typography.Paragraph>
          ) : null}
          <Row gutter={[16, 16]}>
            {rows.map((row) => {
              const fields = customFieldEntries(row)
              return (
                <Col key={row.id} xs={24} sm={12} md={8}>
                  <Card loading={devices.isLoading} className="h-100" styles={{ body: { textAlign: 'center' } }}>
                    <DeviceImageThumb
                      imageUrl={row.imageUrl}
                      image={row.image}
                      templateImage={row.templateImage}
                      size={80}
                      style={{ maxHeight: 80, objectFit: 'cover', margin: '0 auto' }}
                      includeDefault={false}
                    />
                    <Typography.Title level={5} style={{ marginTop: 8, marginBottom: 4 }}>
                      {row.name}
                    </Typography.Title>
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                      {row.brand || '-'} | {row.model || '-'}
                    </Typography.Text>
                    <div style={{ margin: '8px 0' }}>
                      <Badge
                        status={row.isActive ? 'success' : 'default'}
                        text={row.isActive ? t('status_active') : t('status_inactive')}
                      />
                    </div>
                    {fields.length > 0 && (
                      <div className="device-card-custom-fields">
                        {fields.map(([k, v]) => (
                          <div key={k} className="custom-field-item">
                            <strong>{k}:</strong>
                            <span>{v}</span>
                          </div>
                        ))}
                      </div>
                    )}
                    <div style={{ marginTop: 12 }}>{actionButtons(row)}</div>
                  </Card>
                </Col>
              )
            })}
          </Row>
          <div style={{ display: 'flex', justifyContent: 'center', marginTop: 16 }}>
            <Pagination
              current={page}
              pageSize={cardPageSize}
              total={total}
              onChange={setPage}
              showSizeChanger={false}
            />
          </div>
        </>
      )}
    </Space>
  )
}
