<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Get channel icon based on type
 */
function getChannelIcon($type) {
    $icons = [
        'line' => 'chat-dots',
        'email' => 'envelope',
        'telegram' => 'telegram',
        'webhook' => 'link'
    ];
    return isset($icons[$type]) ? $icons[$type] : 'bell';
}

/**
 * Get number of connected devices for a channel
 */
function getConnectedDevices($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    // Query to get devices that use this notification channel
    $result = $CI->db->select('COUNT(DISTINCT device_id) as count')
                     ->from('device_notifications')
                     ->where('notification_channel_id', $channelId)
                     ->where('is_active', true)
                     ->get()
                     ->row();
    
    return $result ? $result->count : 0;
}

/**
 * Get last message sent through this channel
 */
function getLastMessage($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    // Query to get last message from alarm_log_dispatch (existing table)
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
 */
function getSuccessRate($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    // Query to get success rate from alarm_log_dispatch (existing table)
    $total = $CI->db->where('channel_id', $channelId)->count_all_results('alarm_log_dispatch');
    $success = $CI->db->where('channel_id', $channelId)
                      ->where('status', 'sent')
                      ->count_all_results('alarm_log_dispatch');
    
    return $total > 0 ? round(($success / $total) * 100) : 0;
}

/**
 * Get device list for a channel
 */
function getDeviceList($channelId) {
    $CI =& get_instance();
    $CI->load->database();
    
    return $CI->db->select('d.name as device_name, d.id as device_id, d.location, dn.metric_name, dn.threshold')
                  ->from('devices d')
                  ->join('device_notifications dn', 'd.id = dn.device_id')
                  ->where('dn.notification_channel_id', $channelId)
                  ->where('dn.is_active', true)
                  ->where('d.is_deleted', false)
                  ->order_by('d.name', 'asc')
                  ->get()
                  ->result();
}

/**
 * Get message history for a channel
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
    $CI->load->model('Notification_channel_model');
    
    $channel = $CI->Notification_channel_model->getById($channelId);
    if (!$channel) return '';
    
    $template = $channel->message_template ?: '{{metric}} = {{value}}';
    
    // Replace placeholders with sample values
    $preview = str_replace(
        ['{{metric}}', '{{value}}', '{{device}}', '{{timestamp}}'],
        ['Temperature', '25.5°C', 'Device-001', date('Y-m-d H:i:s')],
        $template
    );
    
    return $preview;
}

/**
 * Send test message through a channel
 */
function sendTestMessage($channelId, $message) {
    $CI =& get_instance();
    $CI->load->model('Notification_channel_model');
    
    $channel = $CI->Notification_channel_model->getById($channelId);
    if (!$channel) {
        return ['success' => false, 'error' => 'Channel not found'];
    }
    
    try {
        // Load appropriate driver based on channel type
        $CI->load->library('notification/' . ucfirst($channel->type) . 'Driver');
        $driver = $CI->{strtolower($channel->type) . 'driver'};
        
        // Send test message
        $result = $driver->send($message, json_decode($channel->config, true));
        
        // Log the test message
        $CI->db->insert('notification_logs', [
            'channel_id' => $channelId,
            'message' => $message,
            'status' => $result ? 'success' : 'failed',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return ['success' => $result, 'error' => $result ? null : 'Failed to send message'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
} 