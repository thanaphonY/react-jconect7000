<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Test Template Controller
 */

class TestTemplate extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->config('custom_reports', TRUE);
    }

    public function index()
    {
        echo "<h1>Template Test</h1>";
        
        // Test template types
        $template_types = $this->config->item('template_types', 'custom_reports');
        echo "<h2>Template Types:</h2>";
        echo "<pre>" . print_r($template_types, true) . "</pre>";
        
        // Test API call
        echo "<h2>Test API Call:</h2>";
        echo "<p>Testing Excel templates...</p>";
        
        $this->load->model('modules/reports/CustomReports_model');
        $templates = $this->CustomReports_model->get_templates('excel');
        
        echo "<h3>Excel Templates:</h3>";
        echo "<pre>" . print_r($templates, true) . "</pre>";
        
        // Test HTML templates
        $html_templates = $this->CustomReports_model->get_templates('html');
        echo "<h3>HTML Templates:</h3>";
        echo "<pre>" . print_r($html_templates, true) . "</pre>";
        
        echo "<hr>";
        echo "<h2>Test Links:</h2>";
        echo "<p><a href='/reports/builder'>Report Builder</a></p>";
        echo "<p><a href='/reports/templates'>Template Manager</a></p>";
    }
}
