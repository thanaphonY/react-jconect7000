<?php

use FontLib\Table\Type\name;

defined('BASEPATH') or exit('No direct script access allowed');

class Device extends CI_Controller
{

    protected $user_id;
    protected $userName;


    public function __construct()
    {
        parent::__construct();
        $this->load->model('Device_model');
        $this->load->model('Metrics_model');
        $this->load->model('Devicetemplate/Devicetemplate_model');
        $this->load->model('Device_categories_model');
        $this->load->model('Notification_manager/Notification_channel_model');
        $this->load->library('form_validation');
        $this->load->model("eventlogs/Eventlogs_model");
        $this->load->helper('eventlog'); // เพิ่ม eventlog helper
        $this->load->helper('notification_helper'); // เพิ่ม notification helper

        isLogin();
        site_require_global();
        $sessionUser = $this->session->get_userdata();
        $this->user_id = isset($sessionUser['user_details'][0]->ia_users_id)
            ? $sessionUser['user_details'][0]->ia_users_id : 1;

        $this->userName = isset($sessionUser['user_details'][0]->name)
            ? $sessionUser['user_details'][0]->name : '';

        $this->load->helper('language');
        $this->lang->load('device', $this->session->userdata('site_lang') ?: 'english');
        $this->lang->load('device_categories', $this->session->userdata('site_lang') ?: 'english');
        $this->lang->load('home', $this->session->userdata('site_lang') ?: 'english');
    }

    private function postedSiteNodeId()
    {
        if (!$this->db->field_exists('site_node_id', 'devices')) {
            return null;
        }
        $nodeId = $this->input->post('site_node_id');
        if ($nodeId === null || $nodeId === '') {
            return null;
        }
        return (int) $nodeId;
    }

    public function index()
    {
        $devices = $this->Device_model->getAll();
        
        // เพิ่มข้อมูล template image ให้แต่ละ device
        foreach ($devices as $device) {
            if ($device->device_template_id) {
                $template = $this->Devicetemplate_model->getById($device->device_template_id);
                $device->template_image = $template ? $template->image : null;
            } else {
                $device->template_image = null;
            }
        }
        
        $data['devices'] = $devices;
        $this->load->view('include/header');
        $this->load->view('device/index', $data);
        $this->load->view('include/footer');
    }

    /**
     * AJAX endpoint for server-side pagination and filtering
     */
    public function get_devices_ajax() {
        // Get parameters
        $status = $this->input->post('status');
        $template = $this->input->post('template');
        $location = $this->input->post('location');
        $search = $this->input->post('search_custom');
        $page = $this->input->post('page') ?: 1;
        $length = $this->input->post('length') ?: 10;
        
        // Calculate offset
        $offset = ($page - 1) * $length;

        try {
            // Get devices with pagination
            $result = $this->Device_model->getDevicesPaginated($status, $template, $location, $search, $length, $offset);
            
            // Get total count for pagination
            $totalCount = $this->Device_model->getDevicesCount($status, $template, $location, $search);
            
            $formattedData = [];
            
            foreach ($result['data'] as $device) {
                // Get device template info
                $deviceInfo = [];
                $templateImage = null;
                if ($device->device_template_id) {
                    $templateInfo = $this->Device_model->getDeviceTemplate($device->device_template_id);
                    if ($templateInfo) {
                        $deviceInfo = json_decode($templateInfo->device_information, true) ?: [];
                        $templateImage = $templateInfo->image;
                    }
                }
                
                // Get custom fields
                $customFields = $this->Device_model->getCustomFields($device->id);
                $customFieldsHtml = $this->Device_model->getCustomFieldsHtml($device->id);
                
                // Generate image URL using backup logic
                $imageUrl = '';
                if (!empty($device->image)) {
                    $imageUrl = base_url('uploads/devices/' . $device->image);
                } elseif (!empty($templateImage)) {
                    $imageUrl = base_url('uploads/device_templates/' . $templateImage);
                }
                
                $formattedData[] = [
                    'id' => $device->id,
                    'name' => htmlspecialchars($device->name),
                    'image' => $imageUrl ?: base_url('assets/images/default-device.png'),
                    'image_url' => $imageUrl,
                    'brand' => htmlspecialchars($deviceInfo['Brand'] ?? '-'),
                    'model' => htmlspecialchars($deviceInfo['Model'] ?? '-'),
                    'serial_number' => htmlspecialchars($device->serial_number),
                    'location' => htmlspecialchars(site_location_label(isset($device->site_node_name) ? $device->site_node_name : '', $device->location)),
                    'description' => htmlspecialchars($device->description ?? ''),
                    'active' => ($device->is_active === 't') ? '<span class="badge bg-success">' . lang('status_active') . '</span>' : '<span class="badge bg-secondary">' . lang('status_inactive') . '</span>',
                    'custom_fields' => $customFields,
                    'custom_fields_html' => $customFieldsHtml,
                    'actions' => $this->_getActionButtonsHtml($device->id)
                ];
            }
            
            $response = [
                'status' => 'success',
                'data' => $formattedData,
                'total' => $totalCount,
                'current_page' => (int)$page,
                'total_pages' => ceil($totalCount / $length),
                'recordsFiltered' => $totalCount,
                'recordsTotal' => $totalCount
            ];
            
        } catch (Exception $e) {
            log_message('error', 'Error in get_devices_ajax: ' . $e->getMessage());
            $response = [
                'status' => 'error',
                'message' => 'Database error occurred'
            ];
        }
        
        $this->output->set_content_type('application/json');
        echo json_encode($response);
    }

