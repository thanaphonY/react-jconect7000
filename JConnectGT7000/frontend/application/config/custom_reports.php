<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Custom Reports Configuration
|--------------------------------------------------------------------------
| ระบบ Custom Reports ที่รองรับ Template System และ Visual Builder
|
*/

// =====================================================
// DATA SOURCES CONFIGURATION
// =====================================================

$config['data_sources'] = [
    'device_data' => [
        'name' => 'Device Data',
        'description' => 'ข้อมูลอุปกรณ์และ telemetry',
        'table' => 'device_data',
        'joins' => [
            'devices' => 'devices.id = device_data.device_id',
            'device_templates' => 'device_templates.id = devices.device_template_id'
        ],
        'available_fields' => [
            'device_data.id' => ['label' => 'ID', 'type' => 'integer'],
            'device_data.device_id' => ['label' => 'Device ID', 'type' => 'integer'],
            'device_data.key' => ['label' => 'Metric Key', 'type' => 'string'],
            'device_data.value' => ['label' => 'Value', 'type' => 'float'],
            'device_data.timestamp' => ['label' => 'Timestamp', 'type' => 'datetime'],
            'devices.name' => ['label' => 'Device Name', 'type' => 'string'],
            'devices.location' => ['label' => 'Location', 'type' => 'string'],
            'devices.config' => ['label' => 'Device Config', 'type' => 'jsonb'],
            'device_templates.name' => ['label' => 'Template Name', 'type' => 'string'],
            'device_templates.device_information' => ['label' => 'Device Info', 'type' => 'jsonb']
        ],
        'default_filters' => ['device_id', 'key', 'timestamp_range']
    ],
    
    'devices' => [
        'name' => 'Devices',
        'description' => 'ข้อมูลอุปกรณ์ทั้งหมด',
        'table' => 'devices',
        'joins' => [
            'device_templates' => 'device_templates.id = devices.device_template_id',
            'device_categories' => 'device_categories.id = devices.category_id'
        ],
        'available_fields' => [
            'devices.id' => ['label' => 'ID', 'type' => 'integer'],
            'devices.name' => ['label' => 'Name', 'type' => 'string'],
            'devices.serial_number' => ['label' => 'Serial Number', 'type' => 'string'],
            'devices.location' => ['label' => 'Location', 'type' => 'string'],
            'devices.config' => ['label' => 'Config', 'type' => 'jsonb'],
            'devices.is_active' => ['label' => 'Active', 'type' => 'boolean'],
            'devices.created_at' => ['label' => 'Created At', 'type' => 'datetime'],
            'device_templates.name' => ['label' => 'Template', 'type' => 'string'],
            'device_templates.device_information' => ['label' => 'Device Info', 'type' => 'jsonb'],
            'device_categories.name' => ['label' => 'Category', 'type' => 'string']
        ],
        'default_filters' => ['is_active', 'location', 'template_id']
    ],
    
    'event_logs' => [
        'name' => 'Event Logs',
        'description' => 'บันทึกเหตุการณ์และ alarms',
        'table' => 'event_logs',
        'joins' => [
            'ia_users' => 'ia_users.ia_users_id = event_logs.user_id'
        ],
        'available_fields' => [
            'event_logs.id' => ['label' => 'ID', 'type' => 'integer'],
            'event_logs.action_type' => ['label' => 'Action Type', 'type' => 'string'],
            'event_logs.entity_type' => ['label' => 'Entity Type', 'type' => 'string'],
            'event_logs.entity_id' => ['label' => 'Entity ID', 'type' => 'integer'],
            'event_logs.message' => ['label' => 'Message', 'type' => 'text'],
            'event_logs.created_at' => ['label' => 'Created At', 'type' => 'datetime'],
            'event_logs.metadata' => ['label' => 'Metadata', 'type' => 'jsonb'],
            'ia_users.name' => ['label' => 'User Name', 'type' => 'string']
        ],
        'default_filters' => ['action_type', 'entity_type', 'date_range']
    ]
];

// =====================================================
// TEMPLATE SYSTEM CONFIGURATION
// =====================================================

