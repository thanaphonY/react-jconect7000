<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * reports Controller - Template Report Builder
 *
 * ตัวสร้าง Template รายงาน: เลือก Filter (Device, Metric, Time Interval) และกำหนด Input ตอน Export (Date, Month ฯลฯ)
 */
class Reports extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->config('reports');
        $this->load->library('session');
        $this->load->library('form_validation');
        $this->load->database();
        $this->load->helper('url');
        $this->load->helper('form');
        $this->load->model('Reports_model', 'builder_model');
        $this->load->model('Report_schedules_model', 'schedules_model');

        $this->load->helper('language');
        $this->load->helper('eventlog');
        $this->lang->load('reports', $this->session->userdata('site_lang'));


       

        $sessionUser = $this->session->get_userdata();
        $this->user_id = isset($sessionUser['user_details'][0]->ia_users_id)
            ? (int) $sessionUser['user_details'][0]->ia_users_id : 0;
    }

    /**
     * หน้ารายการ Template ที่สร้างไว้ + ปุ่มสร้างใหม่
     */
    public function index()
    {
        isLogin();
        $templates = $this->builder_model->get_templates_list();
        $data = [
            'title' => 'Report Template Builder',
            'templates' => $templates,
        ];
        $this->load->view('include/header');
        $this->load->view('index', $data);
        $this->load->view('include/footer');
    }

    /**
     * หน้า Manage Schedules - จัดการ schedule แยกจากหน้า Templates
     */
    public function schedules()
    {
        $schedules = $this->schedules_model->get_all_with_templates();
        $templates = $this->builder_model->get_templates_list();
        $data = [
            'title' => 'Manage Report Schedules',
            'schedules' => $schedules,
            'templates' => $templates,
        ];
        $this->load->view('include/header');
        $this->load->view('schedules', $data);
        $this->load->view('include/footer');
    }

    /**
     * หน้าสร้าง/แก้ไข Template (Builder)
     */
    public function builder($template_id = null)
    {
        $template = null;
        if ($template_id) {
            $template = $this->builder_model->get_template((int) $template_id);
            if (!$template) {
                show_404();
                return;
            }
        }

        $data = [
            'title' => $template ? 'แก้ไข Template' : 'สร้าง Template รายงาน',
            'template' => $template,
            'template_id' => $template_id,
        ];
        $this->load->view('include/header');
        $this->load->view('builder', $data);
        $this->load->view('include/footer');
    }

    /**
     * บันทึก Template (สร้างใหม่หรืออัปเดต)
     */
    public function save_template()
    {
        $this->output->set_content_type('application/json');

        if ($this->input->method() !== 'post') {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Invalid method']));
            return;
        }

        $template_id = $this->input->post('template_id') ? (int) $this->input->post('template_id') : null;
        $name = trim((string) $this->input->post('name'));
        $description = trim((string) $this->input->post('description'));

        $this->form_validation->set_rules('name', 'Name', 'required|max_length[255]');
        if (!$this->form_validation->run()) {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => validation_errors(' ', "\n"),
            ]));
            return;
        }

        $data_source = $this->input->post('data_source');
        if (!in_array($data_source, ['device_data', 'alarm_logs', 'event_logs'], true)) {
            $data_source = 'device_data';
        }

        $time_interval = $this->input->post('filter_time_interval') ?: 'date_range';
        if ($data_source === 'event_logs') {
            $filters = [
                'action_type' => (bool) $this->input->post('filter_action_type'),
                'entity_type' => (bool) $this->input->post('filter_entity_type'),
                'entity' => (bool) $this->input->post('filter_entity'),
                'time_interval' => $time_interval,
            ];
        } else {
            $selected_device_metrics = $this->input->post('selected_device_metrics');
            if (!is_array($selected_device_metrics)) {
                $selected_device_metrics = [];
            }
            $display = $this->input->post('display');
            if (!is_array($display)) {
                $display = ['min', 'max', 'average'];
            }
            $filters = [
                'time_range' => $this->input->post('time_range') ?: 'last_24h',
                'time_interval' => $this->input->post('time_interval') ?: '1h',
                'display' => array_values(array_filter($display)),
                'sort_order' => $this->input->post('sort_order') === 'asc' ? 'asc' : 'desc',
                'selected_device_metrics' => $selected_device_metrics,
            ];
        }

        $export_inputs_raw = $this->input->post('export_inputs');
        $export_inputs = [];
        if (is_array($export_inputs_raw)) {
            foreach ($export_inputs_raw as $item) {
                if (is_array($item) && !empty($item['type'])) {
                    $export_inputs[] = [
                        'type' => $item['type'],
                        'label' => isset($item['label']) ? $item['label'] : $item['type'],
                        'required' => !empty($item['required']),
                    ];
                }
            }
        }

        $editor_raw = $this->input->post('editor');
        $editor = [
            'report_title' => isset($editor_raw['report_title']) ? trim($editor_raw['report_title']) : '',
            'table_layout' => isset($editor_raw['table_layout']) && $editor_raw['table_layout'] === 'pivot' ? 'pivot' : 'rows',
            'pdf_orientation' => isset($editor_raw['pdf_orientation']) && $editor_raw['pdf_orientation'] === 'landscape' ? 'landscape' : 'portrait',
            'pdf_title' => isset($editor_raw['pdf_title']) ? trim($editor_raw['pdf_title']) : '',
            'excel_sheet_name' => isset($editor_raw['excel_sheet_name']) ? trim($editor_raw['excel_sheet_name']) : 'Sheet1',
            'csv_delimiter' => isset($editor_raw['csv_delimiter']) && in_array($editor_raw['csv_delimiter'], [',', ';', 'tab'], true) ? $editor_raw['csv_delimiter'] : ',',
            'display_columns' => [],
        ];
        // display_columns จาก editor_display_columns (ที่ส่งแยกใน payload) หรือจาก editor[display_columns]
        $editor_cols = $this->input->post('editor_display_columns');
        if (!is_array($editor_cols) && isset($editor_raw['display_columns']) && is_array($editor_raw['display_columns'])) {
            $editor_cols = $editor_raw['display_columns'];
        }
        if (!is_array($editor_cols)) {
            $editor_cols = [];
        }
        if (is_array($editor_cols)) {
            foreach ($editor_cols as $item) {
                if (is_array($item) && !empty($item['key'])) {
                    $editor['display_columns'][] = [
                        'key' => trim($item['key']),
                        'label' => isset($item['label']) ? trim($item['label']) : trim($item['key']),
                    ];
                }
            }
        }

        $config = [
            'builder_version' => 1,
            'data_source' => $data_source,
            'filters' => $filters,
            'export_inputs' => $export_inputs,
            'editor' => $editor,
            'template_type' => 'custom_builder',
        ];

        $data = [
            'name' => $name,
            'description' => $description,
            'template_type' => 'custom_builder',
            'config' => $config,
            'created_by' => $this->user_id,
            'html_content' => '',
        ];

        if ($template_id) {
            $ok = $this->builder_model->update_template($template_id, $data);
        } else {
            $ok = $this->builder_model->create_template($data);
        }

        if ($ok) {
            $id = $template_id ?: $ok;
            if ($template_id) {
                log_record_updated('report_template', (int) $id, $name, [], ['name' => $name, 'description' => $description]);
            } else {
                log_record_created('report_template', (int) $id, $name, ['name' => $name, 'description' => $description]);
            }
            $this->output->set_output(json_encode([
                'success' => true,
                'message' => 'บันทึกสำเร็จ',
                'template_id' => (int) $id,
                'redirect' => base_url('reports'),
            ]));
        } else {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'บันทึกไม่สำเร็จ',
            ]));
        }
    }

    /**
     * API: รายการ Devices สำหรับ dropdown
     */
    public function get_devices()
    {
        $this->output->set_content_type('application/json');
        $list = $this->builder_model->get_devices_list();
        $this->output->set_output(json_encode(['success' => true, 'data' => $list]));
    }

    /**
     * API: รายการ Metric keys จาก device_data
     */
    public function get_metrics()
    {
        $this->output->set_content_type('application/json');
        $list = $this->builder_model->get_metrics_list();
        $this->output->set_output(json_encode(['success' => true, 'data' => $list]));
    }

    /**
     * API: รายการ Metric ของ device นั้น (จาก device_data)
     */
    public function get_metrics_by_device($device_id)
    {
        $this->output->set_content_type('application/json');
        $device_id = (int) $device_id;
        $list = $this->builder_model->get_metrics_by_device($device_id);
        $this->output->set_output(json_encode(['success' => true, 'data' => $list]));
    }

    /**
     * API: รายการ Room (location)
     */
    public function get_rooms()
    {
        $this->output->set_content_type('application/json');
        $list = $this->builder_model->get_rooms_list();
        $this->output->set_output(json_encode(['success' => true, 'data' => $list]));
    }

    /**
     * API: รายการ Action Type (event_logs)
     */
    public function get_action_types()
    {
        $this->output->set_content_type('application/json');
        $list = $this->builder_model->get_action_types_list();
        $this->output->set_output(json_encode(['success' => true, 'data' => $list]));
    }

    /**
     * API: รายการ Entity Type (event_logs)
     */
    public function get_entity_types()
    {
        $this->output->set_content_type('application/json');
        $list = $this->builder_model->get_entity_types_list();
        $this->output->set_output(json_encode(['success' => true, 'data' => $list]));
    }

    /**
     * หน้า Run/Export ตาม Template ที่เลือก (ผู้ใช้กรอก Filter ตามที่กำหนดใน Template)
     */
    public function run($template_id)
    {
        $template_id = (int) $template_id;
        $template = $this->builder_model->get_template($template_id);
        if (!$template) {
            show_404();
            return;
        }

        $config = isset($template->config) ? (is_string($template->config) ? json_decode($template->config, true) : $template->config) : [];
        $export_inputs = isset($config['export_inputs']) ? $config['export_inputs'] : [];
        $filters_config = isset($config['filters']) ? $config['filters'] : [];
        $data_source = isset($config['data_source']) ? $config['data_source'] : 'device_data';

        $data = [
            'title' => $template->name,
            'template' => $template,
            'export_inputs' => $export_inputs,
            'filters_config' => $filters_config,
            'config' => $config,
            'data_source' => $data_source,
        ];
        $this->load->view('include/header');
        $this->load->view('run', $data);
        $this->load->view('include/footer');
    }

    /**
     * API: ดึงข้อมูลตาม filter (สำหรับหน้ารันรายงาน) รองรับ Data Source: device_data, alarm_logs, event_logs
     */
    public function fetch_data()
    {
        $this->output->set_content_type('application/json');
        $template_id = (int) $this->input->post('template_id');
        $filters = $this->input->post('filters');
        if (!is_array($filters)) {
            $filters = [];
        }

        $template = $this->builder_model->get_template($template_id);
        if (!$template) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'ไม่พบ Template']));
            return;
        }

        $config = isset($template->config) ? (is_string($template->config) ? json_decode($template->config, true) : $template->config) : [];
        $data_source = isset($config['data_source']) ? $config['data_source'] : 'device_data';
        if (!in_array($data_source, ['device_data', 'alarm_logs', 'event_logs'], true)) {
            $data_source = 'device_data';
        }

        $device_id = isset($filters['device_id']) ? (int) $filters['device_id'] : null;
        $metric = isset($filters['metric']) ? trim($filters['metric']) : null;
        $room = isset($filters['room']) ? trim($filters['room']) : null;
        $date = isset($filters['date']) ? $filters['date'] : null;
        $month = isset($filters['month']) ? $filters['month'] : null;
        $year = isset($filters['year']) ? $filters['year'] : null;
        $start_date = isset($filters['start_date']) ? $filters['start_date'] : null;
        $end_date = isset($filters['end_date']) ? $filters['end_date'] : null;

        $action_type = isset($filters['action_type']) ? trim($filters['action_type']) : null;
        $entity_type = isset($filters['entity_type']) ? trim($filters['entity_type']) : null;

        $filters_config = isset($config['filters']) ? $config['filters'] : [];
        $selected_device_metrics = isset($filters_config['selected_device_metrics']) ? $filters_config['selected_device_metrics'] : [];
        if (!is_array($selected_device_metrics)) {
            $selected_device_metrics = [];
        }
        $sort_order = isset($filters_config['sort_order']) && $filters_config['sort_order'] === 'asc' ? 'asc' : 'desc';

        $time_range = isset($filters_config['time_range']) ? $filters_config['time_range'] : null;
        if ($time_range && $time_range !== 'input_date_range' && in_array($data_source, ['device_data', 'alarm_logs'], true)) {
            list($start_date, $end_date) = $this->_get_time_range_bounds($time_range);
            $date = $month = $year = null;
        }
        // เมื่อ time_range = input_date_range ใช้ start_date, end_date จาก filters (Input ตอน Export)

        $time_interval = isset($filters_config['time_interval']) ? $filters_config['time_interval'] : null;
        $display = isset($filters_config['display']) && is_array($filters_config['display']) ? $filters_config['display'] : [];
        if (empty($display) && in_array($data_source, ['device_data', 'alarm_logs'], true)) {
            $display = $this->config->item('display_default') ?: ['min', 'max', 'average'];
        }

        if ($data_source === 'alarm_logs') {
            $rows = $this->_fetch_alarm_logs($device_id, $metric, $room, $date, $month, $year, $start_date, $end_date, $selected_device_metrics, $sort_order);
            // แปลงเวลาเป็นเขตเวลา UTC+7
            $this->_convert_datetime_column_timezone($rows, 'created_at', 'Asia/Bangkok');
        } elseif ($data_source === 'event_logs') {
            $rows = $this->_fetch_event_logs($device_id, $action_type, $entity_type, $date, $month, $year, $start_date, $end_date);
            $this->_convert_datetime_column_timezone($rows, 'created_at', 'Asia/Bangkok');
        } else {
            $rows = $this->_fetch_device_data($device_id, $metric, $room, $date, $month, $year, $start_date, $end_date, $selected_device_metrics, $time_interval, $display, $sort_order);
            $rows = $this->_format_device_data_by_datatype($rows, $selected_device_metrics);
            $this->_convert_datetime_column_timezone($rows, 'timestamp', 'Asia/Bangkok');
        }

        $this->output->set_output(json_encode([
            'success' => true,
            'data' => $rows,
            'count' => count($rows),
            'data_source' => $data_source,
        ]));
    }

    /**
     * จัดรูปแบบค่าใน device_data เป็นทศนิยม 2 ตำแหน่งเมื่อ metric มี datatype = float
     */
    private function _format_device_data_by_datatype($rows, $selected_device_metrics)
    {
        if (empty($rows)) {
            return $rows;
        }
        $device_ids = [];
        foreach ($rows as $r) {
            if (!empty($r['device_id'])) {
                $device_ids[(int) $r['device_id']] = true;
            }
        }
        if (empty($device_ids)) {
            return $rows;
        }
        foreach ($selected_device_metrics as $p) {
            $pid = (int) (is_array($p) ? ($p['device_id'] ?? 0) : 0);
            if ($pid) {
                $device_ids[$pid] = true;
            }
        }
        $device_ids = array_keys($device_ids);
        $datatype_map = $this->builder_model->get_metrics_datatype_by_devices($device_ids);
        if (empty($datatype_map)) {
            return $rows;
        }
        $value_keys = ['value', 'value_min', 'value_max', 'value_avg', 'value_first', 'value_last'];
        foreach ($rows as &$r) {
            $did = isset($r['device_id']) ? (int) $r['device_id'] : 0;
            $key = isset($r['key']) ? trim($r['key']) : '';
            if ($did && $key !== '' && isset($datatype_map[$did][$key]) && strtolower($datatype_map[$did][$key]) === 'float') {
                foreach ($value_keys as $vk) {
                    if (isset($r[$vk]) && $r[$vk] !== null && $r[$vk] !== '') {
                        $num = is_numeric($r[$vk]) ? (float) $r[$vk] : null;
                        if ($num !== null) {
                            $r[$vk] = round($num, 2);
                        }
                    }
                }
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * แปลง rows (long format) เป็น pivot format สำหรับ export
     * @return array [cols, rows] - cols = [{key, label}], rows = [assoc, ...]
     */
    private function _transform_to_pivot($rows, $display_columns, $selected_device_metrics, $sort_order)
    {
        $pivot_value_keys = ['value_min', 'value_max', 'value_avg', 'value_first', 'value_last'];
        $pivot_default_labels = [
            'value_min' => 'Min', 'value_max' => 'Max', 'value_avg' => 'Average',
            'value_first' => 'First', 'value_last' => 'Last',
        ];
        $id_keys = ['device_name', 'metric_label', 'device_id', 'key', 'metric_key'];

        $sub_cols = [];
        if (!empty($display_columns)) {
            foreach ($display_columns as $c) {
                $k = is_array($c) ? ($c['key'] ?? '') : $c;
                if (in_array($k, $pivot_value_keys, true)) {
                    $sub_cols[] = [
                        'key' => $k,
                        'label' => (is_array($c) ? ($c['label'] ?? null) : null) ?: ($pivot_default_labels[$k] ?? $k),
                    ];
                }
            }
        }
        if (empty($sub_cols)) {
            foreach (['value_min', 'value_max', 'value_avg'] as $k) {
                $sub_cols[] = ['key' => $k, 'label' => $pivot_default_labels[$k]];
            }
        }

        $id_order = $id_keys;
        if (!empty($display_columns)) {
            $order = [];
            $seen = [];
            foreach ($display_columns as $c) {
                $k = is_array($c) ? ($c['key'] ?? '') : $c;
                if (in_array($k, $id_keys, true) && !isset($seen[$k])) {
                    $seen[$k] = true;
                    $order[] = $k;
                }
            }
            if (!empty($order)) {
                $id_order = $order;
            }
        }

        $metric_info = [];
        $seen_metric = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $device_name = isset($row['device_name']) ? trim((string) $row['device_name']) : '';
            $metric_name = isset($row['metric_label']) ? trim((string) $row['metric_label']) : (isset($row['key']) ? trim((string) $row['key']) : (isset($row['metric_key']) ? trim((string) $row['metric_key']) : ''));
            $device_id = (isset($row['device_id']) && $row['device_id'] !== null) ? (string) $row['device_id'] : '';
            $metric_key = isset($row['key']) ? trim((string) $row['key']) : (isset($row['metric_key']) ? trim((string) $row['metric_key']) : '');
            $parts = [];
            foreach ($id_order as $ik) {
                if ($ik === 'device_name' && $device_name !== '') $parts[] = $device_name;
                elseif ($ik === 'metric_label' && $metric_name !== '') $parts[] = $metric_name;
                elseif ($ik === 'device_id' && $device_id !== '') $parts[] = $device_id;
                elseif (($ik === 'key' || $ik === 'metric_key') && $metric_key !== '') $parts[] = $metric_key;
            }
            $header = implode(' ', $parts) ?: ($metric_name ?: $metric_key ?: $device_name);
            $id_parts = [];
            if ($device_id !== '') $id_parts[] = $device_id;
            if ($metric_key !== '') $id_parts[] = $metric_key;
            elseif ($metric_name !== '') $id_parts[] = $metric_name;
            $metric_id = implode('|', $id_parts) ?: ($metric_name ?: $metric_key);
            if ($metric_id === '') continue;
            if (!isset($seen_metric[$metric_id])) {
                $seen_metric[$metric_id] = true;
                $metric_info[$metric_id] = ['header' => $header ?: $metric_id];
            }
        }

        $metric_order = [];
        if (!empty($selected_device_metrics)) {
            foreach ($selected_device_metrics as $dm) {
                $dm = is_array($dm) ? $dm : [];
                $did = isset($dm['device_id']) && $dm['device_id'] !== null ? (string) $dm['device_id'] : '';
                $mkey = isset($dm['metric']) ? trim((string) $dm['metric']) : '';
                $mid = ($did !== '' && $mkey !== '') ? ($did . '|' . $mkey) : ($mkey ?: $did);
                if ($mid !== '' && isset($seen_metric[$mid])) {
                    $metric_order[] = $mid;
                }
            }
        }
        foreach (array_keys($seen_metric) as $mid) {
            if (!in_array($mid, $metric_order, true)) {
                $metric_order[] = $mid;
            }
        }

        if (empty($metric_order)) {
            return [[['key' => 'timestamp', 'label' => 'Time']], []];
        }

        $cols = [['key' => 'timestamp', 'label' => 'Time']];
        foreach ($metric_order as $metric_id) {
            $header = isset($metric_info[$metric_id]) ? $metric_info[$metric_id]['header'] : $metric_id;
            foreach ($sub_cols as $sc) {
                $cols[] = [
                    'key' => $metric_id . '_' . $sc['key'],
                    'label' => $header . ' ' . $sc['label'],
                ];
            }
        }

        $pivot_by_ts = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $ts = isset($row['timestamp']) ? (string) $row['timestamp'] : '';
            $device_id = (isset($row['device_id']) && $row['device_id'] !== null) ? (string) $row['device_id'] : '';
            $metric_key = isset($row['key']) ? trim((string) $row['key']) : (isset($row['metric_key']) ? trim((string) $row['metric_key']) : '');
            $metric_name = isset($row['metric_label']) ? trim((string) $row['metric_label']) : $metric_key;
            $id_parts = [];
            if ($device_id !== '') $id_parts[] = $device_id;
            if ($metric_key !== '') $id_parts[] = $metric_key;
            elseif ($metric_name !== '') $id_parts[] = $metric_name;
            $metric_id = implode('|', $id_parts) ?: ($metric_name ?: $metric_key);
            if ($ts === '' || $metric_id === '') continue;
            if (!isset($pivot_by_ts[$ts])) $pivot_by_ts[$ts] = [];
            if (!isset($pivot_by_ts[$ts][$metric_id])) $pivot_by_ts[$ts][$metric_id] = [];
            foreach ($sub_cols as $sc) {
                $pivot_by_ts[$ts][$metric_id][$sc['key']] = isset($row[$sc['key']]) ? $row[$sc['key']] : '';
            }
        }

        $timestamps = array_keys($pivot_by_ts);
        usort($timestamps, function ($a, $b) use ($sort_order) {
            return $sort_order === 'asc' ? strcmp($a, $b) : strcmp($b, $a);
        });

        $out_rows = [];
        foreach ($timestamps as $ts) {
            $r = ['timestamp' => $ts];
            foreach ($metric_order as $metric_id) {
                $cell = isset($pivot_by_ts[$ts][$metric_id]) ? $pivot_by_ts[$ts][$metric_id] : [];
                foreach ($sub_cols as $sc) {
                    $r[$metric_id . '_' . $sc['key']] = isset($cell[$sc['key']]) ? $cell[$sc['key']] : '';
                }
            }
            $out_rows[] = $r;
        }

        return [$cols, $out_rows];
    }

    /**
     * ดึงข้อมูลจาก device_data
     * รองรับ Time Range (ผ่าน $start_date/$end_date), Time Interval + Display = aggregate ต่อช่วงเวลา
     * $selected_device_metrics = array of { device_id, device_name, metric } จาก template
     */
    private function _fetch_device_data($device_id, $metric, $room, $date, $month, $year, $start_date, $end_date, $selected_device_metrics = [], $time_interval = null, $display = [], $sort_order = 'desc')
    {
        $time_bounds = $this->_resolve_time_bounds($date, $month, $year, $start_date, $end_date);
        $use_aggregate = $time_interval && !empty($display) && $time_bounds;

        if ($use_aggregate) {
            return $this->_fetch_device_data_aggregated($device_id, $metric, $room, $time_bounds[0], $time_bounds[1], $selected_device_metrics, $time_interval, $display, $sort_order);
        }

        $this->db->select('dd.id, dd.device_id, dd.key, dd.value, dd.timestamp, d.name AS device_name');
        $this->db->from('device_data dd');
        $this->db->join('devices d', 'd.id = dd.device_id', 'left');
        if (!empty($selected_device_metrics)) {
            $this->db->group_start();
            $first = true;
            foreach ($selected_device_metrics as $p) {
                $pid = (int) (is_array($p) ? ($p['device_id'] ?? 0) : 0);
                $pmetric = is_array($p) ? trim($p['metric'] ?? '') : '';
                if ($pid && $pmetric !== '') {
                    if ($first) {
                        $this->db->group_start();
                        $first = false;
                    } else {
                        $this->db->or_group_start();
                    }
                    $this->db->where('dd.device_id', $pid);
                    $this->db->where('dd.key', $pmetric);
                    $this->db->group_end();
                }
            }
            $this->db->group_end();
        } else {
            if ($device_id) {
                $this->db->where('dd.device_id', $device_id);
            }
            if ($metric) {
                $this->db->where('dd.key', $metric);
            }
        }
        if ($room) {
            $this->db->where('d.location', $room);
        }
        $this->_apply_time_filter('dd.timestamp', $date, $month, $year, $start_date, $end_date);
        $dir = (strtoupper($sort_order) === 'ASC') ? 'ASC' : 'DESC';
        $this->db->order_by('dd.timestamp', $dir);
        $max_rows = (int) ($this->config->item('max_rows') ?: 50000);
        $this->db->limit($max_rows);
        $rows = $this->db->get()->result_array();
        $this->_enrich_device_data_metric_labels($rows);
        return $rows;
    }

    /**
     * เพิ่ม metric_label (Metric Name) ในแต่ละแถวจาก devices.metrics
     */
    private function _enrich_device_data_metric_labels(&$rows)
    {
        if (empty($rows)) {
            return;
        }
        $device_ids = [];
        foreach ($rows as $r) {
            $did = isset($r['device_id']) ? (int) $r['device_id'] : 0;
            if ($did) {
                $device_ids[$did] = true;
            }
        }
        $device_ids = array_keys($device_ids);
        $key_to_label = [];
        foreach ($device_ids as $did) {
            $list = $this->builder_model->get_metrics_by_device($did);
            foreach ($list as $m) {
                $key_to_label[$did][$m['key']] = $m['label'];
            }
        }
        foreach ($rows as &$r) {
            $did = isset($r['device_id']) ? (int) $r['device_id'] : 0;
            $key = isset($r['key']) ? trim($r['key']) : (isset($r['metric_key']) ? trim($r['metric_key']) : '');
            $r['metric_label'] = ($did && $key && isset($key_to_label[$did][$key])) ? $key_to_label[$did][$key] : $key;
        }
        unset($r);
    }

    /**
     * คืนค่า [start_datetime, end_datetime] จาก filter วันที่ หรือ null ถ้าไม่มี
     * ทุกค่าตีความเป็น UTC+7 (Asia/Bangkok) แล้วแปลงเป็น UTC (+00) สำหรับ query
     */
    private function _resolve_time_bounds($date, $month, $year, $start_date, $end_date)
    {
        if ($date) {
            $s = $date . ' 00:00:00';
            $e = $date . ' 23:59:59';
            return [$this->_bangkok_to_utc_string($s), $this->_bangkok_to_utc_string($e)];
        }
        if ($month) {
            $last = date('Y-m-t', strtotime($month . '-01'));
            $s = $month . '-01 00:00:00';
            $e = $last . ' 23:59:59';
            return [$this->_bangkok_to_utc_string($s), $this->_bangkok_to_utc_string($e)];
        }
        if ($year) {
            $s = $year . '-01-01 00:00:00';
            $e = $year . '-12-31 23:59:59';
            return [$this->_bangkok_to_utc_string($s), $this->_bangkok_to_utc_string($e)];
        }
        if ($start_date && $end_date) {
            $s = strlen($start_date) > 10 ? $start_date : $start_date . ' 00:00:00';
            $e = strlen($end_date) > 10 ? $end_date : $end_date . ' 23:59:59';
            return [$this->_bangkok_to_utc_string($s), $this->_bangkok_to_utc_string($e)];
        }
        return null;
    }

    /**
     * ดึง device_data แบบ aggregate ตาม Time Interval และ Display (min/max/average/first/last)
     */
    private function _fetch_device_data_aggregated($device_id, $metric, $room, $start_dt, $end_dt, $selected_device_metrics, $time_interval, $display, $sort_order = 'desc')
    {
        $bucket_seconds = [
            '1m' => 60, '5m' => 300, '10m' => 600, '15m' => 900, '30m' => 1800,
            '1h' => 3600, '4h' => 14400, '12h' => 43200, '24h' => 86400, '1d' => 86400,
        ];
        $sec = isset($bucket_seconds[$time_interval]) ? $bucket_seconds[$time_interval] : 3600;

        $selects = ['dd.device_id', 'dd.key', "to_timestamp(floor(extract(epoch from dd.timestamp) / ?) * ?) AS bucket_ts", 'MAX(d.name) AS device_name'];
        $bindings = [$sec, $sec];

        if (in_array('min', $display)) {
            $selects[] = 'MIN(dd.value) AS value_min';
        }
        if (in_array('max', $display)) {
            $selects[] = 'MAX(dd.value) AS value_max';
        }
        if (in_array('average', $display)) {
            $selects[] = 'AVG(dd.value) AS value_avg';
        }
        if (in_array('first', $display)) {
            $selects[] = '(array_agg(dd.value ORDER BY dd.timestamp ASC))[1] AS value_first';
        }
        if (in_array('last', $display)) {
            $selects[] = '(array_agg(dd.value ORDER BY dd.timestamp DESC))[1] AS value_last';
        }

        $sql_select = implode(', ', $selects);
        $group_by = "dd.device_id, dd.key, to_timestamp(floor(extract(epoch from dd.timestamp) / ?) * ?)";
        $bindings_group = [$sec, $sec];

        $from = 'device_data dd LEFT JOIN devices d ON d.id = dd.device_id';
        $where_parts = ['dd.timestamp >= ?', 'dd.timestamp <= ?'];
        $bindings[] = $start_dt;
        $bindings[] = $end_dt;

        if ($room) {
            $where_parts[] = 'd.location = ?';
            $bindings[] = $room;
        }

        if (!empty($selected_device_metrics)) {
            $pairs = [];
            foreach ($selected_device_metrics as $p) {
                $pid = (int) (is_array($p) ? ($p['device_id'] ?? 0) : 0);
                $pmetric = is_array($p) ? trim($p['metric'] ?? '') : '';
                if ($pid && $pmetric !== '') {
                    $pairs[] = '(dd.device_id = ? AND dd.key = ?)';
                    $bindings[] = $pid;
                    $bindings[] = $pmetric;
                }
            }
            if (!empty($pairs)) {
                $where_parts[] = '(' . implode(' OR ', $pairs) . ')';
            }
        } else {
            if ($device_id) {
                $where_parts[] = 'dd.device_id = ?';
                $bindings[] = $device_id;
            }
            if ($metric) {
                $where_parts[] = 'dd.key = ?';
                $bindings[] = $metric;
            }
        }

        $where_sql = implode(' AND ', $where_parts);
        $bindings = array_merge($bindings, $bindings_group);
        $dir = (strtoupper($sort_order) === 'ASC') ? 'ASC' : 'DESC';
        $max_rows = (int) ($this->config->item('max_rows') ?: 50000);
        $sql = "SELECT {$sql_select} FROM {$from} WHERE {$where_sql} GROUP BY {$group_by} ORDER BY bucket_ts {$dir} LIMIT {$max_rows}";
        $q = $this->db->query($sql, $bindings);
        $rows = $q ? $q->result_array() : [];

        foreach ($rows as &$r) {
            if (isset($r['bucket_ts'])) {
                $r['timestamp'] = $r['bucket_ts'];
                unset($r['bucket_ts']);
            }
        }
        unset($r);
        $this->_enrich_device_data_metric_labels($rows);
        return $rows;
    }

    /**
     * ดึงข้อมูลจาก alarm_logs
     */
    private function _fetch_alarm_logs($device_id, $metric, $room, $date, $month, $year, $start_date, $end_date, $selected_device_metrics = [], $sort_order = 'desc')
    {
        $this->db->select('al.id, al.device_id, al.metric_key, al.value, al.message, al.severity, al.status, al.created_at, d.name AS device_name');
        $this->db->from('alarm_logs al');
        $this->db->join('devices d', 'd.id = al.device_id', 'left');
        if (!empty($selected_device_metrics)) {
            $this->db->group_start();
            $first = true;
            foreach ($selected_device_metrics as $p) {
                $pid = (int) (is_array($p) ? ($p['device_id'] ?? 0) : 0);
                $pmetric = is_array($p) ? trim($p['metric'] ?? '') : '';
                if ($pid && $pmetric !== '') {
                    if ($first) {
                        $this->db->group_start();
                        $first = false;
                    } else {
                        $this->db->or_group_start();
                    }
                    $this->db->where('al.device_id', $pid);
                    $this->db->where('al.metric_key', $pmetric);
                    $this->db->group_end();
                }
            }
            $this->db->group_end();
        } else {
            if ($device_id) {
                $this->db->where('al.device_id', $device_id);
            }
            if ($metric) {
                $this->db->where('al.metric_key', $metric);
            }
        }
        if ($room) {
            $this->db->where('d.location', $room);
        }
        $this->_apply_time_filter('al.created_at', $date, $month, $year, $start_date, $end_date);
        $dir = (strtoupper($sort_order) === 'ASC') ? 'ASC' : 'DESC';
        $this->db->order_by('al.created_at', $dir);
        $this->db->limit(50000);
        $rows = $this->db->get()->result_array();
        $this->_enrich_device_data_metric_labels($rows);
        return $rows;
    }

    /**
     * ดึงข้อมูลจาก event_logs
     */
    private function _fetch_event_logs($device_id, $date, $month, $year, $start_date, $end_date)
    {
        $this->db->select('el.id, el.user_id, el.action_type, el.entity_type, el.entity_id, el.message, el.created_at, el.metadata');
        $this->db->from('event_logs el');
        if ($device_id) {
            $this->db->where('el.entity_type', 'Device');
            $this->db->where('el.entity_id', $device_id);
        }
        $this->_apply_time_filter('el.created_at', $date, $month, $year, $start_date, $end_date);
        $this->db->order_by('el.created_at', 'DESC');
        $this->db->limit(50000);
        return $this->db->get()->result_array();
    }

    /**
     * ใส่เงื่อนไขช่วงเวลาลงใน query ปัจจุบัน
     * วันที่/เวลาทุกค่าตีความเป็น UTC+7 (Asia/Bangkok) แล้วแปลงเป็น UTC ก่อนใช้ใน WHERE
     */
    private function _apply_time_filter($column, $date, $month, $year, $start_date, $end_date)
    {
        if ($date) {
            $s = $this->_bangkok_to_utc_string($date . ' 00:00:00');
            $e = $this->_bangkok_to_utc_string($date . ' 23:59:59');
            $this->db->where($column . ' >=', $s);
            $this->db->where($column . ' <=', $e);
        } elseif ($month) {
            $last = date('Y-m-t', strtotime($month . '-01'));
            $s = $this->_bangkok_to_utc_string($month . '-01 00:00:00');
            $e = $this->_bangkok_to_utc_string($last . ' 23:59:59');
            $this->db->where($column . ' >=', $s);
            $this->db->where($column . ' <=', $e);
        } elseif ($year) {
            $s = $this->_bangkok_to_utc_string($year . '-01-01 00:00:00');
            $e = $this->_bangkok_to_utc_string($year . '-12-31 23:59:59');
            $this->db->where($column . ' >=', $s);
            $this->db->where($column . ' <=', $e);
        } elseif ($start_date && $end_date) {
            $s = (strlen($start_date) > 10) ? $start_date : ($start_date . ' 00:00:00');
            $e = (strlen($end_date) > 10) ? $end_date : ($end_date . ' 23:59:59');
            $s = $this->_bangkok_to_utc_string($s);
            $e = $this->_bangkok_to_utc_string($e);
            $this->db->where($column . ' >=', $s);
            $this->db->where($column . ' <=', $e);
        }
    }

    /**
     * แปลงค่า datetime ในคอลัมน์ที่ระบุให้เป็นเขตเวลาเป้าหมาย (เช่น UTC+7 / Asia/Bangkok)
     *
     * @param array  $rows     อ้างอิง array ของแถวข้อมูล
     * @param string $column   ชื่อคีย์ในแต่ละแถวที่เก็บ datetime
     * @param string $timezone ชื่อ timezone ตาม PHP (เช่น 'Asia/Bangkok')
     */
    private function _convert_datetime_column_timezone(&$rows, $column, $timezone)
    {
        if (empty($rows) || !$column || !$timezone) {
            return;
        }
        try {
            $tz = new DateTimeZone($timezone);
        } catch (Exception $e) {
            return;
        }
        foreach ($rows as &$r) {
            if (empty($r[$column])) {
                continue;
            }
            try {
                $dt = new DateTime($r[$column]);
                $dt->setTimezone($tz);
                // ส่งกลับรูปแบบพร้อม offset ชัดเจน เช่น 2026-02-04 15:30:00+07:00
                //$r[$column] = $dt->format('Y-m-d H:i:sP');
                $r[$column] = $dt->format('d/m/Y H:i');
            } catch (Exception $e) {
                // ถ้า parse ไม่ได้ ให้ข้ามแถวนั้น
                continue;
            }
        }
        unset($r);
    }

    /**
     * แปลง datetime string ที่ตีความเป็น Asia/Bangkok (UTC+7) เป็น UTC string สำหรับใช้ใน query
     * คืนรูปแบบ 'Y-m-d H:i:s'+00 ถ้า input มี timezone อยู่แล้ว (เช่นลงท้าย +00) จะคืนค่าเดิม
     */
    private function _bangkok_to_utc_string($dateTimeStr)
    {
        if (!$dateTimeStr || strpos($dateTimeStr, '+') !== false || strpos($dateTimeStr, 'Z') !== false) {
            return $dateTimeStr;
        }
        $s = trim($dateTimeStr);
        if (strlen($s) <= 10) {
            $s .= ' 00:00:00';
        }
        try {
            $tzBangkok = new DateTimeZone('Asia/Bangkok');
            $tzUtc = new DateTimeZone('UTC');
            $dt = new DateTime($s, $tzBangkok);
            $dt->setTimezone($tzUtc);
            return $dt->format('Y-m-d H:i:s') . '+00';
        } catch (Exception $e) {
            return $dateTimeStr;
        }
    }

    /**
     * คำนวณ start, end จาก time_range
     * NOTE: DB เก็บเป็น UTC (timestamptz) แต่ UI ใช้มุมมอง Asia/Bangkok (UTC+7)
     * จึงต้องคำนวณช่วงเวลาใน timezone Asia/Bangkok ก่อน แล้วแปลงเป็น UTC เพื่อนำไปใช้ใน WHERE
     */
    private function _get_time_range_bounds($time_range)
    {
        $tzLocal = new DateTimeZone('Asia/Bangkok');
        $tzUtc   = new DateTimeZone('UTC');

        $nowLocal = new DateTime('now', $tzLocal);
        $endLocal = clone $nowLocal;
        $startLocal = clone $nowLocal;

        switch ($time_range) {
            case 'last_6h':
                $startLocal = (clone $nowLocal)->modify('-6 hours');
                break;
            case 'last_12h':
                $startLocal = (clone $nowLocal)->modify('-12 hours');
                break;
            case 'last_24h':
                $startLocal = (clone $nowLocal)->modify('-24 hours');
                break;
            case 'today':
                $startLocal = (clone $nowLocal)->setTime(0, 0, 0);
                break;
            case 'yesterday':
                $startLocal = (clone $nowLocal)->modify('-1 day')->setTime(0, 0, 0);
                $endLocal   = (clone $nowLocal)->modify('-1 day')->setTime(23, 59, 59);
                break;
            case 'last_3d':
                $startLocal = (clone $nowLocal)->modify('-3 days');
                break;
            case 'last_7d':
                $startLocal = (clone $nowLocal)->modify('-7 days');
                break;
            case 'this_week':
                $dow = (int) $nowLocal->format('w');
                $mondayOffset = ($dow === 0) ? -6 : 1 - $dow;
                $startLocal = (clone $nowLocal)->modify($mondayOffset . ' days')->setTime(0, 0, 0);
                break;
            case 'last_week':
                $dow = (int) $nowLocal->format('w');
                $mondayOffset = ($dow === 0) ? -6 : 1 - $dow;
                $lastMonLocal = (clone $nowLocal)->modify($mondayOffset . ' days')->modify('-7 days');
                $startLocal = (clone $lastMonLocal)->setTime(0, 0, 0);
                $endLocal   = (clone $lastMonLocal)->modify('+6 days')->setTime(23, 59, 59);
                break;
            case 'this_month':
                $startLocal = (clone $nowLocal)->setDate((int) $nowLocal->format('Y'), (int) $nowLocal->format('m'), 1)->setTime(0, 0, 0);
                break;
            case 'last_month':
                $firstLocal = (clone $nowLocal)->modify('first day of last month')->setTime(0, 0, 0);
                $startLocal = clone $firstLocal;
                $endLocal   = (clone $firstLocal)->modify('last day of this month')->setTime(23, 59, 59);
                break;
            case 'last_3m':
                $startLocal = (clone $nowLocal)->modify('-3 months');
                break;
            default:
                $startLocal = (clone $nowLocal)->modify('-24 hours');
        }

        // แปลงเป็น UTC และใส่ suffix +00 เพื่อให้ PostgreSQL ตีความเป็น UTC เสมอ (ไม่ขึ้นกับ session timezone)
        // ถ้าไม่ใส่ timezone ค่า 2026-02-03 17:00:00 อาจถูกตีเป็น 17:00 ในเขต server/DB ทำให้ช่วงผิด
        $startUtc = (clone $startLocal)->setTimezone($tzUtc)->format('Y-m-d H:i:s') . '+00';
        $endUtc   = (clone $endLocal)->setTimezone($tzUtc)->format('Y-m-d H:i:s') . '+00';

        return [$startUtc, $endUtc];
    }

    /**
     * ลบ Template
     */
    public function delete_template($template_id)
    {
        $this->output->set_content_type('application/json');
        $template_id = (int) $template_id;
        $template = $this->builder_model->get_template($template_id);
        $name = $template ? $template->name : 'ID ' . $template_id;
        $deleted_values = $template ? (array) $template : [];
        $ok = $this->builder_model->delete_template($template_id);
        if ($ok) {
            log_record_deleted('report_template', $template_id, $name, $deleted_values);
        }
        $this->output->set_output(json_encode([
            'success' => $ok,
            'message' => $ok ? 'ลบแล้ว' : 'ลบไม่สำเร็จ',
            'redirect' => base_url('reports'),
        ]));
    }

    // ---------- File Manager & Export ----------

    /**
     * หน้า File Manager - รายการไฟล์ export
     */
    public function files()
    {
        $template_id = $this->input->get('template_id') ? (int) $this->input->get('template_id') : null;
        $format = $this->input->get('format') ?: null;
        $date_from = $this->input->get('date_from') ?: null;
        $date_to = $this->input->get('date_to') ?: null;
        $items = $this->builder_model->get_export_files($template_id, $format, $date_from, $date_to);
        $templates = $this->builder_model->get_templates_list();
        $data = [
            'title' => 'Report Files',
            'items' => $items,
            'templates' => $templates,
            'template_id' => $template_id,
            'format' => $format,
            'date_from' => $date_from,
            'date_to' => $date_to,
        ];
        $this->load->view('include/header');
        $this->load->view('files', $data);
        $this->load->view('include/footer');
    }

    /**
     * Download ไฟล์ export
     */
    public function download_file($id)
    {
        $id = (int) $id;
        $file = $this->builder_model->get_export_file($id);
        if (!$file) {
            show_404();
            return;
        }
        $export_path = $this->config->item('export_path') ?: 'uploads/reports/export/';
        $path = FCPATH . $export_path . $file['file_path'];
        if (!is_file($path)) {
            show_404();
            return;
        }
        $this->load->helper('download');
        force_download($file['file_name'], file_get_contents($path));
    }

    /**
     * ลบไฟล์ export (จาก File Manager)
     */
    public function delete_file($id)
    {
        $this->output->set_content_type('application/json');
        $id = (int) $id;
        $file = $this->builder_model->get_export_file($id);
        if (!$file) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'File not found']));
            return;
        }
        $export_path = $this->config->item('export_path') ?: 'uploads/reports/export/';
        $path = FCPATH . $export_path . $file['file_path'];
        if (is_file($path)) {
            @unlink($path);
        }
        $ok = $this->builder_model->delete_export_file($id);
        if ($ok) {
            $entity_name = isset($file['file_name']) ? $file['file_name'] : 'ID ' . $id;
            log_record_deleted('report_export_file', $id, $entity_name, $file);
        }
        $this->output->set_output(json_encode(['success' => $ok, 'message' => $ok ? 'Deleted' : 'Delete failed']));
    }

    /**
     * API: Export และบันทึกไฟล์ (manual จาก Run page)
     */
    public function export_save()
    {
        $this->output->set_content_type('application/json');
        if ($this->input->method() !== 'post') {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Invalid method']));
            return;
        }
        $template_id = (int) $this->input->post('template_id');
        $filters = $this->input->post('filters');
        if (!is_array($filters)) $filters = [];
        $format = in_array($this->input->post('format'), ['pdf', 'excel', 'csv']) ? $this->input->post('format') : 'csv';
        $save_to_file_manager = (bool) $this->input->post('save_to_file_manager');

        $result = ['success' => false, 'message' => ''];
        if ($save_to_file_manager) {
            $result = $this->_build_and_save_export($template_id, $filters, $format, null, $this->user_id);
        } else {
            $result = ['success' => true];
        }

        if ($result['success']) {
            $template = $this->builder_model->get_template($template_id);
            $template_name = $template ? $template->name : 'ID ' . $template_id;
            $message = sprintf('Exported report: %s as %s', $template_name, strtoupper($format));
            log_system_action($message, 'report_template', $template_id, [
                'format' => $format,
                'save_to_file_manager' => $save_to_file_manager,
            ]);
        }

        $this->output->set_output(json_encode($result));
    }

    /**
     * Endpoint สำหรับ Node/cron เรียกรัน schedules ที่ถึงเวลา
     * GET /reports/run_schedules?key=SECRET
     */
    public function run_schedules()
    {
        $this->output->set_content_type('application/json');
        $key = $this->input->get('key');
        $expected = $this->config->item('report_schedule_key') ?: 'report_schedule_secret_key';
        if ($key !== $expected) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Unauthorized']));
            return;
        }

        $schedules = $this->schedules_model->get_due_schedules();
        $done = 0;
        $errors = [];

        foreach ($schedules as $s) {
            $template = $this->builder_model->get_template($s->template_id);
            if (!$template) {
                $errors[] = "Schedule {$s->id}: template not found";
                continue;
            }
            $filters = is_string($s->report_params) ? json_decode($s->report_params, true) : (array) $s->report_params;
            if (!is_array($filters)) $filters = [];
            $recipients = is_string($s->recipients) ? json_decode($s->recipients, true) : (array) $s->recipients;
            if (!is_array($recipients)) $recipients = [];

            $result = $this->_build_and_save_export($s->template_id, $filters, $s->export_format, (int) $s->id, null);
            if ($result['success']) {
                $this->schedules_model->update_last_run($s->id);
                $done++;
                // TODO: send email to $recipients with file attachment
            } else {
                $errors[] = "Schedule {$s->id}: " . ($result['message'] ?? 'export failed');
            }
        }

        $this->output->set_output(json_encode([
            'success' => true,
            'processed' => count($schedules),
            'done' => $done,
            'errors' => $errors,
        ]));
    }

    /**
     * สร้างไฟล์ export และบันทึกลง disk + DB
     */
    private function _build_and_save_export($template_id, $filters, $format, $schedule_id = null, $exported_by = null)
    {
        $template = $this->builder_model->get_template($template_id);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found'];
        }

        $config = is_string($template->config) ? json_decode($template->config, true) : (array) $template->config;
        $data_source = $config['data_source'] ?? 'device_data';
        $filters_config = $config['filters'] ?? [];
        $editor = $config['editor'] ?? [];
        $display_columns = $editor['display_columns'] ?? [['key' => 'timestamp', 'label' => 'Time'], ['key' => 'device_name', 'label' => 'Device'], ['key' => 'key', 'label' => 'Metric'], ['key' => 'value', 'label' => 'Value']];
        $report_title = $editor['report_title'] ?? $template->name;

        $device_id = isset($filters['device_id']) ? (int) $filters['device_id'] : null;
        $metric = isset($filters['metric']) ? trim($filters['metric']) : null;
        $room = isset($filters['room']) ? trim($filters['room']) : null;
        $date = $filters['date'] ?? null;
        $month = $filters['month'] ?? null;
        $year = $filters['year'] ?? null;
        $start_date = $filters['start_date'] ?? null;
        $end_date = $filters['end_date'] ?? null;
        $action_type = isset($filters['action_type']) ? trim($filters['action_type']) : null;
        $entity_type = isset($filters['entity_type']) ? trim($filters['entity_type']) : null;
        $selected_device_metrics = $filters_config['selected_device_metrics'] ?? [];
        if (!is_array($selected_device_metrics)) $selected_device_metrics = [];
        $sort_order = ($filters_config['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $time_range = $filters_config['time_range'] ?? null;
        if ($time_range && $time_range !== 'input_date_range' && in_array($data_source, ['device_data', 'alarm_logs'], true)) {
            list($start_date, $end_date) = $this->_get_time_range_bounds($time_range);
            $date = $month = $year = null;
        } elseif ($time_range === 'input_date_range' && in_array($data_source, ['device_data', 'alarm_logs'], true)) {
            if (empty($start_date) || empty($end_date)) {
                $yesterday = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->modify('-1 day')->format('Y-m-d');
                $start_date = $yesterday;
                $end_date = $yesterday;
            }
        }
        $time_interval = $filters_config['time_interval'] ?? null;
        $display = isset($filters_config['display']) && is_array($filters_config['display']) ? $filters_config['display'] : [];
        if (empty($display)) {
            $display = $this->config->item('display_default') ?: ['min', 'max', 'average'];
        }

        if ($data_source === 'alarm_logs') {
            $rows = $this->_fetch_alarm_logs($device_id, $metric, $room, $date, $month, $year, $start_date, $end_date, $selected_device_metrics, $sort_order);
            $this->_convert_datetime_column_timezone($rows, 'created_at', 'Asia/Bangkok');
        } elseif ($data_source === 'event_logs') {
            $rows = $this->_fetch_event_logs($device_id, $action_type, $entity_type, $date, $month, $year, $start_date, $end_date);
            $this->_convert_datetime_column_timezone($rows, 'created_at', 'Asia/Bangkok');
        } else {
            $rows = $this->_fetch_device_data($device_id, $metric, $room, $date, $month, $year, $start_date, $end_date, $selected_device_metrics, $time_interval, $display, $sort_order);
            $rows = $this->_format_device_data_by_datatype($rows, $selected_device_metrics);
            $this->_convert_datetime_column_timezone($rows, 'timestamp', 'Asia/Bangkok');
        }

        if (empty($rows)) {
            return ['success' => false, 'message' => 'No data to export'];
        }

        $table_layout = isset($editor['table_layout']) && $editor['table_layout'] === 'pivot' ? 'pivot' : 'rows';
        $use_pivot = ($table_layout === 'pivot' && $data_source === 'device_data');

        if ($use_pivot) {
            list($cols, $rows) = $this->_transform_to_pivot($rows, $display_columns, $selected_device_metrics, $sort_order);
        } else {
            $cols = [];
            foreach ($display_columns as $c) {
                $k = is_array($c) ? ($c['key'] ?? '') : $c;
                $l = is_array($c) ? ($c['label'] ?? $k) : $k;
                if ($k) $cols[] = ['key' => $k, 'label' => $l];
            }
            if (empty($cols)) {
                $cols = [['key' => 'timestamp', 'label' => 'Time'], ['key' => 'device_name', 'label' => 'Device'], ['key' => 'key', 'label' => 'Metric'], ['key' => 'value', 'label' => 'Value']];
            }
            $rows = array_map(function ($r) {
                return (array) $r;
            }, $rows);
        }

        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $template->name);
        $ts = date('Ymd_His');
        $subdir = date('Y') . '/' . date('m');
        $export_path = $this->config->item('export_path') ?: 'uploads/reports/export/';
        $base = rtrim(FCPATH . $export_path, '/') . '/';
        $dir = $base . $subdir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $ext = $format === 'excel' ? 'xlsx' : ($format === 'pdf' ? 'pdf' : 'csv');
        $filename = $slug . '_' . $ts . '.' . $ext;
        $relpath = $subdir . '/' . $filename;
        $fullpath = $dir . '/' . $filename;

        $ok = false;
        if ($format === 'csv') {
            $delim = ($editor['csv_delimiter'] ?? ',') === 'tab' ? "\t" : ',';
            $fp = fopen($fullpath, 'w');
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, array_column($cols, 'label'), $delim);
            foreach ($rows as $r) {
                $line = [];
                foreach ($cols as $col) {
                    $line[] = isset($r[$col['key']]) ? $r[$col['key']] : '';
                }
                fputcsv($fp, $line, $delim);
            }
            fclose($fp);
            $ok = true;
        } elseif ($format === 'excel' && file_exists(FCPATH . 'vendor/autoload.php')) {
            require_once FCPATH . 'vendor/autoload.php';
            try {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle($editor['excel_sheet_name'] ?? 'Sheet1');
                $r = 1;
                foreach (array_column($cols, 'label') as $c => $l) {
                    $sheet->setCellValueByColumnAndRow($c + 1, $r, $l);
                }
                $r++;
                foreach ($rows as $row) {
                    foreach ($cols as $c => $col) {
                        $v = isset($row[$col['key']]) ? $row[$col['key']] : '';
                        $sheet->setCellValueByColumnAndRow($c + 1, $r, $v);
                    }
                    $r++;
                }
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save($fullpath);
                $ok = true;
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Excel export failed: ' . $e->getMessage()];
            }
        } elseif ($format === 'pdf') {
            // ใช้ CI TCPDF wrapper (application/libraries/Tcpdf.php + third_party/tcpdf)
            $pdf_error = null;
            try {
                $this->load->library('tcpdf_wrapper');

                if (ob_get_level()) {
                    @ob_end_clean();
                }

                // เตรียมเอกสารพื้นฐาน
                $this->tcpdf_wrapper->create($report_title, 'Connect Plus JConnect', $report_title);
                // ใช้ landscape เพราะ report/pivot กว้าง
                $this->tcpdf_wrapper->add_page('L', 'A4');
                // ถ้าต้องการรองรับภาษาไทยแบบ Eventlogs ให้เปลี่ยน font เป็น thsarabunnew
                // $this->tcpdf_wrapper->set_font('thsarabunnew', '', 12);
                $this->tcpdf_wrapper->set_font('thsarabunnew', '', 10);

                // สร้าง HTML ตาราง (สีและรูปแบบเหมือน Eventlogs, ใช้ thead เพื่อให้ header ซ้ำทุกหน้า)
                $html = '<table cellpadding="4" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 10pt; table-layout: fixed; border: 1px solid #d1d9e0;">';
                $html .= '<thead><tr style="background-color: #34495e; color: #ffffff;">';
                foreach ($cols as $col) {
                    $html .= '<th style="text-align: center; padding: 8px 6px; font-size: 10pt; font-weight: bold; border: 1px solid #2c3e50;">' . htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8') . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                $rowIndex = 0;
                foreach ($rows as $row) {
                    $rowBg = ($rowIndex % 2 === 0) ? '#ffffff' : '#f8fafc';
                    $html .= '<tr style="background-color: ' . $rowBg . ';">';
                    foreach ($cols as $col) {
                        $v = isset($row[$col['key']]) ? $row[$col['key']] : '';
                        if (is_array($v) || is_object($v)) {
                            $v = json_encode($v);
                        }
                        $html .= '<td style="padding: 2px; border: 1px solid #e2e8f0; color: #334155; text-align: center;">' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</td>';
                    }
                    $html .= '</tr>';
                    $rowIndex++;
                }
                $html .= '</tbody></table>';

                $this->tcpdf_wrapper->write_html($html, true, false, true, false, '');
                $this->tcpdf_wrapper->save($fullpath);
                $ok = true;
            } catch (\Throwable $e) {
                log_message('error', 'Report PDF export failed: ' . $e->getMessage());
                $pdf_error = $e->getMessage() . ' (trace: ' . $e->getTraceAsString() . ')';
                return ['success' => false, 'message' => 'PDF export failed: ' . $pdf_error];
            }
        } else {
            if ($format !== 'csv') {
                $fp = fopen($fullpath, 'w');
                fwrite($fp, "\xEF\xBB\xBF");
                fputcsv($fp, array_column($cols, 'label'));
                foreach ($rows as $r) {
                    $line = [];
                    foreach ($cols as $col) {
                        $line[] = isset($r[$col['key']]) ? $r[$col['key']] : '';
                    }
                    fputcsv($fp, $line);
                }
                fclose($fp);
                $ext = 'csv';
                $filename = preg_replace('/\.(pdf|xlsx?)$/', '.csv', $filename);
                $relpath = $subdir . '/' . $filename;
                $fullpath = $dir . '/' . $filename;
                $ok = true;
                $format = 'csv';
            }
        }

        if (!$ok || !is_file($fullpath)) {
            return ['success' => false, 'message' => 'Failed to create file'];
        }

        $file_id = $this->builder_model->insert_export_file([
            'template_id' => $template_id,
            'schedule_id' => $schedule_id,
            'file_path' => $relpath,
            'file_name' => $filename,
            'file_size' => filesize($fullpath),
            'export_format' => $format,
            'exported_by' => $exported_by,
        ]);

        return [
            'success' => true,
            'file_id' => $file_id,
            'file_name' => $filename,
            'download_url' => base_url('reports/download_file/' . $file_id),
        ];
    }

    // ---------- Schedule CRUD ----------

    public function schedule_list($template_id)
    {
        $this->output->set_content_type('application/json');
        $schedules = $this->schedules_model->get_by_template((int) $template_id);
        $out = [];
        foreach ($schedules as $s) {
            $out[] = [
                'id' => (int) $s->id,
                'name' => $s->name,
                'is_active' => (bool) $s->is_active,
                'schedule_type' => $s->schedule_type,
                'schedule_config' => is_string($s->schedule_config) ? json_decode($s->schedule_config, true) : [],
                'timezone' => $s->timezone,
                'export_format' => $s->export_format,
                'recipients' => is_string($s->recipients) ? json_decode($s->recipients, true) : [],
                'next_run_at' => $s->next_run_at,
                'last_run_at' => $s->last_run_at,
            ];
        }
        $this->output->set_output(json_encode(['success' => true, 'data' => $out]));
    }

    public function schedule_save()
    {
        $this->output->set_content_type('application/json');
        if ($this->input->method() !== 'post') {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Invalid method']));
            return;
        }
        $id = $this->input->post('id') ? (int) $this->input->post('id') : 0;
        $template_id = (int) $this->input->post('template_id');
        $name = trim($this->input->post('name') ?? '');
        if (!$name) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Name is required']));
            return;
        }
        $recipients_raw = $this->input->post('recipients');
        $recipients = [];
        if (is_array($recipients_raw)) {
            $recipients = array_filter(array_map('trim', $recipients_raw));
        } elseif (is_string($recipients_raw)) {
            $recipients = array_filter(array_map('trim', explode("\n", $recipients_raw)));
        }
        $data = [
            'id' => $id,
            'template_id' => $template_id,
            'name' => $name,
            'is_active' => (bool) $this->input->post('is_active'),
            'schedule_type' => $this->input->post('schedule_type') ?: 'daily',
            'schedule_config' => [
                'time' => $this->input->post('schedule_time') ?: '08:00',
                'weekday' => (int) $this->input->post('schedule_weekday'),
                'day' => (int) $this->input->post('schedule_day'),
            ],
            'timezone' => $this->input->post('timezone') ?: 'Asia/Bangkok',
            'export_format' => $this->input->post('export_format') ?: 'pdf',
            'recipients' => $recipients,
            'created_by' => $this->user_id,
        ];
        $newId = $this->schedules_model->save($data);
        if ($id > 0) {
            log_record_updated('report_schedule', (int) $newId, $name, [], ['name' => $name, 'template_id' => $template_id]);
        } else {
            log_record_created('report_schedule', (int) $newId, $name, ['name' => $name, 'template_id' => $template_id]);
        }
        $this->output->set_output(json_encode(['success' => true, 'id' => $newId]));
    }

    public function schedule_delete($id)
    {
        $this->output->set_content_type('application/json');
        $id = (int) $id;
        $schedule = $this->schedules_model->get($id);
        $name = $schedule ? $schedule->name : 'ID ' . $id;
        $deleted_values = $schedule ? (array) $schedule : [];
        $ok = $this->schedules_model->delete($id);
        if ($ok) {
            log_record_deleted('report_schedule', $id, $name, $deleted_values);
        }
        $this->output->set_output(json_encode(['success' => $ok]));
    }
}