    /**
     * Get filter options for dropdowns
     */
    public function get_filter_options()
    {
        $locations = $this->Device_model->getUniqueLocations();
        $templates = $this->Device_model->getUniqueTemplates();
        $categories = $this->Device_model->getUniqueCategories();
        
        $response = [
            'locations' => $locations,
            'templates' => $templates,
            'categories' => $categories
        ];
        
        $this->output->set_content_type('application/json')->set_output(json_encode($response));
    }

    /**
     * Generate device image HTML
     */
    private function _getDeviceImageHtml($device, $templateImage)
    {
        $deviceImage = !empty($device->image) ? base_url('uploads/devices/' . $device->image) : '';
        $templateImageUrl = !empty($templateImage) ? base_url('uploads/device_templates/' . $templateImage) : '';
        
        return '<div class="device-image-container" data-device-image="' . $deviceImage . '" data-template-image="' . $templateImageUrl . '">
                    <i class="bi bi-box"></i>
                </div>';
    }

    /**
     * Generate device image URL for Card view
     */
    private function _getDeviceImageUrl($device, $templateImage)
    {
        if (!empty($device->image)) {
            return base_url('uploads/devices/' . $device->image);
        } elseif (!empty($templateImage)) {
            return base_url('uploads/device_templates/' . $templateImage);
        }
        return '';
    }

    /**
     * Generate action buttons HTML
     */
    private function _getActionButtonsHtml($deviceId)
    {
        // Debug: Check session language
        $currentLang = $this->session->userdata('site_lang') ?: 'english';
        log_message('debug', 'Current session language: ' . $currentLang);
        log_message('debug', 'View button text: ' . lang('view'));
        log_message('debug', 'Edit button text: ' . lang('edit'));
        log_message('debug', 'Delete button text: ' . lang('delete'));
        
        return '<div class="btn-group" role="group">
                    <a href="' . base_url('device/view/' . $deviceId) . '" class="btn btn-info btn-sm">
                        <i class="bi bi-eye"></i> ' . lang('view') . '
                    </a>
                    <a href="' . base_url('device/edit/' . $deviceId) . '" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil"></i> ' . lang('edit') . '
                    </a>
                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteDevice(' . $deviceId . ')">
                        <i class="bi bi-trash"></i> ' . lang('delete') . '
                    </button>
                </div>';
    }


    public function view($id)
    {
        //if empty id redirect to home
        if (empty($id)) {
            redirect('home');
        }



        $device = $this->Device_model->getById($id);

        if (!$device) {
            redirect('device');
        }

        $data['device'] = $device;
        $template = $this->Devicetemplate_model->getById($device->device_template_id);
        $data['template'] = $template;

        $data['comm'] = json_decode($device->communication_config ?? '{}', true);
        $data['latest_metrics'] = $this->Metrics_model->get_latest_by_device($device->id);
        $this->load->helper('device_display');

        $protocol = strtolower((string) ($template->protocol ?? ''));
        if ($protocol === '' && !empty($data['comm']['protocol'])) {
            $protocol = strtolower((string) $data['comm']['protocol']);
        }
        $data['protocol'] = $protocol;

        $this->load->view('include/header');
        $this->load->view('/device/view', $data);
        $this->load->view('include/footer');
    }


    public function add()
    {
        $data['templates'] = $this->Devicetemplate_model->getAllTemplate();
        $data['channels'] = $this->Notification_channel_model->getAll();
        $data['calc_devices'] = $this->metric_source_devices();
        $data['gateway_devices'] = $this->gateway_devices();
        $this->load->view('include/header');
        $this->load->view('/device/form_add', $data);
        $this->load->view('include/footer');
    }

