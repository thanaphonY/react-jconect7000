<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Static_api extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Static API endpoint for getting device profiles (no network call)
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
        
        // Static device profiles data (from our previous successful curl test)
        $staticData = [
            'status' => true,
            'result' => [
                [
                    'id' => '82b307a1-3ffd-4840-a0af-6e4587d43e9b',
                    'createdAt' => '2025-07-14T07:34:06.764876Z',
                    'updatedAt' => '2025-07-16T11:13:50.810773Z',
                    'name' => 'LR-S-HT',
                    'region' => 'AS923',
                    'macVersion' => 'LORAWAN_1_0_3',
                    'regParamsRevision' => 'A',
                    'supportsOtaa' => true,
                    'supportsClassB' => false,
                    'supportsClassC' => false
                ],
                [
                    'id' => 'e4c58772-b1c3-4ade-8eb7-ba1dce05acf8',
                    'createdAt' => '2025-07-16T05:05:15.274754Z',
                    'updatedAt' => '2025-07-16T11:14:19.333726Z',
                    'name' => 'LR-S-HT-EX',
                    'region' => 'AS923',
                    'macVersion' => 'LORAWAN_1_0_3',
                    'regParamsRevision' => 'A',
                    'supportsOtaa' => true,
                    'supportsClassB' => false,
                    'supportsClassC' => false
                ]
            ],
            'totalCount' => 2,
            'tenantId' => $tenantId
        ];
        
        echo json_encode($staticData);
    }

    /**
     * Test endpoint
     */
    public function test()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => true,
            'message' => 'Static API is working',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
} 