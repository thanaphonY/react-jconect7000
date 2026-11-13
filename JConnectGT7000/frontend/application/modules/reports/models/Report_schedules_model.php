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
        // next_run_at is timestamptz. Compare with the database clock so a
        // Bangkok local timestamp is not read as UTC and run hours early.
        $this->db->where('is_active', true);
        $this->db->where('next_run_at <= NOW()', null, false);
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
        $this->claim($id);
    }

    /**
     * Move next_run_at forward only if this row is still due.
     * A second poll in the same minute then updates zero rows and does not export again.
     */
    public function claim($id)
    {
        $row = $this->get($id);
        if (!$row) {
            return false;
        }
        $next = $this->_compute_next_run($row);
        $this->db->where('id', (int) $id);
        $this->db->where('next_run_at <= NOW()', null, false);
        $this->db->set('last_run_at', 'NOW()', false);
        $this->db->set('next_run_at', $next);
        $this->db->update('report_schedules');
        return $this->db->affected_rows() > 0;
    }

    /**
     * คำนวณ next_run_at จาก schedule_type + schedule_config + timezone
     */
    private function _update_next_run($id)
    {
        $row = $this->get($id);
        if (!$row) return;
        $this->db->where('id', (int) $id);
        $this->db->update('report_schedules', ['next_run_at' => $this->_compute_next_run($row)]);
    }

    /**
     * Next run strictly after $now, in UTC, with an explicit offset.
     * Weekly uses ISO weekdays (1 = Monday ... 7 = Sunday), matching the schedule form.
     */
    private function _compute_next_run($row, DateTime $now = null)
    {
        $tz = new DateTimeZone($row->timezone ?: 'Asia/Bangkok');
        $now = $now ? (clone $now) : new DateTime('now', $tz);
        $now->setTimezone($tz);

        $config = is_string($row->schedule_config) ? json_decode($row->schedule_config, true) : (array) $row->schedule_config;
        if (!is_array($config)) {
            $config = [];
        }
        $time = isset($config['time']) ? $config['time'] : '08:00';
        if (!preg_match('/^\d{1,2}:\d{2}/', $time)) {
            $time = '08:00';
        }
        $hour = (int) substr($time, 0, 2);
        $minute = (int) substr($time, 3, 2);
        $type = $row->schedule_type ?: 'daily';
        $day = min(28, max(1, (int) ($config['day'] ?? 1)));

        if ($type === 'weekly') {
            $weekday = (int) ($config['weekday'] ?? 1);
            if ($weekday < 1 || $weekday > 7) {
                $weekday = 1;
            }
            $next = (clone $now)->setTime($hour, $minute, 0);
            $diff = ($weekday - (int) $next->format('N') + 7) % 7;
            if ($diff === 0 && $next <= $now) {
                $diff = 7;
            }
            if ($diff > 0) {
                $next->modify('+' . $diff . ' days');
            }
        } elseif ($type === 'monthly') {
            $next = (clone $now)->setTime($hour, $minute, 0);
            $next->setDate((int) $now->format('Y'), (int) $now->format('m'), $day);
            if ($next <= $now) {
                $next->modify('first day of next month');
                $next->setTime($hour, $minute, 0);
                $next->setDate((int) $next->format('Y'), (int) $next->format('m'), $day);
            }
        } else {
            $next = (clone $now)->setTime($hour, $minute, 0);
            if ($next <= $now) {
                $next->modify('+1 day');
            }
        }

        $guard = 0;
        while ($next <= $now && $guard < 24) {
            if ($type === 'weekly') {
                $next->modify('+7 days');
            } elseif ($type === 'monthly') {
                $next->modify('first day of next month');
                $next->setTime($hour, $minute, 0);
                $next->setDate((int) $next->format('Y'), (int) $next->format('m'), $day);
            } else {
                $next->modify('+1 day');
            }
            $guard++;
        }

        $next->setTimezone(new DateTimeZone('UTC'));
        return $next->format('Y-m-d H:i:sP');
    }
}
