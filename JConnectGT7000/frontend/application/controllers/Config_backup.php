<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Config_backup extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) {
            $this->requireSetting();
            $this->lang->load('settings', getSetting('language'));
        }
        $this->load->model('Config_backup_model');
    }

    public function index()
    {
        $this->load->view('include/header');
        $this->load->view('setting/config_backup');
        $this->load->view('include/footer');
    }

    public function export()
    {
        $includeUsers = $this->input->post('include_users') === '1';
        $payload = $this->Config_backup_model->exportPayload($includeUsers);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $name = 'jconnect-config-' . date('Ymd-His') . '.json';
        $this->output
            ->set_content_type('application/json')
            ->set_header('Content-Disposition: attachment; filename="' . $name . '"')
            ->set_output($json);
    }

    public function restore()
    {
        if (empty($_FILES['backup']['tmp_name']) || !is_uploaded_file($_FILES['backup']['tmp_name'])) {
            $this->flash('danger', 'Choose a backup file.');
            redirect(base_url() . 'config_backup');
            return;
        }
        $raw = file_get_contents($_FILES['backup']['tmp_name']);
        $payload = json_decode($raw, true);
        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $this->Config_backup_model->restorePayload($payload);
            $this->flash('success', 'Config restored. Telemetry and logs were left in place.');
        } catch (Exception $e) {
            $this->flash('danger', 'Restore was cancelled. Nothing was changed.');
        }
        $this->db->db_debug = $debug;
        redirect(base_url() . 'config_backup');
    }

    private function requireSetting()
    {
        isLogin();
        site_require_global();
        $type = isset($this->session->get_userdata()['user_details'][0]->user_type)
            ? $this->session->get_userdata()['user_details'][0]->user_type : '';
        if ($type === 'admin' || $type === 'Admin') {
            return;
        }
        if (CheckPermission('setting', 'all_read') || CheckPermission('setting', 'own_read')) {
            return;
        }
        $this->flash('danger', 'You do not have permission to open system settings.');
        redirect(base_url() . 'home');
        exit;
    }

    private function flash($type, $message)
    {
        $this->session->set_userdata('alert_msg', array('type' => $type, 'msg' => $message));
    }
}
