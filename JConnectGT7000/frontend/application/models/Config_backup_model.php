<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Config_backup_model extends CI_Model
{
    /** Parents before children. These tables are configuration, not telemetry. */
    public function tables()
    {
        return array(
            'device_categories',
            'device_templates',
            'devices',
            'widgets',
            'dashboard_layouts',
            'dashboard_layout_items',
            'notification_channels',
            'report_categories',
            'report_templates',
            'report_schedules',
            'lorawan_applications',
            'ia_menu',
            'ia_permission',
            'ia_setting',
        );
    }

    public function exportPayload($includeUsers)
    {
        $tables = array();
        foreach ($this->tables() as $table) {
            $tables[$table] = $this->db->order_by($this->primaryKey($table), 'ASC')->get($table)->result_array();
        }
        if ($includeUsers) {
            $tables['ia_users'] = $this->db->order_by('ia_users_id', 'ASC')->get('ia_users')->result_array();
        }
        return array(
            'software' => 'JConnect',
            'kind' => 'config',
            'exported_at' => date('c'),
            'include_users' => $includeUsers ? true : false,
            'tables' => $tables,
        );
    }

    public function restorePayload($payload)
    {
        if (!is_array($payload) || ($payload['software'] ?? '') !== 'JConnect' || ($payload['kind'] ?? '') !== 'config') {
            throw new RuntimeException('invalid_backup');
        }
        if (!isset($payload['tables']) || !is_array($payload['tables'])) {
            throw new RuntimeException('invalid_backup');
        }
        foreach ($this->tables() as $table) {
            if (!isset($payload['tables'][$table]) || !is_array($payload['tables'][$table])) {
                throw new RuntimeException('missing_table_' . $table);
            }
        }

        $this->db->trans_begin();
        try {
            foreach ($this->tables() as $table) {
                $this->replaceTable($table, $payload['tables'][$table]);
            }
            if (!empty($payload['include_users']) && isset($payload['tables']['ia_users']) && is_array($payload['tables']['ia_users'])) {
                $this->upsertOnly('ia_users', $payload['tables']['ia_users']);
            }
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('restore_failed');
            }
            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    private function replaceTable($table, $rows)
    {
        $columns = $this->columns($table);
        $pk = $this->primaryKey($table);
        if (!$this->hasPrimaryKey($table, $pk)) {
            $this->db->query('DELETE FROM "' . $table . '"');
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('bad_row_' . $table);
                }
                $this->insertRow($table, $columns, $row);
            }
            $this->resetSequence($table, $pk);
            return;
        }
        $ids = array();
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($pk, $row)) {
                throw new RuntimeException('bad_row_' . $table);
            }
            $this->upsert($table, $columns, $pk, $row);
            $ids[] = $row[$pk];
        }
        $this->deleteMissing($table, $pk, $ids);
        $this->resetSequence($table, $pk);
    }

    private function upsertOnly($table, $rows)
    {
        $columns = $this->columns($table);
        $pk = $this->primaryKey($table);
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($pk, $row)) {
                throw new RuntimeException('bad_row_' . $table);
            }
            $this->upsert($table, $columns, $pk, $row);
        }
        $this->resetSequence($table, $pk);
    }

    private function upsert($table, $columns, $pk, $row)
    {
        $json = $this->jsonColumns($table);
        $fields = array();
        $insert = array();
        $updates = array();
        $binds = array();
        foreach (array_keys($row) as $key) {
            if (!in_array($key, $columns, true)) {
                throw new RuntimeException('unknown_column_' . $table);
            }
        }
        foreach ($columns as $column) {
            $fields[] = '"' . $column . '"';
            $value = array_key_exists($column, $row) ? $row[$column] : null;
            if (is_array($value)) {
                $value = json_encode($value);
            }
            if (in_array($column, $json, true)) {
                $insert[] = 'CAST(? AS jsonb)';
            } else {
                $insert[] = '?';
            }
            $binds[] = $value === '' && in_array($column, $json, true) ? null : $value;
            if ($column !== $pk) {
                $updates[] = '"' . $column . '" = EXCLUDED."' . $column . '"';
            }
        }
        $sql = 'INSERT INTO "' . $table . '" (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $insert) . ') '
            . 'ON CONFLICT ("' . $pk . '") DO UPDATE SET ' . implode(', ', $updates);
        $ok = $this->db->query($sql, $binds);
        $error = $this->db->error();
        if ($ok === false || !empty($error['code'])) {
            throw new RuntimeException('sql_' . $table);
        }
    }

    private function insertRow($table, $columns, $row)
    {
        $json = $this->jsonColumns($table);
        $fields = array();
        $insert = array();
        $binds = array();
        foreach (array_keys($row) as $key) {
            if (!in_array($key, $columns, true)) {
                throw new RuntimeException('unknown_column_' . $table);
            }
        }
        foreach ($columns as $column) {
            $fields[] = '"' . $column . '"';
            $value = array_key_exists($column, $row) ? $row[$column] : null;
            if (is_array($value)) {
                $value = json_encode($value);
            }
            if (in_array($column, $json, true)) {
                $insert[] = 'CAST(? AS jsonb)';
            } else {
                $insert[] = '?';
            }
            $binds[] = ($value === '' && in_array($column, $json, true)) ? null : $value;
        }
        $ok = $this->db->query(
            'INSERT INTO "' . $table . '" (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $insert) . ')',
            $binds
        );
        $error = $this->db->error();
        if ($ok === false || !empty($error['code'])) {
            throw new RuntimeException('sql_' . $table);
        }
    }

    private function hasPrimaryKey($table, $pk)
    {
        $row = $this->db->query(
            "SELECT 1 AS ok FROM pg_constraint con
             JOIN pg_class c ON c.oid = con.conrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relname = ? AND con.contype = 'p'
             AND pg_get_constraintdef(con.oid) = ?",
            array($table, 'PRIMARY KEY (' . $pk . ')')
        )->row();
        return (bool) $row;
    }

    private function deleteMissing($table, $pk, $ids)
    {
        if (count($ids) === 0) {
            $this->db->query('DELETE FROM "' . $table . '"');
            return;
        }
        $clean = array();
        foreach ($ids as $id) {
            $clean[] = is_int($id) || ctype_digit((string) $id) ? (string) (int) $id : $this->db->escape($id);
        }
        $this->db->query('DELETE FROM "' . $table . '" WHERE "' . $pk . '" NOT IN (' . implode(', ', $clean) . ')');
    }

    private function resetSequence($table, $pk)
    {
        $seqQuery = $this->db->query('SELECT pg_get_serial_sequence(?, ?) AS seq', array($table, $pk));
        if ($seqQuery === false) {
            throw new RuntimeException('sql_' . $table);
        }
        $seq = $seqQuery->row();
        if (!$seq || empty($seq->seq)) {
            return;
        }
        $this->db->query(
            'SELECT setval(?, COALESCE((SELECT MAX("' . $pk . '") FROM "' . $table . '"), 1), true)',
            array($seq->seq)
        );
    }

    private function primaryKey($table)
    {
        return $table === 'ia_users' ? 'ia_users_id' : 'id';
    }

    private function columns($table)
    {
        $rows = $this->db->query(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? ORDER BY ordinal_position",
            array($table)
        )->result_array();
        $names = array();
        foreach ($rows as $row) {
            $names[] = $row['column_name'];
        }
        return $names;
    }

    private function jsonColumns($table)
    {
        $map = array(
            'device_templates' => array('device_information', 'communication_config', 'metrics', 'default_dashboard_layout'),
            'devices' => array('config', 'communication_config', 'metrics', 'dashboard_layout'),
            'widgets' => array('config'),
            'dashboard_layouts' => array('layout'),
            'dashboard_layout_items' => array('config', 'position'),
            'notification_channels' => array('config'),
            'report_templates' => array('config'),
            'report_schedules' => array('schedule_config', 'recipients', 'report_params'),
        );
        return isset($map[$table]) ? $map[$table] : array();
    }
}
