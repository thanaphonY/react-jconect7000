<?php defined('BASEPATH') or exit('No direct script access allowed');
// /application/modules/devicetemplate/models/Devicetemplate_model.php

class Devicetemplate_model extends CI_Model
{
    private $table = 'device_templates';

    function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->ia_users_id) ? $this->session->get_userdata()['user_details'][0]->ia_users_id : '1';
    }

    public function getAll()
    {
        $this->db->select('dt.*, dc.name as category_name, dc.icon as category_icon, dc.color as category_color');
        $this->db->from($this->table . ' dt');
        $this->db->join('device_categories dc', 'dc.id = dt.category_id', 'left');
        $this->db->order_by('dt.id', 'DESC');
        return $this->db->get()->result();
    }

    public function getAllTemplate()
    {
        $this->db->select('dt.*, dc.name as category_name, dc.icon as category_icon, dc.color as category_color');
        $this->db->from($this->table . ' dt');
        $this->db->join('device_categories dc', 'dc.id = dt.category_id', 'left');
        $this->db->where('dt.is_active', true);
        $this->db->order_by('dt.id', 'DESC');
        return $this->db->get()->result();
    }

    public function getById($id)
    {
        $this->db->select('dt.*, dc.name as category_name, dc.icon as category_icon, dc.color as category_color');
        $this->db->from($this->table . ' dt');
        $this->db->join('device_categories dc', 'dc.id = dt.category_id', 'left');
        $this->db->where('dt.id', $id);
        return $this->db->get()->row();
    }

    public function getByCategory($category_id)
    {
        $this->db->select('dt.*, dc.name as category_name, dc.icon as category_icon, dc.color as category_color');
        $this->db->from($this->table . ' dt');
        $this->db->join('device_categories dc', 'dc.id = dt.category_id', 'left');
        $this->db->where('dt.category_id', $category_id);
        $this->db->where('dt.is_active', true);
        $this->db->order_by('dt.name', 'ASC');
        return $this->db->get()->result();
    }

    public function getByProtocol($protocol)
    {
        $this->db->select('dt.*, dc.name as category_name, dc.icon as category_icon, dc.color as category_color');
        $this->db->from($this->table . ' dt');
        $this->db->join('device_categories dc', 'dc.id = dt.category_id', 'left');
        $this->db->where('dt.protocol', $protocol);
        $this->db->where('dt.is_active', true);
        $this->db->order_by('dt.name', 'ASC');
        return $this->db->get()->result();
    }

    public function getByCategoryAndProtocol($category_id, $protocol)
    {
        $this->db->select('dt.*, dc.name as category_name, dc.icon as category_icon, dc.color as category_color');
        $this->db->from($this->table . ' dt');
        $this->db->join('device_categories dc', 'dc.id = dt.category_id', 'left');
        $this->db->where('dt.category_id', $category_id);
        $this->db->where('dt.protocol', $protocol);
        $this->db->where('dt.is_active', true);
        $this->db->order_by('dt.name', 'ASC');
        return $this->db->get()->result();
    }

    public function getUniqueCategories()
    {
        $this->db->select('DISTINCT(dc.id), dc.name, dc.icon, dc.color');
        $this->db->from($this->table . ' dt');
        $this->db->join('device_categories dc', 'dc.id = dt.category_id', 'left');
        $this->db->where('dt.is_active', true);
        $this->db->order_by('dc.name', 'ASC');
        return $this->db->get()->result();
    }

    public function getUniqueProtocols()
    {
        $this->db->select('DISTINCT(protocol) as protocol');
        $this->db->from($this->table);
        $this->db->where('is_active', true);
        $this->db->order_by('protocol', 'ASC');
        return $this->db->get()->result();
    }

    public function insert($data)
    {
        return $this->db->insert($this->table, $data);
    }

    public function update($id, $data)
    {
        return $this->db->where('id', $id)->update($this->table, $data);
    }

    public function delete($id)
    {
        return $this->db->delete($this->table, ['id' => $id]);
    }

    public function getTemplateStats()
    {
        $stats = [
            'total_templates' => $this->db->count_all($this->table),
            'active_templates' => $this->db->where('is_active', true)->count_all_results($this->table),
            'templates_by_category' => $this->db->select('dc.name, COUNT(dt.id) as count')
                                               ->from($this->table . ' dt')
                                               ->join('device_categories dc', 'dc.id = dt.category_id', 'left')
                                               ->where('dt.is_active', true)
                                               ->group_by('dc.id, dc.name')
                                               ->order_by('count', 'DESC')
                                               ->get()->result(),
            'templates_by_protocol' => $this->db->select('protocol, COUNT(*) as count')
                                               ->from($this->table)
                                               ->where('is_active', true)
                                               ->group_by('protocol')
                                               ->order_by('count', 'DESC')
                                               ->get()->result()
        ];

        return $stats;
    }
}
