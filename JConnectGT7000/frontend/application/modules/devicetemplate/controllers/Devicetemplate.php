<?php defined("BASEPATH") or exit("No direct script access allowed");
// /application/modules/devicetemplate/controllers/Devicetemplate.php
class Devicetemplate extends CI_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->load->model("Devicetemplate_model");
        $this->load->model("device/Device_categories_model", "Device_categories_model");
        $this->load->model("device/Device_model", "Device_model");
        $this->load->model("Notification_manager/Notification_channel_model", "Notification_channel_model");
        $this->load->helper('url');
        $this->load->library('form_validation');

        isLogin();
        site_require_global();
        $this->user_id = isset($this->session->get_userdata()['user_details'][0]->ia_users_id) ? $this->session->get_userdata()['user_details'][0]->ia_users_id : 1;
        $this->userName = isset($this->session->get_userdata()['user_details'][0]->name) ? $this->session->get_userdata()['user_details'][0]->name : '';

        $this->load->helper('language');
        $this->lang->load('Devicetemplate', $this->session->userdata('site_lang'));
    }

    //load view
    public function index()
    {
        isLogin();
        $data['templates'] = $this->Devicetemplate_model->getAll();
        $this->load->view("include/header");
        $this->load->view("index", $data);
        $this->load->view("include/footer");
    }

    public function add()
    {
        if ($_POST) {
            $this->form_validation->set_rules('name', 'Template Name', 'required');
            $this->form_validation->set_rules('protocol', 'Protocol', 'required');
            $this->form_validation->set_rules('dcim_category', 'Category', 'required');
            $this->form_validation->set_rules('dcim_sub_category', 'Sub-Category', 'required');

            if ($this->form_validation->run()) {
                $taxonomy = $this->resolve_dcim_selection();
                if (!$taxonomy) {
                    set_alert_message('Please select a valid Device Category and Sub-Category.', 'danger');
                    redirect('devicetemplate/add');
                    return;
                }
                $categoryId = $this->resolve_category_id($taxonomy['label']);
                if (!$categoryId) {
                    set_alert_message('Unable to create or load the selected Device Category.', 'danger');
                    redirect('devicetemplate/add');
                    return;
                }
                $insertData = [
                    'name' => $this->input->post('name'),
                    'description' => $this->input->post('description'),
                    'protocol' => $this->input->post('protocol'),
                    'category_id' => $categoryId,
                    'communication_config' => $this->normalize_json_input($this->input->post('communication_config')),
                    'device_information' => $this->normalize_device_information(
                        $this->input->post('device_information'),
                        $taxonomy
                    ),
                    'metrics' => $this->normalize_json_input($this->input->post('metrics')),
                    'default_dashboard_layout' => $this->normalize_json_input($this->input->post('default_dashboard_layout')),
                    'is_active' => $this->input->post('is_active') ? true : false
                ];
                $image = $this->uploadImage();
                if ($image) {
                    $insertData['image'] = $image;
                }

                $this->Devicetemplate_model->insert($insertData);
                redirect('devicetemplate');
            }
        }

        $data['categories'] = $this->Device_categories_model->getAllActive();
        $data['dcim_taxonomy'] = $this->dcim_taxonomy();
        $data['calc_devices'] = $this->metric_source_devices();
        $data['channels'] = $this->Notification_channel_model->getAll();
        $this->load->view('include/header');
        $this->load->view('device_template/form', $data);
        $this->load->view('include/footer');
    }

    public function edit($id)
    {
        $template = $this->Devicetemplate_model->getById($id);
        if (!$template) {
            show_404();
        }

        if ($_POST) {
            $this->form_validation->set_rules('name', 'Template Name', 'required');
            $this->form_validation->set_rules('protocol', 'Protocol', 'required');
            $this->form_validation->set_rules('dcim_category', 'Category', 'required');
            $this->form_validation->set_rules('dcim_sub_category', 'Sub-Category', 'required');

            if ($this->form_validation->run()) {
                $taxonomy = $this->resolve_dcim_selection();
                if (!$taxonomy) {
                    set_alert_message('Please select a valid Device Category and Sub-Category.', 'danger');
                    redirect('devicetemplate/edit/' . $id);
                    return;
                }
                $categoryId = $this->resolve_category_id($taxonomy['label']);
                if (!$categoryId) {
                    set_alert_message('Unable to create or load the selected Device Category.', 'danger');
                    redirect('devicetemplate/edit/' . $id);
                    return;
                }
                $updateData = [
                    'name' => $this->input->post('name'),
                    'description' => $this->input->post('description'),
                    'protocol' => $this->input->post('protocol'),
                    'category_id' => $categoryId,
                    'communication_config' => $this->normalize_json_input($this->input->post('communication_config')),
                    'device_information' => $this->normalize_device_information(
                        $this->input->post('device_information'),
                        $taxonomy
                    ),
                    'metrics' => $this->normalize_json_input($this->input->post('metrics')),
                    'default_dashboard_layout' => $this->normalize_json_input($this->input->post('default_dashboard_layout')),
                    'is_active' => $this->input->post('is_active') ? true : false
                ];
                $image = $this->uploadImage();
                if ($image) {
                    $updateData['image'] = $image;
                }

                $this->Devicetemplate_model->update($id, $updateData);
                redirect('devicetemplate');
            }
        }

        $data['template'] = $template;
        $data['categories'] = $this->Device_categories_model->getAllActive();
        $data['dcim_taxonomy'] = $this->dcim_taxonomy();
        $selectedTaxonomy = $this->infer_dcim_selection($template);
        $data['selected_dcim_category'] = $selectedTaxonomy['category'];
        $data['selected_dcim_sub_category'] = $selectedTaxonomy['sub_category'];
        $data['calc_devices'] = $this->metric_source_devices();
        $data['channels'] = $this->Notification_channel_model->getAll();
        $this->load->view('include/header');
        $this->load->view('device_template/form', $data);
        $this->load->view('include/footer');
    }

    /**
     * Normalize JSON input from textarea or JS
     */
    private function normalize_json_input($input)
    {
        $decoded = json_decode($input, true);
        return $decoded !== null ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : json_encode([]);
    }

    private function dcim_taxonomy()
    {
        return include APPPATH . 'config/dcim_taxonomy.php';
    }

    private function resolve_dcim_selection()
    {
        $categoryName = trim((string) $this->input->post('dcim_category'));
        $subCategoryName = trim((string) $this->input->post('dcim_sub_category'));

        foreach ($this->dcim_taxonomy() as $entry) {
            if ($entry['category'] !== $categoryName || !in_array($subCategoryName, $entry['subs'], true)) {
                continue;
            }
            return [
                'category' => $categoryName,
                'sub_category' => $subCategoryName,
                'domain' => $entry['domain'],
                'pue_role' => $entry['pue_role'],
                'label' => $categoryName . ' / ' . $subCategoryName
            ];
        }
        return null;
    }

    private function resolve_category_id($label)
    {
        $category = $this->Device_categories_model->getByName($label);
        if ($category) {
            if (!$category->is_active) {
                $this->Device_categories_model->update($category->id, ['is_active' => true]);
            }
            return (int) $category->id;
        }

        return $this->Device_categories_model->insertGetId([
            'name' => $label,
            'description' => 'DCIM device category',
            'is_active' => true
        ]);
    }

    private function normalize_device_information($input, array $taxonomy)
    {
        $info = json_decode((string) $input, true);
        if (!is_array($info)) {
            $info = [];
        }
        $info['domain'] = $taxonomy['domain'];
        $info['pue_role'] = $taxonomy['pue_role'];
        $info['dcim_category'] = $taxonomy['category'];
        $info['dcim_sub_category'] = $taxonomy['sub_category'];
        return json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function infer_dcim_selection($template)
    {
        $taxonomy = $this->dcim_taxonomy();
        $info = json_decode((string) ($template->device_information ?? ''), true);
        $info = is_array($info) ? $info : [];

        $candidates = [];
        if (!empty($info['dcim_category']) && !empty($info['dcim_sub_category'])) {
            $candidates[] = [$info['dcim_category'], $info['dcim_sub_category']];
        }

        $categoryName = trim((string) ($template->category_name ?? ''));
        foreach ($taxonomy as $entry) {
            foreach ($entry['subs'] as $subCategory) {
                if ($categoryName === $entry['category'] . ' / ' . $subCategory) {
                    $candidates[] = [$entry['category'], $subCategory];
                }
            }
        }

        $legacyMap = [
            'UPS' => ['Power', 'UPS System'],
            'Power Meter' => ['Power', 'Power Meter / Energy Meter'],
            'Temperature/Humidity' => ['Environment', 'Temp/Humidity Combo Sensor'],
            'PDU' => ['Power', 'PDU / iPDU / RPP'],
            'Generator' => ['Power', 'Generator'],
            'CRAC' => ['Cooling', 'CRAC / CRAH'],
            'Fire Suppression' => ['Fire & Safety', 'Gas Suppression'],
            'Access Control' => ['Physical Security', 'Access Control Panel'],
            'CCTV' => ['Physical Security', 'CCTV Camera'],
            'Network' => ['IT Network', 'Core Switch'],
            'Other' => ['Building / BMS / OT Integration', 'Edge Gateway']
        ];
        if (isset($legacyMap[$categoryName])) {
            $candidates[] = $legacyMap[$categoryName];
        }

        foreach ($candidates as $candidate) {
            foreach ($taxonomy as $entry) {
                if ($entry['category'] === $candidate[0] && in_array($candidate[1], $entry['subs'], true)) {
                    return ['category' => $candidate[0], 'sub_category' => $candidate[1]];
                }
            }
        }

        return [
            'category' => $taxonomy[0]['category'] ?? '',
            'sub_category' => $taxonomy[0]['subs'][0] ?? ''
        ];
    }

    /**
     * Lightweight device/metric list for calculated metric source pickers.
     */
    private function metric_source_devices()
    {
        $result = [];
        foreach ($this->Device_model->getAll() as $device) {
            $metrics = json_decode($device->metrics ?: '[]', true);
            $result[] = [
                'id' => (int) $device->id,
                'name' => $device->name,
                'metrics' => is_array($metrics) ? $metrics : []
            ];
        }
        return $result;
    }

    public function delete($id)
    {
        $this->Devicetemplate_model->delete($id);
        redirect('devicetemplate');
    }

    private function uploadImage($field = 'image')
    {
        $config['upload_path'] = './uploads/device_templates/';
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

    /**
     * AJAX endpoint สำหรับ template preview
     */
    public function ajax_template_preview($id)
    {
        try {
            $template = $this->Devicetemplate_model->getById($id);
            if (!$template) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Template not found'
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => $template
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
