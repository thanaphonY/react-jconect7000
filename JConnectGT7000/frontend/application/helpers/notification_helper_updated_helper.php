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
    
    // Get all devices and check their metrics JSONB
    $devices = $CI->db->select('id, metrics')
                      ->from('devices')
                      ->where('is_deleted', false)
                      ->where('is_active', true)
                      ->get()
                      ->result();
    
    $count = 0;
    
    foreach ($devices as $device) {
        $metrics = json_decode($device->metrics, true);
        if (!$metrics) continue;
        
        foreach ($metrics as $metric) {
            if (isset($metric['triggers']) && is_array($metric['triggers'])) {
                foreach ($metric['triggers'] as $trigger) {
                    if (isset($trigger['channels']) && is_array($trigger['channels'])) {
                        if (in_array($channelId, $trigger['channels'])) {
                            $count++;
                            break 2; // Found this device, move to next device
                        }
                    }
                }
            }
        }
    }
    
    return $count;
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
                            // Support clear_condition (operator, value)
                            $clear_operator = isset($trigger['clear_condition']['operator']) ? $trigger['clear_condition']['operator'] : '';
                            $clear_value = isset($trigger['clear_condition']['value']) ? $trigger['clear_condition']['value'] : '';
                            $connectedDevices[] = [
                                'device_id' => $device->id,
                                'device_name' => $device->name,
                                'location' => $device->location,
                                'description' => $device->description,
                                'metric_key' => $metric['key'],
                                'metric_label' => $metric['label'],
                                'metric_unit' => $metric['unit'],
                                'trigger_value' => isset($trigger['value']) ? $trigger['value'] : '',
                                'trigger_operator' => isset($trigger['operator']) ? $trigger['operator'] : '',
                                'trigger_message' => isset($trigger['message']) ? $trigger['message'] : '',
                                'severity' => isset($trigger['severity']) ? $trigger['severity'] : '',
                                'is_enabled' => isset($trigger['enable']) && $trigger['enable'] === 'true',
                                'clear_operator' => $clear_operator,
                                'clear_value' => $clear_value
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
 * Calls the alarm_worker.js API at port 3004
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
    
    // Call alarm_worker.js API
    $url = 'http://localhost:3004/api/send-test';
    $data = [
        'channelId' => $channelId,
        'message' => $testMessage
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        return [
            'success' => false,
            'error' => 'Failed to connect to alarm worker service',
            'channel' => $channel->name,
            'type' => $channel->type
        ];
    }
    
    $response = json_decode($result, true);
    
    if (!$response) {
        return [
            'success' => false,
            'error' => 'Invalid response from alarm worker service',
            'channel' => $channel->name,
            'type' => $channel->type
        ];
    }
    
    return [
        'success' => $response['success'] ?? false,
        'message' => $response['message'] ?? 'Test message sent',
        'error' => $response['error'] ?? null,
        'channel' => $channel->name,
        'type' => $channel->type,
        'sent_at' => date('Y-m-d H:i:s')
    ];
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