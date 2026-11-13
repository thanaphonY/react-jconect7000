<?php
class Log_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->ia_users_id) ? $this->session->get_userdata()['user_details'][0]->ia_users_id : '1';
    }

    public function addAuditLogs($form_data)
    {
        $this->db->insert('log_audits', $form_data);
        return $this->db->insert_id();
    }

    public function addAudit($etype,$eid,$uid,$type,$status = 1)
    {
        $data = array(
            'entity_type' => $etype,
            'entity_id' => $eid,
            'user_id' => $uid,
            'type' => $type,
            'status' => $status       
        );

        return $this->addAuditLogs($data);
    }
}
