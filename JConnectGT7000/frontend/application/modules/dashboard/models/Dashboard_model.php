<?php defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard_model extends CI_Model
{
    function __construct()
    {
        $this->table = 'dashboard_layouts';
        parent::__construct($this->table);
        $this->load->database();
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->users_id) ? $this->session->get_userdata()['user_details'][0]->users_id : '1';
    }

    private function log_event($user_id, $action_type, $entity_type, $entity_id, $message, $metadata = null) {
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
}
