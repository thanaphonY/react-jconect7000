<?php
class Alarmlogs_model extends CI_Model {

    private function applyScope($column = 'alarm_logs.device_id')
    {
        $ids = function_exists('site_visible_device_ids') ? site_visible_device_ids() : null;
        if ($ids === null) {
            return;
        }
        if (!$ids) {
            $this->db->where('1 = 0', null, false);
            return;
        }
        $clean = implode(',', array_map('intval', $ids));
        $this->db->where($column . ' IN (' . $clean . ')', null, false);
    }
    
    public function getAll() {
        $this->db->select('alarm_logs.*, devices.name AS device_name');
        $this->db->from('alarm_logs');
        $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');
        $this->applyScope();
        $this->db->order_by('alarm_logs.created_at', 'DESC');
        return $this->db->get()->result();
    }

    public function get_total_alarms() {
        $this->applyScope();
        return $this->db->count_all_results('alarm_logs');
    }

    public function get_active_alarms() {
        $this->applyScope();
        $this->db->where('status', 'active');
        return $this->db->count_all_results('alarm_logs');
    }

    public function get_severity_breakdown() {
        $ids = site_visible_device_ids();
        if (is_array($ids)) {
            if (!$ids) {
                return array();
            }
            $in = implode(',', array_map('intval', $ids));
            return $this->db->query("SELECT severity, COUNT(*) AS count FROM alarm_logs WHERE device_id IN ($in) GROUP BY severity ORDER BY count DESC")->result();
        }
        $this->db->select('severity, COUNT(*) as count');
        $this->db->from('alarm_logs');
        $this->db->group_by('severity');
        $this->db->order_by('count', 'DESC');
        return $this->db->get()->result();
    }

    public function get_device_breakdown() {
        $ids = site_visible_device_ids();
        if (is_array($ids)) {
            if (!$ids) {
                return array();
            }
            $in = implode(',', array_map('intval', $ids));
            return $this->db->query("SELECT devices.name AS device_name, COUNT(*) AS count FROM alarm_logs JOIN devices ON devices.id = alarm_logs.device_id WHERE alarm_logs.device_id IN ($in) GROUP BY devices.id, devices.name ORDER BY count DESC LIMIT 5")->result();
        }
        $this->db->select('devices.name as device_name, COUNT(*) as count');
        $this->db->from('alarm_logs');
        $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');
        $this->db->group_by('devices.id, devices.name');
        $this->db->order_by('count', 'DESC');
        $this->db->limit(5);
        return $this->db->get()->result();
    }

    public function get_unique_devices() {
        $ids = site_visible_device_ids();
        if (is_array($ids)) {
            if (!$ids) {
                return array();
            }
            $in = implode(',', array_map('intval', $ids));
            return $this->db->query("SELECT devices.id, devices.name FROM alarm_logs JOIN devices ON devices.id = alarm_logs.device_id WHERE devices.name IS NOT NULL AND alarm_logs.device_id IN ($in) GROUP BY devices.id, devices.name ORDER BY devices.name ASC")->result();
        }
        $this->db->select('devices.id, devices.name');
        $this->db->from('alarm_logs');
        $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');
        $this->db->where('devices.name IS NOT NULL');
        $this->db->group_by('devices.id, devices.name');
        $this->db->order_by('devices.name', 'ASC');
        return $this->db->get()->result();
    }

    public function get_unique_severities() {
        $ids = site_visible_device_ids();
        if (is_array($ids)) {
            if (!$ids) {
                return array();
            }
            $in = implode(',', array_map('intval', $ids));
            return $this->db->query("SELECT severity FROM alarm_logs WHERE severity IS NOT NULL AND device_id IN ($in) GROUP BY severity ORDER BY severity ASC")->result();
        }
        $this->db->select('severity');
        $this->db->from('alarm_logs');
        $this->db->where('severity IS NOT NULL');
        $this->db->group_by('severity');
        $this->db->order_by('severity', 'ASC');
        return $this->db->get()->result();
    }

    public function get_unique_statuses() {
        $this->db->select('status');
        $this->db->from('alarm_logs');
        $this->db->where('status IS NOT NULL');
        $this->db->group_by('status');
        $this->db->order_by('status', 'ASC');
        return $this->db->get()->result();
    }

