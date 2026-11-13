<?php
$template_id = isset($template_id) ? $template_id : null;
$template = isset($template) ? $template : null;
$device = isset($device) ? $device : null;
$metrics = isset($metrics) ? $metrics : ($device->metrics ?? ($template->metrics ?? '[]'));
?>

<!--begin::App Content Header-->
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5><?= isset($device) ? lang('edit') : lang('add') ?> <?php echo lang('devices'); ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home'); ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('device') ?>"><?php echo lang('devices'); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= isset($device) ? 'Edit' : 'Add' ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>
<!--end::App Content Header-->

<style>
    .custom-fields-display {
        margin-top: 10px;
    }
    .custom-field-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 0;
        border-bottom: 1px solid #eee;
    }
    .custom-field-item:last-child {
        border-bottom: none;
    }
    .custom-field-item strong {
        color: #495057;
        min-width: 120px;
    }
    .custom-field-item span {
        color: #6c757d;
        flex: 1;
        text-align: right;
    }
    .custom-fields-container {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 15px;
        margin-top: 10px;
    }
    .custom-fields-container h6 {
        margin-bottom: 10px;
        color: #495057;
        font-weight: 600;
    }
    
    /* Template card active state */
    .template-card-inner {
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .template-card-inner:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .template-card-inner.border-primary {
        border-width: 3px !important;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    
    .template-card-inner.bg-primary.bg-opacity-10 {
        background-color: rgba(13, 110, 253, 0.1) !important;
    }
    
    /* Metrics styles - Dark Theme Support */
    .metric-item {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        background-color: var(--bs-body-bg);
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
    }
    
    .metric-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    
    .metric-item.border-success {
        border-color: #198754;
    }
    
    .metric-item.border-secondary {
        border-color: #6c757d;
    }
    
    .metric-details {
        transition: all 0.3s ease;
    }
    
    .metric-item .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--bs-body-color);
        margin-bottom: 0.25rem;
    }
    
    .metric-item .form-control,
    .metric-item .form-select {
        font-size: 0.875rem;
        background-color: var(--bs-body-bg);
        border-color: var(--bs-border-color);
        color: var(--bs-body-color);
    }
    
    .metric-item .form-control:focus,
    .metric-item .form-select:focus {
        background-color: var(--bs-body-bg);
        border-color: var(--bs-primary);
        color: var(--bs-body-color);
        box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
    }
    
    .metric-item .btn-danger {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .metric-item .bg-light {
        background-color: var(--bs-light) !important;
    }
    
    [data-bs-theme="dark"] .metric-item .bg-light {
        background-color: var(--bs-dark) !important;
    }
    
    .metric-item .badge {
        font-size: 0.75rem;
    }
    
    /* Dark theme specific adjustments */
    [data-bs-theme="dark"] .metric-item {
        border-color: var(--bs-border-color);
        background-color: var(--bs-body-bg);
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    
    [data-bs-theme="dark"] .metric-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.4);
    }
    
    [data-bs-theme="dark"] .metric-item .form-control,
    [data-bs-theme="dark"] .metric-item .form-select {
        background-color: var(--bs-dark);
        border-color: var(--bs-border-color);
        color: var(--bs-body-color);
    }
    
    [data-bs-theme="dark"] .metric-item .form-control:focus,
    [data-bs-theme="dark"] .metric-item .form-select:focus {
        background-color: var(--bs-dark);
        border-color: var(--bs-primary);
        color: var(--bs-body-color);
    }
    
    [data-bs-theme="dark"] .metric-item .bg-light {
        background-color: rgba(255, 255, 255, 0.05) !important;
    }
    
    /* Text colors for dark theme */
    [data-bs-theme="dark"] .text-muted {
        color: rgba(255, 255, 255, 0.6) !important;
    }
    
    [data-bs-theme="dark"] .metric-item .form-label {
        color: var(--bs-body-color);
    }
    
    /* Button styling for dark theme */
    [data-bs-theme="dark"] .btn-outline-secondary {
        border-color: var(--bs-border-color);
        color: var(--bs-body-color);
    }
    
    [data-bs-theme="dark"] .btn-outline-secondary:hover {
        background-color: var(--bs-secondary);
        border-color: var(--bs-secondary);
        color: var(--bs-white);
    }
    
    [data-bs-theme="dark"] .btn-outline-primary {
        border-color: var(--bs-primary);
        color: var(--bs-primary);
    }
    
    [data-bs-theme="dark"] .btn-outline-primary:hover {
        background-color: var(--bs-primary);
        border-color: var(--bs-primary);
        color: var(--bs-white);
    }
    
    [data-bs-theme="dark"] .btn-outline-danger {
        border-color: var(--bs-danger);
        color: var(--bs-danger);
    }
    
    [data-bs-theme="dark"] .btn-outline-danger:hover {
        background-color: var(--bs-danger);
        border-color: var(--bs-danger);
        color: var(--bs-white);
    }
    
    /* Dark theme for metric headers */
    [data-bs-theme="dark"] .metric-item .bg-light {
        background-color: rgba(255, 255, 255, 0.05) !important;
        border-bottom-color: var(--bs-border-color) !important;
    }
    
    /* Dark theme for protocol configuration section */
    [data-bs-theme="dark"] .fw-bold {
        color: var(--bs-body-color);
    }
    
    /* Dark theme for placeholder text */
    [data-bs-theme="dark"] .form-control::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }
    
    [data-bs-theme="dark"] .form-select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
    }
    
    /* Dark theme for small text */
    [data-bs-theme="dark"] small {
        color: rgba(255, 255, 255, 0.6);
    }
    
    /* Dark theme for strong text */
    [data-bs-theme="dark"] strong {
        color: var(--bs-body-color);
    }
</style>

<!--begin::App Content-->
<div class="app-content">
  <div class="container-fluid">
    <form method="post" enctype="multipart/form-data" id="wizardForm" action="<?= base_url('device/save') ?>" onsubmit="return submitDeviceForm(event)">
      <div class="card">
        <div class="card-header">
          <ul class="nav nav-pills" id="wizardTabs">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#step1" type="button">1. <?php echo lang('select_template') ?></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#step2" type="button">2. <?php echo lang('device_info') ?></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#step3" type="button">3. <?php echo lang('communication') ?></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#step4" type="button">4. <?php echo lang('metrics') ?></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#step5" type="button">5. <?php echo lang('confirm') ?></button></li>
          </ul>
        </div>

        <div class="card-body tab-content">

          <!-- Step 1: Select Template -->
          <div class="tab-pane fade show active" id="step1">
            <div class="row mb-3">
              <div class="col-md-3">
                <input type="text" id="searchBox" class="form-control" placeholder="Search by name...">
              </div>
              <div class="col-md-2">
                <select id="filterCategory" class="form-select">
                  <option value="">All Categories</option>
                  <?php
                    $templateCategories = [];
                    foreach ($templates as $templateOption) {
                        $categoryName = trim((string) ($templateOption->category_name ?? ''));
                        if ($categoryName !== '') {
                            $templateCategories[$categoryName] = $categoryName;
                        }
                    }
                    ksort($templateCategories, SORT_NATURAL | SORT_FLAG_CASE);
                    foreach ($templateCategories as $categoryName):
                  ?>
                    <option value="<?= htmlspecialchars($categoryName) ?>"><?= htmlspecialchars($categoryName) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <select id="filterProtocol" class="form-select">
                  <option value="">All Protocols</option>
                  <?php foreach ([
                    'modbus_tcp', 'modbus_rtu', 'modbus_router',
                    'snmp', 'mqtt',
                    'bacnet_ip', 'bacnet_mstp', 'bacnet_router',
                    'restful', 'webhook', 'lorawan',
                    'gateway_endpoint', 'visual', 'rtsp'
                  ] as $proto): ?>
                    <option value="<?= $proto ?>"><?= strtoupper($proto) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <input type="text" id="filterBrand" class="form-control" placeholder="Brand">
              </div>
              <div class="col-md-2">
                <input type="text" id="filterModel" class="form-control" placeholder="Model">
              </div>
            </div>

            <div class="row" id="templateCards">
              <?php foreach ($templates as $t):
                $info = json_decode($t->device_information ?? '{}', true);
                $brand = $info['Brand'] ?? '-';
                $model = $info['Model'] ?? '-';
              ?>
                <div class="col-md-4 mb-3 template-card"
                     data-name="<?= htmlspecialchars($t->name) ?>"
                     data-category="<?= htmlspecialchars($t->category_name ?? '') ?>"
                     data-protocol="<?= htmlspecialchars($t->protocol) ?>"
                     data-brand="<?= htmlspecialchars($brand) ?>"
                     data-model="<?= htmlspecialchars($model) ?>">
                  <div class="card h-100 template-card-inner" onclick="selectTemplate(<?= $t->id ?>)">
                    <?php if (!empty($t->image)): ?>
                      <img src="<?= base_url('uploads/device_templates/' . $t->image) ?>" class="card-img-top" style="max-height:150px; object-fit:contain">
                    <?php endif; ?>
                    <div class="card-body">
                      <p class="card-text mb-1"><?php echo lang('name') ?> : <?= htmlspecialchars($t->name) ?></p>
                      <p class="card-text mb-1"><strong><?php echo lang('brand') ?> : </strong> <?= htmlspecialchars($brand) ?></p>
                      <p class="card-text"><strong><?php echo lang('model') ?> :</strong> <?= htmlspecialchars($model) ?></p>
                    <?php if (!empty($t->category_name)): ?>
                      <span class="badge bg-light text-dark border mb-2"><?= htmlspecialchars($t->category_name) ?></span><br>
                    <?php endif; ?>
                    <small class="text-muted"> <?php echo lang('protocol') ?> : <?= strtoupper($t->protocol) ?></small>
                    </div>
                    <input type="radio" name="device_template_id" value="<?= $t->id ?>" class="d-none">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="mt-3 text-end">
              <button type="button" class="btn btn-primary" onclick="goToStep(2)"><?php echo lang('next') ?></button>
            </div>
          </div>

          <!-- Step 2: Device Info -->
          <div class="tab-pane fade" id="step2">
            <?php include 'partials/device_info.php'; ?>
            <div class="card mt-3">
              <div class="card-header">
                <strong><?php echo lang('custom_field'); ?></strong>
              </div>
              <div class="card-body">
                <div id="custom-fields-list"></div>
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addCustomField()">+ <?php echo lang('add_custom_field'); ?></button>
                <input type="hidden" name="device_information_json" id="device_information_json">
              </div>
            </div>
          </div>
          <div class="tab-pane fade" id="step3"><?php include 'partials/communication_add.php'; ?></div>
          <div class="tab-pane fade" id="step4"><?php include 'partials/metrics_add.php'; ?></div>
          <div class="tab-pane fade" id="step5"><?php include 'partials/confirm.php'; ?></div>



        </div>
      </div>

    </form>
  </div>
</div>


