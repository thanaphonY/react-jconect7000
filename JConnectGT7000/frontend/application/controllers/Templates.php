<?php defined("BASEPATH") or exit("No direct script access allowed");

class Templates extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        isLogin();
        $this->load->model("Templates_model");
    }

    /**
     * This function is used get html of template page
     */
    public function index()
    {
        $data["view_data"] = $this->Templates_model->getTemplates();
        echo $this->load->view("templates/index", $data, 1);
        exit;
    }

    /**
     * This function is used to add and update templates
     */
    public function addEdit($id = '')
    {
        $data = $this->input->post();
        if ($id = $this->input->post('id')) {
            unset($data['submit']);
            unset($data['fileOld']);
            unset($data['save']);
            unset($data['id']);
            $this->Templates_model->updateRow('ia_email_templates', 'id', $id, $data);
            $art_msg['msg'] = lang('your_data_updated_successfully');
            $art_msg['type'] = 'success';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . 'setting#templates-setting');
        } else {
            unset($data['submit']);
            unset($data['fileOld']);
            unset($data['save']);
            $insert_id = $this->Templates_model->insertRow('ia_email_templates', $data);
            $art_msg['msg'] = lang('your_data_inserted_successfully');
            $art_msg['type'] = 'success';
            $this->session->set_userdata('alert_msg', $art_msg);
            redirect(base_url() . 'setting#templates-setting');
        }
    }

    /**
     * This function is used to Load add abd update view/html
     */
    public function modalForm()
    {
        if ($this->input->post('id')) {
            $data['data'] = $this->Templates_model->getSpecificTemplate($this->input->post('id'));
            echo $this->load->view('templates/add_update', $data, true);
        } else {
            echo $this->load->view('templates/add_update', '', true);
        }
        exit;
    }

    /**
     * This function is used to Load template datatable view and appear template list in table form
     */
public function ajaxData()
{
    $request = $this->input->get();

    $columns = [
        0 => 'template_name'
    ];

    $searchValue = $request['search']['value'] ?? '';
    $orderColumn = $columns[$request['order'][0]['column']] ?? 'template_name';
    $orderDir = $request['order'][0]['dir'] ?? 'asc';
    $start = intval($request['start'] ?? 0);
    $length = intval($request['length'] ?? 10);

    // Count total
    $totalCount = $this->db->count_all('ia_email_templates');

    // Filtering
    $this->db->from('ia_email_templates');
    if (!empty($searchValue)) {
        $this->db->group_start()
                 ->like('template_name', $searchValue)
                 ->group_end();
    }
    $filteredCount = $this->db->count_all_results('', false);

    // Order and Limit
    $this->db->order_by($orderColumn, $orderDir);
    $this->db->limit($length, $start);
    $query = $this->db->get();

    $data = [];
    foreach ($query->result() as $row) {
        $data[] = [
            htmlspecialchars($row->template_name),
            '<div class="text-nowrap">
                <a href="javascript:void(0);" class="btn btn-sm btn-outline-primary" onclick="editTemplate(' . $row->id . ')"><i class="bi bi-pencil"></i></a>
            </div>'
        ];
    }

    echo json_encode([
        "draw" => intval($request['draw']),
        "recordsTotal" => $totalCount,
        "recordsFiltered" => $filteredCount,
        "data" => $data
    ]);
}

    /**
     * This function is used to preview template
     */
    public function preview()
    {
        $template_row = $this->Templates_model->getSpecificTemplate($this->input->post('template_id'));
        echo $html = $template_row->html;
        exit;
    }

    /**
     * Template delete is disabled for now.
     */
    public function delete($id = '')
    {
        redirect(base_url() . 'setting#templates-setting');
    }
}