    public function edit($id)
    {
        $device = $this->Device_model->getById($id);
        $data['device'] = $device;

        $protocol = 'modbus_tcp'; // default fallback

        if ($device && $device->device_template_id) {
            $template = $this->Devicetemplate_model->getById($device->device_template_id);
            $data['template'] = $template;
            if ($template && isset($template->protocol)) {
                $protocol = $template->protocol;
            }
            
            // Get template device_information for potential custom fields
            $templateDeviceInfo = [];
            if ($template && !empty($template->device_information)) {
                try {
                    $templateDeviceInfo = json_decode($template->device_information, true);
                    if (!is_array($templateDeviceInfo)) {
                        $templateDeviceInfo = [];
                    }
                } catch (Exception $e) {
                    $templateDeviceInfo = [];
                }
            }
            $data['template_device_info'] = $templateDeviceInfo;
        }

        // Load custom fields from config
        $customFields = [];
        if ($device && !empty($device->config)) {
            try {
                $config = json_decode($device->config, true);
                if (is_array($config) && isset($config['custom_fields'])) {
                    $customFields = $config['custom_fields'];
                }
            } catch (Exception $e) {
                $customFields = [];
            }
        }
        $data['custom_fields_json'] = json_encode($customFields);

        $data['channels'] = $this->Notification_channel_model->getAll();
        $data['protocol'] = $protocol;
        $data['gateway_devices'] = $this->gateway_devices($id);
        
        $this->load->view('include/header');
        $this->load->view('/device/form_edit', $data);
        $this->load->view('include/footer');
    }

