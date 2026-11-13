<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Health_checks_model extends CI_Model
{
    public function default_template()
    {
        return "{name}\n{datetime}\nStatus: {status}\n\n{alarms}";
    }

    public function get_all()
    {
        $rows = $this->db->order_by('next_run_at', 'ASC')->get('health_checks')->result();
        foreach ($rows as $row) {
            $this->_hydrate($row);
        }
        return $rows;
    }

    public function get($id)
    {
        $row = $this->db->get_where('health_checks', ['id' => (int) $id], 1)->row();
        if ($row) {
            $this->_hydrate($row);
        }
        return $row;
    }

    public function get_due()
    {
        $this->db->where('is_active', true);
        $this->db->where('next_run_at <= NOW()', null, false);
        $this->db->order_by('next_run_at', 'ASC');
        $rows = $this->db->get('health_checks')->result();
        foreach ($rows as $row) {
            $this->_hydrate($row);
        }
        return $rows;
    }

    public function save($data)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $template = trim((string) ($data['message_template'] ?? ''));
        if ($template === '') {
            $template = $this->default_template();
        }
        $config = [
            'time' => $this->_time($data['schedule_time'] ?? '08:00'),
            'weekday' => (int) ($data['schedule_weekday'] ?? 1),
            'day' => min(28, max(1, (int) ($data['schedule_day'] ?? 1))),
        ];
        $channels = array_values(array_filter(array_map('intval', (array) ($data['channel_ids'] ?? []))));
        $rooms = array_values(array_filter(array_map('trim', (array) ($data['rooms'] ?? []))));
        $row = [
            'name' => trim((string) ($data['name'] ?? '')),
            'is_active' => !empty($data['is_active']),
            'schedule_type' => in_array($data['schedule_type'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $data['schedule_type'] : 'daily',
            'schedule_config' => json_encode($config),
            'timezone' => 'Asia/Bangkok',
            'channel_ids' => json_encode($channels),
            'rooms' => json_encode($rooms),
            'message_template' => $template,
        ];
        if ($row['name'] === '') {
            return 0;
        }
        if ($id > 0) {
            $this->db->where('id', $id);
            $this->db->update('health_checks', $row);
        } else {
            $this->db->insert('health_checks', $row);
            $id = (int) $this->db->insert_id();
        }
        $this->_update_next_run($id);
        return $id;
    }

    public function delete($id)
    {
        $this->db->where('id', (int) $id);
        $this->db->delete('health_checks');
        return $this->db->affected_rows() > 0;
    }

    /**
     * Advance next_run_at only while the row is still due.
     */
    public function claim($id)
    {
        $row = $this->get($id);
        if (!$row) {
            return false;
        }
        $next = $this->_compute_next_run($row);
        $this->db->where('id', (int) $id);
        $this->db->where('next_run_at <= NOW()', null, false);
        $this->db->set('last_run_at', 'NOW()', false);
        $this->db->set('next_run_at', $next);
        $this->db->update('health_checks');
        return $this->db->affected_rows() > 0;
    }

    public function render($row)
    {
        $alarmCount = $this->active_alarm_count();
        $alarms = $alarmCount ? $this->open_alarms() : [];
        $status = $alarmCount ? 'Attention' : 'System Normal';
        $alarmText = '';
        if ($alarms) {
            $lines = ['Open alarms'];
            foreach (array_slice($alarms, 0, 20) as $alarm) {
                $where = trim(($alarm['room'] ? $alarm['room'] . ' / ' : '') . $alarm['device']);
                $lines[] = '- ' . ($where !== '' ? $where . ': ' : '') . $alarm['message'];
            }
            $alarmText = implode("\n", $lines);
        }
        $tz = new DateTimeZone($row->timezone ?: 'Asia/Bangkok');
        $now = new DateTime('now', $tz);
        $template = $row->message_template ?: $this->default_template();
        $template = $this->_replace_metrics($template);
        $replace = [
            '{name}' => $row->name,
            '{datetime}' => $now->format('d/m/Y H:i'),
            '{status}' => $status,
            '{alarms_count}' => (string) $alarmCount,
            '{alarms}' => $alarmText,
        ];
        return [
            'status' => $alarms ? 'attention' : 'normal',
            'message' => strtr($template, $replace),
        ];
    }

    public function deliver($row, $rendered)
    {
        $results = [];
        $ids = $row->channel_ids;
        if (!$ids) {
            return [['name' => '', 'type' => '', 'success' => false, 'error' => 'No channel selected']];
        }
        $channels = $this->db->where_in('id', $ids)->where('is_deleted', false)->get('notification_channels')->result();
        $byId = [];
        foreach ($channels as $channel) {
            $byId[(int) $channel->id] = $channel;
        }
        foreach ($ids as $id) {
            $channel = $byId[$id] ?? null;
            if (!$channel || !$this->_flag($channel->is_active)) {
                $results[] = ['channel_id' => $id, 'name' => $channel->name ?? '', 'type' => $channel->type ?? '', 'success' => false, 'error' => 'Channel is inactive'];
                continue;
            }
            $config = json_decode($channel->config ?? '', true);
            if (!is_array($config)) {
                $config = [];
            }
            $sent = $this->_send_channel($channel->type, $config, $rendered['message'], $row->name);
            $results[] = [
                'channel_id' => (int) $channel->id,
                'name' => $channel->name,
                'type' => $channel->type,
                'success' => !empty($sent['success']),
                'error' => $sent['error'] ?? '',
            ];
        }
        return $results;
    }

    public function write_log($id, $rendered, $results)
    {
        $ok = true;
        foreach ($results as $result) {
            if (empty($result['success'])) {
                $ok = false;
                break;
            }
        }
        $this->db->insert('health_check_logs', [
            'health_check_id' => (int) $id,
            'status' => $ok ? $rendered['status'] : 'failed',
            'message' => $rendered['message'],
            'channel_results' => json_encode($results),
        ]);
        return $ok;
    }

    public function active_alarm_count()
    {
        return (int) $this->db->where('status', 'active')->count_all_results('alarm_logs');
    }

    public function open_alarms()
    {
        $this->db->select('al.message, al.metric_key, d.name AS device_name, d.location');
        $this->db->from('alarm_logs al');
        $this->db->join('devices d', 'd.id = al.device_id', 'left');
        $this->db->where('al.status', 'active');
        $this->db->order_by('al.created_at', 'DESC');
        $this->db->limit(20);
        $rows = $this->db->get()->result_array();
        $out = [];
        foreach ($rows as $row) {
            $message = trim((string) ($row['message'] ?? ''));
            if ($message === '') {
                $message = trim((string) ($row['metric_key'] ?? 'Alarm'));
            }
            $out[] = [
                'room' => (string) ($row['location'] ?? ''),
                'device' => (string) ($row['device_name'] ?? ''),
                'message' => $message,
            ];
        }
        return $out;
    }

    /**
     * Replace {device_id.metric_key} with the latest reading, for example {550.temperature}.
     */
    private function _replace_metrics($template)
    {
        if (!preg_match_all('/\{(\d+)\.([^}]+)\}/', $template, $matches, PREG_SET_ORDER)) {
            return $template;
        }
        $pairs = [];
        foreach ($matches as $match) {
            $pairs[$match[1] . "\0" . strtolower($match[2])] = [(int) $match[1], $match[2]];
        }
        $sql = "
            SELECT DISTINCT ON (dd.device_id, lower(dd.key))
                dd.device_id, dd.key, dd.value, d.metrics
            FROM device_data dd
            JOIN devices d ON d.id = dd.device_id
            WHERE " . implode(' OR ', array_fill(0, count($pairs), '(dd.device_id = ? AND lower(dd.key) = lower(?))')) . "
            ORDER BY dd.device_id, lower(dd.key), dd.timestamp DESC
        ";
        $params = [];
        foreach ($pairs as $pair) {
            $params[] = $pair[0];
            $params[] = $pair[1];
        }
        $found = [];
        foreach ($this->db->query($sql, $params)->result_array() as $row) {
            $found[(int) $row['device_id'] . "\0" . strtolower($row['key'])] = $this->_format_reading($row);
        }
        return preg_replace_callback('/\{(\d+)\.([^}]+)\}/', function ($match) use ($found) {
            $key = (int) $match[1] . "\0" . strtolower($match[2]);
            return $found[$key] ?? '-';
        }, $template);
    }

    private function _format_reading(array $row)
    {
        $unit = '';
        $metrics = json_decode($row['metrics'] ?? '', true);
        if (is_array($metrics)) {
            foreach ($metrics as $metric) {
                if (is_array($metric) && strcasecmp((string) ($metric['key'] ?? ''), (string) $row['key']) === 0) {
                    $unit = trim((string) ($metric['unit'] ?? ''));
                    break;
                }
            }
        }
        $value = trim((string) $row['value']);
        if (is_numeric($value)) {
            $value = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        }
        return $unit !== '' ? $value . ' ' . $unit : $value;
    }

    private function _hydrate($row)
    {
        $config = is_string($row->schedule_config) ? json_decode($row->schedule_config, true) : (array) $row->schedule_config;
        $row->schedule_config = is_array($config) ? $config : [];
        $row->schedule_time = $row->schedule_config['time'] ?? '08:00';
        $row->schedule_weekday = (int) ($row->schedule_config['weekday'] ?? 1);
        $row->schedule_day = (int) ($row->schedule_config['day'] ?? 1);
        $channels = is_string($row->channel_ids) ? json_decode($row->channel_ids, true) : (array) $row->channel_ids;
        $rooms = is_string($row->rooms) ? json_decode($row->rooms, true) : (array) $row->rooms;
        $row->channel_ids = array_values(array_map('intval', is_array($channels) ? $channels : []));
        $row->rooms = array_values(array_filter(array_map('strval', is_array($rooms) ? $rooms : [])));
        $row->is_active = $this->_flag($row->is_active);
        $row->next_run_label = $this->_label($row->next_run_at ?? null);
        $row->last_run_label = $this->_label($row->last_run_at ?? null);
    }

    private function _flag($value)
    {
        return $value === true || $value === 't' || $value === '1' || $value === 1;
    }

    private function _label($value)
    {
        if (!$value) {
            return '-';
        }
        try {
            $dt = new DateTime($value);
            $dt->setTimezone(new DateTimeZone('Asia/Bangkok'));
            return $dt->format('d/m/Y H:i');
        } catch (Exception $e) {
            return '-';
        }
    }

    private function _time($value)
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{1,2}:\d{2}/', $value)) {
            return '08:00';
        }
        return substr($value, 0, 5);
    }

    private function _update_next_run($id)
    {
        $row = $this->get($id);
        if (!$row) {
            return;
        }
        $this->db->where('id', (int) $id);
        $this->db->update('health_checks', ['next_run_at' => $this->_compute_next_run($row)]);
    }

    private function _compute_next_run($row, DateTime $now = null)
    {
        $tz = new DateTimeZone($row->timezone ?: 'Asia/Bangkok');
        $now = $now ? (clone $now) : new DateTime('now', $tz);
        $now->setTimezone($tz);
        $config = $row->schedule_config;
        $time = $this->_time($config['time'] ?? '08:00');
        $hour = (int) substr($time, 0, 2);
        $minute = (int) substr($time, 3, 2);
        $type = $row->schedule_type ?: 'daily';
        $day = min(28, max(1, (int) ($config['day'] ?? 1)));

        if ($type === 'weekly') {
            $weekday = (int) ($config['weekday'] ?? 1);
            if ($weekday < 1 || $weekday > 7) {
                $weekday = 1;
            }
            $next = (clone $now)->setTime($hour, $minute, 0);
            $diff = ($weekday - (int) $next->format('N') + 7) % 7;
            if ($diff === 0 && $next <= $now) {
                $diff = 7;
            }
            if ($diff > 0) {
                $next->modify('+' . $diff . ' days');
            }
        } elseif ($type === 'monthly') {
            $next = (clone $now)->setTime($hour, $minute, 0);
            $next->setDate((int) $now->format('Y'), (int) $now->format('m'), $day);
            if ($next <= $now) {
                $next->modify('first day of next month');
                $next->setTime($hour, $minute, 0);
                $next->setDate((int) $next->format('Y'), (int) $next->format('m'), $day);
            }
        } else {
            $next = (clone $now)->setTime($hour, $minute, 0);
            if ($next <= $now) {
                $next->modify('+1 day');
            }
        }

        $guard = 0;
        while ($next <= $now && $guard < 24) {
            if ($type === 'weekly') {
                $next->modify('+7 days');
            } elseif ($type === 'monthly') {
                $next->modify('first day of next month');
                $next->setTime($hour, $minute, 0);
                $next->setDate((int) $next->format('Y'), (int) $next->format('m'), $day);
            } else {
                $next->modify('+1 day');
            }
            $guard++;
        }
        $next->setTimezone(new DateTimeZone('UTC'));
        return $next->format('Y-m-d H:i:sP');
    }

    private function _send_channel($type, array $config, $message, $name)
    {
        $type = strtolower((string) $type);
        if ($type === 'line' || $type === 'line_oa') {
            $token = $config['access_token'] ?? '';
            $to = $config['to'] ?? ($config['user_id'] ?? '');
            if ($token === '' || $to === '') {
                return ['success' => false, 'error' => 'LINE token or recipient is missing'];
            }
            return $this->_http_json('https://api.line.me/v2/bot/message/push', [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ], ['to' => $to, 'messages' => [['type' => 'text', 'text' => $message]]]);
        }
        if ($type === 'telegram') {
            $token = $config['bot_token'] ?? '';
            $chat = $config['chat_id'] ?? '';
            if ($token === '' || $chat === '') {
                return ['success' => false, 'error' => 'Telegram token or chat id is missing'];
            }
            return $this->_http_json('https://api.telegram.org/bot' . $token . '/sendMessage', [
                'Content-Type: application/json',
            ], ['chat_id' => $chat, 'text' => $message]);
        }
        if ($type === 'webhook') {
            $url = $config['url'] ?? '';
            if ($url === '') {
                return ['success' => false, 'error' => 'Webhook URL is missing'];
            }
            $headers = ['Content-Type: application/json'];
            $extra = json_decode($config['headers'] ?? '', true);
            if (is_array($extra)) {
                foreach ($extra as $key => $value) {
                    $headers[] = $key . ': ' . $value;
                }
            }
            $payload = $config['payload'] ?? '';
            if ($payload !== '') {
                $body = str_replace(['{{message}}', '{message}'], $message, $payload);
                $decoded = json_decode($body, true);
                $body = is_array($decoded) ? $decoded : ['message' => $message];
            } else {
                $body = ['message' => $message];
            }
            return $this->_http_json($url, $headers, $body);
        }
        if ($type === 'email') {
            return $this->_send_email($config, $message, $name);
        }
        return ['success' => false, 'error' => 'Unsupported channel type'];
    }

    private function _send_email(array $config, $message, $name)
    {
        $rawTo = (string) ($config['receivers'] ?? ($config['to'] ?? ''));
        $receivers = array_filter(array_map('trim', preg_split('/[,\n]/', $rawTo)));
        $host = $config['host'] ?? '';
        if ($host === '' || !$receivers) {
            return ['success' => false, 'error' => 'Email host or receivers are missing'];
        }
        require_once APPPATH . 'libraries/phpmailer/class.phpmailer.php';
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) ($config['port'] ?? 25);
        $secure = $config['secure'] ?? '';
        if ($secure !== '') {
            $mail->SMTPSecure = $secure;
        }
        $user = $config['username'] ?? ($config['SMTP_EMAIL'] ?? '');
        $pass = $config['password'] ?? ($config['SMTP_PASSWORD'] ?? '');
        $from = $config['from'] ?? ($user !== '' ? $user : 'healthcheck@localhost');
        $fromName = $config['sendername'] ?? 'JConnect';
        if ($user !== '' && $pass !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
        } else {
            $mail->SMTPAuth = false;
        }
        $mail->setFrom($from, $fromName);
        foreach ($receivers as $receiver) {
            $mail->addAddress($receiver);
        }
        $mail->Subject = ($config['subject'] ?? '') !== '' ? $config['subject'] : ('Health Check: ' . $name);
        $mail->Body = $message;
        $mail->isHTML(false);
        if (!$mail->send()) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
        return ['success' => true];
    }

    private function _http_json($url, array $headers, array $body)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        if ($code >= 400) {
            return ['success' => false, 'error' => 'HTTP ' . $code];
        }
        return ['success' => true, 'response' => $response];
    }
}
