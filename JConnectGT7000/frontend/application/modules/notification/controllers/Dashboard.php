<?php defined("BASEPATH") or exit("No direct script access allowed");
class Dashboard extends CI_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->load->model("Dashboard_model");
        $this->load->model("Log_model");
        $this->load->model("Api_model");

        isLogin();
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->ia_users_id) ? $this->session->get_userdata()['user_details'][0]->ia_users_id : 1;
        $this->userName = isset($this->session->get_userdata()['user_details'][0]->name) ? $this->session->get_userdata()['user_details'][0]->name : '';

        $this->load->helper('language');
        $this->lang->load('dashboard', $this->session->userdata('site_lang'));
       // $this->checkEnableStatus();

        $this->auditEntry = 'dashboard';
    }


    //load  view
    public function index()
    {
        isLogin();
        $data[] = '';
        $this->load->view("include/header");
        $this->load->view("index", $data);
        $this->load->view("include/footer");
    }

}
