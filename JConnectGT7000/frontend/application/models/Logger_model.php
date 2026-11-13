<?php
class Logger_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * This function is used to delete user
     * @param: $id - id of user table
     */
    public function delete($id = '')
    {
        $this->db->where('id', $id);
        $this->db->delete('logger');
    }


    function get_all_active_alarm()
    {

        //$joinQuery .= " LEFT JOIN `d_entity` ON `d_entity`.`token` = `logger`.`device_token` LEFT JOIN `n_rule` ON `n_rule`.`id` = `logger`.`rule_id` ";

        $this->db->select('*');
        $this->db->from('logger a');
        $this->db->join('n_rule b', 'b.alarmTicket = a.alarm_tx', 'left');
        $this->db->join('d_entity c', 'c.token = a.device_token', 'left');

        $this->db->order_by('a.datetime', 'desc');

        $array = array('b.alarm_statelast >' => '0');

        $this->db->where($array);

        $query = $this->db->get();
        return $query->result();
    }
}
