import { useState } from 'react'
import { Alert, App, Button, Card, InputNumber, Modal, Space, Table, Tag, Typography } from 'antd'
import { appendPemonMetrics, PEMON_METRICS, pemonConflict, pemonOID } from './pemon'
import type { Metric } from './types'

type Props = { metrics: Metric[]; onApply: (metrics: Metric[]) => void }
export function PemonMetricsBuilder({metrics,onApply}:Props) {
 const {message}=App.useApp()
 const [open,setOpen]=useState(false),[poll,setPoll]=useState<number|null>(30)
 const [selected,setSelected]=useState<string[]>([])
 const available=PEMON_METRICS.filter(d=>!pemonConflict(metrics,d))
 function show(){setSelected(available.map(d=>d.key));setOpen(true)}
 function apply(){
  try {
   const result=appendPemonMetrics(metrics,selected,poll??0)
   if(!result.added){message.warning('ไม่มีรายการใหม่ที่เพิ่มได้');return}
   onApply(result.metrics);setOpen(false)
   message.success(`เพิ่ม PEMON Metrics ${result.added} รายการแล้ว`)
  }catch(e){message.error(e instanceof Error?e.message:'Unable to generate PEMON metrics')}
 }
 return <>
  <Card size="small" title="PEMON Metrics / OID" extra={<Button onClick={show} icon={<i className="bi bi-magic"/>}>สร้าง PEMON Metrics/OID</Button>}>
   <Typography.Paragraph style={{marginBottom:0}}>เลือก OID พร้อม Scale จาก mapping ทดสอบ PEMON เพื่อเพิ่มในอุปกรณ์ SNMP โดยรักษา Metrics เดิมและข้าม Key/OID ที่ซ้ำ</Typography.Paragraph>
  </Card>
  <Modal title="สร้าง PEMON Metrics/OID" open={open} width={1000} onCancel={()=>setOpen(false)} onOk={apply} okText={`เพิ่ม ${selected.length} Metrics`} okButtonProps={{disabled:!selected.length||!Number.isInteger(poll)||Number(poll)<5||Number(poll)>3600}}>
   <Space direction="vertical" size={16} style={{width:'100%'}}>
    <Alert type="info" showIcon message="Starter mapping จากไฟล์ทดสอบ PEMON" description="มี 17 OID ตัวเลขที่พบหลักฐานในข้อมูลทดสอบ โปรดตรวจสอบกับรุ่นอุปกรณ์จริงก่อนบันทึก ไม่รวม OID ข้อความ (เช่น System Name) หรือ OID ที่ยังยืนยัน Scale ไม่ได้ ไม่มีการเชื่อมต่อหรือสั่งงานอุปกรณ์ในขั้นตอนนี้"/>
    <Space wrap><label htmlFor="pemon-poll">Polling interval (seconds)</label><InputNumber id="pemon-poll" min={5} max={3600} precision={0} value={poll} onChange={setPoll}/><Tag>{available.length} available / {PEMON_METRICS.length} mappings</Tag></Space>
    <Table size="small" rowKey="key" pagination={false} scroll={{x:850,y:360}} dataSource={PEMON_METRICS} rowSelection={{selectedRowKeys:selected,onChange:keys=>setSelected(keys.map(String)),getCheckboxProps:d=>({disabled:pemonConflict(metrics,d)})}} columns={[
     {title:'Metric / Key',dataIndex:'label',width:250,render:(_,d)=><><strong>{d.label}</strong><div style={{fontSize:12}}>{d.key}</div></>},
     {title:'OID',width:300,render:(_,d)=><code>{pemonOID(d)}</code>},
     {title:'Scale',dataIndex:'scale',width:85},{title:'Unit',dataIndex:'unit',width:65},
     {title:'Status',width:120,render:(_,d)=>pemonConflict(metrics,d)?<Tag>มีอยู่แล้ว</Tag>:<Tag color="green">เพิ่มได้</Tag>},
    ]}/>
    <Typography.Text type="secondary">Scale เป็นตัวคูณ เช่น 5600 × 0.01 = 56 V · แก้ไข Key, OID, Scale และ Triggers ต่อได้ใน Metrics editor ก่อนบันทึก Device</Typography.Text>
   </Space>
  </Modal>
 </>
}
