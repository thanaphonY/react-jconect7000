<?php defined('BASEPATH') or exit('No direct script access allowed');

class Calculation_model extends CI_Model
{
    public function get_devices()
    {
        return $this->db
            ->select('id, name, metrics, is_active')
            ->where('is_deleted', false)
            ->order_by('name', 'asc')
            ->get('devices')
            ->result();
    }

    public function get_device($id)
    {
        return $this->db
            ->select('id, name, metrics, is_active')
            ->where('id', (int) $id)
            ->where('is_deleted', false)
            ->get('devices')
            ->row();
    }

    public function save_metrics($deviceId, array $metrics)
    {
        return $this->db
            ->where('id', (int) $deviceId)
            ->where('is_deleted', false)
            ->update('devices', [
                'metrics' => json_encode(array_values($metrics), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }
}