$config['template_types'] = [
    'html' => [
        'name' => 'HTML Template',
        'description' => 'HTML template สำหรับแสดงผลบนเว็บ',
        'file_extension' => '.html',
        'editor_type' => 'html',
        'preview_support' => true
    ],
    'pdf' => [
        'name' => 'PDF Template',
        'description' => 'Template สำหรับสร้าง PDF',
        'file_extension' => '.php',
        'editor_type' => 'php',
        'preview_support' => false,
        'libraries' => ['tcpdf']
    ],
    'excel' => [
        'name' => 'Excel Template',
        'description' => 'Template สำหรับสร้าง Excel',
        'file_extension' => '.php',
        'editor_type' => 'php',
        'preview_support' => false,
        'libraries' => ['phpspreadsheet']
    ]
];

$config['template_variables'] = [
    'data' => [
        'description' => 'ข้อมูลหลักของรายงาน',
        'type' => 'array',
        'example' => '$data->rows, $data->summary, $data->charts'
    ],
    'filters' => [
        'description' => 'ค่าที่ใช้ในการกรองข้อมูล',
        'type' => 'array',
        'example' => '$filters->date_range, $filters->device_ids'
    ],
    'parameters' => [
        'description' => 'พารามิเตอร์ของรายงาน',
        'type' => 'array',
        'example' => '$parameters->title, $parameters->subtitle'
    ],
    'metadata' => [
        'description' => 'ข้อมูลเพิ่มเติม',
        'type' => 'array',
        'example' => '$metadata->generated_at, $metadata->user'
    ]
];

// =====================================================
// CUSTOM REPORT BUILDER CONFIGURATION
// =====================================================

$config['builder_components'] = [
    'data_source_selector' => [
        'name' => 'Data Source Selector',
        'description' => 'เลือกแหล่งข้อมูล',
        'type' => 'select',
        'required' => true
    ],
    'field_selector' => [
        'name' => 'Field Selector',
        'description' => 'เลือกฟิลด์ที่ต้องการ',
        'type' => 'multi_select',
        'required' => true,
        'dynamic' => true
    ],
    'filter_builder' => [
        'name' => 'Filter Builder',
        'description' => 'สร้างเงื่อนไขการกรอง',
        'type' => 'dynamic_form',
        'required' => false
    ],
    'aggregation_builder' => [
        'name' => 'Aggregation Builder',
        'description' => 'สร้างการรวมข้อมูล',
        'type' => 'dynamic_form',
        'required' => false
    ],
    'sort_builder' => [
        'name' => 'Sort Builder',
        'description' => 'กำหนดการเรียงลำดับ',
        'type' => 'dynamic_form',
        'required' => false
    ],
    'template_selector' => [
        'name' => 'Template Selector',
        'description' => 'เลือก template',
        'type' => 'select',
        'required' => true
    ]
];

$config['filter_operators'] = [
    'string' => [
        'equals' => ['label' => 'เท่ากับ', 'operator' => '='],
        'not_equals' => ['label' => 'ไม่เท่ากับ', 'operator' => '!='],
        'contains' => ['label' => 'มีคำว่า', 'operator' => 'ILIKE'],
        'not_contains' => ['label' => 'ไม่มีคำว่า', 'operator' => 'NOT ILIKE'],
        'starts_with' => ['label' => 'ขึ้นต้นด้วย', 'operator' => 'ILIKE'],
        'ends_with' => ['label' => 'ลงท้ายด้วย', 'operator' => 'ILIKE']
    ],
    'number' => [
        'equals' => ['label' => 'เท่ากับ', 'operator' => '='],
        'not_equals' => ['label' => 'ไม่เท่ากับ', 'operator' => '!='],
        'greater_than' => ['label' => 'มากกว่า', 'operator' => '>'],
        'less_than' => ['label' => 'น้อยกว่า', 'operator' => '<'],
        'greater_equal' => ['label' => 'มากกว่าหรือเท่ากับ', 'operator' => '>='],
        'less_equal' => ['label' => 'น้อยกว่าหรือเท่ากับ', 'operator' => '<='],
        'between' => ['label' => 'ระหว่าง', 'operator' => 'BETWEEN']
    ],
    'datetime' => [
        'equals' => ['label' => 'เท่ากับ', 'operator' => '='],
        'not_equals' => ['label' => 'ไม่เท่ากับ', 'operator' => '!='],
        'greater_than' => ['label' => 'หลังจาก', 'operator' => '>'],
        'less_than' => ['label' => 'ก่อน', 'operator' => '<'],
        'between' => ['label' => 'ระหว่าง', 'operator' => 'BETWEEN'],
        'today' => ['label' => 'วันนี้', 'operator' => 'CUSTOM'],
        'yesterday' => ['label' => 'เมื่อวาน', 'operator' => 'CUSTOM'],
        'this_week' => ['label' => 'สัปดาห์นี้', 'operator' => 'CUSTOM'],
        'this_month' => ['label' => 'เดือนนี้', 'operator' => 'CUSTOM']
    ],
    'boolean' => [
        'equals' => ['label' => 'เท่ากับ', 'operator' => '='],
        'not_equals' => ['label' => 'ไม่เท่ากับ', 'operator' => '!=']
    ]
];

