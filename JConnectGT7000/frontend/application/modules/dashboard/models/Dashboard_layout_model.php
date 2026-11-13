<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard_layout_model extends CI_Model
{
    protected $hasSortOrderColumn;
    protected $userHomeTableReady = false;


    function __construct()
    {
        $this->table = 'dashboard_layouts';
        parent::__construct($this->table);
        $this->load->database();
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->users_id) ? $this->session->get_userdata()['user_details'][0]->users_id : '1';
    }
    public function get_all()
    {
        $this->db->select('dl.*, u.name AS created_by_name');
        $this->db->from('dashboard_layouts dl');
        $this->db->join('ia_users u', 'dl.created_by = u.ia_users_id', 'left');
        $this->applyLayoutOrdering('dl');
        return $this->db->get()->result();
    }

     public function get_all_names()
    {
        $this->db->select('dl.*, u.name AS created_by_name');
        $this->db->from('dashboard_layouts dl');
        $this->db->join('ia_users u', 'dl.created_by = u.ia_users_id', 'left');
        $this->applyLayoutOrdering('dl');
        return $this->db->get()->result();
    }

    public function has_sort_order_column()
    {
        if ($this->hasSortOrderColumn === null) {
            $this->hasSortOrderColumn = $this->db->field_exists('sort_order', $this->table);
        }
        return $this->hasSortOrderColumn;
    }

    public function ensure_sort_order_column()
    {
        if ($this->has_sort_order_column()) {
            return true;
        }

        $this->db->query('ALTER TABLE dashboard_layouts ADD COLUMN IF NOT EXISTS sort_order INTEGER NULL');
        $this->hasSortOrderColumn = null;
        return $this->has_sort_order_column();
    }

    protected function applyLayoutOrdering($alias = 'dl')
    {
        if ($this->has_sort_order_column()) {
            $this->db->order_by("CASE WHEN {$alias}.sort_order IS NULL THEN 1 ELSE 0 END", 'ASC', false);
            $this->db->order_by("{$alias}.sort_order", 'ASC');
        }
        $this->db->order_by("{$alias}.name", 'ASC');
        $this->db->order_by("{$alias}.id", 'ASC');
    }


    public function get_by_id($id)
    {
        $this->db->select('dl.*, u.name AS created_by_name');
        $this->db->from('dashboard_layouts dl');
        $this->db->join('ia_users u', 'dl.created_by = u.ia_users_id', 'left');
        $this->db->where('dl.id', $id);
        return $this->db->get()->row();
    }


    public function get_by_device($device_id)
    {
        $this->db->select('dl.*, u.name AS created_by_name');
        $this->db->from('dashboard_layouts dl');
        $this->db->join('ia_users u', 'dl.created_by = u.ia_users_id', 'left');
        $this->db->where('dl.device_id', $device_id);
        return $this->db->get()->result();
    }


    public function get_where_home()
    {
        return $this->db->get_where($this->table, [
            'is_home' => true
        ])->row();
    }

    public function get_first_by_order()
    {
        $this->db->from($this->table . ' dl');
        $this->applyLayoutOrdering('dl');
        $this->db->limit(1);
        return $this->db->get()->row();
    }

    public function get_home_for_user($userId)
    {
        $this->ensure_user_home_table();

        $pref = $this->db->get_where('user_dashboard_homes', [
            'user_id' => (int)$userId
        ])->row();

        if ($pref) {
            $layout = $this->get_by_id($pref->layout_id);
            if ($layout) {
                return $layout;
            }
        }

        return $this->get_first_by_order();
    }

    public function set_user_home($userId, $layoutId)
    {
        $this->ensure_user_home_table();

        $sql = 'INSERT INTO user_dashboard_homes (user_id, layout_id, updated_at)
                VALUES (?, ?, ?)
                ON CONFLICT (user_id) DO UPDATE
                SET layout_id = EXCLUDED.layout_id, updated_at = EXCLUDED.updated_at';

        return $this->db->query($sql, [
            (int)$userId,
            (int)$layoutId,
            date('Y-m-d H:i:s')
        ]);
    }

    public function clear_user_home_for_layout($layoutId)
    {
        $this->ensure_user_home_table();
        return $this->db->delete('user_dashboard_homes', [
            'layout_id' => (int)$layoutId
        ]);
    }

    protected function ensure_user_home_table()
    {
        if ($this->userHomeTableReady) {
            return;
        }

        $this->db->query('CREATE TABLE IF NOT EXISTS user_dashboard_homes (
            user_id INTEGER PRIMARY KEY,
            layout_id INTEGER NOT NULL,
            updated_at TIMESTAMP NULL
        )');
        $this->userHomeTableReady = true;
    }



    public function get_home_layout($device_id)
    {
        return $this->db->get_where($this->table, [
            'device_id' => $device_id,
            'is_home' => true
        ])->row();
    }

    public function insert_layout($data)
    {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function update_layout($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    public function delete_layout($id)
    {
        return $this->db->delete($this->table, ['id' => $id]);
    }

    public function unset_all_home()
    {
        return $this->db->update($this->table, ['is_home' => false]);
    }

    public function update_sort_orders($orderedIds)
    {
        if (!$this->ensure_sort_order_column() || !is_array($orderedIds) || empty($orderedIds)) {
            return false;
        }

        $order = 10;
        $updated = 0;
        foreach ($orderedIds as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $this->db->where('id', $id)->update($this->table, ['sort_order' => $order]);
            $order += 10;
            $updated++;
        }

        return $updated > 0;
    }


    public function log_event($user_id, $action_type, $entity_type, $entity_id, $message, $metadata = null)
    {
        $log = [
            'user_id' => $user_id,
            'action_type' => $action_type,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
            'metadata' => $metadata ? json_encode($metadata) : null,
        ];
        $this->db->insert('event_logs', $log);
    }

    public function get_items($layout_id)
    {
        $this->db->select('i.*, w.name as widget_name, w.widget_type');
        $this->db->from('dashboard_layout_items i');
        $this->db->join('widgets w', 'i.widget_id = w.id', 'left'); // ✅ left join ดีกว่า
        $this->db->where('i.layout_id', $layout_id);
        $this->db->order_by('i.y ASC, i.x ASC');
        $query = $this->db->get();
        return $query->result();
    }
}
