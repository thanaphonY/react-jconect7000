<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Reports Module Configuration
|--------------------------------------------------------------------------
| ตั้งค่าสำหรับ Report Builder, Schedule, Export และการแสดงผล
|
| report_schedule_key: ต้องตรงกับ REPORT_SCHEDULE_KEY ใน Node (ecosystem.config.js)
|
*/

// คีย์สำหรับ Node/cron เรียก run_schedules (ต้องตรงกับ ecosystem.config.js env.REPORT_SCHEDULE_KEY)
$config['report_schedule_key'] = 'report_schedule_secret_key';

// ค่า default สำหรับ Display (Min/Max/Average/First/Last) เมื่อ template ไม่ได้กำหนด
$config['display_default'] = ['min', 'max', 'average'];

// Timezone สำหรับการแสดงผลและคำนวณเวลา
$config['timezone'] = 'Asia/Bangkok';

// Export: จำนวนแถวสูงสุดต่อ query
$config['max_rows'] = 50000;

// Export: โฟลเดอร์เก็บไฟล์ (relative จาก FCPATH)
$config['export_path'] = 'uploads/reports/export/';

// Default columns สำหรับ Editor (เมื่อสร้าง template ใหม่)
$config['default_display_columns'] = [
    ['key' => 'timestamp', 'label' => 'Time'],
    ['key' => 'device_name', 'label' => 'Device Name'],
    ['key' => 'metric_label', 'label' => 'Metric Name'],
    ['key' => 'value_min', 'label' => 'Min'],
    ['key' => 'value_max', 'label' => 'Max'],
    ['key' => 'value_avg', 'label' => 'Average'],
    ['key' => 'value_first', 'label' => 'First'],
    ['key' => 'value_last', 'label' => 'Last'],
];
