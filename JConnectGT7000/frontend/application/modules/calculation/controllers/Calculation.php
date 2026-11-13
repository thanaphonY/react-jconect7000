<?php defined('BASEPATH') or exit('No direct script access allowed');

class Calculation extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        isLogin();
        $this->load->model('Calculation_model');
        $this->load->helper(['url', 'language']);
    }

    public function index()
    {
        $devices = $this->Calculation_model->get_devices();
        $calculations = [];
        $deviceOptions = [];

        foreach ($devices as $device) {
            $metrics = $this->decode_metrics($device->metrics);
            $deviceOptions[] = [
                'id' => (int) $device->id,
                'name' => $device->name,
                'metrics' => $metrics
            ];
            foreach ($metrics as $metric) {
                if (isset($metric['type']) && $metric['type'] === 'calc') {
                    $calculations[] = [
                        'device_id' => (int) $device->id,
                        'device_name' => $device->name,
                        'is_active' => $device->is_active,
                        'metric' => $metric
                    ];
                }
            }
        }

        $data = [
            'calculations' => $calculations,
            'calc_devices' => $deviceOptions
        ];
        $this->load->view('include/header');
        $this->load->view('index', $data);
        $this->load->view('include/footer');
    }

    public function save()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }

        $deviceId = (int) $this->input->post('device_id');
        $originalDeviceId = (int) $this->input->post('original_device_id');
        $originalKey = trim((string) $this->input->post('original_key'));
        $key = trim((string) $this->input->post('key'));
        $formula = trim((string) $this->input->post('formula'));
        $inputs = json_decode((string) $this->input->post('inputs_json'), true);

        if (!$deviceId || $key === '' || $formula === '' || !is_array($inputs) || count($inputs) === 0) {
            set_alert_message('Device, metric key, formula, and at least one input are required.', 'danger');
            redirect('calculation');
        }

        $target = $this->Calculation_model->get_device($deviceId);
        if (!$target) {
            set_alert_message('Device not found.', 'danger');
            redirect('calculation');
        }

        $normalizedInputs = [];
        foreach ($inputs as $input) {
            $var = trim((string) ($input['var'] ?? ''));
            if ($var === '') {
                continue;
            }
            if (($input['source'] ?? '') === 'random') {
                $normalizedInputs[] = [
                    'var' => $var,
                    'source' => 'random',
                    'min' => is_numeric($input['min'] ?? null) ? (float) $input['min'] : 0,
                    'max' => is_numeric($input['max'] ?? null) ? (float) $input['max'] : 1
                ];
            } else {
                $metricKey = trim((string) ($input['metricKey'] ?? ''));
                if ($metricKey === '') {
                    continue;
                }
                $source = ['var' => $var, 'metricKey' => $metricKey];
                if (isset($input['deviceId']) && $input['deviceId'] !== '') {
                    $source['deviceId'] = (int) $input['deviceId'];
                }
                $normalizedInputs[] = $source;
            }
        }

        if (count($normalizedInputs) === 0) {
            set_alert_message('At least one valid calculation input is required.', 'danger');
            redirect('calculation');
        }

        $metrics = $this->decode_metrics($target->metrics);
        $existingIndex = -1;
        foreach ($metrics as $index => $metric) {
            if ($originalDeviceId === $deviceId
                && ($metric['key'] ?? '') === $originalKey
                && ($metric['type'] ?? '') === 'calc') {
                $existingIndex = $index;
            }
        }
        foreach ($metrics as $index => $metric) {
            if ($index !== $existingIndex && ($metric['key'] ?? '') === $key) {
                set_alert_message('Metric key already exists on the selected device.', 'danger');
                redirect('calculation');
            }
        }

        $mode = (string) $this->input->post('trigger_mode');
        if (!in_array($mode, ['interval', 'on_pull', 'schedule'], true)) {
            $mode = 'interval';
        }
        $trigger = ['mode' => $mode];
        if ($mode === 'interval') {
            $trigger['intervalSec'] = max(1, (int) $this->input->post('interval_sec'));
        } elseif ($mode === 'schedule') {
            $cron = trim((string) $this->input->post('cron'));
            if ($cron === '') {
                set_alert_message('Cron expression is required for schedule mode.', 'danger');
                redirect('calculation');
            }
            $trigger['cron'] = $cron;
        }

        $metric = [
            'type' => 'calc',
            'key' => $key,
            'label' => trim((string) $this->input->post('label')),
            'unit' => trim((string) $this->input->post('unit')),
            'datatype' => $this->input->post('datatype') === 'int' ? 'int' : 'float',
            'enable' => true,
            'store_policy' => 'always',
            'pull_rate' => max(1, (int) $this->input->post('interval_sec')),
            'calc' => [
                'formula' => $formula,
                'inputs' => $normalizedInputs,
                'trigger' => $trigger,
                'decimals' => max(0, min(8, (int) $this->input->post('decimals')))
            ],
            'triggers' => [],
            'normal_condition' => new stdClass()
        ];
        $maxAge = trim((string) $this->input->post('max_age_sec'));
        if ($maxAge !== '') {
            $metric['calc']['maxAgeSec'] = max(0, (int) $maxAge);
        }

        if ($existingIndex >= 0) {
            $metrics[$existingIndex] = array_merge($metrics[$existingIndex], $metric);
        } else {
            $metrics[] = $metric;
        }
        $this->Calculation_model->save_metrics($deviceId, $metrics);
        if ($originalDeviceId && $originalDeviceId !== $deviceId) {
            $this->remove_metric($originalDeviceId, $originalKey);
        }
        set_alert_message('Calculation saved.', 'success');
        redirect('calculation');
    }

    public function delete()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $this->remove_metric((int) $this->input->post('device_id'), trim((string) $this->input->post('key')));
        set_alert_message('Calculation removed.', 'success');
        redirect('calculation');
    }

    private function remove_metric($deviceId, $key)
    {
        $device = $this->Calculation_model->get_device($deviceId);
        if (!$device || $key === '') {
            return false;
        }
        $metrics = array_values(array_filter($this->decode_metrics($device->metrics), function ($metric) use ($key) {
            return !(($metric['type'] ?? '') === 'calc' && ($metric['key'] ?? '') === $key);
        }));
        return $this->Calculation_model->save_metrics($deviceId, $metrics);
    }

    private function decode_metrics($value)
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        return is_array($decoded) ? $decoded : [];
    }
}
