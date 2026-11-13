import { Button, Typography } from 'antd'
import { useRef } from 'react'
import {
  SCHEDULE_DAYS,
  SCHEDULE_SLOT_COUNT,
  durationToScheduleSlots,
  formatScheduleDays,
  scheduleSlotsToDuration,
  slotLabel,
  toggleSlotRange,
  type DurationWindow,
} from './schedule'
import './metricBuilder.css'

type Props = {
  windows: DurationWindow[]
  onChange: (windows: DurationWindow[]) => void
  readOnly?: boolean
}

export function ScheduleGrid({ windows, onChange, readOnly = false }: Props) {
  const drag = useRef<{ day: number; startSlot: number; mode: 'add' | 'remove' } | null>(null)
  const selected = durationToScheduleSlots(windows)

  function applyRange(day: number, from: number, to: number, mode: 'add' | 'remove') {
    if (readOnly) return
    onChange(scheduleSlotsToDuration(toggleSlotRange(selected, day, from, to, mode)))
  }

  return (
    <div className="jmb-schedule">
      <div className="jmb-schedule-header">
        <div>
          <Typography.Text strong>Active schedule</Typography.Text>
          <div className="jmb-help">
            {windows.length
              ? `${windows.length} active window${windows.length === 1 ? '' : 's'}`
              : 'Always active'}
          </div>
        </div>
        {!readOnly && windows.length > 0 && (
          <Button size="small" onClick={() => onChange([])}>
            Clear
          </Button>
        )}
      </div>
      <div className="jmb-schedule-scroll">
        <div className="jmb-schedule-grid">
          <div className="jmb-schedule-scale">
            <span />
            <span>00:00</span>
            <span>06:00</span>
            <span>12:00</span>
            <span>24:00</span>
          </div>
          {SCHEDULE_DAYS.map(([day, label]) => (
            <div key={day} className="jmb-schedule-day">
              <span className="jmb-schedule-day-label">{label}</span>
              {Array.from({ length: SCHEDULE_SLOT_COUNT }, (_, slot) => {
                const active = selected[day]?.has(slot) ?? false
                return (
                  <button
                    key={slot}
                    type="button"
                    className={`jmb-schedule-slot${active ? ' active' : ''}`}
                    title={`${label} ${slotLabel(slot)}`}
                    aria-pressed={active}
                    disabled={readOnly}
                    onMouseDown={(e) => {
                      if (readOnly || e.button !== 0) return
                      e.preventDefault()
                      drag.current = { day, startSlot: slot, mode: active ? 'remove' : 'add' }
                      applyRange(day, slot, slot, drag.current.mode)
                    }}
                    onMouseEnter={() => {
                      if (!drag.current || drag.current.day !== day) return
                      applyRange(day, drag.current.startSlot, slot, drag.current.mode)
                    }}
                    onMouseUp={() => {
                      drag.current = null
                    }}
                  />
                )
              })}
            </div>
          ))}
        </div>
      </div>
      {windows.length > 0 && (
        <div className="jmb-schedule-summary">
          {windows.slice(0, 4).map((w, i) => (
            <span key={i}>
              {formatScheduleDays(w.days)} {w.start}-{w.end}
            </span>
          ))}
          {windows.length > 4 && <span>+{windows.length - 4} more</span>}
        </div>
      )}
    </div>
  )
}
