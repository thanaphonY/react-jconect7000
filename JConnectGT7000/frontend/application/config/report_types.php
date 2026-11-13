<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Report Types Configuration
|--------------------------------------------------------------------------
| ไฟล์นี้ใช้สำหรับกำหนดประเภทรายงานทั้งหมดในระบบ
| สามารถเพิ่มประเภทใหม่ได้โดยการแก้ไขไฟล์นี้เท่านั้น
|
*/

$config['report_types'] = [
    
    // 1. Device Status Reports
    'device_status' => [
        'name' => 'รายงานสถานะอุปกรณ์',
        'description' => 'บันทึกประวัติการแจ้งเตือน Alarm, แสดงรูปภาพและข้อมูลอุปกรณ์',
        'icon' => 'bi-activity',
        'color' => 'primary',
        'subtypes' => [
            'containment_room' => [
                'name' => 'รายงานบันทึก Event, Alarm ของ Containment แยกรายห้อง',
                'icon' => 'bi-building',
                'description' => 'รายงานแยกตามห้องสำหรับ Containment',
                'filters' => ['location', 'date_range'],
                'export_formats' => ['pdf', 'excel', 'html']
            ],
            'containment_all' => [
                'name' => 'รายงานบันทึก Event, Alarm ของ Containment รวมทุกห้อง',
                'icon' => 'bi-buildings',
                'description' => 'รายงานรวมทุกห้องสำหรับ Containment',
                'filters' => ['date_range'],
                'export_formats' => ['pdf', 'excel', 'html']
            ],
            'lora_individual' => [
                'name' => 'รายงานข้อมูล Sensor LoRa สถานะ Battery, อุณหภูมิ ความชื้น รายตัว',
                'icon' => 'bi-wifi',
                'description' => 'รายงานข้อมูล Sensor แยกตามตัว',
                'filters' => ['device', 'date_range'],
                'export_formats' => ['pdf', 'excel', 'html']
            ],
            'lora_room' => [
                'name' => 'รายงานตารางข้อมูล Sensor LoRa สถานะ Battery, อุณหภูมิ ความชื้น แยกรายห้อง',
                'icon' => 'bi-grid',
                'description' => 'รายงานข้อมูล Sensor แยกตามห้อง',
                'filters' => ['location', 'date_range'],
                'export_formats' => ['pdf', 'excel', 'html']
            ],
            'lora_combined' => [
                'name' => 'รายงานตารางข้อมูล Sensor LoRa สถานะ Battery, อุณหภูมิ ความชื้น รวม',
                'icon' => 'bi-table',
                'description' => 'รายงานข้อมูล Sensor รวมทั้งหมด',
                'filters' => ['date_range'],
                'export_formats' => ['pdf', 'excel', 'html']
            ]
        ]
    ],
    
    // 2. Temperature Reports
    'temperature' => [
        'name' => 'รายงานค่าอุณหภูมิ',
        'description' => 'เลือกอุปกรณ์ตัวเดียว, หลายตัว แสดงกราฟอุณหภูมิ',
        'icon' => 'bi-thermometer-half',
        'color' => 'success',
        'subtypes' => [
            'location_based' => [
                'name' => 'รายงานแยกรายห้อง, หน้า Rack, หลัง Rack, Under Floor, InRoom',
                'icon' => 'bi-geo-alt',
                'description' => 'รายงานอุณหภูมิตามตำแหน่ง',
                'filters' => ['location', 'position', 'date_range'],
                'export_formats' => ['pdf', 'excel', 'html'],
                'options' => ['include_humidity', 'include_charts']
            ],
            'lora_sensor' => [
                'name' => 'รายงานแสดงค่าอุณหภูมิความชื้นของ LoRa Sensor รายตัว',
                'icon' => 'bi-thermometer',
                'description' => 'รายงานข้อมูล Sensor รายตัว',
                'filters' => ['device', 'date_range'],
                'export_formats' => ['pdf', 'excel', 'html'],
                'options' => ['include_humidity', 'include_charts']
            ],
            'containment' => [
                'name' => 'รายงานแสดงค่าอุณหภูมิความชื้นภายใน Containment',
                'icon' => 'bi-box',
                'description' => 'รายงานอุณหภูมิใน Containment',
                'filters' => ['location', 'date_range'],
                'export_formats' => ['pdf', 'excel', 'html'],
                'options' => ['include_humidity', 'include_charts']
            ]
        ]
    ],
    
    // 3. Maintenance Reports
    'maintenance' => [
        'name' => 'รายงาน Maintenance Service',
        'description' => 'รายงานการบำรุงรักษาและซ่อมแซม',
        'icon' => 'bi-tools',
        'color' => 'warning',
        'subtypes' => [
            'containment' => [
                'name' => 'รายงานบันทึกการ ทดสอบ/ตรวจสอบ ซ่อมบำรุง Containment',
                'icon' => 'bi-wrench',
                'description' => 'รายงานการบำรุงรักษา Containment',
                'filters' => ['location', 'date_range', 'maintenance_type'],
                'export_formats' => ['pdf', 'excel', 'html']
            ],
            'lora_gateway' => [
                'name' => 'รายงานบันทึกการ ทดสอบ/ตรวจสอบ ซ่อมบำรุง LoRa Gateway',
                'icon' => 'bi-router',
                'description' => 'รายงานการบำรุงรักษา LoRa Gateway',
                'filters' => ['device', 'date_range', 'maintenance_type'],
                'export_formats' => ['pdf', 'excel', 'html']
            ],
            'lora_sensor_battery' => [
                'name' => 'รายงานบันทึกการ ทดสอบ/ตรวจสอบซ่อมบำรุง เปลี่ยนแบตเตอรี่ LoRa Sensor',
                'icon' => 'bi-battery-charging',
                'description' => 'รายงานการเปลี่ยนแบตเตอรี่ Sensor',
                'filters' => ['device', 'date_range', 'maintenance_type'],
                'export_formats' => ['pdf', 'excel', 'html']
            ],
            'jconnect_software' => [
                'name' => 'รายงานบันทึกการ ทดสอบ/ตรวจสอบ ซ่อมบำรุง Software JConnect',
                'icon' => 'bi-code-square',
                'description' => 'รายงานการบำรุงรักษา Software',
                'filters' => ['date_range', 'maintenance_type'],
                'export_formats' => ['pdf', 'excel', 'html']
            ]
        ]
    ]
];

