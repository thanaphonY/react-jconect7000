<?php
defined('BASEPATH') or exit('No direct script access allowed ');
class Apinotify extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Api_model');
        $this->load->model('User_model');
        //$this->load->library("mqtt");
    }

    public function index()
    {

        $data["data"] = "token false";


        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    public function sendnotify()
    {
        if ($this->input->post('emails')) {
            $setting = settings();

           // $var_key = $this->randomString();
            $emailArray = explode(',', $this->input->post('emails'));
            $msgAlarm = $this->input->post('msgAlarm');
            $deviceToken = $this->input->post('device');

            $emailArray = array_map('trim', $emailArray);

            $body = $this->User_model->getTemplate('notification');
            $result['existCount'] = 0;
            $result['seccessCount'] = 0;
            $result['invalidEmailCount'] = 0;
            $result['noTemplate'] = 0;
            $result['Template'] = $body;
            $result['emails'] = $emailArray;
            $result['msgAlarm'] = $msgAlarm;
            $result['device'] = $deviceToken;

            if (isset($body->html) && $body->html != '') {
                $body = $body->html;

                foreach ($emailArray as $mailKey => $mailValue) {
                    if (filter_var($mailValue, FILTER_VALIDATE_EMAIL)) {
                        $res = $this->User_model->getDataBy('d_entity', $deviceToken, 'token');
                        $result['dbdevice'] =$res;
                        //if (is_array($res) && empty($res)) {
                            $data = array(
                                'user_email' => $mailValue,
                                'alarm_message' => $msgAlarm,
                                'device_name' => $res[0]->title, //ชื่อ
                                'device_description' => $res[0]->description, //รายละเอียด
                                'device_location' => $res[0]->location, //สถานที่
                                'device_token' => $res[0]->token, //key
                                'device_labels' => $res[0]->labels, //lables
                                'device_assign' => $res[0]->assign, //assign                              
                                'device_profile' => $res[0]->data, //profile
                                'action_url' => base_url(),
                                'sender_name' => $setting['company_name'],
                                'website_name' => $setting['website'],
                            );
                            foreach ($data as $key => $value) {
                                $body = str_replace('{var_' . $key . '}', $value, $body);
                            }
                            if ($setting['mail_setting'] == 'php_mailer') {
                                $this->load->library("send_mail");
                                $emm = $this->send_mail->email('Alarm Notification', $body, $mailValue, $setting);
                            } else {
                                // content-type is required when sending HTML email
                                $headers = "MIME-Version: 1.0" . "\r\n";
                                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                                $headers .= 'From: ' . $setting['EMAIL'] . "\r\n";
                                $emm = mail($mailValue, 'Alarm Notification ', $body, $headers);
                            }
                            if ($emm) {
                                /*$darr = array('email' => $mailValue, 'var_key' => $var_key);
                                $this->User_model->insertRow('ia_users', $darr);*/
                                $result['seccessCount'] += 1;
                            }
                        //} else {
                         //   $result['existCount'] += 1;
                        //}
                    } else {
                        $result['invalidEmailCount'] += 1;
                    }
                }
            } else {
                $result['noTemplate'] = lang('no_email_template_availabale') . '.';
            }
        }
        echo json_encode($result);
        exit;
    }
}
