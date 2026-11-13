# JConnect System Diagram

```mermaid
flowchart LR
    User["Browser"]
    subgraph Web["Web interfaces"]
        React["React + Vite :5173"]
        Nginx["Nginx :80"]
        PHP["PHP CodeIgniter"]
        Go["Go Fiber API :3000"]
        API["Node.js Public API"]
    end
    subgraph Collection["Device collection"]
        Devices["Monitoring devices"]
        Sim["PEMON HTTP simulator :8080"]
        Collector["Go PEMON collector"]
        Edge["dataedge HTTP :3002 and WebSocket :3003"]
        Evaluate["Alarm evaluator"]
        Worker["Alarm worker :3004"]
    end
    DB[("TimescaleDB / PostgreSQL")]
    Notify["Email, Telegram, LINE OA, Discord, webhook"]

    User --> React
    User --> Nginx
    Nginx --> PHP
    Nginx -->|/api/public/| API
    Nginx -->|/ws| Edge
    React -->|REST /api/v1/ and /ws| Go
    Go -->|Authenticated WebSocket proxy| Edge
    Devices -->|Device readings| Edge
    Sim -->|HTTP View1.js response| Collector
    Collector -->|Device configuration and readings| DB
    Edge -->|Device configuration and readings| DB
    Edge -->|Live metric evaluation| Evaluate
    Evaluate -->|Alarm state and queued logs| DB
    DB -->|Queued alarms| Worker
    Worker --> Notify
    Worker -->|Dispatch status| DB
    PHP --> DB
    Go --> DB
    API --> DB
```

React ขอค่าล่าสุดของ PEMON จาก Go API ทุก 5 วินาที ส่วน Go PEMON collector อ่านจากตัวจำลองตาม `pull_rate` และบันทึกลง `device_data` กับ `device_latest` โดยตรง เส้นทางนี้จึงแสดงค่าใน React และ PHP ได้จากฐานข้อมูล การส่งค่าผ่าน WebSocket และการประเมิน Alarm อยู่ในเส้นทางของ dataedge

PHP คำนวณ Site Online จากเวลาใน `device_data` ภายใน 15 นาที โดยใช้ค่าจากอุปกรณ์ที่ผูกกับโหนดประเภท Site หรือโหนดลูก

## Database ER Diagram

เปิด [jconnect-erd.dbml](./jconnect-erd.dbml) ใน dbdiagram.io หรือดู [ER Diagram PDF](./jconnect-erd.pdf) ไฟล์ DBML ครอบคลุม 41 รายการใน snapshot ของ public schema และแสดงความสัมพันธ์ตาม foreign key ที่ประกาศไว้ในฐานข้อมูล

```mermaid
flowchart LR
    Sites["site_nodes"] -->|site_node_id| Devices["devices"]
    Categories["device_categories"] --> Templates["device_templates"]
    Templates -->|device_template_id| Devices
    Devices -.->|device_id| History["device_data"]
    Devices -.->|device_id and key| Latest["device_latest"]
    Devices -.->|device_id and metric_key| Alarms["alarm_state / alarm_logs"]
    Alarms -.->|alarm_log_id| Dispatch["alarm_log_dispatch"]
    Channels["notification_channels"] -.->|channel_id| Dispatch
    Users["ia_users"] --> Scope["user_site_scopes"]
    Layouts["dashboard_layouts"] -.-> Items["dashboard_layout_items"]
    Widgets["widgets"] -.-> Items
    Reports["report_templates"] --> Executions["report_executions"]
    Schedules["report_schedules"] --> Files["report_export_files"]
```

เส้นประในแผนภาพย่อแสดงการอ้างอิงจากข้อมูลหรือโค้ด ส่วนเส้นทึบแสดง foreign key ใน schema snapshot
