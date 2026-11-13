import {createContext,useCallback,useContext,useEffect,useRef,type ReactNode} from 'react'
import {useQueryClient} from '@tanstack/react-query'
type Binding={device_id:number;metric_key:string}
type Latest={key:string;value:number|null;valueText:string|null;valueBool:boolean|null;valueJson:unknown;timestamp:string|null}
const Context=createContext<(b:Binding)=>()=>void>(()=>()=>{})
export function Realtime({children}:{children:ReactNode}){
 const qc=useQueryClient(),socket=useRef<WebSocket|null>(null),bindings=useRef(new Map<string,{binding:Binding;count:number}>())
 const subscribe=useCallback((binding:Binding)=>{const key=`${binding.device_id}|${binding.metric_key}`,old=bindings.current.get(key);bindings.current.set(key,{binding,count:(old?.count??0)+1});if(!old&&socket.current?.readyState===WebSocket.OPEN)socket.current.send(JSON.stringify({type:'subscribe',metrics:[binding]}));return()=>{const value=bindings.current.get(key);if(!value)return;if(value.count>1){value.count--;return};bindings.current.delete(key);if(socket.current?.readyState===WebSocket.OPEN)socket.current.send(JSON.stringify({type:'unsubscribe',metrics:[binding]}))}},[])
 useEffect(()=>{let closed=false,timer:ReturnType<typeof setTimeout>|undefined,attempt=0;const connect=()=>{if(closed)return;const s=new WebSocket(`${location.protocol==='https:'?'wss:':'ws:'}//${location.host}/ws`);socket.current=s;s.onopen=()=>{attempt=0;const metrics=[...bindings.current.values()].map(v=>v.binding);if(metrics.length)s.send(JSON.stringify({type:'subscribe',metrics}))};s.onmessage=e=>{try{const values:unknown=JSON.parse(String(e.data));if(!Array.isArray(values))return;for(const raw of values){const r=raw as Record<string,unknown>,id=Number(r.device_id),key=String(r.metric_key??'');if(!bindings.current.has(`${id}|${key}`))continue;for (const cacheKey of ['widget-latest', 'device-metrics-latest']) qc.setQueryData<{deviceId:number;latest:Latest[]}>([cacheKey,id],old=>{if (cacheKey === 'device-metrics-latest' && !old) return old;const row:Latest={key,value:typeof r.value==='number'?r.value:null,valueText:typeof r.value==='string'?r.value:null,valueBool:typeof r.value==='boolean'?r.value:null,valueJson:r.value&&typeof r.value==='object'?r.value:null,timestamp:typeof r.timestamp==='string'?r.timestamp:null};return {deviceId:id,latest:[...(old?.latest??[]).filter(x=>x.key!==key),row]}})}}catch{/* Polling continues if an upstream message is malformed. */}};s.onclose=()=>{if(!closed)timer=setTimeout(connect,Math.min(30000,1000*2**Math.min(attempt++,5)))};s.onerror=()=>s.close()};connect();return()=>{closed=true;clearTimeout(timer);socket.current?.close();socket.current=null}},[qc])
 return <Context.Provider value={subscribe}>{children}</Context.Provider>
}
export function useRealtimeMetric(deviceId:number,key:string){const subscribe=useContext(Context);useEffect(()=>{if(deviceId>0&&key)return subscribe({device_id:deviceId,metric_key:key})},[subscribe,deviceId,key])}

export function useRealtimeMetrics(deviceId: number, keys: string[]) {
  const subscribe = useContext(Context)
  const subscription = JSON.stringify([...new Set(keys)].sort())
  useEffect(() => {
    const metricKeys: string[] = JSON.parse(subscription)
    const cleanups = metricKeys.slice(0, 500).map((metric_key) => subscribe({ device_id: deviceId, metric_key }))
    return () => cleanups.forEach((cleanup) => cleanup())
  }, [deviceId, subscribe, subscription])
}