    /**
     * AJAX: device/metric options for calc metrics and snapshot triggers.
     */
    public function metric_source_devices_ajax()
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($this->metric_source_devices(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Device/metric options used by the shared Gen3-compatible metric builder.
     */
    private function metric_source_devices()
    {
        $result = [];
        foreach ($this->Device_model->getDevicesForMetricBuilder() as $device) {
            $metrics = json_decode($device->metrics ?: '[]', true);
            $result[] = [
                'id' => (int) $device->id,
                'name' => $device->name,
                'metrics' => $this->compactMetricsForBuilder(is_array($metrics) ? $metrics : [])
            ];
        }
        return $result;
    }

    /**
     * Keep only fields needed by metric builder dropdowns (calc / snapshot / gateway map).
     */
    private function compactMetricsForBuilder(array $metrics)
    {
        $compact = [];
        foreach ($metrics as $metric) {
            if (!is_array($metric) || empty($metric['key'])) {
                continue;
            }
            $item = [
                'key' => $metric['key'],
                'label' => $metric['label'] ?? $metric['key'],
                'enable' => $metric['enable'] ?? true,
            ];
            if (!empty($metric['triggers']) && is_array($metric['triggers'])) {
                $item['triggers'] = array_map(function ($trigger) {
                    if (!is_array($trigger)) {
                        return [];
                    }
                    return [
                        'enable' => $trigger['enable'] ?? true,
                        'operator' => $trigger['operator'] ?? '',
                        'value' => $trigger['value'] ?? '',
                        'message' => $trigger['message'] ?? '',
                        'clear_condition' => $trigger['clear_condition'] ?? null,
                        'duration' => $trigger['duration'] ?? [],
                    ];
                }, $metric['triggers']);
            }
            $compact[] = $item;
        }
        return $compact;
    }

    private function gateway_devices($excludeId = null)
    {
        $result = [];
        foreach ($this->Device_model->getDevicesForMetricBuilder() as $device) {
            if ($excludeId !== null && (int) $device->id === (int) $excludeId) {
                continue;
            }
            $config = json_decode($device->config ?: '{}', true);
            if (is_array($config) && strtolower((string) ($config['role'] ?? '')) === 'gateway') {
                $result[] = [
                    'id' => (int) $device->id,
                    'name' => $device->name
                ];
            }
        }
        return $result;
    }

    public function save($id = null)
    {
        $this->form_validation->set_rules('name', 'Device Name', 'required');
        $this->form_validation->set_rules('device_template_id', 'Device Template', 'required');

        if ($this->form_validation->run()) {
            $rawComm = $this->input->post('communication_config');
            $rawmetrics = $this->input->post('metrics');
            $deviceInformationJson = $this->input->post('device_information_json');

            // Process custom fields from form
            $customFields = [];
            if (!empty($deviceInformationJson)) {
                try {
                    $customFields = json_decode($deviceInformationJson, true);
                    if (!is_array($customFields)) {
                        $customFields = [];
                    }
                } catch (Exception $e) {
                    $customFields = [];
                }
            }

            // Get template (needed for protocol + category)
            $templateId = $this->input->post('device_template_id');
            $template = null;
            if ($templateId) {
                $template = $this->Devicetemplate_model->getById($templateId);
            }

            // Get template device_information and copy non-Brand/Model fields to custom fields
            /*
            if ($templateId) {
                $template = $this->Devicetemplate_model->getById($templateId);
                if ($template && !empty($template->device_information)) {
                    try {
                        $templateInfo = json_decode($template->device_information, true);
                        if (is_array($templateInfo)) {
                            // Copy fields that are not Brand or Model
                            foreach ($templateInfo as $key => $value) {
                                if ($key !== 'Brand' && $key !== 'Model' && !empty($value)) {
                                    // Only add if not already in custom fields (to avoid overwriting user input)
                                    if (!isset($customFields[$key])) {
                                        $customFields[$key] = $value;
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Continue if template parsing fails
                    }
                }
            }*/

            $deviceRole = strtolower((string) $this->input->post('device_role'));
            if (!in_array($deviceRole, ['native_device', 'gateway', 'endpoint'], true)) {
                $deviceRole = 'native_device';
            }

            // Native and collector metrics are copied directly from the
            // template. Endpoint metrics retain the source mappings selected
            // in the wizard and are normalized below.
            if (!$id && $template && $deviceRole !== 'endpoint') {
                $templateMetrics = is_string($template->metrics)
                    ? json_decode($template->metrics, true)
                    : $template->metrics;
                $rawmetrics = json_encode(
                    is_array($templateMetrics) ? $templateMetrics : [],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }

            // Get existing config if updating
            $existingConfig = [];
            if ($id) {
                $existingDevice = $this->Device_model->getById($id);
                if ($existingDevice && !empty($existingDevice->config)) {
                    try {
                        $existingConfig = json_decode($existingDevice->config, true);
                        if (!is_array($existingConfig)) {
                            $existingConfig = [];
                        }
                    } catch (Exception $e) {
                        $existingConfig = [];
                    }
                }
            }

            // Merge custom fields into config
            $config = $existingConfig;
            $config['custom_fields'] = $customFields;

            // Gen3-compatible device role configuration.
            $config['role'] = $deviceRole;

            // Camera snapshot triggers (RTSP): JSON array on devices.config.snapshot_triggers
            $rawSnapTriggers = $this->input->post('snapshot_triggers_json');
            if ($rawSnapTriggers !== null && $rawSnapTriggers !== '') {
                $parsedTriggers = json_decode($rawSnapTriggers, true);
                if (is_array($parsedTriggers)) {
                    $config['snapshot_triggers'] = $parsedTriggers;
                }
            }

            if ($deviceRole === 'endpoint') {
                $gatewayDeviceId = (int) $this->input->post('gateway_device_id');
                $gatewayDevice = $gatewayDeviceId > 0 ? $this->Device_model->getById($gatewayDeviceId) : null;
                $gatewayConfig = $gatewayDevice && !empty($gatewayDevice->config)
                    ? json_decode($gatewayDevice->config, true)
                    : [];
                $isGateway = is_array($gatewayConfig)
                    && strtolower((string) ($gatewayConfig['role'] ?? '')) === 'gateway';

                if (!$gatewayDevice || !$isGateway || ($id && $gatewayDeviceId === (int) $id)) {
                    set_alert_message('Please select a valid Gateway Collector.', 'danger');
                    redirect($id ? 'device/edit/' . $id : 'device/add');
                    return;
                }

                $config['via_gateway'] = $gatewayDeviceId;
                $config['endpoint_template_id'] = (int) $templateId;

                $communicationConfig = is_string($rawComm) ? json_decode($rawComm, true) : $rawComm;
                if (!is_array($communicationConfig)) {
                    $communicationConfig = [];
                }
                $communicationConfig['protocol'] = 'gateway_endpoint';
                if (!isset($communicationConfig['source']) || !is_array($communicationConfig['source'])) {
                    $communicationConfig['source'] = [];
                }
                $communicationConfig['source']['gateway_device_id'] = $gatewayDeviceId;
                $rawComm = json_encode(
                    $communicationConfig,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                $endpointMetrics = is_string($rawmetrics) ? json_decode($rawmetrics, true) : $rawmetrics;
                if ((!is_array($endpointMetrics) || count($endpointMetrics) === 0) && $template) {
                    $endpointMetrics = is_string($template->metrics)
                        ? json_decode($template->metrics, true)
                        : $template->metrics;
                }
                if (!is_array($endpointMetrics)) {
                    $endpointMetrics = [];
                }
                foreach ($endpointMetrics as &$endpointMetric) {
                    if (!is_array($endpointMetric)) {
                        continue;
                    }
                    $endpointMetric['protocol'] = 'gateway_endpoint';
                    $endpointMetric['type'] = 'gateway_mapped';
                    $endpointMetric['pull_rate'] = 0;
                    if (!isset($endpointMetric['source']) || !is_array($endpointMetric['source'])) {
                        $endpointMetric['source'] = [];
                    }
                    $endpointMetric['source']['gateway_device_id'] = $gatewayDeviceId;
                }
                unset($endpointMetric);
                $rawmetrics = json_encode(
                    $endpointMetrics,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            } else {
                unset($config['via_gateway'], $config['endpoint_template_id']);
            }

            // Ensure communication_config.protocol matches the template (needed by dataedge routing).
            if ($deviceRole !== 'endpoint' && $template && !empty($template->protocol)) {
                $communicationConfig = is_string($rawComm) ? json_decode($rawComm, true) : $rawComm;
                if (!is_array($communicationConfig)) {
                    $communicationConfig = [];
                }
                if (empty($communicationConfig['protocol'])) {
                    $communicationConfig['protocol'] = $template->protocol;
                }
                $rawComm = json_encode(
                    $communicationConfig,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }

            $deviceData = [
                'device_template_id' => $this->input->post('device_template_id'),
                'name' => $this->input->post('name'),
                'serial_number' => $this->input->post('serial_number'),
                'location' => $this->input->post('location'),
                'description' => $this->input->post('description'),
                'communication_config' => is_string($rawComm) ? $rawComm : json_encode($rawComm),
                'metrics' => is_string($rawmetrics) ? $rawmetrics : json_encode($rawmetrics),
                'config' => json_encode($config),
                'is_active' => $this->input->post('is_active') ? true : false
            ];
            if (array_key_exists('site_node_id', $_POST) && $this->db->field_exists('site_node_id', 'devices')) {
                $deviceData['site_node_id'] = $this->postedSiteNodeId();
            }
            if ($template && isset($template->category_id)) {
                $deviceData['category_id'] = (int) $template->category_id;
            }

            $image = $this->uploadImage();
            if ($image) {
                $deviceData['image'] = $image;
            }

            if ($id) {
                // Get old values for logging
                $old_device = $this->Device_model->getById($id);
                $old_device_data = $old_device ? (array) $old_device : [];
                
                $deviceData['updated_at'] = date('Y-m-d H:i:s');
                $result = $this->Device_model->update($id, $deviceData);
                
                // Log the update
                if ($result) {
                    log_record_updated(
                        'Device',
                        $id,
                        $deviceData['name'],
                        $old_device_data,
                        $deviceData
                    );
                }
                set_alert_message(lang('device_updated_successfully'), 'success');
            } else {
                $deviceData['created_at'] = date('Y-m-d H:i:s');
                $result = $this->Device_model->insert($deviceData);
                
                // Log the creation
                if ($result) {
                    log_record_created(
                        'Device',
                        $result,
                        $deviceData['name'],
                        $deviceData
                    );
                }
                set_alert_message(lang('device_created_successfully'), 'success');
            }

            

            redirect($id ? 'device/edit/' . $id : 'device');
        } else {
            set_alert_message(validation_errors(), 'danger');
            redirect($id ? 'device/edit/' . $id : 'device/add');
        }
    }

    public function delete($id)
    {
        // Debug logging
        log_message('debug', 'Delete device called for ID: ' . $id);
        log_message('debug', 'Request method: ' . $this->input->method());
        log_message('debug', 'Content-Type: ' . $this->input->get_request_header('Content-Type'));
        log_message('debug', 'X-Requested-With: ' . $this->input->get_request_header('X-Requested-With'));
        
        try {
            $device = $this->Device_model->getById($id);
            
            if (!$device) {
                $response = [
                    'status' => 'error',
                    'message' => lang('device_not_found')
                ];
            } else {
                if ($this->Device_model->delete($id)) {
                    // Log the deletion
                    log_record_deleted(
                        'Device',
                        $id,
                        $device->name,
                        (array) $device
                    );
                    
                    $response = [
                        'status' => 'success',
                        'message' => lang('device_deleted_successfully')
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => lang('failed_to_delete_device')
                    ];
                }
            }
            
            // Check if this is an AJAX request (improved detection)
            $isAjax = $this->input->is_ajax_request() || 
                     $this->input->get_request_header('X-Requested-With') === 'XMLHttpRequest' ||
                     $this->input->get_request_header('Content-Type') === 'application/x-www-form-urlencoded';
            
            log_message('debug', 'Is AJAX request: ' . ($isAjax ? 'true' : 'false'));
            log_message('debug', 'Response: ' . json_encode($response));
            
            if ($isAjax) {
                $this->output->set_content_type('application/json')->set_output(json_encode($response));
            } else {
                // For non-AJAX requests, use notification helper and redirect
                if ($response['status'] === 'success') {
                    set_alert_message($response['message'], 'success');
                } else {
                    set_alert_message($response['message'], 'danger');
                }
            }
            
        } catch (Exception $e) {
            log_message('error', 'Exception in delete device: ' . $e->getMessage());
            $response = [
                'status' => 'error',
                'message' => 'Error deleting device: ' . $e->getMessage()
            ];
            
            $isAjax = $this->input->is_ajax_request() || 
                     $this->input->get_request_header('X-Requested-With') === 'XMLHttpRequest' ||
                     $this->input->get_request_header('Content-Type') === 'application/x-www-form-urlencoded';
            
            if ($isAjax) {
                $this->output->set_content_type('application/json')->set_output(json_encode($response));
            } else {
                set_alert_message($response['message'], 'danger');
            }
        }
    }

    public function ajax_template_preview($id)
    {
        $template = $this->Devicetemplate_model->getById($id);
        
        // Debug logging
        log_message('debug', 'Template preview for ID ' . $id . ': ' . json_encode($template));
        
        $output = [
            'protocol' => $template->protocol,
            'communication_config' => json_decode($template->communication_config, true),
            'metrics' => json_decode($template->metrics, true),
            'device_information' => json_decode($template->device_information, true),
        ];
        
        // Debug logging
        log_message('debug', 'Template preview output: ' . json_encode($output));
        
        $this->output->set_content_type('application/json')->set_output(json_encode($output));
    }


    private function uploadImage($field = 'image')
    {

        $config['upload_path'] = './uploads/devices/';
        $config['allowed_types'] = 'jpg|jpeg|png|gif|webp|svg';
        $config['max_size'] = 2048;
        $config['encrypt_name'] = true;

        $this->load->library('upload', $config);

        if (!is_dir($config['upload_path'])) {
            mkdir($config['upload_path'], 0755, true);
        }

        if ($this->upload->do_upload($field)) {
            $data = $this->upload->data();
            return $data['file_name'];
        }

        return null;
    }

    public function metrics($device_id)
    {
        if (!$device_id) {
            return $this->output->set_content_type('application/json')->set_output(json_encode([]));
        }

        $this->load->model('Metrics_model');
        $metrics = $this->Metrics_model->get_latest_metrics($device_id);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($metrics));
    }


    // POST /device/save_layout/:id  body: {"background": "map_x.png", "items": [{x, y, label, metric_key, unit, show}]}
    public function save_layout($id)
    {
        $device = $this->Device_model->getById($id);
        if (!$device || $this->input->method() !== 'post') {
            return $this->output->set_status_header(404)->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Device not found']));
        }

        $input = json_decode($this->input->raw_input_stream, true);
        if (!is_array($input)) {
            return $this->output->set_status_header(400)->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Invalid JSON']));
        }

        // Same shape as the dashboard map_view widget (1000 x 600 viewBox).
        $background = basename((string) ($input['background'] ?? ''));
        if (!preg_match('/^[\w.-]+\.(png|jpe?g|gif)$/i', $background)) {
            $background = '';
        }
        $shows = ['label', 'value', 'label+value', 'value+unit', 'label+value+unit', 'point'];
        $items = [];
        foreach (array_slice((array) ($input['items'] ?? []), 0, 200) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $show = (string) ($item['show'] ?? 'label+value+unit');
            $items[] = [
                'x' => round(max(0, min(1000, (float) ($item['x'] ?? 0))), 1),
                'y' => round(max(0, min(600, (float) ($item['y'] ?? 0))), 1),
                'label' => mb_substr(trim((string) ($item['label'] ?? '')), 0, 100),
                'metric_key' => mb_substr(trim((string) ($item['metric_key'] ?? '')), 0, 150),
                'unit' => mb_substr(trim((string) ($item['unit'] ?? '')), 0, 20),
                'show' => in_array($show, $shows, true) ? $show : 'label+value+unit',
            ];
        }

        $layout = json_decode((string) ($device->dashboard_layout ?? ''), true);
        if (!is_array($layout)) {
            $layout = [];
        }
        $old = $layout['map_view'] ?? [];
        $layout['map_view'] = ['background' => $background, 'items' => $items];

        $this->Device_model->update((int) $device->id, ['dashboard_layout' => json_encode($layout, JSON_UNESCAPED_UNICODE)]);
        log_record_updated('device', (int) $device->id, $device->name, ['map_view' => $old], ['map_view' => $layout['map_view']]);

        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'success', 'map_view' => $layout['map_view']]));
    }

    // GET /device/layout_values/:id  → {"metric.key": value, ...} (same display values as the Dashboard tab)
    public function layout_values($id)
    {
        $this->load->helper('device_display');
        $values = device_display_values($this->Metrics_model->get_latest_by_device((int) $id));

        return $this->output->set_content_type('application/json')->set_output(json_encode($values));
    }

    // GET /device/data/latest/:device_id/:metric_key
    public function data_latest($device_id, $metric_key)
    {
        $data = $this->Metrics_model->get_latest_value($device_id, $metric_key);
        if (!$data) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['value' => null, 'timestamp' => null]));
        }

