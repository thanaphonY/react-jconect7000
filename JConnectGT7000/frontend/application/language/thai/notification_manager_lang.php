<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$lang['notification_manager'] = 'จัดการการแจ้งเตือน';
$lang['notification_channels'] = 'ช่องทางการแจ้งเตือน';
$lang['add_channel'] = 'เพิ่มช่องทาง';
$lang['edit_channel'] = 'แก้ไขช่องทาง';
$lang['delete_channel'] = 'ลบช่องทาง';
$lang['restore_channel'] = 'กู้คืนช่องทาง';

// Alert Messages
$lang['channel_created_successfully'] = 'สร้างช่องทางสำเร็จ!';
$lang['channel_updated_successfully'] = 'อัปเดตช่องทางสำเร็จ!';
$lang['channel_deleted_successfully'] = 'ลบช่องทางสำเร็จ (soft delete)';
$lang['channel_restored_successfully'] = 'กู้คืนช่องทางสำเร็จ';
$lang['failed_to_create_channel'] = 'สร้างช่องทางไม่สำเร็จ!';
$lang['failed_to_update_channel'] = 'อัปเดตช่องทางไม่สำเร็จ!';
$lang['failed_to_delete_channel'] = 'ลบช่องทางไม่สำเร็จ';
$lang['failed_to_restore_channel'] = 'กู้คืนช่องทางไม่สำเร็จ';
$lang['channel_not_found'] = 'ไม่พบช่องทาง';
$lang['permission_denied'] = 'ไม่มีสิทธิ์เข้าถึง';

// Channel Types
$lang['email_channel'] = 'ช่องทางอีเมล';
$lang['sms_channel'] = 'ช่องทาง SMS';
$lang['line_channel'] = 'ช่องทาง Line';
$lang['webhook_channel'] = 'ช่องทาง Webhook';

// Form Fields
$lang['channel_name'] = 'ชื่อช่องทาง';
$lang['channel_type'] = 'ประเภทช่องทาง';
$lang['message_template'] = 'เทมเพลตข้อความ';
$lang['description'] = 'คำอธิบาย';
$lang['is_active'] = 'ใช้งาน';
$lang['smtp_configuration'] = 'การตั้งค่า SMTP';
$lang['smtp_host'] = 'SMTP Host';
$lang['smtp_port'] = 'SMTP Port';
$lang['smtp_secure'] = 'SMTP Secure';
$lang['smtp_username'] = 'SMTP Username';
$lang['smtp_password'] = 'SMTP Password';
$lang['from_email'] = 'อีเมลผู้ส่ง';
$lang['email_subject'] = 'หัวข้ออีเมล';
$lang['recipient_emails'] = 'อีเมลผู้รับ';

// Placeholders
$lang['available_placeholders'] = 'ตัวแปรที่ใช้ได้: {SEVERITY}, {TIMESTAMP}';
$lang['multiple_emails_hint'] = 'ใส่หลาย email โดยใช้เครื่องหมายจุลภาค (,) หรือขึ้นบรรทัดใหม่ เช่น: user1@example.com, user2@example.com';

// Actions
$lang['save_channel'] = 'บันทึกช่องทาง';
$lang['cancel'] = 'ยกเลิก';
$lang['test_channel'] = 'ทดสอบช่องทาง';
$lang['view_devices'] = 'ดูอุปกรณ์';
$lang['view_history'] = 'ดูประวัติ';

// Statistics
$lang['total_channels'] = 'ช่องทางทั้งหมด';
$lang['active_channels'] = 'ช่องทางที่ใช้งาน';
$lang['total_devices'] = 'อุปกรณ์ทั้งหมด';
$lang['total_alarms'] = 'การแจ้งเตือนทั้งหมด';

// Status
$lang['status_active'] = 'ใช้งาน';
$lang['status_inactive'] = 'ไม่ใช้งาน';
$lang['status_pending'] = 'รอดำเนินการ';
$lang['status_deleted'] = 'ถูกลบ'; 