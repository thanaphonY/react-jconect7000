<?php
/**
 * Notification Manager Model
 * Handles notification channel statistics and device relationships
 */
class Notification_manager_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Get number of connected devices for a channel
     */
    public function getConnectedDevices($channelId) {
        $result = $this->db->select('COUNT(*) as count')
                           ->from('devices')
                           ->where('is_deleted', false)
                           ->where('is_active', true)
                           ->where("metrics::text LIKE '%\"channels\":[%' || ? || '%]'", $channelId)
                           ->get()
                           ->row();
        
        return $result ? $result->count : 0;
    }
    
    /**
     * Get last message sent through a channel
     */
    public function getLastMessage($channelId) {
        $result = $this->db->select('ald.sent_at, al.message')
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
    public function getSuccessRate($channelId) {
        $total = $this->db->where('channel_id', $channelId)->count_all_results('alarm_log_dispatch');
        $success = $this->db->where('channel_id', $channelId)
                            ->where('status', 'sent')
                            ->count_all_results('alarm_log_dispatch');
        
        return $total > 0 ? round(($success / $total) * 100) : 0;
    }
    
    /**
     * Get detailed list of devices connected to a channel
     */
    public function getDeviceList($channelId) {
        $devices = $this->db->select('id, name, location, description, metrics')
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
     */
    public function getMessageHistory($channelId, $limit = 50) {
        return $this->db->select('ald.id, ald.status, ald.sent_at, ald.error, al.message, al.severity, d.name as device_name')
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
    public function getChannelTemplate($channelId) {
        $channel = $this->db->where('id', $channelId)->get('notification_channels')->row();
        
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
     */
    public function sendTestMessage($channelId, $message = null) {
        $channel = $this->db->where('id', $channelId)->get('notification_channels')->row();
        
        if (!$channel) {
            return ['success' => false, 'error' => 'Channel not found'];
        }
        
        // Use provided message or channel template
        $testMessage = $message ?: $this->getChannelTemplate($channelId);
        
        // Simulate sending (in real implementation, call the appropriate driver)
        $success = rand(0, 1) == 1; // Random success/failure for demo
        
        if ($success) {
            // Log successful test message
            $this->db->insert('alarm_log_dispatch', [
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
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        $stats = [];
        
        // Total devices
        $stats['total_devices'] = $this->db->where('is_deleted', false)->count_all_results('devices');
        
        // Today's alarms
        $today = date('Y-m-d');
        $stats['total_alarms'] = $this->db->where('DATE(created_at)', $today)
                                         ->count_all_results('alarm_logs');
        
        // Total channels
        $stats['total_channels'] = $this->db->count_all_results('notification_channels');
        
        // Active channels (channels with connected devices)
        $stats['active_channels'] = $this->db->select('COUNT(DISTINCT nc.id) as count')
                                            ->from('notification_channels nc')
                                            ->join('devices d', "d.metrics::text LIKE '%\"channels\":[%' || nc.id::text || '%]'")
                                            ->where('d.is_deleted', false)
                                            ->where('d.is_active', true)
                                            ->get()
                                            ->row()
                                            ->count;
        
        return $stats;
    }
    
    /**
     * Get metrics that use a specific notification channel
     */
    public function getMetricsUsingChannel($channelId) {
        $devices = $this->db->select('id, name, metrics')
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
} 