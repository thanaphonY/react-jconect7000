<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Controllogs extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        isLogin();
        $this->load->model('Controllogs_model');
        $this->load->helper('language');
        $this->lang->load('controllogs', $this->session->userdata('site_lang') ?: 'english');
    }

    private function can_read()
    {
        return CheckPermission('controllogs', 'own_read')
            || CheckPermission('controllogs', 'all_read')
            || CheckPermission('control', 'own_read')
            || CheckPermission('control', 'all_read');
    }

    private function require_read($json = false)
    {
        if ($this->can_read()) {
            return;
        }
        if ($json) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => lang('controllogs_permission_denied')]);
            exit;
        }
        redirect(base_url());
    }

    private function filters_from_request($fromPost = true)
    {
        $src = $fromPost ? 'post' : 'get';
        return [
            'range' => $this->input->$src('range') ?: 'last_30_days',
            'startDate' => $this->input->$src('startDate'),
            'endDate' => $this->input->$src('endDate'),
            'group_filter' => $this->input->$src('group_filter'),
            'device_filter' => $this->input->$src('device_filter'),
            'source_filter' => $this->input->$src('source_filter'),
            'result_filter' => $this->input->$src('result_filter'),
            'search' => $this->input->$src('search') ?: '',
        ];
    }

    public function index()
    {
        $this->require_read();
        $empty = [];
        try {
            $data = [
                'groups' => $this->Controllogs_model->get_unique_groups(),
                'devices' => $this->Controllogs_model->get_unique_devices(),
                'sources' => $this->Controllogs_model->get_unique_sources(),
                'results' => $this->Controllogs_model->get_unique_results(),
            ];
        } catch (Exception $e) {
            log_message('error', 'Controllogs index filters: ' . $e->getMessage());
            $data = [
                'groups' => $empty,
                'devices' => $empty,
                'sources' => $empty,
                'results' => $empty,
            ];
        }
        $this->load->view('include/header');
        $this->load->view('index', $data);
        $this->load->view('include/footer');
    }

    public function get_logs()
    {
        $this->require_read(true);
        header('Content-Type: application/json');
        try {
            $page = max(1, intval($this->input->post('page')) ?: 1);
            $pageSize = intval($this->input->post('pageSize')) ?: 10;
            if ($pageSize < 1) {
                $pageSize = 10;
            }
            if ($pageSize > 100) {
                $pageSize = 100;
            }
            $filters = $this->filters_from_request(true);
            $offset = ($page - 1) * $pageSize;
            $totalRecords = $this->Controllogs_model->count_logs($filters);
            $logs = $this->Controllogs_model->get_logs($filters, $pageSize, $offset);
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'totalRecords' => $totalRecords,
                'totalPages' => $pageSize > 0 ? (int) ceil($totalRecords / $pageSize) : 1,
                'currentPage' => $page,
                'pageSize' => $pageSize,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
            log_message('error', 'Controllogs get_logs: ' . $e->getMessage());
        }
    }

    public function get_statistics()
    {
        $this->require_read(true);
        header('Content-Type: application/json');
        try {
            $filters = $this->filters_from_request(false);
            echo json_encode([
                'success' => true,
                'stats' => $this->Controllogs_model->get_statistics($filters),
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function export_preview()
    {
        $this->require_read();
        $filters = $this->filters_from_request(false);
        $data['logs'] = $this->Controllogs_model->get_logs_for_export($filters);
        $data['filters'] = $filters;
        $this->load->view('export_preview', $data);
    }

    public function export_data()
    {
        $this->require_read();
        try {
            $this->db->query("SET NAMES 'UTF8'");
            $format = $this->input->get('format');
            $download = $this->input->get('download');
            $filters = $this->filters_from_request(false);
            $logs = $this->Controllogs_model->get_logs_for_export($filters);
            $filename = 'control_logs_' . date('Y-m-d_H-i-s');

            switch ($format) {
                case 'csv':
                    $this->export_csv($logs, $filename);
                    break;
                case 'excel':
                    $this->export_excel($logs, $filename);
                    break;
                case 'pdf':
                    $this->export_pdf($logs, $filename, $download, $filters);
                    break;
                default:
                    redirect('controllogs');
            }
        } catch (Exception $e) {
            log_message('error', 'Controllogs export error: ' . $e->getMessage());
            redirect('controllogs');
        }
    }

    private function format_value_label($value)
    {
        $v = strtolower(trim((string) $value));
        if (in_array($v, ['1', 'on', 'true'], true)) {
            return 'ON';
        }
        if (in_array($v, ['0', 'off', 'false'], true)) {
            return 'OFF';
        }
        return $value === null || $value === '' ? '-' : (string) $value;
    }

    private function format_source_label($log)
    {
        $source = (string) ($log->source ?? '-');
        $extra = [];
        if (!empty($log->sequence_name)) {
            $extra[] = $log->sequence_name;
        }
        if (!empty($log->rule_name)) {
            $extra[] = $log->rule_name;
        }
        return $extra ? $source . ' (' . implode(' / ', $extra) . ')' : $source;
    }

    private function export_row($log)
    {
        return [
            date('Y-m-d H:i:s', strtotime($log->created_at)),
            $log->command_name ?: ('#' . ($log->command_id ?? '')),
            $log->device_name ?: '-',
            $log->group_name ?: lang('ungrouped'),
            $this->format_source_label($log),
            $this->format_value_label($log->requested_value ?? ''),
            $log->result ?: '-',
            $log->user_name ?: '-',
            $log->error ?: '',
        ];
    }

    private function export_headers()
    {
        return ['Date Time', 'Command', 'Device', 'Group', 'Source', 'Value', 'Result', 'User', 'Error'];
    }

    private function export_csv($logs, $filename)
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, $this->export_headers());
        foreach ($logs as $log) {
            fputcsv($output, $this->export_row($log));
        }
        fclose($output);
        exit();
    }

    private function export_excel($logs, $filename)
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $this->export_headers();
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $row = 2;
        foreach ($logs as $log) {
            $values = $this->export_row($log);
            $sheet->fromArray($values, null, 'A' . $row);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        exit();
    }

    private function period_label($filters)
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
                public function Footer()
                {
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
            $pdf->SetTitle('รายงานบันทึกการควบคุม');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->SetDefaultMonospacedFont('courier');
            $pdf->SetFont('thsarabunnew', '', 16);
            $pdf->SetMargins(15, 12, 15);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(12);
            $pdf->SetAutoPageBreak(true, 18);
            $pdf->AddPage();

            $period_label = $this->period_label($filters);
            ob_start();
            include APPPATH . 'modules/controllogs/views/pdf_template.php';
            $html = ob_get_clean();

            $pdf->writeHTML($html, true, false, true, false, '');

            if ($download) {
                $pdf->Output($filename . '.pdf', 'D');
            } else {
                $pdf->Output($filename . '.pdf', 'I');
            }
        } catch (Exception $e) {
            log_message('error', 'Controllogs PDF export error: ' . $e->getMessage());
            throw $e;
        }
    }
}
