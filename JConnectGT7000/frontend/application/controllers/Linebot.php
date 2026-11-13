<?php
defined('BASEPATH') or exit('No direct script access allowed ');
class Linebot extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Logger_model');
        $this->lang->load('logger', getSetting('language'));
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->ia_users_id) ? $this->session->get_userdata()['user_details'][0]->ia_users_id : '1';
    }

    public function verify (){
        echo 'verify ok';
    }
}