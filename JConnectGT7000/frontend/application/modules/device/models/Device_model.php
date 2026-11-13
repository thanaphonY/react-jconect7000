<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Device_model extends CI_Model
{
    private $table = 'devices';

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('language');
        $this->lang->load('device', $this->session->userdata('site_lang') ?: 'english');
    }

    public function getAll()
    {
        $this->db->select('devices.*, templates.name as template_name, templates.device_information, dc.name as category_name, dc.icon as category_icon, dc.color as category_color');
        $this->db->from('devices');
        $this->db->join('device_templates as templates', 'templates.id = devices.device_template_id', 'left');
        $this->db->join('device_categories dc', 'dc.id = devices.category_id', 'left');
        $this->db->where('devices.is_deleted', false);
        $this->db->order_by('devices.name', 'asc');
        return $this->db->get()->result();
    }

    /**
     * Get custom fields from device config
     */
    public function getCustomFields($deviceId)
    {
        try {
            $device = $this->db->select('config')
                        ->where('id', $deviceId)
                        ->get('devices')
                        ->row();
            
            if (!$device || !$device->config) {
                return null;
            }
            
            $config = json_decode($device->config, true);
            return isset($config['custom_fields']) ? $config['custom_fields'] : null;
            
        } catch (Exception $e) {
            log_message('error', 'Error getting custom fields for device ' . $deviceId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get custom fields as HTML for display
     */
    public function getCustomFieldsHtml($deviceId)
    {
        try {
            $customFields = $this->getCustomFields($deviceId);
            
            if (!$customFields || empty($customFields)) {
                return '<span class="text-muted">' . lang('no_additional_info') . '</span>';
            }
            
            $html = '<div class="custom-fields-display">';
            
            // Handle both array format and object format
            if (is_array($customFields)) {
                foreach ($customFields as $key => $value) {
                    if (is_string($key) && !empty($value)) {
                        $html .= '<div class="mb-1">';
                        $html .= '<small class="text-muted">' . htmlspecialchars($key) . ':</small> ';
                        $html .= '<span class="fw-medium">' . htmlspecialchars($value) . '</span>';
                        $html .= '</div>';
                    }
                }
            }
            
            $html .= '</div>';
            
            return $html;
            
        } catch (Exception $e) {
            log_message('error', 'Error getting custom fields HTML for device ' . $deviceId . ': ' . $e->getMessage());
            return '<span class="text-muted">' . lang('no_additional_info') . '</span>';
        }
    }

    /**
     * Get devices with server-side pagination and filtering
     */
    public function getDevicesPaginated($status = '', $template = '', $location = '', $search = '', $length = 10, $offset = 0) {
        $select = 'devices.*, device_templates.name as template_name';
        if ($this->db->field_exists('site_node_id', 'devices')) {
            $select .= ', site_nodes.name AS site_node_name';
        }
        $this->db->select($select);
        $this->db->from('devices');
        $this->db->join('device_templates', 'devices.device_template_id = device_templates.id', 'left');
        if ($this->db->field_exists('site_node_id', 'devices')) {
            $this->db->join('site_nodes', 'site_nodes.id = devices.site_node_id', 'left');
        }
        $this->db->where('devices.is_deleted', false);
        
        // Apply filters
        if (!empty($status)) {
            if ($status === 'active') {
                $this->db->where('devices.is_active', true);
            } elseif ($status === 'inactive') {
                $this->db->where('devices.is_active', false);
            }
        }
        
        if (!empty($template)) {
            $this->db->where('device_templates.name', $template);
        }
        
        if (!empty($location)) {
            $this->db->where('devices.location', $location);
        }
        
        // Apply search
        if (!empty($search)) {
            $searchEscaped = $this->db->escape_like_str($search);
            $this->db->group_start();
            $this->db->like('devices.name', $searchEscaped);
            $this->db->or_like('devices.serial_number', $searchEscaped);
            $this->db->or_like('devices.location', $searchEscaped);
            $this->db->or_like('devices.description', $searchEscaped);
            $this->db->or_like('device_templates.name', $searchEscaped);
            // Use raw SQL for JSONB search to avoid syntax issues
            $this->db->or_where("devices.config::text ILIKE '%{$searchEscaped}%'");
            $this->db->group_end();
        }
        
        // Get total count first
        $totalRecords = $this->db->count_all_results('', false);
        
        // Reset query builder and build the main query
        $this->db->reset_query();
        $this->db->select('devices.*, device_templates.name as template_name');
        $this->db->from('devices');
        $this->db->join('device_templates', 'devices.device_template_id = device_templates.id', 'left');
        $this->db->where('devices.is_deleted', false);
        
        // Apply filters again
        if (!empty($status)) {
            if ($status === 'active') {
                $this->db->where('devices.is_active', true);
            } elseif ($status === 'inactive') {
                $this->db->where('devices.is_active', false);
            }
        }
        
        if (!empty($template)) {
            $this->db->where('device_templates.name', $template);
        }
        
        if (!empty($location)) {
            $this->db->where('devices.location', $location);
        }
        
        // Apply search again
        if (!empty($search)) {
            $searchEscaped = $this->db->escape_like_str($search);
            $this->db->group_start();
            $this->db->like('devices.name', $searchEscaped);
            $this->db->or_like('devices.serial_number', $searchEscaped);
            $this->db->or_like('devices.location', $searchEscaped);
            $this->db->or_like('devices.description', $searchEscaped);
            $this->db->or_like('device_templates.name', $searchEscaped);
            // Use raw SQL for JSONB search to avoid syntax issues
            $this->db->or_where("devices.config::text ILIKE '%{$searchEscaped}%'");
            $this->db->group_end();
        }
        
        // Apply pagination and ordering
        $this->db->limit($length, $offset);
        $this->db->order_by('devices.name', 'ASC');
        
        $query = $this->db->get();
        $data = $query->result();
        
        return [
            'data' => $data,
            'totalRecords' => $totalRecords,
            'filteredRecords' => $totalRecords
        ];
    }

    public function getDevicesCount($status = '', $template = '', $location = '', $search = '') {
        $this->db->select('COUNT(*) as total');
        $this->db->from('devices');
        $this->db->join('device_templates', 'devices.device_template_id = device_templates.id', 'left');
        $this->db->where('devices.is_deleted', false);
        
        // Apply filters
        if (!empty($status)) {
            if ($status === 'active') {
                $this->db->where('devices.is_active', true);
            } elseif ($status === 'inactive') {
                $this->db->where('devices.is_active', false);
            }
        }
        
        if (!empty($template)) {
            $this->db->where('device_templates.name', $template);
        }
        
        if (!empty($location)) {
            $this->db->where('devices.location', $location);
        }
        
        // Apply search
        if (!empty($search)) {
            $searchEscaped = $this->db->escape_like_str($search);
            $this->db->group_start();
            $this->db->like('devices.name', $searchEscaped);
            $this->db->or_like('devices.serial_number', $searchEscaped);
            $this->db->or_like('devices.location', $searchEscaped);
            $this->db->or_like('devices.description', $searchEscaped);
            $this->db->or_like('device_templates.name', $searchEscaped);
            // Use raw SQL for JSONB search to avoid syntax issues
            $this->db->or_where("devices.config::text ILIKE '%{$searchEscaped}%'");
            $this->db->group_end();
        }
        
        $query = $this->db->get();
        $result = $query->row();
        return $result ? $result->total : 0;
    }

    public function getDeviceTemplate($templateId) {
        return $this->db->where('id', $templateId)->get('device_templates')->row();
    }

    /**
     * Get unique locations for filter dropdown
     */
    public function getUniqueLocations()
    {
        $this->db->select('DISTINCT(location) as location');
        $this->db->from('devices');
        $this->db->where('is_deleted', false);
        $this->db->where('location IS NOT NULL');
        $this->db->where('location !=', '');
        $this->db->order_by('location', 'asc');
        $result = $this->db->get()->result();
        
        return array_map(function($item) {
            return $item->location;
        }, $result);
    }

    /**
     * Get unique templates for filter dropdown
     */
    public function getUniqueTemplates()
    {
        $this->db->select('DISTINCT(templates.name) as template_name');
        $this->db->from('devices');
        $this->db->join('device_templates as templates', 'templates.id = devices.device_template_id', 'left');
        $this->db->where('devices.is_deleted', false);
        $this->db->where('templates.name IS NOT NULL');
        $this->db->where('templates.name !=', '');
        $this->db->order_by('templates.name', 'asc');
        $result = $this->db->get()->result();
        
        return array_map(function($item) {
            return $item->template_name;
        }, $result);
    }

    /**
     * Get unique categories for filter dropdown
     */
    public function getUniqueCategories()
    {
        $this->db->select('DISTINCT(dc.name) as category_name');
        $this->db->from('devices');
        $this->db->join('device_categories dc', 'dc.id = devices.category_id', 'left');
        $this->db->where('devices.is_deleted', false);
        $this->db->where('dc.name IS NOT NULL');
        $this->db->where('dc.name !=', '');
        $this->db->order_by('dc.name', 'asc');
        $result = $this->db->get()->result();
        
        return array_map(function($item) {
            return $item->category_name;
        }, $result);
    }

    public function getAllWithTemplate()
    {
        $this->db->select('devices.*, templates.name as template_name, templates.device_information');
        $this->db->from('devices');
        $this->db->join('device_templates as templates', 'templates.id = devices.device_template_id', 'left');
        $this->db->where('devices.is_deleted', false);
        $this->db->order_by('devices.name', 'asc');
        return $this->db->get()->result();
    }

    public function get_all()
    {
       
            $this->db->select('id, name'); // ปรับให้ตรงกับชื่อ column จริงในตาราง device
            $this->db->order_by('name', 'asc');
            $this->db->where('is_deleted', false);
             return $this->db->get('devices')->result();
    }

    public function getById($id)
    {
        return $this->db->get_where($this->table, ['id' => $id])->row();
    }

    /**
     * Lightweight device list for metric builder (calc / snapshot source dropdowns).
     */
    public function getDevicesForMetricBuilder()
    {
        $this->db->select('devices.id, devices.name, devices.metrics, devices.config');
        $this->db->from('devices');
        $this->db->where('devices.is_deleted', false);
        $this->db->order_by('devices.name', 'asc');
        return $this->db->get()->result();
    }

    public function insert($data)
    {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function update($id, $data)
    {
        return $this->db->update($this->table, $data, ['id' => $id]);
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->update('devices', ['is_deleted' => true]);
    }


    public function getLatestMetrics($deviceId, $limit = 10)
    {
        $this->db->select('key, value, timestamp');
        $this->db->from('device_data');
        $this->db->where('device_id', $deviceId);
        $this->db->order_by('timestamp', 'desc');
        $this->db->limit($limit); // หรือเปลี่ยนตามที่ต้องการ
        return $this->db->get()->result();
    }



    // for lorawan application management
    public function get_local_application_by_id($id)
    {
        return $this->db->get_where('lorawan_applications', ['id' => $id])->row_array();
    }


    private function callChirpstackAPI($endpoint, $method = 'GET', $data = null, $queryParams = [])
    {
        $token = $this->config->item('chirpstack_api_key');
        $base_url = $this->config->item('chirpstack_base_url');

        $headers = [
            'Authorization: ' . $token,
            'Content-Type: application/json',
        ];

        $query = http_build_query($queryParams);
        $url = rtrim($base_url, '/') . '/' . ltrim($endpoint, '/');
        if (!empty($query)) {
            $url .= '?' . $query;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    public function registerDevice($comm, $info)
    {
        // ดึงข้อมูล local application ก่อน (จาก DB หรือ cache)
        $app = $this->get_local_application_by_id(intval($comm['application_server']));


        if (!$app || empty($app["application_id"])) {
            return ['success' => false, 'message' => 'Application server not found or invalid'];
        }

        $devEui = strtolower($comm['dev_eui']);

        $payload = [
                "applicationId"     => $app["application_id"],
                "description"       => $info['description'] ?? '',
                "devEui"            => $devEui,
                "deviceProfileId"   => $comm['device_profile_id'],////
                "isDisabled"        => false,
                "joinEui"           => $comm['app_eui'] ?? '',
                "name"              => $info['name'] ?? $devEui,
                "skipFcntCheck"     => true,
                "tags" => [
                    "sn"        => $info['serial_number'] ?? '',
                    "location"  => $info['location'] ?? '',
                    "position"  => $info['description'] ?? '',
                ]
        ];

        // เรียก ChirpStack API
        $response = $this->callChirpstackAPI("devices", 'POST', ['device' => $payload]);

        if (isset($response['id']) || empty($response)) { // บางทีไม่มี response body ถ้า success

            if(!empty($comm['appskey']) && !empty($comm['netskey'])) {
                $this->callChirpstackAPI("devices/{$devEui}/keys", 'POST', [
                    "deviceKeys" => [
                        "nwkKey"  => $comm['app_key'],
                        "appKey" => $comm['appskey'],
                        "genAppKey" => $comm['netskey']
                    ]
                ]);
            }else if (!empty($comm['app_key'])) {
                $this->callChirpstackAPI("devices/{$devEui}/keys", 'POST', [
                    "deviceKeys" => [
                        "nwkKey"  => $comm['app_key']
                    ]
                ]);
            }
            return ['success' => true];
        } else {
            return [
                'success' => false,
                'message' =>  $response
            ];
        }
    }



}
