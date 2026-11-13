<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Device Module Language File - Thai

// General
$lang['device'] = 'อุปกรณ์';
$lang['devices'] = 'อุปกรณ์';
$lang['device_list'] = 'รายการอุปกรณ์';
$lang['add_device'] = 'เพิ่มอุปกรณ์';
$lang['edit_device'] = 'แก้ไขอุปกรณ์';
$lang['delete_device'] = 'ลบอุปกรณ์';
$lang['device_details'] = 'รายละเอียดอุปกรณ์';
$lang['device_view'] = 'ดูอุปกรณ์';
$lang['home'] = 'หน้าแรก';

// UI Elements
$lang['list'] = 'รายการ';
$lang['card'] = 'การ์ด';
$lang['image'] = 'รูปภาพ';
$lang['actions'] = 'การดำเนินการ';
$lang['all_status'] = 'สถานะทั้งหมด';
$lang['all_templates'] = 'เทมเพลตทั้งหมด';
$lang['all_locations'] = 'ตำแหน่งทั้งหมด';
$lang['serial_number'] = 'หมายเลขซีเรียล';

// Device Information
$lang['device_name'] = 'ชื่ออุปกรณ์';
$lang['device_id'] = 'รหัสอุปกรณ์';
$lang['device_type'] = 'ประเภทอุปกรณ์';
$lang['device_status'] = 'สถานะอุปกรณ์';
$lang['device_protocol'] = 'โปรโตคอล';
$lang['device_template'] = 'เทมเพลตอุปกรณ์';
$lang['device_brand'] = 'แบรนด์';
$lang['device_model'] = 'รุ่น';
$lang['device_description'] = 'คำอธิบาย';
$lang['device_location'] = 'ตำแหน่ง';
$lang['device_notes'] = 'หมายเหตุ';

// Device Status
$lang['status_active'] = 'ใช้งาน';
$lang['status_inactive'] = 'ไม่ใช้งาน';
$lang['status_online'] = 'ออนไลน์';
$lang['status_offline'] = 'ออฟไลน์';
$lang['status_maintenance'] = 'บำรุงรักษา';
$lang['status_error'] = 'ข้อผิดพลาด';

// Communication
$lang['communication_config'] = 'การตั้งค่าการสื่อสาร';
$lang['ip_address'] = 'ที่อยู่ IP';
$lang['port'] = 'พอร์ต';
$lang['unit_id'] = 'รหัสหน่วย';
$lang['slave_id'] = 'รหัส Slave';
$lang['baudrate'] = 'อัตราบอด';
$lang['parity'] = 'พาริตี้';
$lang['stop_bits'] = 'บิตหยุด';
$lang['data_bits'] = 'บิตข้อมูล';
$lang['community'] = 'ชุมชน';
$lang['version'] = 'เวอร์ชัน';
$lang['broker_url'] = 'URL บรอกเกอร์';
$lang['username'] = 'ชื่อผู้ใช้';
$lang['password'] = 'รหัสผ่าน';
$lang['client_id'] = 'รหัสไคลเอนต์';
$lang['qos'] = 'QoS';
$lang['use_tls'] = 'ใช้ TLS';
$lang['topic'] = 'หัวข้อ';
$lang['json_path'] = 'เส้นทาง JSON';
$lang['oid'] = 'OID';
$lang['object_id'] = 'รหัสออบเจ็กต์';
$lang['property_id'] = 'รหัสคุณสมบัติ';
$lang['application_server'] = 'เซิร์ฟเวอร์แอปพลิเคชัน';
$lang['device_profile_id'] = 'รหัสโปรไฟล์อุปกรณ์';
$lang['dev_eui'] = 'Dev EUI';
$lang['app_eui'] = 'App EUI';
$lang['app_key'] = 'App Key';
$lang['netskey'] = 'NwkSKey';
$lang['appskey'] = 'AppSKey';

// Metrics
$lang['metrics'] = 'เมตริก';
$lang['metric'] = 'เมตริก';
$lang['metric_key'] = 'คีย์เมตริก';
$lang['metric_label'] = 'ป้าย';
$lang['metric_unit'] = 'หน่วย';
$lang['metric_datatype'] = 'ประเภทข้อมูล';
$lang['metric_scale'] = 'สเกล';
$lang['metric_pull_rate'] = 'อัตราการดึงข้อมูล';
$lang['metric_store_policy'] = 'นโยบายการจัดเก็บ';
$lang['metric_interval'] = 'ช่วงเวลา (นาที)';
$lang['metric_enable'] = 'เปิดใช้งาน';
$lang['metric_disable'] = 'ปิดใช้งาน';

