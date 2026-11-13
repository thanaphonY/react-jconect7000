<?php
class Alarmstate_model extends CI_Model
{
    public function get_active_alarms()
    {
        $this->db->select('a.*, d.name as device_name, d.metrics');
        $this->db->from('alarm_state a');
        $this->db->join('devices d', 'a.device_id = d.id', 'left');
        $this->db->where('a.active', true);
        $this->db->where('d.is_active', true);
        $this->db->where('d.is_deleted', false);
        $this->db->order_by('a.last_triggered', 'DESC');
    
        $alarms = $this->db->get()->result();
    
        // แปลง metric_key → label
        foreach ($alarms as &$a) {
            $metrics = json_decode($a->metrics ?? '[]', true);
            $label = '';
            foreach ($metrics as $m) {
                if ($m['key'] === $a->metric_key) {
                    $label = $m['label'] ?? $m['key'];
                    break;
                }
            }
            $a->metric_label = $label ?: $a->metric_key;
        }
    
        return $alarms;
    }
    
    public function acknowledge($device_id, $metric_key, $user)
    {
        $this->db->where('device_id', $device_id);
        $this->db->where('metric_key', $metric_key);
        return $this->db->update('alarm_state', [
            'acknowledged' => true,
            'acknowledged_by' => $user,
            'acknowledged_at' => date('Y-m-d H:i:s')
        ]);
    }
}
