<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends CI_Controller
{
    protected $user_id;
    protected $userName;

    function __construct()
    {
        parent::__construct();

        $this->load->model("Dashboard_layout_model");
        $this->load->model("Api_model");
        $this->load->helper('notification_helper');
        $this->load->helper('eventlog');

        isLogin();

        $sessionUser = $this->session->get_userdata();
        $this->user_id = isset($sessionUser['user_details'][0]->ia_users_id)
            ? $sessionUser['user_details'][0]->ia_users_id : 1;

        $this->userName = isset($sessionUser['user_details'][0]->name)
            ? $sessionUser['user_details'][0]->name : '';

        $this->load->helper('language');
        $this->lang->load('dashboard', $this->session->userdata('site_lang'));
    }

    public function index()
    {
        $layouts = $this->Dashboard_layout_model->get_all();
        $allowedLayouts = site_allowed_layout_ids();
        if (is_array($allowedLayouts)) {
            $allowedLookup = array_flip($allowedLayouts);
            $layouts = array_values(array_filter($layouts, function ($row) use ($allowedLookup) {
                return isset($allowedLookup[(int) $row->id]);
            }));
        }

        $userHome = $this->Dashboard_layout_model->get_home_for_user($this->user_id);

        $data = [
            'layouts' => $layouts,
            'user_id' => $this->user_id,
            'user_home_layout_id' => $userHome ? (int)$userHome->id : 0,
        ];

        $this->load->view("include/header");
        $this->load->view("index", $data);
        $this->load->view("include/footer");
    }



    public function view($layout_id)
    {
        if (!site_layout_allowed($layout_id)) {
            redirect(base_url() . 'home');
            return;
        }
        $layout = $this->Dashboard_layout_model->get_by_id($layout_id);
        if (!$layout) show_404();

        $layout_items = $this->Dashboard_layout_model->get_items($layout_id);

        $data['layout'] = $layout;
        $data['layout_items'] = $layout_items;
        $data['user_id'] = $this->user_id;
        $data['created_by'] = $layout->created_by;

        $this->load->view('include/header');
        $this->load->view('/dashboard/view', $data);
        $this->load->view('include/footer');
    }





    public function add()
    {
        $this->load->view("include/header");
        $this->load->view("form_add");
        $this->load->view("include/footer");
    }

    public function create()
    {
        $name = $this->input->post('name');
        $description = $this->input->post('description');
        $sortOrder = $this->input->post('sort_order');

        $data = [
            'name' => $name,
            'description' => $description,
            'layout' => json_encode([]),
            'created_by' => $this->user_id,
        ];

        if ($this->Dashboard_layout_model->has_sort_order_column()) {
            $data['sort_order'] = ($sortOrder === '' || $sortOrder === null) ? null : (int)$sortOrder;
        }

        $layout_id = $this->Dashboard_layout_model->insert_layout($data);

        log_record_created('dashboard_layout', $layout_id, $name, [
            'name' => $name,
            'description' => $description,
        ]);

        set_alert_message(lang('layout_created_successfully'), 'success');
        redirect('dashboard');
    }

    public function edit($layout_id)
    {
        if (!site_layout_allowed($layout_id)) {
            redirect(base_url() . 'home');
            return;
        }
        $layout = $this->Dashboard_layout_model->get_by_id($layout_id);

        if (!$layout) {
            show_404();
        }

        $data = [
            'layout' => $layout,
            'has_sort_order_column' => $this->Dashboard_layout_model->has_sort_order_column(),
        ];

        $this->load->view("include/header");
        $this->load->view("form_edit", $data);
        $this->load->view("include/footer");
    }

    public function save()
    {
        $layout_id = $this->input->post('id');
        $json = $this->input->post('layout');

        $data = [
            'layout' => json_decode($json),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->Dashboard_layout_model->update_layout($layout_id, $data);

        $layout = $this->Dashboard_layout_model->get_by_id($layout_id);
        $name = $layout ? $layout->name : 'ID ' . $layout_id;
        log_record_updated('dashboard_layout', $layout_id, $name, [], ['layout' => $data['layout']]);

        echo json_encode(['status' => 'success']);
    }

    public function set_home($layout_id)
    {
        $layout = $this->Dashboard_layout_model->get_by_id($layout_id);

        if (!$layout) {
            show_404();
        }

        $this->Dashboard_layout_model->set_user_home($this->user_id, $layout_id);

        log_system_action('Set layout as Home: ' . $layout->name, 'dashboard_layout', $layout_id, ['layout_id' => $layout_id]);

        set_alert_message(lang('layout_set_as_home'), 'success');
        redirect('dashboard');
    }


    public function update($layout_id)
    {
        $layout = $this->Dashboard_layout_model->get_by_id($layout_id);
        if (!$layout) {
            show_404();
        }

        $name = $this->input->post('name');
        $description = $this->input->post('description');
        $sortOrder = $this->input->post('sort_order');

        $data = [
            'name' => $name,
            'description' => $description,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($this->Dashboard_layout_model->has_sort_order_column()) {
            $data['sort_order'] = ($sortOrder === '' || $sortOrder === null) ? null : (int)$sortOrder;
        }

        $this->Dashboard_layout_model->update_layout($layout_id, $data);

        log_record_updated('dashboard_layout', $layout_id, $name, [
            'name' => $layout->name,
            'description' => $layout->description,
        ], [
            'name' => $name,
            'description' => $description,
        ]);

        set_alert_message(lang('layout_updated_successfully'), 'success');
        redirect('dashboard');
    }


    public function delete($layout_id)
    {
        $layout = $this->Dashboard_layout_model->get_by_id($layout_id);
        if (!$layout) {
            show_404();
        }

        $this->Dashboard_layout_model->clear_user_home_for_layout($layout_id);
        $this->Dashboard_layout_model->delete_layout($layout_id);

        log_record_deleted('dashboard_layout', $layout_id, $layout->name, [
            'name' => $layout->name,
            'description' => $layout->description,
        ]);

        set_alert_message(lang('layout_deleted_successfully'), 'success');
        redirect('dashboard');
    }

    public function update_sort_order()
    {
        $order = $this->input->post('order');
        if (!is_array($order) || empty($order)) {
            $input = json_decode($this->input->raw_input_stream, true);
            $order = is_array($input) ? ($input['order'] ?? []) : [];
        }

        if (!is_array($order) || empty($order)) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Invalid order payload'
                ]));
        }

        $ok = $this->Dashboard_layout_model->update_sort_orders($order);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => $ok ? 'success' : 'error'
            ]));
    }



    public function metrics_latest()
    {
        $device_id = $this->input->get('device_id');
        $metric_key = $this->input->get('metric_key');

        $result = $this->Metrics_model->get_latest_value($device_id, $metric_key);
        echo json_encode([
            'value' => $result->value,
            'alarm' => $result->is_alarm
        ]);
    }

    public function edit_layout($layout_id)
    {
        if (!site_layout_allowed($layout_id)) {
            redirect(base_url() . 'home');
            return;
        }
        $layout = $this->Dashboard_layout_model->get_by_id($layout_id);
        $this->load->model('widgets/Widgets_model');

        // ดึง layout_items พร้อม widget_type จาก model
        $layout_items = $this->Dashboard_layout_model->get_items($layout_id);


        $layout_json = json_encode(array_map(function ($item) {
            $cfg = json_decode($item->config ?? '{}', true);
            return [
                'widget_id' => $item->widget_id,
                'widget_type' => $item->widget_type ?? '',
                'x' => $item->x,
                'y' => $item->y,
                'w' => $item->w,
                'h' => $item->h,
                'config' => $cfg  // 👈 ส่ง config ทั้งก้อน
            ];
        }, $layout_items));

        $data['layout'] = $layout;
        $data['layout_items'] = $layout_items;
        $data['layout_json'] = $layout_json;
        $data['widgets'] = $this->Widgets_model->get_all();
        $data['dashboard_layouts_json'] = json_encode(array_map(function ($row) {
            return [
                'id' => (string)$row->id,
                'name' => $row->name ?? ('Dashboard #' . $row->id),
            ];
        }, $this->Dashboard_layout_model->get_all() ?: []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

        if (!$layout) {
            show_404();
        }

        $this->load->view("include/header");
        $this->load->view("/dashboard/edit_layout", $data);
        $this->load->view("include/footer");
    }



    public function layoutsave()
    {
        $layout_id = $this->input->post('id');
        $json = $this->input->post('layout');

        $widgetFieldMap = [
            'value_box' => ['label', 'device_id', 'metric_key', 'unit', 'show_icon', 'icon', 'link_url', 'value_maps', 'show_border', 'bg_color', 'bg_opacity'],
            'ratio' => ['label', 'device_id1', 'metric_key1', 'unit1', 'label1', 'device_id2', 'metric_key2', 'unit2', 'label2', 'show_border', 'bg_color', 'bg_opacity'],
            'gauge' => ['label', 'device_id', 'metric_key', 'unit', 'min', 'max', 'show_border', 'bg_color', 'bg_opacity', 'color_ranges'],
            'level_bar' => ['label', 'device_id', 'metric_key', 'unit', 'min', 'max', 'orientation', 'show_border', 'bg_color', 'bg_opacity', 'color_ranges'],
            'temp_hum_sensor' => ['label', 'device_id', 'temp_key', 'hum_key', 'show_border', 'bg_color', 'bg_opacity'],
            'map_view' => ['background', 'items', 'show_border', 'bg_color', 'bg_opacity'],
            'value_box2' => ['label', 'device_id1', 'metric_key1', 'unit1', 'label1', 'device_id2', 'metric_key2', 'unit2', 'label2', 'spector', 'show_border', 'bg_color', 'bg_opacity'],
            'value_box3' => ['label', 'device_id1', 'metric_key1', 'unit1', 'label1', 'device_id2', 'metric_key2', 'unit2', 'label2', 'device_id3', 'metric_key3', 'unit3', 'label3', 'spector', 'show_border', 'bg_color', 'bg_opacity'],
            'status_box' => ['label', 'device_id', 'metric_key', 'label_normal', 'label_alarm', 'show_border', 'bg_color', 'bg_opacity'],
            'status_box2' => ['label', 'device_id1', 'metric_key1', 'label_normal1', 'label_alarm1', 'device_id2', 'metric_key2', 'label_normal2', 'label_alarm2', 'show_border', 'bg_color', 'bg_opacity'],
            'status_box3' => ['label', 'device_id1', 'metric_key1', 'label_normal1', 'label_alarm1', 'device_id2', 'metric_key2', 'label_normal2', 'label_alarm2', 'device_id3', 'metric_key3', 'label_normal3', 'label_alarm3', 'show_border', 'bg_color', 'bg_opacity'],
            'line_chart' => ['label', 'yMin', 'yMax', 'yUnit', 'yTitle', 'xTitle', 'items', 'show_border', 'bg_color', 'bg_opacity'],
            'bar_chart' => ['label', 'yMin', 'yMax', 'yUnit', 'yTitle', 'sort_order', 'limit', 'orientation', 'setpoint_value', 'setpoint_color', 'bar_color_over', 'bar_color_under', 'items', 'show_border', 'bg_color', 'bg_opacity'],
            'line_chart_dynamic' => ['label', 'yMin', 'yMax', 'yUnit', 'yTitle', 'xTitle', 'time_range', 'time_interval', 'limit', 'display', 'chart_height_pct', 'items', 'show_border', 'bg_color', 'bg_opacity'],
            'table' => ['label', 'items', 'show_border', 'bg_color', 'bg_opacity'],
            'zone_overview' => ['zone_name', 'zone_subtitle', 'link_url', 'icon', 'show_primary', 'primary_label', 'primary_device_id', 'primary_metric_key', 'primary_unit', 'value_maps', 'items', 'alarm_devices', 'show_border', 'bg_color', 'bg_opacity'],
            'svg_widget' => ['label', 'svg_file', 'show_border', 'bg_color', 'bg_opacity', 'bindings'],
            'camera_stream' => ['label', 'device_id', 'show_border', 'bg_color', 'bg_opacity'],
            'status_grid' => ['label', 'columns', 'items', 'show_border', 'bg_color', 'bg_opacity', 'link_url'],
            'navigator' => ['label', 'columns', 'items', 'show_border', 'bg_color', 'bg_opacity'],
            'heatmap' => ['label', 'columns', 'items', 'warn_threshold', 'alarm_threshold', 'unit', 'show_border', 'bg_color', 'bg_opacity'],
            'alarm_summary' => ['label', 'show_total', 'device_filter', 'show_border', 'bg_color', 'bg_opacity'],
            'ups_panel' => ['label', 'device_id', 'capacity_key', 'voltage_key', 'load_r_key', 'load_s_key', 'load_t_key', 'mode_key', 'runtime_key', 'connection_key', 'show_border', 'bg_color', 'bg_opacity'],
            'power_meter' => ['label', 'device_id', 'power_key', 'connection_key', 'voltage_r_key', 'voltage_s_key', 'voltage_t_key', 'current_r_key', 'current_s_key', 'current_t_key', 'show_border', 'bg_color', 'bg_opacity'],
            'cooling_panel' => ['label', 'device_id', 'connection_key', 'running_key', 'alarm_key', 'running_on', 'running_off', 'alarm_on', 'alarm_off', 'supply_temp_key', 'return_temp_key', 'setpoint_key', 'show_border', 'bg_color', 'bg_opacity'],
        ];

        if (!$layout_id || !$json) {
            show_error("Missing layout data", 400);
        }

        $items = json_decode($json, true);

        // ลบ layout items เดิมก่อน
        $this->db->where('layout_id', $layout_id)->delete('dashboard_layout_items');

        foreach ($items as $item) {
            $type = $item['widget_type'] ?? '';
            $fields = $widgetFieldMap[$type] ?? [];

            $configData = $item['config'] ?? [];

            $cfg = [];
            foreach ($fields as $field) {
                    if ($field === 'value_maps' || $field === 'color_ranges' || $field === 'bindings' || $field === 'items' || $field === 'alarm_devices' || $field === 'device_filter') {
                    $maps = $configData[$field] ?? [];
                    if (is_string($maps)) {
                        $decoded = json_decode($maps, true);
                        $maps = is_array($decoded) ? $decoded : [];
                    }
                    $cfg[$field] = is_array($maps) ? array_values($maps) : [];
                    continue;
                }
                $cfg[$field] = $configData[$field] ?? '';
            }

            $this->db->insert('dashboard_layout_items', [
                'layout_id' => $layout_id,
                'widget_id' => $item['widget_id'],
                'x' => $item['x'],
                'y' => $item['y'],
                'w' => $item['w'],
                'h' => $item['h'],
                'config' => json_encode($cfg)
            ]);
        }
        set_alert_message(lang('layout_saved_successfully'), 'success');
        redirect('dashboard/edit_layout/' . $layout_id);
    }


    public function widget_data($widget_id)
    {
        $this->load->model('Widget_model');
        $widget = $this->Widget_model->get_widget_with_config($widget_id);
        if (!$widget || empty($widget->config)) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(["error" => "Widget not found or misconfigured"]));
        }

        $config = json_decode($widget->config);

        if (($config->widget_type ?? $widget->widget_type ?? null) === 'bar_chart') {
            $result = $this->Widget_model->get_bar_chart_data($config ?? []);
        } else {
            $result = $this->Widget_model->get_line_chart_data($config ?? []);
        }
        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function widget_data_dynamic($widget_id = null)
    {
        $this->load->model('Widget_model');
        // rConfig from POST (form-urlencoded) or fallback from JSON body
        $rConfig = $this->input->post('rConfig');
        if ($rConfig === null || $rConfig === false) {
            $input = json_decode($this->input->raw_input_stream, true);
            $rConfig = isset($input['rConfig']) ? $input['rConfig'] : null;
        }

        $widget = $this->Widget_model->get_widget_with_config($widget_id);
        if (!$widget || empty($widget->config)) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(["error" => "Widget not found or misconfigured"]));
        }

        $config = json_decode($widget->config);

        $result = $this->Widget_model->get_line_chart_data_dynamic($config ?? [], json_decode($rConfig) ?? []);

        $result['rConfig'] = json_decode($rConfig);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function automaticAdd() {}



    /* devices.metrics มีค่า 
     [
  {
    "id": "32dce27235024d0fb35603e25a09e6a3",
    "key": "Temperature",
    "unit": "°C",
    "label": "Temperature",
    "scale": 1,
    "enable": "true",
    "datatype": "int",
    "protocol": "lorawan",
    "triggers": [],
    "pull_rate": 10000,
    "store_policy": "always",
    "normal_condition": []
  },
  {
    "id": "2b1433ce2eb84b288e797b7660446ed7",
    "key": "Humidity",
    "unit": "%rH",
    "label": "Humidity",
    "scale": 1,
    "enable": "true",
    "datatype": "int",
    "protocol": "lorawan",
    "triggers": [],
    "pull_rate": 10000,
    "store_policy": "always",
    "normal_condition": []
  },
  {
    "id": "d4e3d98534364c0d86b127fb09ce6ec3",
    "key": "Bat_Level",
    "unit": "%",
    "label": "Battery Level",
    "scale": 1,
    "enable": "true",
    "datatype": "int",
    "protocol": "lorawan",
    "triggers": [],
    "pull_rate": 10000,
    "store_policy": "always",
    "normal_condition": []
  },
  {
    "id": "0ca1475640d34a9aa58ae45f11a7bb8d",
    "key": "Bat_mV",
    "unit": "mV",
    "label": "Battery Voltage",
    "scale": 1,
    "enable": "true",
    "datatype": "int",
    "protocol": "lorawan",
    "triggers": [],
    "pull_rate": 10000,
    "store_policy": "always",
    "normal_condition": []
  },
  {
    "id": "93a4c1aff5d34db99e35094fd8523b9b",
    "key": "rssi",
    "unit": "",
    "label": "RSSI",
    "scale": 1,
    "enable": "true",
    "datatype": "int",
    "protocol": "lorawan",
    "triggers": [],
    "pull_rate": 10000,
    "store_policy": "always",
    "normal_condition": []
  },
  {
    "id": "3e350586d0c0459685cb1e347619f66a",
    "key": "snr",
    "unit": "",
    "label": "SNR",
    "scale": 1,
    "enable": "true",
    "datatype": "int",
    "protocol": "lorawan",
    "triggers": [],
    "pull_rate": 10000,
    "store_policy": "always",
    "normal_condition": []
  }
  */
    public function labeltooltip()
    {
        $device_id = $this->input->get('device_id');
        $metric_key = $this->input->get('metric_key');

        if (!$device_id) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Missing device_id']));
        }

        $metric_keys = ['Temperature', 'Humidity'];

        // ดึงข้อมูลอุปกรณ์
        $device = $this->db->get_where('devices', ['id' => $device_id])->row();

        if (!$device) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Device not found']));
        }


        $metricLabel = array_filter(json_decode($device->metrics), function ($m) use ($metric_key) {
            return $m->key == $metric_key;
        });
        $label = $metricLabel[0]->label ?? 'No label';
   

        // ดึงค่า Temperature และ Humidity ล่าสุด
        $this->db
            ->select('key, value, timestamp')
            ->from('device_data')
            ->where('device_id', $device_id)
            ->where_in('key', $metric_keys)
            ->order_by('timestamp', 'desc');
        $rows = $this->db->get()->result();

        $metrics = [];
        foreach ($rows as $row) {
            if (!isset($metrics[$row->key])) {
                $metrics[$row->key] = [
                    'value' => round((float)$row->value, 2),
                    'timestamp' => $row->timestamp
                ];
            }
        }

        $response = [
            'device_name' => $device->name,
            'location' => $device->location,
            'description' => $device->description,
            'temperature' => $metrics['Temperature']['value'] ?? null,
            'temperature_unit' => '°C',
            'humidity' => $metrics['Humidity']['value'] ?? null,
            'humidity_unit' => '%rH',
            'timestamp' => $metrics['Temperature']['timestamp'] ?? $metrics['Humidity']['timestamp'] ?? '',
            'label' => $label
        ];

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
}