<script>
  // Global variables
  let customFields = [];
  window.metrics = <?= isset($device) && $device->metrics ? json_encode(json_decode($device->metrics), JSON_UNESCAPED_UNICODE) : '[]' ?>;
  window.templates = <?= json_encode($templates ?? []) ?>;
  let currentProtocol = '';
  let availableChannels = <?php echo json_encode($channels ?? []); ?>;
  let templates = <?php echo json_encode($templates ?? []); ?>;
  let metrics = [];

  // เมื่อเลือก template
  function selectTemplate(templateId) {
      try {
          // Remove active class from all cards
          $('.template-card-inner').removeClass('border-primary border-3');
          $('.template-card-inner').removeClass('bg-primary bg-opacity-10');
          
          // Add active class to selected card
          const selectedCard = $(`.template-card:has(input[value="${templateId}"]) .template-card-inner`);
          selectedCard.addClass('border-primary border-3');
          selectedCard.addClass('bg-primary bg-opacity-10');
          
          // Set the radio button
          $(`input[name="device_template_id"][value="${templateId}"]`).prop('checked', true);
          
          // Load template preview
          $.ajax({
              url: '<?= base_url("device/ajax_template_preview/") ?>' + templateId,
              type: 'GET',
              success: function(response) {
                  console.log('Template preview:', response);
                  console.log('Communication config:', response.communication_config);
                  console.log('Metrics:', response.metrics);
                  console.log('Protocol:', response.protocol);
                  
                  try {
                      // Apply the role before other template renderers so endpoint rules
                      // still take effect if an optional renderer fails.
                      if (typeof applyTemplateDeviceRole === 'function') {
                          applyTemplateDeviceRole(response);
                      }

                      // Update communication fields
                      updateCommunicationFields(response.communication_config, response.protocol);
                      
                      // Update metrics fields
                      updateMetricsFields(response.metrics);
                      
                      // Load template device_information fields to custom fields
                      loadTemplateCustomFields(response.device_information);
                      
                      // Fill form with template data
                      fillFormWithTemplateData(response);

                      // Update protocol for LoRaWAN handling
                      currentProtocol = response.protocol;
                  } catch (error) {
                      console.error('Error processing template preview:', error);
                  }
              },
              error: function(xhr, status, error) {
                  console.error('Error loading template preview:', error);
                  console.error('Response:', xhr.responseText);
              }
          });
      } catch (error) {
          console.error('Error in selectTemplate:', error);
      }
  }

    // โหลด template device_information fields มาใส่ใน custom fields
    function loadTemplateCustomFields(templateDeviceInfo) {
        try {
            if (templateDeviceInfo && typeof templateDeviceInfo === 'object') {
                let addedFields = [];
                
                // เพิ่ม fields จาก template (ที่ไม่ใช่ Brand, Model) เข้าไปใน custom fields
                Object.keys(templateDeviceInfo).forEach(key => {
                    if (key !== 'Brand' && key !== 'Model' && templateDeviceInfo[key] && templateDeviceInfo[key].trim() !== '') {
                        // ตรวจสอบว่า field นี้มีอยู่แล้วหรือไม่
                        const existingIndex = customFields.findIndex(f => f.key === key);
                        if (existingIndex === -1) {
                            // ถ้าไม่มี ให้เพิ่มใหม่
                            customFields.push({ key: key, value: templateDeviceInfo[key] });
                            addedFields.push(key);
                        }
                    }
                });
                
                // แสดงข้อความแจ้งเตือนถ้ามีการเพิ่ม fields
                if (addedFields.length > 0) {
                    const fieldNames = addedFields.join(', ');
                    try {
                        showNotification(`โหลดข้อมูลจาก Template: ${fieldNames}`, 'info');
                    } catch (notifyError) {
                        console.log('Notification shown:', `โหลดข้อมูลจาก Template: ${fieldNames}`);
                    }
                }
                
                renderCustomFields();
            }
        } catch (error) {
            console.error('Error in loadTemplateCustomFields:', error);
            // Continue without showing notification if there's an error
        }
    }

    // ฟังก์ชันแสดง notification
    function showNotification(message, type = 'info') {
        // Check if global showNotification exists (from custom.js or other files)
        if (typeof window.showNotification === 'function' && window.showNotification !== showNotification) {
            try {
                window.showNotification(message, type);
                return;
            } catch (error) {
                console.log('Global showNotification failed, using fallback');
            }
        }
        
        // Check if $.notify is available
        if (typeof $.notify === 'function') {
            try {
                $.notify(message, type);
                return;
            } catch (error) {
                console.log('$.notify failed, using fallback');
            }
        }
        
        // Fallback to Bootstrap alert
        const alertClass = type === 'info' ? 'alert-info' : 'alert-success';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // แสดง notification ด้านบนของ custom fields section
        const customFieldsSection = document.querySelector('#step2 .card-body');
        if (customFieldsSection) {
            const existingAlert = customFieldsSection.querySelector('.alert');
            if (existingAlert) {
                existingAlert.remove();
            }
            customFieldsSection.insertAdjacentHTML('afterbegin', alertHtml);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                const alert = customFieldsSection.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }
    }

    // ฟังก์ชันเติมข้อมูลในฟอร์มจาก template
    function fillFormWithTemplateData(templateData) {
        try {
            // Fill device information from template
            if (templateData.device_information) {
                const deviceInfo = templateData.device_information;
                
                // Fill Brand and Model in template fields (readonly)
                if (deviceInfo.Brand) {
                    const brandInput = document.getElementById('templateBrand');
                    if (brandInput) brandInput.value = deviceInfo.Brand;
                }
                
                if (deviceInfo.Model) {
                    const modelInput = document.getElementById('templateModel');
                    if (modelInput) modelInput.value = deviceInfo.Model;
                }
                
                // Fill other device information fields if they exist
                if (deviceInfo.Serial_Number) {
                    const serialInput = document.querySelector('input[name="serial_number"]');
                    if (serialInput && !serialInput.value) serialInput.value = deviceInfo.Serial_Number;
                }
                
                // Fill device name with template name if empty
                const nameInput = document.querySelector('input[name="name"]');
                if (nameInput && !nameInput.value && templateData.name) {
                    nameInput.value = templateData.name;
                }
                
                // Fill description with template description if empty
                if (templateData.description) {
                    const descInput = document.querySelector('textarea[name="description"]');
                    if (descInput && !descInput.value) descInput.value = templateData.description;
                }
            }
            
            console.log('Form filled with template data:', templateData);
        } catch (error) {
            console.error('Error filling form with template data:', error);
        }
    }

    // Update communication fields based on protocol
    function updateCommunicationFields(commConfig, protocol) {
        console.log('Updating communication fields for protocol:', protocol, commConfig);
        
        // Set the commConfig global variable
        window.commConfig = commConfig || {};
        
        // Render communication fields using the unified function
        renderCommFields();
        if (typeof window.toggleRtspSnapshotPanel === 'function') {
            window.toggleRtspSnapshotPanel();
        }
    }

    // Update metrics fields
    function updateMetricsFields(metrics) {
        console.log('Updating metrics fields:', metrics);
        
        // Clear existing metrics
        const metricsContainer = document.getElementById('metricsFields');
        if (metricsContainer) {
            metricsContainer.innerHTML = '';
        }
        
        // Set global metrics variable
        window.metrics = metrics || [];
        
        // Render metrics
        renderMetrics();
        
        // Update hidden JSON field
        updateMetricsJSON();
    }

    // Render LoRaWAN communication fields
    function renderLoRaWANFields(commConfig) {
        const container = document.getElementById('communicationFields');
        if (!container) return;
        
        container.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Dev EUI</label>
                        <input type="text" class="form-control" name="dev_eui" value="${commConfig.dev_eui || ''}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">App EUI</label>
                        <input type="text" class="form-control" name="app_eui" value="${commConfig.app_eui || ''}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Device Profile ID</label>
                        <input type="text" class="form-control" name="device_profile_id" value="${commConfig.device_profile_id || ''}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Application Server</label>
                        <select class="form-select" name="application_server" required>
                            <option value="">Select Application Server</option>
                            ${availableChannels.map(ch => `<option value="${ch.id}" ${commConfig.application_server == ch.id ? 'selected' : ''}>${ch.name}</option>`).join('')}
                        </select>
                    </div>
                </div>
            </div>
        `;
    }

    // Render Modbus communication fields
    function renderModbusFields(commConfig) {
        const container = document.getElementById('communicationFields');
        if (!container) return;
        
        container.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" class="form-control" name="ip_address" value="${commConfig.ip_address || ''}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Port</label>
                        <input type="number" class="form-control" name="port" value="${commConfig.port || '502'}" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Slave ID</label>
                        <input type="number" class="form-control" name="slave_id" value="${commConfig.slave_id || '1'}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Timeout (ms)</label>
                        <input type="number" class="form-control" name="timeout" value="${commConfig.timeout || '5000'}" required>
                    </div>
                </div>
            </div>
        `;
    }

    // Render SNMP communication fields
    function renderSNMPFields(commConfig) {
        const container = document.getElementById('communicationFields');
        if (!container) return;
        
        container.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" class="form-control" name="ip_address" value="${commConfig.ip_address || ''}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">SNMP Version</label>
                        <select class="form-select" name="version" id="snmp_version" onchange="toggleSNMPV3Fields()" required>
                            <option value="1" ${commConfig.version == '1' ? 'selected' : ''}>SNMP v1</option>
                            <option value="2c" ${commConfig.version == '2c' ? 'selected' : ''}>SNMP v2c</option>
                            <option value="3" ${commConfig.version == '3' ? 'selected' : ''}>SNMP v3</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- SNMP v1/v2c fields -->
            <div id="snmp_v1v2c_fields" class="row" style="display: ${commConfig.version == '3' ? 'none' : 'flex'}">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Community String</label>
                        <input type="text" class="form-control" name="community" value="${commConfig.community || 'public'}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Timeout (ms)</label>
                        <input type="number" class="form-control" name="timeout" value="${commConfig.timeout || '5000'}" required>
                    </div>
                </div>
            </div>
            
            <!-- SNMP v3 fields -->
            <div id="snmp_v3_fields" class="row" style="display: ${commConfig.version == '3' ? 'flex' : 'none'}">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" value="${commConfig.username || ''}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Security Level</label>
                        <select class="form-select" name="security_level" id="security_level" onchange="toggleSNMPV3AuthFields()">
                            <option value="noAuthNoPriv" ${commConfig.security_level == 'noAuthNoPriv' ? 'selected' : ''}>No Auth No Priv</option>
                            <option value="authNoPriv" ${commConfig.security_level == 'authNoPriv' ? 'selected' : ''}>Auth No Priv</option>
                            <option value="authPriv" ${commConfig.security_level == 'authPriv' ? 'selected' : ''}>Auth Priv</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- SNMP v3 Authentication fields -->
            <div id="snmp_v3_auth_fields" class="row" style="display: ${(commConfig.security_level == 'authNoPriv' || commConfig.security_level == 'authPriv') ? 'flex' : 'none'}">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Auth Protocol</label>
                        <select class="form-select" name="auth_protocol">
                            <option value="MD5" ${commConfig.auth_protocol == 'MD5' ? 'selected' : ''}>MD5</option>
                            <option value="SHA" ${commConfig.auth_protocol == 'SHA' ? 'selected' : ''}>SHA</option>
                            <option value="SHA-224" ${commConfig.auth_protocol == 'SHA-224' ? 'selected' : ''}>SHA-224</option>
                            <option value="SHA-256" ${commConfig.auth_protocol == 'SHA-256' ? 'selected' : ''}>SHA-256</option>
                            <option value="SHA-384" ${commConfig.auth_protocol == 'SHA-384' ? 'selected' : ''}>SHA-384</option>
                            <option value="SHA-512" ${commConfig.auth_protocol == 'SHA-512' ? 'selected' : ''}>SHA-512</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Auth Password</label>
                        <input type="password" class="form-control" name="auth_password" value="${commConfig.auth_password || ''}">
                    </div>
                </div>
            </div>
            
            <!-- SNMP v3 Privacy fields -->
            <div id="snmp_v3_priv_fields" class="row" style="display: ${commConfig.security_level == 'authPriv' ? 'flex' : 'none'}">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Priv Protocol</label>
                        <select class="form-select" name="priv_protocol">
                            <option value="DES" ${commConfig.priv_protocol == 'DES' ? 'selected' : ''}>DES</option>
                            <option value="AES" ${commConfig.priv_protocol == 'AES' ? 'selected' : ''}>AES</option>
                            <option value="AES-192" ${commConfig.priv_protocol == 'AES-192' ? 'selected' : ''}>AES-192</option>
                            <option value="AES-256" ${commConfig.priv_protocol == 'AES-256' ? 'selected' : ''}>AES-256</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Priv Password</label>
                        <input type="password" class="form-control" name="priv_password" value="${commConfig.priv_password || ''}">
                    </div>
                </div>
            </div>
            
            <!-- Common SNMP v3 fields -->
            <div id="snmp_v3_common_fields" class="row" style="display: ${commConfig.version == '3' ? 'flex' : 'none'}">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Timeout (ms)</label>
                        <input type="number" class="form-control" name="timeout" value="${commConfig.timeout || '5000'}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Retries</label>
                        <input type="number" class="form-control" name="retries" value="${commConfig.retries || '3'}" required>
                    </div>
                </div>
            </div>
        `;
        
        // Initialize field visibility
        toggleSNMPV3Fields();
        toggleSNMPV3AuthFields();
    }

    // Render MQTT communication fields
    function renderMQTTFields(commConfig) {
        const container = document.getElementById('communicationFields');
        if (!container) return;
        
        container.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Broker URL</label>
                        <input type="text" class="form-control" name="broker_url" value="${commConfig.broker_url || ''}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Port</label>
                        <input type="number" class="form-control" name="port" value="${commConfig.port || '1883'}" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" value="${commConfig.username || ''}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" value="${commConfig.password || ''}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Topic</label>
                        <input type="text" class="form-control" name="topic" value="${commConfig.topic || ''}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Client ID</label>
                        <input type="text" class="form-control" name="client_id" value="${commConfig.client_id || ''}">
                    </div>
                </div>
            </div>
        `;
    }

    // Render generic communication fields
    function renderGenericFields(commConfig) {
        const container = document.getElementById('communicationFields');
        if (!container) return;
        
        container.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Connection String</label>
                        <input type="text" class="form-control" name="connection_string" value="${commConfig.connection_string || ''}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Timeout (ms)</label>
                        <input type="number" class="form-control" name="timeout" value="${commConfig.timeout || '5000'}" required>
                    </div>
                </div>
            </div>
        `;
    }

    // Update communication config JSON
    function updateCommConfigJSON() {
        const container = document.getElementById('communicationFields');
        if (!container) return;
        
        const formData = {};
        const inputs = container.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            if (input.name) {
                formData[input.name] = input.value;
            }
        });
        
        document.getElementById('comm_config_json').value = JSON.stringify(formData);
    }

    // Update metrics JSON
    function updateMetricsJSON() {
        document.getElementById('metrics_json').value = JSON.stringify(window.metrics || []);
    }

    // Custom fields functions
    function renderCustomFields() {
        const list = document.getElementById('custom-fields-list');
        if (!list) return;
        
        list.innerHTML = '';
        if (customFields.length === 0) {
            list.innerHTML = '<div class="text-muted"><?php echo lang("no_additional_info"); ?></div>';
            updateDeviceInfoJSON();
            return;
        }
        customFields.forEach((f, i) => {
            list.innerHTML += `
                <div class="row mb-2 align-items-center" data-index="${i}">
                    <div class="col-md-5">
                        <input type="text" class="form-control" placeholder="ชื่อฟิลด์" value="${f.key}" onchange="updateCustomField(${i}, 'key', this.value)">
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" placeholder="ค่า" value="${f.value}" onchange="updateCustomField(${i}, 'value', this.value)">
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeCustomField(${i})">ลบ</button>
                    </div>
                </div>
            `;
        });
        updateDeviceInfoJSON();
    }

    function addCustomField() {
        customFields.push({ key: '', value: '' });
        renderCustomFields();
    }

    function updateCustomField(index, field, value) {
        customFields[index][field] = value;
        updateDeviceInfoJSON();
    }

    function removeCustomField(index) {
        customFields.splice(index, 1);
        renderCustomFields();
    }

    function updateDeviceInfoJSON() {
        // สร้าง object จาก customFields
        const info = {};
        customFields.forEach(f => {
            if (f.key.trim() !== '') info[f.key] = f.value;
        });
        document.getElementById('device_information_json').value = JSON.stringify(info);
    }

    // Metrics functions
    function renderMetrics() {
        const container = document.getElementById('metrics-container');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (!window.metrics || window.metrics.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-graph-up text-muted" style="font-size: 3rem; color: var(--bs-secondary);"></i>
                    <p class="text-muted mt-2">ไม่มี metrics ที่กำหนด</p>
                    <button type="button" class="btn btn-primary" onclick="addMetric()">
                        <i class="bi bi-plus-circle"></i> เพิ่ม Metric แรก
                    </button>
                </div>
            `;
            return;
        }
        
        // Get current protocol
        const selected = document.querySelector("input[name='device_template_id']:checked");
        const templateId = selected?.value;
        const template = window.templates?.find(t => t.id == templateId);
        const protocol = template?.protocol || 'modbus_tcp';
        
        // Protocol-specific fields
        const protocolFields = {
            modbus_tcp: ['address', 'function_code', 'length', 'type'],
            modbus_rtu: ['address', 'function_code', 'length', 'type'],
            snmp: ['oid', 'type'],
            mqtt: ['topic', 'json_path'],
            bacnet: ['object_id', 'property_id'],
            lorawan: []
        };
        
        // Add metrics summary header
        const activeMetrics = window.metrics.filter(m => m.enable === 'true').length;
        const totalMetrics = window.metrics.length;
        
        container.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="mb-0">Metrics Configuration</h6>
                    <small class="text-muted">${activeMetrics} active / ${totalMetrics} total metrics</small>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="expandAllMetrics()">
                        <i class="bi bi-chevron-down"></i> Expand All
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="collapseAllMetrics()">
                        <i class="bi bi-chevron-up"></i> Collapse All
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addMetric()">
                        <i class="bi bi-plus-circle"></i> เพิ่ม Metric
                    </button>
                </div>
            </div>
        `;
        
        window.metrics.forEach((metric, index) => {
            const dynamicFields = protocolFields[protocol] || [];
            const isActive = metric.enable === 'true';
            const statusColor = isActive ? 'success' : 'secondary';
            const statusIcon = isActive ? 'bi-check-circle-fill' : 'bi-pause-circle-fill';
            // Default expanded to false if not set
            if (typeof metric.expanded === 'undefined') metric.expanded = false;
            const detailsDisplay = metric.expanded ? 'block' : 'none';
            const chevronClass = metric.expanded ? 'bi-chevron-up' : 'bi-chevron-down';
            
            const metricHtml = `
                <div class="border rounded mb-3 metric-item ${isActive ? 'border-success' : 'border-secondary'}" data-index="${index}">
                    <!-- Metric Header -->
                    <div class="d-flex justify-content-between align-items-center p-3 bg-light border-bottom">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-${statusColor} me-2">
                                <i class="bi ${statusIcon}"></i>
                            </span>
                            <div>
                                <strong>${metric.key || 'Unnamed Metric'}</strong>
                                ${metric.label ? `<br><small class="text-muted">${metric.label}</small>` : ''}
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleMetricDetails(${index})">
                                <i class="bi ${chevronClass}" id="chevron-${index}"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMetric(${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Metric Details (Collapsible) -->
                    <div class="metric-details" id="details-${index}" style="display: ${detailsDisplay};">
                        <div class="p-3">
                            <!-- Basic Info Row -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select form-select-sm" onchange="updateMetricField(${index}, 'enable', this.value)">
                                        <option value="false" ${metric.enable === 'false' ? 'selected' : ''}>Inactive</option>
                                        <option value="true" ${metric.enable === 'true' ? 'selected' : ''}>Active</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Metric Key</label>
                                    <input type="text" class="form-control form-control-sm" value="${metric.key || ''}" onchange="updateMetricField(${index}, 'key', this.value)" placeholder="Enter key">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Label</label>
                                    <input type="text" class="form-control form-control-sm" value="${metric.label || ''}" onchange="updateMetricField(${index}, 'label', this.value)" placeholder="Display name">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" class="form-control form-control-sm" value="${metric.unit || ''}" onchange="updateMetricField(${index}, 'unit', this.value)" placeholder="e.g., °C, %">
                                </div>
                            </div>
                            
                            <!-- Data Type Row -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Data Type</label>
                                    ${(protocol === 'modbus_tcp' || protocol === 'modbus_rtu') ? `
                                    <select class="form-select form-select-sm" onchange="updateMetricField(${index}, 'datatype', this.value)">
                                        <option value="int16" ${metric.datatype === 'int16' ? 'selected' : ''}>Int16 (Signed 16-bit)</option>
                                        <option value="uint16" ${metric.datatype === 'uint16' ? 'selected' : ''}>UInt16 (Unsigned 16-bit)</option>
                                        <option value="int32" ${metric.datatype === 'int32' ? 'selected' : ''}>Int32 (Signed 32-bit)</option>
                                        <option value="uint32" ${metric.datatype === 'uint32' ? 'selected' : ''}>UInt32 (Unsigned 32-bit)</option>
                                        <option value="float32" ${metric.datatype === 'float32' ? 'selected' : ''}>Float32 (32-bit Float)</option>
                                        <option value="float32_swap" ${metric.datatype === 'float32_swap' ? 'selected' : ''}>Float32 Swap (32-bit Float - Word Swap)</option>
                                        <option value="float32_fullswap" ${metric.datatype === 'float32_fullswap' ? 'selected' : ''}>Float32 Full Swap (32-bit Float - Byte Swap)</option>
                                    </select>
                                    ` : `
                                    <input type="text" class="form-control form-control-sm" value="${metric.datatype || 'int'}" onchange="updateMetricField(${index}, 'datatype', this.value)">
                                    `}
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Scale</label>
                                    <input type="number" class="form-control form-control-sm" step="any" value="${metric.scale || 1}" onchange="updateMetricField(${index}, 'scale', parseFloat(this.value))">
                                </div>
                            </div>
                            
                            <!-- Pull Rate (if applicable) -->
                            ${(protocol === 'modbus_tcp' || protocol === 'modbus_rtu' || 
                               (protocol === 'snmp' && metric.type !== 'trap') || 
                               protocol === 'bacnet') ? `
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Pull Rate (Second)</label>
                                    <input type="number" class="form-control form-control-sm" min="1" value="${metric.pull_rate || 1}" onchange="updateMetricField(${index}, 'pull_rate', parseInt(this.value))" placeholder="Enter minutes">
                                </div>
                            </div>
                            ` : ''}
                            
                            <!-- Store Policy Row -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Store Policy</label>
                                    <select class="form-select form-select-sm" onchange="updateMetricField(${index}, 'store_policy', this.value)">
                                        <option value="always" ${metric.store_policy === 'always' ? 'selected' : ''}>Always</option>
                                        <option value="on_change" ${metric.store_policy === 'on_change' ? 'selected' : ''}>On Change</option>
                                        <option value="interval" ${metric.store_policy === 'interval' ? 'selected' : ''}>Interval</option>
                                        <option value="none" ${metric.store_policy === 'none' ? 'selected' : ''}>None</option>
                                    </select>
                                </div>
                                ${metric.store_policy === 'interval' ? `
                                <div class="col-md-3">
                                    <label class="form-label">Store Interval (minutes)</label>
                                    <input type="number" class="form-control form-control-sm" value="${metric.interval_minutes || 5}" onchange="updateMetricField(${index}, 'interval_minutes', parseInt(this.value))">
                                </div>
                                ` : ''}
                            </div>
                            
                            <!-- Protocol-specific fields -->
                            ${dynamicFields.length > 0 ? `
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Protocol Configuration</label>
                                </div>
                                ${dynamicFields.map(field => {
                                    if (field === 'function_code' && (protocol === 'modbus_tcp' || protocol === 'modbus_rtu')) {
                                        return `
                                        <div class="col-md-6">
                                            <label class="form-label">Function Code</label>
                                            <select class="form-select form-select-sm" onchange="updateMetricField(${index}, 'function_code', this.value)">
                                                <option value="1" ${metric.function_code == '1' ? 'selected' : ''}>1 - Read Coils</option>
                                                <option value="2" ${metric.function_code == '2' ? 'selected' : ''}>2 - Read Discrete Inputs</option>
                                                <option value="3" ${metric.function_code == '3' ? 'selected' : ''}>3 - Read Holding Registers</option>
                                                <option value="4" ${metric.function_code == '4' ? 'selected' : ''}>4 - Read Input Registers</option>
                                            </select>
                                        </div>
                                        `;
                                    } else {
                                        return `
                                        <div class="col-md-6">
                                            <label class="form-label">${field.charAt(0).toUpperCase() + field.slice(1)}</label>
                                            <input type="text" class="form-control form-control-sm" placeholder="${field}" value="${metric[field] || ''}" onchange="updateMetricField(${index}, '${field}', this.value)">
                                        </div>
                                        `;
                                    }
                                }).join('')}
                            </div>
                            ` : ''}
                            ${renderTriggers(metric, index)}
                        </div>
                    </div>
                </div>
            `;
            container.innerHTML += metricHtml;
        });
    }

    // Add metric function
    function addMetric() {
        if (!window.metrics) {
            window.metrics = [];
        }
        
        // Get current protocol
        const selected = document.querySelector("input[name='device_template_id']:checked");
        const templateId = selected?.value;
        const template = window.templates?.find(t => t.id == templateId);
        const protocol = template?.protocol || 'modbus_tcp';
        
        const newMetric = {
            id: MD5(unescape(encodeURIComponent(new Date().toISOString()))),
            key: '',
            label: '',
            unit: '',
            datatype: 'int',
            scale: 1,
            enable: 'true',
            pull_rate: 10000,
            store_policy: 'always',
            interval_minutes: 5,
            protocol: protocol
        };
        
        // Add protocol-specific fields
        const protocolFields = {
            modbus_tcp: ['address', 'function_code', 'length', 'type'],
            modbus_rtu: ['address', 'function_code', 'length', 'type'],
            snmp: ['oid', 'type'],
            mqtt: ['topic', 'json_path'],
            bacnet: ['object_id', 'property_id'],
            lorawan: []
        };
        
        const dynamicFields = protocolFields[protocol] || [];
        dynamicFields.forEach(field => {
            newMetric[field] = '';
        });
        
        // Set default values for Modbus
        if (protocol === 'modbus_tcp' || protocol === 'modbus_rtu') {
            newMetric.function_code = '3';
            newMetric.length = '1';
            newMetric.type = 'modbus_read';
        }
        
        window.metrics.push(newMetric);
        renderMetrics();
        updateMetricsJSON();
    }

    // Update metric field function
    function updateMetricField(index, field, value) {
        if (window.metrics && window.metrics[index]) {
            window.metrics[index][field] = value;
            // Always re-render if store_policy changes to show/hide interval field
            if (field === 'store_policy') {
                renderMetrics();
            }
            updateMetricsJSON();
        }
    }

    // Remove metric function
    function removeMetric(index) {
        if (window.metrics && window.metrics[index]) {
            window.metrics.splice(index, 1);
            renderMetrics();
            updateMetricsJSON();
        }
    }

    // Toggle metric details function
    function toggleMetricDetails(index) {
        if (window.metrics && window.metrics[index]) {
            window.metrics[index].expanded = !window.metrics[index].expanded;
            renderMetrics();
        }
    }

    // Expand all metrics function
    function expandAllMetrics() {
        window.metrics.forEach((m) => { m.expanded = true; });
        renderMetrics();
    }

    // Collapse all metrics function
    function collapseAllMetrics() {
        window.metrics.forEach((m) => { m.expanded = false; });
        renderMetrics();
    }

    // MD5 function for generating unique IDs
    function MD5(string) {
        function RotateLeft(lValue, iShiftBits) {
            return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
        }
        function AddUnsigned(lX, lY) {
            var lX4, lY4, lX8, lY8, lResult;
            lX8 = (lX & 0x80000000);
            lY8 = (lY & 0x80000000);
            lX4 = (lX & 0x40000000);
            lY4 = (lY & 0x40000000);
            lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
            if (lX4 & lY4) {
                return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
            }
            if (lX4 | lY4) {
                if (lResult & 0x40000000) {
                    return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
                } else {
                    return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
                }
            } else {
                return (lResult ^ lX8 ^ lY8);
            }
        }
        function F(x, y, z) { return (x & y) | ((~x) & z); }
        function G(x, y, z) { return (x & z) | (y & (~z)); }
        function H(x, y, z) { return (x ^ y ^ z); }
        function I(x, y, z) { return (y ^ (x | (~z))); }
        function FF(a, b, c, d, x, s, ac) {
            a = AddUnsigned(a, AddUnsigned(AddUnsigned(F(b, c, d), x), ac));
            return AddUnsigned(RotateLeft(a, s), b);
        }
        function GG(a, b, c, d, x, s, ac) {
            a = AddUnsigned(a, AddUnsigned(AddUnsigned(G(b, c, d), x), ac));
            return AddUnsigned(RotateLeft(a, s), b);
        }
        function HH(a, b, c, d, x, s, ac) {
            a = AddUnsigned(a, AddUnsigned(AddUnsigned(H(b, c, d), x), ac));
            return AddUnsigned(RotateLeft(a, s), b);
        }
        function II(a, b, c, d, x, s, ac) {
            a = AddUnsigned(a, AddUnsigned(AddUnsigned(I(b, c, d), x), ac));
            return AddUnsigned(RotateLeft(a, s), b);
        }
        function ConvertToWordArray(string) {
            var lWordCount;
            var lMessageLength = string.length;
            var lNumberOfWords_temp1 = lMessageLength + 8;
            var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64;
            var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16;
            var lWordArray = Array(lNumberOfWords - 1);
            var lBytePosition = 0;
            var lByteCount = 0;
            while (lByteCount < lMessageLength) {
                lWordCount = (lByteCount - (lByteCount % 4)) / 4;
                lBytePosition = (lByteCount % 4) * 8;
                lWordArray[lWordCount] = (lWordArray[lWordCount] | (string.charCodeAt(lByteCount) << lBytePosition));
                lByteCount++;
            }
            lWordCount = (lByteCount - (lByteCount % 4)) / 4;
            lBytePosition = (lByteCount % 4) * 8;
            lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
            lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
            lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
            return lWordArray;
        }
        function WordToHex(lValue) {
            var WordToHexValue = "", WordToHexValue_temp = "", lByte, lCount;
            for (lCount = 0; lCount <= 3; lCount++) {
                lByte = (lValue >>> (lCount * 8)) & 255;
                WordToHexValue_temp = "0" + lByte.toString(16);
                WordToHexValue = WordToHexValue + WordToHexValue_temp.substr(WordToHexValue_temp.length - 2, 2);
            }
            return WordToHexValue;
        }
        function Utf8Encode(string) {
            string = string.replace(/\r\n/g, "\n");
            var utftext = "";
            for (var n = 0; n < string.length; n++) {
                var c = string.charCodeAt(n);
                if (c < 128) {
                    utftext += String.fromCharCode(c);
                }
                else if ((c > 127) && (c < 2048)) {
                    utftext += String.fromCharCode((c >> 6) | 192);
                    utftext += String.fromCharCode((c & 63) | 128);
                }
                else {
                    utftext += String.fromCharCode((c >> 12) | 224);
                    utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                    utftext += String.fromCharCode((c & 63) | 128);
                }
            }
            return utftext;
        }
        var x = Array();
        var k, AA, BB, CC, DD, a, b, c, d;
        var S11 = 7, S12 = 12, S13 = 17, S14 = 22;
        var S21 = 5, S22 = 9, S23 = 14, S24 = 20;
        var S31 = 4, S32 = 11, S33 = 16, S34 = 23;
        var S41 = 6, S42 = 10, S43 = 15, S44 = 21;
        string = Utf8Encode(string);
        x = ConvertToWordArray(string);
        a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;
        for (k = 0; k < x.length; k += 16) {
            AA = a; BB = b; CC = c; DD = d;
            a = FF(a, b, c, d, x[k + 0], S11, 0xD76AA478);
            d = FF(d, a, b, c, x[k + 1], S12, 0xE8C7B756);
            c = FF(c, d, a, b, x[k + 2], S13, 0x242070DB);
            b = FF(b, c, d, a, x[k + 3], S14, 0xC1BDCEEE);
            a = FF(a, b, c, d, x[k + 4], S11, 0xF57C0FAF);
            d = FF(d, a, b, c, x[k + 5], S12, 0x4787C62A);
            c = FF(c, d, a, b, x[k + 6], S13, 0xA8304613);
            b = FF(b, c, d, a, x[k + 7], S14, 0xFD469501);
            a = FF(a, b, c, d, x[k + 8], S11, 0x698098D8);
            d = FF(d, a, b, c, x[k + 9], S12, 0x8B44F7AF);
            c = FF(c, d, a, b, x[k + 10], S13, 0xFFFF5BB1);
            b = FF(b, c, d, a, x[k + 11], S14, 0x895CD7BE);
            a = FF(a, b, c, d, x[k + 12], S11, 0x6B901122);
            d = FF(d, a, b, c, x[k + 13], S12, 0xFD987193);
            c = FF(c, d, a, b, x[k + 14], S13, 0xA679438E);
            b = FF(b, c, d, a, x[k + 15], S14, 0x49B40821);
            a = GG(a, b, c, d, x[k + 1], S21, 0xF61E2562);
            d = GG(d, a, b, c, x[k + 6], S22, 0xC040B340);
            c = GG(c, d, a, b, x[k + 11], S23, 0x265E5A51);
            b = GG(b, c, d, a, x[k + 0], S24, 0xE9B6C7AA);
            a = GG(a, b, c, d, x[k + 5], S21, 0xD62F105D);
            d = GG(d, a, b, c, x[k + 10], S22, 0x2441453);
            c = GG(c, d, a, b, x[k + 15], S23, 0xD8A1E681);
            b = GG(b, c, d, a, x[k + 4], S24, 0xE7D3FBC8);
            a = GG(a, b, c, d, x[k + 9], S21, 0x21E1CDE6);
            d = GG(d, a, b, c, x[k + 14], S22, 0xC33707D6);
            c = GG(c, d, a, b, x[k + 3], S23, 0xF4D50D87);
            b = GG(b, c, d, a, x[k + 8], S24, 0x455A14ED);
            a = GG(a, b, c, d, x[k + 13], S21, 0xA9E3E905);
            d = GG(d, a, b, c, x[k + 2], S22, 0xFCEFA3F8);
            c = GG(c, d, a, b, x[k + 7], S23, 0x676F02D9);
            b = GG(b, c, d, a, x[k + 12], S24, 0x8D2A4C8A);
            a = HH(a, b, c, d, x[k + 5], S31, 0xFFFA3942);
            d = HH(d, a, b, c, x[k + 8], S32, 0x8771F681);
            c = HH(c, d, a, b, x[k + 11], S33, 0x6D9D6122);
            b = HH(b, c, d, a, x[k + 14], S34, 0xFDE5380C);
            a = HH(a, b, c, d, x[k + 1], S31, 0xA4BEEA44);
            d = HH(d, a, b, c, x[k + 4], S32, 0x4BDECFA9);
            c = HH(c, d, a, b, x[k + 7], S33, 0xF6BB4B60);
            b = HH(b, c, d, a, x[k + 10], S34, 0xBEBFBC70);
            a = HH(a, b, c, d, x[k + 13], S31, 0x289B7EC6);
            d = HH(d, a, b, c, x[k + 0], S32, 0xEAA127FA);
            c = HH(c, d, a, b, x[k + 3], S33, 0xD4EF3085);
            b = HH(b, c, d, a, x[k + 6], S34, 0x4881D05);
            a = HH(a, b, c, d, x[k + 9], S31, 0xD9D4D039);
            d = HH(d, a, b, c, x[k + 12], S32, 0xE6DB99E5);
            c = HH(c, d, a, b, x[k + 15], S33, 0x1FA27CF8);
            b = HH(b, c, d, a, x[k + 2], S34, 0xC4AC5665);
            a = II(a, b, c, d, x[k + 0], S41, 0xF4292244);
            d = II(d, a, b, c, x[k + 7], S42, 0x432AFF97);
            c = II(c, d, a, b, x[k + 14], S43, 0xAB9423A7);
            b = II(b, c, d, a, x[k + 5], S44, 0xFC93A039);
            a = II(a, b, c, d, x[k + 12], S41, 0x655B59C3);
            d = II(d, a, b, c, x[k + 3], S42, 0x8F0CCC92);
            c = II(c, d, a, b, x[k + 10], S43, 0xFFEFF47D);
            b = II(b, c, d, a, x[k + 1], S44, 0x85845DD1);
            a = II(a, b, c, d, x[k + 8], S41, 0x6FA87E4F);
            d = II(d, a, b, c, x[k + 15], S42, 0xFE2CE6E0);
            c = II(c, d, a, b, x[k + 6], S43, 0xA3014314);
            b = II(b, c, d, a, x[k + 13], S44, 0x4E0811A1);
            a = II(a, b, c, d, x[k + 4], S41, 0xF7537E82);
            d = II(d, a, b, c, x[k + 11], S42, 0xBD3AF235);
            c = II(c, d, a, b, x[k + 2], S43, 0x2AD7D2BB);
            b = II(b, c, d, a, x[k + 9], S44, 0xEB86D391);
            a = AddUnsigned(a, AA);
            b = AddUnsigned(b, BB);
            c = AddUnsigned(c, CC);
            d = AddUnsigned(d, DD);
        }
        var temp = WordToHex(a) + WordToHex(b) + WordToHex(c) + WordToHex(d);
        return temp.toLowerCase();
    }

    // SNMP V3 field toggle functions
    function toggleSNMPV3Fields() {
        const version = document.getElementById('snmp_version')?.value;
        const v1v2cFields = document.getElementById('snmp_v1v2c_fields');
        const v3Fields = document.getElementById('snmp_v3_fields');
        const v3CommonFields = document.getElementById('snmp_v3_common_fields');
        
        if (version === '3') {
            if (v1v2cFields) v1v2cFields.style.display = 'none';
            if (v3Fields) v3Fields.style.display = 'flex';
            if (v3CommonFields) v3CommonFields.style.display = 'flex';
        } else {
            if (v1v2cFields) v1v2cFields.style.display = 'flex';
            if (v3Fields) v3Fields.style.display = 'none';
            if (v3CommonFields) v3CommonFields.style.display = 'none';
        }
        
        // Update required attributes
        const communityField = document.querySelector('input[name="community"]');
        const usernameField = document.querySelector('input[name="username"]');
        
        if (communityField) {
            communityField.required = (version !== '3');
        }
        if (usernameField) {
            usernameField.required = (version === '3');
        }
        
        // Update JSON
        updateCommConfigJSON();
    }

    function toggleSNMPV3AuthFields() {
        const securityLevel = document.getElementById('security_level')?.value;
        const authFields = document.getElementById('snmp_v3_auth_fields');
        const privFields = document.getElementById('snmp_v3_priv_fields');
        
        if (authFields) {
            authFields.style.display = (securityLevel === 'authNoPriv' || securityLevel === 'authPriv') ? 'flex' : 'none';
        }
        
        if (privFields) {
            privFields.style.display = (securityLevel === 'authPriv') ? 'flex' : 'none';
        }
        
        // Update required attributes
        const authPasswordField = document.querySelector('input[name="auth_password"]');
        const privPasswordField = document.querySelector('input[name="priv_password"]');
        
        if (authPasswordField) {
            authPasswordField.required = (securityLevel === 'authNoPriv' || securityLevel === 'authPriv');
        }
        if (privPasswordField) {
            privPasswordField.required = (securityLevel === 'authPriv');
        }
        
        // Update JSON
        updateCommConfigJSON();
    }

    // Simple navigation function
    function goToStep(index) {
        if (index === 2 && !validateStep1()) return;
        if (index === 3 && !validateStep2()) return;
        if (index === 4 && !validateStep3()) return;
        if (index === 5) showConfirmSummary();
        const tabTrigger = new bootstrap.Tab(document.querySelectorAll('#wizardTabs .nav-link')[index - 1]);
        tabTrigger.show();
        if (index === 2 && typeof window.initSiteNodeSelect2 === 'function') {
            window.initSiteNodeSelect2();
        }
    }

    // Step validation functions
    function validateStep1() {
        const selected = document.querySelector('input[name="device_template_id"]:checked');
        if (!selected) {
            alert('กรุณาเลือก Device Template');
            return false;
        }
        return true;
    }

    function validateStep2() {
        const requiredFields = ['name'];
        let isValid = true;

        for (const fieldName of requiredFields) {
            const input = document.querySelector(`[name="${fieldName}"]`); 
            if (!input || !input.value.trim()) {
                isValid = false;
                input.classList.add('is-invalid');
                input.focus();
                break;
            } else {
                input.classList.remove('is-invalid');
            }
        }

        return isValid;
    }

    function validateStep3() {
        const protocol = getCurrentProtocol();
        let isValid = true;

        if (protocol === 'lorawan') {
            const requiredFields = ['application_server', 'device_profile_id', 'dev_eui'];
            for (const fieldName of requiredFields) {
                const input = document.querySelector(`[name="comm_${fieldName}"]`);
                if (input && !input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    input.focus();
                    break;
                } else if (input) {
                    input.classList.remove('is-invalid');
                }
            }
        }

        return isValid;
    }

    // Add event listeners for communication fields
    document.addEventListener('DOMContentLoaded', function() {
        // Listen for changes in communication fields
        document.addEventListener('input', function(e) {
            if (e.target.closest('#communicationFields')) {
                updateCommConfigJSON();
            }
        });
        
        // Listen for changes in SNMP version and security level
        document.addEventListener('change', function(e) {
            if (e.target.closest('#communicationFields')) {
                if (e.target.name === 'version' || e.target.name === 'security_level') {
                    updateCommConfigJSON();
                }
            }
        });
        
        // Listen for changes in metrics fields
        document.addEventListener('input', function(e) {
            if (e.target.closest('#metrics-container')) {
                updateMetricsJSON();
            }
        });
        
        // Listen for changes in metrics select fields
        document.addEventListener('change', function(e) {
            if (e.target.closest('#metrics-container')) {
                updateMetricsJSON();
            }
        });

        // Load custom fields for edit mode
        // ถ้ามี device_information_json เดิม (edit mode)
        const hidden = document.getElementById('device_information_json');
        if (hidden && hidden.value) {
            try {
                const info = JSON.parse(hidden.value);
                customFields = Object.entries(info).map(([key, value]) => ({ key, value }));
            } catch (e) { customFields = []; }
        }
        
        // ถ้ามี custom_fields_json จาก backend (edit mode)
        <?php if (isset($custom_fields_json) && !empty($custom_fields_json)): ?>
        try {
            const backendCustomFields = <?php echo $custom_fields_json; ?>;
            if (backendCustomFields && Object.keys(backendCustomFields).length > 0) {
                customFields = Object.entries(backendCustomFields).map(([key, value]) => ({ key, value }));
            }
        } catch (e) { 
            console.log('Error loading backend custom fields:', e);
        }
        <?php endif; ?>
        
        // ถ้ามี template_device_info จาก backend (edit mode)
        <?php if (isset($template_device_info) && !empty($template_device_info)): ?>
        try {
            const templateDeviceInfo = <?php echo json_encode($template_device_info); ?>;
            if (templateDeviceInfo && Object.keys(templateDeviceInfo).length > 0) {
                // เพิ่ม template fields ที่ยังไม่มีใน custom fields
                Object.keys(templateDeviceInfo).forEach(key => {
                    if (key !== 'Brand' && key !== 'Model' && templateDeviceInfo[key] && templateDeviceInfo[key].trim() !== '') {
                        const existingIndex = customFields.findIndex(f => f.key === key);
                        if (existingIndex === -1) {
                            customFields.push({ key: key, value: templateDeviceInfo[key] });
                        }
                    }
                });
            }
        } catch (e) { 
            console.log('Error loading template device info:', e);
        }
        <?php endif; ?>
        
        renderCustomFields();
    });

  // Filtering logic
  ['searchBox', 'filterCategory', 'filterProtocol', 'filterBrand', 'filterModel'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', function() {
      const searchTerm = document.getElementById('searchBox').value.toLowerCase();
      const categoryFilter = document.getElementById('filterCategory').value.toLowerCase();
      const protocolFilter = document.getElementById('filterProtocol').value.toLowerCase();
      const brandFilter = document.getElementById('filterBrand').value.toLowerCase();
      const modelFilter = document.getElementById('filterModel').value.toLowerCase();

      document.querySelectorAll('.template-card').forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const category = (card.dataset.category || '').toLowerCase();
        const protocol = card.dataset.protocol.toLowerCase();
        const brand = card.dataset.brand.toLowerCase();
        const model = card.dataset.model.toLowerCase();

        const matchesSearch = name.includes(searchTerm);
        const matchesCategory = !categoryFilter || category === categoryFilter;
        const matchesProtocol = !protocolFilter || protocol.includes(protocolFilter);
        const matchesBrand = !brandFilter || brand.includes(brandFilter);
        const matchesModel = !modelFilter || model.includes(modelFilter);

        card.style.display = matchesSearch && matchesCategory && matchesProtocol && matchesBrand && matchesModel ? 'block' : 'none';
      });
    });
  });

    // Toggle metric details function
    function toggleMetricDetails(index) {
        if (window.metrics && window.metrics[index]) {
            window.metrics[index].expanded = !window.metrics[index].expanded;
            renderMetrics();
        }
    }

    // Expand all metrics function
    function expandAllMetrics() {
        window.metrics.forEach((m) => { m.expanded = true; });
        renderMetrics();
    }

    // Collapse all metrics function
    function collapseAllMetrics() {
        window.metrics.forEach((m) => { m.expanded = false; });
        renderMetrics();
    }

    // Helper to generate unique ID
    function generateId() {
        return MD5(unescape(encodeURIComponent(new Date().toISOString() + Math.random())));
    }

    // Operator options for triggers
    const operatorOptions = ['==', '!=', '>', '>=', '<', '<='];
    function renderOperatorSelect(currentValue, onchange) {
        return `<select class="form-control form-control-sm" onchange="${onchange}">
        ${operatorOptions.map(op => `<option value="${op}" ${currentValue === op ? 'selected' : ''}>${op}</option>`).join('')}
        </select>`;
    }

    // Add trigger to a metric
    function addTrigger(i) {
        if (!window.metrics[i].triggers) window.metrics[i].triggers = [];
        const newTrigger = {
            id: MD5(unescape(encodeURIComponent(new Date().toISOString()))),
            enable: 'true',
            operator: '==',
            value: '',
            message: '',
            severity: 'Info', // Set default severity to Info
            repeat_interval: '',
            duration: [],
            clear_condition: {
                operator: '!=',
                value: '',
                message: ''
            },
            channels: [] // Initialize empty channels array
        };
        window.metrics[i].triggers.push(newTrigger);
        renderMetrics();
        updateMetricsJSON();
    }

    // Remove trigger from a metric
    function removeTrigger(i, j) {
        window.metrics[i].triggers.splice(j, 1);
        renderMetrics();
        updateMetricsJSON();
    }

    // Update trigger field
    function updateTrigger(i, j, field, value) {
        if (!window.metrics[i].triggers) window.metrics[i].triggers = [];
        if (!window.metrics[i].triggers[j]) window.metrics[i].triggers[j] = {};
        window.metrics[i].triggers[j][field] = value;
        updateMetricsJSON();
    }

    // Update trigger clear_condition field
    function updateTriggerClear(i, j, field, value) {
        if (!window.metrics[i].triggers[j].clear_condition) window.metrics[i].triggers[j].clear_condition = {};
        window.metrics[i].triggers[j].clear_condition[field] = value;
        updateMetricsJSON();
    }

    // Render triggers for a metric (like form_edit.php)
    function renderTriggers(metric, i) {
        let html = '';
        if (!metric.triggers || metric.triggers.length === 0) {
            html += `<div class='row'><div class='mb-2 text-muted'>No triggers defined.</div><div><button class='btn btn-sm btn-primary' type='button' onclick='addTrigger(${i})'>+ Add Trigger</button></div></div>`;
            return html;
        }
        html += `<div class='row'><div class='mb-2'><strong>Triggers:</strong> <button class='btn btn-sm btn-primary ms-2' type='button' onclick='addTrigger(${i})'>+ Add Trigger</button></div></div>`;
        (metric.triggers || []).forEach((t, j) => {
            const clearOp = t.clear_condition?.operator || '!=';
            const triggerOp = t.operator || '==';
            const triggerid = t.id || MD5(unescape(encodeURIComponent(new Date().toISOString())));
            const triggerenable = t.enable ?? 'true';
            html += `
            <div class="border p-2 mb-2">
                <div class="row mb-1">
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" onchange="updateTrigger(${i}, ${j}, 'enable', this.value)">
                            <option value="true" ${triggerenable == 'true' ? 'selected' : ''}>Active</option>
                            <option value="false" ${triggerenable == 'false' ? 'selected' : ''}>Deactive</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-1">
                    <input type="hidden" value="${triggerid}" onchange="updateTrigger(${i}, ${j}, 'id', this.value)">
                    <div class="col-md-3">${renderOperatorSelect(triggerOp, `updateTrigger(${i}, ${j}, 'operator', this.value)`)}</div>
                    <div class="col-md-3"><input class="form-control form-control-sm" placeholder="Value" value="${t.value || ''}" onchange="updateTrigger(${i}, ${j}, 'value', this.value)"></div>
                    <div class="col-md-4"><input class="form-control form-control-sm" placeholder="Message" value="${t.message || ''}" onchange="updateTrigger(${i}, ${j}, 'message', this.value)"></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-danger" type="button" onclick="removeTrigger(${i}, ${j})">ลบ</button></div>
                </div>
                <div class="row mb-1">
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" onchange="updateTrigger(${i}, ${j}, 'severity', this.value)">
                            <option value="">Select Severity</option>
                            <option value="Critical" ${t.severity === 'Critical' ? 'selected' : ''}>Critical</option>
                            <option value="High" ${t.severity === 'High' ? 'selected' : ''}>High</option>
                            <option value="Warning" ${t.severity === 'Warning' ? 'selected' : ''}>Warning</option>
                            <option value="Medium" ${t.severity === 'Medium' ? 'selected' : ''}>Medium</option>
                            <option value="Low" ${t.severity === 'Low' ? 'selected' : ''}>Low</option>
                            <option value="Info" ${t.severity === 'Info' ? 'selected' : ''}>Info</option>
                            <option value="Other" ${t.severity === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="hidden" class="form-control form-control-sm" value='${typeof t.duration === 'string' ? t.duration : JSON.stringify(t.duration) || ''}' onchange="updateTrigger(${i}, ${j}, 'duration', this.value)"></div>
                    <div class="col-md-4"><input class="form-control form-control-sm" placeholder="Repeat Interval" value="${t.repeat_interval || ''}" onchange="updateTrigger(${i}, ${j}, 'repeat_interval', this.value)"></div>
                </div>
                <div class="row mb-1">
                    <div class="col-md-3">${renderOperatorSelect(clearOp, `updateTriggerClear(${i}, ${j}, 'operator', this.value)`)}</div>
                    <div class="col-md-3"><input class="form-control form-control-sm" placeholder="Clear Value" value="${t.clear_condition?.value || ''}" onchange="updateTriggerClear(${i}, ${j}, 'value', this.value)"></div>
                    <div class="col-md-3"><input class="form-control form-control-sm" placeholder="Clear Messages" value="${t.clear_condition?.message || ''}" onchange="updateTriggerClear(${i}, ${j}, 'message', this.value)"></div>
                </div>
                <div class="row mb-1">
                    <div class="col-md-12">
                        <label class="form-label">Notification Channels</label>
                        ${renderChannelSelector(i, j, t.channels || [])}
                    </div>
                </div>
                ${renderDuration(i, j, t)}
            </div>`;
        });
        return html;
    }

    // In renderMetrics, after protocol-specific fields, call:
    // ${renderTriggers(metric, index)}

    // Days of week for duration
    const daysOfWeek = [
        { key: 0, label: 'Sun' },
        { key: 1, label: 'Mon' },
        { key: 2, label: 'Tue' },
        { key: 3, label: 'Wed' },
        { key: 4, label: 'Thu' },
        { key: 5, label: 'Fri' },
        { key: 6, label: 'Sat' }
    ];

    function addDuration(i, j) {
        if (!window.metrics[i].triggers[j].duration || !Array.isArray(window.metrics[i].triggers[j].duration)) {
            window.metrics[i].triggers[j].duration = [];
        }
        window.metrics[i].triggers[j].duration.push({ days: [1,2,3,4,5], start: '08:00', end: '17:00' });
        renderMetrics();
        updateMetricsJSON();
    }

    function removeDuration(i, j, dIdx) {
        window.metrics[i].triggers[j].duration.splice(dIdx, 1);
        renderMetrics();
        updateMetricsJSON();
    }

    function updateDurationField(i, j, dIdx, field, value) {
        if (field === 'days') {
            // value is array of checked day keys
            window.metrics[i].triggers[j].duration[dIdx].days = value;
        } else {
            window.metrics[i].triggers[j].duration[dIdx][field] = value;
        }
        updateMetricsJSON();
    }

    function toggleDurationDay(i, j, dIdx, dayKey) {
        const dur = window.metrics[i].triggers[j].duration[dIdx];
        if (!dur.days.includes(dayKey)) {
            dur.days.push(dayKey);
        } else {
            dur.days = dur.days.filter(d => d !== dayKey);
        }
        updateMetricsJSON();
        renderMetrics();
    }

    // Render duration section for a trigger
    function renderDuration(i, j, trigger) {
        let html = `<div class='mb-2'><strong>Active Time / Duration:</strong> <button type='button' class='btn btn-outline-primary btn-sm ms-2' onclick='addDuration(${i},${j})'>+ Add Time Window</button></div>`;
        if (!trigger.duration || trigger.duration.length === 0) {
            html += `<div class='text-muted mb-2'>No time window set (trigger always active)</div>`;
        }
        (trigger.duration || []).forEach((d, dIdx) => {
            html += `<div class='border rounded p-2 mb-2'>`;
            html += `<div class='mb-1'>`;
            daysOfWeek.forEach(day => {
                const checked = d.days.includes(day.key) ? 'checked' : '';
                html += `<label class='me-2'><input type='checkbox' onchange='toggleDurationDay(${i},${j},${dIdx},${day.key})' ${checked}> ${day.label}</label>`;
            });
            html += `</div>`;
            html += `<div class='row mb-1'>
                <div class='col-md-4'>Start: <input type='time' class='form-control form-control-sm' value='${d.start}' onchange='updateDurationField(${i},${j},${dIdx},"start",this.value)'></div>
                <div class='col-md-4'>End: <input type='time' class='form-control form-control-sm' value='${d.end}' onchange='updateDurationField(${i},${j},${dIdx},"end",this.value)'></div>
                <div class='col-md-2'><button type='button' class='btn btn-outline-danger btn-sm' onclick='removeDuration(${i},${j},${dIdx})'>ลบ</button></div>
            </div>`;
            html += `</div>`;
        });
        return html;
    }

    // In renderTriggers, after other trigger fields, add:
    // html += renderDuration(i, j, t);

    // Lore Application Server get from ajax
    async function getLoRaAppServer() {
        const res = await fetch('<?= base_url("lorawan/ajax_lorawan_app_server") ?>');
        const data = await res.json();
        return data;
    }

    async function getLoRaDeviceProfile(tenantId) {
        const res = await fetch('<?= base_url("lorawan/get_device_profile/") ?>' + tenantId);
        const data = await res.json();
        return data;
    }

    // Step 3: Communication Configuration
    let commConfig = {};

    const commFields = {
        modbus_tcp: ['ip', 'port', 'unit_id'],
        modbus_rtu: ['baudrate', 'parity', 'stop_bits', 'data_bits', 'slave_id', 'port'],
        snmp: ['version', 'community', 'host', 'port', 'max_oids_per_request', 'batch_delay_ms', 'security_level', 'auth_protocol', 'auth_pass', 'priv_protocol', 'priv_pass'],
        mqtt: ['broker_url', 'port', 'username', 'password', 'client_id', 'qos', 'use_tls'],
        bacnet: ['device_id', 'ip', 'port'],
        lorawan: ['application_server', 'device_profile_id', 'dev_eui', 'app_eui', 'app_key', 'netskey', 'appskey'],
        rtsp: ['ip', 'port', 'rtsp_port', 'rtsp_url', 'snapshot_url', 'transport', 'username', 'password']
    };

    async function renderCommFields() {
        const container = document.getElementById('comm-fields-container');
        if (!container) return;
        
        container.innerHTML = '';
        const protocol = getCurrentProtocol();
        const fields = commFields[protocol] || [];

        console.log("protocol", protocol);

        let appServers = [];
        let deviceProfiles = [];

        if (fields.includes('application_server')) {
            appServers = await getLoRaAppServer(); // ดึง app server list
        }

        const selectedAppServerId = commConfig?.application_server || '';
        const selectedAppServer = appServers.find(s => s.id === selectedAppServerId);
        const tenantId = selectedAppServer?.tenant_id ?? '';

        if (fields.includes('device_profile_id') && tenantId) {
            const dpResponse = await getLoRaDeviceProfile(tenantId);
            console.log("device_profile_id", dpResponse);
            deviceProfiles = dpResponse.result || [];
        }

        for (const field of fields) {
            const label = field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            const value = commConfig?.[field] ?? '';

            if (field === 'application_server') {
                container.innerHTML += `
                <div class="mb-3">
                    <label class="form-label">${label}</label>
                    <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value); renderCommFields();" required>
                        <option value="">Select Application Server</option>
                        ${appServers.map(server => `
                            <option value="${server.id}" ${value == server.id ? 'selected' : ''}>${server.name}</option>
                        `).join('')}
                    </select>
                </div>
                `;
            } else if (field === 'device_profile_id') {
                container.innerHTML += `
                <div class="mb-3">
                    <label class="form-label">${label}</label>
                    <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value)" required>
                        <option value="">Select Device Profile</option>
                        ${deviceProfiles.map(dp => `
                            <option value="${dp.id}" ${value == dp.id ? 'selected' : ''}>${dp.name}</option>
                        `).join('')}
                    </select>
                </div>
                `;
            } else if (field == "dev_eui") {
                container.innerHTML += `
                <div class="mb-3">
                    <label class="form-label">${label}</label>
                    <input type="text" class="form-control" name="comm_${field}" value="${value}"
                        oninput="updateCommField('${field}', this.value)" required />
                </div>
                `;
            } else if (field === 'version' && protocol === 'snmp') {
                container.innerHTML += `
                <div class="mb-3">
                    <label class="form-label">${label}</label>
                    <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value); renderCommFields();" required>
                        <option value="1" ${value == '1' ? 'selected' : ''}>SNMP v1</option>
                        <option value="2c" ${value == '2c' ? 'selected' : ''}>SNMP v2c</option>
                        <option value="3" ${value == '3' ? 'selected' : ''}>SNMP v3</option>
                    </select>
                </div>
                `;
            } else if (field === 'security_level' && protocol === 'snmp') {
                container.innerHTML += `
                <div class="mb-3" id="snmp_v3_fields" style="display: ${commConfig?.version == '3' ? 'block' : 'none'}">
                    <label class="form-label">${label}</label>
                    <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value); renderCommFields();">
                        <option value="noAuthNoPriv" ${value == 'noAuthNoPriv' ? 'selected' : ''}>No Auth No Priv</option>
                        <option value="authNoPriv" ${value == 'authNoPriv' ? 'selected' : ''}>Auth No Priv</option>
                        <option value="authPriv" ${value == 'authPriv' ? 'selected' : ''}>Auth Priv</option>
                    </select>
                </div>
                `;
            } else if (field === 'auth_protocol' && protocol === 'snmp') {
                container.innerHTML += `
                <div class="mb-3" id="snmp_v3_auth_fields" style="display: ${(commConfig?.security_level == 'authNoPriv' || commConfig?.security_level == 'authPriv') ? 'block' : 'none'}">
                    <label class="form-label">${label}</label>
                    <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value)">
                        <option value="MD5" ${value == 'MD5' ? 'selected' : ''}>MD5</option>
                        <option value="SHA" ${value == 'SHA' ? 'selected' : ''}>SHA</option>
                        <option value="SHA-224" ${value == 'SHA-224' ? 'selected' : ''}>SHA-224</option>
                        <option value="SHA-256" ${value == 'SHA-256' ? 'selected' : ''}>SHA-256</option>
                        <option value="SHA-384" ${value == 'SHA-384' ? 'selected' : ''}>SHA-384</option>
                        <option value="SHA-512" ${value == 'SHA-512' ? 'selected' : ''}>SHA-512</option>
                    </select>
                </div>
                `;
            } else if (field === 'priv_protocol' && protocol === 'snmp') {
                container.innerHTML += `
                <div class="mb-3" id="snmp_v3_priv_fields" style="display: ${commConfig?.security_level == 'authPriv' ? 'block' : 'none'}">
                    <label class="form-label">${label}</label>
                    <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value)">
                        <option value="DES" ${value == 'DES' ? 'selected' : ''}>DES</option>
                        <option value="AES" ${value == 'AES' ? 'selected' : ''}>AES</option>
                        <option value="AES-192" ${value == 'AES-192' ? 'selected' : ''}>AES-192</option>
                        <option value="AES-256" ${value == 'AES-256' ? 'selected' : ''}>AES-256</option>
                    </select>
                </div>
                `;
            } else if (field === 'auth_pass' && protocol === 'snmp') {
                container.innerHTML += `
                <div class="mb-3" id="snmp_v3_auth_pass_field" style="display: ${(commConfig?.security_level == 'authNoPriv' || commConfig?.security_level == 'authPriv') ? 'block' : 'none'}">
                    <label class="form-label">${label}</label>
                    <input type="password" class="form-control" name="comm_${field}" value="${value}"
                        oninput="updateCommField('${field}', this.value)">
                </div>
                `;
            } else if (field === 'priv_pass' && protocol === 'snmp') {
                container.innerHTML += `
                <div class="mb-3" id="snmp_v3_priv_pass_field" style="display: ${commConfig?.security_level == 'authPriv' ? 'block' : 'none'}">
                    <label class="form-label">${label}</label>
                    <input type="password" class="form-control" name="comm_${field}" value="${value}"
                        oninput="updateCommField('${field}', this.value)">
                </div>
                `;
            } else if (protocol === 'snmp' && ['max_oids_per_request', 'batch_delay_ms'].includes(field)) {
                const labels = {
                    max_oids_per_request: 'Max OIDs Per Request',
                    batch_delay_ms: 'Batch Delay (ms)'
                };
                const placeholders = {
                    max_oids_per_request: '10',
                    batch_delay_ms: '0'
                };
                container.innerHTML += `
                <div class="mb-3">
                    <label class="form-label">${labels[field]}</label>
                    <input type="number" min="0" class="form-control" name="comm_${field}" value="${value}"
                        placeholder="${placeholders[field]}"
                        oninput="updateCommField('${field}', this.value)">
                </div>
                `;
            } else if (field === 'transport' && protocol === 'rtsp') {
                container.innerHTML += `
                <div class="mb-3">
                    <label class="form-label">Transport</label>
                    <select class="form-select" name="comm_${field}" onchange="updateCommField('${field}', this.value)">
                        <option value="tcp" ${value === 'udp' ? '' : 'selected'}>TCP</option>
                        <option value="udp" ${value === 'udp' ? 'selected' : ''}>UDP</option>
                    </select>
                </div>
                `;
            } else if (protocol === 'rtsp' && field === 'password') {
                container.innerHTML += `
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="comm_${field}" value="${value}"
                        autocomplete="new-password"
                        oninput="updateCommField('${field}', this.value)">
                </div>
                `;
            } else if (protocol === 'rtsp' && ['ip', 'port', 'rtsp_port', 'rtsp_url', 'snapshot_url', 'username'].includes(field)) {
                const labels = {
                    ip: 'IP Address',
                    port: 'REST Port',
                    rtsp_port: 'RTSP Port',
                    rtsp_url: 'RTSP Path',
                    snapshot_url: 'Snapshot Path',
                    username: 'Username'
                };
                const placeholders = {
                    ip: '172.16.180.42',
                    port: '80',
                    rtsp_port: '554',
                    rtsp_url: '/Streaming/Channels/102',
                    snapshot_url: '/ISAPI/Streaming/channels/101/picture',
                    username: ''
                };
                const inputType = field === 'port' || field === 'rtsp_port' ? 'number' : 'text';
                container.innerHTML += `
                <div class="mb-3">
                    <label class="form-label">${labels[field] || label}</label>
                    <input type="${inputType}" class="form-control" name="comm_${field}" value="${value}"
                        placeholder="${placeholders[field] || ''}"
                        oninput="updateCommField('${field}', this.value)">
                </div>
                `;
            } else {
                container.innerHTML += `
                <div class="mb-3">
                    <label class="form-label">${label}</label>
                    <input type="text" class="form-control" name="comm_${field}" value="${value}"
                        oninput="updateCommField('${field}', this.value)">
                </div>
                `;
            }
        }

        if (protocol === 'rtsp') {
            commConfig.protocol = 'rtsp';
            if (!commConfig.port) commConfig.port = '80';
            if (!commConfig.rtsp_port) commConfig.rtsp_port = '554';
        }

        updateCommJSON();
    }

    function updateCommField(field, value) {
        commConfig[field] = value;
        updateCommJSON();
    }

    function updateCommJSON() {
        const commConfigField = document.getElementById('comm_config_json');
        if (commConfigField) {
            commConfigField.value = JSON.stringify(commConfig);
        }
    }

    // Get current protocol function
    function getCurrentProtocol() {
        const selected = document.querySelector("input[name='device_template_id']:checked");
        const templateId = selected?.value;
        const template = window.templates?.find(t => t.id == templateId);
        return template?.protocol || 'modbus_tcp';
    }

    // Initialize communication fields on page load
    document.addEventListener('DOMContentLoaded', () => {
        renderCommFields();
        renderMetrics(); // Add this line to render metrics

        // Update form when template is selected
        const tplSelect = document.querySelector('input[name="device_template_id"]');
        if (tplSelect) {
            tplSelect.addEventListener('change', () => {
                commConfig = {}; // reset config if user changes template
                renderCommFields();
            });
        }
    });

    // Initialize available channels for LoRaWAN
    window.availableChannels = <?= json_encode($channels ?? [], JSON_UNESCAPED_UNICODE) ?>;
    console.log("availableChannels", window.availableChannels);

    // Notification channel selector functions
    const channelOptions = window.availableChannels || [];

    function renderChannelSelector(i, j, selectedIds = []) {
        return `<select multiple class="form-control channel-select" onchange="updateTriggerChannels(${i}, ${j}, this.selectedOptions)">` +
            channelOptions.map(c => {
                const selected = selectedIds.includes(parseInt(c.id)) ? 'selected' : '';
                return `<option value="${c.id}" ${selected}>${c.name} ${c.is_active == 't' ? '' : '(Inactive)'}</option>`;
            }).join('') +
            '</select>';
    }

    function updateTriggerChannels(i, j, selectedOptions) {
        const selected = Array.from(selectedOptions).map(o => parseInt(o.value));
        if (!window.metrics[i].triggers[j]) window.metrics[i].triggers[j] = {};
        window.metrics[i].triggers[j].channels = selected;
        updateMetricsJSON();
    }

    function renderChannelSelectorNormal(i, selectedIds = []) {
        console.log('Rendering channel selector for Normal', i, 'with selected IDs:', selectedIds)
        return `<select multiple class="form-control channel-select" onchange="updateTriggerChannelsNormal(${i}, this.selectedOptions)">` +
            channelOptions.map(c => {
                const selected = selectedIds.includes(parseInt(c.id)) ? 'selected' : '';
                return `<option value="${c.id}" ${selected}>${c.name}</option>`;
            }).join('') +
            '</select>';
    }

    function updateTriggerChannelsNormal(i, selectedOptions) {
        const selected = Array.from(selectedOptions).map(o => parseInt(o.value));
        if (!window.metrics[i].normal_condition) window.metrics[i].normal_condition = {};
        window.metrics[i].normal_condition.channels = selected;
        updateMetricsJSON();
    }
</script>

<script>
  // Step 5: Confirm Summary
  function showConfirmSummary() {
    const templateId = document.querySelector("input[name='device_template_id']:checked")?.value;
    const template = templates.find(t => t.id == templateId) || {};

    console.log('template', template);
    const metrics = JSON.parse(document.getElementById('metrics_json').value || '[]');
    const comm = JSON.parse(document.getElementById('comm_config_json').value || '[]');
    const protocol = template.protocol || 'modbus_tcp';

    const info = JSON.parse(template.device_information) || {};

    const brand = info.Brand || '';
    const model = info.Model || '';

    document.getElementById('confirm-template-name').textContent = template.name || '';
    document.getElementById('confirm-template-category').textContent = template.category_name || '-';
    document.getElementById('confirm-template-protocol').textContent = template.protocol || '';
    document.getElementById('confirm-template-brand').textContent = brand;
    document.getElementById('confirm-template-model').textContent = model;

    document.getElementById('confirm-name').textContent = document.querySelector('input[name="name"]').value;
    const roleSelect = document.getElementById('device_role');
    const gatewaySelect = document.getElementById('gateway_device_id');
    document.getElementById('confirm-device-role').textContent = roleSelect?.selectedOptions[0]?.textContent || 'Native';
    const gatewayRow = document.getElementById('confirm-parent-gateway-row');
    const showGateway = roleSelect?.value === 'endpoint';
    gatewayRow.style.display = showGateway ? '' : 'none';
    document.getElementById('confirm-parent-gateway').textContent = showGateway
      ? (gatewaySelect?.selectedOptions[0]?.textContent || '-')
      : '-';
    document.getElementById('confirm-serial').textContent = document.querySelector('input[name="serial_number"]').value;
    document.getElementById('confirm-location').textContent = document.querySelector('textarea[name="location"]').value;
    document.getElementById('confirm-description').textContent = document.querySelector('textarea[name="description"]')?.value || '-';
    document.getElementById('confirm-status').textContent = document.querySelector('input[name="is_active"]').checked ? 'Active' : 'Inactive';


    document.getElementById('confirm-comm').textContent = JSON.stringify(comm, null, 2);

    document.getElementById('confirm-metrics').textContent = JSON.stringify(metrics, null, 2);
  }
</script>

<script>
  async function submitDeviceForm(event) {
    event.preventDefault(); // ป้องกันการ submit ปกติ

    const templateId = document.querySelector("input[name='device_template_id']:checked")?.value;
    const template = templates.find(t => t.id == templateId) || {};
    const protocol = template.protocol || 'modbus_tcp';

    if (protocol !== 'lorawan') {
      document.getElementById('wizardForm').submit();
      return true;
    }

    const commConfigJson = document.getElementById('comm_config_json').value || '{}';
    const commConfig = JSON.parse(commConfigJson);

    // === ดึงข้อมูล Device Info ===
    const info = {
      name: document.querySelector('input[name="name"]')?.value ?? '',
      serial_number: document.querySelector('input[name="serial_number"]')?.value ?? '',
      location: document.querySelector('textarea[name="location"]')?.value ?? '',
      description: document.querySelector('textarea[name="description"]')?.value ?? '',
      is_active: document.querySelector('input[name="is_active"]')?.checked ? 1 : 0,
      device_template_id: templateId,
    };

    // Validate LoRaWAN fields
    const requiredFields = ['dev_eui', 'device_profile_id', 'application_server'];
    for (const field of requiredFields) {
      if (!commConfig[field]) {
        alert(`กรุณากรอกข้อมูล ${field.replace(/_/g, ' ')} ให้ครบถ้วน`);
        return false;
      }
    }

    try {
      const res = await fetch('<?= base_url("lorawan/global_adddevice") ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          comm_config: commConfig,
          info
        })
      });

      const result = await res.json();

      if (!result.success) {
       // alert("ไม่สามารถเพิ่ม LoRaWAN device ได้: " + (result.message || ''));
        //return false;
      }

      //console.log("LoRa device added", result);

      // ไปต่อ: Submit ไปที่ device/save
      document.getElementById('wizardForm').submit();
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการส่งข้อมูล LoRaWAN device");
      console.error(err);
    }

    return false;
  }
</script>

<script>
  window.availableChannels = <?= json_encode($channels ?? [], JSON_UNESCAPED_UNICODE) ?>;
  console.log("availableChannels", window.availableChannels);
</script>

<style>
    .custom-fields-display {
        margin-top: 10px;
    }
    .custom-field-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 0;
        border-bottom: 1px solid #eee;
    }
    .custom-field-item:last-child {
        border-bottom: none;
    }
    .custom-field-item strong {
        color: #495057;
        min-width: 120px;
    }
    .custom-field-item span {
        color: #6c757d;
        flex: 1;
        text-align: right;
    }
    .custom-fields-container {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 15px;
        margin-top: 10px;
    }
    .custom-fields-container h6 {
        margin-bottom: 10px;
        color: #495057;
        font-weight: 600;
    }
</style>

<script>
    // Initialize available channels for LoRaWAN
    window.availableChannels = <?= json_encode($channels ?? [], JSON_UNESCAPED_UNICODE) ?>;
    console.log("availableChannels", window.availableChannels);
</script>

<link rel="stylesheet" href="<?= base_url('assets/css/jconnect-metric-builder.css') ?>">
<script src="<?= base_url('assets/js/jconnect-metric-builder.js') ?>?v=20260824-snapshot-triggers"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  window.metrics = JConnectMetricBuilder.parseMetrics(document.getElementById('metrics_json')?.value || []);
  window.deviceMetricBuilder = JConnectMetricBuilder.create({
    containerId: 'metrics-container',
    inputId: 'metrics_json',
    snapshotInputId: 'snapshot_triggers_json',
    protocol: (typeof getCurrentProtocol === 'function' ? getCurrentProtocol() : 'modbus_tcp'),
    metrics: window.metrics,
    snapshotTriggers: [],
    devices: <?= json_encode($calc_devices ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    channels: <?= json_encode($channels ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    roleId: 'device_role',
    gatewayId: 'gateway_device_id',
    readOnly: true,
    snapshotReadOnly: false
  });

  window.renderMetrics = function () { window.deviceMetricBuilder.render(); };
  window.addMetric = function () {};
  window.updateMetricsFields = function (value) {
    window.metrics = JConnectMetricBuilder.parseMetrics(value);
    window.deviceMetricBuilder.protocol = (typeof getCurrentProtocol === 'function' ? getCurrentProtocol() : 'modbus_tcp');
    window.deviceMetricBuilder.setMetrics(window.metrics);
  };
});
</script>
