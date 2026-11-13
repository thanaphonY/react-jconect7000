<?php defined('BASEPATH') or exit('No direct script access allowed');

class Control extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        isLogin();
        $this->load->model('Control_model');
        $this->load->helper(['url', 'language', 'notification', 'eventlog']);
        $this->lang->load('control', $this->session->userdata('site_lang') ?: 'english');
        $userDetails = $this->session->get_userdata()['user_details'][0] ?? null;
        $this->user_id = $userDetails ? $userDetails->ia_users_id : 0;
    }

    private function control_can($kind)
    {
        if ($kind === 'read') {
            return CheckPermission('control', 'own_read') || CheckPermission('control', 'all_read');
        }
        if ($kind === 'create') {
            return CheckPermission('control', 'own_create') || CheckPermission('control', 'all_create');
        }
        if ($kind === 'update') {
            return CheckPermission('control', 'own_update') || CheckPermission('control', 'all_update');
        }
        if ($kind === 'delete') {
            return CheckPermission('control', 'own_delete') || CheckPermission('control', 'all_delete');
        }
        return false;
    }

    private function require_control($kind, $json = false)
    {
        if ($this->control_can($kind)) {
            return;
        }
        log_permission_denied($kind, 'Control', 0, 'Missing control ' . $kind . ' permission');
        $msg = lang('control_msg_permission_denied');
        if ($json) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => $msg]);
            exit;
        }
        set_alert_message($msg, 'danger');
        redirect($kind === 'read' ? base_url() : 'control');
    }

    public function index()
    {
        $this->require_control('read');
        $engine = $this->Control_model->health();
        $groupParam = $this->input->get('group');
        $ungrouped = $groupParam === '0' || $groupParam === 'ungrouped';
        $groupId = (!$ungrouped && $groupParam !== null && $groupParam !== '') ? (int) $groupParam : 0;

        $commands = [];
        $logs = [];
        $schedules = [];
        $sequences = [];
        $rules = [];
        $groups = [];
        $ungroupedCount = 0;
        $currentGroup = null;

        if (!empty($engine['ok'])) {
            $grpRes = $this->Control_model->list_groups();
            $groups = $grpRes['groups'] ?? [];
            $ungroupedCount = (int) ($grpRes['ungrouped_count'] ?? 0);

            if ($ungrouped) {
                $currentGroup = [
                    'id' => 0,
                    'name' => lang('control_ungrouped'),
                    'kind' => 'generic',
                    'description' => lang('control_ungrouped_desc'),
                ];
                $cmdRes = $this->Control_model->list_commands(null, true);
                $logRes = $this->Control_model->list_logs(50, null, true);
                $schRes = $this->Control_model->list_schedules(null, true);
                $seqRes = $this->Control_model->list_sequences(null, true);
                $ruleRes = $this->Control_model->list_rules(null, true);
                $commands = $cmdRes['commands'] ?? [];
                $logs = $logRes['logs'] ?? [];
                $schedules = $schRes['schedules'] ?? [];
                $sequences = $seqRes['sequences'] ?? [];
                $rules = $ruleRes['rules'] ?? [];
            } elseif ($groupId > 0) {
                foreach ($groups as $g) {
                    if ((int) ($g['id'] ?? 0) === $groupId) {
                        $currentGroup = $g;
                        break;
                    }
                }
                $cmdRes = $this->Control_model->list_commands($groupId);
                $logRes = $this->Control_model->list_logs(50, $groupId);
                $schRes = $this->Control_model->list_schedules($groupId);
                $seqRes = $this->Control_model->list_sequences($groupId);
                $ruleRes = $this->Control_model->list_rules($groupId);
                $commands = $cmdRes['commands'] ?? [];
                $logs = $logRes['logs'] ?? [];
                $schedules = $schRes['schedules'] ?? [];
                $sequences = $seqRes['sequences'] ?? [];
                $rules = $ruleRes['rules'] ?? [];
            }
        }

        $data = [
            'engine' => $engine,
            'commands' => $commands,
            'logs' => $logs,
            'schedules' => $schedules,
            'sequences' => $sequences,
            'rules' => $rules,
            'groups' => $groups,
            'ungrouped_count' => $ungroupedCount,
            'current_group' => $currentGroup,
            'devices' => $this->Control_model->get_devices(),
            'can_create' => $this->control_can('create'),
            'can_update' => $this->control_can('update'),
            'can_delete' => $this->control_can('delete'),
        ];
        $this->load->view('include/header');
        $this->load->view('index', $data);
        $this->load->view('include/footer');
    }

    private function redirect_group($groupId = null, $tab = null)
    {
        if ($groupId === null || $groupId === '') {
            $groupId = $this->input->post('group_id');
        }
        $posted = $this->input->post('tab');
        if (is_string($posted) && $posted !== '') {
            $tab = $posted;
        }
        $allowed = ['overview', 'flow', 'commands', 'schedules', 'sequences', 'rules', 'logs'];
        if (!$tab || !in_array($tab, $allowed, true)) {
            $tab = null;
        }
        $tabQs = $tab ? '&tab=' . rawurlencode($tab) : '';
        if ($groupId === 0 || $groupId === '0') {
            redirect('control?group=0' . $tabQs);
            return;
        }
        $id = (int) $groupId;
        if ($id > 0) {
            redirect('control?group=' . $id . $tabQs);
            return;
        }
        redirect('control' . ($tab ? '?tab=' . rawurlencode($tab) : ''));
    }

    public function save()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }

        $id = (int) $this->input->post('id');
        $this->require_control($id > 0 ? 'update' : 'create');
        $writeConfig = json_decode((string) $this->input->post('write_config_json'), true);
        if (!is_array($writeConfig)) {
            $writeConfig = [];
        }
        $writeConfig = $this->merge_write_config_fields($writeConfig);

        $payload = [
            'name' => trim((string) $this->input->post('name')),
            'description' => trim((string) $this->input->post('description')),
            'device_id' => (int) $this->input->post('device_id'),
            'protocol' => trim((string) $this->input->post('protocol')),
            'write_config' => $writeConfig,
            'feedback_metric_key' => trim((string) $this->input->post('feedback_metric_key')),
            'enabled' => $this->input->post('enabled') ? true : false,
            'group_id' => (int) $this->input->post('group_id') ?: null,
        ];

        $res = $id > 0
            ? $this->Control_model->update_command($id, $payload)
            : $this->Control_model->create_command($payload);

        $savedId = (int) ($res['command']['id'] ?? $id);
        $this->log_control(
            !empty($res['ok']),
            $id > 0 ? 'update' : 'create',
            'Control Command',
            $savedId,
            $payload['name'],
            $payload,
            $res['error'] ?? ''
        );

        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_command_saved'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_save_failed'), 'danger');
        }
        $this->redirect_group($payload['group_id']);
    }

    public function delete()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control('delete');
        $groupId = $this->input->post('group_id');
        $name = $this->posted_name();
        $res = $this->Control_model->delete_command($id);
        $this->log_control(
            !empty($res['ok']),
            'delete',
            'Control Command',
            $id,
            $name,
            ['id' => $id, 'name' => $name],
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_command_removed'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_delete_failed'), 'danger');
        }
        $this->redirect_group($groupId === '' || $groupId === null ? null : (int) $groupId);
    }

    public function execute()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $ajax = strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        $this->require_control('update', $ajax);
        $value = trim((string) $this->input->post('value'));
        $groupId = $this->input->post('group_id');
        $isAuto = strtolower($value) === 'auto';
        $res = $isAuto
            ? $this->Control_model->release_hold($id, $this->user_id)
            : $this->Control_model->execute_command($id, $value, $this->user_id);
        $valueLabel = $isAuto
            ? 'Auto'
            : (($value === '0' || strtolower($value) === 'off') ? 'Off' : 'On');
        $this->log_control(
            !empty($res['ok']),
            $isAuto ? 'auto' : 'execute',
            'Control Command',
            $id,
            $this->posted_name($res['command']['name'] ?? ''),
            [
                'value' => $value,
                'value_label' => $valueLabel,
                'source' => 'manual',
            ],
            $res['error'] ?? ''
        );
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode($res);
            return;
        }
        if (!empty($res['ok'])) {
            set_alert_message($res['note'] ?? lang('control_msg_command_written'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_execute_failed'), 'danger');
        }
        $this->redirect_group($groupId === '' || $groupId === null ? null : (int) $groupId);
    }

    public function overview_status()
    {
        $this->require_control('read', true);
        $groupParam = $this->input->get('group');
        $ungrouped = $groupParam === '0' || $groupParam === 'ungrouped';
        $groupId = (!$ungrouped && $groupParam !== null && $groupParam !== '') ? (int) $groupParam : 0;

        if ($ungrouped) {
            $cmdRes = $this->Control_model->list_commands(null, true);
            $logRes = $this->Control_model->list_logs(50, null, true);
            $seqRes = $this->Control_model->list_sequences(null, true);
            $ruleRes = $this->Control_model->list_rules(null, true);
        } elseif ($groupId > 0) {
            $cmdRes = $this->Control_model->list_commands($groupId);
            $logRes = $this->Control_model->list_logs(50, $groupId);
            $seqRes = $this->Control_model->list_sequences($groupId);
            $ruleRes = $this->Control_model->list_rules($groupId);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'group required']);
            return;
        }

        $commands = $cmdRes['commands'] ?? [];
        $live = $this->Control_model->command_live_status(
            $commands,
            $logRes['logs'] ?? []
        );
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'items' => $live['items'],
            'summary' => $live['summary'],
            'sequences' => $this->Control_model->running_sequence_diagrams(
                $seqRes['sequences'] ?? [],
                $commands
            ),
            'rules' => $this->Control_model->active_rule_cards(
                $ruleRes['rules'] ?? [],
                $commands,
                $seqRes['sequences'] ?? [],
                $this->Control_model->get_devices()
            ),
        ]);
    }

    public function save_schedule()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control($id > 0 ? 'update' : 'create');
        $time = trim((string) $this->input->post('time'));
        $days = $this->input->post('days');
        if (!is_array($days)) {
            $days = [];
        }
        $days = array_values(array_unique(array_map('intval', $days)));
        $cron = $this->build_cron($time, $days);
        $payload = [
            'command_id' => (int) $this->input->post('command_id'),
            'name' => trim((string) $this->input->post('name')),
            'cron' => $cron,
            'action' => trim((string) $this->input->post('action')) === 'off' ? 'off' : 'on',
            'enabled' => $this->input->post('enabled') ? true : false,
        ];
        $res = $id > 0
            ? $this->Control_model->update_schedule($id, $payload)
            : $this->Control_model->create_schedule($payload);
        $savedId = (int) ($res['schedule']['id'] ?? $id);
        $this->log_control(
            !empty($res['ok']),
            $id > 0 ? 'update' : 'create',
            'Control Schedule',
            $savedId,
            $payload['name'] ?: ($res['schedule']['name'] ?? ''),
            $payload,
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_schedule_saved'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_schedule_save_failed'), 'danger');
        }
        $this->redirect_group($this->input->post('group_id'));
    }

    public function delete_schedule()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control('delete');
        $groupId = $this->input->post('group_id');
        $name = $this->posted_name();
        $res = $this->Control_model->delete_schedule($id);
        $this->log_control(
            !empty($res['ok']),
            'delete',
            'Control Schedule',
            $id,
            $name,
            ['id' => $id, 'name' => $name],
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_schedule_removed'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_schedule_delete_failed'), 'danger');
        }
        $this->redirect_group($groupId === '' || $groupId === null ? null : (int) $groupId);
    }

    public function toggle_schedule()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control('update');
        $enable = $this->input->post('enabled') && $this->input->post('enabled') !== '0';
        $payload = [
            'command_id' => (int) $this->input->post('command_id'),
            'name' => trim((string) $this->input->post('name')),
            'cron' => trim((string) $this->input->post('cron')),
            'action' => trim((string) $this->input->post('action')) === 'off' ? 'off' : 'on',
            'enabled' => $enable,
        ];
        $res = $this->Control_model->update_schedule($id, $payload);
        $this->log_control(
            !empty($res['ok']),
            $enable ? 'activate' : 'deactivate',
            'Control Schedule',
            $id,
            $payload['name'],
            $payload,
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(
                $enable ? lang('control_msg_schedule_activated') : lang('control_msg_schedule_deactivated'),
                'success'
            );
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_schedule_save_failed'), 'danger');
        }
        $this->redirect_group($this->input->post('group_id'), 'schedules');
    }

    public function save_sequence()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control($id > 0 ? 'update' : 'create');
        $steps = json_decode((string) $this->input->post('steps_json'), true);
        if (!is_array($steps)) {
            $steps = [];
        }
        $payload = [
            'name' => trim((string) $this->input->post('name')),
            'steps' => $this->normalize_sequence_steps($steps),
            'enabled' => $this->input->post('enabled') ? true : false,
            'group_id' => (int) $this->input->post('group_id') ?: null,
            'loop' => $this->input->post('loop') ? true : false,
            'loop_count' => max(0, min(9999, (int) $this->input->post('loop_count'))),
            'loop_delay_sec' => max(0, min(86400, (int) $this->input->post('loop_delay_sec'))),
        ];
        $res = $id > 0
            ? $this->Control_model->update_sequence($id, $payload)
            : $this->Control_model->create_sequence($payload);
        $savedId = (int) ($res['sequence']['id'] ?? $id);
        $this->log_control(
            !empty($res['ok']),
            $id > 0 ? 'update' : 'create',
            'Control Sequence',
            $savedId,
            $payload['name'],
            $payload,
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_sequence_saved'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_sequence_save_failed'), 'danger');
        }
        $this->redirect_group($payload['group_id'], 'sequences');
    }

    public function delete_sequence()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control('delete');
        $groupId = $this->input->post('group_id');
        $name = $this->posted_name();
        $res = $this->Control_model->delete_sequence($id);
        $this->log_control(
            !empty($res['ok']),
            'delete',
            'Control Sequence',
            $id,
            $name,
            ['id' => $id, 'name' => $name],
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_sequence_removed'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_sequence_delete_failed'), 'danger');
        }
        $this->redirect_group($groupId === '' || $groupId === null ? null : (int) $groupId, 'sequences');
    }

    public function execute_sequence()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $ajax = strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        $this->require_control('update', $ajax);
        $groupId = $this->input->post('group_id');
        $res = $this->Control_model->execute_sequence($id, $this->user_id);
        $this->log_control(
            !empty($res['ok']),
            'run',
            'Control Sequence',
            $id,
            $this->posted_name($res['sequence']['name'] ?? ''),
            ['source' => 'manual'],
            $res['error'] ?? ''
        );
        $ajax = strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode($res);
            return;
        }
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_sequence_started'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_sequence_run_failed'), 'danger');
        }
        $this->redirect_group($groupId === '' || $groupId === null ? null : (int) $groupId, 'sequences');
    }

    public function stop_sequence()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $ajax = strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        $this->require_control('update', $ajax);
        $groupId = $this->input->post('group_id');
        $res = $this->Control_model->stop_sequence($id);
        $this->log_control(
            !empty($res['ok']),
            'stop',
            'Control Sequence',
            $id,
            $this->posted_name(),
            ['source' => 'manual'],
            $res['error'] ?? ''
        );
        $ajax = strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode($res);
            return;
        }
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_sequence_stopping'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_sequence_stop_failed'), 'danger');
        }
        $this->redirect_group($groupId === '' || $groupId === null ? null : (int) $groupId, 'sequences');
    }

    public function pause_sequence()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $ajax = strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        $this->require_control('update', $ajax);
        $groupId = $this->input->post('group_id');
        $res = $this->Control_model->pause_sequence($id);
        $this->log_control(
            !empty($res['ok']),
            'pause',
            'Control Sequence',
            $id,
            $this->posted_name(),
            ['source' => 'manual'],
            $res['error'] ?? ''
        );
        $ajax = strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode($res);
            return;
        }
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_sequence_paused'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_sequence_pause_failed'), 'danger');
        }
        $this->redirect_group($groupId === '' || $groupId === null ? null : (int) $groupId, 'sequences');
    }

    public function resume_sequence()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $ajax = strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        $this->require_control('update', $ajax);
        $groupId = $this->input->post('group_id');
        $res = $this->Control_model->resume_sequence($id);
        $this->log_control(
            !empty($res['ok']),
            'resume',
            'Control Sequence',
            $id,
            $this->posted_name(),
            ['source' => 'manual'],
            $res['error'] ?? ''
        );
        $ajax = strtolower((string) $this->input->get_request_header('X-Requested-With')) === 'xmlhttprequest';
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode($res);
            return;
        }
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_sequence_resumed'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_sequence_resume_failed'), 'danger');
        }
        $this->redirect_group($groupId === '' || $groupId === null ? null : (int) $groupId, 'sequences');
    }

    public function save_rule()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control($id > 0 ? 'update' : 'create');
        $conditions = json_decode((string) $this->input->post('conditions_json'), true);
        $action = json_decode((string) $this->input->post('action_json'), true);
        $payload = [
            'name' => trim((string) $this->input->post('name')),
            'conditions' => $this->normalize_rule_conditions(is_array($conditions) ? $conditions : []),
            'action' => $this->normalize_rule_action(is_array($action) ? $action : []),
            'enabled' => $this->input->post('enabled') ? true : false,
            'group_id' => (int) $this->input->post('group_id') ?: null,
            'cooldown_sec' => max(0, min(86400, (int) $this->input->post('cooldown_sec'))),
        ];
        $res = $id > 0
            ? $this->Control_model->update_rule($id, $payload)
            : $this->Control_model->create_rule($payload);
        $savedId = (int) ($res['rule']['id'] ?? $id);
        $this->log_control(
            !empty($res['ok']),
            $id > 0 ? 'update' : 'create',
            'Control Rule',
            $savedId,
            $payload['name'],
            $payload,
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_rule_saved'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_rule_save_failed'), 'danger');
        }
        $this->redirect_group($payload['group_id'], 'rules');
    }

    public function delete_rule()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control('delete');
        $groupId = $this->input->post('group_id');
        $name = $this->posted_name();
        $res = $this->Control_model->delete_rule($id);
        $this->log_control(
            !empty($res['ok']),
            'delete',
            'Control Rule',
            $id,
            $name,
            ['id' => $id, 'name' => $name],
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_rule_removed'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_rule_delete_failed'), 'danger');
        }
        $this->redirect_group($groupId === '' || $groupId === null ? null : (int) $groupId, 'rules');
    }

    private function normalize_rule_conditions(array $raw)
    {
        $match = strtolower((string) ($raw['match'] ?? 'all')) === 'any' ? 'any' : 'all';
        $items = [];
        $list = isset($raw['items']) && is_array($raw['items']) ? $raw['items'] : $raw;
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = strtolower((string) ($item['type'] ?? ''));
            if ($type === 'command_status') {
                $cid = (int) ($item['command_id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                $items[] = [
                    'type' => 'command_status',
                    'command_id' => $cid,
                    'state' => strtolower((string) ($item['state'] ?? 'off')) === 'on' ? 'on' : 'off',
                ];
            } elseif ($type === 'alarm') {
                $did = (int) ($item['device_id'] ?? 0);
                if ($did <= 0) {
                    continue;
                }
                $key = trim((string) ($item['metric_key'] ?? ''));
                $items[] = [
                    'type' => 'alarm',
                    'device_id' => $did,
                    'metric_key' => $key !== '' ? $key : null,
                ];
            } elseif ($type === 'metric') {
                $did = (int) ($item['device_id'] ?? 0);
                $key = trim((string) ($item['key'] ?? $item['metric_key'] ?? ''));
                $op = (string) ($item['op'] ?? '>');
                if ($did <= 0 || $key === '') {
                    continue;
                }
                if (!in_array($op, ['>', '>=', '<', '<=', '==', '!='], true)) {
                    $op = '>';
                }
                $items[] = [
                    'type' => 'metric',
                    'device_id' => $did,
                    'key' => $key,
                    'op' => $op,
                    'value' => $item['value'] ?? '',
                ];
            }
        }
        return ['match' => $match, 'items' => $items];
    }

    private function normalize_rule_action(array $raw)
    {
        $kind = strtolower((string) ($raw['kind'] ?? $raw['type'] ?? 'command'));
        if ($kind === 'sequence') {
            return [
                'kind' => 'sequence',
                'sequence_id' => (int) ($raw['sequence_id'] ?? 0),
                'commands' => [],
            ];
        }
        $commands = [];
        $list = isset($raw['commands']) && is_array($raw['commands']) ? $raw['commands'] : [];
        foreach ($list as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cid = (int) ($c['command_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $commands[] = [
                'command_id' => $cid,
                'action' => strtolower((string) ($c['action'] ?? 'on')) === 'off' ? 'off' : 'on',
            ];
        }
        return [
            'kind' => 'command',
            'sequence_id' => null,
            'commands' => $commands,
        ];
    }

    private function normalize_sequence_steps(array $steps)
    {
        $clean = [];
        foreach ($steps as $s) {
            if (!is_array($s)) {
                continue;
            }
            $cid = (int) ($s['command_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $wait = strtolower((string) ($s['wait'] ?? 'none'));
            if (!in_array($wait, ['on', 'off'], true)) {
                $wait = 'none';
            }
            $unit = strtolower((string) ($s['delay_unit'] ?? 's'));
            if (!in_array($unit, ['s', 'min', 'h'], true)) {
                $unit = 's';
            }
            $clean[] = [
                'command_id' => $cid,
                'action' => strtolower((string) ($s['action'] ?? 'on')) === 'off' ? 'off' : 'on',
                'wait' => $wait,
                'timeout_sec' => max(0, (int) ($s['timeout_sec'] ?? 0)),
                'delay_sec' => max(0, min(86400, (int) ($s['delay_sec'] ?? 0))),
                'delay_unit' => $unit,
            ];
        }
        return $clean;
    }

    public function save_group()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control($id > 0 ? 'update' : 'create');
        $payload = [
            'name' => trim((string) $this->input->post('name')),
            'kind' => trim((string) $this->input->post('kind')),
            'description' => trim((string) $this->input->post('description')),
        ];
        $res = $id > 0
            ? $this->Control_model->update_group($id, $payload)
            : $this->Control_model->create_group($payload);
        $savedId = (int) ($res['group']['id'] ?? $id);
        $this->log_control(
            !empty($res['ok']),
            $id > 0 ? 'update' : 'create',
            'Control Group',
            $savedId,
            $payload['name'],
            $payload,
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_group_saved'), 'success');
            $newId = (int) ($res['group']['id'] ?? $id);
            $this->redirect_group($newId ?: null);
            return;
        }
        set_alert_message($res['error'] ?? lang('control_msg_group_save_failed'), 'danger');
        redirect('control');
    }

    public function delete_group()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
        $id = (int) $this->input->post('id');
        $this->require_control('delete');
        $name = $this->posted_name();
        $res = $this->Control_model->delete_group($id);
        $this->log_control(
            !empty($res['ok']),
            'delete',
            'Control Group',
            $id,
            $name,
            ['id' => $id, 'name' => $name],
            $res['error'] ?? ''
        );
        if (!empty($res['ok'])) {
            set_alert_message(lang('control_msg_group_removed'), 'success');
        } else {
            set_alert_message($res['error'] ?? lang('control_msg_group_delete_failed'), 'danger');
        }
        redirect('control');
    }

    private function build_cron($time, array $days)
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', (string) $time, $m)) {
            $m = [null, '8', '0'];
        }
        $hour = max(0, min(23, (int) $m[1]));
        $minute = max(0, min(59, (int) $m[2]));
        $valid = [];
        foreach ($days as $d) {
            if ($d >= 0 && $d <= 6) {
                $valid[] = $d;
            }
        }
        sort($valid);
        $dow = count($valid) === 0 || count($valid) === 7 ? '*' : implode(',', $valid);
        return $minute . ' ' . $hour . ' * * ' . $dow;
    }

    private function merge_write_config_fields(array $writeConfig)
    {
        $on = $this->coerce_set_value($this->input->post('on_value'));
        $off = $this->coerce_set_value($this->input->post('off_value'));
        if ($on === null) {
            unset($writeConfig['on_value']);
        } else {
            $writeConfig['on_value'] = $on;
        }
        if ($off === null) {
            unset($writeConfig['off_value']);
        } else {
            $writeConfig['off_value'] = $off;
        }

        $fbOn = $this->parse_map_list($this->input->post('feedback_on'));
        $fbOff = $this->parse_map_list($this->input->post('feedback_off'));
        if ($fbOn) {
            $writeConfig['feedback_on'] = $fbOn;
        } else {
            unset($writeConfig['feedback_on']);
        }
        if ($fbOff) {
            $writeConfig['feedback_off'] = $fbOff;
        } else {
            unset($writeConfig['feedback_off']);
        }

        $onLabel = trim((string) $this->input->post('feedback_on_label'));
        $offLabel = trim((string) $this->input->post('feedback_off_label'));
        if ($onLabel !== '') {
            $writeConfig['feedback_on_label'] = $onLabel;
        } else {
            unset($writeConfig['feedback_on_label']);
        }
        if ($offLabel !== '') {
            $writeConfig['feedback_off_label'] = $offLabel;
        } else {
            unset($writeConfig['feedback_off_label']);
        }
        return $writeConfig;
    }

    private function coerce_set_value($raw)
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (is_numeric($s)) {
            return strpos($s, '.') === false ? (int) $s : (float) $s;
        }
        return $s;
    }

    private function parse_map_list($raw)
    {
        $parts = array_map('trim', explode(',', (string) $raw));
        $out = [];
        foreach ($parts as $p) {
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    private function posted_name($fallback = '')
    {
        $n = trim((string) $this->input->post('name'));
        return $n !== '' ? $n : trim((string) $fallback);
    }

    private function log_safe_meta(array $meta)
    {
        $secretKeys = ['community', 'write_community', 'set_community', 'rw_community', 'password'];
        foreach ($meta as $k => $v) {
            if (is_array($v)) {
                $meta[$k] = $this->log_safe_meta($v);
            } elseif (in_array(strtolower((string) $k), $secretKeys, true) && $v !== '' && $v !== null) {
                $meta[$k] = '***';
            }
        }
        return $meta;
    }

    private function log_control($ok, $action, $entityType, $id, $name, array $meta = [], $error = '')
    {
        $id = (int) $id;
        $name = trim((string) $name);
        if ($name === '') {
            $name = '#' . $id;
        }
        $meta = $this->log_safe_meta($meta);
        if ($ok) {
            if ($action === 'create') {
                log_record_created($entityType, $id, $name, $meta);
                return;
            }
            if ($action === 'update') {
                log_record_updated($entityType, $id, $name, [], $meta);
                return;
            }
            if ($action === 'delete') {
                log_record_deleted($entityType, $id, $name, $meta ?: ['id' => $id, 'name' => $name]);
                return;
            }
            $label = [
                'execute' => 'Executed',
                'auto' => 'Released to Auto',
                'activate' => 'Activated',
                'deactivate' => 'Deactivated',
                'run' => 'Started',
                'stop' => 'Stopped',
                'pause' => 'Paused',
                'resume' => 'Resumed',
            ];
            $verb = $label[$action] ?? ucfirst((string) $action);
            $message = $verb . ' ' . $entityType . ': ' . $name;
            if ($action === 'execute' && !empty($meta['value_label'])) {
                $message .= ' (' . $meta['value_label'] . ')';
            }
            $meta['status'] = 'success';
            insert_event_log($action, $entityType, $id, $message, $meta);
            return;
        }
        $message = 'Failed to ' . $action . ' ' . $entityType . ': ' . $name;
        if ($error !== '') {
            $message .= ' — ' . $error;
        }
        log_error($message, $entityType, $id, array_merge($meta, [
            'error_message' => $error,
            'attempted_action' => $action,
        ]));
    }

    public function device_metrics($device_id = 0)
    {
        if (!$this->control_can('create') && !$this->control_can('update')) {
            $this->require_control('update', true);
        }
        $writeDeviceId = (int) $this->input->get('write_device_id');
        $ctx = $this->Control_model->get_device_control_context((int) $device_id, $writeDeviceId);
        header('Content-Type: application/json');
        if (!$ctx) {
            echo json_encode(['ok' => false, 'error' => 'Device not found']);
            return;
        }
        echo json_encode(['ok' => true, 'device' => $ctx]);
    }
}
