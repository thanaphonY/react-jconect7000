<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Retention_model extends CI_Model
{
    public function policies()
    {
        return array(
            'device_data' => array('column' => 'timestamp', 'default_days' => 90),
            'event_logs' => array('column' => 'created_at', 'default_days' => 180),
            'alarm_logs' => array('column' => 'created_at', 'default_days' => 365),
            'alarm_log_dispatch' => array('column' => 'created_at', 'default_days' => 365),
            'health_check_logs' => array('column' => 'sent_at', 'default_days' => 30),
            'report_export_files' => array('column' => 'exported_at', 'default_days' => 30),
            'ia_sessions' => array('column' => 'timestamp', 'default_days' => 7, 'unix' => true),
        );
    }

    public function current()
    {
        $out = array();
        foreach ($this->policies() as $table => $policy) {
            $enabled = getSetting('retention_' . $table . '_enabled');
            $days = getSetting('retention_' . $table . '_days');
            $out[$table] = array(
                'enabled' => ($enabled === false || $enabled === '' || $enabled === null) ? true : ((string) $enabled === '1'),
                'days' => ($days === false || $days === '' || $days === null) ? $policy['default_days'] : (int) $days,
                'column' => $policy['column'],
            );
        }
        return $out;
    }

    public function save($input)
    {
        foreach ($this->policies() as $table => $policy) {
            $days = isset($input['days'][$table]) ? (int) $input['days'][$table] : $policy['default_days'];
            if ($days < 1) {
                $days = 1;
            }
            $enabled = isset($input['enabled'][$table]) ? '1' : '0';
            $this->upsertSetting('retention_' . $table . '_enabled', $enabled);
            $this->upsertSetting('retention_' . $table . '_days', (string) $days);
        }
    }

    public function preview()
    {
        $counts = array();
        foreach ($this->current() as $table => $policy) {
            if (!$policy['enabled']) {
                $counts[$table] = 0;
                continue;
            }
            $counts[$table] = $this->countOlder($table, $policy['column'], $policy['days'], !empty($this->policies()[$table]['unix']));
        }
        return $counts;
    }

    public function purge()
    {
        $deleted = array();
        foreach ($this->current() as $table => $policy) {
            if (!$policy['enabled']) {
                $deleted[$table] = 0;
                continue;
            }
            $unix = !empty($this->policies()[$table]['unix']);
            $deleted[$table] = $this->deleteOlder($table, $policy['column'], $policy['days'], $unix);
        }
        return $deleted;
    }

    private function countOlder($table, $column, $days, $unix)
    {
        $sql = 'SELECT COUNT(*) AS n FROM "' . $table . '" WHERE ' . $this->olderThan($column, $unix);
        $row = $this->db->query($sql, array((int) $days))->row();
        return (int) $row->n;
    }

    private function deleteOlder($table, $column, $days, $unix)
    {
        $sql = 'DELETE FROM "' . $table . '" WHERE ' . $this->olderThan($column, $unix);
        $this->db->query($sql, array((int) $days));
        return (int) $this->db->affected_rows();
    }

    private function olderThan($column, $unix)
    {
        if ($unix) {
            return '"' . $column . '" < EXTRACT(EPOCH FROM (NOW() - (? || \' days\')::interval))';
        }
        return '"' . $column . '" < NOW() - (? || \' days\')::interval';
    }

    private function upsertSetting($key, $value)
    {
        $exists = $this->db->get_where('ia_setting', array('keys' => $key))->row();
        if ($exists) {
            $this->db->where('keys', $key)->update('ia_setting', array('value' => $value));
            return;
        }
        $this->db->insert('ia_setting', array('keys' => $key, 'value' => $value));
    }
}