$config['aggregation_functions'] = [
    'count' => [
        'name' => 'Count',
        'description' => 'นับจำนวน',
        'syntax' => 'COUNT({field})',
        'applicable_types' => ['all']
    ],
    'sum' => [
        'name' => 'Sum',
        'description' => 'ผลรวม',
        'syntax' => 'SUM({field})',
        'applicable_types' => ['number']
    ],
    'avg' => [
        'name' => 'Average',
        'description' => 'ค่าเฉลี่ย',
        'syntax' => 'AVG({field})',
        'applicable_types' => ['number']
    ],
    'min' => [
        'name' => 'Minimum',
        'description' => 'ค่าต่ำสุด',
        'syntax' => 'MIN({field})',
        'applicable_types' => ['number', 'datetime', 'string']
    ],
    'max' => [
        'name' => 'Maximum',
        'description' => 'ค่าสูงสุด',
        'syntax' => 'MAX({field})',
        'applicable_types' => ['number', 'datetime', 'string']
    ],
    'stddev' => [
        'name' => 'Standard Deviation',
        'description' => 'ส่วนเบี่ยงเบนมาตรฐาน',
        'syntax' => 'STDDEV({field})',
        'applicable_types' => ['number']
    ]
];

// =====================================================
// SCHEDULING CONFIGURATION
// =====================================================

$config['schedule_types'] = [
    'once' => [
        'name' => 'ครั้งเดียว',
        'description' => 'รันครั้งเดียวตามเวลาที่กำหนด',
        'config_fields' => ['run_at']
    ],
    'daily' => [
        'name' => 'รายวัน',
        'description' => 'รันทุกวันตามเวลาที่กำหนด',
        'config_fields' => ['time']
    ],
    'weekly' => [
        'name' => 'รายสัปดาห์',
        'description' => 'รันทุกสัปดาห์ตามวันและเวลาที่กำหนด',
        'config_fields' => ['day_of_week', 'time']
    ],
    'monthly' => [
        'name' => 'รายเดือน',
        'description' => 'รันทุกเดือนตามวันที่และเวลาที่กำหนด',
        'config_fields' => ['day_of_month', 'time']
    ],
    'custom' => [
        'name' => 'กำหนดเอง',
        'description' => 'กำหนด cron expression เอง',
        'config_fields' => ['cron_expression']
    ]
];

$config['delivery_methods'] = [
    'email' => [
        'name' => 'Email',
        'description' => 'ส่งทางอีเมล',
        'config_fields' => ['recipients', 'subject', 'message']
    ],
    'file_storage' => [
        'name' => 'File Storage',
        'description' => 'บันทึกไฟล์ในระบบ',
        'config_fields' => ['storage_path']
    ],
    'webhook' => [
        'name' => 'Webhook',
        'description' => 'ส่งข้อมูลไปยัง webhook',
        'config_fields' => ['webhook_url', 'method', 'headers']
    ]
];

// =====================================================
// DEFAULT TEMPLATES
// =====================================================

