<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Api extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        // ไม่ต้องเรียก isLogin() เพื่อให้ API นี้ไม่ต้องการ authentication
        $this->load->model('Lorawan_model');
    }

    /**
     * Public API endpoint for getting device profiles (no authentication required)
     */
    public function get_device_profile($tenantId = null)
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');
        
        if ($tenantId == null) {
            echo json_encode([
                'status' => false,
                'message' => 'Tenant ID is required'
            ]);
            return;
        }
        
        try {
            // Use system curl directly to avoid PHP curl issues
            $token = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJjaGlycHN0YWNrIiwiaXNzIjoiY2hpcnBzdGFjayIsInN1YiI6ImZjZjI2NjNhLTNiNTItNDhiYi1hNDY0LWYwNWU5N2M5YTA2NSIsInR5cCI6ImtleSJ9.X9sKAgsbuIWXMmzJPEiUIXnWr_-6BnLsJDQWkYXZeyo';
            $url = "http://localhost:8090/api/device-profiles?tenantId={$tenantId}&limit=100";
            
            $curlCmd = "curl -s -H 'Authorization: {$token}' -H 'Content-Type: application/json' '{$url}'";
            
            $response = shell_exec($curlCmd);
            
            if ($response === null || $response === false) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Failed to fetch device profiles - system curl failed',
                    'tenantId' => $tenantId,
                    'debug' => 'Response: ' . $response
                ]);
                return;
            }
            
            $result = json_decode($response, true);
            
            if ($result === null) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Failed to parse JSON response',
                    'tenantId' => $tenantId,
                    'debug' => 'Raw response: ' . $response
                ]);
                return;
            }
            
            // ส่งคืนข้อมูลในรูปแบบที่ถูกต้อง
            echo json_encode([
                'status' => true,
                'result' => $result['result'] ?? [],
                'totalCount' => $result['totalCount'] ?? 0,
                'tenantId' => $tenantId
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'tenantId' => $tenantId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Public API endpoint for getting application servers (no authentication required)
     */
    public function get_app_servers()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');
        
        try {
            $servers = $this->Lorawan_model->get_all_local_applications();
            echo json_encode([
                'status' => true,
                'result' => $servers
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
} 