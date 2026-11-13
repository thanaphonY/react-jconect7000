<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Custom Report Builder Helper
 * 
 * Helper functions สำหรับ Custom Report Builder และ Template System
 */

if (!function_exists('generate_custom_report_builder')) {
    /**
     * สร้าง Custom Report Builder UI
     */
    function generate_custom_report_builder() {
        $CI =& get_instance();
        $CI->config->load('custom_reports', TRUE);
        
        $data_sources = $CI->config->item('data_sources', 'custom_reports');
        $builder_components = $CI->config->item('builder_components', 'custom_reports');
        
        $html = '<div id="custom-report-builder" class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h5 class="mb-0"><i class="bi bi-pencil-square"></i> Custom Report Builder</h5>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        
        // Step 1: Data Source Selection
        $html .= generate_data_source_selector($data_sources);
        
        // Step 2: Field Selection
        $html .= generate_field_selector();
        
        // Step 3: Filter Builder
        $html .= generate_filter_builder();
        
        // Step 4: Aggregation Builder
        $html .= generate_aggregation_builder();
        
        // Step 5: Sort Builder
        $html .= generate_sort_builder();
        
        // Step 6: Template Selection
        $html .= generate_template_selector();
        
        // Step 7: Preview & Generate
        $html .= generate_preview_section();
        
        $html .= '</div>'; // card-body
        $html .= '</div>'; // card
        
        return $html;
    }
}