// Modbus Specific
$lang['address'] = 'ที่อยู่';
$lang['function_code'] = 'รหัสฟังก์ชัน';
$lang['length'] = 'ความยาว';
$lang['type'] = 'ประเภท';
$lang['modbus_read'] = 'อ่าน Modbus';
$lang['holding_register'] = 'รีจิสเตอร์เก็บข้อมูล';
$lang['input_register'] = 'รีจิสเตอร์อินพุต';
$lang['coil'] = 'คอยล์';
$lang['discrete_input'] = 'อินพุตแยก';

// Data Types
$lang['int'] = 'จำนวนเต็ม';
$lang['float'] = 'ทศนิยม';
$lang['string'] = 'สตริง';
$lang['boolean'] = 'บูลีน';
$lang['hex'] = 'เลขฐานสิบหก';

// Store Policies
$lang['always'] = 'เสมอ';
$lang['on_change'] = 'เมื่อเปลี่ยนแปลง';
$lang['on_alarm'] = 'เมื่อแจ้งเตือน';

// Triggers
$lang['triggers'] = 'ทริกเกอร์';
$lang['trigger'] = 'ทริกเกอร์';
$lang['add_trigger'] = 'เพิ่มทริกเกอร์';
$lang['remove_trigger'] = 'ลบทริกเกอร์';
$lang['trigger_condition'] = 'เงื่อนไข';
$lang['trigger_operator'] = 'ตัวดำเนินการ';
$lang['trigger_value'] = 'ค่า';
$lang['trigger_message'] = 'ข้อความ';
$lang['trigger_severity'] = 'ระดับความรุนแรง';
$lang['trigger_enable'] = 'เปิดใช้งาน';
$lang['trigger_disable'] = 'ปิดใช้งาน';
$lang['trigger_repeat_interval'] = 'ช่วงเวลาซ้ำ';
$lang['trigger_duration'] = 'ระยะเวลา';
$lang['trigger_clear_condition'] = 'เงื่อนไขการล้าง';

// Severity Levels
$lang['severity_critical'] = 'วิกฤต';
$lang['severity_high'] = 'สูง';
$lang['severity_warning'] = 'เตือน';
$lang['severity_medium'] = 'ปานกลาง';
$lang['severity_low'] = 'ต่ำ';
$lang['severity_info'] = 'ข้อมูล';
$lang['severity_other'] = 'อื่นๆ';

// Operators
$lang['operator_equal'] = 'เท่ากับ (==)';
$lang['operator_not_equal'] = 'ไม่เท่ากับ (!=)';
$lang['operator_greater'] = 'มากกว่า (>)';
$lang['operator_greater_equal'] = 'มากกว่าหรือเท่ากับ (>=)';
$lang['operator_less'] = 'น้อยกว่า (<)';
$lang['operator_less_equal'] = 'น้อยกว่าหรือเท่ากับ (<=)';

// Duration
$lang['duration_days'] = 'วัน';
$lang['duration_start_time'] = 'เวลาเริ่มต้น';
$lang['duration_end_time'] = 'เวลาสิ้นสุด';
$lang['add_time_window'] = 'เพิ่มหน้าต่างเวลา';
$lang['remove_time_window'] = 'ลบหน้าต่างเวลา';

// Days of Week
$lang['day_sun'] = 'อา';
$lang['day_mon'] = 'จ';
$lang['day_tue'] = 'อ';
$lang['day_wed'] = 'พ';
$lang['day_thu'] = 'พฤ';
$lang['day_fri'] = 'ศ';
$lang['day_sat'] = 'ส';

// Custom Fields
$lang['custom_fields'] = 'ฟิลด์เพิ่มเติม';
$lang['custom_field'] = 'ฟิลด์เพิ่มเติม';
$lang['add_custom_field'] = 'เพิ่มฟิลด์';
$lang['remove_custom_field'] = 'ลบฟิลด์';
$lang['field_name'] = 'ชื่อฟิลด์';
$lang['field_value'] = 'ค่าฟิลด์';
$lang['field_type'] = 'ประเภทฟิลด์';
$lang['field_required'] = 'จำเป็น';

