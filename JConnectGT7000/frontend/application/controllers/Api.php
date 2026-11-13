<?php
defined('BASEPATH') or exit('No direct script access allowed ');
class Api extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Api_model');
        $this->load->model('User_model');
        $this->load->model('device/Metrics_model');
        $this->load->model('device/Device_model');
        // $this->load->library("mqtt");
    }




    /**
     * This function API ON OFF
     * https://www.adeviot.com/api?token="TOKEN"&device="SERIAL"&act="ACTIVITY"&ch="CHANEL"&stat="STATE"
     * @return JSON
     */
    public function index()
    {

        $data["status"] = "false";

        echo json_encode($data, JSON_PRETTY_PRINT);
    }




    public function getlasttele($token = 0)
    {
        $data["status"] = "false";
        if (!$token) {
            $data["messages"] = "token false";
        } else {
            if ($this->Api_model->authApiDevices($token)) {
                $data["status"] = "ok";
                $data["data"] = $this->Api_model->getLastTeley($token);
            } else {
                $data["messages"] = "auth false";
            }
        }
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    public function devices()
    {
        $devices = $this->Device_model->get_all();

        $data = array_map(function ($d) {
            return [
                'id' => $d->id,
                'name' => $d->name
            ];
        }, $devices);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'success',
                'devices' => $data
            ]));
    }

    public function metrics($device_id = null)
    {

        if (!$device_id) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Missing device_id'
                ]));
        }

        // สมมติว่าคุณมี model ดึง metric ของอุปกรณ์นี้
        $metrics = $this->Metrics_model->get_metrics_by_device($device_id);

        if ($metrics) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'success',
                    'metrics' => $metrics
                ]));
        } else {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'No metrics found'
                ]));
        }
    }




    public function timeNow()
    {
        $datetime = new DateTime();
        $date = $datetime->format("Y-m-d");
        $time =  $datetime->format("H:i");
        $dayofweek = date('w', strtotime($date));

        $data["status"] = "System time";
        $data["date"] = $date;
        $data["time"] =  $time;
        $data["DayOfWeek"] =  $dayofweek;

        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    public function timeNow2()
    {
        $datetime = new DateTime();
        $date = $datetime->format("Y-m-d");
        $time =  $datetime->format("H:i");
        $dayofweek = date('w', strtotime($date));

        $data["status"] = "System time";
        $data["date"] = $date;
        $data["time"] =  $time;
        $data["DayOfWeek"] =  $dayofweek;

        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }











    public function getdevice()
    {
        $data = $this->User_model->getDeviceAll();

        $userTimezone = new DateTimeZone('Asia/Bangkok');
        $gmtTimezone = new DateTimeZone('GMT');


        $ta = array();
        foreach ($data as $value) {
            $res["serial"] = $value->serial;
            $res["data"] = $value->data;
            $res["flag"] = $value->flagRun;
            $myDateTime = new DateTime($value->time, $gmtTimezone);
            $offset = $userTimezone->getOffset($myDateTime);
            $myInterval = DateInterval::createFromDateString((string)$offset . 'seconds');
            $myDateTime->add($myInterval);
            $result = $myDateTime->format('Y-m-d H:i:s');
            $res["time"] = $result;
            array_push($ta, $res);
        }

        header('Content-Type: application/json');
        echo json_encode($ta, JSON_PRETTY_PRINT);
    }




    /**
     * This function ...
     * @return String
     */
    public function change($apikey)
    {
        $res = $this->Api_model->apifindkey($apikey);
        if ($res) {
            if ($this->Api_model->authApi($res[0]->device_id, $res[0]->token)) {

                $mqtt = $this->mqttpub("change", $res[0]->device_id, $res[0]->ch, $res[0]->state);
                if ($mqtt) {
                    $data["status"] = "Success";
                } else {
                    $data["status"] = "Failed to send";
                }
            } else {
                $data["status"] = "token false";
            }
        } else {
            $data["status"] = "false";
        }

        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * This function ...
     * @return 
     */
    public function apireturn($device = '', $ch = '', $stat = '')
    {
        $server = "localhost";
        $port = 1883;
        $username = "";
        $password = "";

        if (empty($device) || empty($ch)) {
            $data["status"] = "false";
        } else {
            $client_id = $this->randomString(5);

            $topic = "/api/sub/" . $device . "/ch" . $ch;
            if ($stat == '1') {
                $message = "True";
            } else {
                $message = "False";
            }
            $mqttpub = $this->mqtt->pub($server, $port, $username, $password, $client_id, $topic, $message);
            if ($mqttpub) {
                $data["status"] = "success";
                $data["topic"] = $topic;
                $data["massage"] = $message;
            } else {
                $data["status"] = "sent false";
            }
        }

        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    /**
     * This function is used to Generate a random string
     * @return String
     */
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

    /**
     * This function is used to runtimer on REQUEST
     * @return String
     */
    public function timerrun()

    {
        $datetime = new DateTime();
        $date = $datetime->format("Y-m-d");
        $time =  $datetime->format("H:i:00");
        $dayofweek = date('w', strtotime($date));

        $data["status"] = "timer";
        $data["date"] = $date;
        $data["time"] =  $time;
        $data["DayOfWeek"] =  $dayofweek;
        $activity = 0;
        $type_one = 0;
        $type_ever = 0;
        $type_daily = 0;
        $mqttpub = 0;
        $deletes = 0;


        $res = $this->Api_model->findthistime($time);
        //$data["data"] =  $res;
        if (!$res) {
            $data["activity"] = "null";
        } else {
            //$data["testd"] = $res[0]->device_id;
            foreach ($res as $value) {
                if ($value->type == '1' && $value->time == $time) {
                    $activity++;
                    $type_ever++;
                    $mqtt = $this->mqttpub("change", $value->device_id, $value->ch, $value->activity);
                    if ($mqtt) {
                        $mqttpub++;
                        $this->Api_model->upddataTimmerStatus($value->id, true);
                    } else {
                        $this->Api_model->upddataTimmerStatus($value->id, false);
                    }
                } elseif ($value->type == '2' && $value->time == $time) {
                    $dayArr = explode(",", $value->day);
                    if (in_array($dayofweek, $dayArr)) {
                        $activity++;
                        $type_daily++;
                        $mqtt = $this->mqttpub("change", $value->device_id, $value->ch, $value->activity);
                        if ($mqtt) {
                            $mqttpub++;
                            $this->Api_model->upddataTimmerStatus($value->id, true);
                        } else {
                            $this->Api_model->upddataTimmerStatus($value->id, false);
                        }
                    }
                } elseif ($value->type == '0' && $value->time == $time && $value->date == $date) {
                    $activity++;
                    $type_one++;
                    $mqtt = $this->mqttpub("change", $value->device_id, $value->ch, $value->activity);

                    if ($mqtt) {
                        $mqttpub++;
                        $delete = $this->Api_model->deleteTimer($value->id);
                        if ($delete) {
                            $deletes++;
                        } else {
                            $this->Api_model->upddataTimmerStatus($value->id, false);
                        }
                    } else {
                        $this->Api_model->upddataTimmerStatus($value->id, false);
                    }
                }
            }

            $data["activity"] = $activity;
            $data["type0"] = $type_one;
            $data["type1"] = $type_ever;
            $data["type2"] = $type_daily;
            $data["sent"] = $mqttpub;
            $data["delete"] = $deletes;
        }

        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * This function is MQTT Publish
     * @return String
     */
    public function mqttpub($act = '', $device = '', $ch = '', $stat = '')
    {
        $server = "localhost";
        $port = 1883;
        $username = "";
        $password = "";

        if (empty($act) || empty($device) || empty($ch)) {
            return false;
        }
        $client_id = $this->randomString();

        $topic = "/" . $device . "/client" . "/";
        $message = $act . ",null,ch" . $ch . ":" . $stat;
        $mqttpub = $this->mqtt->pub($server, $port, $username, $password, $client_id, $topic, $message);
        if ($mqttpub) {
            $data["topic"] = $topic;
            $data["msg"] = $message;
            return $data;
        } else {
            return false;
        }
    }
    /**
     * This function is qr code generator
     * @return png
     */
    public function qr()
    {
        if (isset($_GET['qr'])) {
            $value = $_GET['qr'];
        } else {
            $value = 'https://app.dcimthai.com';
        }

        $this->load->library('ciqrcode');
        header("Content-Type: image/png");
        $params['size'] = 10;
        $params['data'] = $value;
        $this->ciqrcode->generate($params);
    }


    /**
     * 
     * This Group Function Duplication 
     * module->energy->Energy.php
     * module->energy->Energy_model.php
     * 
     * Create For Node-red Using with api/calUpdateMeter
     * 22/11/66 - Kunagon Create
     **/
    public function calUpdateMeter()
    {
        $dbcall = $this->getAllMeter();
        $res = array();
        for ($i = 0; $i < count($dbcall); $i++) {

            $meterMaxCount = isset($meterData->maxCount) ? intval($meterData->maxCount) : 1000000000;
            $meterSource = $dbcall[$i]->labels;
            $meterData = json_decode($dbcall[$i]->data);
            $data['id'] = $dbcall[$i]->id;
            $data['title'] = $dbcall[$i]->title;
            $data['description'] = $dbcall[$i]->description;
            $data['source'] = $meterSource;
            $data['token'] = $dbcall[$i]->token;
            $data['maxCount'] = $meterMaxCount;
            $data['save'] = $this->energyStagingCal($meterSource, $meterMaxCount);
            array_push($res, $data);
        }

        header('Content-Type: application/json');
        echo json_encode($res, JSON_PRETTY_PRINT);
    }

    public function getAllMeter()
    {
        $where = array('type' => 'meter', 'deleted' => '0');
        $this->db->select('*');
        $this->db->where($where);
        $this->db->from('d_entity');
        $result  = $this->db->get()->result();
        return $result;
    }

    private function energyStagingCal($meterSource = "", $meterMaxCount = 1000000000, $limit = 1000, $skip = 0)
    {
        if (empty($meterSource)) {
            return false;
        }

        $resDb = $this->getDataStagingGroupbyMax1d($meterSource, $limit);
        if (count($resDb) < 3) {
            return false;
        }
        $resultDb = array_reverse($resDb);
        $cal = array();
        if (count($resultDb) > 2) {
            for ($x = 1; $x < count($resultDb); $x++) {
                $prevCounter = floatval($resultDb[$x - 1]->max_value);
                $nowCounter = floatval($resultDb[$x]->max_value);

                if ($prevCounter > $nowCounter) {
                    $totalUse = ((float)$meterMaxCount - $prevCounter) + $nowCounter;
                } else {
                    $totalUse = round($nowCounter - $prevCounter, 5);
                }

                if (isset($cal[$x - 2]['nowUse'])) {
                    $prevUse = $cal[$x - 2]['nowUse'];
                    $trend = round((($totalUse - $prevUse) / $prevUse) * 100, 2);
                } else {
                    $prevUse = 0;
                    $trend = 0;
                }
                $update_date = $resultDb[$x]->datetime . ":00:00";
                $timestamp = $resultDb[$x]->timestamp;
                $rec['meterToken'] = $resultDb[$x]->device_token;
                $rec['prevUse'] = $prevUse;
                $rec['nowUse'] = $totalUse;
                $rec['prevCounter'] = $prevCounter;
                $rec['nowCounter'] = $nowCounter;
                $rec['totalUse'] =  $totalUse;
                $rec['update_date'] = $update_date;
                $rec['timestemp'] = $timestamp;
                $rec['trend'] = $trend;
                $activity = $this->addUpdateEnergyuse($resultDb[$x]->device_token, $update_date, $rec);
                array_push($cal, $rec);
            }
        }
        return true;
    }

    public function getDataStagingGroupbyMax1h($meterToken = "", $limit = 50, $skip = 0)
    {

        $where = array('device_token' => $meterToken, 'device_key' => 'Energy');
        $this->db->select("device_token, DATE_FORMAT(CONVERT_TZ(timestemp, '+00:00', '+08:00'), '%Y-%m-%d %H') AS datetime");
        $this->db->select('MAX(device_value) AS max_value ,MAX(id) AS maxId,MAX(timestemp) AS timestamp');
        $this->db->where($where);
        $this->db->from('device_data');
        $this->db->group_by('datetime'); // Group by date, hour, and minute
        $this->db->order_by('datetime', 'desc'); // Optional: Order by the datetime column
        $this->db->limit($limit, $skip);

        $result  = $this->db->get()->result();
        return $result;
    }

    public function upload_map_image()
    {
        // Add authentication check
        if (!isset($_SESSION['user_details'])) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Authentication required']));
        }

        if (!isset($_FILES['image'])) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'No file uploaded']));
        }

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed']));
        }

        // Validate file size (max 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($_FILES['image']['size'] > $max_size) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'File size too large. Maximum size is 10MB']));
        }

        $target_dir = FCPATH . 'uploads/maps/';
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                return $this->output->set_content_type('application/json')
                    ->set_output(json_encode(['status' => 'error', 'message' => 'Failed to create directory']));
            }
        }

        // Check if directory is writable
        if (!is_writable($target_dir)) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Directory is not writable']));
        }

        $filename = uniqid('map_') . '.' . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // Set proper file permissions
            chmod($target_file, 0644);
            
            $relative_path = base_url('uploads/maps/' . $filename);
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'success', 'filename' => $filename, 'path' => $relative_path]));
        } else {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Failed to save file']));
        }
    }

    public function upload_svg_widget()
    {
        if (!isset($_SESSION['user_details'])) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Authentication required']));
        }

        if (!isset($_FILES['svg'])) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'No file uploaded']));
        }

        $file = $_FILES['svg'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowed_types = ['image/svg+xml', 'text/xml', 'application/xml'];
        if ($ext !== 'svg' || (!empty($file['type']) && !in_array($file['type'], $allowed_types))) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Invalid file type. Only SVG is allowed']));
        }

        $max_size = 2 * 1024 * 1024; // 2MB
        if (($file['size'] ?? 0) > $max_size) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'File size too large. Maximum size is 2MB']));
        }

        $target_dir = FCPATH . 'uploads/mimics/';
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                return $this->output->set_content_type('application/json')
                    ->set_output(json_encode(['status' => 'error', 'message' => 'Failed to create directory']));
            }
        }

        if (!is_writable($target_dir)) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Directory is not writable']));
        }

        $base = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
        $base = trim((string)$base, '-_');
        if ($base === '') $base = 'svg-widget';
        $filename = $base . '-' . uniqid() . '.svg';
        $target_file = $target_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Failed to save file']));
        }

        $content = @file_get_contents($target_file);
        if ($content === false || stripos($content, '<svg') === false) {
            @unlink($target_file);
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Uploaded file is not a valid SVG']));
        }

        chmod($target_file, 0644);
        $relative_path = base_url('uploads/mimics/' . $filename);
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'success', 'filename' => $filename, 'path' => $relative_path]));
    }

    public function getDataStagingGroupbyMax1d($meterToken = "", $limit = 50, $skip = 0)
    {

        $where = array('device_token' => $meterToken, 'device_key' => 'Energy');
        $this->db->select("device_token, DATE_FORMAT(CONVERT_TZ(timestemp, '+00:00', '+08:00'), '%Y-%m-%d') AS datetime");
        $this->db->select('MAX(device_value) AS max_value ,MAX(id) AS maxId,MAX(timestemp) AS timestamp');
        $this->db->where($where);
        $this->db->from('device_data');
        $this->db->group_by('datetime'); // Group by date, hour, and minute
        $this->db->order_by('datetime', 'desc'); // Optional: Order by the datetime column
        $this->db->limit($limit, $skip);

        $result  = $this->db->get()->result();
        return $result;
    }

    function addUpdateEnergyuse($meterToken, $update_date, $data)
    {
        $where = array('meterToken' => $meterToken, 'update_date' => $update_date);
        $this->db->select('*');
        $this->db->where($where);
        $this->db->from('d_energyuse');
        $query = $this->db->get();
        $count = $query->num_rows();
        $result = $query->result();
        if ($count == 0) {
            $this->db->insert('d_energyuse', $data);
            $res = "insert";
        } elseif ($count > 0) {
            if ($result[0]->timestemp != $data['timestemp']) {
                $whereUpdate = array('meterToken' => $meterToken, 'update_date' => $update_date);
                $this->db->update('d_energyuse', $data, $whereUpdate);
                //$this->updateWhere($data, $whereUpdate);
                $res = "update";
            } else {
                $res = "no update";
            }
        }
        return $res;
    }

    public function alarm($sec = null)
    {
        if ($sec == "active") {
            $this->db->select('a.*, d.name as device_name');
            $this->db->from('alarm_state a');
            $this->db->join('devices d', 'a.device_id = d.id', 'left');
            $this->db->where('a.active', true);
            $this->db->order_by('a.last_triggered', 'desc');
            $query = $this->db->get();

            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'success',
                    'alarms' => $query->result()
                ]));
        } elseif ($sec == "acknowledge") {
            $input = json_decode($this->input->raw_input_stream, true);
            $trigger_id = $input['trigger_id'] ?? null;
            $user = $this->session->userdata('username') ?? 'unknown';
            $note = $input['note'] ?? '';

            if (!$trigger_id) {
                return $this->output->set_status_header(400)->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Missing trigger_id'
                ]));
            }

            $this->db->where('trigger_id', $trigger_id)->update('alarm_state', [
                'acknowledged' => true,
                'acknowledged_by' => $user,
                'acknowledged_at' => date('Y-m-d H:i:s'),
                'acknowledge_note' => $note
            ]);

            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'success']));
        }
    }
}
