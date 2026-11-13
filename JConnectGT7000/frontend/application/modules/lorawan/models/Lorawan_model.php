<?php
// This file is part of the LoRaWAN module for a web application.
// application/modules/lorawan/models/Lorawan_model.php
class Lorawan_model extends CI_Model
{
    private $token;
    private $base_url;

    public function __construct()
    {
        parent::__construct();
        $this->token = $this->config->item('chirpstack_api_key');
        $this->base_url = $this->config->item('chirpstack_base_url');
    }


    private function callChirpstackAPI($endpoint, $method = 'GET', $data = null, $queryParams = [])
    {
        $headers = [
            'Authorization: ' . $this->token,
            'Content-Type: application/json',
        ];

        $query = http_build_query($queryParams);
        $url = rtrim($this->base_url, '/') . '/' . ltrim($endpoint, '/');
        if (!empty($query)) {
            $url .= '?' . $query;
        }

        // Debug: Log the request
        error_log('ChirpStack API Request: ' . $method . ' ' . $url);
        error_log('ChirpStack API Headers: ' . json_encode($headers));

        // Try PHP curl first
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Debug: Log the response
        error_log('ChirpStack API Response Code: ' . $httpCode);
        error_log('ChirpStack API Response: ' . $response);
        if ($error) {
            error_log('ChirpStack API Error: ' . $error);
        }
        
        // If PHP curl fails, try system curl as fallback
        if ($error || $httpCode !== 200) {
            error_log('PHP curl failed, trying system curl as fallback');
            
            // Build curl command
            $curlCmd = 'curl -s -H "Authorization: ' . $this->token . '" -H "Content-Type: application/json"';
            if ($method !== 'GET') {
                $curlCmd .= ' -X ' . $method;
            }
            if ($data) {
                $curlCmd .= ' -d \'' . json_encode($data) . '\'';
            }
            $curlCmd .= ' "' . $url . '"';
            
            error_log('System curl command: ' . $curlCmd);
            
            $response = shell_exec($curlCmd);
            $httpCode = 200; // Assume success for system curl
            $error = null;
            
            error_log('System curl response: ' . $response);
        }
        
        if ($error) {
            return ['error' => $error];
        }
        
        if ($httpCode !== 200) {
            return ['error' => 'HTTP ' . $httpCode, 'response' => $response];
        }
        
        return json_decode($response, true);
    }




    public function add_gateway($data)
    {
        return $this->callChirpstackAPI('gateways', 'POST', $data);
    }

    public function get_gateways()
    {
        $res = $this->callChirpstackAPI('gateways');
        return $res['result'] ?? [];
    }


    public function get_applications($tenantId)
    {
        $endpoint = 'applications?tenantId=' . urlencode($tenantId);
        return $this->callChirpstackAPI($endpoint, 'GET');
    }

    public function get_gateway($gatewayId)
    {
        $endpoint = 'gateways/' . urlencode($gatewayId);
        return $this->callChirpstackAPI($endpoint, 'GET');
    }

    public function update_gateway($gatewayId, $data)
    {
        $endpoint = 'gateways/' . urlencode($gatewayId);
        return $this->callChirpstackAPI($endpoint, 'PUT', $data);
    }

    public function delete_gateway($gatewayId)
    {
        $endpoint = 'gateways/' . urlencode($gatewayId);
        return $this->callChirpstackAPI($endpoint, 'DELETE');
    }


    public function create_application($data)
    {
        return $this->callChirpstackAPI('applications', 'POST', ['application' => $data]);
    }

    public function update_application($id, $data)
    {
        return $this->callChirpstackAPI("applications/{$id}", 'PUT', ['application' => $data]);
    }

    public function delete_application($id)
    {
        return $this->callChirpstackAPI("applications/{$id}", 'DELETE');
    }

    public function update_local_application($id, $data)
    {
        $this->db->where('application_id', $id);
        return $this->db->update('lorawan_applications', $data);
    }

    public function delete_local_application($id)
    {
        return $this->db->delete('lorawan_applications', ['application_id' => $id]);
    }

    public function get_local_application_by_id($id)
    {
        return $this->db->get_where('lorawan_applications', ['id' => $id])->row_array();
    }

    public function save_application_to_db($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return $this->db->insert('lorawan_applications', $data);
    }

    public function get_all_local_applications()
    {
        return $this->db->get('lorawan_applications')->result_array();
    }


    public function get_device_profiles($tenantId, $limit = 100, $offset = 0)
    {
        $result = $this->callChirpstackAPI('device-profiles', 'GET', null, [
            'tenantId' => $tenantId,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        // Debug: Log the response
        error_log('ChirpStack API Response: ' . json_encode($result));
        
        return $result;
    }


    public function add_device($deviceData)
    {
        // $deviceData should be an associative array matching the "device" structure in the curl example
        // Example: [
        //   "applicationId" => "...",
        //   "description" => "...",
        //   "devEui" => "...",
        //   ...
        // ]
        return $this->callChirpstackAPI('devices', 'POST', ['device' => $deviceData]);
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
                "deviceProfileId"   => $comm['device_profile_id'],
                "isDisabled"        => false,
                "joinEui"           => $comm['app_eui'] ?? '',
                "name"              => $info['name'] ?? $devEui,
                "skipFcntCheck"     => true,
                "tags" => [
                    "sn"        => $info['serial_number'] ?? '',
                    "location"  => $info['location'] ?? '',
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
//'message' => $response['message'] ?? 'Failed to register device'