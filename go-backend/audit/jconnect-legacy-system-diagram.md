# JConnect GT7000

```mermaid
flowchart LR
    User["ผู้ใช้"] --> Nginx["Nginx :80"]
    Nginx -->|หน้าเว็บและ FastCGI| PHP["PHP CodeIgniter"]
    Nginx -->|/api/public/| Public["Node.js Public API"]
    Nginx -->|/ws| Edge["dataedge HTTP :3002 · WebSocket :3003"]
    PHP -->|อ่านและจัดการข้อมูล| DB[("TimescaleDB / PostgreSQL")]
    Public --> DB
    Edge -->|โหลดอุปกรณ์ · บันทึกค่า| DB
    Devices["อุปกรณ์ตรวจวัด"] -->|SNMP · Modbus · BACnet · HTTP REST| Edge
    Shared["shared · WebSocket bus และ gateway mapping"] --- Edge
    Edge -->|ประเมินค่าที่ได้รับ| Eval["Alarm evaluator"]
    Eval -->|alarm_state และ alarm_logs| DB
    DB -->|คิวแจ้งเตือน| Worker["alarm-engine worker :3004"]
    Worker -->|ผลการส่ง| DB
    Worker --> Channels["Email · Telegram · LINE OA · Discord · Webhook"]
    Scheduler["Report scheduler"] -->|รันรายงานและ health checks| PHP
    Admin["pgAdmin"] --> DB
    Fastify["Node.js Fastify API ใน backend"] --> DB
    Fastify -->|LoRaWAN API| ChirpStack["ChirpStack"]
    Sim["PEMON HTTP simulator :8080"] --- Test["PEMON Test · ตัวอย่างข้อมูล HTTP และ SNMP"]
```

PHP คำนวณ Site Online จากค่าที่บันทึกในช่วง 15 นาทีล่าสุดของอุปกรณ์ที่ผูกกับ Site หรือโหนดลูก และใช้ WebSocket สำหรับอัปเดต Device Data

ตัวจำลอง PEMON ให้บริการ `GET /View1.js` ส่วนตัวอ่าน HTTP ใน dataedge ชุดนี้รองรับ metric ชนิด `rest_get` ข้อมูล PEMON ที่บันทึกไว้แล้วแสดงใน PHP ผ่าน TimescaleDB

เปิด [ER Diagram DBML](./jconnect-erd.dbml) ใน dbdiagram.io เพื่อดูตารางทั้งหมด 41 รายการและ foreign key 17 รายการจาก snapshot ของ public schema
