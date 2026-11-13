<!--begin::App Content Header-->
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><?php echo lang("devices"); ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home'); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo lang('devices'); ?></li>
          </ol>
        </nav>
      </div>
      <div class="col text-end">
        <div class="btn-group" role="group">
          <button type="button" id="toggleListView" class="btn btn-primary">
            <i class="bi bi-list"></i> <?php echo lang('list'); ?>
          </button>
          <button type="button" id="toggleCardView" class="btn btn-outline-secondary">
            <i class="bi bi-grid"></i> <?php echo lang('card'); ?>
          </button>
        </div>
        <a href="<?= base_url('device/add') ?>" class="btn btn-primary ms-2">
          <i class="bi bi-plus"></i> <?php echo lang('add_device'); ?>
        </a>

      </div>
    </div>
  </div>
</div>
<!--end::App Content Header-->

<!--begin::App Content-->
<div class="app-content">
  <div class="container-fluid">
    <div class="row mb-3">
      <div class="col-md-2 mb-2">
        <select id="pageLength" class="form-select">
          <option value="10">Show 10 entries</option>
          <option value="25">Show 25 entries</option>
          <option value="50">Show 50 entries</option>
          <option value="100">Show 100 entries</option>
        </select>
      </div>
      <div class="col-md-3 mb-2">
        <input type="text" id="deviceSearch" class="form-control" placeholder="<?php echo lang('search_devices'); ?>">
      </div>
      <div class="col-md-2 mb-2">
        <select id="filterActive" class="form-select">
          <option value=""><?php echo lang('all_status'); ?></option>
          <option value="active"><?php echo lang('status_active'); ?></option>
          <option value="inactive"><?php echo lang('status_inactive'); ?></option>
        </select>
      </div>
      <div class="col-md-2 mb-2">
        <select id="filterTemplate" class="form-select">
          <option value=""><?php echo lang('all_templates'); ?></option>
        </select>
      </div>
      <div class="col-md-2 mb-2">
        <select id="filterLocation" class="form-select">
          <option value=""><?php echo lang('all_locations'); ?></option>
        </select>
      </div>

    </div>

    <!-- List View -->
    <div id="deviceListView">
      <div class="table-responsive table-responsive-sm w-auto">
        <table id="devicesTable" class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?php echo lang('image'); ?></th>
              <th><?php echo lang('device_name'); ?></th>
              <th><?php echo lang('device_brand'); ?></th>
              <th><?php echo lang('device_model'); ?></th>
              <th><?php echo lang('serial_number'); ?></th>
              <th><?php echo lang('device_location'); ?></th>
              <th><?php echo lang('device_description'); ?></th>
              <th><?php echo lang('custom_field'); ?></th>
              <th><?php echo lang('device_status'); ?></th>
              <th><?php echo lang('actions'); ?></th>
            </tr>
          </thead>
          <tbody>
            <!-- Data will be loaded via AJAX -->
          </tbody>
        </table>
      </div>
    </div>

    <!-- Card View -->
    <div id="deviceCardView" style="display: none;">
      <div class="row" id="deviceCardContainer">
        <!-- Cards will be loaded via AJAX -->
      </div>

      <!-- Card View Pagination -->
      <div class="row mt-3" id="cardPaginationContainer">
        <div class="col-12">
          <nav aria-label="Card pagination">
            <ul class="pagination justify-content-center" id="cardPagination">
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <!-- Card View Pagination -->
  <div class="row mt-3" id="cardPaginationContainer" style="display: none;">
    <div class="col-12">
      <nav aria-label="Card pagination">
        <ul class="pagination justify-content-center" id="cardPagination">
        </ul>
      </nav>
    </div>
  </div>
</div>
<!--end::App Content-->

