import { Button, Input, Select, Space, Typography } from 'antd'
import {
  CustomFieldsEditor,
  customFieldsToObject,
  objectToCustomFields,
  type CustomFieldRow,
} from '../devices/CustomFieldsEditor'

const RTSP_FIELDS = [
  { key: 'ip', label: 'IP Address', type: 'text', placeholder: '172.16.180.42' },
  { key: 'port', label: 'REST Port', type: 'number', placeholder: '80' },
  { key: 'rtsp_port', label: 'RTSP Port', type: 'number', placeholder: '554' },
  { key: 'rtsp_url', label: 'RTSP Path', type: 'text', placeholder: '/Streaming/Channels/102' },
  {
    key: 'snapshot_url',
    label: 'Snapshot Path',
    type: 'text',
    placeholder: '/ISAPI/Streaming/channels/101/picture',
  },
  { key: 'transport', label: 'Transport', type: 'select' },
  { key: 'username', label: 'Username', type: 'text', placeholder: '' },
  { key: 'password', label: 'Password', type: 'password', placeholder: '' },
] as const

type Props = {
  protocol: string
  value: Record<string, unknown>
  onChange: (next: Record<string, unknown>) => void
  addLabel: string
  removeLabel: string
}

/** Protocol seed defaults matching PHP communication.php populateCommonFields(). */
export function seedCommunicationForProtocol(
  protocol: string,
  current: Record<string, unknown>,
): Record<string, unknown> {
  if (protocol === 'modbus_tcp' || protocol === 'modbus_rtu') {
    return {
      ...current,
      host: current.host ?? '192.168.1.100',
      port: current.port ?? '502',
      slave_id: current.slave_id ?? '1',
    }
  }
  if (protocol === 'snmp') {
    return {
      ...current,
      host: current.host ?? '192.168.1.100',
      community: current.community ?? 'public',
      version: current.version ?? '2c',
    }
  }
  if (protocol === 'mqtt') {
    return {
      ...current,
      broker: current.broker ?? 'mqtt://localhost:1883',
      topic: current.topic ?? 'device/data',
      username: current.username ?? '',
      password: current.password ?? '',
    }
  }
  if (protocol === 'visual') {
    return { protocol: 'visual' }
  }
  if (protocol === 'gateway_endpoint') {
    return {
      ...current,
      source_kind: current.source_kind ?? 'digital_input',
      protocol: 'gateway_endpoint',
    }
  }
  if (protocol === 'rtsp') {
    const rtspUrl = String(current.rtsp_url || '')
    const snapshotUrl = String(current.snapshot_url || '')
    return {
      protocol: 'rtsp',
      ip: current.ip || '192.168.1.100',
      port: current.port || '80',
      rtsp_port: current.rtsp_port || '554',
      rtsp_url: /^rtsp:\/\//i.test(rtspUrl) ? '/Streaming/Channels/102' : rtspUrl || '/Streaming/Channels/102',
      snapshot_url: /^https?:\/\//i.test(snapshotUrl)
        ? '/ISAPI/Streaming/channels/101/picture'
        : snapshotUrl || '/ISAPI/Streaming/channels/101/picture',
      transport: current.transport || 'tcp',
      username: current.username || '',
      password: current.password || '',
    }
  }
  return current
}

export function TemplateCommunicationEditor({
  protocol,
  value,
  onChange,
  addLabel,
  removeLabel,
}: Props) {
  if (protocol === 'rtsp') {
    return (
      <Space direction="vertical" size={12} style={{ width: '100%' }}>
        {RTSP_FIELDS.map((field) => {
          const current = value[field.key] ?? (field.key === 'transport' ? 'tcp' : '')
          if (field.type === 'select') {
            return (
              <div key={field.key}>
                <Typography.Text>{field.label}</Typography.Text>
                <Select
                  style={{ width: '100%', marginTop: 4 }}
                  value={String(current || 'tcp')}
                  onChange={(v) => onChange({ ...value, protocol: 'rtsp', [field.key]: v })}
                  options={[
                    { value: 'tcp', label: 'TCP' },
                    { value: 'udp', label: 'UDP' },
                  ]}
                />
              </div>
            )
          }
          return (
            <div key={field.key}>
              <Typography.Text>{field.label}</Typography.Text>
              <Input
                style={{ marginTop: 4 }}
                type={field.type === 'password' ? 'password' : field.type === 'number' ? 'number' : 'text'}
                placeholder={field.placeholder}
                value={String(current)}
                autoComplete={field.type === 'password' ? 'new-password' : undefined}
                onChange={(e) =>
                  onChange({ ...value, protocol: 'rtsp', [field.key]: e.target.value })
                }
              />
            </div>
          )
        })}
      </Space>
    )
  }

  const rows = objectToCustomFields(value)
  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <CustomFieldsEditor
        value={rows}
        addLabel={addLabel}
        keyPlaceholder="Key"
        valuePlaceholder="Value"
        removeLabel={removeLabel}
        onChange={(next: CustomFieldRow[]) => onChange(customFieldsToObject(next))}
      />
    </Space>
  )
}
