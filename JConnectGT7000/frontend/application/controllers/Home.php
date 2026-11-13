<?php
defined('BASEPATH') or exit('No direct script access allowed ');
class Home extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->model('Api_model');
        $this->load->model('Logger_model');
        $this->load->model('dashboard/Dashboard_layout_model');

        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->ia_users_id) ? $this->session->get_userdata()['user_details'][0]->ia_users_id : '1';
        $siteLang = $this->session->userdata('site_lang');
        $this->load->helper('language');
        if ($siteLang) {
            $this->lang->load('home', $siteLang);
            $this->lang->load('dashboard/dashboard', $siteLang);
        } else {
            $this->lang->load('home', 'english');
            $this->lang->load('dashboard/dashboard', 'english');
        }
    }

    /**
     * This function is index
     * @return Void
     */
    public function index($view = '')
    {
        isLogin();
        $data[] = '';

        $allDashboardLayouts = $this->Dashboard_layout_model->get_all_names();
        if (site_is_regional()) {
            $allowedLayouts = array_flip(site_allowed_layout_ids());
            $allDashboardLayouts = array_values(array_filter($allDashboardLayouts, function ($row) use ($allowedLayouts) {
                return isset($allowedLayouts[(int) $row->id]);
            }));
        }

        $id = $this->session->userdata('user_details')[0]->ia_users_id;


        if ($view == '') {

            $home_layout = $this->Dashboard_layout_model->get_home_for_user($id);
        } else {
            $home_layout = $this->Dashboard_layout_model->get_by_id($view);
            if (!$home_layout) {
                $home_layout = $this->Dashboard_layout_model->get_by_device($id);
            }
        }

        if (!$home_layout) {
            $layout = null;
        } else {
            $layout = $this->Dashboard_layout_model->get_by_id($home_layout->id);
        }

        if (!$layout && site_is_regional()) {
            $this->load->view('include/header');
            $this->loadSiteHome(false);
            $this->load->view('include/footer');
            return;
        }
        if (!$layout) {
            show_404();
        }

        $layout_items = $this->Dashboard_layout_model->get_items($home_layout->id);

        $data['layout'] = $layout;
        $data['layout_items'] = $layout_items;
        $data['all_dashboard_layouts'] = $allDashboardLayouts;

        $this->load->view('include/header');
        if (!site_is_regional() || site_layout_allowed($layout->id)) {
            $this->load->view('dashboard/dashboard/view', $data);
        }
        $this->loadSiteHomeContent(true);
        $this->load->view('include/footer');
    }

    private function loadSiteHome($hidden = true)
    {
        $this->loadSiteHomeContent($hidden);
    }

    private function loadSiteHomeContent($hidden = true)
    {
        $this->load->model('Site_model');
        if (!$this->Site_model->ready()) {
            $hiddenClass = $hidden ? ' d-none' : '';
            echo '<div id="home-site-panel" class="app-content' . $hiddenClass . '"><div class="container-fluid py-4 text-body-secondary">Site tables are not installed.</div></div>';
            return;
        }
        $alarms = $this->Site_model->alarmByNode();
        $tree = $this->Site_model->tree();
        $pathById = site_tree_path_by_id($tree);
        $pins = array();
        $rows = array();
        foreach ($this->Site_model->visibleRows() as $node) {
            $info = $alarms[(int) $node->id] ?? array('count' => 0, 'severity' => '');
            if ($node->node_type === 'Region' || $node->node_type === 'Site') {
                $rows[] = array(
                    'id' => (int) $node->id,
                    'type' => $node->node_type,
                    'name' => $node->name,
                    'path' => isset($pathById[(int) $node->id]) ? $pathById[(int) $node->id] : '',
                    'count' => (int) $info['count'],
                    'severity' => $info['severity'],
                );
            }
            if ($node->node_type === 'Site' && $node->latitude !== null && $node->longitude !== null && $node->latitude !== '' && $node->longitude !== '') {
                $pins[] = array(
                    'id' => (int) $node->id,
                    'name' => $node->name,
                    'latitude' => (float) $node->latitude,
                    'longitude' => (float) $node->longitude,
                    'severity' => $info['severity'],
                    'color' => $this->Site_model->pinColor($info['severity']),
                );
            }
        }
        $mapWidget = $this->Site_model->homeMapWidget();
        $mapConfig = $mapWidget ? json_decode($mapWidget->config ?? '{}') : null;
        $summary = $this->Site_model->homeSummary($alarms);
        $statusById = array();
        foreach ($summary['sites'] as $site) {
            $statusById[(int) $site['id']] = $site;
        }
        $nodesById = array();
        foreach ($this->Site_model->visibleRows() as $node) {
            $nodesById[(int) $node->id] = $node;
        }
        $ancestorOfType = function ($id, $type) use ($nodesById) {
            $current = isset($nodesById[$id]) ? $nodesById[$id] : null;
            $guard = 0;
            while ($current && $guard < 30) {
                if ($current->node_type === $type) {
                    return $current;
                }
                $parentId = $current->parent_id ? (int) $current->parent_id : 0;
                $current = ($parentId && isset($nodesById[$parentId])) ? $nodesById[$parentId] : null;
                $guard++;
            }
            return null;
        };
        $mapMains = array();
        $mapRegions = array();
        foreach ($nodesById as $node) {
            if ($node->node_type === 'Main') {
                $mapMains[] = array('id' => (int) $node->id, 'name' => $node->name);
            } elseif ($node->node_type === 'Region') {
                $main = $ancestorOfType((int) $node->id, 'Main');
                $mapRegions[] = array(
                    'id' => (int) $node->id,
                    'name' => $node->name,
                    'main_id' => $main ? (int) $main->id : 0,
                );
            }
        }
        usort($mapMains, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        usort($mapRegions, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        foreach ($summary['sites'] as $index => $site) {
            $main = $ancestorOfType((int) $site['id'], 'Main');
            $region = $ancestorOfType((int) $site['id'], 'Region');
            $summary['sites'][$index]['main_id'] = $main ? (int) $main->id : 0;
            $summary['sites'][$index]['region_id'] = $region ? (int) $region->id : 0;
        }
        $mapSites = array();
        foreach ($pins as $pin) {
            $status = isset($statusById[(int) $pin['id']]) ? $statusById[(int) $pin['id']] : null;
            $main = $ancestorOfType((int) $pin['id'], 'Main');
            $region = $ancestorOfType((int) $pin['id'], 'Region');
            $mapSites[] = array(
                'id' => (int) $pin['id'],
                'name' => $pin['name'],
                'lat' => (float) $pin['latitude'],
                'lng' => (float) $pin['longitude'],
                'severity' => $pin['severity'],
                'color' => $pin['color'],
                'online' => $status ? !empty($status['online']) : false,
                'alarms' => $status ? (int) $status['alarms'] : 0,
                'main_id' => $main ? (int) $main->id : 0,
                'region_id' => $region ? (int) $region->id : 0,
                'url' => base_url('site/view/' . (int) $pin['id']),
            );
        }
        $this->load->view('site/home_panel', array(
            'site_tree' => $tree,
            'site_pins' => $pins,
            'site_rows' => $rows,
            'site_summary' => $summary,
            'map_widget' => $mapWidget,
            'map_sites' => $mapSites,
            'map_mains' => $mapMains,
            'map_regions' => $mapRegions,
            'map_bounds' => site_map_form_bounds($mapSites, null, null, $mapConfig),
            'map_bg_url' => site_map_form_background_url($mapConfig),
            'map_tiles_url' => site_map_form_local_tiles_url(),
            'panel_hidden' => (bool) $hidden,
        ));
    }

    public function viewDashboard()
    {
        isLogin();
        $data[] = '';

        $allDashboardLayouts = $this->Dashboard_layout_model->get_all();
        $id = $this->session->userdata('user_details')[0]->ia_users_id;

        $view = $this->input->post('layout_id');

        if (empty($view)) {

            $home_layout = $this->Dashboard_layout_model->get_home_for_user($id);
        } else {
            $home_layout = $this->Dashboard_layout_model->get_by_id($view);
            if (!$home_layout) {
                $home_layout = $this->Dashboard_layout_model->get_by_device($id);
            }
        }

        if (!$home_layout) {
            show_404();
        }



        $layout = $this->Dashboard_layout_model->get_by_id($home_layout->id);
        if (!$layout) show_404();

        $layout_items = $this->Dashboard_layout_model->get_items($home_layout->id);

        $data['layout'] = $layout;
        $data['layout_items'] = $layout_items;
        $data['all_dashboard_layouts'] = $allDashboardLayouts;

        $this->load->view('dashboard/dashboard/view', $data);
    }

    /**
     * This function is used to Generate a random string
     * @return String
     */
    public function randomString($cnt = 8)
    {
        $alpha = "abcdefghijklmnopqrstuvwxyz";
        $alpha_upper = strtoupper($alpha);
        $numeric = "0123456789";
        $special = ".-+=_,!@$#*%<>[]{}";
        $chars = $alpha . $alpha_upper . $numeric;
        $pw = '';
        $chars = str_shuffle($chars);
        $pw = substr($chars, $cnt, $cnt);
        return $pw;
    }

    /**
     * This function is used to show popup of user to add and update
     * @return Void
     */
    public function getDeviceModal()
    {
        isLogin();

        echo $this->load->view('add_device', '', true);

        exit;
    }

    /**
     * This function is used to add and update device
     * @return Void
     */
    public function addEditDevice()
    {
        isLogin();
        $id = $this->user_id;
        if ($this->input->post('serial') != '') {
            $device['serial'] = $this->input->post('serial');
        }
        if ($this->input->post('location') != '') {
            $device['location'] = $this->input->post('location');
        }
        $device['users_id'] =   $id;
        $device['token'] =  md5($id);
        $checkValue = $this->User_model->checkExists('devices', 'serial', $this->input->post('serial'), md5($id), 'token');
        if ($checkValue == false) {
            $art_msg['msg'] = lang('device_this_already_registered_with_us');
            $art_msg['type'] = 'info';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . 'dashboard', 'refresh');
        }

        $this->User_model->insertRow('devices', $device);
        redirect(base_url() . 'dashboard', 'refresh');
    }

    /**
     * This function is used to delete users
     * @return Void
     */
    public function deleteDevice()
    {
        isLogin();
        if ($this->input->post('id') != '') {
            $Id = $this->input->post('id');
            $deviceId = $this->input->post('device');
            $this->User_model->deleteDevice($Id);
            $this->Api_model->deleteDevicetimer($deviceId);
            $this->Api_model->deleteDeviceapi($deviceId);
            $data["status"] = "success";
        } else {
            $data["status"] = "false";
        }
        echo json_encode($data);
    }


    public function getAlarmActive()
    {

        $data = $this->Logger_model->get_all_active_alarm();
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }


    /**
     * This function to load dashboard
     * @return Void
     */
    public function device($device_id = '')
    {
        isLogin();
        $user = $this->user_id;

        $auth_device = $this->Api_model->authApi($device_id, md5($user));
        if (!$auth_device) {
            $err["status"] = "false";
            $err["error"] = "auth";
            echo json_encode($err);
            exit;
        }
        $detail = $this->Api_model->deviceLastLog($device_id);
        $device = $this->Api_model->deviceLastLogData($device_id);

        $data['api'] = $this->Api_model->getapidata($device_id);
        if (isset($data['api']) && !empty($data['api'])) {
            foreach ($data['api'] as $key => $val) {
                $ch = $data['api'][$key]['ch'];
                $ac = $data['api'][$key]['state'];

                if ($ch == "1") {
                    $data['api'][$key]['ch'] = isset($device) ? $device->ch1->name : lang('device_ch1');
                } elseif ($ch == "2") {
                    $data['api'][$key]['ch'] = isset($device) ? $device->ch2->name : lang('device_ch2');
                } elseif ($ch == "3") {
                    $data['api'][$key]['ch'] = isset($device) ? $device->ch3->name : lang('device_ch3');
                } else {
                    $data['api'][$key]['ch'] = lang('device_type_notuse');
                }

                if ($ac == "0") {
                    $data['api'][$key]['state'] = lang('api_off');
                    $data['api'][$key]['submsg'] = "False";
                } else {
                    $data['api'][$key]['state'] = lang('api_on');
                    $data['api'][$key]['submsg'] = "True";
                }

                $data['api'][$key]['pubtopic'] = "/api/pub/" . $data['api'][$key]['device_id'] . "/ch" . $ch;
                $data['api'][$key]['subtopic'] = "/api/sub/" . $data['api'][$key]['device_id'] . "/ch" . $ch;
                $data['api'][$key]['pubmsg'] = $data['api'][$key]['apikey'];


                /*
                $data['api'][$key]['url'] = $val[$key][5];
                $data['api'][$key]['apikey'] = $val[$key][5];
                $data['api'][$key]['state'] = $val[$key][5];
                */
            }
        }

        if (!$detail) {
            $data['device_serial'] = $device_id;
            $data['device_name'] = "null";
            $data['device_fw'] = "null";
            $data['device_ch1'] = "null";
            $data['device_ch2'] = "null";
            $data['device_ch3'] = "null";
            $data['device_last_use'] = "null";
        } else {
            $json = $this->Api_model->deviceLastLogData($device_id);
            $data['device_serial'] = $detail[0]->device_id;
            $data['device_name'] = $json->name;
            $data['device_fw'] = $json->fw;
            $data['device_ch1'] = $json->ch1->type;
            $data['device_ch2'] = $json->ch2->type;
            $data['device_ch3'] = $json->ch3->type;
            $data['device_last_use'] = $detail[0]->time;
            $data['details'] = $json;
        }

        $data['device'] = $device_id;
        $data['user'] = $user;
        $this->load->view('include/header');
        $this->load->view('device/index', $data);
        $this->load->view('include/footer');
    }
    /**
     * This function is used to show timer list
     */
    public function timerData($device_id = '')
    {
        isLogin();
        $user = $this->user_id;

        $auth_device = $this->Api_model->authApi($device_id, md5($user));
        if (!$auth_device && $device_id != "null") {
            $err["status"] = "false";
            $err["error"] = "auth";
            echo json_encode($err);
            exit;
        }
        $device = $this->Api_model->deviceLastLogData($device_id);


        $primaryKey = 'device_id';
        $table = 'timer';
        $columns = array(
            array('db' => 'id', 'dt' => 0),
            array('db' => 'ch', 'dt' => 1),
            array('db' => 'type', 'dt' => 2),
            array('db' => 'activity', 'dt' => 3),
            array('db' => 'date', 'dt' => 4),
            array('db' => 'time', 'dt' => 5),
            array('db' => 'day', 'dt' => 6)
        );
        $joinQuery = "FROM `timer` ";
        $where = '';
        $j = 0;
        if (strpos($joinQuery, 'JOIN') > 0) {
            $j = 1;
        }
        $where = SSP::iaFilter($_GET, $columns, $j);
        if ($where == '') {
            $where .= ' WHERE ';
        } else {
            $where .= ' AND ';
        }
        $where .= "  `timer`.`device_id` = $device_id";

        $group_by = "";
        $having = "";

        $limit = SSP::limit($_GET, $columns);
        $order = SSP::order($_GET, $columns, $j);
        $col = SSP::pluck($columns, 'db', $j);

        $query = "SELECT SQL_CALC_FOUND_ROWS " . implode(", ", $col) . " " . $joinQuery . " " . $where . " " . $group_by . " " . $having . " " . $order . " " . $limit . " ";
        $res = $this->db->query($query);
        $res = $res->result_array();
        $recordsTotal = $this->db->select("count('timer') AS c")->where('device_id', $device_id)->get('timer')->row()->c;
        $res = SSP::data_output($columns, $res, $j);

        $output_arr['draw'] = intval($_GET['draw']);
        $output_arr['recordsTotal'] = intval($recordsTotal);
        $output_arr['recordsFiltered'] = intval($recordsTotal);
        $output_arr['data'] = $res;

        foreach ($output_arr['data'] as $key => $value) {

            $id = $output_arr['data'][$key][0];
            $ch = $output_arr['data'][$key][1];
            $re = $output_arr['data'][$key][2];
            $ac = $output_arr['data'][$key][3];
            if ($ch == "1") {
                $output_arr['data'][$key][1] = isset($device) ? $device->ch1->name : lang('device_ch1');
            } elseif ($ch == "2") {
                $output_arr['data'][$key][1] = isset($device) ? $device->ch2->name : lang('device_ch2');
            } elseif ($ch == "3") {
                $output_arr['data'][$key][1] = isset($device) ? $device->ch3->name : lang('device_ch3');
            } else {
                $output_arr['data'][$key][1] = lang('device_type_notuse');
            }

            if ($re == "1") {
                $output_arr['data'][$key][2] = lang('timer_everday');
                $output_arr['data'][$key][4] = $output_arr['data'][$key][5];
            } else if ($re == "2") {
                $trans = array("0" => lang('day_Sun'), "1" => lang('day_Mon'), "2" => lang('day_Tue'), "3" => lang('day_Wed'), "4" => lang('day_Thu'), "5" => lang('day_Fri'), "6" => lang('day_Sat'));
                $dayofweek = strtr($output_arr['data'][$key][6], $trans);
                $output_arr['data'][$key][2] = lang('timer_daily');
                $output_arr['data'][$key][4] =  $dayofweek;
                $output_arr['data'][$key][4] .= ' ';
                $output_arr['data'][$key][4] .= $output_arr['data'][$key][5];
            } else {
                $output_arr['data'][$key][4] .= ' ';
                $output_arr['data'][$key][4] .= $output_arr['data'][$key][5];
                $output_arr['data'][$key][2] = lang('timer_onetime');
            }

            if ($ac == "1") {
                $output_arr['data'][$key][3] = lang('timer_on');
            } else {
                $output_arr['data'][$key][3] = lang('timer_off');
            }

            $output_arr['data'][$key][5] = '';
            $output_arr['data'][$key][5] .= '<a sty id="btnEditRow" class="modalButton mClass"  href="javascript:;" type="button" data-toggle="modal"  data-target="#custom_fields_modal" data-src="' . $id . '" title="' . lang('edit') . '"><i class="material-icons font-20">mode_edit</i></a>';
            $output_arr['data'][$key][5] .= '<a data-toggle="modal" class="mClass" style="cursor:pointer;"  data-target="#cnfrm_delete" title="' . lang('delete') . '" onclick="setId(' . $id . ', \'dashboard\')"><i class="material-icons col-red font-20">delete</i></a>';
        }
        echo json_encode($output_arr);
    }

    public function getModal()
    {
        $device_id = $this->input->post('device');
        $detail = $this->Api_model->deviceLastLogData($device_id);
        if ($detail) {
            $data['details'] = $detail;
        }

        $data['device'] = $device_id;
        if ($this->input->post('id')) {
            $deviceInfo = $this->Api_model->GetDataById($this->input->post('id'));
            $data['data'] = $deviceInfo;
            $data['day'] = explode(",", $deviceInfo->day);
            echo $this->load->view('device/add_update', $data, true);
        } else {
            echo $this->load->view('device/add_update', $data, true);
        }
        exit;
    }
    public function getModalApi()
    {
        $device_id = $this->input->post('device');
        $detail = $this->Api_model->deviceLastLogData($device_id);
        if ($detail) {
            $data['details'] = $detail;
        }

        $data['device'] = $device_id;
        if ($this->input->post('id')) {
            $data['data'] = $this->Api_model->GetDataById($this->input->post('id'));
            echo $this->load->view('device/add_update_api', $data, true);
        } else {
            echo $this->load->view('device/add_update_api', $data, true);
        }
        exit;
    }
    public function customtimer()
    {
        if ($this->input->post('id')) {
            $data = array(
                "ch" => $this->input->post('ch'),
                "activity" => $this->input->post('action'),
                "type" => $this->input->post('type'),
                "date" => $this->input->post('date'),
                "day" => implode(',', $this->input->post('day')),
                "time" => $this->input->post('time'),
                "device_id" => $this->input->post('device_id')
            );
            if ($this->Api_model->updateRow('timer', 'id', $this->input->post('id'), $data)) {
                $this->session->set_flashdata('message', lang('timer_updated_successfully') . '..');
            }
        } else {
            $data = array(
                "ch" => $this->input->post('ch'),
                "activity" => $this->input->post('action'),
                "type" => $this->input->post('type'),
                "date" => $this->input->post('date'),
                "day" => implode(',', $this->input->post('day')),
                "time" => $this->input->post('time'),
                "device_id" => $this->input->post('device_id')
            );
            if ($this->Api_model->insertRow('timer', $data)) {
                $art_msg['msg'] = lang('timer_added_successfully');
                $art_msg['type'] = 'success';
                $this->session->set_userdata('alert_msg', $art_msg);
            }
        }
        redirect(base_url() . '/dashboard/device/' . $this->input->post('device_id') . '#timer-setting');
    }

    public function customapi()
    {
        // id	apikey	url	user_id	device_id	ch	state	token
        $user = $this->user_id;
        $token = md5($user);
        $apikey =  $this->randomString(5);
        $url = base_url() . "api/change/" . $apikey;
        if ($this->input->post('id')) {
            $data = array(
                "apikey" => $apikey,
                "url" => $url,
                "ch" => $this->input->post('ch'),
                "state" => $this->input->post('action'),
            );
            if ($this->Api_model->updateRow('api', 'id', $this->input->post('id'), $data)) {
                $this->session->set_flashdata('message', lang('api_updated_successfully') . '..');
            }
        } else {
            $data = array(
                "apikey" => $apikey,
                "url" => $url,
                "user_id" => $user,
                "device_id" => $this->input->post('device_id'),
                "ch" => $this->input->post('ch'),
                "state" => $this->input->post('action'),
                "token" => $token
            );
            if ($this->Api_model->insertRow('api', $data)) {
                $art_msg['msg'] = lang('api_added_successfully');
                $art_msg['type'] = 'success';
                $this->session->set_userdata('alert_msg', $art_msg);
            }
        }
        redirect(base_url() . '/dashboard/device/' . $this->input->post('device_id') . '#api-setting');
    }
    public function delete($ids)
    {
        $idsArr = explode('-', $ids);
        foreach ($idsArr as $key => $value) {
            $this->Api_model->deleteData($value);
        }
        echo json_encode($idsArr);
    }

    public function deleteapi($act = '', $keys = '')
    {
        if (empty($act)) {
            $data["status"] = "activity false";
        }
        if (!empty($keys)) {
            $key = $keys;
        } else {
            $key =  $this->input->post('key');
        }
        if (empty($key)) {
            $data["status"] = "token false";
        } else {
            $res = $this->Api_model->deleteApi($key);
            if ($res) {
                $data["status"] = "success";
                $data["key"] = $key;
            } else {
                $data["status"] = "false";
            }
        }

        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
}
