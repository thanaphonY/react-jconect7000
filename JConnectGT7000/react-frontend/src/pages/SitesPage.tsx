import {useMemo,useState} from 'react'
import {useQuery,useMutation,useQueryClient} from '@tanstack/react-query'
import {App,Alert,Breadcrumb,Button,Card,Dropdown,Form,Input,InputNumber,Modal,Select,Space,Table,Tag,Typography} from 'antd'
import {Link,useNavigate,useParams} from 'react-router'
import {apiRequest} from '../api/client'
import './sites.css'

type Node={id:number;parentId:number|null;name:string;nodeType:string;sortOrder:number;latitude:number|null;longitude:number|null;dashboardLayoutId:number|null;devicesCount:number}
const types=['Main','Region','Site','Building','Room']
const nodeIcons:Record<string,string>={Main:'bi-globe2',Region:'bi-map',Site:'bi-geo-alt-fill',Building:'bi-building',Room:'bi-door-open'}

export function SitesPage(){
 const nav=useNavigate(),{message,modal}=App.useApp(),qc=useQueryClient(),[form]=Form.useForm<Node>()
 const q=useQuery({queryKey:['site-nodes'],queryFn:()=>apiRequest<Node[]>('/site-nodes')})
 const [search,setSearch]=useState(''),[type,setType]=useState(''),[editing,setEditing]=useState<Node|null|undefined>()
 const [expanded,setExpanded]=useState<Set<number>|null>(null),[tab,setTab]=useState('tree')
 const rows=q.data??[]
 const index=useMemo(()=>{
  const byId=new Map(rows.map(n=>[n.id,n])),children=new Map<number,Node[]>()
  for(const n of rows){if(n.parentId!==null&&byId.has(n.parentId)){const list=children.get(n.parentId)??[];list.push(n);children.set(n.parentId,list)}}
  const roots=rows.filter(n=>n.parentId===null||!byId.has(n.parentId))
  const paths=new Map<number,string>(),counts=new Map<number,number>()
  function visit(n:Node,parent:string,seen:Set<number>):number{
   if(seen.has(n.id))return 0
   paths.set(n.id,parent?`${parent} › ${n.name}`:n.name)
   const next=new Set([...seen,n.id]);let count=n.devicesCount||0
   for(const c of children.get(n.id)??[])count+=visit(c,paths.get(n.id)!,next)
   counts.set(n.id,count);return count
  }
  roots.forEach(n=>visit(n,'',new Set()))
  return {byId,children,roots,paths,counts}
 },[rows])
 const query=search.toLowerCase().trim(),filtering=Boolean(query||type)
 const matches=rows.filter(n=>(!query||`${index.paths.get(n.id)??n.name} ${n.nodeType}`.toLowerCase().includes(query))&&(!type||n.nodeType===type))
 const visible=new Set(matches.map(n=>n.id)),forcedOpen=new Set<number>()
 for(const n of matches){let parent=n.parentId;const seen=new Set<number>();while(parent!==null&&index.byId.has(parent)&&!seen.has(parent)){seen.add(parent);visible.add(parent);if(filtering)forcedOpen.add(parent);parent=index.byId.get(parent)!.parentId}}
 const siteRows=rows.filter(n=>n.nodeType==='Site'),filteredSites=matches.filter(n=>n.nodeType==='Site')
 const refresh=()=>qc.invalidateQueries({queryKey:['site-nodes']})
 const save=useMutation({mutationFn:(b:Node)=>apiRequest(editing?`/site-nodes/${editing.id}`:'/site-nodes',{method:editing?'PUT':'POST',body:b}),onSuccess:()=>{setEditing(undefined);void refresh();message.success('Saved')},onError:(e:Error)=>message.error(e.message)})
 const remove=useMutation({mutationFn:(id:number)=>apiRequest(`/site-nodes/${id}`,{method:'DELETE'}),onSuccess:()=>{void refresh();message.success('Node deleted')},onError:(e:Error)=>message.error(e.message)})
 function edit(n:Node){form.resetFields();form.setFieldsValue(n);setEditing(n)}
 function add(parent?:Node){form.resetFields();form.setFieldsValue({sortOrder:0,nodeType:parent?types[Math.min(types.indexOf(parent.nodeType)+1,4)]:'Main',parentId:parent?.id??null});setEditing(null)}
 function actions(n:Node){return <Dropdown trigger={['click']} placement="bottomRight" menu={{items:[
  {key:'edit',label:'Edit',icon:<i className="bi bi-pencil"/>},
  ...(n.nodeType==='Room'?[]:[{key:'child',label:'Add child',icon:<i className="bi bi-plus-lg"/>}]),
  ...(n.nodeType==='Site'?[{key:'open',label:'Open site',icon:<i className="bi bi-box-arrow-up-right"/>}]:[]),
  {type:'divider' as const},{key:'delete',label:'Delete',danger:true,icon:<i className="bi bi-trash"/>}
 ],onClick:({key})=>{if(key==='edit')edit(n);if(key==='child')add(n);if(key==='open')nav(`/site/view/${n.id}`);if(key==='delete')modal.confirm({title:'Delete this node?',content:n.name,okText:'Delete',okButtonProps:{danger:true},onOk:()=>remove.mutateAsync(n.id)})}}}><Button className="site-action" size="small" aria-label={`Actions for ${n.name}`}>Actions <i className="bi bi-caret-down-fill"/></Button></Dropdown>}
 function tree(nodes:Node[],depth=0,seen=new Set<number>()){
  return <ul className={depth===0?'site-hierarchy-root':'site-hierarchy-children'}>{nodes.filter(n=>visible.has(n.id)&&!seen.has(n.id)).map(n=>{
   const children=index.children.get(n.id)??[],hasChildren=children.length>0
   const open=expanded?expanded.has(n.id):(forcedOpen.has(n.id)||depth===0)
   return <li key={n.id}><div className={`site-node-row ${hasChildren?'site-node-branch':''}`}>
    <div className="site-node-main">{hasChildren&&<button type="button" className="site-node-toggle" aria-expanded={open} aria-label={`${open?'Collapse':'Expand'} ${n.name}`} onClick={()=>setExpanded(prev=>{const next=new Set(prev??[...index.roots.map(r=>r.id),...forcedOpen]);if(open)next.delete(n.id);else next.add(n.id);return next})}><i className={`bi bi-chevron-${open?'down':'right'}`}/></button>}
    <i className={`bi ${nodeIcons[n.nodeType]??'bi-diagram-3'} site-node-icon`} aria-hidden="true"/>
    <span className="site-node-name">{n.name}</span><span className={`site-type-badge site-type-${n.nodeType.toLowerCase()}`}>{n.nodeType}</span>
    {(index.counts.get(n.id)??0)>0&&<span className="site-device-badge" title="Devices in this branch"><i className="bi bi-hdd-network"/> {index.counts.get(n.id)} {(index.counts.get(n.id)??0)===1?'device':'devices'}</span>}
    </div>{actions(n)}</div>
    {hasChildren&&open&&tree(children,depth+1,new Set([...seen,n.id]))}</li>
  })}</ul>
 }
 return <div className="php-site-management">
  <div className="site-page-heading"><div><Typography.Title level={5}><i className="bi bi-diagram-3"/> Site Management</Typography.Title><Breadcrumb items={[{title:<Link to="/home">Home</Link>},{title:'Site Management'}]}/><p className="site-summary">{rows.length} nodes · {siteRows.length} sites</p></div><Space size={12}><Button className="site-scope" onClick={()=>nav('/site/access')}><i className="bi bi-people"/> User scope</Button><Button type="primary" onClick={()=>add()}><i className="bi bi-plus-lg"/> Add node</Button></Space></div>
  {q.isError&&<Alert type="error" showIcon message="Unable to load sites" description={q.error.message} action={<Button onClick={()=>void q.refetch()}>Retry</Button>}/>}
  <Card className="site-management-card" loading={q.isLoading}>
   {!rows.length&&!q.isLoading&&!q.isError?<div className="site-empty"><i className="bi bi-diagram-3"/><p>No sites are assigned to this user.</p></div>:<>
   <div className="site-toolbar"><div><label htmlFor="site-tree-search">Search</label><Input id="site-tree-search" type="search" placeholder="Name or type…" autoComplete="off" allowClear value={search} onChange={e=>{setSearch(e.target.value);setExpanded(null)}}/></div><div><label htmlFor="site-type-filter">Type</label><select id="site-type-filter" value={type} onChange={e=>{setType(e.target.value);setExpanded(null)}}><option value="">All types</option>{types.map(t=><option key={t}>{t}</option>)}</select></div><div className="site-tree-tools"><Button size="small" onClick={()=>setExpanded(new Set(rows.map(n=>n.id)))}>Expand all</Button><Button size="small" onClick={()=>setExpanded(new Set(index.roots.map(n=>n.id)))}>Collapse</Button>{filtering&&<span aria-live="polite">{matches.length} nodes</span>}</div></div>
   <div className="site-view-tabs" role="tablist" aria-label="Site views"><button id="site-tree-tab" type="button" role="tab" aria-selected={tab==='tree'} aria-controls="site-tree-panel" onClick={()=>setTab('tree')}>Hierarchy</button><button id="site-sites-tab" type="button" role="tab" aria-selected={tab==='sites'} aria-controls="site-sites-panel" onClick={()=>setTab('sites')}>Sites ({siteRows.length})</button></div>
   <div id="site-tree-panel" role="tabpanel" aria-labelledby="site-tree-tab" className="site-tree-scroll" hidden={tab!=='tree'}>{matches.length?tree(index.roots):<p className="site-empty">No matching nodes.</p>}</div>
   <div id="site-sites-panel" role="tabpanel" aria-labelledby="site-sites-tab" className="site-tree-scroll" hidden={tab!=='sites'}><Table size="small" rowKey="id" pagination={false} dataSource={filteredSites} columns={[{title:'Site',dataIndex:'name',render:v=><strong>{v}</strong>},{title:'Path',render:(_,n)=>{const path=(index.paths.get(n.id)??'').split(' › ').slice(0,-1).join(' › ');return <span className="site-table-path" title={path}>{path||'—'}</span>}},{title:'Actions',align:'right',width:120,render:(_,n)=><Space size={4}><Button size="small" aria-label={`Edit ${n.name}`} title="Edit" icon={<i className="bi bi-pencil"/>} onClick={()=>edit(n)}/><Button size="small" aria-label={`Open ${n.name}`} title="Open" icon={<i className="bi bi-box-arrow-up-right"/>} onClick={()=>nav(`/site/view/${n.id}`)}/></Space>}]}/></div>
   </>}
  </Card>
  <Modal title={editing?'Edit node':'Add node'} open={editing!==undefined} onCancel={()=>setEditing(undefined)} onOk={()=>form.submit()} confirmLoading={save.isPending}><Form form={form} layout="vertical" onFinish={v=>save.mutate(v)} initialValues={{sortOrder:0,nodeType:'Main'}}><Form.Item name="name" label="Name" rules={[{required:true}]}><Input/></Form.Item><Form.Item name="nodeType" label="Type" rules={[{required:true}]}><Select options={types.map(value=>({value}))}/></Form.Item><Form.Item name="parentId" label="Parent"><Select allowClear options={rows.filter(n=>n.id!==editing?.id).map(n=>({value:n.id,label:`${n.name} (${n.nodeType})`}))}/></Form.Item><Form.Item name="sortOrder" label="Sort order"><InputNumber/></Form.Item><Form.Item name="latitude" label="Latitude"><InputNumber min={-90} max={90}/></Form.Item><Form.Item name="longitude" label="Longitude"><InputNumber min={-180} max={180}/></Form.Item><Form.Item name="dashboardLayoutId" label="Dashboard layout ID"><InputNumber min={1}/></Form.Item></Form></Modal>
 </div>
}
export function SiteViewPage(){const {id}=useParams();const q=useQuery({queryKey:['site-node',id],queryFn:()=>apiRequest<{node:Node;children:Node[];devices:Array<{id:number;name:string;isActive:boolean;location:string}>}>(`/site-nodes/${id}`)});return <Space direction="vertical" style={{width:'100%'}}><Typography.Title level={4}>{q.data?.node.name??'Site'}</Typography.Title><Link to="/site">← Site Management</Link><Card title="Child nodes" loading={q.isLoading}>{q.data?.children.map(n=><p key={n.id}><Link to={`/site/view/${n.id}`}>{n.name}</Link> <Tag>{n.nodeType}</Tag></p>)}</Card><Card title="Devices"><Table rowKey="id" dataSource={q.data?.devices??[]} columns={[{title:'Name',render:(_,r)=><Link to={`/device/view/${r.id}`}>{r.name}</Link>},{title:'Location',dataIndex:'location'},{title:'Status',render:(_,r)=><Tag color={r.isActive?'green':'default'}>{r.isActive?'Active':'Inactive'}</Tag>}]}/></Card></Space>}
export function SiteAccessPage(){const q=useQuery({queryKey:['site-access'],queryFn:()=>apiRequest<Array<{id:number;name:string;email:string;accessLevel:string;siteIds:number[]}>>('/site-access')});return <Card title="User scope" extra={<Link to="/site">Back</Link>}><p>Global users see every node. Regional users see selected nodes and their children.</p><Table rowKey="id" loading={q.isLoading} dataSource={q.data??[]} columns={[{title:'Name',dataIndex:'name'},{title:'Email',dataIndex:'email'},{title:'Access',dataIndex:'accessLevel'},{title:'Nodes',render:(_,r)=>(r.siteIds??[]).join(', ')}]}/></Card>}
export function ApiManagementPage(){const q=useQuery({queryKey:['api-clients'],queryFn:()=>apiRequest<Array<{id:number;name:string;tokenPrefix:string;active:boolean;expiresAt:string;lastUsedAt:string;rateLimitPerMinute:number}>>('/api-clients')});return <Card title="API Management"><Table rowKey="id" loading={q.isLoading} dataSource={q.data??[]} columns={[{title:'Name',dataIndex:'name'},{title:'Token prefix',render:(_,r)=>`${r.tokenPrefix}…`},{title:'Status',render:(_,r)=><Tag color={r.active?'green':'default'}>{r.active?'Active':'Revoked'}</Tag>},{title:'Expires',dataIndex:'expiresAt'},{title:'Requests / minute',dataIndex:'rateLimitPerMinute'},{title:'Last used',dataIndex:'lastUsedAt'}]}/></Card>}
