<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Reports_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * รายการ Devices (id, name, location) ที่ active
     */
    public function get_devices_list()
    {
        $q = $this->db->query("
            SELECT id, name, location
            FROM devices
            WHERE is_deleted = false AND is_active = true
            ORDER BY name
        ");
        return $q->result_array();
    }

    /**
     * รายการ Metric (key, label) ของ device นั้น จาก devices.metrics
     * ใช้ devices table เดียว - เร็วกว่า query device_data
     */
    public function get_metrics_by_device($device_id)
    {
        $device_id = (int) $device_id;
        if ($device_id <= 0) {
            return [];
        }
        $q = $this->db->query("
            SELECT metrics FROM devices WHERE id = ? AND metrics IS NOT NULL LIMIT 1
        ", [$device_id]);
        $row = $q->row();
        if (!$row || empty($row->metrics)) {
            return [];
        }
        $raw = $row->metrics;
        if (is_string($raw)) {
            $metrics = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $metrics = $raw;
        } else {
            $metrics = json_decode(json_encode($raw), true);
        }
        if (!is_array($metrics)) {
            return [];
        }
        $result = [];
        foreach ($metrics as $m) {
            $enable = isset($m['enable']) ? $m['enable'] : null;
            if ($enable !== true && $enable !== 'true') {
                continue;
            }
            $key = isset($m['key']) ? trim($m['key']) : '';
            if ($key === '') {
                continue;
            }
            $label = isset($m['label']) ? trim($m['label']) : $key;
            $result[] = ['key' => $key, 'label' => $label];
        }
        usort($result, function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });
        return $result;
    }

    /**
     * คืนค่า map ของ datatype ต่อ (device_id, key) จาก devices.metrics
     * ใช้สำหรับจัดรูปแบบค่า float เป็นทศนิยม 2 ตำแหน่ง
     * @param int[] $device_ids
     * @return array [ device_id => [ 'key' => 'datatype' ], ... ]
     */
    public function get_metrics_datatype_by_devices($device_ids)
    {
        if (empty($device_ids) || !is_array($device_ids)) {
            return [];
        }
        $device_ids = array_map('intval', array_filter($device_ids));
        if (empty($device_ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($device_ids), '?'));
        $q = $this->db->query("
            SELECT id, metrics FROM devices WHERE id IN ({$placeholders}) AND metrics IS NOT NULL
        ", $device_ids);
        $out = [];
        foreach ($q->result() as $row) {
            $did = (int) $row->id;
            $raw = $row->metrics;
            if (is_string($raw)) {
                $metrics = json_decode($raw, true);
            } elseif (is_array($raw)) {
                $metrics = $raw;
            } else {
                $metrics = json_decode(json_encode($raw), true);
            }
            if (!is_array($metrics)) {
                continue;
            }
            $out[$did] = [];
            foreach ($metrics as $m) {
                $key = isset($m['key']) ? trim($m['key']) : '';
                if ($key === '') {
                    continue;
                }
                $datatype = isset($m['datatype']) ? trim($m['datatype']) : '';
                $out[$did][$key] = $datatype;
            }
        }
        return $out;
    }

    /**
     * รายการ Metric keys ที่มีใน device_data (ไม่ซ้ำ)
     */
    public function get_metrics_list()
    {
        $q = $this->db->query("
            SELECT DISTINCT \"key\" AS metric_key
            FROM device_data
            WHERE \"key\" IS NOT NULL AND \"key\" != ''
            ORDER BY \"key\"
        ");
        $rows = $q->result_array();
        return array_column($rows, 'metric_key');
    }

    /**
     * รายการ Room (location) ที่ไม่ซ้ำ
     */
    public function get_rooms_list()
    {
        $q = $this->db->query("
            SELECT DISTINCT location AS room
            FROM devices
            WHERE is_deleted = false AND is_active = true
              AND location IS NOT NULL AND location != ''
            ORDER BY location
        ");
        $rows = $q->result_array();
        return array_column($rows, 'room');
    }

    /**
     * รายการ Action Type ที่ไม่ซ้ำจาก event_logs
     */
    public function get_action_types_list()
    {
        $q = $this->db->query("
            SELECT DISTINCT action_type
            FROM event_logs
            WHERE action_type IS NOT NULL AND action_type != ''
            ORDER BY action_type
        ");
        return array_column($q->result_array(), 'action_type');
    }

    /**
     * รายการ Entity Type ที่ไม่ซ้ำจาก event_logs
     */
    public function get_entity_types_list()
    {
        $q = $this->db->query("
            SELECT DISTINCT entity_type
            FROM event_logs
            WHERE entity_type IS NOT NULL AND entity_type != ''
            ORDER BY entity_type
        ");
        return array_column($q->result_array(), 'entity_type');
    }

    /**
     * รายการ Template ที่สร้างจาก Builder (template_type = custom_builder)
     */
    public function get_templates_list()
    {
        $this->db->select('id, name, description, template_type, config, created_at, updated_at');
        $this->db->from('report_templates');
        $this->db->where('template_type', 'custom_builder');
        $this->db->order_by('updated_at', 'DESC');
        $q = $this->db->get();
        return $q->result();
    }

    /**
     * ดึง Template ตาม id
     */
    public function get_template($id)
    {
        $q = $this->db->get_where('report_templates', ['id' => (int) $id], 1);
        return $q->row();
    }

    /**
     * สร้าง Template ใหม่
     */
    public function create_template($data)
    {
        $insert = [
            'name' => $data['name'],
            'description' => isset($data['description']) ? $data['description'] : '',
            'template_type' => isset($data['template_type']) ? $data['template_type'] : 'custom_builder',
            'html_content' => isset($data['html_content']) ? $data['html_content'] : '',
            'is_default' => false,
        ];
        if (isset($data['created_by'])) {
            $insert['created_by'] = (int) $data['created_by'];
        }
        if (isset($data['config'])) {
            $insert['config'] = is_string($data['config']) ? $data['config'] : json_encode($data['config']);
        }
        $this->db->insert('report_templates', $insert);
        return $this->db->insert_id();
    }

    /**
     * อัปเดต Template
     */
    public function update_template($id, $data)
    {
        $update = [
            'name' => $data['name'],
            'description' => isset($data['description']) ? $data['description'] : '',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (isset($data['template_type'])) {
            $update['template_type'] = $data['template_type'];
        }
        if (isset($data['config'])) {
            $update['config'] = is_string($data['config']) ? $data['config'] : json_encode($data['config']);
        }
        if (array_key_exists('html_content', $data)) {
            $update['html_content'] = $data['html_content'];
        }
        $this->db->where('id', (int) $id);
        $this->db->update('report_templates', $update);
        return $this->db->affected_rows() >= 0;
    }

    /**
     * ลบ Template
     */
    public function delete_template($id)
    {
        $this->db->where('id', (int) $id);
        $this->db->where('template_type', 'custom_builder');
        $this->db->delete('report_templates');
        return $this->db->affected_rows() > 0;
    }

    /**
     * บันทึก metadata ไฟล์ export
     */
    public function insert_export_file($data)
    {
        $insert = [
            'template_id' => (int) $data['template_id'],
            'schedule_id' => isset($data['schedule_id']) ? (int) $data['schedule_id'] : null,
            'file_path' => $data['file_path'],
            'file_name' => $data['file_name'],
            'file_size' => isset($data['file_size']) ? (int) $data['file_size'] : 0,
            'export_format' => in_array($data['export_format'] ?? '', ['pdf', 'excel', 'csv']) ? $data['export_format'] : 'pdf',
            'exported_at' => date('Y-m-d H:i:sP'),
            'exported_by' => isset($data['exported_by']) ? (int) $data['exported_by'] : null,
        ];
        $this->db->insert('report_export_files', $insert);
        return $this->db->insert_id();
    }

    /**
     * รายการไฟล์ export (filter ได้)
     */
    public function get_export_files($template_id = null, $format = null, $date_from = null, $date_to = null, $limit = 100)
    {
        $this->db->select('f.id, f.template_id, f.file_path, f.file_name, f.file_size, f.export_format, f.exported_at, f.schedule_id, t.name AS template_name');
        $this->db->from('report_export_files f');
        $this->db->join('report_templates t', 't.id = f.template_id', 'left');
        $this->db->order_by('f.exported_at', 'DESC');
        if ($template_id) $this->db->where('f.template_id', (int) $template_id);
        if ($format) $this->db->where('f.export_format', $format);
        if ($date_from) $this->db->where('f.exported_at >=', $date_from . ' 00:00:00');
        if ($date_to) $this->db->where('f.exported_at <=', $date_to . ' 23:59:59');
        $this->db->limit($limit);
        return $this->db->get()->result_array();
    }

    /**
     * ดึงไฟล์ตาม id
     */
    public function get_export_file($id)
    {
        return $this->db->get_where('report_export_files', ['id' => (int) $id], 1)->row_array();
    }

    /**
     * ลบรายการไฟล์ export (ใช้ร่วมกับลบไฟล์บน disk ใน controller)
     */
    public function delete_export_file($id)
    {
        $this->db->where('id', (int) $id);
        $this->db->delete('report_export_files');
        return $this->db->affected_rows() > 0;
    }
}
