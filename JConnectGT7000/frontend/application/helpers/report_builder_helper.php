<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Report Builder Helper
 * 
 * Helper functions สำหรับสร้าง UI แบบ dynamic จาก configuration
 */

if (!function_exists('generate_report_types_html')) {
    /**
     * สร้าง HTML สำหรับ Report Types จาก configuration
     */
    function generate_report_types_html() {
        $CI =& get_instance();
        $CI->config->load('report_types', TRUE);
        $report_types = $CI->config->item('report_types', 'report_types');
        
        $html = '';
        
        foreach ($report_types as $type_key => $type_config) {
            $html .= generate_report_type_card($type_key, $type_config);
        }
        
        return $html;
    }
}

if (!function_exists('generate_report_type_card')) {
    /**
     * สร้าง HTML สำหรับ Report Type Card
     */
    function generate_report_type_card($type_key, $type_config) {
        $color_class = 'border-' . $type_config['color'];
        $bg_class = 'bg-' . $type_config['color'];
        
        $html = '<div class="col-lg-4 col-md-6 mb-3">';
        $html .= '<div class="card h-100 ' . $color_class . '">';
        $html .= '<div class="card-header ' . $bg_class . ' text-white">';
        $html .= '<h6 class="mb-0"><i class="' . $type_config['icon'] . '"></i> ' . $type_config['name'] . '</h6>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        $html .= '<p class="small text-muted">' . $type_config['description'] . '</p>';
        $html .= '<div class="list-group list-group-flush">';
        
        foreach ($type_config['subtypes'] as $subtype_key => $subtype_config) {
            $html .= generate_report_subtype_button($type_key, $subtype_key, $subtype_config);
        }
        
        $html .= '</div>'; // list-group
        $html .= '</div>'; // card-body
        $html .= '</div>'; // card
        $html .= '</div>'; // col
        
        return $html;
    }
}

if (!function_exists('generate_report_subtype_button')) {
    /**
     * สร้าง HTML สำหรับ Report Subtype Button
     */
    function generate_report_subtype_button($type_key, $subtype_key, $subtype_config) {
        $html = '<button class="list-group-item list-group-item-action report-type-btn" ';
        $html .= 'data-type="' . $type_key . '" ';
        $html .= 'data-subtype="' . $subtype_key . '" ';
        $html .= 'data-filters="' . json_encode($subtype_config['filters']) . '" ';
        $html .= 'data-export-formats="' . json_encode($subtype_config['export_formats']) . '" ';
        if (isset($subtype_config['options'])) {
            $html .= 'data-options="' . json_encode($subtype_config['options']) . '" ';
        }
        $html .= 'title="' . $subtype_config['description'] . '">';
        $html .= '<i class="' . $subtype_config['icon'] . '"></i> ' . $subtype_config['name'];
        $html .= '</button>';
        
        return $html;
    }
}

if (!function_exists('generate_dynamic_filters')) {
    /**
     * สร้าง HTML สำหรับ Dynamic Filters
     */
    function generate_dynamic_filters($filters) {
        $CI =& get_instance();
        $CI->config->load('report_types', TRUE);
        $available_filters = $CI->config->item('available_filters', 'report_types');
        
        $html = '';
        
        foreach ($filters as $filter_key) {
            if (isset($available_filters[$filter_key])) {
                $html .= generate_filter_field($filter_key, $available_filters[$filter_key]);
            }
        }
        
        return $html;
    }
}

