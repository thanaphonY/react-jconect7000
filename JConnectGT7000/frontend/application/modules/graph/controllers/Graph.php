<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Graph extends CI_Controller {

    public function index() {
        $this->load->model('Graph_model');
        $data['device_data'] = $this->Graph_model->get_datatable();
        $this->load->view('index', $data); // ส่งข้อมูลไปยัง View
    }
}
