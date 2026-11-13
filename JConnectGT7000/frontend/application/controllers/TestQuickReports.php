<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Test Quick Reports Controller
 * 
 * สำหรับ Debug และทดสอบ Quick Reports Module
 */

class TestQuickReports extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Test Quick Reports Module
     */
    public function index()
    {
        echo "<h1>Test Quick Reports Module</h1>";
        
        // Test 1: Check if module exists
        echo "<h2>1. Module Structure Check</h2>";
        $module_path = APPPATH . 'modules/quickreports/';
        if (is_dir($module_path)) {
            echo "✅ Module directory exists: $module_path<br>";
            
            // Check controller
            $controller_path = $module_path . 'controllers/QuickReports.php';
            if (file_exists($controller_path)) {
                echo "✅ Controller exists: $controller_path<br>";
            } else {
                echo "❌ Controller missing: $controller_path<br>";
            }
            
            // Check model
            $model_path = $module_path . 'models/Quickreports_model.php';
            if (file_exists($model_path)) {
                echo "✅ Model exists: $model_path<br>";
            } else {
                echo "❌ Model missing: $model_path<br>";
            }
            
            // Check config
            $config_path = $module_path . 'config/quick_reports.php';
            if (file_exists($config_path)) {
                echo "✅ Config exists: $config_path<br>";
            } else {
                echo "❌ Config missing: $config_path<br>";
            }
            
            // Check view
            $view_path = $module_path . 'views/dashboard.php';
            if (file_exists($view_path)) {
                echo "✅ View exists: $view_path<br>";
            } else {
                echo "❌ View missing: $view_path<br>";
            }
        } else {
            echo "❌ Module directory missing: $module_path<br>";
        }
        
        // Test 2: Try to load model
        echo "<h2>2. Model Loading Test</h2>";
        try {
            $this->load->model('quickreports/Quickreports_model');
            echo "✅ Model loaded successfully<br>";
            
            // Test model method
            $stats = $this->Quickreports_model->get_system_stats();
            echo "✅ Model method works: " . json_encode($stats) . "<br>";
        } catch (Exception $e) {
            echo "❌ Model loading failed: " . $e->getMessage() . "<br>";
        }
        
        // Test 3: Try to load config
        echo "<h2>3. Config Loading Test</h2>";
        try {
            $this->load->config('quickreports/quick_reports', TRUE);
            $popular_reports = $this->config->item('popular_reports', 'quick_reports');
            echo "✅ Config loaded successfully<br>";
            echo "✅ Popular reports count: " . count($popular_reports) . "<br>";
        } catch (Exception $e) {
            echo "❌ Config loading failed: " . $e->getMessage() . "<br>";
        }
        
        // Test 4: Check routes
        echo "<h2>4. Routes Check</h2>";
        $routes_file = APPPATH . 'config/routes.php';
        if (file_exists($routes_file)) {
            $routes_content = file_get_contents($routes_file);
            if (strpos($routes_content, 'quickreports') !== false) {
                echo "✅ Routes found in routes.php<br>";
            } else {
                echo "❌ Routes not found in routes.php<br>";
            }
        } else {
            echo "❌ Routes file missing<br>";
        }
        
        // Test 5: Check upload directory
        echo "<h2>5. Upload Directory Check</h2>";
        $upload_path = FCPATH . 'uploads/reports/';
        if (is_dir($upload_path)) {
            echo "✅ Upload directory exists: $upload_path<br>";
            
            // Check subdirectories
            $subdirs = ['pdf', 'excel', 'html'];
            foreach ($subdirs as $subdir) {
                $subdir_path = $upload_path . $subdir . '/';
                if (is_dir($subdir_path)) {
                    echo "✅ Subdirectory exists: $subdir_path<br>";
                } else {
                    echo "❌ Subdirectory missing: $subdir_path<br>";
                }
            }
        } else {
            echo "❌ Upload directory missing: $upload_path<br>";
        }
        
        // Test 6: Check libraries
        echo "<h2>6. Libraries Check</h2>";
        $libraries = ['tcpdf', 'phpspreadsheet'];
        foreach ($libraries as $library) {
            $library_path = APPPATH . 'libraries/' . ucfirst($library) . '.php';
            if (file_exists($library_path)) {
                echo "✅ Library exists: $library_path<br>";
            } else {
                echo "❌ Library missing: $library_path<br>";
            }
        }
        
        // Test 7: Direct URL test
        echo "<h2>7. Direct URL Test</h2>";
        echo "<a href='" . base_url('quickreports') . "' target='_blank'>Test Quick Reports Dashboard</a><br>";
        echo "<a href='" . base_url('quickreports/generate-popular-report') . "' target='_blank'>Test Generate Popular Report API</a><br>";
        
        echo "<hr>";
        echo "<p><strong>Test completed!</strong></p>";
    }
}
