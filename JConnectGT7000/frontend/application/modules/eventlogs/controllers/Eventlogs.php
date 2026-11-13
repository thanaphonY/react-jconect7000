<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Eventlogs extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        isLogin();
        site_require_global();
        $userDetails = $this->session->get_userdata()['user_details'][0] ?? null;
        $username = $userDetails ? $userDetails->name : 'unknown';
        $this->user_id = $userDetails ? $userDetails->ia_users_id : 0;

        $this->load->model('Eventlogs_model');
        $this->load->helper('url');
        $this->load->helper('language');
        $this->lang->load('eventlogs', $this->session->userdata('site_lang'));
    }

    public function index()
    {
        // Get filter options for dropdowns
        $data['users'] = $this->Eventlogs_model->get_unique_users();
        $data['action_types'] = $this->Eventlogs_model->get_unique_action_types();
        $data['entity_types'] = $this->Eventlogs_model->get_unique_entity_types();

        $this->load->view('include/header');
        $this->load->view('index', $data);
        $this->load->view('include/footer');
    }

    public function datatable()
    {
        try {
            $start = intval($this->input->post('start'));
            $length = intval($this->input->post('length'));
            $search = $this->input->post('search')['value'] ?? '';
            $order = $this->input->post('order')[0] ?? null;
            $range = $this->input->post('range') ?: 'last_30_days';
            $startDate = $this->input->post('startDate');
            $endDate = $this->input->post('endDate');
            $user_filter = $this->input->post('user_filter');
            $action_filter = $this->input->post('action_filter');
            $entity_filter = $this->input->post('entity_filter');
            $status_filter = $this->input->post('status_filter'); // success/fail

            // Get total records count first
            $totalRecords = $this->db->count_all('event_logs');

            // Build query for filtered data
            $this->db->select('event_logs.*, ia_users.name AS user_name');
            $this->db->from('event_logs');
            $this->db->join('ia_users', 'ia_users.ia_users_id = event_logs.user_id', 'left');

            // Apply date range filter
            if ($range) {
                switch ($range) {
                    case 'today':
                        $this->db->where('DATE(event_logs.created_at)', date('Y-m-d'));
                        break;
                    case 'yesterday':
                        $this->db->where('DATE(event_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                        break;
                    case 'last_7_days':
                        $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                        break;
                    case 'last_30_days':
                        $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                        break;
                    case 'custom':
                        if ($startDate && $endDate) {
                            $this->db->where('DATE(event_logs.created_at) >=', $startDate);
                            $this->db->where('DATE(event_logs.created_at) <=', $endDate);
                        }
                        break;
                }
            }

            // Apply additional filters
            if ($user_filter && $user_filter !== 'all') {
                $this->db->where('event_logs.user_id', $user_filter);
            }
            if ($action_filter && $action_filter !== 'all') {
                $this->db->where('event_logs.action_type', $action_filter);
            }
            if ($entity_filter && $entity_filter !== 'all') {
                $this->db->where('event_logs.entity_type', $entity_filter);
            }
            if ($status_filter && $status_filter !== 'all') {
                // Check metadata for success/fail status
                if ($status_filter === 'success') {
                    $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"success\"%'");
                } elseif ($status_filter === 'fail') {
                    $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"fail\"%'");
                }
            }

            // Apply search
            if ($search) {
                $this->db->group_start();
                $this->db->like('event_logs.message', $search);
                $this->db->or_like('event_logs.action_type', $search);
                $this->db->or_like('event_logs.entity_type', $search);
                $this->db->or_like('ia_users.name', $search);
                $this->db->group_end();
            }

            // Get filtered count before applying limit
            $filteredQuery = $this->db->get_compiled_select();
            $this->db->reset_query();

            $this->db->from('(' . $filteredQuery . ') as filtered_data');
            $recordsFiltered = $this->db->count_all_results();

            // Rebuild query for actual data
            $this->db->select('event_logs.*, ia_users.name AS user_name');
            $this->db->from('event_logs');
            $this->db->join('ia_users', 'ia_users.ia_users_id = event_logs.user_id', 'left');

            // Re-apply all filters
            if ($range) {
                switch ($range) {
                    case 'today':
                        $this->db->where('DATE(event_logs.created_at)', date('Y-m-d'));
                        break;
                    case 'yesterday':
                        $this->db->where('DATE(event_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                        break;
                    case 'last_7_days':
                        $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                        break;
                    case 'last_30_days':
                        $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                        break;
                    case 'custom':
                        if ($startDate && $endDate) {
                            $this->db->where('DATE(event_logs.created_at) >=', $startDate);
                            $this->db->where('DATE(event_logs.created_at) <=', $endDate);
                        }
                        break;
                }
            }

            if ($user_filter && $user_filter !== 'all') {
                $this->db->where('event_logs.user_id', $user_filter);
            }
            if ($action_filter && $action_filter !== 'all') {
                $this->db->where('event_logs.action_type', $action_filter);
            }
            if ($entity_filter && $entity_filter !== 'all') {
                $this->db->where('event_logs.entity_type', $entity_filter);
            }
            if ($status_filter && $status_filter !== 'all') {
                if ($status_filter === 'success') {
                    $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"success\"%'");
                } elseif ($status_filter === 'fail') {
                    $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"fail\"%'");
                }
            }

            if ($search) {
                $this->db->group_start();
                $this->db->like('event_logs.message', $search);
                $this->db->or_like('event_logs.action_type', $search);
                $this->db->or_like('event_logs.entity_type', $search);
                $this->db->or_like('ia_users.name', $search);
                $this->db->group_end();
            }

            // Apply ordering
            if ($order && isset($order['column']) && isset($order['dir'])) {
                $columns = ['created_at', 'user_name', 'action_type', 'entity_type', 'message'];
                $column_index = intval($order['column']);
                $column_name = $columns[$column_index] ?? 'created_at';
                $column_dir = $order['dir'] ?? 'desc';

                if ($column_name === 'user_name') {
                    $this->db->order_by('ia_users.name', $column_dir);
                } else {
                    $this->db->order_by('event_logs.' . $column_name, $column_dir);
                }
            } else {
                $this->db->order_by('event_logs.created_at', 'desc');
            }

            // Apply pagination
            $this->db->limit($length, $start);
            $query = $this->db->get();
            $data = $query->result();

            echo json_encode([
                "draw" => intval($this->input->post('draw')),
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $recordsFiltered,
                "data" => $data
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "error" => true,
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
            log_message('error', 'Eventlogs datatable error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    public function get_logs()
    {
        try {
            $page = intval($this->input->post('page')) ?: 1;
            $pageSize = intval($this->input->post('pageSize')) ?: 25;
            $search = $this->input->post('search') ?: '';
            $range = $this->input->post('range') ?: 'last_30_days';
            $startDate = $this->input->post('startDate');
            $endDate = $this->input->post('endDate');
            $user_filter = $this->input->post('user_filter');
            $action_filter = $this->input->post('action_filter');
            $entity_filter = $this->input->post('entity_filter');
            $status_filter = $this->input->post('status_filter');

            // Calculate offset
            $offset = ($page - 1) * $pageSize;

            // Set charset for PostgreSQL connection
            $this->db->query("SET NAMES 'UTF8'");

            // Build query
            $this->db->select('event_logs.*, ia_users.name AS user_name');
            $this->db->from('event_logs');
            $this->db->join('ia_users', 'ia_users.ia_users_id = event_logs.user_id', 'left');

            // Apply date range filter
            if ($range) {
                switch ($range) {
                    case 'today':
                        $this->db->where('DATE(event_logs.created_at)', date('Y-m-d'));
                        break;
                    case 'yesterday':
                        $this->db->where('DATE(event_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                        break;
                    case 'last_7_days':
                        $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                        break;
                    case 'last_30_days':
                        $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                        break;
                    case 'custom':
                        if ($startDate && $endDate) {
                            $this->db->where('DATE(event_logs.created_at) >=', $startDate);
                            $this->db->where('DATE(event_logs.created_at) <=', $endDate);
                        }
                        break;
                }
            }

            // Apply additional filters
            if ($user_filter && $user_filter !== 'all') {
                $this->db->where('event_logs.user_id', $user_filter);
            }
            if ($action_filter && $action_filter !== 'all') {
                $this->db->where('event_logs.action_type', $action_filter);
            }
            if ($entity_filter && $entity_filter !== 'all') {
                $this->db->where('event_logs.entity_type', $entity_filter);
            }
            if ($status_filter && $status_filter !== 'all') {
                if ($status_filter === 'success') {
                    $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"success\"%'");
                } elseif ($status_filter === 'fail') {
                    $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"fail\"%'");
                }
            }

            // Apply search
            if ($search) {
                $this->db->group_start();
                $this->db->like('event_logs.message', $search);
                $this->db->or_like('event_logs.action_type', $search);
                $this->db->or_like('event_logs.entity_type', $search);
                $this->db->or_like('ia_users.name', $search);
                $this->db->group_end();
            }

            // Get total count for pagination
            $totalQuery = $this->db->get_compiled_select();
            $this->db->reset_query();

            $this->db->from('(' . $totalQuery . ') as filtered_data');
            $totalRecords = $this->db->count_all_results();

            // Rebuild query for actual data
            $this->db->select('event_logs.*, ia_users.name AS user_name');
            $this->db->from('event_logs');
            $this->db->join('ia_users', 'ia_users.ia_users_id = event_logs.user_id', 'left');

            // Re-apply all filters
            if ($range) {
                switch ($range) {
                    case 'today':
                        $this->db->where('DATE(event_logs.created_at)', date('Y-m-d'));
                        break;
                    case 'yesterday':
                        $this->db->where('DATE(event_logs.created_at)', date('Y-m-d', strtotime('-1 day')));
                        break;
                    case 'last_7_days':
                        $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-7 days')));
                        break;
                    case 'last_30_days':
                        $this->db->where('DATE(event_logs.created_at) >=', date('Y-m-d', strtotime('-30 days')));
                        break;
                    case 'custom':
                        if ($startDate && $endDate) {
                            $this->db->where('DATE(event_logs.created_at) >=', $startDate);
                            $this->db->where('DATE(event_logs.created_at) <=', $endDate);
                        }
                        break;
                }
            }

            if ($user_filter && $user_filter !== 'all') {
                $this->db->where('event_logs.user_id', $user_filter);
            }
            if ($action_filter && $action_filter !== 'all') {
                $this->db->where('event_logs.action_type', $action_filter);
            }
            if ($entity_filter && $entity_filter !== 'all') {
                $this->db->where('event_logs.entity_type', $entity_filter);
            }
            if ($status_filter && $status_filter !== 'all') {
                if ($status_filter === 'success') {
                    $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"success\"%'");
                } elseif ($status_filter === 'fail') {
                    $this->db->where("event_logs.metadata::text LIKE '%\"status\":\"fail\"%'");
                }
            }

            if ($search) {
                $this->db->group_start();
                $this->db->like('event_logs.message', $search);
                $this->db->or_like('event_logs.action_type', $search);
                $this->db->or_like('event_logs.entity_type', $search);
                $this->db->or_like('ia_users.name', $search);
                $this->db->group_end();
            }

            // Apply ordering and pagination
            $this->db->order_by('event_logs.created_at', 'desc');
            $this->db->limit($pageSize, $offset);
            $query = $this->db->get();
            $logs = $query->result();

            $totalPages = ceil($totalRecords / $pageSize);

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
            log_message('error', 'Eventlogs get_logs error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    public function get_log_detail($id)
    {
        $log = $this->Eventlogs_model->get_log_by_id($id);
        if ($log) {
            echo json_encode(['success' => true, 'data' => $log]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Log not found']);
        }
    }

    public function get_statistics()
    {
        $range = $this->input->get('range') ?? 'last_30_days';
        $stats = $this->Eventlogs_model->get_log_statistics($range);

        if ($stats) {
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Unable to load statistics'
            ]);
        }
    }

    public function export_preview()
    {
        // Get filter parameters (same as datatable)
        $range = $this->input->get('range');
        $startDate = $this->input->get('startDate');
        $endDate = $this->input->get('endDate');
        $user_filter = $this->input->get('user_filter');
        $action_filter = $this->input->get('action_filter');
        $entity_filter = $this->input->get('entity_filter');
        $status_filter = $this->input->get('status_filter');
        $search = $this->input->get('search'); // Add search parameter

        $data['logs'] = $this->Eventlogs_model->get_logs_for_export(
            $range,
            $startDate,
            $endDate,
            $user_filter,
            $action_filter,
            $entity_filter,
            $status_filter,
            $search
        );

        $data['filters'] = [
            'range' => $range,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'user_filter' => $user_filter,
            'action_filter' => $action_filter,
            'entity_filter' => $entity_filter,
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
            $user_filter = $this->input->get('user_filter');
            $action_filter = $this->input->get('action_filter');
            $entity_filter = $this->input->get('entity_filter');
            $status_filter = $this->input->get('status_filter');
            $search = $this->input->get('search'); // Add search parameter
            $download = $this->input->get('download'); // Check if download is requested

            $logs = $this->Eventlogs_model->get_logs_for_export(
                $range,
                $startDate,
                $endDate,
                $user_filter,
                $action_filter,
                $entity_filter,
                $status_filter,
                $search
            );

            $filename = 'event_logs_' . date('Y-m-d_H-i-s');

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
                        'user_filter' => $user_filter,
                        'action_filter' => $action_filter,
                        'entity_filter' => $entity_filter,
                        'status_filter' => $status_filter,
                        'search' => $search,
                    ]);
                    break;
                default:
                    redirect('eventlogs');
            }
        } catch (Exception $e) {
            // Log error for debugging
            error_log('Export error: ' . $e->getMessage());
            error_log('Export error trace: ' . $e->getTraceAsString());

            // Show user-friendly error
            echo "Export failed: " . $e->getMessage();
        }
    }

    private function export_csv($logs, $filename)
    {
        // Clear any output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Headers
        fputcsv($output, [
            'Date Time',
            'User',
            'Action',
            'Section',
            'Message',
            'IP Address'
        ]);

        // Data rows
        foreach ($logs as $log) {
            $metadata = json_decode($log->metadata, true);

            // Format date properly
            $created_at = date('Y-m-d H:i:s', strtotime($log->created_at));

            fputcsv($output, [
                $created_at,
                $log->user_name ?? 'Unknown',
                ucfirst($log->action_type),
                ucfirst($log->entity_type),
                $log->message,
                $metadata['ip_address'] ?? '-'
            ]);
        }

        fclose($output);
        exit();
    }

    private function export_excel($logs, $filename)
    {
        try {
            // PhpSpreadsheet is loaded via Composer autoload

            // Clear any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Set headers first to prevent output issues
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

            // Create new spreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = [
                'A1' => 'Date Time',
                'B1' => 'User',
                'C1' => 'Action',
                'D1' => 'Section',
                'E1' => 'Message',
                'F1' => 'IP Address'
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            // Add data
            $row = 2;
            foreach ($logs as $log) {
                $metadata = json_decode($log->metadata, true);

                // Format date properly
                $created_at = date('Y-m-d H:i:s', strtotime($log->created_at));

                $sheet->setCellValue('A' . $row, $created_at);
                $sheet->setCellValue('B' . $row, $log->user_name ?? 'Unknown');
                $sheet->setCellValue('C' . $row, ucfirst($log->action_type));
                $sheet->setCellValue('D' . $row, ucfirst($log->entity_type));
                $sheet->setCellValue('E' . $row, $log->message);
                $sheet->setCellValue('F' . $row, $metadata['ip_address'] ?? '-');
                $row++;
            }

            // Create writer and output
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');

            // Clean up
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            exit();
        } catch (Exception $e) {
            error_log('Excel export error: ' . $e->getMessage());
            error_log('Excel export error trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    private function eventlogs_period_label($filters)
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
            $pdf->SetTitle('รายงานบันทึกเหตุการณ์');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->SetDefaultMonospacedFont('courier');
            $pdf->SetFont('thsarabunnew', '', 16);
            $pdf->SetMargins(15, 12, 15);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(12);
            $pdf->SetAutoPageBreak(true, 18);
            $pdf->AddPage();

            $period_label = $this->eventlogs_period_label($filters);
            ob_start();
            include APPPATH . 'modules/eventlogs/views/pdf_template.php';
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

    private function format_additional_info($metadata)
    {
        if (!$metadata) return '-';

        $info = [];
        if (isset($metadata['old_values'])) $info[] = 'Old: ' . json_encode($metadata['old_values']);
        if (isset($metadata['new_values'])) $info[] = 'New: ' . json_encode($metadata['new_values']);
        if (isset($metadata['affected_records'])) $info[] = 'Records: ' . $metadata['affected_records'];

        return implode(' | ', $info);
    }
}
