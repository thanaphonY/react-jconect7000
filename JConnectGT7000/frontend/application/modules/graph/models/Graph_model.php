<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Graph_model extends CI_Model {
    public function get_datatable() {
        return $this->db
                    ->select('timestamp, value')          // ✅ เลือกเฉพาะ timestamp และ value
                    ->where('key', 'Temperature')         // ✅ เงื่อนไข key = 'Temperature'
                    ->get('device_data')
                    ->result();
    }
}
