<?php
class Api_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * This function is api authenticate device
     */
    public function authApi($device = '', $token = '')
    {

        if (empty($device) || empty($token)) {
            return false;
        }

        $this->db->where("serial='$device' AND token='$token'");
        $result = $this->db->get('devices')->result();
        if (!empty($result)) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * This function is get last avtivity formlog
     */
    public function deviceLastLog($device = '')
    {

        if (empty($device)) {
            return false;
        }

        $this->db->order_by("time", "desc");
        $this->db->limit(1);
        $this->db->where("device_id='$device'");
        $result = $this->db->get('logger')->result();
        if (!empty($result)) {
            return $result;
        } else {
            return false;
        }
    }
    public function  deviceLastLogData($device = '')
    {
        if (empty($device)) {
            return false;
        }
        $detail = $this->Api_model->deviceLastLog($device);

        return   $data = json_decode($detail[0]->data);
    }

    public function GetDataById($id = '')
    {
        $this->db->select('*');
        $this->db->from('timer');
        $this->db->where('id', $id);
        $query = $this->db->get();
        return $result = $query->row();
    }
    public function insertRow($table, $data)
    {
        $this->db->insert($table, $data);
        return $this->db->insert_id();
    }
    public function updateRow($table, $col, $colVal, $data)
    {
        $this->db->where($col, $colVal);
        $this->db->update($table, $data);
        return true;
    }
    public function delete($table, $col, $col_val)
    {
        $this->db->where($col, $col_val);
        $this->db->delete($table);
    }
    public function deleteData($id = '')
    {
        $this->db->where('id', $id);
        $this->db->delete('timer');
    }

    public function deleteDevicetimer($id = '')
    {
        $this->db->where_in('device_id', $id);
        $this->db->delete('timer');
    }

    public function  findthistime($time = '')
    {
        if (empty($time)) {
            return false;
        }
        $this->db->order_by("rec", "desc");
        $this->db->where("time='$time'");
        $result = $this->db->get('timer')->result();
        if (!empty($result)) {
            return $result;
        } else {
            return false;
        }
    }

    public function deleteTimer($id = '')
    {
        $this->db->where_in('id', $id);
        $result = $this->db->delete('timer');
        if (!empty($result)) {
            return true;
        } else {
            return false;
        }
    }

    public function upddataTimmerStatus($id = '', $data = '')
    {
        $this->db->where('id', $id);
        $this->db->update('timer', array('lastststus' => $data));
        return true;
    }


    public function getapidata($device)
    {
        if (empty($device)) {
            return false;
        }
        $this->db->order_by("id", "asc");
        $this->db->where("device_id='$device'");
        $result = $this->db->get('api')->result_array();
        if (!empty($result)) {
            return $result;
        } else {
            return false;
        }
    }
    public function getapikey($device, $ch, $stat)
    {
        if (empty($device) || empty($ch)  || empty($stat)) {
            return false;
        }
        $array = array('device_id' => $device, 'ch' => $ch, 'state' => $stat);
        $this->db->order_by("id", "asc");
        $this->db->where($array);
        $result = $this->db->get('api')->result_array();
        if (!empty($result)) {
            return $result;
        } else {
            return false;
        }
    }

    public function apifindkey($key)
    {
        if (empty($key)) {
            return false;
        }
        $this->db->order_by("id", "desc");
        $this->db->limit(1);
        $this->db->where("apikey='$key'");
        $result = $this->db->get('api')->result();
        if (!empty($result)) {
            return $result;
        } else {
            return false;
        }
    }


    public function deleteApi($key = '')
    {
        if (empty($key)) {
            return false;
        }
        $this->db->where_in('apikey', $key);
        $result = $this->db->delete('api');
        if (!empty($result)) {
            return true;
        } else {
            return false;
        }
    }
    public function deleteDeviceapi($id = '')
    {
        $this->db->where_in('device_id', $id);
        $this->db->delete('api');
    }
    public function savelog($form_data)
    {
        $this->db->insert('logger', $form_data);
        if ($this->db->affected_rows() == '1') {
            return TRUE;
        } else {
            return FALSE;
        }
    }


    public function randomString()
    {
        $alpha = "abcdefghijklmnopqrstuvwxyz";
        $alpha_upper = strtoupper($alpha);
        $numeric = "0123456789";
        $special = ".-+=_,!@$#*%<>[]{}";
        $chars = $alpha . $alpha_upper . $numeric;
        $pw = '';
        $chars = str_shuffle($chars);
        $pw = substr($chars, 8, 8);
        return $pw;
    }

    public function randomStringSize($length = 10)
    {
        $alpha = "abcdefghijklmnopqrstuvwxyz";
        $alpha_upper = strtoupper($alpha);
        $numeric = "0123456789";
        $special = ".-+=_,!@$#*%<>[]{}";
        $chars = $alpha . $alpha_upper . $numeric;
        $pw = '';
        $chars = str_shuffle($chars);
        $pw = substr($chars, $length, $length);
        return $pw;
    }




    /*************** */


    public function authApiDevices($token = '')
    {

        if (empty($token)) {
            return false;
        }

        $this->db->where("token='$token'");
        $result = $this->db->get('d_entity')->result();
        if (!empty($result)) {
            return true;
        } else {
            return false;
        }
    }


    public function getLastTeley($token = '')
    {      
        $this->db->order_by("val_date", "asc");
        $this->db->where("val_token='$token'");
        return $this->db->get('d_telemetry')->result();
    }

    public function updatetedata($token = '', $key = '', $data = '')
    {
        if (empty($token) || empty($key)) {
            return false;
        }

        $userTimezone = new DateTimeZone('Asia/Bangkok');
        $gmtTimezone = new DateTimeZone('GMT');
        $myDateTime = new DateTime('now', $gmtTimezone);
        $offset = $userTimezone->getOffset($myDateTime);
        $myInterval = DateInterval::createFromDateString((string)$offset . 'seconds');
        $myDateTime->add($myInterval);
        $datetime = $myDateTime->format('Y-m-d H:i:s');

        $result = $this->checkkeyExists($token, $key);
        if (empty($result)) {

            $val['val_token'] = $token;
            $val['val_key'] = $key;
            $val['val_data'] = $data;

            $valarr['val_date'] = $datetime;

            $this->db->insert('d_telemetry', $val);
        } else {

            $where = array("id" => $result->id);
            $valarr['val_data'] = $data;
            $valarr['val_date'] = $datetime;

            //$this->db->where('id', $result);
            $this->db->update('d_telemetry', $valarr, $where);
        }

        return true;

        /* if (!empty($result)) {
            return true;
        } else {
            return false;
        }*/
    }

    public function checkkeyExists($token, $key)
    {

        $this->db->where("val_token='$token' AND val_key='$key'");
        return  $this->db->get('d_telemetry')->row();
        /* if (!empty($result)) {
            return true;
        } else {
            return false;
        }


        */
    }

   
}
