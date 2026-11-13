<?php
/**
 * Enhanced Notification Manager Helper Functions
 * Compatible with existing JConnect structure using devices.metrics JSONB
 */

/**
 * Get Bootstrap icon class based on channel type
 */
function getChannelIcon($type) {
    $icons = [
        'email' => 'bi-envelope',
        'line' => 'bi-chat-dots',
        'line_oa' => 'bi-chat-square-dots',
        'sms' => 'bi-phone',
        'webhook' => 'bi-link-45deg',
        'slack' => 'bi-slack',
        'telegram' => 'bi-telegram'
    ];
    
    return isset($icons[$type]) ? $icons[$type] : 'bi-bell';
}

/**
 * Get number of connected devices for a channel
 * Uses existing devices.metrics structure
 */
function getConnectedDevices($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    // Query devices that have this channel ID in their metrics triggers
    $result = $CI->db->select('COUNT(*) as count')
                     ->from('devices')
                     ->where('is_deleted', false)
                     ->where('is_active', true)
                     ->where("metrics::text LIKE '%\"channels\":[%' || ? || '%]'", $channelId)
                     ->get()
                     ->row();
    
    return $result ? $result->count : 0;
}

/**
 * Get last message sent through this channel
 * Uses existing alarm_log_dispatch table
 */
function getLastMessage($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    $result = $CI->db->select('ald.sent_at, al.message')
                     ->from('alarm_log_dispatch ald')
                     ->join('alarm_logs al', 'ald.alarm_log_id = al.id')
                     ->where('ald.channel_id', $channelId)
                     ->where('ald.status', 'sent')
                     ->order_by('ald.sent_at', 'DESC')
                     ->limit(1)
                     ->get()
                     ->row();
    
    if ($result) {
        return date('M d, H:i', strtotime($result->sent_at)) . ' - ' . 
               substr($result->message, 0, 50) . (strlen($result->message) > 50 ? '...' : '');
    }
    
    return 'No messages yet';
}

/**
 * Get success rate for a channel
 * Uses existing alarm_log_dispatch table
 */
function getSuccessRate($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    $total = $CI->db->where('channel_id', $channelId)->count_all_results('alarm_log_dispatch');
    $success = $CI->db->where('channel_id', $channelId)
                      ->where('status', 'sent')
                      ->count_all_results('alarm_log_dispatch');
    
    return $total > 0 ? round(($success / $total) * 100) : 0;
}

/**
 * Get detailed list of devices connected to a channel
 * Uses existing devices.metrics structure
 */
function getDeviceList($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    $devices = $CI->db->select('id, name, location, description, metrics')
                      ->from('devices')
                      ->where('is_deleted', false)
                      ->where('is_active', true)
                      ->get()
                      ->result();
    
    $connectedDevices = [];
    
    foreach ($devices as $device) {
        $metrics = json_decode($device->metrics, true);
        if (!$metrics) continue;
        
        foreach ($metrics as $metric) {
            if (isset($metric['triggers']) && is_array($metric['triggers'])) {
                foreach ($metric['triggers'] as $trigger) {
                    if (isset($trigger['channels']) && is_array($trigger['channels'])) {
                        if (in_array($channelId, $trigger['channels'])) {
                            $connectedDevices[] = [
                                'device_id' => $device->id,
                                'device_name' => $device->name,
                                'location' => $device->location,
                                'description' => $device->description,
                                'metric_key' => $metric['key'],
                                'metric_label' => $metric['label'],
                                'metric_unit' => $metric['unit'],
                                'trigger_value' => $trigger['value'],
                                'trigger_operator' => $trigger['operator'],
                                'trigger_message' => $trigger['message'],
                                'severity' => $trigger['severity'],
                                'is_enabled' => $trigger['enable'] === 'true'
                            ];
                        }
                    }
                }
            }
        }
    }
    
    return $connectedDevices;
}

/**
 * Get message history for a channel
 * Uses existing alarm_log_dispatch table
 */
function getMessageHistory($channelId, $limit = 50) {
    $CI =& get_instance();
    $CI->load->database();
    
    return $CI->db->select('ald.id, ald.status, ald.sent_at, ald.error, al.message, al.severity, d.name as device_name')
                  ->from('alarm_log_dispatch ald')
                  ->join('alarm_logs al', 'ald.alarm_log_id = al.id')
                  ->join('devices d', 'al.device_id = d.id')
                  ->where('ald.channel_id', $channelId)
                  ->order_by('ald.sent_at', 'DESC')
                  ->limit($limit)
                  ->get()
                  ->result();
}

