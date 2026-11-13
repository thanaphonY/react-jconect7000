<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Alarmlogs extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        isLogin();
        $userDetails = $this->session->get_userdata()['user_details'][0] ?? null;
        $username = $userDetails ? $userDetails->name : 'unknown';
        $this->user_id = $userDetails ? $userDetails->ia_users_id : 0;
    
        $this->load->model('Alarmlogs_model');

        $this->load->helper('language');
        $this->lang->load('alarmlogs', $this->session->userdata('site_lang'));
    }

    public function index()
    {
        // Get statistics data
        $data['total_alarms'] = $this->Alarmlogs_model->get_total_alarms();
        $data['active_alarms'] = $this->Alarmlogs_model->get_active_alarms();
        $data['severity_breakdown'] = $this->Alarmlogs_model->get_severity_breakdown();
        $data['device_breakdown'] = $this->Alarmlogs_model->get_device_breakdown();
        
        // Get filter options
        $data['devices'] = $this->Alarmlogs_model->get_unique_devices();
        $data['severities'] = $this->Alarmlogs_model->get_unique_severities();
        $data['statuses'] = $this->Alarmlogs_model->get_unique_statuses();
        
        $this->load->view('include/header');
        $this->load->view('index', $data);
        $this->load->view('include/footer');
    }

    public function datatable()
    {
        $range = $this->input->get('range');
        $startDate = $this->input->get('startRange');
        $endDate = $this->input->get('endRange');



        $start = $this->input->post('start');
        $length = $this->input->post('length');
        $search = $this->input->post('search')['value'] ?? '';

        $this->db->select('alarm_logs.*, devices.name AS device_name');

        $this->db->from('alarm_logs');
        $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');
        $scopeIds = site_visible_device_ids();
        if (is_array($scopeIds)) {
            if (!$scopeIds) {
                $this->db->where('1 = 0', null, false);
            } else {
                $this->db->where('alarm_logs.device_id IN (' . implode(',', array_map('intval', $scopeIds)) . ')', null, false);
            }
        }

        if ($range) {
            switch ($range) {
                case 'today':
                    $this->db->where('DATE(alarm_logs.created_at)', date('Y-m-d'));
                    break;
                case 'yesterday':
                    $this->db->where('DATE(alarm_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                    break;
                case 'last_7_days':
                    $this->db->where('DATE(alarm_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                    break;
                case 'last_30_days':
                    $this->db->where('DATE(alarm_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                    break;
                case 'custom':
                    if ($startDate && $endDate) {
                        $this->db->where('DATE(alarm_logs.created_at) >=', $startDate);
                        $this->db->where('DATE(alarm_logs.created_at) <=', $endDate);
                    }
                    break;
            }
        }



        if ($search) {
            $this->db->group_start();
            $this->db->like('devices.name', $search);
            $this->db->or_like('alarm_logs.metric_key', $search);
            $this->db->or_like('alarm_logs.message', $search);
            $this->db->group_end();
        }

        $recordsFiltered = $this->db->count_all_results('', false);
        $this->db->limit($length, $start);
        $query = $this->db->get();

        $recordsTotal = is_array($scopeIds) ? $recordsFiltered : $this->db->count_all('alarm_logs');

        echo json_encode([
            "draw" => intval($this->input->post('draw')),
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $query->result()
        ]);
    }

    public function get_statistics()
    {
        try {
            $range = $this->input->get('range') ?? 'last_30_days';
            
            $stats = [
                'total_alarms' => $this->Alarmlogs_model->get_total_alarms(),
                'active_alarms' => $this->Alarmlogs_model->get_active_alarms(),
                'severity_breakdown' => $this->Alarmlogs_model->get_severity_breakdown(),
                'device_breakdown' => $this->Alarmlogs_model->get_device_breakdown()
            ];
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function get_logs()
    {
        try {
            $page = intval($this->input->post('page')) ?: 1;
            $pageSize = intval($this->input->post('pageSize')) ?: 10;
            $search = $this->input->post('search') ?: '';
            $range = $this->input->post('range') ?: 'last_30_days';
            $startDate = $this->input->post('startDate');
            $endDate = $this->input->post('endDate');
            $device_filter = $this->input->post('device_filter');
            $severity_filter = $this->input->post('severity_filter');
            $status_filter = $this->input->post('status_filter');

            // Calculate offset
            $offset = ($page - 1) * $pageSize;

            // Set charset for PostgreSQL connection
            $this->db->query("SET NAMES 'UTF8'");
            
            // Build query
            $this->db->select('alarm_logs.*, devices.name AS device_name');
            $this->db->from('alarm_logs');
            $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');

            // Apply date range filter
            if ($range && $range !== 'all') {
                switch ($range) {
                    case 'today':
                        $this->db->where('DATE(alarm_logs.created_at)', date('Y-m-d'));
                        break;
                    case 'yesterday':
                        $this->db->where('DATE(alarm_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                        break;
                    case 'last_7_days':
                        $this->db->where('DATE(alarm_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                        break;
                    case 'last_30_days':
                        $this->db->where('DATE(alarm_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                        break;
                    case 'custom':
                        if ($startDate && $endDate) {
                            $this->db->where('DATE(alarm_logs.created_at) >=', $startDate);
                            $this->db->where('DATE(alarm_logs.created_at) <=', $endDate);
                        }
                        break;
                }
            }

            // Apply additional filters
            if ($device_filter && $device_filter !== 'all') {
                $this->db->where('alarm_logs.device_id', $device_filter);
            }
            if ($severity_filter && $severity_filter !== 'all') {
                $this->db->where('alarm_logs.severity', $severity_filter);
            }
            if ($status_filter && $status_filter !== 'all') {
                $this->db->where('alarm_logs.status', $status_filter);
            }

            // Apply search
            if ($search) {
                $this->db->group_start();
                $this->db->like('devices.name', $search);
                $this->db->or_like('alarm_logs.metric_key', $search);
                $this->db->or_like('alarm_logs.message', $search);
                $this->db->or_like('alarm_logs.severity', $search);
                $this->db->group_end();
            }

            // Get total count for pagination
            $totalQuery = $this->db->get_compiled_select();
            $this->db->reset_query();
            
            $this->db->from('(' . $totalQuery . ') as filtered_data');
            $totalRecords = $this->db->count_all_results();

            // Rebuild query for actual data
            $this->db->select('alarm_logs.*, devices.name AS device_name');
            $this->db->from('alarm_logs');
            $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');

            // Re-apply all filters
            if ($range && $range !== 'all') {
                switch ($range) {
                    case 'today':
                        $this->db->where('DATE(alarm_logs.created_at)', date('Y-m-d'));
                        break;
                    case 'yesterday':
                        $this->db->where('DATE(alarm_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                        break;
                    case 'last_7_days':
                        $this->db->where('DATE(alarm_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                        break;
                    case 'last_30_days':
                        $this->db->where('DATE(alarm_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                        break;
                    case 'custom':
                        if ($startDate && $endDate) {
                            $this->db->where('DATE(alarm_logs.created_at) >=', $startDate);
                            $this->db->where('DATE(alarm_logs.created_at) <=', $endDate);
                        }
                        break;
                }
            }

            if ($device_filter && $device_filter !== 'all') {
                $this->db->where('alarm_logs.device_id', $device_filter);
            }
            if ($severity_filter && $severity_filter !== 'all') {
                $this->db->where('alarm_logs.severity', $severity_filter);
            }
            if ($status_filter && $status_filter !== 'all') {
                $this->db->where('alarm_logs.status', $status_filter);
            }

            if ($search) {
                $this->db->group_start();
                $this->db->like('devices.name', $search);
                $this->db->or_like('alarm_logs.metric_key', $search);
                $this->db->or_like('alarm_logs.message', $search);
                $this->db->or_like('alarm_logs.severity', $search);
                $this->db->group_end();
            }

            // Apply ordering and pagination
            $this->db->order_by('alarm_logs.created_at', 'desc');
            $this->db->limit($pageSize, $offset);
            $query = $this->db->get();
            $logs = $query->result();

            $totalPages = ceil($totalRecords / $pageSize);

            // Debug log
            log_message('debug', 'Alarm logs query - Range: ' . $range . ', Total Records: ' . $totalRecords . ', Found: ' . count($logs));

            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'totalRecords' => $totalRecords,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'pageSize' => $pageSize
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            log_message('error', 'Alarmlogs get_logs error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    public function export_preview()
    {
        // Get filter parameters
        $range = $this->input->get('range');
        $startDate = $this->input->get('startDate');
        $endDate = $this->input->get('endDate');
        $device_filter = $this->input->get('device_filter');
        $severity_filter = $this->input->get('severity_filter');
        $status_filter = $this->input->get('status_filter');
        $search = $this->input->get('search');

        $data['logs'] = $this->Alarmlogs_model->get_logs_for_export(
            $range, $startDate, $endDate, $device_filter, $severity_filter, $status_filter, $search
        );
        
        $data['filters'] = [
            'range' => $range,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'device_filter' => $device_filter,
            'severity_filter' => $severity_filter,
            'status_filter' => $status_filter,
            'search' => $search
        ];

        $this->load->view('export_preview', $data);
    }

    public function export_data()
    {
        try {
            // Set charset for PostgreSQL connection
            $this->db->query("SET NAMES 'UTF8'");
            
            $format = $this->input->get('format'); // csv, excel, pdf
            $range = $this->input->get('range');
            $startDate = $this->input->get('startDate');
            $endDate = $this->input->get('endDate');
            $device_filter = $this->input->get('device_filter');
            $severity_filter = $this->input->get('severity_filter');
            $status_filter = $this->input->get('status_filter');
            $search = $this->input->get('search');
            $download = $this->input->get('download');

            $logs = $this->Alarmlogs_model->get_logs_for_export(
                $range, $startDate, $endDate, $device_filter, $severity_filter, $status_filter, $search
            );

            $filename = 'alarm_logs_' . date('Y-m-d_H-i-s');

            switch ($format) {
                case 'csv':
                    $this->export_csv($logs, $filename);
                    break;
                case 'excel':
                    $this->export_excel($logs, $filename);
                    break;
                case 'pdf':
                    $this->export_pdf($logs, $filename, $download, [
                        'range' => $range,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'device_filter' => $device_filter,
                        'severity_filter' => $severity_filter,
                        'status_filter' => $status_filter,
                        'search' => $search,
                    ]);
                    break;
                default:
                    redirect('alarmlogs');
            }
        } catch (Exception $e) {
            log_message('error', 'Alarmlogs export error: ' . $e->getMessage());
            redirect('alarmlogs');
        }
    }

    private function export_csv($logs, $filename)
    {
        // Clear any output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers
        fputcsv($output, ['Date Time', 'Device', 'Metric', 'Severity', 'Message']);

        // Write data
        foreach ($logs as $log) {
            fputcsv($output, [
                date('d/m/Y H:i:s', strtotime($log->created_at)),
                $log->device_name,
                $log->metric_key,
                $log->severity,
                $log->message
            ]);
        }

        fclose($output);
        exit();
    }

    private function export_excel($logs, $filename)
    {
        // Clear any output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Load PhpSpreadsheet
        //require_once APPPATH . 'third_party/PhpSpreadsheet/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = ['Date Time', 'Device', 'Metric', 'Severity', 'Message'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Add data
        $row = 2;
        foreach ($logs as $log) {
            $sheet->setCellValue('A' . $row, date('d/m/Y H:i:s', strtotime($log->created_at)));
            $sheet->setCellValue('B' . $row, $log->device_name);
            $sheet->setCellValue('C' . $row, $log->metric_key);
            $sheet->setCellValue('D' . $row, $log->severity);
            $sheet->setCellValue('E' . $row, $log->message);
            $row++;
        }

        // Create Excel file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        // Set headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        $writer->save('php://output');
        exit();
    }

    private function alarmlogs_period_label($filters)
    {
        $range = $filters['range'] ?? '';
        $startDate = $filters['startDate'] ?? '';
        $endDate = $filters['endDate'] ?? '';

        switch ($range) {
            case 'today':
                $startDate = $endDate = date('Y-m-d');
                break;
            case 'yesterday':
                $startDate = $endDate = date('Y-m-d', strtotime('-1 day'));
                break;
            case 'last_7_days':
                $startDate = date('Y-m-d', strtotime('-7 days'));
                $endDate = date('Y-m-d');
                break;
            case 'last_30_days':
                $startDate = date('Y-m-d', strtotime('-30 days'));
                $endDate = date('Y-m-d');
                break;
        }

        if ($startDate && $endDate) {
            return 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($startDate)) . ' ถึง ' . date('d/m/Y', strtotime($endDate));
        }

        return '';
    }

    private function export_pdf($logs, $filename, $download = false, $filters = [])
    {
        try {
            require_once APPPATH . 'third_party/tcpdf/tcpdf.php';

            if (ob_get_level()) {
                ob_end_clean();
            }

            $pdf = new class('L', 'mm', 'A4', true, 'UTF-8', false) extends TCPDF {
                public function Header() {}
                public function Footer() {
                    $this->SetY(-15);
                    $this->SetFont('thsarabunnew', '', 10);
                    $pageWidth = $this->getPageWidth() - 30;
                    $cellWidth = $pageWidth / 3;
                    $this->Cell($cellWidth, 10, 'วันที่สร้าง: ' . date('d/m/Y H:i'), 0, false, 'L', 0, '', 0, false, 'T', 'M');
                    $this->Cell($cellWidth, 10, 'This report generated by ConnectPlus JConnect', 0, false, 'C', 0, '', 0, false, 'T', 'M');
                    $this->Cell($cellWidth, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
                }
            };

            $pdf->SetCreator('JConnect');
            $pdf->SetAuthor('JConnect');
            $pdf->SetTitle('รายงานบันทึก Alarm');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->SetDefaultMonospacedFont('courier');
            $pdf->SetFont('thsarabunnew', '', 16);
            $pdf->SetMargins(15, 12, 15);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(12);
            $pdf->SetAutoPageBreak(true, 18);
            $pdf->AddPage();

            $period_label = $this->alarmlogs_period_label($filters);
            ob_start();
            include APPPATH . 'modules/alarmlogs/views/pdf_template.php';
            $html = ob_get_clean();

            $pdf->writeHTML($html, true, false, true, false, '');

            if ($download) {
                $pdf->Output($filename . '.pdf', 'D');
            } else {
                $pdf->Output($filename . '.pdf', 'I');
            }
        } catch (Exception $e) {
            error_log('PDF export error: ' . $e->getMessage());
            error_log('PDF export error trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    public function acknowledge()
    {
        try {
            $alarm_id = $this->input->post('alarm_id');
            $note = $this->input->post('note');
            $user_id = $this->session->userdata('user_id');

            if (!$alarm_id) {
                throw new Exception('Alarm ID is required');
            }

            // Update alarm status
            $this->db->where('id', $alarm_id);
            $this->db->update('alarm_logs', [
                'status' => 'acknowledged',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Log the acknowledgment
            $this->load->model('Eventlogs_model');
            $this->Eventlogs_model->log_event(
                'acknowledge',
                'alarm_logs',
                $alarm_id,
                "Alarm acknowledged by user",
                [
                    'alarm_id' => $alarm_id,
                    'note' => $note,
                    'previous_status' => 'active',
                    'new_status' => 'acknowledged'
                ]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Alarm acknowledged successfully'
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function resolve()
    {
        try {
            $alarm_id = $this->input->post('alarm_id');
            $resolution_note = $this->input->post('resolution_note');
            $user_id = $this->session->userdata('user_id');

            if (!$alarm_id) {
                throw new Exception('Alarm ID is required');
            }

            // Update alarm status
            $this->db->where('id', $alarm_id);
            $this->db->update('alarm_logs', [
                'status' => 'resolved',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Log the resolution
            $this->load->model('Eventlogs_model');
            $this->Eventlogs_model->log_event(
                'resolve',
                'alarm_logs',
                $alarm_id,
                "Alarm resolved by user",
                [
                    'alarm_id' => $alarm_id,
                    'resolution_note' => $resolution_note,
                    'previous_status' => 'acknowledged',
                    'new_status' => 'resolved'
                ]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Alarm resolved successfully'
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function get_alarm_detail()
    {
        try {
            $alarm_id = $this->input->get('alarm_id');

            if (!$alarm_id) {
                throw new Exception('Alarm ID is required');
            }

            $this->db->select('alarm_logs.*, devices.name AS device_name, devices.device_information');
            $this->db->from('alarm_logs');
            $this->db->join('devices', 'devices.id = alarm_logs.device_id', 'left');
            $this->db->where('alarm_logs.id', $alarm_id);
            $query = $this->db->get();
            $alarm = $query->row();

            if (!$alarm) {
                throw new Exception('Alarm not found');
            }

            // Get related alarms for the same device and metric
            $this->db->select('alarm_logs.*');
            $this->db->from('alarm_logs');
            $this->db->where('device_id', $alarm->device_id);
            $this->db->where('metric_key', $alarm->metric_key);
            $this->db->where('id !=', $alarm_id);
            $this->db->order_by('created_at', 'DESC');
            $this->db->limit(5);
            $related_alarms = $this->db->get()->result();

            // Get alarm dispatch history
            $this->db->select('alarm_log_dispatch.*, notification_channels.name as channel_name');
            $this->db->from('alarm_log_dispatch');
            $this->db->join('notification_channels', 'notification_channels.id = alarm_log_dispatch.channel_id', 'left');
            $this->db->where('alarm_log_dispatch.alarm_log_id', $alarm_id);
            $this->db->order_by('alarm_log_dispatch.created_at', 'DESC');
            $dispatch_history = $this->db->get()->result();

            echo json_encode([
                'success' => true,
                'alarm' => $alarm,
                'related_alarms' => $related_alarms,
                'dispatch_history' => $dispatch_history
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function bulk_acknowledge()
    {
        try {
            $alarm_ids = $this->input->post('alarm_ids');
            $note = $this->input->post('note');
            $user_id = $this->session->userdata('user_id');

            if (!$alarm_ids || !is_array($alarm_ids)) {
                throw new Exception('Alarm IDs are required');
            }

            $success_count = 0;
            $failed_count = 0;

            foreach ($alarm_ids as $alarm_id) {
                try {
                    // Update alarm status
                    $this->db->where('id', $alarm_id);
                    $this->db->where('status', 'active'); // Only acknowledge active alarms
                    $result = $this->db->update('alarm_logs', [
                        'status' => 'acknowledged',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    if ($this->db->affected_rows() > 0) {
                        $success_count++;
                        
                        // Log the acknowledgment
                        $this->load->model('Eventlogs_model');
                        $this->Eventlogs_model->log_event(
                            'acknowledge',
                            'alarm_logs',
                            $alarm_id,
                            "Alarm acknowledged in bulk operation",
                            [
                                'alarm_id' => $alarm_id,
                                'note' => $note,
                                'bulk_operation' => true
                            ]
                        );
                    } else {
                        $failed_count++;
                    }
                } catch (Exception $e) {
                    $failed_count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Bulk acknowledge completed. Success: {$success_count}, Failed: {$failed_count}",
                'success_count' => $success_count,
                'failed_count' => $failed_count
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function bulk_resolve()
    {
        try {
            $alarm_ids = $this->input->post('alarm_ids');
            $resolution_note = $this->input->post('resolution_note');
            $user_id = $this->session->userdata('user_id');

            if (!$alarm_ids || !is_array($alarm_ids)) {
                throw new Exception('Alarm IDs are required');
            }

            $success_count = 0;
            $failed_count = 0;

            foreach ($alarm_ids as $alarm_id) {
                try {
                    // Update alarm status
                    $this->db->where('id', $alarm_id);
                    $this->db->where_in('status', ['active', 'acknowledged']); // Only resolve active/acknowledged alarms
                    $result = $this->db->update('alarm_logs', [
                        'status' => 'resolved',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    if ($this->db->affected_rows() > 0) {
                        $success_count++;
                        
                        // Log the resolution
                        $this->load->model('Eventlogs_model');
                        $this->Eventlogs_model->log_event(
                            'resolve',
                            'alarm_logs',
                            $alarm_id,
                            "Alarm resolved in bulk operation",
                            [
                                'alarm_id' => $alarm_id,
                                'resolution_note' => $resolution_note,
                                'bulk_operation' => true
                            ]
                        );
                    } else {
                        $failed_count++;
                    }
                } catch (Exception $e) {
                    $failed_count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Bulk resolve completed. Success: {$success_count}, Failed: {$failed_count}",
                'success_count' => $success_count,
                'failed_count' => $failed_count
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
