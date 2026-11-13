<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Notification Helper
 * 
 * Provides functions for managing session-based alert messages
 * that persist across redirects
 */

/**
 * Set alert message in session
 * 
 * @param string $message - Alert message
 * @param string $type - Alert type (success, danger, warning, info)
 * @param string $redirect_url - URL to redirect to (optional)
 */
function set_alert_message($message, $type = 'success', $redirect_url = null)
{
    $CI =& get_instance();
    
    $alert_msg = [
        'msg' => $message,
        'type' => $type
    ];
    
    $CI->session->set_userdata('alert_msg', $alert_msg);
    
    if ($redirect_url) {
        redirect($redirect_url, 'refresh');
    }
}

/**
 * Get alert message from session and clear it
 * 
 * @return array|null - Alert message array or null if not exists
 */
function get_alert_message()
{
    $CI =& get_instance();
    
    $alert_msg = $CI->session->userdata('alert_msg');
    
    if ($alert_msg) {
        $CI->session->unset_userdata('alert_msg');
        return $alert_msg;
    }
    
    return null;
}

/**
 * Check if alert message exists in session
 * 
 * @return bool - True if alert message exists
 */
function has_alert_message()
{
    $CI =& get_instance();
    return $CI->session->userdata('alert_msg') !== null;
}

/**
 * Set success alert message
 * 
 * @param string $message - Success message
 * @param string $redirect_url - URL to redirect to (optional)
 */
function set_success_message($message, $redirect_url = null)
{
    set_alert_message($message, 'success', $redirect_url);
}

/**
 * Set error alert message
 * 
 * @param string $message - Error message
 * @param string $redirect_url - URL to redirect to (optional)
 */
function set_error_message($message, $redirect_url = null)
{
    set_alert_message($message, 'danger', $redirect_url);
}

/**
 * Set warning alert message
 * 
 * @param string $message - Warning message
 * @param string $redirect_url - URL to redirect to (optional)
 */
function set_warning_message($message, $redirect_url = null)
{
    set_alert_message($message, 'warning', $redirect_url);
}

/**
 * Set info alert message
 * 
 * @param string $message - Info message
 * @param string $redirect_url - URL to redirect to (optional)
 */
function set_info_message($message, $redirect_url = null)
{
    set_alert_message($message, 'info', $redirect_url);
}

/**
 * Flash alert message (set and redirect)
 * 
 * @param string $message - Alert message
 * @param string $type - Alert type
 * @param string $redirect_url - URL to redirect to
 */
function flash_alert_message($message, $type, $redirect_url)
{
    set_alert_message($message, $type, $redirect_url);
}

/**
 * Flash success message
 * 
 * @param string $message - Success message
 * @param string $redirect_url - URL to redirect to
 */
function flash_success_message($message, $redirect_url)
{
    flash_alert_message($message, 'success', $redirect_url);
}

/**
 * Flash error message
 * 
 * @param string $message - Error message
 * @param string $redirect_url - URL to redirect to
 */
function flash_error_message($message, $redirect_url)
{
    flash_alert_message($message, 'danger', $redirect_url);
}

/**
 * Flash warning message
 * 
 * @param string $message - Warning message
 * @param string $redirect_url - URL to redirect to
 */
function flash_warning_message($message, $redirect_url)
{
    flash_alert_message($message, 'warning', $redirect_url);
}

/**
 * Flash info message
 * 
 * @param string $message - Info message
 * @param string $redirect_url - URL to redirect to
 */
function flash_info_message($message, $redirect_url)
{
    flash_alert_message($message, 'info', $redirect_url);
} 