<style>
  .device-image-container {
    position: relative;
    cursor: pointer;
    display: inline-block;
  }

  /* Disabled hover effect for card view */
  /*
  .device-image-container:hover::after {
    content: '';
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 400px;
    height: 300px;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    border: 3px solid #007bff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    z-index: 9999;
    background-image: var(--hover-image);
    background-color: #fff;
    animation: imageZoomIn 0.3s ease-out;
  }

  .device-image-container:hover::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9998;
  }

  @keyframes imageZoomIn {
    from {
      opacity: 0;
      transform: translate(-50%, -50%) scale(0.8);
    }
    to {
      opacity: 1;
      transform: translate(-50%, -50%) scale(1);
    }
  }
  */

  /* Alternative hover effect for table images */
  .device-image-table {
    position: relative;
    cursor: pointer;
    transition: transform 0.2s ease;
  }

  .device-image-table:hover {
    transform: scale(1.1);
  }

  .device-image-table:hover::after {
    content: '';
    position: absolute;
    top: -20px;
    left: 50px;
    width: 250px;
    height: 200px;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    border: 2px solid #007bff;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
    z-index: 1000;
    background-image: var(--hover-image);
    background-color: #fff;
    animation: tableImageZoom 0.2s ease-out;
  }

  .device-image-table:hover::before {
    content: '';
    position: absolute;
    top: -8px;
    left: 45px;
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-bottom: 8px solid #007bff;
    z-index: 1001;
  }

  @keyframes tableImageZoom {
    from {
      opacity: 0;
      transform: scale(0.9);
    }
    to {
      opacity: 1;
      transform: scale(1);
    }
  }

  /* Custom Fields Styles */
  .custom-fields-display {
    max-width: 200px;
    font-size: 0.9em;
  }
  .custom-field-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2px 0;
    border-bottom: 1px solid #eee;
  }
  .custom-field-item:last-child {
    border-bottom: none;
  }
  .custom-field-item strong {
    /*color: #495057;*/
    font-size: 1em;
    min-width: 80px;
  }
  .custom-field-item span {
    /*color: #6c757d;*/
    font-size: 1em;
    text-align: right;
  }
  
  /* Custom Fields in Table */
  #devicesTable .custom-fields-display {
    max-width: 150px;
    font-size: 0.85em;
  }
  
  #devicesTable .custom-field-item {
    display: block;
    padding: 1px 0;
    border-bottom: none;
    margin-bottom: 2px;
  }
  
  #devicesTable .custom-field-item:last-child {
    margin-bottom: 0;
  }
  
  #devicesTable .custom-field-item small {
    color: var(--bs-secondary);
    font-weight: 500;
  }
  
  #devicesTable .custom-field-item .fw-medium {
    color: var(--bs-body-color);
    font-weight: 600;
  }
</style>

