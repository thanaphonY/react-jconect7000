<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Metrics_model extends CI_Model
{

    protected $db_ts;

    public function __construct()
    {
        parent::__construct();
        // Load secondary database connection for TimescaleDB
        $this->db_ts = $this->load->database('timescaledb', TRUE);
    }

    /**
     * Get time series data for a device's metric
     *
     * @param int \$device_id
     * @param string \$key
     * @param string \$range (e.g., '24h', '7d', '30d')
     * @return array
     */
    public function get_timeseries($device_id, $key, $range = '24h')
    {
        return $this->db_ts
            ->select('timestamp, value')
            ->from('device_data')
            ->where('device_id', $device_id)
            ->where('key', $key)
            ->where("timestamp > now() - interval '{$range}'", null, false)
            ->order_by('timestamp', 'asc')
            ->get()
            ->result_array();
    }

    public function get_timeseries_chart($device_id, $key, $range = '24h')
    {
        $this->db_ts->reset_query();
        // กำหนด bucket interval และ format ตาม range
        if ($range === '24h') {
            $interval = '1 hour';
            $format = 'Y-m-d H:00:00';
            $php_interval = new DateInterval('PT1H');
            $start = (new DateTime('now', new DateTimeZone('UTC')))->sub(new DateInterval('PT24H'));
        } elseif ($range === '31d') {
            $interval = '1 day';
            $format = 'Y-m-d 00:00:00';
            $php_interval = new DateInterval('P1D');
            $start = (new DateTime('now', new DateTimeZone('UTC')))->sub(new DateInterval('P31D'));
        } elseif ($range === '1y') {
            $interval = '1 month';
            $format = 'Y-m-01 00:00:00';
            $php_interval = new DateInterval('P1M');
            $start = (new DateTime('now', new DateTimeZone('UTC')))->sub(new DateInterval('P1Y'));
        } else {
            // fallback: ดึงข้อมูลดิบตามช่วงเวลา (ไม่เติม null/gap)
            $sql = "
                SELECT timestamp, value
                FROM device_data
                WHERE device_id = ? AND key = ? AND timestamp >= now() - interval ?
                ORDER BY timestamp ASC
            ";
            $rows = $this->db_ts->query($sql, [$device_id, $key, $range])->result_array();
            // return array of ['timestamp' => ..., 'value' => ...]
            return $rows;
        }

        // ดึงข้อมูลจริงจาก DB
        if ($range === '24h') {
            $sql = "
                SELECT time_bucket('1 hour', timestamp) AS bucket, AVG(value) AS value
                FROM device_data
                WHERE device_id = ? AND key = ? AND timestamp >= now() - interval '24 hour'
                GROUP BY bucket
                ORDER BY bucket
            ";
        } elseif ($range === '31d') {
            $sql = "
                SELECT date_trunc('day', timestamp) AS bucket, AVG(value) AS value
                FROM device_data
                WHERE device_id = ? AND key = ? AND timestamp >= now() - interval '31 day'
                GROUP BY bucket
                ORDER BY bucket
            ";
        } elseif ($range === '1y') {
            $sql = "
                SELECT date_trunc('month', timestamp) AS bucket, AVG(value) AS value
                FROM device_data
                WHERE device_id = ? AND key = ? AND timestamp >= now() - interval '1 year'
                GROUP BY bucket
                ORDER BY bucket
            ";
        }
        $rows = $this->db_ts->query($sql, [$device_id, $key])->result_array();
        // map bucket => value
        $data = [];
        foreach ($rows as $row) {
            $data[(new DateTime($row['bucket'], new DateTimeZone('UTC')))->format($format)] = (float)$row['value'];
        }
        // สร้างช่วงเวลาทั้งหมด
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $buckets = [];
        $cur = clone $start;
        while ($cur <= $now) {
            $buckets[] = $cur->format($format);
            $cur->add($php_interval);
        }
        // สร้าง series/timestamps โดยเติม null ถ้าไม่มีข้อมูล
        $series = [];
        $timestamps = [];
        foreach ($buckets as $b) {
            $series[] = array_key_exists($b, $data) ? $data[$b] : null;
            $timestamps[] = $b;
        }
        return array_map(function($ts, $val) {
            return ['timestamp' => $ts, 'value' => $val];
        }, $timestamps, $series);
    }


    /**
     * Get latest value per metric key for device
     *
     * @param int \$device_id
     * @return array
     */
    public function get_latest_by_device($device_id)
    {
        $sql = "
            SELECT DISTINCT ON (key)
                   key,
                   value,
                   value_text,
                   value_bool,
                   value_json,
                   timestamp
            FROM device_data
            WHERE device_id = ?
            ORDER BY key, timestamp DESC
        ";
        $query = $this->db_ts->query($sql, [$device_id]);
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




    public function get_metrics_by_device($device_id)
    {
        $this->db->select('metrics');
        $this->db->from('devices');
        $this->db->where('id', $device_id);
        $query = $this->db->get()->row();

        if ($query && !empty($query->metrics)) {
            return json_decode($query->metrics, true);
        }

        return [];
    }


    public function get_latest_value($device_id, $key)
    {
        return $this->db_ts
            ->select('value, value_text, value_bool, value_json, timestamp')
            ->from('device_data')
            ->where('device_id', $device_id)
            ->where('key', $key)
            ->order_by('timestamp', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Resolve typed device_data columns into a JSON-friendly value for widgets.
     *
     * Boolean metrics are stored in value_bool; the numeric value column is often 0/null.
     */
    public function resolve_typed_value($row, $datatype = '')
    {
        if (!is_array($row)) {
            return null;
        }

        $dt = strtolower(trim((string) $datatype));
        $value = $row['value'] ?? null;
        $valueText = $row['value_text'] ?? null;
        $valueBool = $row['value_bool'] ?? null;
        $valueJson = $row['value_json'] ?? null;

        $boolTruth = function ($v) {
            if (is_bool($v)) {
                return $v;
            }
            if ($v === 1 || $v === 1.0 || $v === '1' || $v === 't' || $v === 'true' || $v === 'TRUE' || $v === 'True') {
                return true;
            }
            if ($v === 0 || $v === 0.0 || $v === '0' || $v === 'f' || $v === 'false' || $v === 'FALSE' || $v === 'False') {
                return false;
            }
            return null;
        };

        if ($dt === 'bool' || $dt === 'boolean') {
            $b = $boolTruth($valueBool);
            if ($b === null) {
                $b = $boolTruth($value);
            }
            if ($b === null && $valueText !== null && $valueText !== '') {
                $b = $boolTruth($valueText);
            }
            return $b;
        }

        if ($dt === 'string') {
            if ($valueText !== null && $valueText !== '') {
                return (string) $valueText;
            }
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
            $b = $boolTruth($valueBool);
            if ($b !== null) {
                return $b ? 'True' : 'False';
            }
            return null;
        }

        if ($dt === 'json') {
            if ($valueJson !== null && $valueJson !== '') {
                if (is_string($valueJson)) {
                    $decoded = json_decode($valueJson, true);
                    return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $valueJson;
                }
                return $valueJson;
            }
            if ($valueText !== null && $valueText !== '') {
                return (string) $valueText;
            }
            return null;
        }

        if ($dt === 'int' || $dt === 'int16' || $dt === 'uint16' || $dt === 'int32' || $dt === 'uint32') {
            if ($value !== null && !(is_string($value) && trim($value) === '')) {
                return (int) $value;
            }
            $b = $boolTruth($valueBool);
            if ($b !== null) {
                return $b ? 1 : 0;
            }
            return null;
        }

        // float / default numeric
        if ($value !== null && !(is_string($value) && trim($value) === '')) {
            if (is_numeric($value)) {
                return (float) $value;
            }
            return (string) $value;
        }
        if ($valueText !== null && $valueText !== '') {
            return (string) $valueText;
        }
        $b = $boolTruth($valueBool);
        if ($b !== null) {
            return $b;
        }
        return null;
    }

    public function get_metric_datatype($device_id, $metric_key)
    {
        $metrics = $this->get_metrics_by_device($device_id);
        if (!is_array($metrics)) {
            return '';
        }
        foreach ($metrics as $m) {
            if (!is_array($m)) {
                continue;
            }
            if (($m['key'] ?? '') === $metric_key) {
                return (string) ($m['datatype'] ?? '');
            }
        }
        return '';
    }
}
