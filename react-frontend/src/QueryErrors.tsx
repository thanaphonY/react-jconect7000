import {useEffect,useState} from 'react'
import {useQueryClient} from '@tanstack/react-query'
import {Alert} from 'antd'
export function QueryErrors(){const qc=useQueryClient();const [errors,setErrors]=useState<string[]>([]);useEffect(()=>qc.getQueryCache().subscribe(()=>{const messages=qc.getQueryCache().findAll({type:'active'}).filter(q=>q.state.status==='error').map(q=>q.state.error instanceof Error?q.state.error.message:'API request failed');setErrors([...new Set(messages)])}),[qc]);return <>{errors.map(e=><Alert key={e} type="error" showIcon message={e} style={{marginBottom:12}}/>)}</>}
