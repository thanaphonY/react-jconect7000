import type { Metric } from './types'

export const PEMON_OID_ROOT = '1.3.6.1.4.1.12807.3.1.1'
export type PemonDefinition = { key: string; label: string; suffix: string; unit: string; scale: number; source: 'decoded' | 'comparison' }
// Evidence: PEMON Test/data/last-snmp.json (decoded.mapped) and
// last-snmp-ui-compare.json (checks). These are a starter mapping, not a full MIB.
// No sys.name (text) or zero-only samples with unverified scale are generated.
export const PEMON_METRICS: PemonDefinition[] = [
 {key:'dc.high_float_v',label:'DC High Float Voltage',suffix:'1.1',unit:'V',scale:0.01,source:'decoded'},
 {key:'dc.low_discharge_v',label:'DC Low Discharge Voltage',suffix:'1.2',unit:'V',scale:0.01,source:'decoded'},
 {key:'dc.low_float_v',label:'DC Low Float Voltage',suffix:'1.3',unit:'V',scale:0.01,source:'decoded'},
 {key:'ac.high_freq_hz',label:'AC High Frequency',suffix:'1.7',unit:'Hz',scale:0.1,source:'decoded'},
 {key:'ac.low_freq_hz',label:'AC Low Frequency',suffix:'1.8',unit:'Hz',scale:0.1,source:'decoded'},
 {key:'ac.high_voltage_v',label:'AC High Voltage',suffix:'1.9',unit:'V',scale:0.1,source:'decoded'},
 {key:'ac.low_voltage_v',label:'AC Low Voltage',suffix:'1.10',unit:'V',scale:0.1,source:'decoded'},
 {key:'ac.high_current_a',label:'AC High Current',suffix:'1.11',unit:'A',scale:0.1,source:'decoded'},
 {key:'ac.high_power',label:'AC High Power',suffix:'1.13',unit:'',scale:0.001,source:'comparison'},
 {key:'ac.low_power',label:'AC Low Power',suffix:'1.14',unit:'',scale:0.001,source:'comparison'},
 {key:'pemon.temperature',label:'Temperature 1',suffix:'2.1',unit:'°C',scale:0.1,source:'comparison'},
 {key:'pemon.humidity',label:'Humidity 1',suffix:'2.2',unit:'%',scale:1,source:'comparison'},
 {key:'snmp.block2.vstr_v',label:'DC String Voltage 1',suffix:'2.3',unit:'V',scale:0.01,source:'decoded'},
 {key:'snmp.block2.cell1_v',label:'DC Cell Reading 1',suffix:'2.5',unit:'V',scale:0.001,source:'decoded'},
 {key:'snmp.block2.cell2_v',label:'DC Cell Reading 2',suffix:'2.6',unit:'V',scale:0.001,source:'decoded'},
 {key:'pemon.ac_line1_voltage',label:'AC Line 1 Voltage',suffix:'2.8',unit:'V',scale:0.1,source:'comparison'},
 {key:'pemon.ac_line1_current',label:'AC Line 1 Current',suffix:'2.9',unit:'A',scale:0.1,source:'comparison'},
]
export function pemonOID(definition: PemonDefinition): string { return `${PEMON_OID_ROOT}.${definition.suffix}.0` }
function normalizeOID(value: unknown): string { return String(value ?? '').trim().replace(/^\.+/, '') }
export function pemonConflict(existing: Metric[], definition: PemonDefinition): boolean {
 return existing.some(m => m.key === definition.key || normalizeOID(m.oid ?? m.OID ?? m.snmp_oid) === pemonOID(definition))
}
export function appendPemonMetrics(existing: Metric[], keys: string[], pollSeconds: number): { metrics: Metric[]; added: number; skipped: number } {
 if (!Number.isInteger(pollSeconds) || pollSeconds < 5 || pollSeconds > 3600) throw new Error('Polling interval must be an integer between 5 and 3600 seconds')
 const metrics = [...existing]
 let added = 0, skipped = 0
 const selected = new Set(keys)
 for (const definition of PEMON_METRICS) {
  if (!selected.has(definition.key)) continue
  if (pemonConflict(metrics, definition)) { skipped++; continue }
  metrics.push({key:definition.key,label:definition.label,oid:pemonOID(definition),unit:definition.unit,scale:definition.scale,type:'snmp_get',protocol:'snmp',datatype:'float',enable:true,pull_rate:pollSeconds,store_policy:'always',triggers:[],normal_condition:[],description:'PEMON starter mapping; verify OID and scale for this device model.'})
  added++
 }
 return {metrics,added,skipped}
}