if (!function_exists('generate_data_source_selector')) {
    /**
     * สร้าง Data Source Selector
     */
    function generate_data_source_selector($data_sources) {
        $html = '<div class="builder-step" id="step-data-source">';
        $html .= '<h6 class="step-title"><i class="bi bi-database"></i> Step 1: เลือกแหล่งข้อมูล</h6>';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-6">';
        $html .= '<select class="form-select" id="data-source-selector">';
        $html .= '<option value="">เลือกแหล่งข้อมูล...</option>';
        
        foreach ($data_sources as $key => $source) {
            $html .= '<option value="' . $key . '" data-table="' . $source['table'] . '">';
            $html .= $source['name'] . ' - ' . $source['description'];
            $html .= '</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<div id="data-source-info" class="alert alert-info" style="display: none;">';
        $html .= '<strong>ข้อมูลแหล่งข้อมูล:</strong><br>';
        $html .= '<span id="source-description"></span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_field_selector')) {
    /**
     * สร้าง Field Selector
     */
    function generate_field_selector() {
        $html = '<div class="builder-step" id="step-field-selector" style="display: none;">';
        $html .= '<h6 class="step-title"><i class="bi bi-list-check"></i> Step 2: เลือกฟิลด์</h6>';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-8">';
        $html .= '<div class="field-selector-container">';
        $html .= '<div class="available-fields">';
        $html .= '<h6>Available Fields:</h6>';
        $html .= '<div id="available-fields-list" class="list-group">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="col-md-4">';
        $html .= '<div class="selected-fields">';
        $html .= '<h6>Selected Fields:</h6>';
        $html .= '<div id="selected-fields-list" class="list-group">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_filter_builder')) {
    /**
     * สร้าง Filter Builder
     */
    function generate_filter_builder() {
        $CI =& get_instance();
        $CI->config->load('custom_reports', TRUE);
        $filter_operators = $CI->config->item('filter_operators', 'custom_reports');
        
        $html = '<div class="builder-step" id="step-filter-builder" style="display: none;">';
        $html .= '<h6 class="step-title"><i class="bi bi-funnel"></i> Step 3: สร้างเงื่อนไขการกรอง</h6>';
        $html .= '<div class="filter-container">';
        $html .= '<div id="filter-rules">';
        $html .= '</div>';
        $html .= '<button type="button" class="btn btn-outline-primary btn-sm" id="add-filter-rule">';
        $html .= '<i class="bi bi-plus"></i> เพิ่มเงื่อนไข';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_aggregation_builder')) {
    /**
     * สร้าง Aggregation Builder
     */
    function generate_aggregation_builder() {
        $CI =& get_instance();
        $CI->config->load('custom_reports', TRUE);
        $aggregation_functions = $CI->config->item('aggregation_functions', 'custom_reports');
        
        $html = '<div class="builder-step" id="step-aggregation-builder" style="display: none;">';
        $html .= '<h6 class="step-title"><i class="bi bi-calculator"></i> Step 4: การรวมข้อมูล</h6>';
        $html .= '<div class="aggregation-container">';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-6">';
        $html .= '<h6>Group By Fields:</h6>';
        $html .= '<div id="group-by-fields" class="list-group">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<h6>Aggregation Functions:</h6>';
        $html .= '<div id="aggregation-functions" class="list-group">';
        foreach ($aggregation_functions as $key => $func) {
            $html .= '<div class="list-group-item">';
            $html .= '<div class="form-check">';
            $html .= '<input class="form-check-input" type="checkbox" id="agg-' . $key . '">';
            $html .= '<label class="form-check-label" for="agg-' . $key . '">';
            $html .= '<strong>' . $func['name'] . '</strong><br>';
            $html .= '<small class="text-muted">' . $func['description'] . '</small>';
            $html .= '</label>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_sort_builder')) {
    /**
     * สร้าง Sort Builder
     */
    function generate_sort_builder() {
        $html = '<div class="builder-step" id="step-sort-builder" style="display: none;">';
        $html .= '<h6 class="step-title"><i class="bi bi-sort-down"></i> Step 5: การเรียงลำดับ</h6>';
        $html .= '<div class="sort-container">';
        $html .= '<div id="sort-rules">';
        $html .= '</div>';
        $html .= '<button type="button" class="btn btn-outline-primary btn-sm" id="add-sort-rule">';
        $html .= '<i class="bi bi-plus"></i> เพิ่มการเรียงลำดับ';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_template_selector')) {
    /**
     * สร้าง Template Selector
     */
    function generate_template_selector() {
        $CI =& get_instance();
        $CI->config->load('custom_reports', TRUE);
        $template_types = $CI->config->item('template_types', 'custom_reports');
        
        $html = '<div class="builder-step" id="step-template-selector" style="display: none;">';
        $html .= '<h6 class="step-title"><i class="bi bi-file-earmark-text"></i> Step 6: เลือก Template</h6>';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-6">';
        $html .= '<h6>Template Type:</h6>';
        $html .= '<select class="form-select" id="template-type-selector">';
        $html .= '<option value="">เลือกประเภท Template...</option>';
        
        foreach ($template_types as $key => $type) {
            $html .= '<option value="' . $key . '">' . $type['name'] . '</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<h6>Available Templates:</h6>';
        $html .= '<div id="available-templates" class="list-group">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="row mt-3">';
        $html .= '<div class="col-12">';
        $html .= '<button type="button" class="btn btn-outline-secondary btn-sm" id="create-new-template">';
        $html .= '<i class="bi bi-plus-circle"></i> สร้าง Template ใหม่';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_preview_section')) {
    /**
     * สร้าง Preview Section
     */
    function generate_preview_section() {
        $html = '<div class="builder-step" id="step-preview" style="display: none;">';
        $html .= '<h6 class="step-title"><i class="bi bi-eye"></i> Step 7: ดูตัวอย่างและสร้างรายงาน</h6>';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-8">';
        $html .= '<div class="preview-container">';
        $html .= '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h6 class="mb-0">Preview</h6>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        $html .= '<div id="report-preview">';
        $html .= '<p class="text-muted">คลิก "Generate Preview" เพื่อดูตัวอย่างรายงาน</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="col-md-4">';
        $html .= '<div class="action-buttons">';
        $html .= '<button type="button" class="btn btn-primary mb-2 w-100" id="generate-preview">';
        $html .= '<i class="bi bi-eye"></i> Generate Preview';
        $html .= '</button>';
        $html .= '<button type="button" class="btn btn-success mb-2 w-100" id="save-report">';
        $html .= '<i class="bi bi-save"></i> Save Report';
        $html .= '</button>';
        $html .= '<button type="button" class="btn btn-info mb-2 w-100" id="export-report">';
        $html .= '<i class="bi bi-download"></i> Export Report';
        $html .= '</button>';
        $html .= '<button type="button" class="btn btn-warning mb-2 w-100" id="schedule-report">';
        $html .= '<i class="bi bi-clock"></i> Schedule Report';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_filter_rule_html')) {
    /**
     * สร้าง HTML สำหรับ Filter Rule
     */
    function generate_filter_rule_html($rule_id = null) {
        $CI =& get_instance();
        $CI->config->load('custom_reports', TRUE);
        $filter_operators = $CI->config->item('filter_operators', 'custom_reports');
        
        $rule_id = $rule_id ?: 'rule_' . uniqid();
        
        $html = '<div class="filter-rule" data-rule-id="' . $rule_id . '">';
        $html .= '<div class="row align-items-end">';
        $html .= '<div class="col-md-3">';
        $html .= '<label class="form-label">Field</label>';
        $html .= '<select class="form-select filter-field" data-rule-id="' . $rule_id . '">';
        $html .= '<option value="">เลือกฟิลด์...</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<label class="form-label">Operator</label>';
        $html .= '<select class="form-select filter-operator" data-rule-id="' . $rule_id . '">';
        $html .= '<option value="">เลือก...</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-md-3">';
        $html .= '<label class="form-label">Value</label>';
        $html .= '<input type="text" class="form-control filter-value" data-rule-id="' . $rule_id . '">';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<label class="form-label">Logic</label>';
        $html .= '<select class="form-select filter-logic" data-rule-id="' . $rule_id . '">';
        $html .= '<option value="AND">AND</option>';
        $html .= '<option value="OR">OR</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<button type="button" class="btn btn-outline-danger btn-sm remove-filter-rule" data-rule-id="' . $rule_id . '">';
        $html .= '<i class="bi bi-trash"></i>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_sort_rule_html')) {
    /**
     * สร้าง HTML สำหรับ Sort Rule
     */
    function generate_sort_rule_html($rule_id = null) {
        $rule_id = $rule_id ?: 'sort_' . uniqid();
        
        $html = '<div class="sort-rule" data-rule-id="' . $rule_id . '">';
        $html .= '<div class="row align-items-end">';
        $html .= '<div class="col-md-4">';
        $html .= '<label class="form-label">Field</label>';
        $html .= '<select class="form-select sort-field" data-rule-id="' . $rule_id . '">';
        $html .= '<option value="">เลือกฟิลด์...</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-md-3">';
        $html .= '<label class="form-label">Direction</label>';
        $html .= '<select class="form-select sort-direction" data-rule-id="' . $rule_id . '">';
        $html .= '<option value="ASC">Ascending</option>';
        $html .= '<option value="DESC">Descending</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-md-3">';
        $html .= '<label class="form-label">Priority</label>';
        $html .= '<input type="number" class="form-control sort-priority" data-rule-id="' . $rule_id . '" value="1" min="1">';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<button type="button" class="btn btn-outline-danger btn-sm remove-sort-rule" data-rule-id="' . $rule_id . '">';
        $html .= '<i class="bi bi-trash"></i>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_template_editor')) {
    /**
     * สร้าง Template Editor
     */
    function generate_template_editor($template_type = 'html') {
        $CI =& get_instance();
        $CI->config->load('custom_reports', TRUE);
        $template_variables = $CI->config->item('template_variables', 'custom_reports');
        
        $html = '<div class="template-editor">';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-8">';
        $html .= '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h6 class="mb-0">Template Editor</h6>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        $html .= '<textarea id="template-content" class="form-control" rows="20" placeholder="Enter template content..."></textarea>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="col-md-4">';
        $html .= '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h6 class="mb-0">Available Variables</h6>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        
        foreach ($template_variables as $key => $variable) {
            $html .= '<div class="variable-item mb-2">';
            $html .= '<strong>$' . $key . '</strong><br>';
            $html .= '<small class="text-muted">' . $variable['description'] . '</small><br>';
            $html .= '<small class="text-info">' . $variable['example'] . '</small>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="row mt-3">';
        $html .= '<div class="col-12">';
        $html .= '<button type="button" class="btn btn-primary" id="save-template">Save Template</button>';
        $html .= '<button type="button" class="btn btn-secondary" id="preview-template">Preview</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('generate_schedule_config')) {
    /**
     * สร้าง Schedule Configuration Form
     */
    function generate_schedule_config() {
        $CI =& get_instance();
        $CI->config->load('custom_reports', TRUE);
        $schedule_types = $CI->config->item('schedule_types', 'custom_reports');
        $delivery_methods = $CI->config->item('delivery_methods', 'custom_reports');
        
        $html = '<div class="schedule-config">';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-6">';
        $html .= '<h6>Schedule Configuration</h6>';
        $html .= '<div class="mb-3">';
        $html .= '<label class="form-label">Schedule Type</label>';
        $html .= '<select class="form-select" id="schedule-type">';
        $html .= '<option value="">เลือกประเภท...</option>';
        
        foreach ($schedule_types as $key => $type) {
            $html .= '<option value="' . $key . '">' . $type['name'] . '</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div id="schedule-config-fields">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<h6>Delivery Configuration</h6>';
        $html .= '<div class="mb-3">';
        $html .= '<label class="form-label">Delivery Method</label>';
        $html .= '<select class="form-select" id="delivery-method">';
        $html .= '<option value="">เลือกวิธีส่ง...</option>';
        
        foreach ($delivery_methods as $key => $method) {
            $html .= '<option value="' . $key . '">' . $method['name'] . '</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div id="delivery-config-fields">';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('build_sql_query')) {
    /**
     * สร้าง SQL Query จาก configuration
     */
    function build_sql_query($data_source, $fields, $filters = [], $aggregations = [], $sorts = [], $group_by = []) {
        $CI =& get_instance();
        $CI->config->load('custom_reports', TRUE);
        $data_sources = $CI->config->item('data_sources', 'custom_reports');
        
        if (!isset($data_sources[$data_source])) {
            return false;
        }
        
        $source_config = $data_sources[$data_source];
        $table = $source_config['table'];
        $joins = $source_config['joins'];
        
        // Build SELECT clause
        $select_fields = [];
        foreach ($fields as $field) {
            $select_fields[] = $field;
        }
        
        // Add aggregations
        foreach ($aggregations as $agg) {
            $select_fields[] = $agg['function'] . '(' . $agg['field'] . ') as ' . $agg['alias'];
        }
        
        $sql = 'SELECT ' . implode(', ', $select_fields) . ' FROM ' . $table;
        
        // Add JOINs
        foreach ($joins as $join_table => $join_condition) {
            $sql .= ' LEFT JOIN ' . $join_table . ' ON ' . $join_condition;
        }
        
        // Add WHERE clause
        if (!empty($filters)) {
            $where_conditions = [];
            foreach ($filters as $filter) {
                $where_conditions[] = build_filter_condition($filter);
            }
            $sql .= ' WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Add GROUP BY
        if (!empty($group_by)) {
            $sql .= ' GROUP BY ' . implode(', ', $group_by);
        }
        
        // Add ORDER BY
        if (!empty($sorts)) {
            $order_conditions = [];
            foreach ($sorts as $sort) {
                $order_conditions[] = $sort['field'] . ' ' . $sort['direction'];
            }
            $sql .= ' ORDER BY ' . implode(', ', $order_conditions);
        }
        
        return $sql;
    }
}

if (!function_exists('build_filter_condition')) {
    /**
     * สร้าง Filter Condition
     */
    function build_filter_condition($filter) {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'];
        
        switch ($operator) {
            case 'equals':
                return $field . " = '" . $value . "'";
            case 'not_equals':
                return $field . " != '" . $value . "'";
            case 'contains':
                return $field . " ILIKE '%" . $value . "%'";
            case 'not_contains':
                return $field . " NOT ILIKE '%" . $value . "%'";
            case 'starts_with':
                return $field . " ILIKE '" . $value . "%'";
            case 'ends_with':
                return $field . " ILIKE '%" . $value . "'";
            case 'greater_than':
                return $field . " > " . $value;
            case 'less_than':
                return $field . " < " . $value;
            case 'greater_equal':
                return $field . " >= " . $value;
            case 'less_equal':
                return $field . " <= " . $value;
            case 'between':
                $values = explode(',', $value);
                return $field . " BETWEEN " . $values[0] . " AND " . $values[1];
            default:
                return $field . " = '" . $value . "'";
        }
    }
}

if (!function_exists('render_template')) {
    /**
     * Render Template ด้วยข้อมูล
     */
    function render_template($template_content, $data, $filters, $parameters, $metadata) {
        // Simple template engine
        $template_content = str_replace('{{$parameters->title}}', $parameters['title'] ?? '', $template_content);
        $template_content = str_replace('{{$metadata->generated_at}}', $metadata['generated_at'] ?? '', $template_content);
        
        // Handle loops
        if (strpos($template_content, '@foreach') !== false) {
            $template_content = process_foreach_loops($template_content, $data);
        }
        
        return $template_content;
    }
}

if (!function_exists('process_foreach_loops')) {
    /**
     * Process @foreach loops in template
     */
    function process_foreach_loops($content, $data) {
        // Simple foreach processing
        preg_match_all('/@foreach\\(\$([^)]+)\\)(.*?)@endforeach/s', $content, $matches);
        
        foreach ($matches[0] as $index => $full_match) {
            $data_path = $matches[1][$index];
            $loop_content = $matches[2][$index];
            
            $loop_data = get_data_by_path($data, $data_path);
            $replacement = '';
            
            if (is_array($loop_data)) {
                foreach ($loop_data as $item) {
                    $item_content = $loop_content;
                    $item_content = replace_variables($item_content, $item);
                    $replacement .= $item_content;
                }
            }
            
            $content = str_replace($full_match, $replacement, $content);
        }
        
        return $content;
    }
}

if (!function_exists('get_data_by_path')) {
    /**
     * Get data by dot notation path
     */
    function get_data_by_path($data, $path) {
        $keys = explode('->', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (isset($current->$key)) {
                $current = $current->$key;
            } elseif (isset($current[$key])) {
                $current = $current[$key];
            } else {
                return null;
            }
        }
        
        return $current;
    }
}

if (!function_exists('replace_variables')) {
    /**
     * Replace variables in template
     */
    function replace_variables($content, $data) {
        preg_match_all('/\{\{\$([^}]+)\}\}/', $content, $matches);
        
        foreach ($matches[0] as $index => $full_match) {
            $variable_path = $matches[1][$index];
            $value = get_data_by_path($data, $variable_path);
            $content = str_replace($full_match, $value ?? '', $content);
        }
        
        return $content;
    }
}
