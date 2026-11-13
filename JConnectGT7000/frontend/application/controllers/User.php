<?php
defined('BASEPATH') or exit('No direct script access allowed ');
class User extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->helper('eventlog'); // เพิ่ม eventlog helper
        $this->load->helper('notification_helper'); // เพิ่ม notification helper
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->ia_users_id) ? $this->session->get_userdata()['user_details'][0]->ia_users_id : '1';
        $this->lang->load('user', getSetting('language'));
        $method = strtolower((string) $this->router->fetch_method());
        $open = array(
            'index', 'login', 'logout', 'registration', 'authuser', 'forgetpassword',
            'verifyotp', 'resendotp', 'mailverify', 'reset_password', 'getverificationcode',
            'profile', 'addeditprofile', 'registermail', 'registrationmailverify',
            'chekinvitation', 'registerinvited', 'checemailexist', 'randomstring',
        );
        if (!in_array($method, $open, true)) {
            site_require_global();
        }
    }

    /**
     * This function is redirect to users profile page
     * @return Void
     */
    public function index()
    {
        if (isLogin()) {
            redirect(base_url() . 'home', 'refresh');
        }
    }

    /**
     * This function is used to load login view page
     * @return Void
     */
    public function login()
    {
        if (isset($_SESSION['user_details'])) {
            redirect(base_url() . 'home', 'refresh');
        }

        // auto-login the user if they are remembered
        if (get_cookie('identity') && get_cookie('remember_code')) {
            $recheck = $this->User_model->login_remembered_user();
            if ($recheck) {
                redirect(base_url() . 'home', 'refresh');
            }
        }
        $this->load->view('login');
    }

    /**
     * This function is used to logout user
     * @return Void
     */
    public function logout()
    {
        isLogin();
        
        // Get user data before logout for logging
        $user_details = $this->session->userdata('user_details')[0] ?? null;
        if ($user_details) {
            log_user_logout($user_details->ia_users_id, $user_details->name);
        }

        $this->session->unset_userdata('user_details');

		// delete the remember me cookies if they exist
		if (get_cookie('identity'))
		{
			delete_cookie('identity');
		}
		if (get_cookie('remember_code'))
		{
			delete_cookie('remember_code');
		}

		// Destroy the session
		$this->session->sess_destroy();

        redirect(base_url() . 'login', 'refresh');
    }

    /**
     * This function is used to registr user
     * @return Void
     */
    public function registration()
    {
        if (isset($_SESSION['user_details'])) {
            redirect(base_url() . 'profile', 'refresh');
        }
        //Check if admin allow to registration for user
        if (getSetting('register_allowed') == 1) {
            if ($this->input->post()) {
                $this->addEdit();
                $art_msg['msg'] = lang('successfully_registered');
                $art_msg['type'] = 'success';
                $this->session->set_userdata('alert_msg', $art_msg);
            } else {
                $this->load->view('register');
            }
        } else {
            $art_msg['msg'] = lang('registration_not_allowed');
            $art_msg['type'] = 'warning';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . 'login', 'refresh');
        }
    }

    /**
     * This function is used for user authentication ( Working in login process )
     * @return Void
     */
    public function authUser($page = '')
    {

        $remember = (bool) $this->input->post('remember_me');

        $return = $this->User_model->authUser($remember);
        if (empty($return)) {
            $art_msg['msg'] = lang('invalid_details');
            $art_msg['type'] = 'warning';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . 'login', 'refresh');
        } else {
            if ($return == 'not_varified') {
                $art_msg['msg'] = lang('this_account_is_not_verified_please_contact_to_your_admin');
                $art_msg['type'] = 'danger';
                $this->session->set_userdata('alert_msg', $art_msg);
                redirect(base_url() . 'login', 'refresh');
            } else {
                $this->session->set_userdata('user_details', $return);

                log_user_login(
                    $return[0]->ia_users_id,
                    $return[0]->name,
                    true
                );
            }
            redirect(base_url() . 'home', 'refresh');
        }
    }

    /**
     * This function is used send OTP mail in forget password
     * @return Void
     */
    public function forgetPassword()
    {
        $page['title'] = lang('forgot_password');
        if ($this->input->post()) {
            $email = trim((string) $this->input->post('email'));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->setAlert(lang('this_account_does_not_exist'), 'danger');
                redirect(base_url() . 'forgot_password', 'refresh');
                return;
            }
            $res = $this->User_model->getDataBy('ia_users', $email, 'email', 1);
            if (isset($res[0]->ia_users_id) && $res[0]->ia_users_id != '') {
                $lastSent = (int) $this->session->userdata('reset_otp_last_sent');
                $sessionEmail = $this->session->userdata('reset_otp_email');
                if ($sessionEmail === $email && $lastSent && (time() - $lastSent) < 60) {
                    $this->setAlert(lang('please_wait_before_resend'), 'warning');
                    redirect(base_url() . 'forgot_password/otp', 'refresh');
                    return;
                }

                if ($this->sendPasswordResetOtp($res[0], $email)) {
                    $this->setAlert(lang('to_reset_your_password_otp_has_been_sent_to_your_email'), 'info');
                    redirect(base_url() . 'forgot_password/otp', 'refresh');
                    return;
                }

                $this->setAlert(lang('failed_to_send_email'), 'danger');
                redirect(base_url() . 'forgot_password', 'refresh');
                return;
            }

            $this->setAlert(lang('this_account_does_not_exist'), 'danger');
            redirect(base_url() . 'forgot_password', 'refresh');
            return;
        }

        $this->load->view('forget_password');
    }

    /**
     * Verify the OTP sent to email, then show the set-password form
     * @return Void
     */
    public function verifyOtp()
    {
        $email = $this->session->userdata('reset_otp_email');
        if (empty($email)) {
            redirect(base_url() . 'forgot_password', 'refresh');
            return;
        }

        if ($this->session->userdata('reset_otp_verified')) {
            $this->load->view('set_password', array('email' => $email));
            return;
        }

        if ($this->input->post()) {
            $attempts = (int) $this->session->userdata('reset_otp_attempts');
            if ($attempts >= 5) {
                $this->setAlert(lang('otp_too_many_attempts'), 'danger');
                redirect(base_url() . 'forgot_password/otp', 'refresh');
                return;
            }

            $otp = preg_replace('/\D/', '', (string) $this->input->post('otp'));
            $result = $this->User_model->verifyPasswordResetOtp($email, $otp);
            if ($result === 'ok') {
                $this->User_model->consumePasswordResetOtp($email);
                $this->session->set_userdata('reset_otp_verified', 1);
                $this->session->unset_userdata('reset_otp_attempts');
                $this->setAlert(lang('otp_verified_set_password'), 'success');
                $this->load->view('set_password', array('email' => $email));
                return;
            }

            $this->session->set_userdata('reset_otp_attempts', $attempts + 1);
            if ($result === 'expired') {
                $this->setAlert(lang('otp_expired'), 'danger');
            } else {
                $this->setAlert(lang('invalid_otp'), 'danger');
            }
            redirect(base_url() . 'forgot_password/otp', 'refresh');
            return;
        }

        $this->load->view('verify_otp', array(
            'email' => $email,
            'masked_email' => $this->maskEmail($email),
        ));
    }

    /**
     * Resend password-reset OTP to the email in session
     * @return Void
     */
    public function resendOtp()
    {
        $email = $this->session->userdata('reset_otp_email');
        if (empty($email)) {
            redirect(base_url() . 'forgot_password', 'refresh');
            return;
        }

        $lastSent = (int) $this->session->userdata('reset_otp_last_sent');
        if ($lastSent && (time() - $lastSent) < 60) {
            $this->setAlert(lang('please_wait_before_resend'), 'warning');
            redirect(base_url() . 'forgot_password/otp', 'refresh');
            return;
        }

        $res = $this->User_model->getDataBy('ia_users', $email, 'email', 1);
        if (empty($res) || empty($res[0]->ia_users_id)) {
            $this->setAlert(lang('this_account_does_not_exist'), 'danger');
            redirect(base_url() . 'forgot_password', 'refresh');
            return;
        }

        if ($this->sendPasswordResetOtp($res[0], $email)) {
            $this->session->unset_userdata('reset_otp_attempts');
            $this->session->unset_userdata('reset_otp_verified');
            $this->setAlert(lang('otp_sent_successfully'), 'success');
        } else {
            $this->setAlert(lang('failed_to_send_email'), 'danger');
        }
        redirect(base_url() . 'forgot_password/otp', 'refresh');
    }

    /**
     * Old email-link flow is replaced by OTP
     * @return Void
     */
    public function mailVerify()
    {
        $this->setAlert(lang('otp_link_no_longer_used'), 'info');
        redirect(base_url() . 'forgot_password', 'refresh');
    }

    /**
     * This function is used to reset password in forget password process
     * @return : void
     */
    public function reset_password()
    {
        if (!$this->session->userdata('reset_otp_verified') || !$this->session->userdata('reset_otp_email')) {
            $this->setAlert(lang('invalid_otp'), 'danger');
            redirect(base_url() . 'forgot_password', 'refresh');
            return;
        }

        $return = $this->User_model->ResetPpassword();
        if ($return) {
            $this->clearResetOtpSession();
            $this->setAlert(lang('password_changed_successfully'), 'success');
            redirect(base_url() . 'login', 'refresh');
            return;
        }

        $this->setAlert(lang('unable_to_update_password'), 'danger');
        redirect(base_url() . 'login', 'refresh');
    }

    /**
     * Generate OTP, store it, and email it to the user
     * @return bool
     */
    private function sendPasswordResetOtp($user, $email)
    {
        $otp = $this->generateOtp();
        $expireMinutes = 10;
        $this->User_model->savePasswordResetOtp($user->ia_users_id, $otp, $expireMinutes * 60);

        $setting = settings();
        $data = array(
            'user_name' => $user->name,
            'action_url' => base_url(),
            'sender_name' => isset($setting['company_name']) ? $setting['company_name'] : '',
            'website_name' => isset($setting['website']) ? $setting['website'] : '',
            'otp' => $otp,
            'otp_expire_minutes' => $expireMinutes,
            'varification_link' => '',
            'url_link' => '',
        );
        $template = $this->User_model->getTemplate('forgot_password');
        $body = (isset($template->html) && $template->html != '') ? $template->html : $this->defaultOtpEmailBody();
        foreach ($data as $key => $value) {
            $body = str_replace('{var_' . $key . '}', $value, $body);
        }
        if (strpos($body, (string) $otp) === false) {
            $body .= '<p>OTP: <strong>' . $otp . '</strong></p>';
        }

        $sub = lang('reset_password_otp_subject');
        if ($sub === 'reset_password_otp_subject' || $sub === '') {
            $sub = 'Reset password OTP';
        }

        if (isset($setting['mail_setting']) && $setting['mail_setting'] == 'php_mailer') {
            $this->load->library("send_mail");
            $emm = $this->send_mail->email($sub, $body, $email, $setting);
        } else {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: ' . (isset($setting['EMAIL']) ? $setting['EMAIL'] : '') . "\r\n";
            $emm = mail($email, $sub, $body, $headers);
        }

        if ($emm === true) {
            $this->session->set_userdata('reset_otp_email', $email);
            $this->session->set_userdata('reset_otp_verified', 0);
            $this->session->set_userdata('reset_otp_last_sent', time());
            $this->session->set_userdata('reset_otp_attempts', 0);
            return true;
        }
        return false;
    }

    private function generateOtp()
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function maskEmail($email)
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        $name = $parts[0];
        $visible = max(1, min(2, strlen($name)));
        return substr($name, 0, $visible) . str_repeat('*', max(1, strlen($name) - $visible)) . '@' . $parts[1];
    }

    private function setAlert($msg, $type)
    {
        $this->session->set_userdata('alert_msg', array(
            'msg' => $msg,
            'type' => $type,
        ));
    }

    private function clearResetOtpSession()
    {
        $this->session->unset_userdata('reset_otp_email');
        $this->session->unset_userdata('reset_otp_verified');
        $this->session->unset_userdata('reset_otp_last_sent');
        $this->session->unset_userdata('reset_otp_attempts');
    }

    private function defaultOtpEmailBody()
    {
        return '<p>Hi {var_user_name},</p>
            <p>Your OTP to reset the password for {var_website_name} is:</p>
            <p style="font-size:28px;letter-spacing:6px;font-weight:bold;">{var_otp}</p>
            <p>This code expires in {var_otp_expire_minutes} minutes.</p>
            <p>If you did not request a password reset, please ignore this email.</p>';
    }

    /**
     * This function is generate hash code for random string
     * @return string
     */
    public function getVerificationCode()
    {
        $pw = $this->randomString();
        return $varificat_key = password_hash($pw, PASSWORD_DEFAULT);
    }

    /**
     * This function is used for show users list
     * @return Void
     */
    public function userlist()
    {
        isLogin();
        if (CheckPermission("user", "own_read") || CheckPermission("user", "all_read")) {
            $this->load->view('include/header');
            $this->load->view('user_table');
            $this->load->view('include/footer');
        } else {
            $art_msg['msg'] = lang('you_do_not_have_permission_to_access');
            $art_msg['type'] = 'danger';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . 'profile', 'refresh');
        }
    }

    /**
     * This function is used to create datatable in users list page
     * @return Void
     */
    public function userTable()
    {
        isLogin();
        
        try {
            $page = $this->input->post('start') ? ($this->input->post('start') / $this->input->post('length')) + 1 : 1;
            $pageSize = $this->input->post('length') ?: 10;
            $search = $this->input->post('search')['value'] ?? '';
            
            // Build query
            $this->db->select('ia_users.*, ia_users.ia_users_id as id, ia_users.name, ia_users.email, ia_users.user_type, ia_users.status, ia_users.profile_pic, ia_users.create_date');
            $this->db->from('ia_users');
            $this->db->where('ia_users.is_deleted', 0);
            
            // Apply permissions
            if (CheckPermission("user", "all_read") || $this->session->userdata('user_details')[0]->user_type == 'admin') {
                $this->db->where('ia_users.user_type !=', 'admin');
                $this->db->where('ia_users.ia_users_id !=', $this->user_id);
            } else {
                $this->db->where('ia_users.user_type !=', 'admin');
                $this->db->where('ia_users.user_id', $this->user_id);
            }
            
            // Apply search
            if ($search) {
                $this->db->group_start();
                $this->db->like('ia_users.name', $search);
                $this->db->or_like('ia_users.email', $search);
                $this->db->or_like('ia_users.user_type', $search);
                $this->db->group_end();
            }
            
            // Get total count for pagination
            $totalRecords = $this->db->count_all_results();
            
            // Apply ordering
            $orderColumn = $this->input->post('order')[0]['column'] ?? 1;
            $orderDir = $this->input->post('order')[0]['dir'] ?? 'asc';
            
            $columns = ['ia_users_id', 'name', 'email', 'user_type', 'status', 'create_date'];
            if (isset($columns[$orderColumn])) {
                $this->db->order_by($columns[$orderColumn], $orderDir);
            } else {
                $this->db->order_by('ia_users.name', 'asc');
            }
            
            // Apply pagination
            $offset = ($page - 1) * $pageSize;
            $this->db->limit($pageSize, $offset);
            
            $query = $this->db->get();
            $users = $query->result();
            
            // Format data for DataTable
            $data = [];
            foreach ($users as $user) {
                $data[] = [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->user_type,
                    $user->status,
                    $user->create_date,
                    $user->profile_pic
                ];
            }
            
            echo json_encode([
                'draw' => intval($this->input->post('draw')),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $data
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'draw' => intval($this->input->post('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get user statistics for dashboard
     * @return JSON
     */
    public function get_statistics()
    {
        isLogin();
        
        try {
            // Total users - exclude admin (Super Admin)
            $this->db->select('COUNT(*) as total');
            $this->db->where('is_deleted', '0');
            $this->db->where('user_type !=', 'admin');
            $totalResult = $this->db->get('ia_users')->row();
            $totalUsers = $totalResult ? $totalResult->total : 0;
            
            // Active users - exclude admin (Super Admin)
            $this->db->select('COUNT(*) as total');
            $this->db->where('is_deleted', '0');
            $this->db->where('user_type !=', 'admin');
            $this->db->where('status', 'active');
            $activeResult = $this->db->get('ia_users')->row();
            $activeUsers = $activeResult ? $activeResult->total : 0;
            
            // Pending users - exclude admin (Super Admin)
            $this->db->select('COUNT(*) as total');
            $this->db->where('is_deleted', '0');
            $this->db->where('user_type !=', 'admin');
            $this->db->where('status', 'pending');
            $pendingResult = $this->db->get('ia_users')->row();
            $pendingUsers = $pendingResult ? $pendingResult->total : 0;
            
            // User types count - exclude admin (Super Admin)
            $this->db->select('user_type, COUNT(*) as count');
            $this->db->where('is_deleted', '0');
            $this->db->where('user_type !=', 'admin');
            $this->db->group_by('user_type');
            $userTypes = $this->db->get('ia_users')->result();
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'pending_users' => $pendingUsers,
                    'user_types' => count($userTypes),
                    'user_types_breakdown' => $userTypes
                ]
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get users for custom table
     * @return JSON
     */
    public function get_users()
    {
        isLogin();
        
        try {
            $page = $this->input->post('page') ?: 1;
            $pageSize = $this->input->post('pageSize') ?: 10;
            $userTypeFilter = $this->input->post('user_type_filter');
            $statusFilter = $this->input->post('status_filter');
            $search = $this->input->post('search');
            
            // Build base conditions
            $conditions = ['is_deleted' => '0'];
            
            // Apply permissions - exclude admin (Super Admin) from all queries
            if (CheckPermission("user", "all_read") || $this->session->userdata('user_details')[0]->user_type == 'admin') {
                $conditions['user_type !='] = 'admin';
                $conditions['ia_users_id !='] = $this->user_id;
            } else {
                $conditions['user_type !='] = 'admin';
                $conditions['user_id'] = $this->user_id;
            }
            
            // Apply filters
            if ($userTypeFilter && $userTypeFilter !== 'all') {
                $conditions['user_type'] = $userTypeFilter;
            }
            
            if ($statusFilter && $statusFilter !== 'all') {
                $conditions['status'] = $statusFilter;
            }
            
            // Get total count
            $this->db->where($conditions);
            if ($search) {
                $this->db->group_start();
                $this->db->like('name', $search);
                $this->db->or_like('email', $search);
                $this->db->or_like('user_type', $search);
                $this->db->group_end();
            }
            $totalRecords = $this->db->count_all_results('ia_users');
            
            // Get data
            $this->db->select('ia_users_id as id, name, email, user_type, status, profile_pic, create_date');
            $this->db->from('ia_users');
            $this->db->where($conditions);
            
            if ($search) {
                $this->db->group_start();
                $this->db->like('name', $search);
                $this->db->or_like('email', $search);
                $this->db->or_like('user_type', $search);
                $this->db->group_end();
            }
            
            $this->db->order_by('name', 'asc');
            $this->db->limit($pageSize, ($page - 1) * $pageSize);
            
            $users = $this->db->get()->result();
            $totalPages = ceil($totalRecords / $pageSize);
            
            echo json_encode([
                'success' => true,
                'users' => $users,
                'totalRecords' => $totalRecords,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'pageSize' => $pageSize
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get user types from ia_permission table (excluding admin)
     * @return JSON
     */
    public function get_user_types()
    {
        isLogin();
        
        try {
            $this->db->select('user_type');
            $this->db->from('ia_permission');
            $this->db->where('user_type !=', 'admin');
            $this->db->order_by('user_type', 'asc');
            
            $userTypes = $this->db->get()->result();
            
            echo json_encode([
                'success' => true,
                'user_types' => $userTypes
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * This function is Showing users profile
     * @return Void
     */
    public function profile($id = '')
    {
        isLogin();
        if (!isset($id) || $id == '') {
            $id = $this->session->userdata('user_details')[0]->ia_users_id;
        }
        $data['user_data'] = $this->User_model->getUsers($id);
        $this->load->view('include/header');
        $this->load->view('profile', $data);
        $this->load->view('include/footer');
    }

    /**
     * This function is used to show popup of user to add and update
     * @return Void
     */
    public function getModal()
    {
        isLogin();
        if ($this->input->post('id')) {
            $data['userData'] = getDataByid('ia_users', $this->input->post('id'), 'ia_users_id');
            echo $this->load->view('add_user', $data, true);
        } else {
            echo $this->load->view('add_user', '', true);
        }
        exit;
    }

    /**
     * This function is used to add and update users
     * @return Void
     */
    public function addEdit($id = '')
    {
        $data = $this->input->post();
        $profile_pic = 'user.png';
        if ($this->input->post('ia_users_id')) {
            $id = $this->input->post('ia_users_id');
        }
        if (isset($this->session->userdata('user_details')[0]->ia_users_id)) {
            if ($this->input->post('ia_users_id') == $this->session->userdata('user_details')[0]->ia_users_id) {
                $redirect = 'profile';
                $setSession = true;
            } else {
                $redirect = 'user/userlist';
                $setSession = false;
            }
        } else {
            $redirect = 'login';
        }
        if ($this->input->post('fileOld')) {
            $newname = $this->input->post('fileOld');
            $profile_pic = $newname;
        } else {
            $profile_pic = 'user.png';
        }
        foreach ($_FILES as $name => $fileInfo) {
            if (!empty($_FILES[$name]['name'])) {
                if ($newname = upload($name)) {
                    $data[$name] = $newname;
                    $profile_pic = $newname;
                }
            } else {
                if ($this->input->post('fileOld')) {
                    $newname = $this->input->post('fileOld');
                    $data[$name] = $newname;
                    $profile_pic = $newname;
                } else {
                    $data[$name] = '';
                    $profile_pic = 'user.png';
                }
            }
        }
        if ($id != '') {
            // Get old data before update for comparison
            $old_data = getDataByid('ia_users', $id, 'ia_users_id');
            $old_data_array = (array) $old_data;
            
            $data = $this->input->post();
            if ($this->input->post('status') != '') {
                $data['status'] = $this->input->post('status');
            }
            if ($this->input->post('ia_users_id') == 1) {
                $data['user_type'] = 'admin';
                $data['status'] = 'active';
            }
            $checkValue = $this->User_model->checkExists('ia_users', 'email', $this->input->post('email'), $id, 'ia_users_id');
            if ($checkValue == false) {
                $art_msg['msg'] = lang('this_email_already_registered_with_us');
                $art_msg['type'] = 'info';
                $this->session->set_userdata('alert_msg', $art_msg);
                redirect(base_url() . 'user/userlist', 'refresh');
            }

            if ($this->input->post('password') != '') {
                if ($this->input->post('currentpassword') != '') {
                    $old_row = getDataByid('ia_users', $this->input->post('ia_users_id'), 'ia_users_id');
                    if (password_verify($this->input->post('currentpassword'), $old_row->password)) {
                        if ($this->input->post('password') == $this->input->post('confirmPassword')) {
                            $password = password_hash($this->input->post('password'), PASSWORD_DEFAULT);
                            $data['password'] = $password;
                        } else {
                            $art_msg['msg'] = lang('password_and_confirm_password_should_be_same');
                            $art_msg['type'] = 'warning';
                            $this->session->set_userdata('alert_msg', $art_msg);
                            redirect(base_url() . $redirect, 'refresh');
                        }
                    } else {
                        $art_msg['msg'] = lang('enter_valid_current_password');
                        $art_msg['type'] = 'warning';
                        $this->session->set_userdata('alert_msg', $art_msg);
                        redirect(base_url() . $redirect, 'refresh');
                    }
                } else {
                    $art_msg['msg'] = lang('current_password_is_required');
                    $art_msg['type'] = 'warning';
                    $this->session->set_userdata('alert_msg', $art_msg);
                    redirect(base_url() . $redirect, 'refresh');
                }
            }
            $id = $this->input->post('ia_users_id');
            unset($data['fileOld']);
            unset($data['currentpassword']);
            unset($data['confirmPassword']);
            unset($data['ia_users_id']);
            if (isset($data['edit'])) {
                unset($data['edit']);
            }
            if (isset($data['password']) && $data['password'] == '') {
                unset($data['password']);
            }
            if (isset($data['mkacf'])) {
                $custom_fields = $data['mkacf'];
                unset($data['mkacf']);
                if (!empty($custom_fields)) {
                    foreach ($custom_fields as $cfkey => $cfvalue) {
                        $qr = "SELECT * FROM ia_custom_fields_values WHERE rel_crud_id = '" . $id . "' AND cf_id = '" . $cfkey . "'";
                        $cf_data = $this->User_model->getQrResult($qr);
                        if (is_array($cf_data) && !empty($cf_data)) {
                            $d = array(
                                "value" => $custom_fields[$cf_data[0]->cf_id],
                            );
                            $this->User_model->updateRow('ia_custom_fields_values', 'ia_custom_fields_values_id', $cf_data[0]->ia_custom_fields_values_id, $d);
                        } else {
                            $d = array(
                                "rel_crud_id" => $id,
                                "cf_id" => $cfkey,
                                "curd" => 'user',
                                "value" => $cfvalue,
                            );
                            $this->User_model->insertRow('ia_custom_fields_values', $d);
                        }
                    }
                }
            }
            $data['profile_pic'] = $profile_pic;
            foreach ($data as $dkey => $dvalue) {
                if (is_array($dvalue)) {
                    $data[$dkey] = implode(',', $dvalue);
                }
            }
            $this->User_model->updateRow('ia_users', 'ia_users_id', $id, $data);
            $new_data = $this->User_model->getDataBy('ia_users', $id, 'ia_users_id');
            if (isset($setSession) && $setSession == true) {
                $this->session->set_userdata('user_details', $new_data);
            }

            // Log user update event with before/after comparison
            $new_data_array = (array) $new_data[0];
            
            // Determine entity type and message based on redirect
            if ($redirect == 'profile') {
                $entity_type = 'user profile';
                $message = "Updated profile: {$new_data[0]->name} ({$new_data[0]->email})";
            } else {
                $entity_type = 'user';
                $message = "Updated user: {$new_data[0]->name} ({$new_data[0]->email})";
            }
            
            log_record_updated(
                'User',
                $id,
                $new_data[0]->name,
                $old_data_array,
                $new_data_array
            );

            $art_msg['msg'] = lang('your_data_updated_successfully');
            $art_msg['type'] = 'success';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . $redirect, 'refresh');
        } else {
            if ($this->input->post('user_type') != 'admin') {
                $data = $this->input->post();
                $password = password_hash($this->input->post('password'), PASSWORD_DEFAULT);
                $checkValue = $this->User_model->checkExists('ia_users', 'email', $this->input->post('email'));
                if ($checkValue == false) {
                    $art_msg['msg'] = lang('this_email_already_registered_with_us');
                    $art_msg['type'] = 'warning';
                    $this->session->set_userdata('alert_msg', $art_msg);
                    redirect(base_url() . 'user/userlist', 'refresh');
                }

                $data['status'] = 'active';
                if (getSetting('admin_approval') == 1) {
                    $data['status'] = 'pending';
                }

                if ($this->input->post('status') != '') {
                    $data['status'] = $this->input->post('status');
                }

                $data['user_id'] = $this->user_id;
                $data['password'] = $password;
                $data['profile_pic'] = $profile_pic;
                $data['is_deleted'] = 0;
                $data['create_date'] = date('Y-m-d');
                if (isset($data['password_confirmation'])) {
                    unset($data['password_confirmation']);
                }
                if (isset($data['call_from'])) {
                    unset($data['call_from']);
                }
                unset($data['submit']);
                $custom_fields = array();
                if (isset($data['mkacf'])) {
                    $custom_fields = $data['mkacf'];
                    unset($data['mkacf']);
                }

                foreach ($data as $dkey => $dvalue) {
                    if (is_array($dvalue)) {
                        $data[$dkey] = implode(',', $dvalue);
                    }
                }
                if ($redirect == 'login') {
                    $data['status'] = 'pending';
                    $var_key = $this->getVerificationCode();
                    $data['var_key'] = $var_key;
                }
                $last_id = $this->User_model->insertRow('ia_users', $data);
                if (!empty($custom_fields)) {
                    foreach ($custom_fields as $cfkey => $cfvalue) {
                        $d = array(
                            "rel_crud_id" => $last_id,
                            "cf_id" => $cfkey,
                            "curd" => 'user',
                            "value" => $cfvalue,
                        );
                        $this->User_model->insertRow('ia_custom_fields_values', $d);
                    }
                }

                // Log user creation event
                log_record_created(
                    'User',
                    $last_id,
                    $data['name'],
                    $data
                );

                if ($redirect == 'login') {
                    $this->registerMail($data);
                }
                redirect(base_url() . $redirect, 'refresh');
            } else {
                $art_msg['msg'] = lang('you_do_not_have_permission_to_access');
                $art_msg['type'] = 'danger';
                $this->session->set_userdata('alert_msg', $art_msg);
                redirect(base_url() . 'user/registration', 'refresh');
            }
        }
    }


    public function addEditProfile($id = '')
    {
        $data = $this->input->post();
        $profile_pic = 'user.png';
        if ($this->input->post('ia_users_id')) {
            $id = $this->input->post('ia_users_id');
        }
        if (isset($this->session->userdata('user_details')[0]->ia_users_id)) {
            if ($this->input->post('ia_users_id') == $this->session->userdata('user_details')[0]->ia_users_id) {
                $redirect = 'profile';
                $setSession = true;
            } else {
                $redirect = 'user/userlist';
                $setSession = false;
            }
        } else {
            $redirect = 'login';
        }
        if ($this->input->post('fileOld')) {
            $newname = $this->input->post('fileOld');
            $profile_pic = $newname;
        } else {
            $profile_pic = 'user.png';
        }
        foreach ($_FILES as $name => $fileInfo) {
            if (!empty($_FILES[$name]['name'])) {
                if ($newname = upload($name)) {
                    $data[$name] = $newname;
                    $profile_pic = $newname;
                }
            } else {
                if ($this->input->post('fileOld')) {
                    $newname = $this->input->post('fileOld');
                    $data[$name] = $newname;
                    $profile_pic = $newname;
                } else {
                    $data[$name] = '';
                    $profile_pic = 'user.png';
                }
            }
        }
        if ($id != '') {
            // Get old data before update for comparison
            $old_data = getDataByid('ia_users', $id, 'ia_users_id');
            $old_data_array = (array) $old_data;
            
            $data = $this->input->post();
            if ($this->input->post('status') != '') {
                $data['status'] = $this->input->post('status');
            }
            if ($this->input->post('ia_users_id') == 1) {
                $data['user_type'] = 'admin';
                $data['status'] = 'active';
            }
            $checkValue = $this->User_model->checkExists('ia_users', 'email', $this->input->post('email'), $id, 'ia_users_id');
            if ($checkValue == false) {
                $art_msg['msg'] = lang('this_email_already_registered_with_us');
                $art_msg['type'] = 'info';
                $this->session->set_userdata('alert_msg', $art_msg);
                redirect(base_url() . 'user/userlist', 'refresh');
            }

            if ($this->input->post('password') != '') {
                if ($this->input->post('currentpassword') != '') {
                    $old_row = getDataByid('ia_users', $this->input->post('ia_users_id'), 'ia_users_id');
                    if (password_verify($this->input->post('currentpassword'), $old_row->password)) {
                        if ($this->input->post('password') == $this->input->post('confirmPassword')) {
                            $password = password_hash($this->input->post('password'), PASSWORD_DEFAULT);
                            $data['password'] = $password;
                        } else {
                            $art_msg['msg'] = lang('password_and_confirm_password_should_be_same');
                            $art_msg['type'] = 'warning';
                            $this->session->set_userdata('alert_msg', $art_msg);
                            redirect(base_url() . $redirect, 'refresh');
                        }
                    } else {
                        $art_msg['msg'] = lang('enter_valid_current_password');
                        $art_msg['type'] = 'warning';
                        $this->session->set_userdata('alert_msg', $art_msg);
                        redirect(base_url() . $redirect, 'refresh');
                    }
                } else {
                    $art_msg['msg'] = lang('current_password_is_required');
                    $art_msg['type'] = 'warning';
                    $this->session->set_userdata('alert_msg', $art_msg);
                    redirect(base_url() . $redirect, 'refresh');
                }
            }
            $id = $this->input->post('ia_users_id');
            unset($data['fileOld']);
            unset($data['currentpassword']);
            unset($data['confirmPassword']);
            unset($data['ia_users_id']);
            if (isset($data['edit'])) {
                unset($data['edit']);
            }
            if (isset($data['password']) && $data['password'] == '') {
                unset($data['password']);
            }
            if (isset($data['mkacf'])) {
                $custom_fields = $data['mkacf'];
                unset($data['mkacf']);
                if (!empty($custom_fields)) {
                    foreach ($custom_fields as $cfkey => $cfvalue) {
                        $qr = "SELECT * FROM ia_custom_fields_values WHERE rel_crud_id = '" . $id . "' AND cf_id = '" . $cfkey . "'";
                        $cf_data = $this->User_model->getQrResult($qr);
                        if (is_array($cf_data) && !empty($cf_data)) {
                            $d = array(
                                "value" => $custom_fields[$cf_data[0]->cf_id],
                            );
                            $this->User_model->updateRow('ia_custom_fields_values', 'ia_custom_fields_values_id', $cf_data[0]->ia_custom_fields_values_id, $d);
                        } else {
                            $d = array(
                                "rel_crud_id" => $id,
                                "cf_id" => $cfkey,
                                "curd" => 'user',
                                "value" => $cfvalue,
                            );
                            $this->User_model->insertRow('ia_custom_fields_values', $d);
                        }
                    }
                }
            }
            $data['profile_pic'] = $profile_pic;
            foreach ($data as $dkey => $dvalue) {
                if (is_array($dvalue)) {
                    $data[$dkey] = implode(',', $dvalue);
                }
            }
            $this->User_model->updateRow('ia_users', 'ia_users_id', $id, $data);
            $new_data = $this->User_model->getDataBy('ia_users', $id, 'ia_users_id');
            if (isset($setSession) && $setSession == true) {
                $this->session->set_userdata('user_details', $new_data);
            }

            // Log user update event with before/after comparison
            $new_data_array = (array) $new_data[0];
            
            // Determine entity type and message based on redirect
            if ($redirect == 'profile') {
                $entity_type = 'user profile';
                $message = "Updated profile: {$new_data[0]->name} ({$new_data[0]->email})";
            } else {
                $entity_type = 'user';
                $message = "Updated user: {$new_data[0]->name} ({$new_data[0]->email})";
            }
            
            log_record_updated(
                'User Profile',
                $id,
                $new_data[0]->name,
                $old_data_array,
                $new_data_array
            );

            $art_msg['msg'] = lang('your_data_updated_successfully');
            $art_msg['type'] = 'success';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . $redirect, 'refresh');
        } else {
            if ($this->input->post('user_type') != 'admin') {
                $data = $this->input->post();
                $password = password_hash($this->input->post('password'), PASSWORD_DEFAULT);
                $checkValue = $this->User_model->checkExists('ia_users', 'email', $this->input->post('email'));
                if ($checkValue == false) {
                    $art_msg['msg'] = lang('this_email_already_registered_with_us');
                    $art_msg['type'] = 'warning';
                    $this->session->set_userdata('alert_msg', $art_msg);
                    redirect(base_url() . 'user/userlist', 'refresh');
                }

                $data['status'] = 'active';
                if (getSetting('admin_approval') == 1) {
                    $data['status'] = 'pending';
                }

                if ($this->input->post('status') != '') {
                    $data['status'] = $this->input->post('status');
                }

                $data['user_id'] = $this->user_id;
                $data['password'] = $password;
                $data['profile_pic'] = $profile_pic;
                $data['is_deleted'] = 0;
                $data['create_date'] = date('Y-m-d');
                if (isset($data['password_confirmation'])) {
                    unset($data['password_confirmation']);
                }
                if (isset($data['call_from'])) {
                    unset($data['call_from']);
                }
                unset($data['submit']);
                $custom_fields = array();
                if (isset($data['mkacf'])) {
                    $custom_fields = $data['mkacf'];
                    unset($data['mkacf']);
                }

                foreach ($data as $dkey => $dvalue) {
                    if (is_array($dvalue)) {
                        $data[$dkey] = implode(',', $dvalue);
                    }
                }
                if ($redirect == 'login') {
                    $data['status'] = 'pending';
                    $var_key = $this->getVerificationCode();
                    $data['var_key'] = $var_key;
                }
                $last_id = $this->User_model->insertRow('ia_users', $data);
                if (!empty($custom_fields)) {
                    foreach ($custom_fields as $cfkey => $cfvalue) {
                        $d = array(
                            "rel_crud_id" => $last_id,
                            "cf_id" => $cfkey,
                            "curd" => 'user',
                            "value" => $cfvalue,
                        );
                        $this->User_model->insertRow('ia_custom_fields_values', $d);
                    }
                }

                // Log user creation event
                log_record_created(
                    'User Profile',
                    $last_id,
                    $data['name'],
                    $data
                );

                if ($redirect == 'login') {
                    $this->registerMail($data);
                }
                redirect(base_url() . $redirect, 'refresh');
            } else {
                $art_msg['msg'] = lang('you_do_not_have_permission_to_access');
                $art_msg['type'] = 'danger';
                $this->session->set_userdata('alert_msg', $art_msg);
                redirect(base_url() . 'user/registration', 'refresh');
            }
        }
    }

    /**
     * This function is used to send verification mail for registeration of ia_users
     * @return Void
     */

    public function registerMail($data1)
    {
        $setting = settings();
        $res = $this->User_model->getDataBy('ia_users', $data1['email'], 'email', 1);
        if (isset($res[0]->ia_users_id) && $res[0]->ia_users_id != '') {
            $sub = "Varify your account";
            $email = $data1['email'];

            $data = array(
                'user_name' => $res[0]->name,
                'varification_link' => base_url() . 'user/registrationMailVerify?code=' . $data1['var_key'],
                'website_name' => $setting['website'],
            );
            $body = $this->User_model->getTemplate('registration');
            $body = $body->html;
            foreach ($data as $key => $value) {
                $body = str_replace('{var_' . $key . '}', $value, $body);
            }

            if ($setting['mail_setting'] == 'php_mailer') {
                $this->load->library("send_mail");
                $emm = $this->send_mail->email($sub, $body, $email, $setting);
            } else {
                // content-type is required when sending HTML email
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= 'From: ' . $setting['EMAIL'] . "\r\n";
                $emm = mail($email, $sub, $body, $headers);
            }
            if ($emm) {
                $art_msg['msg'] = lang('successfully_registered_check_your_mail_for_varification');
                $art_msg['type'] = 'success';
                $this->session->set_userdata('alert_msg', $art_msg);
                redirect(base_url() . 'login', 'refresh');
            }
        }
    }

    public function registrationMailVerify()
    {
        $return = $this->User_model->registrationMailVerify();
        if ($return) {
            $art_msg['msg'] = lang('successfully_verified') . '!';
            $art_msg['type'] = 'success';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . 'login', 'refresh');
        } else {
            $art_msg['msg'] = lang('invalid_link');
            $art_msg['type'] = 'danger';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . 'user/registration', 'refresh');
        }
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
            // Get user data before deletion for logging
            $user_data = getDataByid('ia_users', $id, 'ia_users_id');
            
            if ($user_data) {
                // Log user deletion event
                log_record_deleted(
                    'User',
                    $id,
                    $user_data->name,
                    [
                        'name' => $user_data->name,
                        'email' => $user_data->email,
                        'user_type' => $user_data->user_type,
                        'status' => $user_data->status
                    ]
                );
            }
            
            $this->User_model->delete($id);
        }
        redirect(base_url() . 'user/userlist', 'refresh');
    }

    /**
     * This function is used to send invitation mail to users for registration
     * @return Void
     */
    public function InvitePeople()
    {
        isLogin();
        if ($this->input->post('emails')) {
            $setting = settings();
            $var_key = $this->randomString();
            $emailArray = explode(',', $this->input->post('emails'));
            $emailArray = array_map('trim', $emailArray);
            $body = $this->User_model->getTemplate('invitation');
            $result['existCount'] = 0;
            $result['seccessCount'] = 0;
            $result['invalidEmailCount'] = 0;
            $result['noTemplate'] = 0;
            if (isset($body->html) && $body->html != '') {
                $body = $body->html;
                foreach ($emailArray as $mailKey => $mailValue) {
                    if (filter_var($mailValue, FILTER_VALIDATE_EMAIL)) {
                        $res = $this->User_model->getDataBy('ia_users', $mailValue, 'email');
                        if (is_array($res) && empty($res)) {
                            $link = (string) '<a href="' . base_url() . 'user/registration?invited=' . $var_key . '">Click here</a>';
                            $data = array('var_user_email' => $mailValue, 'var_inviation_link' => $link);
                            foreach ($data as $key => $value) {
                                $body = str_replace('{' . $key . '}', $value, $body);
                            }
                            if ($setting['mail_setting'] == 'php_mailer') {
                                $this->load->library("send_mail");
                                $emm = $this->send_mail->email('Invitation for registration', $body, $mailValue, $setting);
                            } else {
                                // content-type is required when sending HTML email
                                $headers = "MIME-Version: 1.0" . "\r\n";
                                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                                $headers .= 'From: ' . $setting['EMAIL'] . "\r\n";
                                $emm = mail($mailValue, 'Invitation for registration', $body, $headers);
                            }
                            if ($emm) {
                                $darr = array('email' => $mailValue, 'var_key' => $var_key);
                                $this->User_model->insertRow('ia_users', $darr);
                                $result['seccessCount'] += 1;
                            }
                        } else {
                            $result['existCount'] += 1;
                        }
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

    /**
     * This function is used to Check invitation code for user registration
     * @return TRUE/FALSE
     */
    public function chekInvitation()
    {
        if ($this->input->post('code') && $this->input->post('code') != '') {
            $res = $this->User_model->getDataBy('ia_users', $this->input->post('code'), 'var_key');
            $result = array();
            if (is_array($res) && !empty($res)) {
                $result['email'] = $res[0]->email;
                $result['ia_users_id'] = $res[0]->ia_users_id;
                $result['result'] = 'success';
            } else {
                $art_msg['msg'] = lang('this_code_is_not_valid');
                $art_msg['type'] = 'warning';
                $this->session->set_userdata('alert_msg', $art_msg);
                $result['result'] = 'error';
            }
        }
        echo json_encode($result);
        exit;
    }

    /**
     * This function is used to registr invited user
     * @return Void
     */
    public function registerInvited($id)
    {
        $data = $this->input->post();
        $password = password_hash($this->input->post('password'), PASSWORD_DEFAULT);
        $data['password'] = $password;
        $data['var_key'] = null;
        $data['is_deleted'] = 0;
        $data['status'] = 'active';
        $data['user_id'] = 1;
        if (isset($data['password_confirmation'])) {
            unset($data['password_confirmation']);
        }
        if (isset($data['call_from'])) {
            unset($data['call_from']);
        }
        if (isset($data['submit'])) {
            unset($data['submit']);
        }
        $this->User_model->updateRow('ia_users', 'ia_users_id', $id, $data);
        $art_msg['msg'] = lang('successfully_registered');
        $art_msg['type'] = 'success';
        $this->session->set_userdata('alert_msg', $art_msg);
        redirect(base_url() . 'login', 'refresh');
    }

    /**
     * This function is used to check email is alredy exist or not
     * @return TRUE/FALSE
     */
    public function checEmailExist()
    {
        $result = 1;
        $res = $this->User_model->getDataBy('ia_users', $this->input->post('email'), 'email');
        if (!empty($res)) {
            if ($res[0]->ia_users_id != $this->input->post('uId')) {
                $result = 0;
            }
        }
        echo $result;
        exit;
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

}