    public function get_logs_for_export($range = null, $startDate = null, $endDate = null, $device_filter = null, $severity_filter = null, $status_filter = null, $search = null) {
        $this->db->select('alarm_logs.*, devices.name AS device_name');
        $this->db->from('alarm_logs');
        $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');
        $this->applyScope();

        // Apply date range filter
        if ($range) {
            switch ($range) {
                case 'today':
                    $this->db->where('DATE(alarm_logs.created_at)', date('Y-m-d'));
                    break;
                case 'yesterday':
                    $this->db->where('DATE(alarm_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                    break;
                case 'last_7_days':
                    $this->db->where('DATE(alarm_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                    break;
                case 'last_30_days':
                    $this->db->where('DATE(alarm_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                    break;
                case 'custom':
                    if ($startDate && $endDate) {
                        $this->db->where('DATE(alarm_logs.created_at) >=', $startDate);
                        $this->db->where('DATE(alarm_logs.created_at) <=', $endDate);
                    }
                    break;
            }
        }

        // Apply additional filters
        if ($device_filter && $device_filter !== 'all') {
            $this->db->where('alarm_logs.device_id', $device_filter);
        }
        if ($severity_filter && $severity_filter !== 'all') {
            $this->db->where('alarm_logs.severity', $severity_filter);
        }
        if ($status_filter && $status_filter !== 'all') {
            $this->db->where('alarm_logs.status', $status_filter);
        }

        // Apply search
        if ($search && !empty(trim($search))) {
            $this->db->group_start();
            $this->db->like('devices.name', $search);
            $this->db->or_like('alarm_logs.metric_key', $search);
            $this->db->or_like('alarm_logs.message', $search);
            $this->db->or_like('alarm_logs.severity', $search);
            $this->db->group_end();
        }

        $this->db->order_by('alarm_logs.created_at', 'DESC');
        $query = $this->db->get();
        return $query->result();
    }

    public function update_alarm_status($alarm_id, $status, $note = null)
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($note) {
            $data['note'] = $note;
        }

        $this->db->where('id', $alarm_id);
        return $this->db->update('alarm_logs', $data);
    }

    public function get_alarm_by_id($alarm_id)
    {
        $this->db->select('alarm_logs.*, devices.name AS device_name, devices.device_information');
        $this->db->from('alarm_logs');
        $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');
        $this->db->where('alarm_logs.id', $alarm_id);
        return $this->db->get()->row();
    }

    public function get_related_alarms($device_id, $metric_key, $exclude_id = null, $limit = 5)
    {
        $this->db->select('alarm_logs.*');
        $this->db->from('alarm_logs');
        $this->db->where('device_id', $device_id);
        $this->db->where('metric_key', $metric_key);
        
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get()->result();
    }

    public function get_dispatch_history($alarm_id)
    {
        $this->db->select('alarm_log_dispatch.*, notification_channels.name as channel_name, notification_channels.type as channel_type');
        $this->db->from('alarm_log_dispatch');
        $this->db->join('notification_channels', 'notification_channels.id = alarm_log_dispatch.channel_id', 'left');
        $this->db->where('alarm_log_dispatch.alarm_log_id', $alarm_id);
        $this->db->order_by('alarm_log_dispatch.created_at', 'DESC');
        return $this->db->get()->result();
    }

    public function get_alarm_trends($days = 30)
    {
        $this->db->select('DATE(created_at) as date, COUNT(*) as count, severity');
        $this->db->from('alarm_logs');
        $this->db->where('created_at >=', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        $this->db->group_by('DATE(created_at), severity');
        $this->db->order_by('date', 'ASC');
        return $this->db->get()->result();
    }

    public function get_device_alarm_summary($device_id = null)
    {
        $this->db->select('devices.name as device_name, COUNT(*) as total_alarms, 
                          SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) as critical_count,
                          SUM(CASE WHEN severity = "high" THEN 1 ELSE 0 END) as high_count,
                          SUM(CASE WHEN severity = "medium" THEN 1 ELSE 0 END) as medium_count,
                          SUM(CASE WHEN severity = "low" THEN 1 ELSE 0 END) as low_count,
                          SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_count');
        $this->db->from('alarm_logs');
        $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');
        
        if ($device_id) {
            $this->db->where('alarm_logs.device_id', $device_id);
        }
        
        $this->db->group_by('devices.id, devices.name');
        $this->db->order_by('total_alarms', 'DESC');
        return $this->db->get()->result();
    }

    public function get_metric_alarm_summary()
    {
        $this->db->select('metric_key, COUNT(*) as total_alarms, 
                          SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) as critical_count,
                          SUM(CASE WHEN severity = "high" THEN 1 ELSE 0 END) as high_count,
                          SUM(CASE WHEN severity = "medium" THEN 1 ELSE 0 END) as medium_count,
                          SUM(CASE WHEN severity = "low" THEN 1 ELSE 0 END) as low_count');
        $this->db->from('alarm_logs');
        $this->db->group_by('metric_key');
        $this->db->order_by('total_alarms', 'DESC');
        return $this->db->get()->result();
    }
}