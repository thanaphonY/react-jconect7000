<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ReportScheduler {
    
    protected $CI;
    
    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->model('Reports_model');
    }
    
    /**
     * Process scheduled reports
     */
    public function process_scheduled_reports() {
        $scheduled_reports = $this->get_due_reports();
        
        foreach ($scheduled_reports as $report) {
            try {
                $this->execute_scheduled_report($report);
                $this->update_next_run($report->id);
            } catch (Exception $e) {
                log_message('error', 'Failed to execute scheduled report ' . $report->id . ': ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Get reports that are due for execution
     */
    private function get_due_reports() {
        $this->CI->db->select('*');
        $this->CI->db->from('scheduled_reports');
        $this->CI->db->where('is_active', true);
        $this->CI->db->where('next_run <=', date('Y-m-d H:i:s'));
        return $this->CI->db->get()->result();
    }
    
    /**
     * Execute a scheduled report
     */
    private function execute_scheduled_report($report) {
        // Get report definition
        $definition = $this->CI->db->get_where('report_definitions', ['id' => $report->report_definition_id])->row();
        if (!$definition) {
            throw new Exception('Report definition not found');
        }
        
        // Generate report
        $report_data = $this->CI->Reports_model->generate_custom_report(
            $report->report_definition_id,
            json_decode($report->schedule_config, true),
            $report->export_format
        );
        
        // Export report
        $file_path = $this->CI->Reports_model->export_report(
            $report_data,
            $report->export_format,
            $definition->template_id
        );
        
        // Send to recipients
        $this->send_report_to_recipients($report, $file_path);
        
        // Log execution
        $this->log_execution($report->id, 'success', $file_path);
    }
    
    /**
     * Send report to recipients
     */
    private function send_report_to_recipients($report, $file_path) {
        $recipients = json_decode($report->recipients, true);
        
        if (empty($recipients)) {
            return;
        }
        
        $this->CI->load->library('email');
        
        $config = [
            'protocol' => 'smtp',
            'smtp_host' => 'localhost',
            'smtp_port' => 587,
            'smtp_user' => '',
            'smtp_pass' => '',
            'mailtype' => 'html',
            'charset' => 'utf-8'
        ];
        
        $this->CI->email->initialize($config);
        $this->CI->email->from('noreply@jconnect.com', 'JConnect System');
        $this->CI->email->to($recipients);
        $this->CI->email->subject('Scheduled Report: ' . $report->name);
        $this->CI->email->message('Please find attached the scheduled report.');
        $this->CI->email->attach($file_path);
        
        $this->CI->email->send();
    }
    
    /**
     * Update next run time for a scheduled report
     */
    private function update_next_run($report_id) {
        $report = $this->CI->db->get_where('scheduled_reports', ['id' => $report_id])->row();
        
        $next_run = $this->calculate_next_run($report->schedule_type, json_decode($report->schedule_config, true));
        
        $this->CI->db->where('id', $report_id);
        $this->CI->db->update('scheduled_reports', [
            'last_run' => date('Y-m-d H:i:s'),
            'next_run' => $next_run
        ]);
    }
    
    /**
     * Calculate next run time
     */
    private function calculate_next_run($schedule_type, $config) {
        $now = new DateTime();
        
        switch ($schedule_type) {
            case 'daily':
                $next = $now->modify('+1 day');
                break;
            case 'weekly':
                $next = $now->modify('+1 week');
                break;
            case 'monthly':
                $next = $now->modify('+1 month');
                break;
            case 'custom':
                // Handle custom cron-like expressions
                $next = $this->parse_cron_expression($config['cron_expression']);
                break;
            default:
                $next = $now->modify('+1 day');
        }
        
        return $next->format('Y-m-d H:i:s');
    }
    
    /**
     * Parse cron expression (basic implementation)
     */
    private function parse_cron_expression($expression) {
        // Basic cron expression parser
        // Format: minute hour day month weekday
        // Example: "0 9 * * 1" = Every Monday at 9 AM
        
        $parts = explode(' ', $expression);
        if (count($parts) !== 5) {
            throw new Exception('Invalid cron expression');
        }
        
        $now = new DateTime();
        
        // This is a simplified parser - in production, use a proper cron library
        return $now->modify('+1 day');
    }
    
    /**
     * Log report execution
     */
    private function log_execution($report_id, $status, $file_path = null) {
        $data = [
            'report_definition_id' => $report_id,
            'execution_time' => date('Y-m-d H:i:s'),
            'status' => $status,
            'file_path' => $file_path,
            'file_size' => $file_path ? filesize($file_path) : null,
            'execution_time_ms' => 0, // You can measure this
            'created_by' => 1 // System user
        ];
        
        $this->CI->db->insert('report_executions', $data);
    }
    
    /**
     * Create a new scheduled report
     */
    public function create_scheduled_report($data) {
        $data['next_run'] = $this->calculate_next_run($data['schedule_type'], $data['schedule_config']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->CI->db->insert('scheduled_reports', $data);
        return $this->CI->db->insert_id();
    }
    
    /**
     * Update a scheduled report
     */
    public function update_scheduled_report($id, $data) {
        if (isset($data['schedule_type']) || isset($data['schedule_config'])) {
            $data['next_run'] = $this->calculate_next_run(
                $data['schedule_type'] ?? 'daily',
                $data['schedule_config'] ?? []
            );
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->CI->db->where('id', $id);
        return $this->CI->db->update('scheduled_reports', $data);
    }
    
    /**
     * Delete a scheduled report
     */
    public function delete_scheduled_report($id) {
        $this->CI->db->where('id', $id);
        return $this->CI->db->update('scheduled_reports', ['is_active' => false]);
    }
    
    /**
     * Get scheduled report statistics
     */
    public function get_statistics() {
        $stats = [];
        
        // Total scheduled reports
        $stats['total'] = $this->CI->db->where('is_active', true)->count_all_results('scheduled_reports');
        
        // Reports due today
        $today = date('Y-m-d');
        $stats['due_today'] = $this->CI->db->where('is_active', true)
                                          ->where('DATE(next_run)', $today)
                                          ->count_all_results('scheduled_reports');
        
        // Reports executed today
        $stats['executed_today'] = $this->CI->db->where('DATE(execution_time)', $today)
                                               ->where('status', 'success')
                                               ->count_all_results('report_executions');
        
        // Failed executions today
        $stats['failed_today'] = $this->CI->db->where('DATE(execution_time)', $today)
                                             ->where('status', 'failed')
                                             ->count_all_results('report_executions');
        
        return $stats;
    }
}
