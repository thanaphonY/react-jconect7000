import {useEffect,useMemo,useRef,useState} from 'react'
import {useQueries} from '@tanstack/react-query'
import {Alert,Select,Space,Table,Typography} from 'antd'
import * as echarts from 'echarts'
import {apiRequest} from './api/client'
type Config=Record<string,unknown>
type Point={timestamp:string;value:number|null}
export function SeriesWidget({config,bar=false}:{config:Config;bar?:boolean}){
 const [range,setRange]=useState(String(config.time_range??'24h')),[interval,setInterval]=useState(String(config.time_interval??'1h')),ref=useRef<HTMLDivElement>(null)
 const items=useMemo(()=>Array.isArray(config.items)?config.items.filter((v):v is Config=>!!v&&typeof v==='object').map(v=>({id:Number(v.device_id??v.deviceId),key:String(v.metric_key??v.key??''),label:String(v.label??v.metric_key??v.key??'')})).filter(v=>v.id>0&&v.key):[],[config.items])
 const queries=useQueries({queries:items.map(b=>({queryKey:['widget-series',b.id,b.key,range,interval],queryFn:()=>apiRequest<{points:Point[]}>(`/devices/${b.id}/series?key=${encodeURIComponent(b.key)}&range=${encodeURIComponent(range)}&interval=${encodeURIComponent(interval)}&limit=1000`),refetchInterval:15000}))})
 const signature=JSON.stringify(queries.map(q=>q.data?.points??[]))
 useEffect(()=>{if(!ref.current)return;const chart=echarts.init(ref.current);const dark=document.documentElement.getAttribute('data-bs-theme')==='dark';chart.setOption({textStyle:{color:dark?'#dee2e6':'#212529',fontFamily:'Sarabun'},tooltip:{trigger:'axis'},legend:{bottom:0,textStyle:{color:dark?'#dee2e6':'#212529'}},grid:{left:50,right:20,top:20,bottom:65},xAxis:{type:'time',name:String(config.xTitle??'')},yAxis:{type:'value',name:String(config.yTitle??''),scale:true,min:config.yMin===''?undefined:config.yMin,max:config.yMax===''?undefined:config.yMax},series:items.map((b,i)=>({name:b.label,type:bar?'bar':'line',showSymbol:false,connectNulls:false,data:(queries[i].data?.points??[]).map(p=>[p.timestamp,p.value])}))});const o=new ResizeObserver(()=>chart.resize());o.observe(ref.current);return()=>{o.disconnect();chart.dispose()}},[signature,items,bar,config.xTitle,config.yTitle,config.yMin,config.yMax])
 if(!items.length)return <Typography.Text type="secondary">No metrics configured</Typography.Text>
 const errors=queries.flatMap(q=>q.error?[q.error.message]:[])
 const summary=items.map((b,i)=>{const values=(queries[i].data?.points??[]).flatMap(p=>p.value==null?[]:[p.value]);return {key:`${b.id}:${b.key}`,name:b.label,min:values.length?Math.min(...values):null,max:values.length?Math.max(...values):null,average:values.length?values.reduce((a,v)=>a+v,0)/values.length:null,last:values.at(-1)??null}})
 return <div><Space wrap><Select size="small" value={range} onChange={setRange} options={['6h','12h','24h','7d','30d'].map(value=>({value,label:`Last ${value}`}))}/><Select size="small" value={interval} onChange={setInterval} options={['1m','5m','15m','30m','1h','4h','1d'].map(value=>({value}))}/></Space>{errors.map((e,i)=><Alert key={i} type="error" message={e}/>)}<div ref={ref} style={{height:260,width:'100%'}}/><Table size="small" pagination={false} dataSource={summary} columns={[{title:'Metric',dataIndex:'name'},...['min','max','average','last'].map(key=>({title:key,dataIndex:key,render:(v:number|null)=>v==null?'—':v.toFixed(2)}))]}/></div>
}
