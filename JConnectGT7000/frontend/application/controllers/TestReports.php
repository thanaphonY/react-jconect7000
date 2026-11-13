<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class TestReports extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->helper('url');
    }
    
    /**
     * Test reports module access
     */
    public function index() {
        echo "<h1>Test Reports Module</h1>";
        echo "<p>Testing reports module access...</p>";
        
        // Test if module exists
        $module_path = APPPATH . 'modules/reports/';
        if (is_dir($module_path)) {
            echo "<p>✅ Reports module directory exists</p>";
            
            // Test controller
            $controller_path = $module_path . 'controllers/Dashboard.php';
            if (file_exists($controller_path)) {
                echo "<p>✅ Dashboard controller exists</p>";
            } else {
                echo "<p>❌ Dashboard controller not found</p>";
            }
            
            // Test model
            $model_path = $module_path . 'models/Dashboard_model.php';
            if (file_exists($model_path)) {
                echo "<p>✅ Dashboard model exists</p>";
            } else {
                echo "<p>❌ Dashboard model not found</p>";
            }
            
            // Test view
            $view_path = $module_path . 'views/dashboard.php';
            if (file_exists($view_path)) {
                echo "<p>✅ Dashboard view exists</p>";
            } else {
                echo "<p>❌ Dashboard view not found</p>";
            }
            
        } else {
            echo "<p>❌ Reports module directory not found</p>";
        }
        
        // Test direct access
        echo "<h2>Direct Access Test</h2>";
        echo "<p><a href='" . base_url('reports/dashboard') . "'>Access Dashboard</a></p>";
        echo "<p><a href='" . base_url('reports') . "'>Access Reports Root</a></p>";
        
        // Test API endpoints
        echo "<h2>API Endpoints Test</h2>";
        echo "<p><a href='" . base_url('reports/dashboard/get_statistics') . "'>Get Statistics</a></p>";
        echo "<p><a href='" . base_url('reports/dashboard/get_device_activity') . "'>Get Device Activity</a></p>";
        echo "<p><a href='" . base_url('reports/dashboard/get_device_status') . "'>Get Device Status</a></p>";
        echo "<p><a href='" . base_url('reports/dashboard/get_device_table') . "'>Get Device Table</a></p>";
    }
    
    /**
     * Test module loading
     */
    public function test_module() {
        echo "<h1>Test Module Loading</h1>";
        
        try {
            // Try to load the module
            $this->load->module('reports/dashboard');
            echo "<p>✅ Module loaded successfully</p>";
            
            // Try to call index method
            $this->dashboard->index();
            
        } catch (Exception $e) {
            echo "<p>❌ Error loading module: " . $e->getMessage() . "</p>";
        }
    }
    
    /**
     * Test model loading
     */
    public function test_model() {
        echo "<h1>Test Model Loading</h1>";
        
        try {
            // Try to load the model
            $this->load->model('reports/Dashboard_model');
            echo "<p>✅ Model loaded successfully</p>";
            
            // Try to get statistics
            $stats = $this->Dashboard_model->get_statistics();
            echo "<p>✅ Statistics retrieved:</p>";
            echo "<pre>" . print_r($stats, true) . "</pre>";
            
        } catch (Exception $e) {
            echo "<p>❌ Error loading model: " . $e->getMessage() . "</p>";
        }
    }
}

