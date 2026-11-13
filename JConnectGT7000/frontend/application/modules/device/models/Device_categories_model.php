<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Device_categories_model extends CI_Model
{
    private $table = 'device_categories';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function getAll()
    {
        $this->db->order_by('name', 'ASC');
        return $this->db->get($this->table)->result();
    }

    public function getAllActive()
    {
        $this->db->where('is_active', true);
        $this->db->order_by('name', 'ASC');
        return $this->db->get($this->table)->result();
    }

    public function getById($id)
    {
        return $this->db->get_where($this->table, ['id' => $id])->row();
    }

    public function getByName($name)
    {
        return $this->db->get_where($this->table, ['name' => $name])->row();
    }

    public function insert($data)
    {
        return $this->db->insert($this->table, $data);
    }

    public function insertGetId($data)
    {
        if (!$this->db->insert($this->table, $data)) {
            return null;
        }
        return (int) $this->db->insert_id();
    }

    public function update($id, $data)
    {
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    public function delete($id)
    {
        return $this->db->delete($this->table, ['id' => $id]);
    }

    public function checkUsage($category_id)
    {
        // Check devices using this category
        $devices_count = $this->db->where('category_id', $category_id)
                                 ->where('is_deleted', false)
                                 ->count_all_results('devices');

        // Check templates using this category
        $templates_count = $this->db->where('category_id', $category_id)
                                   ->where('is_active', true)
                                   ->count_all_results('device_templates');

        return [
            'devices_count' => $devices_count,
            'templates_count' => $templates_count
        ];
    }

    public function getCategoriesWithCounts()
    {
        $this->db->select('dc.*, 
                          COUNT(DISTINCT d.id) as devices_count,
                          COUNT(DISTINCT dt.id) as templates_count')
                 ->from($this->table . ' dc')
                 ->join('devices d', 'd.category_id = dc.id AND d.is_deleted = false', 'left')
                 ->join('device_templates dt', 'dt.category_id = dc.id AND dt.is_active = true', 'left')
                 ->group_by('dc.id')
                 ->order_by('dc.name', 'ASC');
        
        return $this->db->get()->result();
    }

    public function getCategoryStats()
    {
        $stats = [
            'total_categories' => $this->db->count_all($this->table),
            'active_categories' => $this->db->where('is_active', true)->count_all_results($this->table),
            'categories_with_devices' => $this->db->join('devices d', 'd.category_id = device_categories.id AND d.is_deleted = false')
                                                 ->where('device_categories.is_active', true)
                                                 ->count_all_results($this->table),
            'categories_with_templates' => $this->db->join('device_templates dt', 'dt.category_id = device_categories.id AND dt.is_active = true')
                                                   ->where('device_categories.is_active', true)
                                                   ->count_all_results($this->table)
        ];

        return $stats;
    }
}