// Actions
$lang['add'] = 'เพิ่ม';
$lang['edit'] = 'แก้ไข';
$lang['delete'] = 'ลบ';
$lang['save'] = 'บันทึก';
$lang['cancel'] = 'ยกเลิก';
$lang['back'] = 'กลับ';
$lang['next'] = 'ถัดไป';
$lang['previous'] = 'ก่อนหน้า';
$lang['confirm'] = 'ยืนยัน';
$lang['search'] = 'ค้นหา';
$lang['filter'] = 'กรอง';
$lang['refresh'] = 'รีเฟรช';
$lang['export'] = 'ส่งออก';
$lang['import'] = 'นำเข้า';

// Messages
$lang['device_added_successfully'] = 'เพิ่มอุปกรณ์สำเร็จ';
$lang['device_updated_successfully'] = 'อัปเดตอุปกรณ์สำเร็จ';
$lang['device_deleted_successfully'] = 'ลบอุปกรณ์สำเร็จ';
$lang['device_not_found'] = 'ไม่พบอุปกรณ์';
$lang['confirm_delete_device'] = 'คุณแน่ใจหรือไม่ที่จะลบอุปกรณ์นี้?';
$lang['device_already_exists'] = 'อุปกรณ์มีอยู่แล้ว';
$lang['invalid_device_data'] = 'ข้อมูลอุปกรณ์ไม่ถูกต้อง';

// Validation
$lang['required_field'] = 'ฟิลด์นี้จำเป็น';
$lang['invalid_ip_address'] = 'ที่อยู่ IP ไม่ถูกต้อง';
$lang['invalid_port'] = 'หมายเลขพอร์ตไม่ถูกต้อง';
$lang['invalid_address'] = 'ที่อยู่ไม่ถูกต้อง';
$lang['invalid_function_code'] = 'รหัสฟังก์ชันไม่ถูกต้อง';

// Dashboard
$lang['dashboard'] = 'แดชบอร์ด';
$lang['real_time_data'] = 'ข้อมูลแบบเรียลไทม์';
$lang['historical_data'] = 'ข้อมูลย้อนหลัง';
$lang['alarms'] = 'การแจ้งเตือน';
$lang['events'] = 'เหตุการณ์';
$lang['logs'] = 'บันทึก';

// Charts
$lang['chart'] = 'แผนภูมิ';
$lang['line_chart'] = 'แผนภูมิเส้น';
$lang['area_chart'] = 'แผนภูมิพื้นที่';
$lang['bar_chart'] = 'แผนภูมิแท่ง';
$lang['gauge'] = 'เกจ';
$lang['value_box'] = 'กล่องค่า';
$lang['donut_chart'] = 'แผนภูมิโดนัท';
$lang['gradient_radial_bar'] = 'แถบเรเดียลไล่ระดับ';

// Time Ranges
$lang['last_24_hours'] = '24 ชั่วโมงที่ผ่านมา';
$lang['last_31_days'] = '31 วันที่ผ่านมา';
$lang['last_year'] = 'ปีที่ผ่านมา';
$lang['custom_range'] = 'ช่วงเวลาที่กำหนดเอง';

// Permissions
$lang['permission_view'] = 'ดูอุปกรณ์';
$lang['permission_add'] = 'เพิ่มอุปกรณ์';
$lang['permission_edit'] = 'แก้ไขอุปกรณ์';
$lang['permission_delete'] = 'ลบอุปกรณ์';
$lang['permission_export'] = 'ส่งออกอุปกรณ์';

// Templates
$lang['templates'] = 'เทมเพลต';
$lang['template'] = 'เทมเพลต';
$lang['device_templates'] = 'เทมเพลตอุปกรณ์';
$lang['add_template'] = 'เพิ่มเทมเพลต';
$lang['edit_template'] = 'แก้ไขเทมเพลต';
$lang['delete_template'] = 'ลบเทมเพลต';
$lang['template_name'] = 'ชื่อเทมเพลต';
$lang['template_protocol'] = 'โปรโตคอลเทมเพลต';
$lang['template_metrics'] = 'เมตริกเทมเพลต';
$lang['template_communication'] = 'การสื่อสารเทมเพลต';

