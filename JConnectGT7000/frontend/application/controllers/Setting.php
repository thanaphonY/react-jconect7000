<?php defined("BASEPATH") or exit("No direct script access allowed");

class Setting extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        //Checking user is login or not
        isLogin();
        site_require_global();
        $this->load->model("Setting_model");
        $this->lang->load('settings', getSetting('language'));
    }

    /**
     * Load Setting view page
     */
    public function index()
    {
        $this->load->model("Update_model");
        $result = array();
        $data['result'] = getSetting();
        $result = [];
        foreach ($data['result'] as $key => $value) {
            $result[$value->keys] = $value->value;
        }

        $this->load->view('include/header');
        if (getSetting('UserModules') == 'yes') {
            if ((isset($this->session->get_userdata()['user_details'][0]->user_type) && $this->session->get_userdata()['user_details'][0]->user_type == 'admin') || CheckPermission('setting', "all_read")) {
                $data['result'] = $result;
                $this->load->view('setting/index', $data);
            }
        } else {
            $data['result'] = $result;
            $this->load->view('setting/index', $data);
        }

        $this->load->view('include/footer');
    }

    /**
     * This function is used to update setting
     */
    public function editSetting()
    {
        $data = array();
        $data = $this->input->post();
        $data['logo'] = '';
        if (isset($data['user_type']) && is_array($data['user_type']) && !empty($data['user_type'])) {
            $data['user_type'] = json_encode($data['user_type']);
        }

        if (!empty($_FILES['logo']['name'])) {
            $newname = upload('logo');
            $data['logo'] = $newname;
            if (!$newname) {
                redirect(base_url() . 'setting', 'refresh');
            }
        } else {
            if ($this->input->post('fileOldlogo')) {
                $data['logo'] = $this->input->post('fileOldlogo');
            }
        }
        if (!isset($data['register_allowed'])) {
            $data['register_allowed'] = 0;
        }

        if (!isset($data['admin_approval'])) {
            $data['admin_approval'] = 0;
        }

        foreach ($data as $key => $value) {
            $this->Setting_model->updateRow('ia_setting', 'keys', $key, array('value' => $value));
        }

        $art_msg['msg'] = lang('your_data_updated_successfully');
        $art_msg['type'] = 'success';
        $this->session->set_userdata('alert_msg', $art_msg);
        if ($this->input->post('mail_setting')) {
            redirect(base_url() . 'setting#emailSetting', 'refresh');
        } else {
            redirect(base_url() . 'setting', 'refresh');
        }
    }

    public function updateRole()
    {
        $data = $this->input->post();

        if (isset($data['user_type_name'])) {
            if (is_array($data['user_type_name']) && !empty($data['user_type_name'])) {
                foreach ($data['user_type_name'] as $utkey => $utvalue) {
                    $this->Setting_model->updateRow('ia_permission', 'id', $utkey, array('user_type' => $utvalue));
                }
            }

            unset($data['user_type_name']);
        }

        if (isset($data['new_user_name'])) {
            $permissiona = $this->Setting_model->getAdminPermissions();
            if (is_array($data['new_user_name']) && !empty($data['new_user_name'])) {
                foreach ($data['new_user_name'] as $nutkey => $nutvalue) {
                    $inst_data = array(
                        'user_type' => $nutvalue,
                        'data' => $permissiona->data,
                    );
                    $this->Setting_model->insertRow('ia_permission', $inst_data);
                }
            }
            unset($data['new_user_name']);
        }

        if (isset($data['rm_user_type'])) {
            if (is_array($data['rm_user_type']) && !empty($data['rm_user_type'])) {
                foreach ($data['rm_user_type'] as $rutkey => $rutvalue) {
                    $this->Setting_model->delete('ia_permission', 'id', $rutvalue);
                }
            }
            unset($data['rm_user_type']);
        }

        $art_msg['msg'] = lang('your_data_updated_successfully');
        $art_msg['type'] = 'success';
        $this->session->set_userdata('alert_msg', $art_msg);
        redirect(base_url() . 'setting#permission-setting', 'refresh');
    }

    /**
     * This function is used to add new user type
     * @return: true if update successfuly
     */
    public function addUserType()
    {
        echo $this->Setting_model->addUserType();
        exit;
    }

    /**
     * This function is used to update user permissions
     * @return: true if update successfuly
     */

    public function permission()
    {
        $data = array();
        $dataa = $this->input->post('data');
        foreach ($dataa as $key => $value) {
            $key = str_replace('_SPACE_', ' ', $key);
            $arr = array();
            foreach ($value as $vkey => $vvalue) {
                $vkey = str_replace('_SPACE_', ' ', $vkey);
                $arr[$vkey] = $vvalue;
            }
            $this->Setting_model->updateRow('ia_permission', 'user_type', $key, array('data' => json_encode($arr)));
        }
        $art_msg['msg'] = lang('your_data_updated_successfully');
        $art_msg['type'] = 'success';
        $this->session->set_userdata('alert_msg', $art_msg);
        redirect(base_url() . 'setting#permission-setting', 'refresh');
    }

    /**
     * This function is used to show custom fields list in setting
     */
    public function ajaxData()
    {
        $draw = intval($this->input->get('draw'));
        $start = intval($this->input->get('start'));
        $length = intval($this->input->get('length'));
        $search = $this->input->get('search')['value'];
        $order_column_index = $this->input->get('order')[0]['column'];
        $order_column = $this->input->get('columns')[$order_column_index]['data'];
        $order_dir = $this->input->get('order')[0]['dir'];

        $this->db->from('ia_custom_fields');

        if (!empty($search)) {
            $this->db->group_start()
                ->like('ia_custom_fields_id::text', $search)
                ->or_like('name', $search)
                ->or_like('type', $search)
                ->or_like('rel_crud', $search)
                ->or_like('status', $search)
                ->or_like('create_date::text', $search)
                ->group_end();
        }

        $recordsFiltered = $this->db->count_all_results('', false);

        $this->db->order_by($order_column, $order_dir);
        $this->db->limit($length, $start);
        $query = $this->db->get();
        $data = $query->result_array();

        $recordsTotal = $this->db->count_all('ia_custom_fields');

        $output_arr = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => []
        ];

        foreach ($data as $row) {
            $id = $row['ia_custom_fields_id'];
            $row[] = '<a id="btnEditRow" class="modalButton mClass" href="javascript:;" type="button" data-toggle="modal" data-target="#custom_fields_modal" data-src="' . $id . '" title="Edit"><i class="material-icons font-20">mode_edit</i></a>';
            $row[count($row)-1] .= '<a data-toggle="modal" class="mClass" style="cursor:pointer;" data-target="#cnfrm_delete" title="Delete" onclick="setId(' . $id . ', \"setting\")"><i class="material-icons col-red font-20">delete</i></a>';
            $output_arr['data'][] = array_values($row);
        }

        echo json_encode($output_arr);
    }

    public function customField()
    {
        if ($this->input->post('id')) {
            $data = array(
                "rel_crud" => $this->input->post('crud_nmae'),
                "name" => $this->input->post('field_name'),
                "type" => $this->input->post('type'),
                "required" => $this->input->post('required'),
                "options" => $this->input->post('options'),
                "status" => $this->input->post('status'),
                "show_in_grid" => $this->input->post('show_in_grid'),
            );
            $old_res = $this->getCustomFieldById($this->input->post('id'));
            if ($this->Setting_model->updateRow('ia_custom_fields', 'ia_custom_fields_id', $this->input->post('id'), $data)) {
                $this->UpdateCustomFieldLang($old_res, $this->input->post('field_name'), $this->input->post('crud_nmae'));
                $this->session->set_flashdata('message', lang('your_data_updated_successfully') . '..');
            }
        } else {
            $data = array(
                "rel_crud" => $this->input->post('crud_nmae'),
                "name" => $this->input->post('field_name'),
                "type" => $this->input->post('type'),
                "required" => $this->input->post('required'),
                "options" => $this->input->post('options'),
                "status" => $this->input->post('status'),
                "show_in_grid" => $this->input->post('show_in_grid'),
            );
            if ($this->Setting_model->insertRow('ia_custom_fields', $data)) {
                $this->setCustomFieldLang($this->input->post('field_name'), $this->input->post('crud_nmae'));
                $art_msg['msg'] = lang('your_data_added_successfully');
                $art_msg['type'] = 'success';
                $this->session->set_userdata('alert_msg', $art_msg);
            }
        }
        redirect(base_url() . '/setting#custom-fields');
    }

    public function getModal()
    {
        $data['result'] = getSetting();
        $result = [];
        foreach ($data['result'] as $key => $value) {
            $result[$value->keys] = $value->value;
        }
        $data['result'] = $result;
        if ($this->input->post('id')) {
            $data['data'] = $this->Setting_model->GetDataById($this->input->post('id'));
            echo $this->load->view('setting/add_update', $data, true);
        } else {
            echo $this->load->view('setting/add_update', $data, true);
        }
        exit;
    }

    public function delete($ids)
    {
        $idsArr = explode('-', $ids);
        foreach ($idsArr as $key => $value) {
            $this->Setting_model->deleteData($value);
        }
        echo json_encode($idsArr);
        exit;
    }

    public function switchLang($language = "")
    {
        $language = ($language != "") ? $language : "english";
        $this->Setting_model->updateRow('ia_setting', 'keys', 'language', array('value' => $language));
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function setThemeColorCookie()
    {
        if ($this->input->post('theme_color')) {
            $this->load->helper('cookie');
            $cookie = array(
                'name' => 'theme_color',
                'value' => $this->input->post('theme_color'),
                'expire' => 86400 * 7,
                'path' => '/',
                'secure' => false,
            );
            set_cookie($cookie);

            echo "1";
            exit;
        } else {
            echo "0";
            exit;
        }
    }

    public function setCustomFieldLang()
    {
        $lang_var = '';
        if ($this->input->post('options') != '') {
            $opt = explode(',', $this->input->post('options'));
            foreach ($opt as $key => $value) {
                $lang_var .= '$lang[\'' . getLang($this->input->post('crud_nmae') . '_' . trim($value)) . '\'] = \'' . trim($value) . '\';' . PHP_EOL;
            }
        }
        $lang_var .= '$lang[\'' . getLang(trim($this->input->post('crud_nmae'))) . '_' . getLang(trim($this->input->post('field_name'))) . '\'] = \'' . $this->input->post('field_name') . '\';' . PHP_EOL;
        $Path = realpath(dirname(dirname(dirname(dirname(__FILE__))))) . '/language/english';
        $g_lang = file_get_contents($Path . '/custom_field_lang.php');
        $lg = $lang_var . '/*ia_custom_variables*/';
        $g_lang = str_replace('/*ia_custom_variables*/', $lg, $g_lang);
        file_put_contents($Path . '/custom_field_lang.php', $g_lang);
    }

    public function getCustomFieldById($id)
    {
        $this->db->select('*');
        $this->db->from('ia_custom_fields');
        $this->db->where('ia_custom_fields_id', $id);
        $query = $this->db->get();
        return $result = $query->row();
    }

    public function UpdateCustomFieldLang($old_result, $field_name, $crud_name)
    {
        $lang_var = '$lang[\'' . getLang(trim($crud_name)) . '_' . getLang(trim($field_name)) . '\'] = \'' . $field_name . '\';';
        $lg = '$lang[\'' . getLang(trim($old_result->rel_crud)) . '_' . getLang(trim($old_result->name)) . '\'] = \'' . $old_result->name . '\';';
        $Path = realpath(dirname(dirname(dirname(dirname(__FILE__))))) . '/language/english';
        $g_lang = file_get_contents($Path . '/custom_field_lang.php');
        $g_lang = str_replace($lg, $lang_var, $g_lang);
        file_put_contents($Path . '/custom_field_lang.php', $g_lang);

        if ($this->input->post('options') != '') {
            $opt = explode(',', $this->input->post('options'));
            $old = explode(',', $old_result->options);
            $lang_var = '';
            $lg = '';
            foreach ($opt as $key => $value) {
                $Path = realpath(dirname(dirname(dirname(dirname(__FILE__))))) . '/language/english';
                $g_lang = file_get_contents($Path . '/custom_field_lang.php');
                $m = '';
                if (isset($old[$key])) {
                    $lg = '$lang[\'' . getLang(trim($this->input->post('crud_nmae'))) . '_' . getLang(trim($old[$key])) . '\'] = \'' . trim($old[$key]) . '\';';
                } else {
                    $lg = '/*ia_custom_variables*/';
                    $m = PHP_EOL . $lg;
                }
                $lang_var = '$lang[\'' . getLang($this->input->post('crud_nmae') . '_' . trim($value)) . '\'] = \'' . trim($value) . '\';' . $m;

                $g_lang = str_replace($lg, $lang_var, $g_lang);
                file_put_contents($Path . '/custom_field_lang.php', $g_lang);
            }
        }
    }

    public function getUpdateInfo()
    {
        $res = 0;
        $this->load->model("Update_model");
        $updateInfo = ''; //$this->Update_model->getUpdateInfo();
        $updateInfo = json_decode($updateInfo);
        $current = $this->Update_model->getCurrentDbVersion();
        if (isset($updateInfo->latest_version) && $updateInfo->latest_version > $current) {
            $res = 1;
            $this->session->set_userdata('VersionUpdate', 1);
        }

        echo $res;
        exit;
    }

    public function getCurrentVersion()
    {

        /*if (isset($this->session->get_userdata()['VersionChecking'])) {
        $this->session->unset_userdata('VersionChecking');
        }
        if (isset($this->session->get_userdata()['update_info'])) {
        $this->session->unset_userdata('update_info');
        }

        die;*/
        $res = array();
        if (isset($this->session->get_userdata()['VersionChecking']) && $this->session->get_userdata()['VersionChecking'] == 1) {
            if (isset($this->session->get_userdata()['update_info'])) {
                $res = array('action' => false, 'ia_data' => $this->session->get_userdata()['update_info']);
            } else {
                $res = array('action' => false, 'ia_data' => '0');
            }
        } else {
            $this->session->set_userdata('VersionChecking', 1);
            $this->load->model("Update_model");
            $res = array('action' => true, 'ia_data' => $this->Update_model->getCurrentDbVersion());
        }
        echo json_encode($res);
        exit;
    }

    public function getNotificationHtml()
    {
        $this->session->set_userdata('update_info', $this->input->post('update_info'));
        $html = '
            <div class="panel bg-white">
                <div class="panel-heading b-light bg-light">
                  <strong> ' . lang('you_have') . ' <span>1</span> ' . lang('notifications') . ' </strong>
                </div>
                <div class="list-group">
                  <a href=" /setting#system-update-setting" class="list-group-item">
                    <span class="clear block m-b-none">
                      ' . $this->input->post('update_info')['latest_version'] . '  ' . lang('initial_released') . '<br>
                    </span>
                  </a>
                </div>
              </div>
        ';
        echo $html;
        exit;
    }

    public function getUpdateHtml()
    {
        $this->load->model("Update_model");
        if (!extension_loaded('curl')) {
            $data['update_errors'][] = lang('CURL_Extension_not_enabled');
            $data['latest_version'] = 0;
            $data['update_info'] = json_decode("");
        } else {
            $data['update_info'] = ''; //$this->Update_model->getUpdateInfo();
            if (strpos($data['update_info'], 'Curl Error -') !== false) {
                $data['update_errors'][] = $data['update_info'];
                $data['latest_version'] = 0;
                $data['update_info'] = json_decode("");
                $data['download_url'] = '';
            } else {
                $data['update_info'] = json_decode($data['update_info']);
                $data['latest_version'] = $data['update_info']->latest_version;
                $data['download_url'] = $data['update_info']->download_url;
                $data['update_errors'] = array();
            }
        }

        if (!extension_loaded('zip')) {
            $data['update_errors'][] = lang('ZIP_extension_not_enabled');
        }

        $data['current_version'] = $this->Update_model->getCurrentDbVersion();

        echo $this->load->view('setting/update', $data, true);
        exit;
    }

    function getnetwork()
    {
        $this->load->library('Network/adaptor');
        $this->load->library('Network/networkinterfaces');
        //$res = $this->ethernet_model->getin();

        $handle = new Networkinterfaces('/etc/network/interfaces');
        // parse file
        $handle->parse();
        // print all adaptors
        $res = json_encode($handle->Adaptors);
        echo $res;
    }

    private function fileReplaceContent($path, $oldContent, $newContent)
    {
        $str = file_get_contents($path);
        $str = str_replace($oldContent, $newContent, $str);
        file_put_contents($path, $str);
    }

    public function updateEth1()
    {
        // $data = array();
        $dhcp = $this->input->post('dhcp');
        $ipv4 = $this->input->post('ipv4');
        $subnet = $this->input->post('subnet');
        $gateway = $this->input->post('gateway');
        $dns1 = $this->input->post('dns1');
        $dns2 = $this->input->post('dns2');


        $dataa["dhcp"] = $dhcp;
        $dataa["ipv4"] = $ipv4;
        $dataa["subnet"] = $subnet;
        $dataa["gateway"] = $gateway;
        $dataa["dns1"] = $dns1;
        $dataa["dns2"] = $dns2;

        $this->Setting_model->updateRow('ia_setting', 'keys', 'eth1', array('value' => json_encode($dataa)));
        $art_msg['msg'] = lang('your_data_updated_successfully');
        $art_msg['type'] = 'success';
        $this->session->set_userdata('alert_msg', $art_msg);

        $this->fileReplaceContent('/etc/network/interfaces', 'dns-nameservers', 'dnsnameservers');


        $this->load->library('Network/adaptor');
        $this->load->library('Network/networkinterfaces');

        $handle = new NetworkInterfaces('/etc/network/interfaces');
        $handle->parse();

        if ($dhcp == "1")
            $handle->Adaptors['eth0']->method = 'dhcp';
        else
            $handle->Adaptors['eth0']->method = 'static';

        $handle->Adaptors['lo']->auto = true;
        $handle->Adaptors['eth0']->address = $ipv4;
        $handle->Adaptors['eth0']->netmask = $subnet;
        $handle->Adaptors['eth0']->gateway = $gateway;
        $handle->Adaptors['eth0']->dnsnameservers = $dns1;
        //$handle->Adaptors['eth0']->dnsnameservers = $dns1 . ' ' . $dns2;
        $handle->write();

        $this->fileReplaceContent('/etc/network/interfaces', 'dnsnameservers', 'dns-nameservers');

        $setdns = "#Jconnect\n";
        $setdns .= "nameserver " . $dns1 . "\n";
        $setdns  .= "nameserver " . $dns2;
        file_put_contents('/etc/resolvconf/resolv.conf.d/head', $setdns);
        shell_exec("sudo resolvconf -u");

        //$handle->down('eth1', true);
        $handle->restart('eth0', true);
        //$handle->up('eth1', true);
        //echo "ok";

        redirect(base_url() . 'setting#network-setting', 'refresh');
    }

    public function updateEth2()
    {
        $dhcp = $this->input->post('dhcp');
        $ipv4 = $this->input->post('ipv4');
        $subnet = $this->input->post('subnet');


        $dataa["dhcp"] = $dhcp;
        $dataa["ipv4"] = $ipv4;
        $dataa["subnet"] = $subnet;

        $this->Setting_model->updateRow('ia_setting', 'keys', 'eth2', array('value' => json_encode($dataa)));
        $art_msg['msg'] = lang('your_data_updated_successfully');
        $art_msg['type'] = 'success';
        $this->session->set_userdata('alert_msg', $art_msg);


        $this->load->library('Network/adaptor');
        $this->load->library('Network/networkinterfaces');

        $handle = new NetworkInterfaces('/etc/network/interfaces');
        $handle->parse();

        if ($dhcp == "1")
            $handle->Adaptors['eth1']->method = 'dhcp';
        else
            $handle->Adaptors['eth1']->method = 'static';

        //$handle->Adaptors['eth1']->auto = true;
        $handle->Adaptors['eth1']->address = $ipv4;
        $handle->Adaptors['eth1']->netmask = $subnet;
        $handle->write();
        $handle->restart('eth1', true);

        redirect(base_url() . 'setting#network-setting', 'refresh');
    }

    public function updateRount()
    {
        // $data = array();
        $dataa["line"] = $this->input->post('line');
        $dataa["email"] = $this->input->post('email');
        $dataa["sms"] = $this->input->post('sms');

        $this->Setting_model->updateRow('ia_setting', 'keys', 'route', array('value' => json_encode($dataa)));
        $art_msg['msg'] = lang('your_data_updated_successfully');
        $art_msg['type'] = 'success';
        $this->session->set_userdata('alert_msg', $art_msg);
        redirect(base_url() . 'setting#network-setting', 'refresh');
    }

    public function j1000gateway()
    {
        // $data = array();
        $ipv4 = $this->input->post('ipv4');
        $access = $this->input->post('access');

        $dataa["ipv4"] = $ipv4;
        $dataa["access"] = $access;

        $this->Setting_model->updateRow('ia_setting', 'keys', 'j1000gateway', array('value' => json_encode($dataa)));
        $art_msg['msg'] = lang('your_data_updated_successfully');
        $art_msg['type'] = 'success';
        $this->session->set_userdata('alert_msg', $art_msg);

        $setnginx = "#Create by JConnect\n";
        $setnginx .= "server {\n";
        $setnginx .= "    listen 9090;\n";
        $setnginx .= "    root /var/www/html;\n";
        $setnginx .= "    index index.html index.php index.htm index.nginx-debian.html;\n";
        $setnginx .= "    location /{\n";
        $setnginx .= "        proxy_pass http://" . $ipv4 . ":80;\n";
        $setnginx .= "        proxy_set_header X-Real-IP \$remote_addr;\n";
        $setnginx .= "        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;\n";
        $setnginx .= "        proxy_set_header X-Forwarded-Proto \$scheme;\n";
        $setnginx .= "    }\n";
        $setnginx .= "  client_max_body_size 10M;\n";
        $setnginx .= "  client_body_buffer_size 10M;\n";
        $setnginx .= "}";

        file_put_contents('/etc/nginx/snippets/phpj1000.conf', $setnginx);
        $res = shell_exec("sudo nginx -t");
        shell_exec("sudo systemctl reload nginx");

        //echo $res;
        redirect(base_url() . 'setting#network-setting', 'refresh');
    }


    public function setnetworketh1()
    {
        $this->load->library('Network/adaptor');
        $this->load->library('Network/networkinterfaces');

        // create new handle from /etc/networking/interfaces
        $handle = new NetworkInterfaces('/etc/network/interfaces');

        // parse file
        $handle->parse();


        $handle->down('eth0', true);

        // change eth0 ip address

        $handle->Adaptors['eth0']->method = 'static';
        $handle->Adaptors['eth0']->address = '192.168.137.30';
        $handle->Adaptors['eth0']->netmask = '255.255.255.0';
        $handle->Adaptors['eth0']->gateway = '192.168.137.1';
        $handle->Adaptors['eth1']->dnsnameserver = "8.8.8.8";



        // Write changes to /etc/networking/interfaces
        $handle->write();

        // bringing up new interface
        $handle->up('eth0', true);
        echo "OK";
    }
    function downeth1()
    {
        $this->load->library('Network/adaptor');
        $this->load->library('Network/networkinterfaces');

        // create new handle from /etc/networking/interfaces
        $handle = new NetworkInterfaces('/etc/network/interfaces');

        // parse file
        $handle->parse();

        $output = $handle->down('eth1', true);

        echo $output;
        //echo "OK";
    }
    function upeth1()
    {
        $this->load->library('Network/adaptor');
        $this->load->library('Network/networkinterfaces');

        // create new handle from /etc/networking/interfaces
        $handle = new NetworkInterfaces('/etc/network/interfaces');

        // parse file
        $handle->parse();

        $output = $handle->up('eth1', true);

        echo $output;
        //echo "OK";
    }
    function reboot()
    {
        $output = shell_exec('sudo reboot');
        echo "<pre>$output</pre>";

        //echo "OK";
    }
}
