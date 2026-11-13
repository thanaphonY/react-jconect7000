import type { FieldOption } from './types'

/** Ported from PHP edit_layout.php WIDGET_ICON_OPTIONS */
export const WIDGET_ICON_OPTIONS: FieldOption[] = [
  { label: 'Circle (default)', value: 'bi-circle' },
  { label: 'Dot', value: 'bi-dot' },
  { label: 'Fan / Cooling', value: 'bi-fan' },
  { label: 'Arrow Repeat (spin)', value: 'bi-arrow-repeat' },
  { label: 'Play', value: 'bi-play-circle' },
  { label: 'Pause', value: 'bi-pause-circle' },
  { label: 'Stop', value: 'bi-stop-circle' },
  { label: 'Record / Active', value: 'bi-record-circle' },
  { label: 'Check / OK', value: 'bi-check-circle' },
  { label: 'Error / X', value: 'bi-x-circle' },
  { label: 'Warning', value: 'bi-exclamation-triangle' },
  { label: 'Shield OK', value: 'bi-shield-check' },
  { label: 'Shield Alert', value: 'bi-shield-exclamation' },
  { label: 'Bell / Alarm', value: 'bi-bell' },
  { label: 'Toggle On', value: 'bi-toggle-on' },
  { label: 'Toggle Off', value: 'bi-toggle-off' },
  { label: 'Hourglass / Waiting', value: 'bi-hourglass-split' },
  { label: 'Temperature', value: 'bi-thermometer-half' },
  { label: 'Humidity', value: 'bi-moisture' },
  { label: 'Water / Liquid', value: 'bi-droplet' },
  { label: 'Airflow', value: 'bi-wind' },
  { label: 'Heat / Fire', value: 'bi-fire' },
  { label: 'Power', value: 'bi-power' },
  { label: 'Voltage / Energy', value: 'bi-lightning-charge' },
  { label: 'Voltage (solid)', value: 'bi-lightning-fill' },
  { label: 'AC / Plug', value: 'bi-plug' },
  { label: 'Outlet', value: 'bi-outlet' },
  { label: 'DC / Battery', value: 'bi-battery' },
  { label: 'DC Charging', value: 'bi-battery-charging' },
  { label: 'Battery Full', value: 'bi-battery-full' },
  { label: 'Waveform ≈ Sine', value: 'bi-activity' },
  { label: 'Soundwave / AC wave', value: 'bi-soundwave' },
  { label: '± / Polarity', value: 'bi-plus-slash-minus' },
  { label: 'Bidirectional / Transfer', value: 'bi-arrow-left-right' },
  { label: 'Thunderbolt', value: 'bi-thunderbolt' },
  { label: 'EV / Charging Station', value: 'bi-ev-station' },
  { label: 'Gauge / Frequency', value: 'bi-speedometer2' },
  { label: 'Trend / Load', value: 'bi-graph-up' },
  { label: 'CPU / Device', value: 'bi-cpu' },
  { label: 'Server Rack', value: 'bi-hdd-rack' },
  { label: 'Server', value: 'bi-server' },
  { label: 'Building', value: 'bi-building' },
  { label: 'House', value: 'bi-house-door' },
  { label: 'Machine / Gear', value: 'bi-gear-wide-connected' },
  { label: 'Antenna / Signal', value: 'bi-broadcast' },
  { label: 'Signal Strength', value: 'bi-reception-4' },
  { label: 'WiFi', value: 'bi-wifi' },
  { label: 'WiFi Off', value: 'bi-wifi-off' },
  { label: 'Hazard', value: 'bi-radioactive' },
  { label: 'Compass / Navigate', value: 'bi-compass' },
  { label: 'Map', value: 'bi-map' },
  { label: 'Dashboard Grid', value: 'bi-grid-1x2' },
]

/** Ported from frontend/assets/js/widgets/navigator.js NAVIGATOR_ICONS */
export const NAVIGATOR_ICONS: FieldOption[] = [
  { label: 'Dashboard', value: 'bi-grid-1x2' },
  { label: 'Compass', value: 'bi-compass' },
  { label: 'Map', value: 'bi-map' },
  { label: 'Building', value: 'bi-building' },
  { label: 'House', value: 'bi-house-door' },
  { label: 'Server Rack', value: 'bi-hdd-rack' },
  { label: 'Server', value: 'bi-server' },
  { label: 'Power', value: 'bi-lightning-charge' },
  { label: 'Cooling', value: 'bi-fan' },
  { label: 'Temperature', value: 'bi-thermometer-half' },
  { label: 'Water', value: 'bi-droplet' },
  { label: 'CPU', value: 'bi-cpu' },
  { label: 'Link', value: 'bi-box-arrow-up-right' },
]

export function normalizeBiIconClass(name: string | null | undefined): string {
  const n = String(name || 'bi-circle').trim()
  if (!n) return 'bi bi-circle'
  if (n.startsWith('bi bi-')) return n
  if (n.startsWith('bi-')) return `bi ${n}`
  return `bi bi-${n}`
}
