<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Widget_model extends CI_Model
{

    public function get_widget_with_config($widget_id)
    {
        return $this->db
            ->select('dashboard_layout_items.*, widgets.widget_type')
            ->from('dashboard_layout_items')
            ->join('widgets', 'widgets.id = dashboard_layout_items.widget_id', 'left')
            ->where('dashboard_layout_items.id', $widget_id)
            ->get()
            ->row();
    }
    public function get_line_chart_data($config)
    {

        $series_items = $config->items;
        date_default_timezone_set('UTC');

        $datasets = [];
        $labels = [];

        $start = new DateTime('-24 hours', new DateTimeZone('UTC'));
        $minute = (int)$start->format('i');
        $adjustedMinute = $minute - ($minute % 15);
        $start->setTime((int)$start->format('H'), $adjustedMinute, 0);
        $end = new DateTime(null, new DateTimeZone('UTC'));
        $interval = new DateInterval('PT15M');
        $period = new DatePeriod($start, $interval, $end);

        $timeBuckets = [];
        foreach ($period as $dt) {
            $dt->setTimezone(new DateTimeZone('UTC'));
            $ts = $dt->format('Y-m-d H:i:00');
            $labels[] = $dt->format(DateTime::ATOM);
            $timeBuckets[$ts] = null;
        }

        $timeKeys = array_keys($timeBuckets);
        $labels = array_map(fn($ts) => (new DateTime($ts))->format(DateTime::ATOM), $timeKeys);


        foreach ($series_items as $s) {
            $query = $this->db->query("
            SELECT to_char(date_trunc('minute', time_bucket('15 minutes', timestamp)), 'YYYY-MM-DD HH24:MI:00') as bucket,
                   AVG(value) as avg_value
            FROM device_data
            WHERE device_id = ?
              AND key = ?
              AND timestamp >= (NOW() AT TIME ZONE 'UTC') - INTERVAL '24 hours'
            GROUP BY bucket
            ORDER BY bucket ASC
        ", [$s->device_id, $s->metric_key]);

            $raw = $query->result_array();
            $merged = $timeBuckets;

            foreach ($raw as $row) {
                $merged[$row['bucket']] = round((float)$row['avg_value'], 2);
            }

            $deviceInfo = $this->db
                ->where('id', $s->device_id)
                ->from('devices')
                ->get()
                ->row();

            $lastData = $this->db
                ->where(['device_id' => $s->device_id,'key'=> $s->metric_key])
                ->from('device_data')
                ->order_by('timestamp','desc')
                ->limit(1)
                ->get()
                ->row();

            $datasets[] = [
                'label' => $s->label ?? $s->metric_key,
                'data' => array_map(fn($k) => $merged[$k], $timeKeys),
                'borderColor' => $s->color ?? '#007bff',
                'borderWidth' => 2,
                'fill' => false,
                'device_name' => $deviceInfo->name ?? '',
                'location' => $deviceInfo->location ?? '',
                'description' => $deviceInfo->description ?? '',
                'unit' => $s->unit,
                'lastdata' => $lastData->value ??'-',
                'lasttime' => $lastData->timestamp ??'',
            ];
        }


        $result = [
            'labels' => $labels,
            'datasets' => $datasets,
            'yMin' => $config->yMin === '' ? null : intval($config->yMin ?? null),
            'yMax' => $config->yMax === '' ? null : intval($config->yMax ?? null),
            'yTitle' => $config->yTitle ?? '',
            'xTitle' => $config->xTitle ?? '',
            'yUnit' => $config->yUnit ?? ''
        ];

        return $result;
    }

    public function get_bar_chart_data($config)
    {
        $config = is_array($config) ? (object)$config : $config;
        $items = $config->items ?? [];
        if (empty($items)) {
            return [
                'labels' => [],
                'datasets' => [],
                'yMin' => null,
                'yMax' => null,
                'yTitle' => $config->yTitle ?? '',
                'yUnit' => $config->yUnit ?? '',
                'indexAxis' => ($config->orientation ?? 'horizontal') === 'vertical' ? 'x' : 'y',
                'chartType' => 'bar',
            ];
        }

        $rows = [];
        foreach ($items as $idx => $item) {
            $latest = $this->db
                ->where(['device_id' => $item->device_id, 'key' => $item->metric_key])
                ->from('device_data')
                ->order_by('timestamp', 'desc')
                ->limit(1)
                ->get()
                ->row();

            $deviceInfo = $this->db
                ->where('id', $item->device_id)
                ->from('devices')
                ->get()
                ->row();

            $value = isset($latest->value) && is_numeric($latest->value) ? (float)$latest->value : null;
            $rows[] = [
                'label' => $item->label ?? $item->metric_key ?? ('Series ' . ($idx + 1)),
                'value' => $value,
                'color' => $item->color ?? '#0d6efd',
                'unit' => $item->unit ?? ($config->yUnit ?? ''),
                'device_name' => $deviceInfo->name ?? '',
            ];
        }

        $sortOrder = strtolower($config->sort_order ?? 'desc');
        if ($sortOrder === 'asc' || $sortOrder === 'desc') {
            usort($rows, function ($a, $b) use ($sortOrder) {
                $av = $a['value'];
                $bv = $b['value'];
                if ($av === $bv) return 0;
                if ($av === null) return 1;
                if ($bv === null) return -1;
                return $sortOrder === 'asc' ? ($av <=> $bv) : ($bv <=> $av);
            });
        }

        $limit = (int)($config->limit ?? 0);
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        $setpointValue = ($config->setpoint_value ?? '') === '' ? null : (is_numeric($config->setpoint_value) ? (float)$config->setpoint_value : null);
        $setpointColor = $config->setpoint_color ?? '#f59e0b';
        $overColor = $config->bar_color_over ?? '#ef4444';
        $underColor = $config->bar_color_under ?? '#f59e0b';
        $indexAxis = ($config->orientation ?? 'horizontal') === 'vertical' ? 'x' : 'y';

        $barColors = array_map(function ($row) use ($setpointValue, $overColor, $underColor) {
            if ($setpointValue !== null && $row['value'] !== null && $row['value'] > $setpointValue) {
                return $overColor;
            }
            if ($setpointValue !== null && $row['value'] !== null && $row['value'] < $setpointValue) {
                return $underColor;
            }
            return $row['color'];
        }, $rows);

        $resolvedUnit = trim((string)($config->yUnit ?? '')) !== ''
            ? (string)$config->yUnit
            : (string)($rows[0]['unit'] ?? '');

        $datasets = [[
            'label' => $config->yTitle ?? ($config->label ?? 'Bar Chart'),
            'data' => array_map(fn($row) => $row['value'], $rows),
            'backgroundColor' => $barColors,
            'borderColor' => $barColors,
            'borderWidth' => 1,
            'borderRadius' => 6,
            'unit' => $resolvedUnit,
        ]];

        return [
            'labels' => array_map(fn($row) => $row['label'], $rows),
            'datasets' => $datasets,
            'yMin' => $config->yMin === '' ? null : (is_numeric($config->yMin ?? null) ? (float)$config->yMin : null),
            'yMax' => $config->yMax === '' ? null : (is_numeric($config->yMax ?? null) ? (float)$config->yMax : null),
            'yTitle' => $config->yTitle ?? '',
            'yUnit' => $resolvedUnit,
            'indexAxis' => $indexAxis,
            'chartType' => 'bar',
            'setpoint_value' => $setpointValue,
            'setpoint_color' => $setpointColor,
        ];
    }

    /**
     * Parse time_range (6h, 12h, 24h, 7d, 30d) to PHP relative string and SQL interval.
     */
    private function _parse_time_range($time_range)
    {
        $map = [
            '6h' => ['php' => '-6 hours', 'sql' => '6 hours'],
            '12h' => ['php' => '-12 hours', 'sql' => '12 hours'],
            '24h' => ['php' => '-24 hours', 'sql' => '24 hours'],
            '7d' => ['php' => '-7 days', 'sql' => '7 days'],
            '30d' => ['php' => '-30 days', 'sql' => '30 days'],
        ];
        $key = $time_range ?? '24h';
        return $map[$key] ?? $map['24h'];
    }

    /**
     * Parse time_interval (1m, 5m, 10m, 15m, 30m, 1h, 4h, 12h, 24h, 1d) to PostgreSQL bucket and PHP DateInterval.
     */
    private function _parse_time_interval($time_interval)
    {
        $map = [
            '1m' => ['sql' => '1 minute', 'php' => 'PT1M', 'align_sec' => 60, 'tz' => null],
            '5m' => ['sql' => '5 minutes', 'php' => 'PT5M', 'align_sec' => 300, 'tz' => null],
            '10m' => ['sql' => '10 minutes', 'php' => 'PT10M', 'align_sec' => 600, 'tz' => null],
            '15m' => ['sql' => '15 minutes', 'php' => 'PT15M', 'align_sec' => 900, 'tz' => null],
            '30m' => ['sql' => '30 minutes', 'php' => 'PT30M', 'align_sec' => 1800, 'tz' => null],
            '1h' => ['sql' => '1 hour', 'php' => 'PT1H', 'align_sec' => 3600, 'tz' => null],
            '4h' => ['sql' => '4 hours', 'php' => 'PT4H', 'align_sec' => 14400, 'tz' => 'Asia/Bangkok'],
            '12h' => ['sql' => '12 hours', 'php' => 'PT12H', 'align_sec' => 43200, 'tz' => 'Asia/Bangkok'],
            '24h' => ['sql' => '24 hours', 'php' => 'PT24H', 'align_sec' => 86400, 'tz' => 'Asia/Bangkok'],
            '1d' => ['sql' => '1 day', 'php' => 'P1D', 'align_sec' => 86400, 'tz' => 'Asia/Bangkok'],
        ];
        $key = $time_interval ?? '15m';
        return $map[$key] ?? $map['15m'];
    }

    /** GMT+7 offset in seconds (Asia/Bangkok = UTC+7). */
    const GMT7_OFFSET_SEC = 25200;

    /**
     * Round DateTime down to interval boundary so PHP buckets match PostgreSQL time_bucket().
     * For 4h, 12h, 24h, 1d: align to midnight GMT+7 (Asia/Bangkok) so time starts at 00:00 in Thailand.
     */
    private function _align_start_to_interval(DateTime $start, $align_sec)
    {
        if ($align_sec >= 14400) {
            $ts = $start->getTimestamp();
            $aligned = (int)floor(($ts + self::GMT7_OFFSET_SEC) / $align_sec) * $align_sec - self::GMT7_OFFSET_SEC;
            $start->setTimestamp($aligned);
            return $start;
        }
        $ts = $start->getTimestamp();
        $aligned = (int)floor($ts / $align_sec) * $align_sec;
        $start->setTimestamp($aligned);
        return $start;
    }

    /**
     * Build SQL aggregate expression for display (min, max, avg, first, last).
     */
    private function _aggregate_expr($display)
    {
        $d = strtolower($display ?? 'avg');
        switch ($d) {
            case 'min':
                return 'MIN(value)';
            case 'max':
                return 'MAX(value)';
            case 'first':
                return '(array_agg(value ORDER BY timestamp ASC))[1]';
            case 'last':
                return '(array_agg(value ORDER BY timestamp DESC))[1]';
            default:
                return 'AVG(value)';
        }
    }

    public function get_line_chart_data_dynamic($config, $rConfig)
    {
        $config = is_array($config) ? (object)$config : $config;
        $rConfig = is_array($rConfig) ? (object)$rConfig : $rConfig;

        $series_items = $config->items ?? [];
        if (empty($series_items)) {
            return [
                'labels' => [],
                'datasets' => [],
                'yMin' => null, 'yMax' => null, 'yTitle' => '', 'xTitle' => '', 'yUnit' => ''
            ];
        }

        date_default_timezone_set('UTC');

        $time_range = $rConfig->time_range ?? '24h';
        $time_interval = $rConfig->time_interval ?? '15m';
        $limit = (int)($rConfig->limit ?? 24);
        $limit = $limit > 0 ? $limit : 24;
        $display = $rConfig->display ?? 'avg';

        $interval_info = $this->_parse_time_interval($time_interval);
        $agg_expr = $this->_aggregate_expr($display);

        $use_custom_range = ($time_range === 'custom' && !empty($rConfig->start_date) && !empty($rConfig->end_date));
        if ($use_custom_range) {
            $start = \DateTime::createFromFormat('Y-m-d\TH:i', $rConfig->start_date, new \DateTimeZone('UTC'));
            if (!$start) $start = new \DateTime($rConfig->start_date, new \DateTimeZone('UTC'));
            $end = \DateTime::createFromFormat('Y-m-d\TH:i', $rConfig->end_date, new \DateTimeZone('UTC'));
            if (!$end) $end = new \DateTime($rConfig->end_date, new \DateTimeZone('UTC'));
            $start = $this->_align_start_to_interval(clone $start, $interval_info['align_sec']);
        } else {
            $range = $this->_parse_time_range($time_range);
            $start = new DateTime($range['php'], new DateTimeZone('UTC'));
            $start = $this->_align_start_to_interval($start, $interval_info['align_sec']);
            $end = new DateTime('now', new DateTimeZone('UTC'));
        }
        $interval = new DateInterval($interval_info['php']);
        $endForPeriod = clone $end;
        $endForPeriod->add($interval);
        $period = new DatePeriod($start, $interval, $endForPeriod);

        $timeBuckets = [];
        foreach ($period as $dt) {
            $dt->setTimezone(new DateTimeZone('UTC'));
            $ts = $dt->format('Y-m-d H:i:00');
            $timeBuckets[$ts] = null;
        }
        $allKeys = array_keys($timeBuckets);
        $timeKeys = array_slice($allKeys, -$limit);
        $labels = array_map(fn($ts) => (new DateTime($ts))->format(DateTime::ATOM), $timeKeys);

        $bucket_sql = $interval_info['sql'];
        $bucket_esc = $this->db->escape_str($bucket_sql);
        $tz = $interval_info['tz'] ?? null;
        $time_bucket_expr = $tz
            ? "time_bucket('{$bucket_esc}', timestamp, '{$this->db->escape_str($tz)}')"
            : "time_bucket('{$bucket_esc}', timestamp)";
        if (!$use_custom_range) {
            $range = $this->_parse_time_range($time_range);
            $interval_sql = $range['sql'];
            $interval_esc = $this->db->escape_str($interval_sql);
        }
        $start_iso = $start->format('Y-m-d H:i:s');
        $end_iso = $end->format('Y-m-d H:i:s');

        // Collect all bucket keys from DB (format may differ from PHP) and use them as master list
        $allBucketsFromDb = [];
        $allRawBySeries = [];

        foreach ($series_items as $s) {
            if ($use_custom_range) {
                $sql = "
                SELECT to_char({$time_bucket_expr}, 'YYYY-MM-DD HH24:MI:00') as bucket,
                       {$agg_expr} as agg_value
                FROM device_data
                WHERE device_id = ?
                  AND key = ?
                  AND timestamp >= ?
                  AND timestamp <= ?
                GROUP BY 1
                ORDER BY 1 ASC
                ";
                $query = $this->db->query($sql, [$s->device_id, $s->metric_key, $start_iso, $end_iso]);
            } else {
                $sql = "
                SELECT to_char({$time_bucket_expr}, 'YYYY-MM-DD HH24:MI:00') as bucket,
                       {$agg_expr} as agg_value
                FROM device_data
                WHERE device_id = ?
                  AND key = ?
                  AND timestamp >= (NOW() AT TIME ZONE 'UTC') - INTERVAL '{$interval_esc}'
                GROUP BY 1
                ORDER BY 1 ASC
                ";
                $query = $this->db->query($sql, [$s->device_id, $s->metric_key]);
            }
            $raw = $query->result_array();
            $allRawBySeries[] = $raw;
            foreach ($raw as $row) {
                $bk = trim((string)($row['bucket'] ?? ''));
                if ($bk !== '') {
                    $allBucketsFromDb[$bk] = true;
                }
            }
        }

        $timeKeysFromDb = array_keys($allBucketsFromDb);
        sort($timeKeysFromDb);
        $timeKeys = array_slice($timeKeysFromDb, -$limit);
        if (empty($timeKeys)) {
            $timeKeys = array_slice($allKeys, -$limit);
        }
        $labels = array_map(fn($ts) => (new DateTime($ts))->format(DateTime::ATOM), $timeKeys);

        $seriesIndex = 0;
        foreach ($series_items as $s) {
            $raw = $allRawBySeries[$seriesIndex++];
            $valueByBucket = [];
            foreach ($raw as $row) {
                $bk = trim((string)($row['bucket'] ?? ''));
                if ($bk !== '') {
                    $valueByBucket[$bk] = round((float)($row['agg_value'] ?? 0), 2);
                }
            }

            $deviceInfo = $this->db
                ->where('id', $s->device_id)
                ->from('devices')
                ->get()
                ->row();

            $lastData = $this->db
                ->where(['device_id' => $s->device_id, 'key' => $s->metric_key])
                ->from('device_data')
                ->order_by('timestamp', 'desc')
                ->limit(1)
                ->get()
                ->row();

            $datasets[] = [
                'label' => $s->label ?? $s->metric_key,
                'data' => array_map(fn($k) => $valueByBucket[$k] ?? null, $timeKeys),
                'borderColor' => $s->color ?? '#007bff',
                'borderWidth' => 2,
                'fill' => false,
                'device_name' => $deviceInfo->name ?? '',
                'location' => $deviceInfo->location ?? '',
                'description' => $deviceInfo->description ?? '',
                'unit' => $s->unit,
                'lastdata' => $lastData->value ?? '-',
                'lasttime' => $lastData->timestamp ?? '',
            ];
        }

        $result = [
            'labels' => $labels,
            'datasets' => $datasets,
            'yMin' => $config->yMin === '' ? null : intval($config->yMin ?? null),
            'yMax' => $config->yMax === '' ? null : intval($config->yMax ?? null),
            'yTitle' => $config->yTitle ?? '',
            'xTitle' => $config->xTitle ?? '',
            'yUnit' => $config->yUnit ?? ''
        ];

        return $result;
    }

}


//     
