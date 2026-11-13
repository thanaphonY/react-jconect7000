<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Report_schedules_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function get_by_template($template_id)
    {
        return $this->db->get_where('report_schedules', ['template_id' => (int) $template_id])
            ->result();
    }

    public function get_all_active()
    {
        return $this->db->get_where('report_schedules', ['is_active' => true])->result();
    }

    /**
     * รายการ schedules ทั้งหมด พร้อมชื่อ template (สำหรับหน้า Manage Schedules)
     */
    public function get_all_with_templates()
    {
        $this->db->select('s.*, t.name AS template_name');
        $this->db->from('report_schedules s');
        $this->db->join('report_templates t', 't.id = s.template_id', 'left');
        $this->db->order_by('s.next_run_at', 'ASC');
        return $this->db->get()->result();
    }

    /**
     * Schedules ที่ถึงเวลาแล้ว (next_run_at <= now)
     */
    public function get_due_schedules()
    {
        $now = date('Y-m-d H:i:s');
        $this->db->where('is_active', true);
        $this->db->where('next_run_at <=', $now);
        $this->db->order_by('next_run_at', 'ASC');
        return $this->db->get('report_schedules')->result();
    }

    public function get($id)
    {
        return $this->db->get_where('report_schedules', ['id' => (int) $id], 1)->row();
    }

    public function save($data)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $row = [
            'template_id' => (int) $data['template_id'],
            'name' => trim($data['name'] ?? ''),
            'is_active' => !empty($data['is_active']),
            'schedule_type' => $data['schedule_type'] ?? 'daily',
            'schedule_config' => json_encode($data['schedule_config'] ?? []),
            'timezone' => $data['timezone'] ?? 'Asia/Bangkok',
            'export_format' => in_array($data['export_format'] ?? '', ['pdf', 'excel', 'csv']) ? $data['export_format'] : 'pdf',
            'recipients' => json_encode(is_array($data['recipients'] ?? null) ? $data['recipients'] : []),
            'report_params' => json_encode($data['report_params'] ?? []),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id > 0) {
            $this->db->where('id', $id);
            $this->db->update('report_schedules', $row);
            $this->_update_next_run($id);
            return $id;
        }
        $row['created_by'] = isset($data['created_by']) ? (int) $data['created_by'] : null;
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('report_schedules', $row);
        $newId = $this->db->insert_id();
        $this->_update_next_run($newId);
        return $newId;
    }

    public function delete($id)
    {
        $this->db->where('id', (int) $id);
        $this->db->delete('report_schedules');
        return $this->db->affected_rows() > 0;
    }

    public function update_last_run($id)
    {
        $now = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id);
        $this->db->update('report_schedules', ['last_run_at' => $now]);
        $this->_update_next_run($id);
    }

    /**
     * คำนวณ next_run_at จาก schedule_type + schedule_config + timezone
     */
    private function _update_next_run($id)
    {
        $row = $this->get($id);
        if (!$row) return;
        $tz = new DateTimeZone($row->timezone ?: 'Asia/Bangkok');
        $now = new DateTime('now', $tz);
        $config = is_string($row->schedule_config) ? json_decode($row->schedule_config, true) : (array) $row->schedule_config;
        $time = isset($config['time']) ? $config['time'] : '08:00';
        if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) $time = '08:00';

        $next = null;
        switch ($row->schedule_type) {
            case 'daily':
                $next = (clone $now)->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2), 0);
                if ($next <= $now) $next->modify('+1 day');
                break;
            case 'weekly':
                $weekday = isset($config['weekday']) ? (int) $config['weekday'] : 1;
                $next = (clone $now)->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2), 0);
                $dow = (int) $next->format('w');
                $diff = $weekday - ($dow ?: 7);
                if ($diff < 0) $diff += 7;
                $next->modify("+{$diff} days");
                if ($next <= $now) $next->modify('+7 days');
                break;
            case 'monthly':
                $day = isset($config['day']) ? min(28, max(1, (int) $config['day'])) : 1;
                $next = (clone $now)->setDate((int) $now->format('Y'), (int) $now->format('m'), $day)
                    ->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2), 0);
                if ($next <= $now) $next->modify('first day of next month')->setDate((int) $next->format('Y'), (int) $next->format('m'), min($day, (int) $next->format('t')));
                break;
            default:
                $next = (clone $now)->modify('+1 day')->setTime(8, 0, 0);
        }
        $next->setTimezone(new DateTimeZone('UTC'));
        $this->db->where('id', (int) $id);
        $this->db->update('report_schedules', ['next_run_at' => $next->format('Y-m-d H:i:s')]);
    }
}
