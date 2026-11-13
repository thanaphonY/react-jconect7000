<?php
defined('BASEPATH') or exit('No direct script access allowed ');
class Logger extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Logger_model');
        $this->lang->load('logger', getSetting('language'));
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->ia_users_id) ? $this->session->get_userdata()['user_details'][0]->ia_users_id : '1';
    }
    public function index()
    {
        isLogin();
        //if (CheckPermission("user", "own_read") || CheckPermission("user", "all_read")) {
        $this->load->view('include/header');
        $this->load->view('logger_table');
        $this->load->view('include/footer');
        /* } else {
            $art_msg['msg'] = lang('you_do_not_have_permission_to_access');
            $art_msg['type'] = 'danger';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . 'profile', 'refresh');
        }*/
    }

    /**
     * This function is used to create datatable in users list page
     * @return Void
     */
    public function logTable()
    {
        isLogin();


        $primaryKey = 'id';
        $table = 'logger';


        $columns = array(
            array('db' => 'logger.datetime AS datetime', 'field' => 'datetime', 'dt' => 0), //
            array('db' => 'd_entity.title AS title1', 'field' => 'title1', 'dt' => 1),//
            array('db' => 'n_rule.title AS title', 'field' => 'title', 'dt' => 2),//
            array('db' => 'logger.msg AS msg', 'field' => 'msg', 'dt' => 3),//
            array('db' => 'logger.flag AS flag', 'field' => 'flag', 'dt' => 4),//
            array('db' => 'logger.alarm_tx AS alarm_tx', 'field' => 'alarm_tx', 'dt' => 5),
            array('db' => 'logger.id AS id', 'field' => 'id', 'dt' => 6) //
        );




        $joinQuery = "FROM `" . $table . "` ";
        $joinQuery .= " LEFT JOIN `d_entity` ON `d_entity`.`token` = `logger`.`device_token` LEFT JOIN `n_rule` ON `n_rule`.`id` = `logger`.`rule_id` ";
        //$joinQuery .= " LEFT JOIN `ia_custom_fields_values` AS ia_custom_fields_values_" . $cfkey . "  ON  `ia_users`.`ia_users_id` = `ia_custom_fields_values_" . $cfkey . "`.`rel_crud_id` AND `ia_custom_fields_values_" . $cfkey . "`.`cf_id` =  '" . $cfvalue->custom_fields_id . "' ";

        $where = '';
        $j = 0;
        if (strpos($joinQuery, 'JOIN') > 0) {
            $j = 1;
        }
        $where = SSP::iaFilter($_GET, $columns, $j);
        if (trim($where) == 'WHERE') {
            $where = '';
        }

        $group_by = "";
        $having = "";

        $limit = SSP::limit($_GET, $columns);
        $order = SSP::iaOrder($_GET, $columns, $j);
        if (trim($order) == 'ORDER BY') {
            $order = '';
        }
        $col = SSP::pluck($columns, 'db', $j);

        $query = "SELECT SQL_CALC_FOUND_ROWS " . implode(", ", $col) . " " . $joinQuery . " " . $where . " " . $group_by . " " . $having . " " . $order . " " . $limit . " ";
        $res = $this->db->query($query);
        $res = $res->result_array();


        $recordsTotal = $this->db->select("count('logger') AS c")->get('logger')->row()->c;


        $res = SSP::iaDataOutput($columns, $res, $j);

        $output_arr['draw'] = intval($_GET['draw']);
        $output_arr['recordsTotal'] = intval($recordsTotal);
        $output_arr['recordsFiltered'] = intval($recordsTotal);
        $output_arr['data'] = $res;

        foreach ($output_arr['data'] as $key => $value) {
            /*
            $jsonString = $output_arr['data'][$key][count($output_arr['data'][$key]) - 1];
            $jsonObj = json_decode($jsonString);
            $output_arr['data'][$key][count($output_arr['data'][$key]) - 1] = $jsonObj->ch1.$jsonObj->ch2.$jsonObj->ch3;
            */
            //$output_arr['data'][$key][0] = '<input type="checkbox" id="basic_checkbox_' . $key . '" name="selData" value="' . $output_arr['data'][$key][0] . '"><label for="basic_checkbox_' . $key . '"></label>';
        }
        echo json_encode($output_arr);
    }

    /**
     * This function is used to delete users
     * @return Void
     */
    public function delete($id)
    {
        isLogin();
        $ids = explode('-', $id);
        foreach ($ids as $id) {
            $this->Logger_model->delete($id);
        }
        redirect(base_url() . 'logger', 'refresh');
    }
}
