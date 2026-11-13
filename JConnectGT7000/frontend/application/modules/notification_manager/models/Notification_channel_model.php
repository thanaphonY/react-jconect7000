<?php
class Notification_channel_model extends CI_Model
{
    private $table = 'notification_channels';

    /**
     * Get all active (non-deleted) channels
     */
    public function getAll()
    {
        return $this->db->select('*')
        ->from($this->table)
        ->where('is_deleted', false)
        ->order_by('id', 'desc')
        ->get()
        ->result();
    }

    /**
     * Get all channels including deleted ones (for admin purposes)
     */
    public function getAllIncludingDeleted()
    {
        return $this->db->select('*')
        ->from($this->table)
        ->order_by('id', 'desc')
        ->get()
        ->result();
    }

    /**
     * Get channel by ID (only active channels)
     */
    public function getById($id)
    {
        return $this->db->where('id', $id)
        ->where('is_deleted', FALSE)
        ->get($this->table)
        ->row();
    }

    /**
     * Get channel by ID including deleted ones
     */
    public function getByIdIncludingDeleted($id)
    {
        return $this->db->where('id', $id)
        ->get($this->table)
        ->row();
    }

    /**
     * Insert new channel
     */
    public function insert($data)
    {
        // Remove created_at from data array if it exists
        unset($data['created_at']);
        
        // Build column names and values for raw SQL
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ", created_at) 
                VALUES (" . implode(', ', $placeholders) . ", NOW() AT TIME ZONE 'UTC')";
        
        $values = array_values($data);
        
        return $this->db->query($sql, $values);
    }

    /**
     * Update channel
     */
    public function update($id, $data)
    {
        // Remove updated_at from data array if it exists
        unset($data['updated_at']);
        
        // Build SET clause for raw SQL
        $set_clauses = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $set_clauses[] = "{$column} = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set_clauses) . ", updated_at = NOW() AT TIME ZONE 'UTC' WHERE id = ?";
        $values[] = $id;
        
        return $this->db->query($sql, $values);
    }

    /**
     * Soft delete channel (set is_deleted = true)
     */
    public function delete($id)
    {
        return $this->db->where('id', $id)
        ->update($this->table, ['is_deleted' => TRUE]);
    }

    /**
     * Hard delete channel (permanently remove from database)
     * Use with caution!
     */
    public function hardDelete($id)
    {
        return $this->db->where('id', $id)->delete($this->table);
    }

    /**
     * Restore soft deleted channel (set is_deleted = false)
     */
    public function restore($id)
    {
        return $this->db->where('id', $id)
        ->update($this->table, ['is_deleted' => FALSE]);
    }
}
