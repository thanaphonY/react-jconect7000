<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Widgets extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Widgets_model');
        isLogin(); // ใช้ฟังก์ชัน auth ที่มีอยู่แล้ว
    }

    public function index()
    {
        $data['widgets'] = $this->Widgets_model->get_all();
        $this->load->view('include/header');
        $this->load->view('index', $data);
        $this->load->view('include/footer');
    }

    public function add()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $this->input->post('name'),
                'widget_type' => $this->input->post('widget_type'),
                'config' => json_encode($this->input->post('config') ?? []),
            ];
            $this->Widgets_model->insert($data);
            redirect('widgets');
        }
        $this->load->view('include/header');
        $this->load->view('form_addedit');
        $this->load->view('include/footer');
    }

    public function edit($id)
    {
        $widget = $this->Widgets_model->get($id);
        if (!$widget) show_404();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $this->input->post('name'),
                'widget_type' => $this->input->post('widget_type'),
                'config' => json_encode($this->input->post('config') ?? []),
            ];
            $this->Widgets_model->update($id, $data);
            redirect('widgets');
        }

        $data['widget'] = $widget;
        $this->load->view('include/header');
        $this->load->view('form_addedit', $data);
        $this->load->view('include/footer');
    }

    public function delete($id)
    {
        $this->Widgets_model->delete($id);
        redirect('widgets');
    }


    public function get_widget($id)
{
    $this->load->model('widgets/Widgets_model');
    $widget = $this->Widgets_model->get($id);

    if (!$widget) {
        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'error', 'message' => 'Widget not found']));
    }


    if (is_string($widget->config)) {
        $widget->config = json_decode($widget->config, true); // หรือ false ถ้าอยากได้ stdClass
    }

    return $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode(['status' => 'success', 'widget' => $widget]));
}
}
