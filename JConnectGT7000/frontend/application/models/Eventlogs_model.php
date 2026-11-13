<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Eventlogs_model extends CI_Model
{
    /**
     * Log an event to the event_logs table
     *
     * @param int $user_id
     * @param string $action_type
     * @param string $entity_type
     * @param int $entity_id
     * @param string $message
     * @param array $metadata
     * @return int Inserted log ID
     */
    public function log_event($user_id, $action_type, $entity_type, $entity_id, $message, $metadata = [])
    {
        // Use raw SQL to set UTC timezone
        $sql = "INSERT INTO event_logs (user_id, action_type, entity_type, entity_id, message, metadata, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW() AT TIME ZONE 'UTC')";
        
        $params = [
            $user_id,
            $action_type,
            $entity_type,
            $entity_id,
            $message,
            json_encode($metadata)
        ];
        
        $this->db->query($sql, $params);
        return $this->db->insert_id();
    }
} 