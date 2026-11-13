<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PHPSpreadsheet Library Wrapper
 * 
 * Wrapper สำหรับ PHPSpreadsheet library
 * ใช้งาน: $this->load->library('phpspreadsheet');
 */
class PhpSpreadsheet
{
    private $spreadsheet;
    private $sheet;
    
    public function __construct()
    {
        // Load TCPDF first (required for PDF generation)
        $tcpdf_path = APPPATH . 'third_party/tcpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            require_once $tcpdf_path;
        }
        
        // Load PHPSpreadsheet autoloader from vendor directory
        // Since we're running in VM, use the actual VM path
        $autoload_path = '/var/www/frontend/vendor/autoload.php';
        
        if (!file_exists($autoload_path)) {
            throw new Exception('PhpSpreadsheet autoloader not found. Please install via composer: composer require phpoffice/phpspreadsheet');
        }
        
        require_once $autoload_path;
    }
    
    /**
     * Create new spreadsheet
     */
    public function create($title = 'Report')
    {
        $this->spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
        $this->sheet->setTitle($title);
        
        return $this;
    }
    
    /**
     * Add data to sheet
     */
    public function add_data($data, $start_row = 1)
    {
        if (empty($data)) {
            return $this;
        }
        
        // Add headers if first row
        if ($start_row == 1) {
            $headers = array_keys((array) $data[0]);
            $col = 'A';
            foreach ($headers as $header) {
                $this->sheet->setCellValue($col . $start_row, $header);
                $col++;
            }
            $start_row++;
        }
        
        // Add data
        foreach ($data as $record) {
            $col = 'A';
            foreach ($record as $value) {
                $this->sheet->setCellValue($col . $start_row, $value);
                $col++;
            }
            $start_row++;
        }
        
        return $this;
    }
    
    /**
     * Set cell value
     */
    public function set_cell($cell, $value)
    {
        $this->sheet->setCellValue($cell, $value);
        return $this;
    }
    
    /**
     * Set cell style
     */
    public function set_style($cell, $style)
    {
        $this->sheet->getStyle($cell)->applyFromArray($style);
        return $this;
    }
    
    /**
     * Auto size columns
     */
    public function auto_size_columns()
    {
        foreach (range('A', $this->sheet->getHighestColumn()) as $col) {
            $this->sheet->getColumnDimension($col)->setAutoSize(true);
        }
        return $this;
    }
    
    /**
     * Save to file
     */
    public function save($file_path)
    {
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $writer->save($file_path);
        
        return $file_path;
    }
    
    /**
     * Download file
     */
    public function download($filename = 'report.xlsx')
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $writer->save('php://output');
    }
    
    /**
     * Get spreadsheet object
     */
    public function get_spreadsheet()
    {
        return $this->spreadsheet;
    }
    
    /**
     * Get active sheet
     */
    public function get_sheet()
    {
        return $this->sheet;
    }
    
    /**
     * Load XLSX template file
     */
    public function load_template($template_path)
    {
        if (!file_exists($template_path)) {
            throw new Exception('Template file not found: ' . $template_path);
        }
        
        $this->spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($template_path);
        $this->sheet = $this->spreadsheet->getActiveSheet();
        
        return $this;
    }
    
    /**
     * Process template with data (replace variables)
     */
    public function process_template($data)
    {
        if (!$this->spreadsheet) {
            throw new Exception('No spreadsheet loaded');
        }
        
        // Process all worksheets
        foreach ($this->spreadsheet->getWorksheetIterator() as $worksheet) {
            $this->process_worksheet($worksheet, $data);
        }
        
        return $this;
    }
    
    /**
     * Process individual worksheet
     */
    private function process_worksheet($worksheet, $data)
    {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellAddress = $col . $row;
                $cellValue = $worksheet->getCell($cellAddress)->getValue();
                
                if (is_string($cellValue) && strpos($cellValue, '={{') !== false) {
                    $processedValue = $this->replace_variables($cellValue, $data);
                    $worksheet->getCell($cellAddress)->setValue($processedValue);
                }
            }
        }
    }
    
    /**
     * Replace template variables with actual data
     */
    private function replace_variables($cellValue, $data)
    {
        return preg_replace_callback('/={{([^}]+)}}/', function($matches) use ($data) {
            $variable = $matches[1];
            return $this->get_variable_value($variable, $data);
        }, $cellValue);
    }
    
    /**
     * Get variable value from data array using dot notation
     */
    private function get_variable_value($variable, $data)
    {
        $keys = explode('.', $variable);
        $value = $data;
        
        foreach ($keys as $key) {
            if (isset($value[$key])) {
                $value = $value[$key];
            } else {
                return ''; // Variable not found
            }
        }
        
        return $value;
    }
    
    /**
     * Generate PDF from current spreadsheet
     */
    public function generate_pdf($filename = null)
    {
        if (!$this->spreadsheet) {
            throw new Exception('No spreadsheet loaded');
        }
        
        // Create PDF writer - use TCPDF instead of mPDF
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, 'Tcpdf');
        
        // Configure TCPDF basic settings
        $writer->setOrientation('P'); // Portrait
        $writer->setPaperSize('A4');
        
        if ($filename) {
            $writer->save($filename);
            return $filename;
        } else {
            // Return PDF content
            ob_start();
            $writer->save('php://output');
            return ob_get_clean();
        }
    }
    
    /**
     * Download PDF
     */
    public function download_pdf($filename = 'report.pdf')
    {
        $pdf_content = $this->generate_pdf();
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        echo $pdf_content;
    }
    
    /**
     * Preview PDF in browser
     */
    public function preview_pdf()
    {
        if (!$this->spreadsheet) {
            throw new Exception('No spreadsheet loaded');
        }
        
        // Create PDF writer 
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, 'Tcpdf');
        
        // Configure TCPDF basic settings
        $writer->setOrientation('P'); // Portrait  
        $writer->setPaperSize('A4');
        
        header('Content-Type: application/pdf');
        header('Cache-Control: max-age=0');
        
        // Generate and output PDF with Thai support
        $writer->save('php://output');
    }
    
    /**
     * Extract variables from template
     */
    public function extract_variables($template_path)
    {
        $this->load_template($template_path);
        $variables = [];
        
        $highestRow = $this->sheet->getHighestRow();
        $highestColumn = $this->sheet->getHighestColumn();
        
        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $this->sheet->getCell($col . $row)->getValue();
                
                if (is_string($cellValue) && preg_match_all('/={{([^}]+)}}/', $cellValue, $matches)) {
                    foreach ($matches[1] as $variable) {
                        $variables[] = $variable;
                    }
                }
            }
        }
        
        return array_unique($variables);
    }
    
    /**
     * Get template info
     */
    public function get_template_info($template_path)
    {
        $this->load_template($template_path);
        
        return [
            'sheets' => $this->spreadsheet->getSheetNames(),
            'active_sheet' => $this->sheet->getTitle(),
            'range' => $this->sheet->getHighestRow() . 'x' . $this->sheet->getHighestColumn(),
            'variables' => $this->extract_variables($template_path)
        ];
    }
}