/**
 * Get channel template preview
 */
function getChannelTemplate($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    $channel = $CI->db->where('id', $channelId)->get('notification_channels')->row();
    
    if (!$channel) return 'Channel not found';
    
    $template = $channel->message_template ?: 'Default template';
    
    // Replace placeholders with sample values
    $sampleData = [
        '{DEVICE_NAME}' => 'Sample Device',
        '{METRIC_NAME}' => 'Temperature',
        '{VALUE}' => '25.5',
        '{UNIT}' => '°C',
        '{LOCATION}' => 'Server Room',
        '{TIMESTAMP}' => date('Y-m-d H:i:s'),
        '{SEVERITY}' => 'Warning'
    ];
    
    return str_replace(array_keys($sampleData), array_values($sampleData), $template);
}

/**
 * Send test message through a channel
 * This is a simulation - in real implementation, you'd call the actual driver
 */
function sendTestMessage($channelId, $message = null) {
    $CI =& get_instance();
    $CI->load->database();
    
    $channel = $CI->db->where('id', $channelId)->get('notification_channels')->row();
    
    if (!$channel) {
        return ['success' => false, 'error' => 'Channel not found'];
    }
    
    // Use provided message or channel template
    $testMessage = $message ?: getChannelTemplate($channelId);
    
    // Simulate sending (in real implementation, call the appropriate driver)
    $success = rand(0, 1) == 1; // Random success/failure for demo
    
    if ($success) {
        // Log successful test message
        $CI->db->insert('alarm_log_dispatch', [
            'alarm_log_id' => null, // Test message, no alarm log
            'channel_id' => $channelId,
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'success' => true,
            'message' => 'Test message sent successfully',
            'channel' => $channel->name,
            'type' => $channel->type,
            'sent_at' => date('Y-m-d H:i:s')
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Failed to send test message',
            'channel' => $channel->name,
            'type' => $channel->type
        ];
    }
}

/**
 * Get metrics that use a specific notification channel
 * Uses existing devices.metrics structure
 */
function getMetricsUsingChannel($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    $devices = $CI->db->select('id, name, metrics')
                      ->from('devices')
                      ->where('is_deleted', false)
                      ->where('is_active', true)
                      ->get()
                      ->result();
    
    $metrics = [];
    
    foreach ($devices as $device) {
        $deviceMetrics = json_decode($device->metrics, true);
        if (!$deviceMetrics) continue;
        
        foreach ($deviceMetrics as $metric) {
            if (isset($metric['triggers']) && is_array($metric['triggers'])) {
                foreach ($metric['triggers'] as $trigger) {
                    if (isset($trigger['channels']) && is_array($trigger['channels'])) {
                        if (in_array($channelId, $trigger['channels'])) {
                            $metrics[] = [
                                'device_id' => $device->id,
                                'device_name' => $device->name,
                                'metric_key' => $metric['key'],
                                'metric_label' => $metric['label'],
                                'metric_unit' => $metric['unit'],
                                'trigger_value' => $trigger['value'],
                                'trigger_operator' => $trigger['operator'],
                                'trigger_message' => $trigger['message'],
                                'severity' => $trigger['severity']
                            ];
                        }
                    }
                }
            }
        }
    }
    
    return $metrics;
}

/**
 * Get current values for metrics using a channel
 * Combines device metrics with current device_data
 */
function getCurrentMetricValues($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    $metrics = getMetricsUsingChannel($channelId);
    $currentValues = [];
    
    foreach ($metrics as $metric) {
        // Get latest value from device_data
        $latestData = $CI->db->select('value, timestamp')
                             ->from('device_data')
                             ->where('device_id', $metric['device_id'])
                             ->where('key', $metric['metric_key'])
                             ->order_by('timestamp', 'DESC')
                             ->limit(1)
                             ->get()
                             ->row();
        
        $currentValues[] = [
            'device_name' => $metric['device_name'],
            'metric_label' => $metric['metric_label'],
            'metric_unit' => $metric['metric_unit'],
            'current_value' => $latestData ? $latestData->value : 'N/A',
            'last_reading' => $latestData ? $latestData->timestamp : 'N/A',
            'threshold' => $metric['trigger_value'],
            'operator' => $metric['trigger_operator'],
            'severity' => $metric['severity']
        ];
    }
    
    return $currentValues;
} 