/** Ported from frontend/assets/js/jconnect-metric-builder.js schedule helpers. */

export const SCHEDULE_DAYS: Array<[number, string]> = [
  [0, 'Sun'],
  [1, 'Mon'],
  [2, 'Tue'],
  [3, 'Wed'],
  [4, 'Thu'],
  [5, 'Fri'],
  [6, 'Sat'],
]

export const SCHEDULE_SLOT_MINUTES = 30
export const SCHEDULE_SLOT_COUNT = 48

export type DurationWindow = { days?: number[]; start?: string; end?: string }

export type ScheduleSlots = Record<number, Set<number>>

function timeToMinutes(value: string | undefined, fallback: number): number {
  const match = String(value ?? '')
    .trim()
    .match(/^(\d{1,2}):([0-5]\d)$/)
  if (!match) return fallback
  const hour = Number(match[1])
  const minutes = Number(match[2])
  return hour >= 0 && hour <= 23 ? hour * 60 + minutes : fallback
}

function minutesToTime(minutes: number): string {
  const safe = Math.max(0, Math.min(23 * 60 + 59, Math.round(minutes)))
  return `${String(Math.floor(safe / 60)).padStart(2, '0')}:${String(safe % 60).padStart(2, '0')}`
}

export function emptyScheduleSlots(): ScheduleSlots {
  return SCHEDULE_DAYS.reduce<ScheduleSlots>((slots, day) => {
    slots[day[0]] = new Set()
    return slots
  }, {})
}

export function durationToScheduleSlots(windows: DurationWindow[] | undefined): ScheduleSlots {
  const selected = emptyScheduleSlots()
  ;(Array.isArray(windows) ? windows : []).forEach((windowItem) => {
    const startMinutes = timeToMinutes(windowItem.start, 0)
    const endMinutes = timeToMinutes(windowItem.end, 23 * 60 + 59)
    const startSlot = Math.max(
      0,
      Math.min(SCHEDULE_SLOT_COUNT - 1, Math.floor(startMinutes / SCHEDULE_SLOT_MINUTES)),
    )
    const endSlotExclusive = Math.max(
      1,
      Math.min(SCHEDULE_SLOT_COUNT, Math.ceil((endMinutes + 1) / SCHEDULE_SLOT_MINUTES)),
    )
    const days = Array.isArray(windowItem.days) ? windowItem.days.map(Number) : []
    days.forEach((day) => {
      if (!selected[day]) return
      if (startMinutes <= endMinutes) {
        for (let slot = startSlot; slot < endSlotExclusive; slot += 1) selected[day].add(slot)
      } else {
        for (let slot = startSlot; slot < SCHEDULE_SLOT_COUNT; slot += 1) selected[day].add(slot)
        for (let slot = 0; slot < endSlotExclusive; slot += 1) selected[day].add(slot)
      }
    })
  })
  return selected
}

export function scheduleSlotsToDuration(selected: ScheduleSlots): DurationWindow[] {
  const byRange: Record<string, DurationWindow> = {}
  SCHEDULE_DAYS.forEach((day) => {
    const slots = Array.from(selected[day[0]] || []).sort((a, b) => a - b)
    let cursor = 0
    while (cursor < slots.length) {
      const startSlot = slots[cursor]
      let endSlot = startSlot
      cursor += 1
      while (cursor < slots.length && slots[cursor] === endSlot + 1) {
        endSlot = slots[cursor]
        cursor += 1
      }
      const start = minutesToTime(startSlot * SCHEDULE_SLOT_MINUTES)
      const end = minutesToTime(
        Math.min(23 * 60 + 59, (endSlot + 1) * SCHEDULE_SLOT_MINUTES - 1),
      )
      const key = `${start}|${end}`
      if (!byRange[key]) byRange[key] = { days: [], start, end }
      byRange[key].days!.push(day[0])
    }
  })
  return Object.values(byRange)
}

export function formatScheduleDays(days: number[] | undefined): string {
  const set = new Set((days || []).map(Number))
  return SCHEDULE_DAYS.filter(([d]) => set.has(d))
    .map(([, label]) => label)
    .join(', ')
}

export function slotLabel(slot: number): string {
  const start = minutesToTime(slot * SCHEDULE_SLOT_MINUTES)
  const end = minutesToTime(
    Math.min(23 * 60 + 59, (slot + 1) * SCHEDULE_SLOT_MINUTES - 1),
  )
  return `${start}-${end}`
}

export function toggleSlotRange(
  selected: ScheduleSlots,
  day: number,
  fromSlot: number,
  toSlot: number,
  mode: 'add' | 'remove',
): ScheduleSlots {
  const next: ScheduleSlots = {}
  SCHEDULE_DAYS.forEach(([d]) => {
    next[d] = new Set(selected[d] || [])
  })
  const start = Math.min(fromSlot, toSlot)
  const end = Math.max(fromSlot, toSlot)
  for (let slot = start; slot <= end; slot += 1) {
    if (mode === 'add') next[day].add(slot)
    else next[day].delete(slot)
  }
  return next
}
