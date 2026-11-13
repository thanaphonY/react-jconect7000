import { useQuery } from '@tanstack/react-query'
import { Input, Select, Space, Switch, Typography } from 'antd'
import { useEffect, useMemo, useState } from 'react'
import { fetchLorawanAppServers, fetchLorawanDeviceProfiles } from './api'
import {
  COMM_FIELDS,
  SNMP_AUTH_PROTOCOLS,
  SNMP_PRIV_PROTOCOLS,
  SNMP_SECURITY_LEVELS,
  RTSP_PLACEHOLDERS,
  fieldLabel,
  normalizeCommProtocol,
} from './protocolFields'

type Props = {
  protocol: string
  value: Record<string, unknown>
  onChange: (next: Record<string, unknown>) => void
  disabled?: boolean
  endpointNotice?: boolean
}

function setField(
  value: Record<string, unknown>,
  onChange: (next: Record<string, unknown>) => void,
  key: string,
  next: unknown,
) {
  onChange({ ...value, [key]: next })
}

export function CommunicationFields({
  protocol,
  value,
  onChange,
  disabled,
  endpointNotice,
}: Props) {
  const proto = normalizeCommProtocol(protocol)
  const fields = COMM_FIELDS[proto] ?? Object.keys(value)
  const isLorawan = proto === 'lorawan'

  const appServers = useQuery({
    queryKey: ['lorawan-app-servers'],
    queryFn: fetchLorawanAppServers,
    enabled: isLorawan,
  })

  const selectedAppServerId = String(value.application_server ?? '')
  const selectedAppServer = useMemo(
    () => (appServers.data ?? []).find((s) => String(s.id) === selectedAppServerId),
    [appServers.data, selectedAppServerId],
  )
  const tenantId = selectedAppServer?.tenantId || ''

  const deviceProfiles = useQuery({
    queryKey: ['lorawan-device-profiles', tenantId],
    queryFn: () => fetchLorawanDeviceProfiles(tenantId),
    enabled: isLorawan && Boolean(tenantId),
  })

  const [profilesFallbackText, setProfilesFallbackText] = useState(false)
  useEffect(() => {
    if (!isLorawan) return
    if (tenantId && deviceProfiles.isFetched && !(deviceProfiles.data ?? []).length) {
      setProfilesFallbackText(true)
    } else {
      setProfilesFallbackText(false)
    }
  }, [isLorawan, tenantId, deviceProfiles.isFetched, deviceProfiles.data])

  if (endpointNotice) {
    return (
      <Typography.Paragraph type="secondary" style={{ margin: 0 }}>
        Gateway Endpoint devices inherit communication from the parent Gateway Collector. Direct
        protocol fields are hidden.
      </Typography.Paragraph>
    )
  }

  if (!fields.length) {
    return (
      <Typography.Paragraph type="secondary" style={{ margin: 0 }}>
        No communication fields for protocol <code>{proto || 'unknown'}</code>.
      </Typography.Paragraph>
    )
  }

  return (
    <Space direction="vertical" size={12} style={{ width: '100%' }}>
      {fields.map((field) => {
        const label = fieldLabel(field)
        const current = value[field] ?? ''

        if (field === 'application_server' && isLorawan) {
          return (
            <div key={field}>
              <Typography.Text>{label}</Typography.Text>
              <Select
                style={{ width: '100%', marginTop: 4 }}
                disabled={disabled}
                loading={appServers.isLoading}
                placeholder="Select Application Server"
                value={selectedAppServerId || undefined}
                onChange={(v) => {
                  onChange({ ...value, application_server: v, device_profile_id: '' })
                }}
                options={(appServers.data ?? []).map((s) => ({
                  value: String(s.id),
                  label: s.name,
                }))}
                allowClear
                showSearch
                optionFilterProp="label"
              />
            </div>
          )
        }

        if (field === 'device_profile_id' && isLorawan) {
          const profiles = deviceProfiles.data ?? []
          if (!profilesFallbackText && (profiles.length > 0 || (tenantId && deviceProfiles.isLoading))) {
            return (
              <div key={field}>
                <Typography.Text>{label}</Typography.Text>
                <Select
                  style={{ width: '100%', marginTop: 4 }}
                  disabled={disabled || !tenantId}
                  loading={deviceProfiles.isLoading}
                  placeholder={
                    tenantId ? 'Select Device Profile' : 'Select Application Server first'
                  }
                  value={current ? String(current) : undefined}
                  onChange={(v) => setField(value, onChange, field, v)}
                  options={profiles.map((p) => ({ value: p.id, label: p.name }))}
                  allowClear
                  showSearch
                  optionFilterProp="label"
                />
              </div>
            )
          }
          return (
            <div key={field}>
              <Typography.Text>{label}</Typography.Text>
              <Input
                style={{ marginTop: 4 }}
                disabled={disabled}
                value={String(current)}
                placeholder="Device Profile ID"
                onChange={(e) => setField(value, onChange, field, e.target.value)}
              />
              {!tenantId && (
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                  Device profile dropdown needs tenant id from LoRaWAN app server API.
                </Typography.Text>
              )}
            </div>
          )
        }

        if (field === 'version' && proto === 'snmp') {
          return (
            <div key={field}>
              <Typography.Text>{label}</Typography.Text>
              <Select
                style={{ width: '100%', marginTop: 4 }}
                disabled={disabled}
                value={String(current || '2c')}
                onChange={(v) => setField(value, onChange, field, v)}
                options={[
                  { value: '1', label: 'SNMP v1' },
                  { value: '2c', label: 'SNMP v2c' },
                  { value: '3', label: 'SNMP v3' },
                ]}
              />
            </div>
          )
        }

        if (field === 'security_level' && proto === 'snmp') {
          if (String(value.version) !== '3') return null
          return (
            <div key={field}>
              <Typography.Text>{label}</Typography.Text>
              <Select
                style={{ width: '100%', marginTop: 4 }}
                disabled={disabled}
                value={String(current || 'noAuthNoPriv')}
                onChange={(v) => setField(value, onChange, field, v)}
                options={SNMP_SECURITY_LEVELS.map((v) => ({ value: v, label: v }))}
              />
            </div>
          )
        }

        if (field === 'auth_protocol' && proto === 'snmp') {
          const level = String(value.security_level || '')
          if (String(value.version) !== '3' || (level !== 'authNoPriv' && level !== 'authPriv'))
            return null
          return (
            <div key={field}>
              <Typography.Text>{label}</Typography.Text>
              <Select
                style={{ width: '100%', marginTop: 4 }}
                disabled={disabled}
                value={String(current || 'SHA')}
                onChange={(v) => setField(value, onChange, field, v)}
                options={SNMP_AUTH_PROTOCOLS.map((v) => ({ value: v, label: v }))}
              />
            </div>
          )
        }

        if (field === 'priv_protocol' && proto === 'snmp') {
          if (String(value.version) !== '3' || String(value.security_level) !== 'authPriv')
            return null
          return (
            <div key={field}>
              <Typography.Text>{label}</Typography.Text>
              <Select
                style={{ width: '100%', marginTop: 4 }}
                disabled={disabled}
                value={String(current || 'AES')}
                onChange={(v) => setField(value, onChange, field, v)}
                options={SNMP_PRIV_PROTOCOLS.map((v) => ({ value: v, label: v }))}
              />
            </div>
          )
        }

        if ((field === 'auth_pass' || field === 'priv_pass') && proto === 'snmp') {
          const level = String(value.security_level || '')
          if (String(value.version) !== '3') return null
          if (field === 'auth_pass' && level !== 'authNoPriv' && level !== 'authPriv') return null
          if (field === 'priv_pass' && level !== 'authPriv') return null
          return (
            <div key={field}>
              <Typography.Text>{label}</Typography.Text>
              <Input.Password
                style={{ marginTop: 4 }}
                disabled={disabled}
                value={String(current)}
                onChange={(e) => setField(value, onChange, field, e.target.value)}
              />
            </div>
          )
        }

        if (field === 'transport' && proto === 'rtsp') {
          return (
            <div key={field}>
              <Typography.Text>Transport</Typography.Text>
              <Select
                style={{ width: '100%', marginTop: 4 }}
                disabled={disabled}
                value={String(current || 'tcp')}
                onChange={(v) => setField(value, onChange, field, v)}
                options={[
                  { value: 'tcp', label: 'TCP' },
                  { value: 'udp', label: 'UDP' },
                ]}
              />
            </div>
          )
        }

        if (field === 'parity' && proto === 'modbus_rtu') {
          return (
            <div key={field}>
              <Typography.Text>{label}</Typography.Text>
              <Select
                style={{ width: '100%', marginTop: 4 }}
                disabled={disabled}
                value={String(current || 'N')}
                onChange={(v) => setField(value, onChange, field, v)}
                options={[
                  { value: 'N', label: 'None (N)' },
                  { value: 'E', label: 'Even (E)' },
                  { value: 'O', label: 'Odd (O)' },
                ]}
              />
            </div>
          )
        }

        if (proto === 'pemon' && (field === 'snmp_enable' || field === 'skip_on_comm_fail')) {
          return (
            <label key={field} style={{ display: 'block' }}>
              {label}
              <Select
                aria-label={label}
                style={{ width: '100%', marginTop: 4 }}
                disabled={disabled}
                value={String(current === true || current === 'true' || current === 1 || current === '1')}
                options={[{ value: 'true' }, { value: 'false' }]}
                onChange={(v) => setField(value, onChange, field, v === 'true')}
              />
            </label>
          )
        }

        if (field === 'use_tls') {
          return (
            <div key={field}>
              <Space>
                <Switch
                  disabled={disabled}
                  checked={
                    current === true || current === 'true' || current === 1 || current === '1'
                  }
                  onChange={(checked) => setField(value, onChange, field, checked)}
                />
                <Typography.Text>{label}</Typography.Text>
              </Space>
            </div>
          )
        }

        if (
          field === 'password' ||
          field === 'app_key' ||
          field === 'netskey' ||
          field === 'appskey'
        ) {
          return (
            <div key={field}>
              <Typography.Text>{label}</Typography.Text>
              <Input.Password
                style={{ marginTop: 4 }}
                disabled={disabled}
                value={String(current)}
                autoComplete="new-password"
                onChange={(e) => setField(value, onChange, field, e.target.value)}
              />
            </div>
          )
        }

        const isNumber = [
          'port',
          'unit_id',
          'slave_id',
          'baudrate',
          'stop_bits',
          'data_bits',
          'qos',
          'timeout',
          'rtsp_port',
          'max_oids_per_request',
          'batch_delay_ms',
          'device_id',
        ].includes(field) || (proto === 'pemon' &&
          ['http_port', 'pull_rate', 'detail_pull_rate', 'snmp_port', 'config_pull_rate'].includes(field))

        return (
          <div key={field}>
            <Typography.Text>{label}</Typography.Text>
            <Input
              style={{ marginTop: 4 }}
              disabled={disabled}
              type={isNumber ? 'number' : 'text'}
              value={current == null ? '' : String(current)}
              placeholder={proto === 'rtsp' ? RTSP_PLACEHOLDERS[field] : undefined}
              onChange={(e) =>
                setField(
                  value,
                  onChange,
                  field,
                  isNumber && e.target.value !== '' ? Number(e.target.value) : e.target.value,
                )
              }
            />
          </div>
        )
      })}
    </Space>
  )
}
