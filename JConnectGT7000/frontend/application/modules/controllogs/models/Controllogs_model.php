<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Controllogs_model extends CI_Model
{
    private function apply_date_range($range, $startDate, $endDate)
    {
        if (!$range || $range === 'all') {
            return;
        }
        switch ($range) {
            case 'today':
                $this->db->where('DATE(control_command_logs.created_at)', date('Y-m-d'));
                break;
            case 'yesterday':
                $this->db->where('DATE(control_command_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                break;
            case 'last_7_days':
                $this->db->where('DATE(control_command_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                break;
            case 'last_30_days':
                $this->db->where('DATE(control_command_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                break;
            case 'custom':
                if ($startDate && $endDate) {
                    $this->db->where('DATE(control_command_logs.created_at) >=', $startDate);
                    $this->db->where('DATE(control_command_logs.created_at) <=', $endDate);
                }
                break;
        }
    }

    private function apply_filters($filters)
    {
        $this->apply_date_range(
            $filters['range'] ?? null,
            $filters['startDate'] ?? null,
            $filters['endDate'] ?? null
        );

        $group = $filters['group_filter'] ?? null;
        if ($group && $group !== 'all') {
            if ($group === 'ungrouped' || $group === '0') {
                $this->db->where('control_commands.group_id IS NULL');
            } else {
                $this->db->where('control_commands.group_id', (int) $group);
            }
        }

        $device = $filters['device_filter'] ?? null;
        if ($device && $device !== 'all') {
            $this->db->where('control_commands.device_id', (int) $device);
        }

        $source = $filters['source_filter'] ?? null;
        if ($source && $source !== 'all') {
            $this->db->where('control_command_logs.source', $source);
        }

        $result = $filters['result_filter'] ?? null;
        if ($result && $result !== 'all') {
            $this->db->where('control_command_logs.result', $result);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('control_commands.name', $search);
            $this->db->or_like('devices.name', $search);
            $this->db->or_like('control_groups.name', $search);
            $this->db->or_like('control_command_logs.source', $search);
            $this->db->or_like('control_command_logs.result', $search);
            $this->db->or_like('control_command_logs.requested_value', $search);
            $this->db->or_like('control_command_logs.error', $search);
            $this->db->or_like('control_sequences.name', $search);
            $this->db->or_like('control_rules.name', $search);
            $this->db->or_like('ia_users.name', $search);
            $this->db->group_end();
        }
    }

    private function from_logs()
    {
        $this->db->from('control_command_logs');
        $this->db->join('control_commands', 'control_commands.id = control_command_logs.command_id', 'left');
        $this->db->join('devices', 'devices.id = control_commands.device_id', 'left');
        $this->db->join('control_groups', 'control_groups.id = control_commands.group_id', 'left');
        $this->db->join('control_sequences', 'control_sequences.id = control_command_logs.sequence_id', 'left');
        $this->db->join('control_rules', 'control_rules.id = control_command_logs.rule_id', 'left');
        $this->db->join('ia_users', 'ia_users.ia_users_id = control_command_logs.user_id', 'left');
    }

    private function select_logs()
    {
        $this->db->select("
            control_command_logs.*,
            control_commands.name AS command_name,
            devices.name AS device_name,
            control_groups.name AS group_name,
            control_sequences.name AS sequence_name,
            control_rules.name AS rule_name,
            ia_users.name AS user_name
        ", false);
        $this->from_logs();
    }

    public function get_logs($filters, $pageSize, $offset)
    {
        $this->select_logs();
        $this->apply_filters($filters);
        $this->db->order_by('control_command_logs.created_at', 'DESC');
        $this->db->limit((int) $pageSize, (int) $offset);
        return $this->db->get()->result();
    }

    public function count_logs($filters)
    {
        $this->db->select('control_command_logs.id');
        $this->from_logs();
        $this->apply_filters($filters);
        return $this->db->count_all_results();
    }

    public function get_statistics($filters)
    {
        $this->db->select("
            COUNT(*) AS total_logs,
            SUM(CASE WHEN control_command_logs.result = 'written' THEN 1 ELSE 0 END) AS written_count,
            SUM(CASE WHEN control_command_logs.result = 'failed' THEN 1 ELSE 0 END) AS failed_count,
            COUNT(DISTINCT control_commands.device_id) AS device_count
        ", false);
        $this->from_logs();
        $this->apply_filters($filters);
        $row = $this->db->get()->row();
        return [
            'total_logs' => (int) ($row->total_logs ?? 0),
            'written_count' => (int) ($row->written_count ?? 0),
            'failed_count' => (int) ($row->failed_count ?? 0),
            'device_count' => (int) ($row->device_count ?? 0),
        ];
    }

    public function get_unique_groups()
    {
        $this->db->select('control_groups.id, control_groups.name');
        $this->db->from('control_command_logs');
        $this->db->join('control_commands', 'control_commands.id = control_command_logs.command_id', 'left');
        $this->db->join('control_groups', 'control_groups.id = control_commands.group_id', 'left');
        $this->db->where('control_groups.id IS NOT NULL');
        $this->db->group_by('control_groups.id, control_groups.name');
        $this->db->order_by('control_groups.name', 'ASC');
        return $this->db->get()->result();
    }

    public function get_unique_devices()
    {
        $this->db->select('devices.id, devices.name');
        $this->db->from('control_command_logs');
        $this->db->join('control_commands', 'control_commands.id = control_command_logs.command_id', 'left');
        $this->db->join('devices', 'devices.id = control_commands.device_id', 'left');
        $this->db->where('devices.id IS NOT NULL');
        $this->db->group_by('devices.id, devices.name');
        $this->db->order_by('devices.name', 'ASC');
        return $this->db->get()->result();
    }

    public function get_unique_sources()
    {
        $this->db->select('source');
        $this->db->from('control_command_logs');
        $this->db->where('source IS NOT NULL');
        $this->db->group_by('source');
        $this->db->order_by('source', 'ASC');
        return $this->db->get()->result();
    }

    public function get_unique_results()
    {
        $this->db->select('result');
        $this->db->from('control_command_logs');
        $this->db->where('result IS NOT NULL');
        $this->db->group_by('result');
        $this->db->order_by('result', 'ASC');
        return $this->db->get()->result();
    }

    public function get_logs_for_export($filters)
    {
        $this->select_logs();
        $this->apply_filters($filters);
        $this->db->order_by('control_command_logs.created_at', 'DESC');
        return $this->db->get()->result();
    }

    public function format_value_label($value)
    {
        $v = strtolower(trim((string) $value));
        if (in_array($v, ['1', 'on', 'true'], true)) {
            return 'ON';
        }
        if (in_array($v, ['0', 'off', 'false'], true)) {
            return 'OFF';
        }
        return $value === null || $value === '' ? '-' : (string) $value;
    }

    public function format_source_label($log)
    {
        $source = (string) ($log->source ?? '-');
        $extra = [];
        if (!empty($log->sequence_name)) {
            $extra[] = $log->sequence_name;
        }
        if (!empty($log->rule_name)) {
            $extra[] = $log->rule_name;
        }
        return $extra ? $source . ' (' . implode(' / ', $extra) . ')' : $source;
    }
}
