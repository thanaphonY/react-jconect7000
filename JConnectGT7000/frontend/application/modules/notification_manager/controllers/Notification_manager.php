<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Notification_manager extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Notification_channel_model');
        $this->load->model('Health_checks_model');
        $this->load->helper('url');
        $this->load->helper('notification_helper_updated');
        $this->load->helper('notification_helper'); // เพิ่ม notification helper
        $this->load->helper('language');
        $this->load->helper('eventlog'); // เพิ่ม eventlog helper
        site_require_global();
        $this->lang->load('notification_manager', $this->session->userdata('site_lang'));
    }

    public function index()
    {
        // Redirect to enhanced version
        $data['channels'] = $this->Notification_channel_model->getAll();

        // Get additional dashboard data
        $data['total_devices'] = $this->db->where('is_deleted', false)->count_all_results('devices');

        // Count today's alarms from alarm_logs (existing table)
        $today = date('Y-m-d');
        $data['total_alarms'] = $this->db->where('DATE(created_at)', $today)
            ->count_all_results('alarm_logs');

        // Get total channels and active channels
        $data['total_channels'] = count($data['channels']);
        $data['active_channels'] = 0;

        foreach ($data['channels'] as $channel) {
            if (getConnectedDevices($channel->id) > 0) {
                $data['active_channels']++;
            }
        }

        $this->load->view('include/header');
        $this->load->view('notification_manager/index_enhanced', $data);
        $this->load->view('include/footer');
    }

    public function add()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $this->input->post('name'),
                'type' => $this->input->post('type'),
                'is_active' => $this->input->post('is_active'),
                'message_template' => $this->input->post('message_template'),
                'description' => $this->input->post('description'),
                'config' => json_encode($this->input->post('config')),
            ];
            
            $result = $this->Notification_channel_model->insert($data);
            
            // Log the creation
            if ($result) {
                log_record_created(
                    'Notification',
                    $result,
                    $data['name'],
                    $data
                );
                
                // Use notification helper for success message
                flash_success_message('Channel created successfully!', 'notification_manager');
            } else {
                // Use notification helper for error message
                flash_error_message('Failed to create channel!', 'notification_manager');
            }
        }
        $this->load->view('include/header');
        $this->load->view('notification_manager/form');
        $this->load->view('include/footer');
    }

    public function edit($id)
    {
        $channel = $this->Notification_channel_model->getById($id);
        if (!$channel) show_404();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Get old values for logging
            $old_channel = (array) $channel;
            
            $data = [
                'name' => $this->input->post('name'),
                'type' => $this->input->post('type'),
                'is_active' => $this->input->post('is_active'),
                'message_template' => $this->input->post('message_template'),
                'description' => $this->input->post('description'),
                'config' => json_encode($this->input->post('config')),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->Notification_channel_model->update($id, $data);
            
            // Log the update
            if ($result) {
                log_record_updated(
                    'Notification',
                    $id,
                    $data['name'],
                    $old_channel,
                    $data
                );
                
                // Use notification helper for success message
                flash_success_message('Channel updated successfully!', 'notification_manager');
            } else {
                // Use notification helper for error message
                flash_error_message('Failed to update channel!', 'notification_manager');
            }
        }

        $data['channel'] = $channel;
        $this->load->view('include/header');
        $this->load->view('notification_manager/form', $data);
        $this->load->view('include/footer');
    }

    public function delete($id)
    {
        // Get channel data before soft deletion for logging
        $channel = $this->Notification_channel_model->getByIdIncludingDeleted($id);
        
        if (!$channel) {
            flash_error_message('Channel not found', 'notification_manager');
            return;
        }
        
        $result = $this->Notification_channel_model->delete($id);
        
        // Log the soft deletion
        if ($result && $channel) {
            log_record_deleted(
                'Notification',
                $id,
                $channel->name,
                (array) $channel
            );
            
            // Use notification helper for success message
            flash_success_message('Channel has been soft deleted successfully', 'notification_manager');
        } else {
            // Use notification helper for error message
            flash_error_message('Failed to delete channel', 'notification_manager');
        }
    }

    /**
     * Restore soft deleted channel (Admin only)
     */
    public function restore($id)
    {
        // Check if user has permission to restore
        if (!CheckPermission("notification_manager", "all_update")) {
            flash_error_message('Permission denied', 'notification_manager');
            return;
        }
        
        // Get channel data before restoration for logging
        $channel = $this->Notification_channel_model->getByIdIncludingDeleted($id);
        
        if (!$channel) {
            flash_error_message('Channel not found', 'notification_manager');
            return;
        }
        
        $result = $this->Notification_channel_model->restore($id);
        
        // Log the restoration
        if ($result && $channel) {
            log_record_updated(
                'Notification',
                $id,
                $channel->name,
                ['is_deleted' => FALSE],
                ['is_deleted' => TRUE]
            );
            
            // Use notification helper for success message
            flash_success_message('Channel has been restored successfully', 'notification_manager');
        } else {
            // Use notification helper for error message
            flash_error_message('Failed to restore channel', 'notification_manager');
        }
    }

    /**
     * Get devices connected to a notification channel
     */
    public function get_devices($channelId)
    {
        $devices = getDeviceList($channelId);

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>Device</th><th>Description</th><th>Location</th><th>Metric</th><th>Threshold</th><th>Clear Threshold</th><th>Severity</th><th>Status</th></tr></thead>';
        $html .= '<tbody>';

        if (empty($devices)) {
            $html .= '<tr><td colspan="8" class="text-center text-muted">No devices connected to this channel</td></tr>';
        } else {
            foreach ($devices as $device) {
                $statusClass = $device['is_enabled'] ? 'text-success' : 'text-danger';
                $statusText = $device['is_enabled'] ? 'Active' : 'Inactive';

                // Severity badge styling
                $sev = strtolower($device['severity']);
                $severityClass = '';
                switch ($sev) {
                    case 'critical':
                        $severityClass = 'bg-danger';
                        break;
                    case 'warning':
                        $severityClass = 'bg-warning';
                        break;
                    case 'info':
                        $severityClass = 'bg-info';
                        break;
                    default:
                        $severityClass = 'bg-secondary';
                }

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($device['device_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($device['description']) . '</td>';
                $html .= '<td>' . htmlspecialchars($device['location']) . '</td>';
                $html .= '<td>' . htmlspecialchars($device['metric_label']) . ' (' . htmlspecialchars($device['metric_unit']) . ')</td>';
                $html .= '<td>' . htmlspecialchars($device['trigger_operator']) . ' ' . htmlspecialchars($device['trigger_value']) . '</td>';
                $html .= '<td>';
                if ($device['clear_value'] !== '') {
                    if ($device['clear_operator'] !== '') {
                        $html .= htmlspecialchars($device['clear_operator']) . ' ' . htmlspecialchars($device['clear_value']);
                    } else {
                        $html .= htmlspecialchars($device['clear_value']);
                    }
                } else {
                    $html .= '-';
                }
                $html .= '</td>';
                $html .= '<td><span class="badge ' . $severityClass . '">' . htmlspecialchars(ucfirst($sev)) . '</span></td>';
                $html .= '<td><span class="' . $statusClass . '">' . $statusText . '</span></td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';

        echo $html;
    }

    /**
     * Get message history for a channel
     */
    public function get_message_history($channelId)
    {
        $messages = getMessageHistory($channelId);

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>Time</th><th>Device</th><th>Message</th><th>Status</th></tr></thead>';
        $html .= '<tbody>';

        if (empty($messages)) {
            $html .= '<tr><td colspan="4" class="text-center text-muted">No message history found</td></tr>';
        } else {
            foreach ($messages as $message) {
                $statusClass = $message->status === 'sent' ? 'text-success' : 'text-danger';
                $statusIcon = $message->status === 'sent' ? 'check-circle' : 'x-circle';

                $html .= '<tr>';
                $html .= '<td>' . date('M d, H:i:s', strtotime($message->sent_at)) . '</td>';
                $html .= '<td>' . htmlspecialchars($message->device_name) . '</td>';
                $html .= '<td>' . htmlspecialchars($message->message) . '</td>';
                $html .= '<td><i class="bi bi-' . $statusIcon . ' ' . $statusClass . '"></i> ' . ucfirst($message->status) . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';

        echo $html;
    }

    /**
     * Get channel template preview
     */
    public function get_channel_template($channelId)
    {
        $preview = getChannelTemplate($channelId);

        echo '<div class="alert alert-info">' . htmlspecialchars($preview) . '</div>';
    }

    /**
     * Send test message through a channel
     */
    public function send_test($channelId)
    {
        $message = $this->input->post('message');
        if (!$message) {
            $message = 'Test notification from JConnect System';
        }

        $result = sendTestMessage($channelId, $message);
        
        // Log the test message
        log_system_action(
            'Send test notification',
            'Notification',
            $channelId,
            [
                'message' => $message,
                'result' => $result,
                'channel_id' => $channelId
            ]
        );

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Enhanced index with dashboard data
     */
    public function index_enhanced()
    {
        $data['channels'] = $this->Notification_channel_model->getAll();

        // Get additional dashboard data
        $data['total_devices'] = $this->db->where('is_deleted', false)->count_all_results('devices');

        // Count today's alarms from alarm_logs (existing table)
        $today = date('Y-m-d');
        $data['total_alarms'] = $this->db->where('DATE(created_at)', $today)
            ->count_all_results('alarm_logs');

        // Get total channels and active channels
        $data['total_channels'] = count($data['channels']);
        $data['active_channels'] = 0;

        foreach ($data['channels'] as $channel) {
            if (getConnectedDevices($channel->id) > 0) {
                $data['active_channels']++;
            }
        }

        $this->load->view('include/header');
        $this->load->view('notification_manager/index_enhanced', $data);
        $this->load->view('include/footer');
    }

    /**
     * Get channel data for edit modal
     */
    public function get_channel_data($id)
    {
        $channel = $this->Notification_channel_model->getById($id);
        if (!$channel) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Channel not found']);
            return;
        }

        // Convert object to array if needed
        if (is_object($channel)) {
            $channel = (array) $channel;
        }

        header('Content-Type: application/json');
        echo json_encode($channel);
    }

    /**
     * Create new channel via AJAX
     */
    public function create_channel()
    {
        $data = [
            'name' => $this->input->post('name'),
            'type' => $this->input->post('type'),
            'is_active' => $this->input->post('is_active') ? 't' : 'f',
            'message_template' => $this->input->post('message_template'),
            'description' => $this->input->post('description'),
            'config' => json_encode($this->input->post('config'))
        ];

        $result = $this->Notification_channel_model->insert($data);

        // Log the creation
        if ($result) {
            // Get the inserted ID from the database
            $inserted_id = $this->db->insert_id();
            log_record_created(
                'Notification',
                $inserted_id,
                $data['name'],
                $data
            );
        }

        header('Content-Type: application/json');
        if ($result) {
            echo json_encode(['success' => true, 'id' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create channel']);
        }
    }

    /**
     * Update channel via AJAX
     */
    public function update_channel($id)
    {
        // Get old values for logging
        $old_channel = $this->Notification_channel_model->getById($id);
        
        $data = [
            'name' => $this->input->post('name'),
            'type' => $this->input->post('type'),
            'is_active' => $this->input->post('is_active') ? true : false,
            'message_template' => $this->input->post('message_template'),
            'description' => $this->input->post('description'),
            'config' => json_encode($this->input->post('config'))
        ];

        $result = $this->Notification_channel_model->update($id, $data);

        // Log the update
        if ($result && $old_channel) {
            log_record_updated(
                'Notification',
                $id,
                $data['name'],
                (array) $old_channel,
                $data
            );
        }

        header('Content-Type: application/json');
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update channel']);
        }
    }

    public function health_checks()
    {
        isLogin();
        $data = [
            'checks' => $this->Health_checks_model->get_all(),
            'channels' => $this->Notification_channel_model->getAll(),
            'default_template' => $this->Health_checks_model->default_template(),
        ];
        $this->load->view('include/header');
        $this->load->view('notification_manager/health_checks', $data);
        $this->load->view('include/footer');
    }

    public function health_check_save()
    {
        isLogin();
        $this->output->set_content_type('application/json');
        $id = $this->Health_checks_model->save([
            'id' => $this->input->post('id'),
            'name' => $this->input->post('name'),
            'is_active' => $this->input->post('is_active'),
            'schedule_type' => $this->input->post('schedule_type'),
            'schedule_time' => $this->input->post('schedule_time'),
            'schedule_weekday' => $this->input->post('schedule_weekday'),
            'schedule_day' => $this->input->post('schedule_day'),
            'channel_ids' => $this->input->post('channel_ids'),
            'message_template' => $this->input->post('message_template'),
        ]);
        $this->output->set_output(json_encode([
            'success' => $id > 0,
            'message' => $id > 0 ? 'Saved' : 'Name is required',
            'id' => $id,
        ]));
    }

    public function health_check_delete($id)
    {
        isLogin();
        $this->output->set_content_type('application/json');
        $ok = $this->Health_checks_model->delete($id);
        $this->output->set_output(json_encode(['success' => $ok]));
    }

    public function health_check_preview()
    {
        isLogin();
        $this->output->set_content_type('application/json');
        $row = $this->_health_check_from_post();
        $rendered = $this->Health_checks_model->render($row);
        $this->output->set_output(json_encode(['success' => true, 'message' => $rendered['message'], 'status' => $rendered['status']]));
    }

    public function health_check_send($id)
    {
        isLogin();
        $this->output->set_content_type('application/json');
        $row = $this->Health_checks_model->get($id);
        if (!$row) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Not found']));
            return;
        }
        $rendered = $this->Health_checks_model->render($row);
        $results = $this->Health_checks_model->deliver($row, $rendered);
        $ok = $this->Health_checks_model->write_log($row->id, $rendered, $results);
        $this->output->set_output(json_encode(['success' => $ok, 'message' => $rendered['message'], 'channels' => $results]));
    }

    /**
     * Called by report-scheduler every minute. Same key as report schedules.
     */
    public function run_health_checks()
    {
        $this->output->set_content_type('application/json');
        $this->config->load('reports');
        $key = $this->input->get('key');
        $expected = $this->config->item('report_schedule_key') ?: 'report_schedule_secret_key';
        if ($key !== $expected) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Unauthorized']));
            return;
        }
        $due = $this->Health_checks_model->get_due();
        $done = 0;
        $errors = [];
        foreach ($due as $row) {
            if (!$this->Health_checks_model->claim($row->id)) {
                continue;
            }
            $rendered = $this->Health_checks_model->render($row);
            $results = $this->Health_checks_model->deliver($row, $rendered);
            $ok = $this->Health_checks_model->write_log($row->id, $rendered, $results);
            if ($ok) {
                $done++;
            } else {
                $errors[] = $row->name . ': one or more channels failed';
            }
        }
        $this->output->set_output(json_encode([
            'success' => true,
            'processed' => count($due),
            'done' => $done,
            'errors' => $errors,
        ]));
    }

    private function _health_check_from_post()
    {
        $channels = $this->input->post('channel_ids');
        $row = (object) [
            'name' => trim((string) $this->input->post('name')),
            'timezone' => 'Asia/Bangkok',
            'message_template' => trim((string) $this->input->post('message_template')),
            'channel_ids' => array_values(array_map('intval', is_array($channels) ? $channels : [])),
            'schedule_config' => [],
            'schedule_type' => 'daily',
        ];
        if ($row->message_template === '') {
            $row->message_template = $this->Health_checks_model->default_template();
        }
        return $row;
    }
}
