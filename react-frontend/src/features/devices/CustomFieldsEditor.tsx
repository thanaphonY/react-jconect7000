import { Button, Col, Input, Row, Space } from 'antd'

export type CustomFieldRow = { key: string; value: string }

type Props = {
  value: CustomFieldRow[]
  onChange: (rows: CustomFieldRow[]) => void
  addLabel?: string
  keyPlaceholder?: string
  valuePlaceholder?: string
  removeLabel?: string
}

export function CustomFieldsEditor({
  value,
  onChange,
  addLabel = 'Add additional information',
  keyPlaceholder = 'Field',
  valuePlaceholder = 'Value',
  removeLabel,
}: Props) {
  return (
    <Space direction="vertical" style={{ width: '100%' }} size={8}>
      {value.map((row, index) => (
        <Row key={index} gutter={8} align="middle">
          <Col span={10}>
            <Input
              placeholder={keyPlaceholder}
              value={row.key}
              onChange={(e) => {
                const next = [...value]
                next[index] = { ...next[index], key: e.target.value }
                onChange(next)
              }}
            />
          </Col>
          <Col span={10}>
            <Input
              placeholder={valuePlaceholder}
              value={row.value}
              onChange={(e) => {
                const next = [...value]
                next[index] = { ...next[index], value: e.target.value }
                onChange(next)
              }}
            />
          </Col>
          <Col span={4}>
            <Button danger onClick={() => onChange(value.filter((_, i) => i !== index))}>
              {removeLabel ?? <i className="bi bi-trash" />}
            </Button>
          </Col>
        </Row>
      ))}
      <Button
        type="dashed"
        icon={<i className="bi bi-plus" />}
        onClick={() => onChange([...value, { key: `field_${value.length + 1}`, value: '' }])}
      >
        {addLabel}
      </Button>
    </Space>
  )
}

export function customFieldsToObject(rows: CustomFieldRow[]): Record<string, string> {
  const out: Record<string, string> = {}
  rows.forEach((row) => {
    const key = row.key.trim()
    if (!key) return
    out[key] = row.value
  })
  return out
}

export function objectToCustomFields(obj: Record<string, unknown> | null | undefined): CustomFieldRow[] {
  if (!obj || typeof obj !== 'object') return []
  return Object.entries(obj).map(([key, value]) => ({
    key,
    value: value == null ? '' : String(value),
  }))
}