$config['default_templates'] = [
    'device_status_html' => [
        'name' => 'Device Status Report (HTML)',
        'description' => 'Template สำหรับรายงานสถานะอุปกรณ์แบบ HTML',
        'category' => 'device_status',
        'template_type' => 'html',
        'html_template' => '
<!DOCTYPE html>
<html>
<head>
    <title>{{$parameters->title}}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .summary { background: #f5f5f5; padding: 15px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{$parameters->title}}</h1>
        <p>Generated on: {{$metadata->generated_at}}</p>
    </div>
    
    <div class="summary">
        <h3>Summary</h3>
        <p>Total Devices: {{$data->summary->total_devices}}</p>
        <p>Active Devices: {{$data->summary->active_devices}}</p>
        <p>Inactive Devices: {{$data->summary->inactive_devices}}</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Device Name</th>
                <th>Location</th>
                <th>Status</th>
                <th>Last Update</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data->rows as $device)
            <tr>
                <td>{{$device->name}}</td>
                <td>{{$device->location}}</td>
                <td>{{$device->status}}</td>
                <td>{{$device->last_update}}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>'
    ],
    
    'temperature_excel' => [
        'name' => 'Temperature Report (Excel)',
        'description' => 'Template สำหรับรายงานอุณหภูมิแบบ Excel',
        'category' => 'temperature',
        'template_type' => 'excel',
        'php_template' => '
<?php
// Excel Template for Temperature Report
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set title
$sheet->setCellValue("A1", $parameters->title);
$sheet->mergeCells("A1:D1");
$sheet->getStyle("A1")->getFont()->setBold(true)->setSize(16);

// Set headers
$sheet->setCellValue("A3", "Device Name");
$sheet->setCellValue("B3", "Location");
$sheet->setCellValue("C3", "Temperature (°C)");
$sheet->setCellValue("D3", "Timestamp");

$row = 4;
foreach ($data->rows as $record) {
    $sheet->setCellValue("A{$row}", $record->device_name);
    $sheet->setCellValue("B{$row}", $record->location);
    $sheet->setCellValue("C{$row}", $record->temperature);
    $sheet->setCellValue("D{$row}", $record->timestamp);
    $row++;
}

// Auto-size columns
foreach (range("A", "D") as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$writer->save($output_file);
?>'
    ]
];

// =====================================================
// PERMISSIONS AND SECURITY
// =====================================================

$config['permissions'] = [
    'view_reports' => [
        'name' => 'View Reports',
        'description' => 'สามารถดูรายงานได้'
    ],
    'create_reports' => [
        'name' => 'Create Reports',
        'description' => 'สามารถสร้างรายงานใหม่ได้'
    ],
    'edit_reports' => [
        'name' => 'Edit Reports',
        'description' => 'สามารถแก้ไขรายงานได้'
    ],
    'delete_reports' => [
        'name' => 'Delete Reports',
        'description' => 'สามารถลบรายงานได้'
    ],
    'schedule_reports' => [
        'name' => 'Schedule Reports',
        'description' => 'สามารถตั้งเวลารายงานได้'
    ],
    'export_reports' => [
        'name' => 'Export Reports',
        'description' => 'สามารถส่งออกรายงานได้'
    ],
    'manage_templates' => [
        'name' => 'Manage Templates',
        'description' => 'สามารถจัดการ template ได้'
    ]
];

// =====================================================
// SYSTEM SETTINGS
// =====================================================

$config['system_settings'] = [
    'max_report_execution_time' => 300, // 5 minutes
    'max_file_size' => 10485760, // 10MB
    'allowed_file_types' => ['pdf', 'xlsx', 'xls', 'html', 'csv'],
    'default_timezone' => 'Asia/Bangkok',
    'date_format' => 'Y-m-d H:i:s',
    'number_format' => [
        'decimal_separator' => '.',
        'thousands_separator' => ',',
        'decimal_places' => 2
    ],
    'pagination' => [
        'default_per_page' => 50,
        'max_per_page' => 1000
    ],
    'caching' => [
        'enabled' => true,
        'duration' => 3600 // 1 hour
    ]
];
