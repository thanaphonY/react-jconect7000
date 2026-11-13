<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Site extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if ($this->input->is_cli_request()) {
            return;
        }
        isLogin();
        $this->load->model('Site_model');
        if (!$this->Site_model->ready()) {
            show_error('Site tables are not installed. Apply Database/migrations/004_site_management.sql.', 500);
        }
    }

    public function index()
    {
        $data['tree'] = $this->Site_model->tree();
        $data['alarms'] = $this->Site_model->alarmByNode();
        $data['devices'] = $this->Site_model->deviceCountByNode();
        $data['regional'] = site_is_regional();
        $this->load->view('include/header');
        $this->load->view('site/index', $data);
        $this->load->view('include/footer');
    }

    public function form($id = 0)
    {
        $id = (int) $id;
        $node = null;
        if ($id) {
            $node = $this->requireNode($id);
        }
        $parentId = $node ? (int) $node->parent_id : (int) $this->input->get('parent');
        $data['node'] = $node;
        $data['parent_id'] = $parentId;
        $data['parents'] = $this->parentOptions($id);
        $data['layouts'] = $this->Site_model->layouts();
        $data['channels'] = site_is_regional() ? array() : array_values(array_filter(
            $this->Site_model->channels(),
            'site_channel_is_active'
        ));
        $data['channel_ids'] = $node && !site_is_regional() ? $this->Site_model->channelIds($id) : array();
        $data['devices'] = $this->Site_model->bindableDevices($id);
        $data['device_ids'] = array();
        if ($node) {
            foreach ($this->Site_model->devicesOn($id) as $device) {
                $data['device_ids'][] = (int) $device->id;
            }
        }
        $data['regional'] = site_is_regional();
        $data['tree'] = $this->Site_model->tree();
        $data['parent_options'] = site_form_parent_options($data['tree'], $data['parents']);
        $data['map_sites'] = site_map_reference_sites($data['tree'], $id);
        $editLat = $node && $node->latitude !== null ? $node->latitude : null;
        $editLng = $node && $node->longitude !== null ? $node->longitude : null;
        $mapWidget = $this->Site_model->homeMapWidget();
        $mapConfig = $mapWidget ? json_decode($mapWidget->config ?? '{}') : null;
        $data['map_bounds'] = site_map_form_bounds($data['map_sites'], $editLat, $editLng, $mapConfig);
        $data['map_bg_url'] = site_map_form_background_url($mapConfig);
        $data['map_tiles_url'] = site_map_form_local_tiles_url();
        $this->load->view('include/header');
        $this->load->view('site/form', $data);
        $this->load->view('include/footer');
    }

    public function save()
    {
        try {
            $id = (int) $this->input->post('id');
            $saved = $this->Site_model->saveNode($this->input->post(), $id ?: null);
            $this->flash('success', 'Site node saved.');
            redirect(base_url() . 'site/view/' . $saved);
        } catch (Throwable $e) {
            $this->flash('danger', $e->getMessage());
            $id = (int) $this->input->post('id');
            redirect($id ? base_url() . 'site/form/' . $id : base_url() . 'site/form');
        }
    }

    public function delete($id = 0)
    {
        try {
            $this->Site_model->deleteNode((int) $id);
            $this->flash('success', 'Site node deleted.');
        } catch (Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        redirect(base_url() . 'site');
    }

    public function view($id = 0)
    {
        $node = $this->requireNode((int) $id);
        $nodeId = (int) $node->id;
        $data['node'] = $node;
        $data['parent'] = $node->parent_id ? $this->Site_model->node((int) $node->parent_id) : null;

        $data['devices'] = $this->Site_model->devicesUnder($nodeId);
        $deviceIds = array();
        foreach ($data['devices'] as $device) {
            $deviceIds[] = (int) $device->id;
        }
        $data['device_alarms'] = $this->Site_model->activeAlarmCountByDevice($deviceIds);
        $alarmSummary = $this->Site_model->alarmByNode();
        $data['alarm_count'] = isset($alarmSummary[$nodeId]) ? (int) $alarmSummary[$nodeId]['count'] : 0;
        $data['alarm_severity'] = isset($alarmSummary[$nodeId]) ? (string) $alarmSummary[$nodeId]['severity'] : '';

        $nodeNames = array();
        foreach ($this->Site_model->allRows() as $row) {
            $nodeNames[(int) $row->id] = $row->name;
        }
        $data['node_names'] = $nodeNames;

        $data['channels'] = array();
        $selected = array_flip($this->Site_model->channelIds($nodeId));
        foreach ($this->Site_model->channels() as $channel) {
            if (isset($selected[(int) $channel->id])) {
                $data['channels'][] = $channel;
            }
        }

        $data['layout'] = null;
        $data['layout_items'] = array();
        if (!empty($node->dashboard_layout_id) && site_layout_allowed($node->dashboard_layout_id)) {
            $this->load->model('dashboard/Dashboard_layout_model');
            $layout = $this->Dashboard_layout_model->get_by_id((int) $node->dashboard_layout_id);
            if ($layout) {
                $data['layout'] = $layout;
                $data['layout_items'] = $this->Dashboard_layout_model->get_items((int) $layout->id);
                $data['user_id'] = site_current_user_id();
                $data['created_by'] = $layout->created_by ?? 0;
                $data['all_dashboard_layouts'] = $this->Dashboard_layout_model->get_all_names();
                if (site_is_regional()) {
                    $allowed = array_flip(site_allowed_layout_ids());
                    $data['all_dashboard_layouts'] = array_values(array_filter($data['all_dashboard_layouts'], function ($row) use ($allowed) {
                        return isset($allowed[(int) $row->id]);
                    }));
                }
            }
        }
        $this->load->view('include/header');
        $this->load->view('site/view', $data);
        $this->load->view('include/footer');
    }

    public function access()
    {
        site_require_global();
        $data['users'] = $this->Site_model->usersForScope();
        $data['nodes'] = $this->Site_model->allRows();
        $names = array();
        foreach ($data['nodes'] as $node) {
            $names[(int) $node->id] = $node->name;
        }
        $scopes = array();
        if ($this->db->table_exists('user_site_scopes')) {
            foreach ($this->db->get('user_site_scopes')->result() as $row) {
                $scopes[(int) $row->ia_users_id][] = $names[(int) $row->site_node_id] ?? ('#' . $row->site_node_id);
            }
        }
        $data['scopes'] = $scopes;
        $this->load->view('include/header');
        $this->load->view('site/access', $data);
        $this->load->view('include/footer');
    }

    public function access_edit($id = 0)
    {
        site_require_global();
        $user = $this->db->get_where('ia_users', array('ia_users_id' => (int) $id, 'is_deleted' => '0'))->row();
        if (!$user) {
            show_404();
        }
        $data['user'] = $user;
        $data['nodes'] = $this->Site_model->allRows();
        $data['selected'] = array_flip($this->Site_model->assignedIds((int) $id));
        $this->load->view('include/header');
        $this->load->view('site/access_edit', $data);
        $this->load->view('include/footer');
    }

    public function access_save()
    {
        site_require_global();
        try {
            $this->Site_model->saveUserScope(
                (int) $this->input->post('ia_users_id'),
                (string) $this->input->post('access_level'),
                $this->input->post('node_ids')
            );
            $this->flash('success', 'User scope saved.');
        } catch (Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        redirect(base_url() . 'site/access');
    }

    public function api()
    {
        site_require_global();
        $this->load->model('Api_client_model');
        $data['ready'] = $this->Api_client_model->ready();
        $data['clients'] = $data['ready'] ? $this->Api_client_model->listClients() : array();
        $data['nodes'] = $data['ready'] ? $this->Api_client_model->nodeOptions() : array();
        $data['new_token'] = $this->session->flashdata('api_token_once');
        $this->load->view('include/header');
        $this->load->view('site/api', $data);
        $this->load->view('include/footer');
    }

    public function api_create()
    {
        site_require_global();
        $this->load->model('Api_client_model');
        $name = trim((string) $this->input->post('name'));
        $rate = (int) $this->input->post('rate_limit_per_minute');
        if ($name === '' || $rate < 1 || !$this->Api_client_model->ready()) {
            $this->flash('danger', 'Enter a name and a request limit.');
            redirect(base_url('site/api'));
        }
        $token = $this->Api_client_model->create(
            $name,
            (int) $this->input->post('site_node_id'),
            $this->bangkokTimestamp($this->input->post('expires_at')),
            $rate,
            site_current_user_id()
        );
        $this->session->set_flashdata('api_token_once', $token);
        $this->flash('success', 'API key created.');
        redirect(base_url('site/api'));
    }

    public function api_update()
    {
        site_require_global();
        $this->load->model('Api_client_model');
        $rate = (int) $this->input->post('rate_limit_per_minute');
        if ($rate < 1 || !$this->Api_client_model->ready()) {
            $this->flash('danger', 'Enter a request limit.');
            redirect(base_url('site/api'));
        }
        $this->Api_client_model->updateLimits(
            (int) $this->input->post('id'),
            $this->bangkokTimestamp($this->input->post('expires_at')),
            $rate
        );
        $this->flash('success', 'API key updated.');
        redirect(base_url('site/api'));
    }

    public function api_revoke()
    {
        site_require_global();
        $this->load->model('Api_client_model');
        if ($this->Api_client_model->ready()) {
            $this->Api_client_model->revoke((int) $this->input->post('id'));
            $this->flash('success', 'API key revoked.');
        }
        redirect(base_url('site/api'));
    }

    private function bangkokTimestamp($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $dt = new DateTime($value, new DateTimeZone('Asia/Bangkok'));
        return $dt->format('Y-m-d H:i:sP');
    }

    private function requireNode($id)
    {
        $node = $this->Site_model->node((int) $id);
        if (!$node || !$this->Site_model->canSee((int) $id)) {
            show_404();
        }
        return $node;
    }

    private function parentOptions($selfId)
    {
        $options = array();
        foreach ($this->Site_model->visibleRows() as $row) {
            if ((int) $row->id === (int) $selfId || $row->node_type === 'Room') {
                continue;
            }
            if ($selfId && in_array((int) $row->id, $this->Site_model->descendantIds((int) $selfId), true)) {
                continue;
            }
            $options[] = $row;
        }
        return $options;
    }

    private function flash($type, $message)
    {
        $this->session->set_userdata('alert_msg', array('type' => $type, 'msg' => $message));
    }
}
