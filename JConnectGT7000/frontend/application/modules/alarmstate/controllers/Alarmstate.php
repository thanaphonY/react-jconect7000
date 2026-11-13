<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Alarmstate extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Alarmstate_model');
    }

    public function index()
    {
        $data['page_title'] = 'Active Alarms';
        $this->load->view('layouts/header', $data);
        $this->load->view('alarmstate/index', $data);
        $this->load->view('layouts/footer');
    }

    public function get_json()
    {
        $alarms = $this->Alarmstate_model->get_active_alarms();
        echo json_encode([
            'status' => 'success',
            'data' => $alarms
        ]);
    }

    public function acknowledge($device_id, $metric_key)
    {
        $this->Alarmstate_model->acknowledge($device_id, $metric_key, $this->session->userdata('username') ?? 'admin');
        echo json_encode(['status' => 'success']);
    }
}
