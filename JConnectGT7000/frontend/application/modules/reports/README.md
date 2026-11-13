# Module reportsDev – ตัวสร้าง Template รายงาน (Report Template Builder)

## การใช้งาน

- **URL หลัก**: `index.php/reportsdev` หรือ `<?= base_url('reportsdev') ?>`
- **สร้าง Template ใหม่**: `reportsdev/builder`
- **แก้ไข Template**: `reportsdev/builder/<template_id>`
- **รันรายงานตาม Template**: `reportsdev/run/<template_id>`

## ฟีเจอร์

1. **Filter ใน Template**
   - Device (อุปกรณ์)
   - Metric (จาก `device_data.key`)
   - Room (location)
   - Time Interval: วันที่เดียว / เดือน / ปี / ช่วงวันที่

2. **Input ตอน Export**
   - เลือกได้หลาย Input: Date, Month, Year, Date Range, Device, Metric, Room
   - กำหนด Label และว่าบังคับกรอกหรือไม่

3. **เก็บใน DB**
   - ใช้ตาราง `report_templates` (template_type = `custom_builder`)
   - config เก็บเป็น JSON: filters, export_inputs

## โครงสร้าง

- `controllers/Reportsdev.php` – index, builder, run, save_template, get_devices, get_metrics, get_rooms, fetch_data, delete_template
- `models/Reportsdev_model.php` – devices, metrics, rooms, CRUD template
- `views/index.php` – รายการ Template
- `views/builder.php` – ฟอร์มสร้าง/แก้ไข Template (Filter + Export Inputs)
- `views/run.php` – ฟอร์มกรอกค่าแล้วดึงข้อมูล / Export

## หมายเหตุ

- ถ้าเซิร์ฟเวอร์เป็น Linux (case-sensitive) และเข้า `reportsdev` ไม่ได้ ให้เปลี่ยนชื่อโฟลเดอร์จาก `reportsDev` เป็น `reportsdev`
