<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Simple Test Controller
 */

class Test extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        echo "<h1>Test Controller Working!</h1>";
        echo "<p>Report System Test</p>";
        echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";
        
        // Test database connection
        $this->load->database();
        if ($this->db->conn_id) {
            echo "<p>✅ Database connection: OK</p>";
        } else {
            echo "<p>❌ Database connection: FAILED</p>";
        }
        
        // Test custom reports config
        $this->load->config('custom_reports', TRUE);
        $data_sources = $this->config->item('data_sources', 'custom_reports');
        if ($data_sources) {
            echo "<p>✅ Custom reports config: OK</p>";
            echo "<p>Data sources: " . implode(', ', array_keys($data_sources)) . "</p>";
        } else {
            echo "<p>❌ Custom reports config: FAILED</p>";
        }
        
        // Test models
        $this->load->model('modules/reports/CustomReports_model');
        try {
            $template_count = $this->CustomReports_model->get_templates_count();
            echo "<p>✅ Templates count: $template_count</p>";
        } catch (Exception $e) {
            echo "<p>❌ Model error: " . $e->getMessage() . "</p>";
        }
        
        echo "<hr>";
        echo "<h2>Test Links:</h2>";
        echo "<p><a href='/reports'>Reports Dashboard</a></p>";
        echo "<p><a href='/reports/builder'>Report Builder</a></p>";
        echo "<p><a href='/reports/templates'>Template Manager</a></p>";
    }
}
