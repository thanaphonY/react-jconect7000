<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Eventlogs_model extends CI_Model {

    public function get_datatable() {
        $start = $this->input->post('start');
        $length = $this->input->post('length');
        $search = $this->input->post('search')['value'] ?? '';

        $this->db->from('event_logs');

        if ($search) {
            $this->db->group_start();
            $this->db->like('action_type', $search);
            $this->db->or_like('entity_type', $search);
            $this->db->or_like('message', $search);
            $this->db->group_end();
        }

        $totalFiltered = $this->db->count_all_results('', false);
        $this->db->limit($length, $start);
        $query = $this->db->get();

        $total = $this->db->count_all('event_logs');

        return [
            'total' => $total,
            'filtered' => $totalFiltered,
            'data' => $query->result()
        ];
    }

    public function log_event($user_id, $action_type, $entity_type, $entity_id, $message, $metadata = null)
    {
        $now = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
        $log = [
            'user_id' => $user_id,
            'action_type' => $action_type,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'message' => $message,
            'created_at' => $now->format(DateTime::ATOM),
            'metadata' => $metadata ? json_encode($metadata) : null,
        ];
        $this->db->insert('event_logs', $log);
        return $this->db->insert_id();
    }

    public function get_unique_users()
    {
        // ใช้ DISTINCT ON สำหรับ PostgreSQL
        $query = $this->db->query('
            SELECT DISTINCT ON (event_logs.user_id) event_logs.user_id, ia_users.name
            FROM event_logs
            LEFT JOIN ia_users ON ia_users.ia_users_id = event_logs.user_id
            WHERE event_logs.user_id IS NOT NULL
            ORDER BY event_logs.user_id, ia_users.name ASC
        ');
        return $query->result();
    }

    public function get_unique_action_types()
    {
        // ใช้ SELECT DISTINCT สำหรับ PostgreSQL
        $query = $this->db->query('
            SELECT DISTINCT action_type
            FROM event_logs
            WHERE action_type IS NOT NULL
            ORDER BY action_type ASC
        ');
        return $query->result();
    }

    public function get_unique_entity_types()
    {
        // ใช้ SELECT DISTINCT สำหรับ PostgreSQL
        $query = $this->db->query('
            SELECT DISTINCT entity_type
            FROM event_logs
            WHERE entity_type IS NOT NULL
            ORDER BY entity_type ASC
        ');
        return $query->result();
    }

    public function get_log_by_id($id)
    {
        $this->db->select('event_logs.*, ia_users.name AS user_name');
        $this->db->from('event_logs');
        $this->db->join('ia_users', 'ia_users.ia_users_id = event_logs.user_id', 'left');
        $this->db->where('event_logs.id', $id);
        $query = $this->db->get();
        return $query->row();
    }

    public function get_logs_for_export($range = null, $startDate = null, $endDate = null, $user_filter = null, $action_filter = null, $entity_filter = null, $status_filter = null, $search = null)
    {
        $this->db->select('event_logs.*, ia_users.name AS user_name');
        $this->db->from('event_logs');
        $this->db->join('ia_users', 'ia_users.ia_users_id = event_logs.user_id', 'left');

        // Apply date range filter
        if ($range) {
            switch ($range) {
                case 'today':
                    $this->db->where('DATE(event_logs.created_at)', date('Y-m-d'));
                    break;
                case 'yesterday':
                    $this->db->where('DATE(event_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                    break;
                case 'last_7_days':
                    $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                    break;
                case 'last_30_days':
                    $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                    break;
                case 'custom':
                    if ($startDate && $endDate) {
                        $this->db->where('DATE(event_logs.created_at) >=', $startDate);
                        $this->db->where('DATE(event_logs.created_at) <=', $endDate);
                    }
                    break;
            }
        }

        // Apply additional filters
        if ($user_filter && $user_filter !== 'all') {
            $this->db->where('event_logs.user_id', $user_filter);
        }
        if ($action_filter && $action_filter !== 'all') {
            $this->db->where('event_logs.action_type', $action_filter);
        }
        if ($entity_filter && $entity_filter !== 'all') {
            $this->db->where('event_logs.entity_type', $entity_filter);
        }
        if ($status_filter && $status_filter !== 'all') {
            if ($status_filter === 'success') {
                $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"success\"%'");
            } elseif ($status_filter === 'fail') {
                $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"fail\"%'");
            }
        }

        // Apply search filter
        if ($search && !empty(trim($search))) {
            $this->db->group_start();
            $this->db->like('event_logs.action_type', $search);
            $this->db->or_like('event_logs.entity_type', $search);
            $this->db->or_like('event_logs.message', $search);
            $this->db->or_like('ia_users.name', $search);
            $this->db->group_end();
        }

        $this->db->order_by('event_logs.created_at', 'DESC');
        $query = $this->db->get();
        return $query->result();
    }

    public function get_log_statistics($range = 'last_30_days')
    {
        $this->db->select('
            COUNT(*) as total_logs,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(DISTINCT action_type) as unique_actions,
            COUNT(DISTINCT entity_type) as unique_entities
        ');
        $this->db->from('event_logs');

        // Apply date range
        switch ($range) {
            case 'today':
                $this->db->where('DATE(created_at)', date('Y-m-d'));
                break;
            case 'yesterday':
                $this->db->where('DATE(created_at)', date('Y-m-d', strtotime('-1 day')));
                break;
            case 'last_7_days':
                $this->db->where('DATE(created_at) >=', date('Y-m-d', strtotime('-7 days')));
                break;
            case 'last_30_days':
            default:
                $this->db->where('DATE(created_at) >=', date('Y-m-d', strtotime('-30 days')));
                break;
        }

        $query = $this->db->get();
        return $query->row();
    }

    public function get_action_type_summary($range = 'last_30_days')
    {
        $this->db->select('action_type, COUNT(*) as count');
        $this->db->from('event_logs');
        $this->db->group_by('action_type');

        // Apply date range
        switch ($range) {
            case 'today':
                $this->db->where('DATE(created_at)', date('Y-m-d'));
                break;
            case 'yesterday':
                $this->db->where('DATE(created_at)', date('Y-m-d', strtotime('-1 day')));
                break;
            case 'last_7_days':
                $this->db->where('DATE(created_at) >=', date('Y-m-d', strtotime('-7 days')));
                break;
            case 'last_30_days':
            default:
                $this->db->where('DATE(created_at) >=', date('Y-m-d', strtotime('-30 days')));
                break;
        }

        $this->db->order_by('count', 'DESC');
        $query = $this->db->get();
        return $query->result();
    }

    public function get_user_activity_summary($range = 'last_30_days')
    {
        $this->db->select('ia_users.name, COUNT(event_logs.id) as activity_count');
        $this->db->from('event_logs');
        $this->db->join('ia_users', 'ia_users.ia_users_id = event_logs.user_id', 'left');
        $this->db->group_by('event_logs.user_id, ia_users.name');

        // Apply date range
        switch ($range) {
            case 'today':
                $this->db->where('DATE(event_logs.created_at)', date('Y-m-d'));
                break;
            case 'yesterday':
                $this->db->where('DATE(event_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                break;
            case 'last_7_days':
                $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                break;
            case 'last_30_days':
            default:
                $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                break;
        }

        $this->db->order_by('activity_count', 'DESC');
        $this->db->limit(10);
        $query = $this->db->get();
        return $query->result();
    }
}
