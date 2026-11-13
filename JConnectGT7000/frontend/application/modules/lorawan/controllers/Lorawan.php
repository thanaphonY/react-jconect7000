<?php
// This file is part of the LoRaWAN module for a web application.
// application/modules/lorawan/controllers/Lorawan.php


defined('BASEPATH') or exit('No direct script access allowed');

class Lorawan extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        isLogin();
        $userDetails = $this->session->get_userdata()['user_details'][0] ?? null;
        $username = $userDetails ? $userDetails->name : 'unknown';
        $this->user_id = $userDetails ? $userDetails->ia_users_id : 0;

        $this->load->model('Lorawan_model');
    }

    public function index()
    {
        $data['applications'] = $this->Lorawan_model->get_all_local_applications();

        $this->load->view('include/header');
        $this->load->view('lorawan/index', $data);
        $this->load->view('include/footer');
    }

    public function ajax_lorawan_app_server()
    {
        $servers = $this->Lorawan_model->get_all_local_applications();
        header('Content-Type: application/json');
        echo json_encode($servers);
    }

    /**
     * Add a new application
     * This method handles the form submission for creating a new LoRaWAN application.
     * It validates the input, calls the model to create the application in ChirpStack,
     * and saves the application details to the local database.
     * If successful, it redirects to the main LoRaWAN page with a success message.
     */
    public function add_application()
    {
        if ($this->input->post()) {
            $payload = [
                'name' => $this->input->post('name'),
                'description' => $this->input->post('description'),
                'tenantId' => $this->input->post('tenant_id')
            ];

            $res = $this->Lorawan_model->create_application($payload);

            if (isset($res['id'])) {
                // Save to local DB
                $this->Lorawan_model->save_application_to_db([
                    'application_id' => $res['id'],
                    'name' => $payload['name'],
                    'description' => $payload['description'],
                    'tenant_id' => $payload['tenantId']
                ]);

                $this->session->set_flashdata('success', 'Application created: ' . $res['id']);
                redirect('lorawan');
            } else {
                $data['error'] = 'Failed to create application';
            }
        }

        $this->load->view('lorawan/add_application');
    }


    /**
     * Edit an existing application
     * This method handles the form submission for editing an existing LoRaWAN application.
     * It retrieves the application details from the local database, updates the application
     * in ChirpStack, and updates the local database with the new details.
     */
    public function edit_application($id)
    {
        $payload = [
            'name' => $this->input->post('name'),
            'description' => $this->input->post('description')
        ];

        // ดึง tenant_id เดิมจาก DB
        $app = $this->Lorawan_model->get_local_application_by_id($id);
        if (!$app) {
            show_error('Application not found');
        }

        $res = $this->Lorawan_model->update_application($id, [
            'name' => $payload['name'],
            'description' => $payload['description'],
            'tenantId' => $app['tenant_id']
        ]);

        if ($res) {
            $this->Lorawan_model->update_local_application($id, $payload);
            $this->session->set_flashdata('success', 'Application updated');
        }

        redirect('lorawan');
    }

    /**
     * Delete an existing application
     * This method handles the deletion of an existing LoRaWAN application.
     * It removes the application from ChirpStack and deletes the local database entry.
     */
    public function delete_application($id)
    {
        $this->Lorawan_model->delete_application($id);
        $this->Lorawan_model->delete_local_application($id);
        $this->session->set_flashdata('success', 'Application deleted');
        redirect('lorawan');
    }

    public function edit_application_ajax($id)
    {
        $name = $this->input->post('name');
        $description = $this->input->post('description');

        $app = $this->Lorawan_model->get_local_application_by_id($id);
        if (!$app) {
            echo json_encode(['success' => false]);
            return;
        }

        $res = $this->Lorawan_model->update_application($id, [
            'name' => $name,
            'description' => $description,
            'tenantId' => $app['tenant_id']
        ]);

        if ($res == []) {
            $this->Lorawan_model->update_local_application($id, [
                'name' => $name,
                'description' => $description
            ]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }


    public function add_gateway()
    {
        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $payload = [
                'gateway' => [
                    'id' => $this->input->post('gateway_id'),
                    'name' => $this->input->post('name'),
                    'description' => $this->input->post('description'),
                    'location' => [
                        'latitude' => (float)$this->input->post('latitude'),
                        'longitude' => (float)$this->input->post('longitude'),
                        'altitude' => (float)$this->input->post('altitude'),
                    ],
                    'network_server_id' => $this->input->post('network_server_id'),
                    'organization_id' => $this->input->post('organization_id'),
                ]
            ];

            $res = $this->Lorawan_model->add_gateway($payload);

            if (isset($res['id'])) {
                $this->session->set_flashdata('success', 'Gateway created successfully');
                redirect('lorawan');
            } else {
                $data['error'] = 'Failed to create gateway';
            }
        }

        $this->load->view('lorawan/add_gateway');
    }

    public function get_device_profile($tenantId = null)
    {
        header('Content-Type: application/json');
        
        if ($tenantId == null) {
            echo json_encode([
                'status' => false,
                'message' => 'Tenant ID is required'
            ]);
            return;
        }
        
        try {
            $result = $this->Lorawan_model->get_device_profiles($tenantId);
            
            if ($result === null || $result === false) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Failed to fetch device profiles',
                    'tenantId' => $tenantId
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
                'tenantId' => $tenantId
            ]);
        }
    }

    /**
     * Public API endpoint for getting device profiles (no authentication required)
     */
    public function api_get_device_profile($tenantId = null)
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
            $result = $this->Lorawan_model->get_device_profiles($tenantId);
            
            if ($result === null || $result === false) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Failed to fetch device profiles',
                    'tenantId' => $tenantId
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
                'tenantId' => $tenantId
            ]);
        }
    }

    public function global_adddevice()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['comm_config']) || !isset($data['info'])) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Missing required data'
                ]));
        }

        $comm = $data['comm_config'];
        $info = $data['info'];

        // ตรวจสอบค่าที่จำเป็น
        $required = ['application_server', 'device_profile_id', 'dev_eui'];
        foreach ($required as $key) {
            if (empty($comm[$key])) {
                return $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'message' => "Missing required field: {$key}"
                    ]));
            }
        }

        // เรียกไปยัง Model เพื่อสร้าง Device บน ChirpStack
        $result = $this->Lorawan_model->registerDevice($comm, $info);

        if ($result && $result['success']) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => true]));
        } else {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to register device'
                ]));
        }
    }
}