// Search and Filter
$lang['search_devices'] = 'ค้นหาอุปกรณ์...';
$lang['search_by_name'] = 'ค้นหาตามชื่อ';
$lang['search_by_id'] = 'ค้นหาตามรหัส';
$lang['search_by_protocol'] = 'ค้นหาตามโปรโตคอล';
$lang['search_by_template'] = 'ค้นหาตามเทมเพลต';
$lang['search_by_custom_fields'] = 'ค้นหาในฟิลด์เพิ่มเติม';
$lang['filter_by_status'] = 'กรองตามสถานะ';
$lang['filter_by_protocol'] = 'กรองตามโปรโตคอล';
$lang['filter_by_template'] = 'กรองตามเทมเพลต';
$lang['clear_filters'] = 'ล้างตัวกรอง';

// Pagination
$lang['showing'] = 'แสดง';
$lang['to'] = 'ถึง';
$lang['of'] = 'จาก';
$lang['entries'] = 'รายการ';
$lang['previous_page'] = 'ก่อนหน้า';
$lang['next_page'] = 'ถัดไป';
$lang['first_page'] = 'หน้าแรก';
$lang['last_page'] = 'หน้าสุดท้าย';

// Export/Import
$lang['export_devices'] = 'ส่งออกอุปกรณ์';
$lang['import_devices'] = 'นำเข้าอุปกรณ์';
$lang['download_template'] = 'ดาวน์โหลดเทมเพลต';
$lang['upload_file'] = 'อัปโหลดไฟล์';
$lang['select_file'] = 'เลือกไฟล์';
$lang['no_file_selected'] = 'ไม่ได้เลือกไฟล์';

// Errors
$lang['error_loading_devices'] = 'เกิดข้อผิดพลาดในการโหลดอุปกรณ์';
$lang['error_saving_device'] = 'เกิดข้อผิดพลาดในการบันทึกอุปกรณ์';
$lang['error_deleting_device'] = 'เกิดข้อผิดพลาดในการลบอุปกรณ์';
$lang['error_loading_template'] = 'เกิดข้อผิดพลาดในการโหลดเทมเพลต';
$lang['error_communication_test'] = 'การทดสอบการสื่อสารล้มเหลว';
$lang['error_invalid_data'] = 'ข้อมูลไม่ถูกต้อง';

// Success
$lang['success_device_saved'] = 'บันทึกอุปกรณ์สำเร็จ';
$lang['success_device_deleted'] = 'ลบอุปกรณ์สำเร็จ';
$lang['success_communication_test'] = 'การทดสอบการสื่อสารสำเร็จ';
$lang['success_template_loaded'] = 'โหลดเทมเพลตสำเร็จ';

// Info
$lang['info_no_devices'] = 'ไม่พบอุปกรณ์';
$lang['info_no_templates'] = 'ไม่มีเทมเพลต';
$lang['info_loading'] = 'กำลังโหลด...';
$lang['info_saving'] = 'กำลังบันทึก...';
$lang['info_deleting'] = 'กำลังลบ...';
$lang['info_testing_communication'] = 'กำลังทดสอบการสื่อสาร...';
$lang['info_no_custom_fields'] = 'ไม่มีข้อมูลเพิ่มเติม'; 

// Alert Messages
$lang['device_saved_successfully'] = 'บันทึกอุปกรณ์สำเร็จ.';
$lang['device_created_successfully'] = 'สร้างอุปกรณ์สำเร็จ.';
$lang['device_updated_successfully'] = 'อัปเดตอุปกรณ์สำเร็จ.';
$lang['device_deleted_successfully'] = 'ลบอุปกรณ์สำเร็จ.';
$lang['failed_to_save_device'] = 'บันทึกอุปกรณ์ไม่สำเร็จ.';
$lang['failed_to_create_device'] = 'สร้างอุปกรณ์ไม่สำเร็จ.';
$lang['failed_to_update_device'] = 'อัปเดตอุปกรณ์ไม่สำเร็จ.';
$lang['failed_to_delete_device'] = 'ลบอุปกรณ์ไม่สำเร็จ.';
$lang['device_not_found'] = 'ไม่พบอุปกรณ์.';
$lang['error_deleting_device'] = 'เกิดข้อผิดพลาดในการลบอุปกรณ์.';
$lang['confirm_delete_device'] = 'คุณแน่ใจหรือไม่ที่จะลบอุปกรณ์นี้? การดำเนินการนี้ไม่สามารถยกเลิกได้.'; 