        $value = isset($data['value']) ? (float) $data['value'] : null;
        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['value' => $value, 'timestamp' => $data['timestamp']]));
    }

    // GET /device/data/timeseries/:device_id/:metric_key?range=24h
    public function data_timeseries($device_id, $metric_key)
    {
        $range = $this->input->get('range') ?? '24h';
        $result = $this->Metrics_model->get_timeseries($device_id, $metric_key, $range);

        $timestamps = array_column($result, 'timestamp');
        $values = array_map('floatval', array_column($result, 'value'));

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'series' => $values,
                'timestamps' => $timestamps
            ]));
    }


    public function data_timeseries_chart($device_id, $metric_key)
    {
        $range = $this->input->get('range') ?? '24h';
        $result = $this->Metrics_model->get_timeseries_chart($device_id, $metric_key, $range);

        // สมมุติว่า timestamp มาจาก database เป็น UTC+7 อยู่แล้ว
        $timestamps = array_map(function ($ts) use ($range) {
            $dt = new DateTime($ts, new DateTimeZone('Asia/Bangkok')); // ไม่ต้องใส่ TimeZone ถ้า timestamp เป็นเวลาท้องถิ่นแล้ว
            $dt->setTimezone(new DateTimeZone('Asia/Bangkok'));
            switch ($range) {
                case '24h':
                    return $dt->format('H:i');     // ชั่วโมง:นาที (เช่น 14:00)
                case '31d':
                    return $dt->format('d M');     // วัน เดือน (เช่น 19 Jul)
                case '1y':
                    return $dt->format('M Y');     // เดือน ปี (เช่น Jul 2025)
                default:
                    return $dt->format('Y-m-d H:i:s');
            }
        }, array_column($result, 'timestamp'));

        $values = array_map(function ($v) {
            return ($v === null || $v === '') ? null : (float) $v;
        }, array_column($result, 'value'));

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'series' => $values,
                'timestamps' => $timestamps,
                'raw_timestamps' => array_column($result, 'timestamp')
            ]));
    }



    public function metrics_latest()
    {
        $device_id = $this->input->get('device_id');
        $metric_key = $this->input->get('metric_key');

        if (!$device_id || !$metric_key) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Missing parameters'
                ]));
        }

        $result = $this->Metrics_model->get_latest_value($device_id, $metric_key);
        if (!$result) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'No data found'
                ]));
        }

        $datatype = $this->Metrics_model->get_metric_datatype($device_id, $metric_key);
        $value = $this->Metrics_model->resolve_typed_value($result, $datatype);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'success',
                'value' => $value,
                'datatype' => $datatype,
                'timestamp' => is_array($result) ? $result['timestamp'] : $result->timestamp,
                'alarm' => is_array($result) ? ($result['is_alarm'] ?? false) : ($result->is_alarm ?? false),
            ]));
    }

    private function uuidv4_nodash()
    {
        return str_replace('-', '', $this->uuidv4());
    }

    private function uuidv4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
    public function upload_csv()
    {

        $this->load->view('include/header');
        $this->load->view('device_upload_csv');
        $this->load->view('include/footer');
    }



    private function randomMetric()
    {
        $template = [
            ["key" => "Temperature", "unit" => "°C",   "label" => "Temperature"],
            ["key" => "Humidity",    "unit" => "%rH",  "label" => "Humidity"],
            ["key" => "Bat_Level",   "unit" => "%",    "label" => "Battery Level"],
            ["key" => "Bat_mV",      "unit" => "mV",   "label" => "Battery Voltage"],
            ["key" => "rssi",        "unit" => "",     "label" => "RSSI"],
            ["key" => "snr",         "unit" => "",     "label" => "SNR"]
        ];

        $metrics = [];
        foreach ($template as $item) {
            $metrics[] = [
                "id" => $this->uuidv4_nodash(),  // 👈 ไม่มีขีด
                "key" => $item['key'],
                "unit" => $item['unit'],
                "label" => $item['label'],
                "scale" => 1,
                "enable" => "true",
                "datatype" => "int",
                "protocol" => "lorawan",
                "triggers" => [],
                "pull_rate" => 10000,
                "store_policy" => "always",
                "normal_condition" => []
            ];
        }
        return $metrics;
    }

    public function automaticadd()
    {
        $data = [
            [
                'name' => "dd",
                'serial_number' => "dd",
                'location' => "dd",
                'description' => "dd",
                "app_key" => "67c63ed5d5126b38e1b026b9a2eef01a",
                "dev_eui" => "a840410d0189c237",
                "application_server" => "2",
            ]

        ];

        foreach ($data as $item) {
            $device_template_id = 11;
            $name = $item['name'];
            $serial_number = $item['serial_number'];
            $location = $item['location'];
            $description = $item['description'];
            $is_active = true;
            $communication_config = isset($item['communication_config']) ? json_encode($item['communication_config']) : null;
            $metrics = $this->randomMetric();

            $communication_config = [
                "app_eui" => 'A840410000000100',
                "app_key" => $item['app_key'],
                "dev_eui" => $item['dev_eui'],
                "device_profile_id" => "82b307a1-3ffd-4840-a0af-6e4587d43e9b",
                "application_server" => $item['application_server']
            ];

            $deviceData = [
                'device_template_id' => $device_template_id,
                'name' => $name,
                'serial_number' => $serial_number,
                'location' => $location,
                'description' => $description,
                'communication_config' => json_encode($communication_config),
                'metrics' => json_encode($metrics),
                'is_active' => $is_active
            ];
            $this->Device_model->insert($deviceData);
        }
    }


    public function import_csv()
    {
        if (!isset($_FILES['csv_file']['tmp_name'])) {
            show_error('No file uploaded');
            return;
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $csvData = array_map('str_getcsv', file($file));
        $header = array_map('trim', $csvData[0]);
        unset($csvData[0]);

        foreach ($csvData as $row) {
            $row = array_map('trim', $row);
            $rowData = array_combine($header, $row);

            // กำหนดค่าคงที่
            $rowData['app_eui'] = 'A840410000000100';

            $comm = [
                'app_eui' => $rowData['app_eui'],
                'app_key' => $rowData['app_key'],
                'dev_eui' => $rowData['dev_eui'],
                'device_profile_id' => '82b307a1-3ffd-4840-a0af-6e4587d43e9b',
                'application_server' => $rowData['application_server']
            ];

            $deviceData = [
                'device_template_id'   => 11,
                'name'                 => $rowData['name'],
                'serial_number'        => $rowData['serial_number'],
                'location'             => $rowData['location'],
                'description'          => $rowData['description'],
                'communication_config' => json_encode($comm),
                'metrics'              => json_encode($this->randomMetric()),
                'is_active'            => true
            ];

            $this->Device_model->insert($deviceData);

            // Register device to ChirpStack
            $registerResult = $this->Device_model->registerDevice($comm, $deviceData);

            //delay Register 100,ms
            usleep(50000); // หน่วงเวลา 50 มิลลิวินาทีเพื่อหลีกเลี่ยจากการเรียก API พร้อมกันมากเกินไป
            if (!$registerResult['success']) {
                echo "Error registering device: " .  $deviceData['name'] . "\n";
            } else {            

                echo "Device registered successfully: " . $deviceData['name'] . "\n";
            }
        }
        echo "Import success.";
    }

    /**
     * Debug method to test database connection and basic query
     */
    public function debug()
    {
        try {
            // Test database connection
            $this->db->simple_query('SELECT 1');
            
            // Get total devices
            $totalDevices = $this->db->where('is_deleted', false)->count_all_results('devices');
            
            // Test custom fields functionality
            $testDevice = $this->db->where('is_deleted', false)->limit(1)->get('devices')->row();
            $customFieldsTest = [];
            $customFieldsHtmlTest = '';
            
            if ($testDevice) {
                $customFieldsTest = $this->Device_model->getCustomFields($testDevice->id);
                $customFieldsHtmlTest = $this->Device_model->getCustomFieldsHtml($testDevice->id);
            }
            
            $response = [
                'status' => 'success',
                'message' => 'Database connected successfully',
                'total_devices' => $totalDevices,
                'test_device_id' => $testDevice ? $testDevice->id : null,
                'custom_fields_test' => $customFieldsTest,
                'custom_fields_html_test' => $customFieldsHtmlTest,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        $this->output->set_content_type('application/json')->set_output(json_encode($response));
    }

    /**
     * Test AJAX endpoint with minimal data
     */
    public function test_ajax()
    {
        $response = [
            'draw' => 1,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ];
        
        $this->output->set_content_type('application/json');
        $this->output->set_output(json_encode($response));
    }

    /**
     * Test AJAX endpoint with real data
     */
    public function test_real_data()
    {
        try {
            // Get real data from model
            $result = $this->Device_model->getDevicesPaginated(0, 5, '', '', '', '', 'devices.name', 'asc');
            
            // Format data for DataTables
            $formattedData = [];
            foreach ($result['data'] as $device) {
                // Get template image
                $templateImage = null;
                if ($device->device_template_id) {
                    $template = $this->Devicetemplate_model->getById($device->device_template_id);
                    $templateImage = $template ? $template->image : null;
                }
                
                // Parse device_information JSONB
                $deviceInfo = json_decode($device->device_information ?? '{}', true);
                
                $formattedData[] = [
                    'image' => $this->_getDeviceImageHtml($device, $templateImage),
                    'image_url' => $this->_getDeviceImageUrl($device, $templateImage), // เพิ่ม URL รูปสำหรับ Card view
                    'name' => htmlspecialchars($device->name),
                    'brand' => htmlspecialchars($deviceInfo['Brand'] ?? '-'),
                    'model' => htmlspecialchars($deviceInfo['Model'] ?? '-'),
                    'serial_number' => htmlspecialchars($device->serial_number),
                    'location' => htmlspecialchars(site_location_label(isset($device->site_node_name) ? $device->site_node_name : '', $device->location)),
                    'description' => htmlspecialchars($device->description ?? ''),
                    'active' => ($device->is_active === true) ? '<span class="badge bg-success">' . lang('status_active') . '</span>' : '<span class="badge bg-secondary">' . lang('status_inactive') . '</span>',
                    'actions' => $this->_getActionButtonsHtml($device->id),
                    'data_active' => ($device->is_active === true) ? 'active' : 'inactive',
                    'data_template' => htmlspecialchars($device->template_name),
                    'data_location' => htmlspecialchars($device->location),
                    'device_information' => $device->device_information
                ];
            }
            
            // Return JSON response
            $response = [
                'draw' => 1,
                'recordsTotal' => $result['totalRecords'],
                'recordsFiltered' => $result['filteredRecords'],
                'data' => $formattedData
            ];
            
            $this->output->set_content_type('application/json');
            $this->output->set_output(json_encode($response));
            
        } catch (Exception $e) {
            $errorResponse = [
                'draw' => 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ];
            
            $this->output->set_content_type('application/json');
            $this->output->set_output(json_encode($errorResponse));
        }
    }
}
