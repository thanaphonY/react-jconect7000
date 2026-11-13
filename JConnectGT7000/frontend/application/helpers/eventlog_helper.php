<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Event Log Helper
 * 
 * Helper functions for automatic event logging across modules
 */

/**
 * Mask password fields in data array
 * @param array $data - data array
 * @return array - data with masked passwords
 */
function mask_password_fields($data)
{
    $password_fields = ['password', 'confirm_password', 'new_password', 'old_password', 'currentpassword', 'confirmPassword'];
    
    if (!is_array($data)) {
        return $data;
    }
    
    foreach ($data as $key => $value) {
        if (in_array(strtolower($key), $password_fields) && !empty($value)) {
            $data[$key] = '***';
        } elseif (is_array($value)) {
            $data[$key] = mask_password_fields($value);
        }
    }
    
    return $data;
}

/**
 * Insert event log with password masking
 * 
 * @param string $action_type - Type of action (create, update, delete, etc.)
 * @param string $entity_type - Type of entity (user, device, notification, etc.)
 * @param int $entity_id - ID of the entity
 * @param string $message - Log message
 * @param array $metadata - Additional metadata (passwords will be masked)
 * @return int|false - Log ID on success, false on failure
 */
function insert_event_log($action_type, $entity_type, $entity_id, $message, $metadata = [])
{
    $CI =& get_instance();
    
    // Get current user info
    $userDetails = $CI->session->get_userdata()['user_details'][0] ?? null;
    $user_id = $userDetails ? $userDetails->ia_users_id : 0;
    
    // Mask password fields in metadata
    $metadata = mask_password_fields($metadata);
    
    // Add common metadata
    $metadata['ip_address'] = $CI->input->ip_address();
    $metadata['user_agent'] = $CI->input->user_agent();
    $metadata['timestamp'] = date('Y-m-d H:i:s');
    
    // Load model if not already loaded
    if (!isset($CI->Eventlogs_model)) {
        $CI->load->model('Eventlogs_model');
    }
    
    try {
        $log_id = $CI->Eventlogs_model->log_event(
            $user_id,
            $action_type,
            $entity_type,
            $entity_id,
            $message,
            $metadata
        );
        
        return $log_id;
    } catch (Exception $e) {
        log_message('error', 'Failed to insert event log: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log user login
 * 
 * @param int $user_id - User ID
 * @param string $username - Username
 * @param bool $success - Login success status
 * @param string $error_message - Error message if login failed
 */
function log_user_login($user_id, $username, $success = true, $error_message = '')
{
    $metadata = [
        'username' => $username,
        'status' => $success ? 'success' : 'fail'
    ];
    
    if (!$success && $error_message) {
        $metadata['error_message'] = $error_message;
    }
    
    $message = $success ? 
        "User '{$username}' logged in successfully" : 
        "Failed login attempt for user '{$username}'";
    
    insert_event_log('login', 'user', $user_id, $message, $metadata);
}

/**
 * Log user logout
 * 
 * @param int $user_id - User ID
 * @param string $username - Username
 */
function log_user_logout($user_id, $username)
{
    $message = "User '{$username}' logged out";
    $metadata = [
        'username' => $username,
        'status' => 'success'
    ];
    
    insert_event_log('logout', 'user', $user_id, $message, $metadata);
}

/**
 * Log record creation
 * 
 * @param string $entity_type - Type of entity (user, device, notification, etc.)
 * @param int $entity_id - ID of created record
 * @param string $entity_name - Name/description of the entity
 * @param array $new_values - New record values
 */
function log_record_created($entity_type, $entity_id, $entity_name, $new_values = [])
{
    $message = "Created new {$entity_type}: {$entity_name}";
    $metadata = [
        'new_values' => $new_values,
        'status' => 'success'
    ];
    
    insert_event_log('create', $entity_type, $entity_id, $message, $metadata);
}

/**
 * Log record update
 * 
 * @param string $entity_type - Type of entity
 * @param int $entity_id - ID of updated record
 * @param string $entity_name - Name/description of the entity
 * @param array $old_values - Previous values
 * @param array $new_values - New values
 */
function log_record_updated($entity_type, $entity_id, $entity_name, $old_values = [], $new_values = [])
{
    $message = "Updated {$entity_type}: {$entity_name}";
    $metadata = [
        'old_values' => $old_values,
        'new_values' => $new_values,
        'status' => 'success'
    ];
    
    insert_event_log('update', $entity_type, $entity_id, $message, $metadata);
}

/**
 * Log record deletion
 * 
 * @param string $entity_type - Type of entity
 * @param int $entity_id - ID of deleted record
 * @param string $entity_name - Name/description of the entity
 * @param array $deleted_values - Values of deleted record
 */
function log_record_deleted($entity_type, $entity_id, $entity_name, $deleted_values = [])
{
    $message = "Deleted {$entity_type}: {$entity_name}";
    $metadata = [
        'deleted_values' => $deleted_values,
        'status' => 'success'
    ];
    
    insert_event_log('delete', $entity_type, $entity_id, $message, $metadata);
}

/**
 * Log system action
 * 
 * @param string $action - Action description
 * @param string $entity_type - Type of entity
 * @param int $entity_id - ID of affected record
 * @param array $metadata - Additional data
 */
function log_system_action($action, $entity_type, $entity_id, $metadata = [])
{
    $metadata['status'] = 'success';
    insert_event_log('system', $entity_type, $entity_id, $action, $metadata);
}

/**
 * Log error/exception
 * 
 * @param string $error_message - Error description
 * @param string $entity_type - Type of entity (optional)
 * @param int $entity_id - ID of affected record (optional)
 * @param array $metadata - Additional error data
 */
function log_error($error_message, $entity_type = 'system', $entity_id = 0, $metadata = [])
{
    $metadata['status'] = 'fail';
    $metadata['error_type'] = 'exception';
    
    insert_event_log('error', $entity_type, $entity_id, $error_message, $metadata);
}

/**
 * Log permission denied
 * 
 * @param string $action - Attempted action
 * @param string $entity_type - Type of entity
 * @param int $entity_id - ID of affected record
 * @param string $reason - Reason for denial
 */
function log_permission_denied($action, $entity_type, $entity_id, $reason = '')
{
    $message = "Permission denied for {$action} on {$entity_type}";
    if ($reason) {
        $message .= ": {$reason}";
    }
    
    $metadata = [
        'attempted_action' => $action,
        'denial_reason' => $reason,
        'status' => 'fail'
    ];
    
    insert_event_log('permission_denied', $entity_type, $entity_id, $message, $metadata);
}

/**
 * Get old values from database for comparison
 * 
 * @param string $table - Table name
 * @param int $id - Record ID
 * @param array $fields - Fields to compare
 * @return array - Old values
 */
function get_old_values($table, $id, $fields = [])
{
    $CI =& get_instance();
    
    $CI->db->select(implode(',', $fields));
    $CI->db->from($table);
    $CI->db->where('id', $id);
    $query = $CI->db->get();
    
    if ($query->num_rows() > 0) {
        return $query->row_array();
    }
    
    return [];
}

/**
 * Compare old and new values for logging
 * 
 * @param array $old_values - Previous values
 * @param array $new_values - New values
 * @return array - Changed values only
 */
function get_changed_values($old_values, $new_values)
{
    $changed = [];
    
    foreach ($new_values as $key => $value) {
        if (isset($old_values[$key]) && $old_values[$key] !== $value) {
            $changed[$key] = [
                'old' => $old_values[$key],
                'new' => $value
            ];
        }
    }
    
    return $changed;
} 