<script>
  $(document).ready(function() {
    var currentView = 'list';
    var currentPage = 1;
    var cardsPerPage = 12;
    var searchTimeout;
    var filterParams = {
      status: '',
      template: '',
      location: '',
      search: ''
    };

    // Load filter options
    loadFilterOptions();

    // Load devices on page load
    loadDevices();

    // Search handler with debouncing
    $('#deviceSearch').on('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function() {
        filterParams.search = $('#deviceSearch').val();
        if (currentView === 'list') {
          loadDevices();
        } else {
          loadCardView();
        }
      }, 500);
    });

    // Filter handlers
    $('#filterActive').on('change', function() {
      filterParams.status = this.value;
      if (currentView === 'list') {
        loadDevices();
      } else {
        loadCardView();
      }
    });

    $('#filterTemplate').on('change', function() {
      filterParams.template = this.value;
      if (currentView === 'list') {
        loadDevices();
      } else {
        loadCardView();
      }
    });

    $('#filterLocation').on('change', function() {
      filterParams.location = this.value;
      if (currentView === 'list') {
        loadCardView();
      } else {
        loadCardView();
      }
    });

    // Page length handler
    $('#pageLength').on('change', function() {
      var newLength = parseInt(this.value);
      if (currentView === 'list') {
        currentPage = 1;
        loadDevices();
      } else {
        cardsPerPage = newLength;
        currentPage = 1;
        loadCardView();
      }
    });

    // Load devices function
    function loadDevices() {
      var pageLength = parseInt($('#pageLength').val());
      
      $.ajax({
        url: '<?= base_url("device/get_devices_ajax") ?>',
        type: 'POST',
        dataType: 'json',
        data: {
          status: filterParams.status,
          template: filterParams.template,
          location: filterParams.location,
          search_custom: filterParams.search,
          page: currentPage,
          length: pageLength
        },
        success: function(response) {
          console.log('AJAX Response:', response);
          console.log('Response status:', response.status);
          console.log('Response status type:', typeof response.status);
          console.log('Response status === "success":', response.status === 'success');
          
          if (response.status === 'success') {
            console.log('Rendering device table with data:', response.data);
            renderDeviceTable(response.data, response.total, response.current_page, response.total_pages);
          } else {
            console.error('Error loading devices:', response.message || 'Unknown error');
            $('#devicesTable tbody').html('<tr><td colspan="10" class="text-center text-muted"><?php echo lang("error_loading_data"); ?></td></tr>');
          }
        },
        error: function(xhr, error, thrown) {
          console.error('AJAX Error:', error);
          $('#devicesTable tbody').html('<tr><td colspan="10" class="text-center text-muted"><?php echo lang("error_loading_data"); ?></td></tr>');
        }
      });
    }

    // Render device table
    function renderDeviceTable(devices, total, currentPage, totalPages) {
      var tbody = $('#devicesTable tbody');
      tbody.empty();

      if (devices.length === 0) {
        tbody.html('<tr><td colspan="10" class="text-center text-muted"><?php echo lang("no_data_found"); ?></td></tr>');
        return;
      }

      devices.forEach(function(device) {
        console.log('Device data:', device);
        console.log('Custom fields:', device.custom_fields);
        console.log('Custom fields HTML:', device.custom_fields_html);
        console.log('Status:', device.active);
        
        var row = '<tr>';
        row += '<td><div class="device-image-table" data-device-image="' + (device.image || '') + '" data-template-image="' + (device.template_image || '') + '"><img src="' + (device.image || '<?= base_url("assets/images/default-device.png") ?>') + '" class="device-image" width="40" height="40" alt="Device"></div></td>';
        row += '<td>' + (device.name || '-') + '</td>';
        row += '<td>' + (device.brand || '-') + '</td>';
        row += '<td>' + (device.model || '-') + '</td>';
        row += '<td>' + (device.serial_number || '-') + '</td>';
        row += '<td>' + (device.location || '-') + '</td>';
        row += '<td>' + (device.description || '-') + '</td>';
        row += '<td>' + (device.custom_fields_html || '<?php echo lang("no_additional_info"); ?>') + '</td>';
        row += '<td>' + (device.active || '') + '</td>';
        row += '<td>' + device.actions + '</td>';
        row += '</tr>';
        tbody.append(row);
      });

      // Render pagination
      renderPagination(currentPage, totalPages, total);
    }

    // Render pagination
    function renderPagination(currentPage, totalPages, total) {
      var pageLength = parseInt($('#pageLength').val());
      var start = ((currentPage - 1) * pageLength) + 1;
      var end = Math.min(currentPage * pageLength, total);
      
      var paginationHtml = '<div class="d-flex justify-content-between align-items-center">';
      paginationHtml += '<div class="text-muted">Showing ' + start + ' to ' + end + ' of ' + total + ' entries</div>';
      
      if (totalPages > 1) {
        paginationHtml += '<nav><ul class="pagination pagination-sm mb-0">';
        
        // Previous button
        if (currentPage > 1) {
          paginationHtml += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' + (currentPage - 1) + ')"><?php echo lang("previous_page"); ?></a></li>';
        } else {
          paginationHtml += '<li class="page-item disabled"><span class="page-link"><?php echo lang("previous_page"); ?></span></li>';
        }
        
        // Page numbers
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
          paginationHtml += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(1)">1</a></li>';
          if (startPage > 2) {
            paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
          }
        }
        
        for (var i = startPage; i <= endPage; i++) {
          if (i === currentPage) {
            paginationHtml += '<li class="page-item active"><span class="page-link">' + i + '</span></li>';
          } else {
            paginationHtml += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' + i + ')">' + i + '</a></li>';
          }
        }
        
        if (endPage < totalPages) {
          if (endPage < totalPages - 1) {
            paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
          }
          paginationHtml += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' + totalPages + ')">' + totalPages + '</a></li>';
        }
        
        // Next button
        if (currentPage < totalPages) {
          paginationHtml += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' + (currentPage + 1) + ')"><?php echo lang("next_page"); ?></a></li>';
        } else {
          paginationHtml += '<li class="page-item disabled"><span class="page-link"><?php echo lang("next_page"); ?></span></li>';
        }
        
        paginationHtml += '</ul></nav>';
      }
      
      paginationHtml += '</div>';
      
      // Add pagination to table
      $('#devicesTable').next('.pagination-container').remove();
      $('#devicesTable').after('<div class="pagination-container mt-3">' + paginationHtml + '</div>');
    }

    // Global function for pagination
    window.changePage = function(page) {
      currentPage = page;
      loadDevices();
    };

    // Toggle List/Card View
    $('#toggleListView').on('click', function() {
      currentView = 'list';
      $('#deviceListView').show();
      $('#deviceCardView').hide();
      $('#cardPaginationContainer').hide();
      $(this).addClass('btn-primary').removeClass('btn-outline-secondary');
      $('#toggleCardView').removeClass('btn-primary').addClass('btn-outline-secondary');
      loadDevices();
    });

    $('#toggleCardView').on('click', function() {
      currentView = 'card';
      $('#deviceListView').hide();
      $('#deviceCardView').show();
      $('#cardPaginationContainer').show();
      $(this).addClass('btn-primary').removeClass('btn-outline-secondary');
      $('#toggleListView').removeClass('btn-primary').addClass('btn-outline-secondary');
      loadCardView();
    });

    // Default: List View
    $('#toggleListView').addClass('btn-primary');

    // Load Card View
    function loadCardView() {
      var params = {
        page: currentPage,
        length: cardsPerPage,
        search_custom: filterParams.search || '',
        status: filterParams.status,
        template: filterParams.template,
        location: filterParams.location
      };

      console.log('loadCardView called with params:', params);

      $.ajax({
        url: '<?= base_url("device/get_devices_ajax") ?>',
        type: 'POST',
        dataType: 'json',
        data: params,
        success: function(response) {
          console.log('Card view AJAX response:', response);
          var container = $('#deviceCardContainer');
          container.empty();

          if (response.data && response.data.length > 0) {
            response.data.forEach(function(device) {
              var cardHtml = generateCardHtml(device);
              container.append(cardHtml);
            });
            updateCardPagination(response.total, response.current_page, response.total_pages);
          } else {
            container.html('<div class="col-12 text-center"><p><?php echo lang("no_data_found"); ?></p></div>');
            $('#cardPaginationContainer').hide();
          }
        },
        error: function(xhr, status, error) {
          console.error('Error loading card view:', error);
          $('#deviceCardContainer').html('<div class="col-12"><div class="alert alert-danger"><?php echo lang("error_loading_data"); ?></div></div>');
        }
      });
    }

    // Generate card HTML
    function generateCardHtml(device) {
      var imageHtml = '';
      
      // Parse device_information for brand and model
      var deviceInfo = {};
      try {
        deviceInfo = JSON.parse(device.device_information || '{}');
      } catch (e) {
        deviceInfo = {};
      }

      // Parse custom fields
      var customFieldsHtml = '';
      if (device.custom_fields && Object.keys(device.custom_fields).length > 0) {
        customFieldsHtml = '<div class="custom-fields-display mt-2">';
        for (var key in device.custom_fields) {
          if (device.custom_fields.hasOwnProperty(key)) {
            customFieldsHtml += '<div class="custom-field-item">';
            customFieldsHtml += '<strong>' + key + ':</strong>';
            customFieldsHtml += '<span>' + device.custom_fields[key] + '</span>';
            customFieldsHtml += '</div>';
          }
        }
        customFieldsHtml += '</div>';
      }

      // แสดงรูปเลยใน Card view
      if (device.image_url && device.image_url !== '') {
        // ถ้ามี URL รูป ให้แสดงรูปเลย
        imageHtml = '<img src="' + device.image_url + '" alt="Device Image" class="img-fluid rounded mb-2" style="max-height:80px;object-fit:cover;">';
      } else {
        // ถ้าไม่มีรูป ให้แสดง icon
        imageHtml = '<span class="bi bi-box" style="font-size:3rem;color:#ccc;"></span>';
      }

      return '<div class="col-md-4 mb-3 device-card">' +
             '<div class="card h-100 shadow-sm">' +
             '<div class="card-body text-center">' +
             imageHtml +
             '<h6 class="mt-2 mb-1">' + device.name + '</h6>' +
             '<div class="text-muted small mb-1">' + (device.brand || '-') + ' | ' + (device.model || '-') + '</div>' +
             '<div class="mb-2">' + device.active + '</div>' +
             '<div class="mb-2" style="justify-items: center;">' + customFieldsHtml + '</div>' +
             device.actions +
             '</div>' +
             '</div>' +
             '</div>';
    }

    // Update card pagination
    function updateCardPagination(total, currentPage, totalPages) {
      var pagination = $('#cardPagination');
      pagination.empty();

      if (totalPages <= 1) {
        $('#cardPaginationContainer').hide();
        return;
      }

      $('#cardPaginationContainer').show();

      // Previous button
      if (currentPage > 1) {
        pagination.append('<li class="page-item"><a class="page-link" href="#" data-page="' + (currentPage - 1) + '"><?php echo lang("previous_page"); ?></a></li>');
      }

      // Page numbers
      var startPage = Math.max(1, currentPage - 2);
      var endPage = Math.min(totalPages, currentPage + 2);
      
      if (startPage > 1) {
        pagination.append('<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>');
        if (startPage > 2) {
          pagination.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
        }
      }
      
      for (var i = startPage; i <= endPage; i++) {
        var active = i === currentPage ? 'active' : '';
        pagination.append('<li class="page-item ' + active + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>');
      }
      
      if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
          pagination.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
        }
        pagination.append('<li class="page-item"><a class="page-link" href="#" data-page="' + totalPages + '">' + totalPages + '</a></li>');
      }

      // Next button
      if (currentPage < totalPages) {
        pagination.append('<li class="page-item"><a class="page-link" href="#" data-page="' + (currentPage + 1) + '"><?php echo lang("next_page"); ?></a></li>');
      }
    }

    // Handle pagination clicks
    $(document).on('click', '#cardPagination .page-link', function(e) {
      e.preventDefault();
      var page = parseInt($(this).data('page'));
      console.log('Pagination clicked - Page:', page, 'Current page before:', currentPage);
      if (page) {
        currentPage = page;
        console.log('Loading card view for page:', currentPage);
        loadCardView();
      }
    });

    // Delete device function
    window.deleteDevice = function(deviceId) {
      if (confirm('<?php echo lang("confirm_delete_device"); ?>')) {
        $.ajax({
          url: '<?= base_url("device/delete/") ?>' + deviceId,
          type: 'POST',
          dataType: 'json',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          success: function(response) {
            if (response.status === 'success') {
              // Show success message
              showNotification('<?php echo lang("device_deleted_successfully"); ?>', 'success');
              
              // Reload data
              if (currentView === 'list') {
                loadDevices();
              } else {
                loadCardView();
              }
            } else {
              showNotification('<?php echo lang("error_deleting_device"); ?>', 'danger');
            }
          },
          error: function() {
            showNotification('<?php echo lang("error_deleting_device"); ?>', 'danger');
          }
        });
      }
    };

    // Load filter options from server
    function loadFilterOptions() {
      $.ajax({
        url: '<?= base_url("device/get_filter_options") ?>',
        type: 'GET',
        success: function(response) {
          // Populate template filter
          var templateSelect = $('#filterTemplate');
          templateSelect.find('option:gt(0)').remove();
          response.templates.forEach(function(template) {
            templateSelect.append('<option value="' + template + '">' + template + '</option>');
          });

          // Populate location filter
          var locationSelect = $('#filterLocation');
          locationSelect.find('option:gt(0)').remove();
          response.locations.forEach(function(location) {
            locationSelect.append('<option value="' + location + '">' + location + '</option>');
          });
        },
        error: function(xhr, error, thrown) {
          console.error('Error loading filter options:', error);
          console.error('Response:', xhr.responseText);
        }
      });
    }

    // Debug function to test database connection
    function testDatabase() {
      $.ajax({
        url: '<?= base_url("device/debug") ?>',
        type: 'GET',
        success: function(response) {
          if (response.status === 'success') {
          } else {
          }
        },
        error: function(xhr, error, thrown) {
          console.error('Database test failed:', error);
          console.error('Response:', xhr.responseText);
        }
      });
    }

    // Test database connection on page load
    testDatabase();

    // Device Image Hover Effect for Table Only
    $(document).on('mouseenter', '.device-image-table', function() {
      var deviceImage = $(this).data('device-image');
      var templateImage = $(this).data('template-image');
      var imageUrl = deviceImage || templateImage;

      if (imageUrl) {
        $(this).css('--hover-image', 'url(' + imageUrl + ')');
      }
    });

    // Close hover effect when clicking outside (table only)
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.device-image-table').length) {
        $('.device-image-table').css('--hover-image', 'none');
      }
    });
  });
</script>