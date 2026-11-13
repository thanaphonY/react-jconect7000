import {SeriesWidget} from './SeriesWidget'
import {useRealtimeMetric} from './Realtime'
import {useEffect,useMemo,useRef} from 'react'
import {useQuery} from '@tanstack/react-query'
import {Alert,Progress,Space,Statistic,Table,Tag,Typography} from 'antd'
import * as echarts from 'echarts'
import {apiRequest} from './api/client'
import type {LayoutItem} from './features/dashboard/types'

type Row=Record<string,unknown>
type Latest={key:string;value:number|null;valueText:string|null;valueBool:boolean|null;valueJson:unknown;timestamp:string|null}
function record(v:unknown):Row{return v&&typeof v==='object'&&!Array.isArray(v)?v as Row:{}}
function binding(v:Row){return {deviceId:Number(v.device_id??v.deviceId??0),key:String(v.metric_key??v.metric??v.key??'')}}
function Chart({points,label,gauge,value,min,max,color}:{points?:Array<{timestamp:string;value:number|null}>;label:string;gauge?:boolean;value?:number;min?:number;max?:number;color?:string}){
 const ref=useRef<HTMLDivElement>(null)
 useEffect(()=>{if(!ref.current)return;const chart=echarts.init(ref.current);chart.setOption(gauge?{series:[{type:'gauge',min:min??0,max:max??100,progress:{show:true,itemStyle:{color:color??'#22c55e'}},axisLine:{lineStyle:{width:12}},detail:{fontSize:25,offsetCenter:[0,'60%']},data:value===undefined?[]:[{value,name:label}]}]}:{tooltip:{trigger:'axis'},grid:{left:45,right:15,top:20,bottom:40},xAxis:{type:'time'},yAxis:{type:'value',scale:true},series:[{type:'line',showSymbol:false,connectNulls:false,itemStyle:{color:color??'#0d6efd'},data:(points??[]).map(p=>[p.timestamp,p.value])}]});const observer=new ResizeObserver(()=>chart.resize());observer.observe(ref.current);return()=>{observer.disconnect();chart.dispose()}},[points,label,gauge,value,min,max,color]);return <div ref={ref} style={{height:'100%',minHeight:180,width:'100%'}}/>
}
function Metric({config,type,compact=false}:{config:Row;type:string;compact?:boolean}){
 const b=binding(config)
 useRealtimeMetric(b.deviceId,b.key)
 const latest=useQuery({queryKey:['widget-latest',b.deviceId],queryFn:()=>apiRequest<{latest:Latest[]}>(`/devices/${b.deviceId}/metrics/latest`),enabled:b.deviceId>0,refetchInterval:5000})
 const series=useQuery({queryKey:['widget-series',b.deviceId,b.key],queryFn:()=>apiRequest<{points:Array<{timestamp:string;value:number|null}>}>(`/devices/${b.deviceId}/series?key=${encodeURIComponent(b.key)}&limit=200`),enabled:b.deviceId>0&&!!b.key&&['line_chart','line_chart_dynamic','chart','bar_chart'].includes(type),refetchInterval:10000})
 if(!b.deviceId||!b.key)return <Typography.Text type="secondary">No metric configured</Typography.Text>
 if(latest.error)return <Alert type="error" message={latest.error.message}/>
 const row=latest.data?.latest.find(r=>r.key===b.key)
 const value=row?.valueBool??row?.valueText??row?.valueJson??row?.value
 const label=String(config.label??b.key),unit=String(config.unit??'')
 if(['line_chart','line_chart_dynamic','chart','bar_chart'].includes(type))return series.error?<Alert type="error" message={series.error.message}/>:<Chart points={series.data?.points} label={label}/>
 if(type==='gauge')return <Chart gauge label={label} value={typeof value==='number'?value:undefined} min={Number(config.min??0)} max={Number(config.max??100)} color={typeof config.color==='string'?config.color:undefined}/>
 if(type==='level_bar')return <Space direction="vertical" style={{width:'100%'}}><Typography.Text>{label}</Typography.Text><Progress percent={typeof value==='number'?Math.max(0,Math.min(100,(value-Number(config.min??0))/(Number(config.max??100)-Number(config.min??0))*100)):0} format={()=>value==null?'—':`${value} ${unit}`}/></Space>
 const maps=Array.isArray(config.value_maps)?config.value_maps.map(record):[]
 const mapped=maps.find(m=>String(m.value)===String(value))
 if(type==='status_grid'||typeof value==='boolean')return <Tag color={typeof mapped?.color==='string'?mapped.color:value===true?'green':'default'}>{mapped?.label?String(mapped.label):value==null?'No recorded value':String(value)}</Tag>
 if(compact)return <span>{value==null?'—':typeof value==='number'?value.toFixed(2):String(value)} {unit}</span>
 return <Statistic title={label} value={mapped?.label?String(mapped.label):value==null?'—':typeof value==='object'?JSON.stringify(value):String(value)} suffix={unit}/>
}
export function LiveWidget({item}:{item:{widgetType:string;type?:string;config:Record<string,unknown>}}){
 const type=item.widgetType||item.type||'',c=item.config
 const alarms=useQuery({queryKey:['alarms-active'],queryFn:()=>apiRequest<Array<{deviceId:number;severity:string}>>('/alarms/active'),enabled:type==='alarm_summary'||type==='zone_overview',refetchInterval:10000})
 const items=useMemo(()=>Array.isArray(c.items)?c.items.map(record):[],[c.items])
 if(['line_chart','line_chart_dynamic','chart','bar_chart'].includes(type))return <SeriesWidget config={c} bar={type==='bar_chart'}/>
 if(type==='power_meter')return <div className="widget-power-meter"><Metric config={{device_id:c.device_id,metric_key:c.power_key??'Active Power Total',label:'Active Power',unit:'kW'}} type="value_box"/><table style={{width:'100%'}}><thead><tr><th>Phase</th><th>Voltage</th><th>Current</th></tr></thead><tbody>{['r','s','t'].map(phase=><tr key={phase}><td>{phase.toUpperCase()}</td><td><Metric compact config={{device_id:c.device_id,metric_key:c[`voltage_${phase}_key`]??`Voltage ${phase.toUpperCase()}`,unit:'V'}} type="value_box"/></td><td><Metric compact config={{device_id:c.device_id,metric_key:c[`current_${phase}_key`]??`Current ${phase.toUpperCase()}`,unit:'A'}} type="value_box"/></td></tr>)}</tbody></table></div>
 if(type==='ratio'||type==='value_box2')return <Space size={24}><Metric config={{device_id:c.device_id1,metric_key:c.metric_key1,label:c.label1,unit:c.unit1}} type="value_box"/><Metric config={{device_id:c.device_id2,metric_key:c.metric_key2,label:c.label2,unit:c.unit2}} type="value_box"/></Space>
 if(type==='temp_hum_sensor'||type==='temp_hum')return <Space size={24}><Metric config={{device_id:c.device_id,metric_key:c.temp_key,label:'Temperature',unit:'°C'}} type="value_box"/><Metric config={{device_id:c.device_id,metric_key:c.hum_key,label:'Humidity',unit:'%'}} type="value_box"/></Space>
 if(type==='alarm_summary'||type==='zone_overview'){if(alarms.error)return <Alert type="error" message={alarms.error.message}/>;const allowed=Array.isArray(c.alarm_devices)?c.alarm_devices.map(v=>Number(typeof v==='object'?record(v).device_id:v)):[];const rows=(alarms.data??[]).filter(a=>allowed.length===0||allowed.includes(a.deviceId));return <Space wrap size={24}><Statistic title="Active Alarms" value={rows.length}/>{['critical','warning','info'].map(severity=><Statistic key={severity} title={severity} value={rows.filter(a=>a.severity.toLowerCase()===severity).length}/>)}</Space>}
 if(type==='navigator')return <Space>{items.map((v,i)=><a key={i} href={String(v.layout_id?`/dashboard/view/${v.layout_id}`:v.url??v.link_url??'#')}>{String(v.label??v.name??'Open')}</a>)}</Space>
 if(type==='table')return <Table<Row & {id:number}> pagination={false} size="small" dataSource={items.map((v,i)=>({...v,id:i}))} rowKey="id" columns={[{title:'Metric',render:(_,r)=>String(r.label??binding(r).key)},{title:'Value',render:(_,r)=><Metric config={r} type="value_box"/>}]}/>
 if(items.length)return <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(140px,1fr))',gap:12}}>{items.map((v,i)=><Metric key={i} config={v} type={type==='status_grid'?'status_grid':'value_box'}/>)}</div>
 return <Metric config={c} type={type}/>
}
