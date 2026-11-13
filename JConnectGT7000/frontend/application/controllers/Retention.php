<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Retention extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) {
            $this->requireSetting();
        }
        $this->load->model('Retention_model');
        if (!$this->input->is_cli_request()) {
            $this->lang->load('settings', getSetting('language'));
        }
    }

    public function index()
    {
        $data['policies'] = $this->Retention_model->current();
        $data['preview'] = $this->session->userdata('retention_preview');
        $this->session->unset_userdata('retention_preview');
        $this->load->view('include/header');
        $this->load->view('setting/retention', $data);
        $this->load->view('include/footer');
    }

    public function save()
    {
        $this->Retention_model->save($this->input->post());
        $this->flash('success', 'Retention policy saved.');
        redirect(base_url() . 'retention');
    }

    public function preview()
    {
        $this->session->set_userdata('retention_preview', $this->Retention_model->preview());
        redirect(base_url() . 'retention');
    }

    public function run()
    {
        $deleted = $this->Retention_model->purge();
        $this->session->set_userdata('retention_preview', $deleted);
        $this->flash('success', 'Retention run finished. Counts below are rows deleted.');
        redirect(base_url() . 'retention');
    }

    public function purge()
    {
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }
        $deleted = $this->Retention_model->purge();
        foreach ($deleted as $table => $count) {
            echo $table . ' ' . $count . PHP_EOL;
        }
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
