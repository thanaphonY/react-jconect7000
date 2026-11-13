<?php
class User_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('cookie');
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->ia_users_id) ? $this->session->get_userdata()['user_details'][0]->ia_users_id : '1';
    }

    /**
     * This function is used authenticate user at login
     */
    public function authUser($remember = FALSE)
    {
        $email = $this->input->post('email');
        $password = $this->input->post('password');
        $this->db->where("is_deleted='0' AND (name='$email' OR email='$email')");
        $result = $this->db->get('ia_users')->result();

        if (!empty($result)) {
            if (password_verify($password, $result[0]->password)) {
                if ($remember) {
                    $this->remember_user($result[0]->ia_users_id);
                }
                return $result;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * user
     *
     * @param int|string|null $id
     *
     * @return static
     * @author Ben Edmunds
     */
    public function user($id = NULL)
    {
        $this->db->from('ia_users');
        $this->db->limit(1);
        $this->db->where('ia_users_id', $id);
        $response = $this->db->get();
        return $response;
    }
    /**
     * remember_user
     *
     * @param int|string $id
     *
     * @return bool
     * @author Ben Edmunds
     */
    public function remember_user($id)
    {
        if (!$id) {
            return FALSE;
        }
        $user = $this->user($id)->row();
        $salt =  $this->salt();
        $this->db->update('ia_users', array('remember_code' => $salt), array('ia_users_id' => $id));
        if ($this->db->affected_rows() > -1) {
            $expire = (60 * 60 * 24 * 365 * 2);
            set_cookie(array(
                'name'   => 'identity',
                'value'  => $user->ia_users_id,
                'expire' => $expire
            ));

            set_cookie(array(
                'name'   => 'remember_code',
                'value'  => $salt,
                'expire' => $expire
            ));

            return TRUE;
        }
        return FALSE;
    }

    /**
     * This function is used to delete user
     * @param: $id - id of user table
     */
    public function delete($id = '')
    {
        $this->db->where('ia_users_id', $id);
        $this->db->delete('ia_users');
    }

    /**
     * This function is used to load view of reset password and varify user too
     */
    public function mailVerify()
    {
        $ucode = $this->input->get('code');
        $this->db->select('email as e_mail');
        $this->db->from('ia_users');
        $this->db->where('var_key', $ucode);
        $query = $this->db->get();
        $result = $query->row();
        if (!empty($result->e_mail)) {
            return $result->e_mail;
        } else {
            return false;
        }
    }

    /**
     * Store a hashed password-reset OTP against the user.
     */
    public function savePasswordResetOtp($userId, $otp, $ttlSeconds = 600)
    {
        $payload = json_encode(array(
            't' => 'otp',
            'h' => password_hash((string) $otp, PASSWORD_DEFAULT),
            'e' => time() + (int) $ttlSeconds,
        ));
        return $this->updateRow('ia_users', 'ia_users_id', $userId, array('var_key' => $payload));
    }

    /**
     * Verify a password-reset OTP for the given email.
     * @return string ok|invalid|expired
     */
    public function verifyPasswordResetOtp($email, $otp)
    {
        $users = $this->getDataBy('ia_users', $email, 'email');
        if (empty($users) || empty($users[0]->var_key)) {
            return 'invalid';
        }
        $payload = json_decode($users[0]->var_key, true);
        if (!is_array($payload) || empty($payload['t']) || $payload['t'] !== 'otp') {
            return 'invalid';
        }
        if (empty($payload['e']) || time() > (int) $payload['e']) {
            return 'expired';
        }
        if (empty($payload['h']) || !password_verify((string) $otp, $payload['h'])) {
            return 'invalid';
        }
        return 'ok';
    }

    /**
     * Invalidate a used password-reset OTP after successful verification.
     */
    public function consumePasswordResetOtp($email)
    {
        return $this->updateRow('ia_users', 'email', $email, array(
            'var_key' => json_encode(array(
                't' => 'otp_ok',
                'e' => time() + 600,
            )),
        ));
    }

    /**
     * This function is used Reset password
     */
    public function ResetPpassword()
    {
        $email = $this->session->userdata('reset_otp_email');
        $verified = $this->session->userdata('reset_otp_verified');
        if (empty($email) || empty($verified)) {
            return false;
        }
        $password = $this->input->post('password');
        $confirm = $this->input->post('password_confirmation');
        if ($password === '' || $password === null || $password !== $confirm) {
            return false;
        }
        $data = array(
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'var_key' => '',
        );
        $this->db->where('email', $email);
        return $this->db->update('ia_users', $data);
    }

    /**
     * This function is used to select data form table
     */
    public function getDataBy($tableName = '', $value = '', $colum = '', $condition = '')
    {
        if ((!empty($value)) && (!empty($colum))) {
            $this->db->where($colum, $value);
        }
        $this->db->select('*');
        $this->db->from($tableName);
        $query = $this->db->get();
        return $query->result();
    }

/************
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 *     public function getWhereIn($tableName = '', $value = '')
    {
        if ((!empty($value)) && (!empty($colum))) {
            $this->db->where($colum, $value);
        }
        $this->db->select('*');
        $this->db->from($tableName);
        $query = $this->db->get();
        return $query->result();
    }

*/

    /**
     * This function is used to check user is alredy exist or not
     */
    public function checkExists($table = '', $colom = '', $colomValue = '', $id = '', $id_CheckCol = '')
    {
        $this->db->where($colom, $colomValue);
        if (!empty($id) && !empty($id_CheckCol)) {
            $this->db->where($id_CheckCol . ' !=', $id);
        }
        $res = $this->db->get($table)->row();
        if (!empty($res)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * This function is used to get users detail
     */
    public function getUsers($userID = '')
    {
        $this->db->where('is_deleted', '0');
        if (isset($userID) && $userID != '') {
            $this->db->where('ia_users_id', $userID);
        } else if ($this->session->userdata('user_details')[0]->user_type == 'admin') {
            $this->db->where('user_type', 'admin');
        } else {
            $this->db->where('ia_users.ia_users_id !=', '1');
        }
        $result = $this->db->get('ia_users')->result();
        return $result;
    }

    /**
     * This function is used to get email template
     */
    public function getTemplate($code)
    {
        $this->db->where('code', $code);
        return $this->db->get('ia_email_templates')->row();
    }

    /**
     * This function is used to Insert record in table
     */
    public function insertRow($table, $data)
    {
        $this->db->insert($table, $data);
        return $this->db->insert_id();
    }

    /**
     * This function is used to Update record in table
     */
    public function updateRow($table, $col, $colVal, $data)
    {
        $this->db->where($col, $colVal);
        $this->db->update($table, $data);
        return true;
    }

    /*      public function get_list_box_data($qr) {
$exe = $this->db->query($qr);
return $exe->result();
}
 */
    public function getQrResult($qr)
    {
        // echo"<pre>"; print_r($qr); die;
        $exe = $this->db->query($qr);
        return $exe->result();
    }

    public function getBarChartData($qr)
    {
        $exe = $this->db->query($qr);
        $res = $exe->result();
        $result = [];
        $i = 1;
        while ($i <= 12) {
            $result[$i] = 0;
            foreach ($res as $key => $value) {
                if ($value->months == $i) {
                    //$result[$i] += $value->mka_sum;
                    $result[$i] += (int) str_replace(',', '', $value->mka_sum);
                }
            }
            $i++;
        }
        return implode(',', $result);
    }

    public function registrationMailVerify()
    {
        $ucode = $this->input->get('code');
        if ($ucode == '') {
            return false;
        }
        $this->db->select('email as e_mail');
        $this->db->from('ia_users');
        $this->db->where('var_key', $ucode);
        $query = $this->db->get();
        $result = $query->row();
        if (!empty($result->e_mail)) {
            $data['var_key'] = '';
            $data['status'] = 'active';
            if (getSetting('admin_approval') == 1) {
                $data['status'] = 'varified';
            }
            $this->db->update('ia_users', $data, "email = '$result->e_mail'");
            return $result->e_mail;
        } else {
            return false;
        }
    }

    /**
     * This function is used to get  device
     */
    public function getUserDevice($userID = '')
    {
        /*
        $this->db->where('users_id', $userID);
        $result = $this->db->get('devices')->result();
        return $result;
*/

        $this->db->select('*');
        $this->db->from('devices');
        $this->db->join('heartbeat', 'heartbeat.device_id = devices.serial', 'left');
        $result  = $this->db->get()->result();
        return $result;
    }

    /**
     * This function is used to get  device
     */
    public function getDeviceAll()
    {
        $this->db->select('*');
        $this->db->from('devices');
        $this->db->join('heartbeat', 'heartbeat.device_id = devices.serial', 'left');
        $result  = $this->db->get()->result();
        return $result;
    }
    /**
     * This function is used to deleteDevice
     */
    public function deleteDevice($id = '')
    {
        $this->db->where('id', $id);
        $this->db->delete('devices');
    }

    /**
     * Generates a random salt value.
     *
     * Salt generation code taken from https://github.com/ircmaxell/password_compat/blob/master/lib/password.php
     *
     * @return bool|string
     * @author Anthony Ferrera
     */
    public function salt()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 20; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    /**
     * Identity check
     *
     * @return bool
     * @author Mathew
     */
    public function identity_check($identity = '')
    {

        if (empty($identity)) {
            return FALSE;
        }

        return $this->db->where('ia_users_id', $identity)
            ->limit(1)
            ->count_all_results('ia_users') > 0;
    }
    /**
     * login_remembed_user
     *
     * @return bool
     * @author Ben Edmunds
     */
    public function login_remembered_user()
    {
        // check for valid data
        if (
            !get_cookie('identity')
            || !get_cookie('remember_code')
            || !$this->identity_check(get_cookie('identity'))
        ) {
            return FALSE;
        }
        $uid = urldecode(get_cookie('identity'));
        $ucode = get_cookie('remember_code');
        $this->db->where("is_deleted='0' AND ia_users_id='$uid' AND remember_code='$ucode' ");
        $result = $this->db->get('ia_users')->result();

        // if the user was found, sign them in
        if (!empty($result)) {
            $this->set_session($result);
            $this->remember_user($result[0]->ia_users_id);
            return TRUE;
        }

        return FALSE;
    }
    /**
     * set_session
     *
     * @param object $user
     *
     * @return bool
     * @author jrmadsen67
     */
    public function set_session($user)
    {
        $this->session->set_userdata('user_details', $user);
        return TRUE;
    }
}