if (!function_exists('generate_filter_field')) {
    /**
     * สร้าง HTML สำหรับ Filter Field
     */
    function generate_filter_field($filter_key, $filter_config) {
        $html = '<div class="col-lg-3 col-md-6 mb-3">';
        $html .= '<label class="form-label fw-bold">';
        $html .= '<i class="bi bi-funnel"></i> ' . $filter_config['name'];
        $html .= '</label>';
        
        switch ($filter_config['type']) {
            case 'select':
                $html .= '<select class="form-select select2-filter" id="filter-' . $filter_key . '" ';
                if ($filter_config['multiple']) {
                    $html .= 'multiple ';
                }
                $html .= '>';
                $html .= '<option value="">เลือก' . $filter_config['name'] . '</option>';
                
                if (isset($filter_config['options'])) {
                    foreach ($filter_config['options'] as $option) {
                        $html .= '<option value="' . $option . '">' . ucfirst($option) . '</option>';
                    }
                }
                
                $html .= '</select>';
                break;
                
            case 'date_range':
                $html .= '<div class="row">';
                $html .= '<div class="col-6">';
                $html .= '<input type="date" class="form-control" id="start-date" name="start_date" ';
                $html .= 'value="' . date('Y-m-d', strtotime('-7 days')) . '">';
                $html .= '</div>';
                $html .= '<div class="col-6">';
                $html .= '<input type="date" class="form-control" id="end-date" name="end_date" ';
                $html .= 'value="' . date('Y-m-d') . '">';
                $html .= '</div>';
                $html .= '</div>';
                break;
        }
        
        $html .= '<small class="form-text text-muted">เลือก' . $filter_config['name'] . '</small>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_dynamic_options')) {
    /**
     * สร้าง HTML สำหรับ Dynamic Options
     */
    function generate_dynamic_options($options) {
        $CI =& get_instance();
        $CI->config->load('report_types', TRUE);
        $available_options = $CI->config->item('available_options', 'report_types');
        
        $html = '<div class="col-md-6" id="dynamic-options">';
        $html .= '<label class="form-label">ตัวเลือกเพิ่มเติม</label>';
        
        foreach ($options as $option_key) {
            if (isset($available_options[$option_key])) {
                $html .= generate_option_field($option_key, $available_options[$option_key]);
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_option_field')) {
    /**
     * สร้าง HTML สำหรับ Option Field
     */
    function generate_option_field($option_key, $option_config) {
        $html = '<div class="form-check">';
        $html .= '<input class="form-check-input" type="' . $option_config['type'] . '" ';
        $html .= 'id="' . $option_key . '" name="' . $option_key . '" ';
        if ($option_config['default']) {
            $html .= 'checked ';
        }
        $html .= '>';
        $html .= '<label class="form-check-label" for="' . $option_key . '">';
        $html .= $option_config['name'];
        $html .= '</label>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_export_format_options')) {
    /**
     * สร้าง HTML สำหรับ Export Format Options
     */
    function generate_export_format_options($export_formats) {
        $CI =& get_instance();
        $CI->config->load('report_types', TRUE);
        $available_formats = $CI->config->item('available_export_formats', 'report_types');
        
        $html = '<select class="form-select" id="export-format" name="export_format">';
        
        foreach ($export_formats as $format_key) {
            if (isset($available_formats[$format_key])) {
                $html .= '<option value="' . $format_key . '">' . $available_formats[$format_key]['name'] . '</option>';
            }
        }
        
        $html .= '</select>';
        
        return $html;
    }
}

if (!function_exists('get_report_type_config')) {
    /**
     * ดึง configuration ของ report type
     */
    function get_report_type_config($type_key, $subtype_key = null) {
        $CI =& get_instance();
        $CI->config->load('report_types', TRUE);
        $report_types = $CI->config->item('report_types', 'report_types');
        
        if (isset($report_types[$type_key])) {
            if ($subtype_key && isset($report_types[$type_key]['subtypes'][$subtype_key])) {
                return $report_types[$type_key]['subtypes'][$subtype_key];
            }
            return $report_types[$type_key];
        }
        
        return null;
    }
}

if (!function_exists('validate_report_config')) {
    /**
     * ตรวจสอบความถูกต้องของ report configuration
     */
    function validate_report_config($type_key, $subtype_key) {
        $config = get_report_type_config($type_key, $subtype_key);
        
        if (!$config) {
            return [
                'valid' => false,
                'message' => 'Invalid report type or subtype'
            ];
        }
        
        return [
            'valid' => true,
            'config' => $config
        ];
    }
}
