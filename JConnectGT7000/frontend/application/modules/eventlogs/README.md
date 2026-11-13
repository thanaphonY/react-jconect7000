# Event Logs Module

## Overview
Event Logs module provides comprehensive audit trail functionality for tracking user actions and system events across the application. It automatically logs user activities, system changes, and provides detailed reporting capabilities.

## Features

### 1. Enhanced Filtering & Search
- **Date Range Filter**: Today, Yesterday, Last 7/30 Days, Custom Range
- **User Filter**: Filter by specific users
- **Action Type Filter**: Filter by action types (create, update, delete, login, etc.)
- **Entity Type Filter**: Filter by sections (user, device, notification, etc.)
- **Status Filter**: Filter by success/fail status
- **Global Search**: Search across messages, actions, and sections

### 2. Dashboard Statistics
- Total Logs count
- Active Users count
- Unique Actions count
- Unique Sections count
- Real-time updates based on selected filters

### 3. Detailed Log View
- **Modal Popup**: View complete log details
- **Metadata Display**: Show old/new values, IP address, user agent
- **Status Indicators**: Visual success/fail status
- **Formatted Data**: JSON formatting for complex data

### 4. Export Functionality
- **Preview Before Export**: Review data before downloading
- **Multiple Formats**: CSV, Excel, PDF
- **Standardized Content**: Consistent column structure across formats
- **Filtered Export**: Export only filtered data

### 5. Automatic Logging
- **Helper Functions**: Easy integration with other modules
- **Comprehensive Metadata**: IP address, user agent, timestamps
- **Error Handling**: Graceful failure handling

## Database Structure

### event_logs Table
```sql
CREATE TABLE event_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES ia_users(ia_users_id),
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INTEGER,
    message TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    metadata JSONB
);
```

### Metadata JSONB Structure
```json
{
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "status": "success|fail",
    "old_values": {"field1": "old_value"},
    "new_values": {"field1": "new_value"},
    "affected_records": 1,
    "error_message": "Error details",
    "timestamp": "2024-01-01 12:00:00"
}
```

## Integration Guide

### 1. Load Helper in Controller
```php
public function __construct() {
    parent::__construct();
    $this->load->helper('eventlog');
}
```

### 2. Basic Usage Examples

#### Log User Login
```php
// Successful login
log_user_login($user_id, $username, true);

// Failed login
log_user_login($user_id, $username, false, 'Invalid password');
```

#### Log Record Operations
```php
// Create record
log_record_created('device', $device_id, $device_name, $new_values);

// Update record
$old_values = get_old_values('devices', $device_id, ['name', 'status']);
log_record_updated('device', $device_id, $device_name, $old_values, $new_values);

// Delete record
log_record_deleted('device', $device_id, $device_name, $deleted_values);
```

#### Log System Actions
```php
// System action
log_system_action('Backup completed', 'system', 0, ['backup_size' => '1.2GB']);

// Error logging
log_error('Database connection failed', 'system', 0, ['error_code' => 5001]);
```

#### Log Permission Denied
```php
log_permission_denied('delete', 'user', $user_id, 'Insufficient privileges');
```

### 3. Advanced Usage

#### Compare Values Before Update
```php
// Get old values
$old_values = get_old_values('devices', $device_id, ['name', 'status', 'location']);

// Update record
$this->db->where('id', $device_id);
$this->db->update('devices', $new_values);

// Log with comparison
$changed_values = get_changed_values($old_values, $new_values);
log_record_updated('device', $device_id, $device_name, $old_values, $new_values);
```

#### Custom Metadata
```php
$metadata = [
    'affected_records' => 5,
    'batch_operation' => true,
    'custom_field' => 'custom_value'
];

insert_event_log('batch_update', 'device', 0, 'Updated 5 devices', $metadata);
```

## API Endpoints

### GET /eventlogs/get_statistics
Returns statistics for dashboard cards.
```json
{
    "success": true,
    "stats": {
        "total_logs": 1250,
        "unique_users": 15,
        "unique_actions": 8,
        "unique_entities": 6
    }
}
```

### GET /eventlogs/get_log_detail/{id}
Returns detailed log information.
```json
{
    "success": true,
    "data": {
        "id": 123,
        "user_id": 5,
        "action_type": "update",
        "entity_type": "device",
        "entity_id": 45,
        "message": "Updated device: Sensor-001",
        "created_at": "2024-01-01 12:00:00",
        "metadata": {...},
        "user_name": "John Doe"
    }
}
```

### GET /eventlogs/export_preview
Shows preview of data to be exported with current filters.

### GET /eventlogs/export_data?format={csv|excel|pdf}
Downloads filtered data in specified format.

## Export Format Standards

### CSV/Excel Columns
1. **Date Time**: Formatted timestamp
2. **User**: User name
3. **Action**: Action type (capitalized)
4. **Section**: Entity type (capitalized)
5. **Message**: Action description
6. **IP Address**: User's IP address
7. **User Agent**: Browser/client info
8. **Status**: Success/Fail
9. **Additional Info**: Old/New values, affected records

### PDF Format
- Landscape orientation for better table fit
- Professional header with system name
- Formatted table with proper styling
- Page numbering and timestamps

## Security Considerations

### Access Control
- Module should only be accessible to admin users
- Implement permission checks using `CheckPermission()` function
- Consider role-based access for different log levels

### Data Privacy
- Sensitive data should be masked in logs
- Implement data retention policies
- Consider GDPR compliance for user data

### Performance
- Large log tables may impact performance
- Consider archiving old logs
- Implement proper indexing on frequently queried columns

## Best Practices

### 1. Consistent Naming
- Use consistent action_type values: 'create', 'update', 'delete', 'login', 'logout'
- Use consistent entity_type values: 'user', 'device', 'notification', 'system'

### 2. Meaningful Messages
- Write clear, descriptive messages
- Include relevant identifiers (names, IDs)
- Avoid sensitive information in messages

### 3. Metadata Usage
- Store complex data in metadata JSONB
- Use consistent metadata field names
- Include relevant context information

### 4. Error Handling
- Always wrap logging calls in try-catch blocks
- Don't let logging failures break main functionality
- Use appropriate error levels

## Troubleshooting

### Common Issues

1. **Helper Not Found**
   - Ensure helper is loaded in controller constructor
   - Check file path: `application/helpers/eventlog_helper.php`

2. **Model Not Found**
   - Ensure Eventlogs_model is loaded
   - Check model file exists: `application/modules/eventlogs/models/Eventlogs_model.php`

3. **Permission Errors**
   - Check user permissions using `CheckPermission()`
   - Ensure user is logged in

4. **Export Issues**
   - Check if required libraries are installed (PhpSpreadsheet, TCPDF)
   - Verify file permissions for temporary files

### Debug Mode
Enable debug logging in `config/config.php`:
```php
$config['log_threshold'] = 4;
```

Check logs in `application/logs/` for detailed error information. 