/*
|--------------------------------------------------------------------------
| How to Add New Report Types
|--------------------------------------------------------------------------
| 
| 1. เพิ่มประเภทใหม่ใน $config['report_types']
| 2. เพิ่ม method ใน Reports_model.php
| 3. เพิ่ม case ใน generate_filtered_report() method
| 4. ระบบจะสร้าง UI อัตโนมัติ
|
| ตัวอย่างการเพิ่มประเภทใหม่:
|
| 'power_consumption' => [
|     'name' => 'รายงานการใช้พลังงาน',
|     'description' => 'รายงานการใช้พลังงานของอุปกรณ์',
|     'icon' => 'bi-lightning',
|     'color' => 'danger',
|     'subtypes' => [
|         'hourly' => [
|             'name' => 'รายงานรายชั่วโมง',
|             'icon' => 'bi-clock',
|             'description' => 'รายงานการใช้พลังงานรายชั่วโมง',
|             'filters' => ['device', 'date_range'],
|             'export_formats' => ['pdf', 'excel', 'html']
|         ]
|     ]
| ]
|
*/

// Available filter types
$config['available_filters'] = [
    'device' => [
        'name' => 'อุปกรณ์',
        'type' => 'select',
        'multiple' => true,
        'required' => false
    ],
    'location' => [
        'name' => 'ตำแหน่ง',
        'type' => 'select',
        'multiple' => true,
        'required' => false
    ],
    'position' => [
        'name' => 'ตำแหน่งในระบบ',
        'type' => 'select',
        'multiple' => true,
        'required' => false
    ],
    'date_range' => [
        'name' => 'ช่วงเวลา',
        'type' => 'date_range',
        'required' => true
    ],
    'maintenance_type' => [
        'name' => 'ประเภทการบำรุงรักษา',
        'type' => 'select',
        'multiple' => false,
        'required' => false,
        'options' => ['test', 'inspection', 'repair', 'battery_replacement']
    ]
];

// Available export formats
$config['available_export_formats'] = [
    'pdf' => [
        'name' => 'PDF',
        'icon' => 'bi-file-pdf',
        'description' => 'Portable Document Format'
    ],
    'excel' => [
        'name' => 'Excel',
        'icon' => 'bi-file-spreadsheet',
        'description' => 'Microsoft Excel Format'
    ],
    'html' => [
        'name' => 'HTML',
        'icon' => 'bi-file-text',
        'description' => 'Web Page Format'
    ]
];

// Available options for reports
$config['available_options'] = [
    'include_humidity' => [
        'name' => 'รวมข้อมูลความชื้น',
        'type' => 'checkbox',
        'default' => true
    ],
    'include_charts' => [
        'name' => 'แสดงกราฟ',
        'type' => 'checkbox',
        'default' => true
    ]
];
