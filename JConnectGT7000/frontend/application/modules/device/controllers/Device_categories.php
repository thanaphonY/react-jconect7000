<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Device_categories extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        isLogin();
        
        // Check if user is SuperAdmin
        $userDetails = $this->session->get_userdata()['user_details'][0] ?? null;
        if (!$userDetails || $userDetails->user_type !== 'admin') {
            redirect('dashboard');
        }
        
        $this->load->model('Device_categories_model');
        $this->load->library('form_validation');
        $this->load->helper('language');
        $this->lang->load('device_categories', $this->session->userdata('site_lang'));
    }

    public function index()
    {
        $data['categories'] = $this->Device_categories_model->getAll();
        $data['total_categories'] = count($data['categories']);
        $data['active_categories'] = count(array_filter($data['categories'], function($cat) {
            return $cat->is_active;
        }));
        
        $this->load->view('include/header');
        $this->load->view('device_categories/index', $data);
        $this->load->view('include/footer');
    }

    public function add()
    {
        if ($_POST) {
            $this->form_validation->set_rules('name', 'Category Name', 'required|is_unique[device_categories.name]');
            $this->form_validation->set_rules('description', 'Description', 'trim');
            $this->form_validation->set_rules('icon', 'Icon', 'trim');
            $this->form_validation->set_rules('color', 'Color', 'trim');

            if ($this->form_validation->run()) {
                $data = [
                    'name' => $this->input->post('name'),
                    'description' => $this->input->post('description'),
                    'icon' => $this->input->post('icon') ?: 'fa-server',
                    'color' => $this->input->post('color') ?: '#007bff',
                    'is_active' => $this->input->post('is_active') ? true : false
                ];

                $result = $this->Device_categories_model->insert($data);
                if ($result) {
                    $this->session->set_flashdata('success', 'Category added successfully');
                    redirect('device_categories');
                } else {
                    $this->session->set_flashdata('error', 'Failed to add category');
                }
            }
        }

        $this->load->view('include/header');
        $this->load->view('device_categories/form');
        $this->load->view('include/footer');
    }

    public function edit($id)
    {
        $category = $this->Device_categories_model->getById($id);
        if (!$category) {
            show_404();
        }

        if ($_POST) {
            $this->form_validation->set_rules('name', 'Category Name', 'required');
            $this->form_validation->set_rules('description', 'Description', 'trim');
            $this->form_validation->set_rules('icon', 'Icon', 'trim');
            $this->form_validation->set_rules('color', 'Color', 'trim');

            if ($this->form_validation->run()) {
                $data = [
                    'name' => $this->input->post('name'),
                    'description' => $this->input->post('description'),
                    'icon' => $this->input->post('icon') ?: 'fa-server',
                    'color' => $this->input->post('color') ?: '#007bff',
                    'is_active' => $this->input->post('is_active') ? true : false
                ];

                $result = $this->Device_categories_model->update($id, $data);
                if ($result) {
                    $this->session->set_flashdata('success', 'Category updated successfully');
                    redirect('device_categories');
                } else {
                    $this->session->set_flashdata('error', 'Failed to update category');
                }
            }
        }

        $data['category'] = $category;
        $this->load->view('include/header');
        $this->load->view('device_categories/form', $data);
        $this->load->view('include/footer');
    }

    public function delete($id)
    {
        // Check if category is being used by any devices or templates
        $usage = $this->Device_categories_model->checkUsage($id);
        if ($usage['devices_count'] > 0 || $usage['templates_count'] > 0) {
            $this->session->set_flashdata('error', 'Cannot delete category. It is being used by ' . 
                $usage['devices_count'] . ' devices and ' . $usage['templates_count'] . ' templates.');
            redirect('device_categories');
        }

        $result = $this->Device_categories_model->delete($id);
        if ($result) {
            $this->session->set_flashdata('success', 'Category deleted successfully');
        } else {
            $this->session->set_flashdata('error', 'Failed to delete category');
        }
        redirect('device_categories');
    }

    public function toggle_status($id)
    {
        $category = $this->Device_categories_model->getById($id);
        if (!$category) {
            show_404();
        }

        $new_status = !$category->is_active;
        $result = $this->Device_categories_model->update($id, ['is_active' => $new_status]);
        
        if ($result) {
            $status_text = $new_status ? 'activated' : 'deactivated';
            $this->session->set_flashdata('success', 'Category ' . $status_text . ' successfully');
        } else {
            $this->session->set_flashdata('error', 'Failed to update category status');
        }
        redirect('device_categories');
    }

    // AJAX endpoint สำหรับ get categories
    public function get_categories_ajax()
    {
        // Skip SuperAdmin check for AJAX endpoint
        try {
            $categories = $this->Device_categories_model->getAllActive();
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
} 