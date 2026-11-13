<?php defined('BASEPATH') or exit('No direct script access allowed');

class Control_model extends CI_Model
{
    private $db_ts;

    private function ts()
    {
        if (!$this->db_ts) {
            $this->db_ts = $this->load->database('timescaledb', true);
        }
        return $this->db_ts;
    }

    private function base_url()
    {
        $url = getenv('CONTROL_LOGIC_URL');
        if ($url) {
            return rtrim($url, '/');
        }
        // Production: PHP → nginx /controlapi/ → control-logic on 127.0.0.1:3006
        // Docker dev: set CONTROL_LOGIC_URL=http://host.docker.internal:3006
        return 'http://127.0.0.1/controlapi';
    }

    private function request($method, $path, $body = null)
    {
        $url = $this->base_url() . $path;
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => $err ?: 'control-logic unreachable'];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'invalid response from control-logic (HTTP ' . $code . ')'];
        }
        return $decoded;
    }

    public function health()
    {
        return $this->request('GET', '/health');
    }

    public function list_commands($groupId = null, $ungrouped = false)
    {
        $qs = [];
        if ($ungrouped) {
            $qs[] = 'ungrouped=1';
        } elseif ($groupId) {
            $qs[] = 'group_id=' . (int) $groupId;
        }
        return $this->request('GET', '/api/commands' . ($qs ? '?' . implode('&', $qs) : ''));
    }

    public function create_command(array $payload)
    {
        return $this->request('POST', '/api/commands', $payload);
    }

    public function update_command($id, array $payload)
    {
        return $this->request('PUT', '/api/commands/' . (int) $id, $payload);
    }

    public function delete_command($id)
    {
        return $this->request('DELETE', '/api/commands/' . (int) $id);
    }

    public function execute_command($id, $value, $userId)
    {
        return $this->request('POST', '/api/commands/' . (int) $id . '/execute', [
            'value' => $value,
            'source' => 'manual',
            'user_id' => (int) $userId,
        ]);
    }

    public function release_hold($id, $userId)
    {
        return $this->request('POST', '/api/commands/' . (int) $id . '/auto', [
            'user_id' => (int) $userId,
        ]);
    }

    public function list_logs($limit = 50, $groupId = null, $ungrouped = false)
    {
        $qs = ['limit=' . (int) $limit];
        if ($ungrouped) {
            $qs[] = 'ungrouped=1';
        } elseif ($groupId) {
            $qs[] = 'group_id=' . (int) $groupId;
        }
        return $this->request('GET', '/api/logs?' . implode('&', $qs));
    }

    public function list_schedules($groupId = null, $ungrouped = false)
    {
        $qs = [];
        if ($ungrouped) {
            $qs[] = 'ungrouped=1';
        } elseif ($groupId) {
            $qs[] = 'group_id=' . (int) $groupId;
        }
        return $this->request('GET', '/api/schedules' . ($qs ? '?' . implode('&', $qs) : ''));
    }

    public function create_schedule(array $payload)
    {
        return $this->request('POST', '/api/schedules', $payload);
    }

    public function update_schedule($id, array $payload)
    {
        return $this->request('PUT', '/api/schedules/' . (int) $id, $payload);
    }

    public function delete_schedule($id)
    {
        return $this->request('DELETE', '/api/schedules/' . (int) $id);
    }

    public function list_sequences($groupId = null, $ungrouped = false)
    {
        $qs = [];
        if ($ungrouped) {
            $qs[] = 'ungrouped=1';
        } elseif ($groupId) {
            $qs[] = 'group_id=' . (int) $groupId;
        }
        return $this->request('GET', '/api/sequences' . ($qs ? '?' . implode('&', $qs) : ''));
    }

    public function create_sequence(array $payload)
    {
        return $this->request('POST', '/api/sequences', $payload);
    }

    public function update_sequence($id, array $payload)
    {
        return $this->request('PUT', '/api/sequences/' . (int) $id, $payload);
    }

    public function delete_sequence($id)
    {
        return $this->request('DELETE', '/api/sequences/' . (int) $id);
    }

    public function execute_sequence($id, $userId)
    {
        return $this->request('POST', '/api/sequences/' . (int) $id . '/execute', [
            'user_id' => (int) $userId,
        ]);
    }

    public function stop_sequence($id)
    {
        return $this->request('POST', '/api/sequences/' . (int) $id . '/stop');
    }

    public function pause_sequence($id)
    {
        return $this->request('POST', '/api/sequences/' . (int) $id . '/pause');
    }

    public function resume_sequence($id)
    {
        return $this->request('POST', '/api/sequences/' . (int) $id . '/resume');
    }

    public function list_rules($groupId = null, $ungrouped = false)
    {
        $qs = [];
        if ($ungrouped) {
            $qs[] = 'ungrouped=1';
        } elseif ($groupId) {
            $qs[] = 'group_id=' . (int) $groupId;
        }
        return $this->request('GET', '/api/rules' . ($qs ? '?' . implode('&', $qs) : ''));
    }

    public function create_rule(array $payload)
    {
        return $this->request('POST', '/api/rules', $payload);
    }

    public function update_rule($id, array $payload)
    {
        return $this->request('PUT', '/api/rules/' . (int) $id, $payload);
    }

    public function delete_rule($id)
    {
        return $this->request('DELETE', '/api/rules/' . (int) $id);
    }

    public function running_sequence_diagrams(array $sequences, array $commands)
    {
        $names = [];
        foreach ($commands as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid > 0) {
                $names[$cid] = [
                    'name' => (string) ($c['name'] ?? ''),
                    'device_name' => (string) ($c['device_name'] ?? ''),
                ];
            }
        }
        $out = [];
        foreach ($sequences as $seq) {
            if (empty($seq['running'])) {
                continue;
            }
            $steps = $seq['steps'] ?? [];
            if (is_string($steps)) {
                $decoded = json_decode($steps, true);
                $steps = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($steps)) {
                $steps = [];
            }
            $formatted = [];
            $i = 0;
            foreach ($steps as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $cid = (int) ($s['command_id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                $i++;
                $formatted[] = [
                    'index' => (int) ($s['index'] ?? $i),
                    'command_id' => $cid,
                    'command_name' => $names[$cid]['name'] ?? ('#' . $cid),
                    'device_name' => $names[$cid]['device_name'] ?? '',
                    'action' => (($s['action'] ?? 'on') === 'off') ? 'off' : 'on',
                    'wait' => in_array($s['wait'] ?? '', ['on', 'off'], true) ? $s['wait'] : 'none',
                    'delay_sec' => (int) ($s['delay_sec'] ?? 0),
                ];
            }
            $out[] = [
                'id' => (int) ($seq['id'] ?? 0),
                'name' => (string) ($seq['name'] ?? ''),
                'loop' => !empty($seq['loop']) || !empty($seq['loop_enabled']),
                'loop_count' => (int) ($seq['loop_count'] ?? 0),
                'loop_delay_sec' => (int) ($seq['loop_delay_sec'] ?? 0),
                'running' => true,
                'paused' => !empty($seq['paused']) || (is_array($seq['progress'] ?? null) && (($seq['progress']['phase'] ?? '') === 'paused' || !empty($seq['progress']['paused']))),
                'progress' => is_array($seq['progress'] ?? null) ? $seq['progress'] : null,
                'steps' => $formatted,
            ];
        }
        return $out;
    }

    public function active_rule_cards(array $rules, array $commands, array $sequences, $devices = [])
    {
        $commandNames = [];
        foreach ($commands as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid > 0) {
                $commandNames[$cid] = (string) ($c['name'] ?? '');
            }
        }
        $sequenceNames = [];
        foreach ($sequences as $s) {
            $sid = (int) ($s['id'] ?? 0);
            if ($sid > 0) {
                $sequenceNames[$sid] = (string) ($s['name'] ?? '');
            }
        }
        $deviceNames = [];
        if (is_array($devices) || $devices instanceof Traversable) {
            foreach ($devices as $d) {
                $id = is_object($d) ? (int) ($d->id ?? 0) : (int) ($d['id'] ?? 0);
                $name = is_object($d) ? (string) ($d->name ?? '') : (string) ($d['name'] ?? '');
                if ($id > 0) {
                    $deviceNames[$id] = $name;
                }
            }
        }
        $out = [];
        foreach ($rules as $rule) {
            $matched = !empty($rule['matched']);
            $latched = !empty($rule['latched']);
            $triggeredAt = $rule['last_triggered_at'] ?? $rule['last_fired_at'] ?? null;
            $live = $matched || $latched;
            if (!$live && !$triggeredAt) {
                continue;
            }
            $cooldownLeft = (int) ($rule['cooldown_remaining_sec'] ?? 0);
            if ($live) {
                $status = $latched || $matched ? 'Active' : 'Matching';
                if ($cooldownLeft > 0 && !$latched) {
                    $status = 'Cooldown';
                }
            } else {
                $status = 'Last active';
            }
            $out[] = [
                'id' => (int) ($rule['id'] ?? 0),
                'name' => (string) ($rule['name'] ?? ''),
                'when' => $this->rule_condition_summary($rule, $commandNames, $deviceNames),
                'then' => $this->rule_action_summary($rule, $commandNames, $sequenceNames),
                'matched' => $matched,
                'latched' => $latched,
                'live' => $live,
                'status' => $status,
                'last_triggered_at' => $triggeredAt,
                'last_result' => $rule['last_result'] ?? null,
                'cooldown_remaining_sec' => $cooldownLeft,
                'skipped' => $rule['skipped'] ?? null,
                '_sort' => $triggeredAt ? strtotime((string) $triggeredAt) : 0,
            ];
        }
        usort($out, function ($a, $b) {
            if (!empty($a['live']) !== !empty($b['live'])) {
                return !empty($b['live']) ? 1 : -1;
            }
            return ($b['_sort'] ?? 0) <=> ($a['_sort'] ?? 0);
        });
        $out = array_slice($out, 0, 5);
        foreach ($out as &$row) {
            unset($row['_sort']);
        }
        unset($row);
        return $out;
    }

    private function rule_decode($raw)
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function rule_condition_summary($rule, array $commandNames, array $deviceNames)
    {
        $c = $this->rule_decode($rule['conditions'] ?? []);
        $items = isset($c['items']) && is_array($c['items']) ? $c['items'] : [];
        if (!$items) {
            return 'No conditions';
        }
        $match = strtolower((string) ($c['match'] ?? 'all')) === 'any' ? 'Any' : 'All';
        $parts = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = strtolower((string) ($item['type'] ?? ''));
            if ($type === 'command_status') {
                $cid = (int) ($item['command_id'] ?? 0);
                $name = $commandNames[$cid] ?? ('#' . $cid);
                $state = strtolower((string) ($item['state'] ?? 'off')) === 'on' ? 'On' : 'Off';
                $parts[] = $name . ' is ' . $state;
            } elseif ($type === 'alarm') {
                $did = (int) ($item['device_id'] ?? 0);
                $dname = $deviceNames[$did] ?? ('device #' . $did);
                $key = trim((string) ($item['metric_key'] ?? ''));
                $parts[] = $key !== '' ? ('alarm ' . $dname . ' · ' . $key) : ('any alarm on ' . $dname);
            } elseif ($type === 'metric') {
                $did = (int) ($item['device_id'] ?? 0);
                $dname = $deviceNames[$did] ?? ('device #' . $did);
                $key = (string) ($item['key'] ?? $item['metric_key'] ?? '');
                $op = (string) ($item['op'] ?? '>');
                $val = (string) ($item['value'] ?? '');
                $parts[] = $dname . ' ' . $key . ' ' . $op . ' ' . $val;
            }
        }
        return $match . ': ' . ($parts ? implode('; ', $parts) : 'No conditions');
    }

    private function rule_action_summary($rule, array $commandNames, array $sequenceNames)
    {
        $a = $this->rule_decode($rule['action'] ?? []);
        $kind = strtolower((string) ($a['kind'] ?? $a['type'] ?? ''));
        if ($kind === 'sequence') {
            $sid = (int) ($a['sequence_id'] ?? 0);
            $name = $sequenceNames[$sid] ?? ('#' . $sid);
            return 'Sequence ' . $name;
        }
        $cmds = isset($a['commands']) && is_array($a['commands']) ? $a['commands'] : [];
        $parts = [];
        foreach ($cmds as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cid = (int) ($c['command_id'] ?? 0);
            $name = $commandNames[$cid] ?? ('#' . $cid);
            $action = strtolower((string) ($c['action'] ?? 'on')) === 'off' ? 'Off' : 'On';
            $parts[] = $name . ' ' . $action;
        }
        return $parts ? implode(', ', $parts) : 'No action';
    }

    public function list_groups()
    {
        return $this->request('GET', '/api/groups');
    }

    public function create_group(array $payload)
    {
        return $this->request('POST', '/api/groups', $payload);
    }

    public function update_group($id, array $payload)
    {
        return $this->request('PUT', '/api/groups/' . (int) $id, $payload);
    }

    public function delete_group($id)
    {
        return $this->request('DELETE', '/api/groups/' . (int) $id);
    }

    public function command_live_status(array $commands, array $logs = [])
    {
        $latestByDevice = [];
        $lastLog = [];
        $lastWritten = [];
        foreach ($logs as $log) {
            $cid = (int) ($log['command_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            if (!isset($lastLog[$cid])) {
                $lastLog[$cid] = $log;
            }
            if (!isset($lastWritten[$cid]) && ($log['result'] ?? '') === 'written') {
                $lastWritten[$cid] = $log;
            }
        }

        $items = [];
        $summary = ['on' => 0, 'off' => 0, 'mismatch' => 0, 'unknown' => 0, 'disabled' => 0];
        foreach ($commands as $c) {
            $deviceId = (int) ($c['device_id'] ?? 0);
            $key = trim((string) ($c['feedback_metric_key'] ?? ''));
            if ($deviceId > 0 && !array_key_exists($deviceId, $latestByDevice)) {
                $latestByDevice[$deviceId] = $this->latest_metrics_for_device($deviceId);
            }
            $latest = ($key !== '' && isset($latestByDevice[$deviceId][$key]))
                ? $latestByDevice[$deviceId][$key]
                : null;
            $cfg = $c['write_config'] ?? [];
            if (is_string($cfg)) {
                $decoded = json_decode($cfg, true);
                $cfg = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($cfg)) {
                $cfg = [];
            }
            $interp = $this->interpret_feedback($latest, !empty($c['enabled']), $cfg);
            $cid = (int) ($c['id'] ?? 0);
            $log = $lastWritten[$cid] ?? $lastLog[$cid] ?? null;
            $commandState = $this->command_requested_state($log);
            $statusState = $interp['state'];
            $tone = $this->card_tone($commandState, $statusState, !empty($c['enabled']));
            $summary[$tone] = ($summary[$tone] ?? 0) + 1;
            $items[] = [
                'id' => $cid,
                'name' => (string) ($c['name'] ?? ''),
                'description' => (string) ($c['description'] ?? ''),
                'device_id' => $deviceId,
                'device_name' => (string) ($c['device_name'] ?? ''),
                'enabled' => !empty($c['enabled']),
                'feedback_metric_key' => $key !== '' ? $key : null,
                'state' => $statusState,
                'state_label' => $interp['label'],
                'value_display' => $interp['value_display'],
                'timestamp' => $interp['timestamp'],
                'stale' => $interp['stale'],
                'command_state' => $commandState,
                'command_label' => $commandState === 'on' ? 'On' : ($commandState === 'off' ? 'Off' : null),
                'card_tone' => $tone,
                'hold_source' => (($c['hold_source'] ?? '') === 'manual') ? 'manual' : null,
                'last_action' => $log ? [
                    'source' => $log['source'] ?? '',
                    'sequence_name' => $log['sequence_name'] ?? '',
                    'rule_name' => $log['rule_name'] ?? '',
                    'value' => $log['requested_value'] ?? '',
                    'result' => $log['result'] ?? '',
                    'at' => $log['created_at'] ?? '',
                ] : null,
            ];
        }
        usort($items, function ($a, $b) {
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
        return ['items' => $items, 'summary' => $summary];
    }

    private function command_requested_state($log)
    {
        if (!is_array($log)) {
            return null;
        }
        $v = strtolower(trim((string) ($log['requested_value'] ?? '')));
        if (in_array($v, ['0', 'off', 'false'], true)) {
            return 'off';
        }
        if (in_array($v, ['1', 'on', 'true'], true)) {
            return 'on';
        }
        return null;
    }

    private function card_tone($commandState, $statusState, $enabled)
    {
        if (!$enabled || $statusState === 'disabled') {
            return 'disabled';
        }
        if ($commandState === 'on' && $statusState === 'on') {
            return 'on';
        }
        if ($commandState === 'off' && $statusState === 'off') {
            return 'off';
        }
        if (in_array($commandState, ['on', 'off'], true) && in_array($statusState, ['on', 'off'], true)) {
            return 'mismatch';
        }
        return 'mismatch';
    }

    private function latest_metrics_for_device($deviceId)
    {
        $deviceId = (int) $deviceId;
        if ($deviceId <= 0) {
            return [];
        }
        $ts = $this->ts();
        $sql = "
            SELECT DISTINCT ON (key)
                   key, value, value_text, value_bool, value_json, timestamp
              FROM device_data
             WHERE device_id = ?
             ORDER BY key, timestamp DESC
        ";
        $query = $ts->query($sql, [$deviceId]);
        $result = [];
        foreach ($query->result() as $row) {
            $result[$row->key] = [
                'value' => $row->value,
                'value_text' => $row->value_text,
                'value_bool' => $row->value_bool,
                'value_json' => $row->value_json,
                'timestamp' => $row->timestamp,
            ];
        }
        return $result;
    }

    private function interpret_feedback($latest, $enabled, array $writeConfig = [])
    {
        if (!$enabled) {
            return [
                'state' => 'disabled',
                'label' => 'Disabled',
                'value_display' => '—',
                'timestamp' => null,
                'stale' => false,
            ];
        }
        if (!is_array($latest)) {
            return [
                'state' => 'unknown',
                'label' => 'No data',
                'value_display' => '—',
                'timestamp' => null,
                'stale' => false,
            ];
        }

        $timestamp = $latest['timestamp'] ?? null;
        $stale = false;
        if ($timestamp) {
            $ts = strtotime((string) $timestamp);
            $stale = $ts !== false && (time() - $ts) > 600;
        }

        $value = $latest['value'] ?? null;
        $text = trim((string) ($latest['value_text'] ?? ''));
        $bool = $latest['value_bool'] ?? null;

        $display = '—';
        if ($text !== '') {
            $display = $text;
        } elseif ($value !== null && $value !== '') {
            $display = is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') : (string) $value;
        } elseif ($bool !== null && $bool !== '') {
            $truth = ($bool === true || $bool === 't' || $bool === '1' || $bool === 1);
            $display = $truth ? 'true' : 'false';
        }

        $fbOn = $this->normalize_map_list($writeConfig['feedback_on'] ?? []);
        $fbOff = $this->normalize_map_list($writeConfig['feedback_off'] ?? []);
        $onLabel = $this->status_text_label($writeConfig, 'on');
        $offLabel = $this->status_text_label($writeConfig, 'off');
        $candidates = [$value, $text];
        if ($bool === true || $bool === 't' || $bool === '1' || $bool === 1) {
            $candidates[] = '1';
            $candidates[] = 'true';
            $candidates[] = 'on';
        } elseif ($bool === false || $bool === 'f' || $bool === '0' || $bool === 0) {
            $candidates[] = '0';
            $candidates[] = 'false';
            $candidates[] = 'off';
        }

        if ($fbOn || $fbOff) {
            $onHit = $this->value_in_map($candidates, $fbOn);
            $offHit = $this->value_in_map($candidates, $fbOff);
            $state = 'unknown';
            $label = ($display !== '—' ? $display : 'Unknown');
            if ($onHit && !$offHit) {
                $state = 'on';
                $label = $onLabel;
            } elseif ($offHit && !$onHit) {
                $state = 'off';
                $label = $offLabel;
            } elseif ($onHit && $offHit) {
                $state = 'unknown';
                $label = 'Ambiguous';
            } else {
                $inferred = $this->infer_common_status($value, $text);
                if ($inferred) {
                    $state = $inferred;
                    $label = $inferred === 'on' ? $onLabel : $offLabel;
                }
            }
            return [
                'state' => $state,
                'label' => $label,
                'value_display' => $display,
                'timestamp' => $timestamp,
                'stale' => $stale,
            ];
        }

        $onWords = ['on', 'running', 'run', 'start', 'started', 'true', 'yes', 'open', 'active', '1'];
        $offWords = ['off', 'stopped', 'stop', 'false', 'no', 'idle', 'inactive', 'closed', '0'];

        $state = 'unknown';
        $label = 'Unknown';
        if ($bool === true || $bool === 't' || $bool === '1' || $bool === 1) {
            $state = 'on';
            $label = $onLabel;
        } elseif ($bool === false || $bool === 'f' || $bool === '0' || $bool === 0) {
            $state = 'off';
            $label = $offLabel;
        } elseif ($text !== '') {
            $tl = strtolower($text);
            if (in_array($tl, $onWords, true)) {
                $state = 'on';
                $label = $onLabel;
            } elseif (in_array($tl, $offWords, true)) {
                $state = 'off';
                $label = $offLabel;
            } else {
                $label = $text;
            }
        }

        if ($state === 'unknown' && $value !== null && $value !== '') {
            $inferred = $this->infer_common_status($value, $text);
            if ($inferred) {
                $state = $inferred;
                $label = $inferred === 'on' ? $onLabel : $offLabel;
            } else {
                $n = (float) $value;
                if ($n == 0.0) {
                    $state = 'off';
                    $label = $offLabel;
                } else {
                    $state = 'on';
                    $label = $onLabel;
                }
            }
        }

        if ($state === 'unknown' && $label === 'Unknown' && $display !== '—') {
            $label = $display;
        }

        return [
            'state' => $state,
            'label' => $label,
            'value_display' => $display,
            'timestamp' => $timestamp,
            'stale' => $stale,
        ];
    }

    private function status_text_label(array $writeConfig, $which)
    {
        $key = $which === 'off' ? 'feedback_off_label' : 'feedback_on_label';
        $s = trim((string) ($writeConfig[$key] ?? ''));
        if ($s !== '') {
            return $s;
        }
        return $which === 'off' ? 'Off' : 'On';
    }

    private function infer_common_status($value, $text = '')
    {
        $tokens = [];
        if ($text !== '') {
            $tokens[] = strtolower(trim((string) $text));
        }
        if ($value !== null && $value !== '') {
            $tokens[] = strtolower(trim((string) $value));
            if (is_numeric($value)) {
                $tokens[] = (string) (0 + $value);
            }
        }
        foreach ($tokens as $t) {
            if (in_array($t, ['3', 'on', 'running', 'run'], true)) {
                return 'on';
            }
            if (in_array($t, ['0', 'off', 'stopped', 'stop', 'idle'], true)) {
                return 'off';
            }
        }
        return null;
    }

    private function normalize_map_list($raw)
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return $out;
    }

    private function value_in_map(array $candidates, array $mapped)
    {
        if (!$mapped) {
            return false;
        }
        $needles = [];
        foreach ($candidates as $c) {
            if ($c === null || $c === '') {
                continue;
            }
            $needles[] = strtolower(trim((string) $c));
            if (is_numeric($c)) {
                $needles[] = (string) (0 + $c);
            }
        }
        foreach ($mapped as $m) {
            $ml = strtolower(trim((string) $m));
            if (in_array($ml, $needles, true)) {
                return true;
            }
            if (is_numeric($m)) {
                foreach ($candidates as $c) {
                    if ($c !== null && $c !== '' && is_numeric($c) && (float) $c == (float) $m) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function get_devices()
    {
        return $this->db
            ->select('id, name')
            ->where('is_deleted', false)
            ->order_by('name', 'asc')
            ->get('devices')
            ->result();
    }

    /**
     * Metrics + write targets for the command form.
     * Command device owns feedback. Write may target another collector (J1000 DO)
     * even when the command device is a native SNMP CRAC (STULZ).
     */
    public function get_device_control_context($deviceId, $writeDeviceId = 0)
    {
        $deviceId = (int) $deviceId;
        $row = $this->load_device_row($deviceId, true);
        if (!$row) {
            return null;
        }

        $metrics = $this->decode_json($row->metrics);
        $comm = $this->decode_json($row->communication_config);
        $config = $this->decode_json($row->config);
        $role = strtolower((string) ($config['role'] ?? ''));
        $protocol = strtolower((string) ($comm['protocol'] ?? $row->template_protocol ?? ''));
        $isEndpoint = $role === 'endpoint' || $protocol === 'gateway_endpoint';

        $gatewayId = (int) ($comm['source']['gateway_device_id'] ?? $config['via_gateway'] ?? 0);
        $gateway = $gatewayId > 0 ? $this->load_device_row($gatewayId, false) : null;

        $feedback = [];
        foreach ($metrics as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $key = trim((string) ($metric['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $src = is_array($metric['source'] ?? null) ? $metric['source'] : [];
            $gwKey = trim((string) ($src['gateway_metric_key'] ?? $src['metric_key'] ?? ''));
            $feedback[] = [
                'key' => $key,
                'label' => (string) ($metric['label'] ?? $key),
                'type' => (string) ($metric['type'] ?? ''),
                'from_gateway' => $gwKey !== '' ? $gwKey : null,
            ];
        }

        $explicitWrite = ((int) $writeDeviceId) > 0 ? $this->load_device_row((int) $writeDeviceId, false) : null;
        $writeRow = $explicitWrite ?: ($isEndpoint && $gateway ? $gateway : $row);
        $resolvedWriteId = (int) $writeRow->id;
        $crossDevice = $resolvedWriteId !== (int) $row->id;
        $writeMetrics = $this->decode_json($writeRow->metrics);

        $writeTargets = [];
        foreach ($writeMetrics as $metric) {
            if (!is_array($metric) || !$this->is_write_target_metric($metric)) {
                continue;
            }
            $built = $this->build_write_target($metric, $crossDevice ? $resolvedWriteId : null);
            if ($built) {
                $writeTargets[] = $built;
            }
        }

        return [
            'id' => (int) $row->id,
            'name' => $row->name,
            'role' => $isEndpoint ? 'endpoint' : ($role === 'gateway' ? 'gateway' : 'device'),
            'protocol' => $protocol,
            'template_name' => $row->template_name ?? null,
            'gateway' => $gateway ? [
                'id' => (int) $gateway->id,
                'name' => $gateway->name,
            ] : null,
            'write_via' => [
                'id' => $resolvedWriteId,
                'name' => $writeRow->name,
                'cross_device' => $crossDevice,
            ],
            'default_write_via_id' => $isEndpoint && $gateway ? (int) $gateway->id : 0,
            'feedback_metrics' => $feedback,
            'write_targets' => $writeTargets,
        ];
    }

    private function load_device_row($deviceId, $withTemplate = false)
    {
        $deviceId = (int) $deviceId;
        if ($deviceId <= 0) {
            return null;
        }
        if ($withTemplate) {
            return $this->db
                ->select('d.id, d.name, d.metrics, d.communication_config, d.config, t.protocol AS template_protocol, t.name AS template_name')
                ->from('devices d')
                ->join('device_templates t', 't.id = d.device_template_id', 'left')
                ->where('d.id', $deviceId)
                ->where('d.is_deleted', false)
                ->get()
                ->row();
        }
        return $this->db
            ->select('id, name, metrics, communication_config, config')
            ->from('devices')
            ->where('id', $deviceId)
            ->where('is_deleted', false)
            ->get()
            ->row();
    }

    private function decode_json($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function is_write_target_metric(array $metric)
    {
        $key = strtoupper(trim((string) ($metric['key'] ?? '')));
        $src = is_array($metric['source'] ?? null) ? $metric['source'] : [];
        $kind = strtolower((string) ($src['kind'] ?? ''));
        $oid = (string) ($metric['oid'] ?? '');
        if (preg_match('/^DO\d+$/', $key)) {
            return true;
        }
        if (in_array($kind, ['digital_output', 'do', 'relay'], true)) {
            return true;
        }
        if (strpos($oid, '1.3.6.1.4.1.12020.1.3.') === 0) {
            return true;
        }
        if (!empty($metric['writable']) || strtolower((string) ($metric['access'] ?? '')) === 'write') {
            return true;
        }
        return false;
    }

    private function build_write_target(array $metric, $gatewayDeviceId = null)
    {
        $key = trim((string) ($metric['key'] ?? ''));
        if ($key === '') {
            return null;
        }
        $oid = trim((string) ($metric['oid'] ?? ''));
        $type = strtolower((string) ($metric['type'] ?? ''));
        $isJ1000Do = strpos($oid, '1.3.6.1.4.1.12020.1.3.') === 0 || preg_match('/^DO\d+$/i', $key);

        $protocol = 'snmp_set';
        if (strpos($type, 'modbus') === 0) {
            $protocol = 'modbus_write';
        } elseif (strpos($type, 'bacnet') === 0) {
            $protocol = 'bacnet_write';
        } elseif (strpos($type, 'mqtt') === 0) {
            $protocol = 'mqtt_pub';
        }

        $writeConfig = [
            'metric_key' => $key,
        ];
        if ($oid !== '') {
            $writeConfig['oid'] = $oid;
        }
        if ($gatewayDeviceId) {
            $writeConfig['gateway_device_id'] = (int) $gatewayDeviceId;
        }
        if ($isJ1000Do) {
            $writeConfig['on_value'] = 3;
            $writeConfig['off_value'] = 0;
        } else {
            $writeConfig['on_value'] = 1;
            $writeConfig['off_value'] = 0;
        }
        if (isset($metric['function_code'])) {
            $writeConfig['function_code'] = $metric['function_code'];
        }
        if (isset($metric['address'])) {
            $writeConfig['address'] = $metric['address'];
        }

        $label = (string) ($metric['label'] ?? $key);
        $hint = $oid !== '' ? $oid : $key;
        if ($gatewayDeviceId) {
            $hint = 'gateway DO / dry contact · ' . $hint;
        }

        return [
            'key' => $key,
            'label' => $label,
            'hint' => $hint,
            'protocol' => $protocol,
            'write_config' => $writeConfig,
        ];
    }
}
