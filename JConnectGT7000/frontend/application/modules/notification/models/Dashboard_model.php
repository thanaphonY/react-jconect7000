<?php defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard_model extends CI_Model
{
    function __construct()
    {
        $this->table = 'd_entity';
        parent::__construct($this->table);
        $this->load->database();
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->users_id) ? $this->session->get_userdata()['user_details'][0]->users_id : '1';
    }


}
