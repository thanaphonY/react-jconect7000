<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$lang['notification_manager'] = 'Notification Manager';
$lang['notification_channels'] = 'Notification Channels';
$lang['add_channel'] = 'Add Channel';
$lang['edit_channel'] = 'Edit Channel';
$lang['delete_channel'] = 'Delete Channel';
$lang['restore_channel'] = 'Restore Channel';

// Alert Messages
$lang['channel_created_successfully'] = 'Channel created successfully!';
$lang['channel_updated_successfully'] = 'Channel updated successfully!';
$lang['channel_deleted_successfully'] = 'Channel has been soft deleted successfully';
$lang['channel_restored_successfully'] = 'Channel has been restored successfully';
$lang['failed_to_create_channel'] = 'Failed to create channel!';
$lang['failed_to_update_channel'] = 'Failed to update channel!';
$lang['failed_to_delete_channel'] = 'Failed to delete channel';
$lang['failed_to_restore_channel'] = 'Failed to restore channel';
$lang['channel_not_found'] = 'Channel not found';
$lang['permission_denied'] = 'Permission denied';

// Channel Types
$lang['email_channel'] = 'Email Channel';
$lang['sms_channel'] = 'SMS Channel';
$lang['line_channel'] = 'Line Channel';
$lang['webhook_channel'] = 'Webhook Channel';

// Form Fields
$lang['channel_name'] = 'Channel Name';
$lang['channel_type'] = 'Channel Type';
$lang['message_template'] = 'Message Template';
$lang['description'] = 'Description';
$lang['is_active'] = 'Active';
$lang['smtp_configuration'] = 'SMTP Configuration';
$lang['smtp_host'] = 'SMTP Host';
$lang['smtp_port'] = 'SMTP Port';
$lang['smtp_secure'] = 'SMTP Secure';
$lang['smtp_username'] = 'SMTP Username';
$lang['smtp_password'] = 'SMTP Password';
$lang['from_email'] = 'From Email (Sender)';
$lang['email_subject'] = 'Email Subject';
$lang['recipient_emails'] = 'Recipient Emails';

// Placeholders
$lang['available_placeholders'] = 'Available placeholders: {SEVERITY}, {TIMESTAMP}';
$lang['multiple_emails_hint'] = 'Enter multiple emails using commas (,) or new lines, e.g.: user1@example.com, user2@example.com';

// Actions
$lang['save_channel'] = 'Save Channel';
$lang['cancel'] = 'Cancel';
$lang['test_channel'] = 'Test Channel';
$lang['view_devices'] = 'View Devices';
$lang['view_history'] = 'View History';

// Statistics
$lang['total_channels'] = 'Total Channels';
$lang['active_channels'] = 'Active Channels';
$lang['total_devices'] = 'Total Devices';
$lang['total_alarms'] = 'Total Alarms';

// Status
$lang['status_active'] = 'Active';
$lang['status_inactive'] = 'Inactive';
$lang['status_pending'] = 'Pending';
$lang['status_deleted'] = 'Deleted'; 