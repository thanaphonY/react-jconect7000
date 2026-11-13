<?php
// NOTE: This is the layout-converted version of your form using Bootstrap nav-tabs
// Assumes Bootstrap 4 or AdminLTE 3+ is available
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6">
      <h4 class="mb-0"><?= isset($template) ? lang('edit') : lang('add') ?> <?php echo lang('device_template') ?></h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('devicetemplate') ?>"><?php echo lang('device_template') ?> </a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= isset($template) ? lang('edit') : lang('add') ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>


<!-- Config Modal -->
<div class="modal fade" id="configModal" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"> <?php echo lang('configure_widget') ?></h5>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cfg-index">
        <div class="form-floating mb-2"><input id="cfg-title" class="form-control"><label><?php echo lang('title') ?></label></div>
        <div class="form-floating mb-2"><input id="cfg-key" class="form-control"><label> <?php echo lang('metric_key') ?></label></div>
        <div class="form-floating mb-2"><input id="cfg-unit" class="form-control"><label><?php echo lang('unit') ?></label></div>
        <div class="form-floating mb-2">
          <select id="cfg-type" class="form-select" onchange="toggleExtraFields(this.value)">
            <option value="value_box"><?php echo lang('value_box') ?> </option>
            <option value="gauge"><?php echo lang('gauge') ?></option>
            <option value="line_chart"><?php echo lang('line_chart') ?> </option>
            <option value="area_chart"><?php echo lang('area_chart') ?> </option>
            <option value="donut_chart"><?php echo lang('donut_chart') ?> </option>
            <option value="text_box"><?php echo lang('text_box') ?> </option>
          </select>
          <label><?php echo lang('widget_type') ?> </label>
        </div>
        <div id="extra-fields"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary btn-sm" onclick="saveConfig()"><?php echo lang('save') ?></button>
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo lang('close') ?></button>
      </div>
    </div>
  </div>
</div>


<div class="app-content">
  <div class="container-fluid">
    <form method="post" enctype="multipart/form-data">
      <ul class="nav nav-tabs" id="templateTab" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab"><?php echo lang('general') ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="comm-tab" data-toggle="tab" href="#comm" role="tab"><?php echo lang('communication') ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="device-tab" data-toggle="tab" href="#device" role="tab"> <?php echo lang('device_info') ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="metrics-tab" data-toggle="tab" href="#metrics" role="tab"><?php echo lang('metrics') ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="layout-tab" data-toggle="tab" href="#layout" role="tab"><?php echo lang('dashboard') ?></a>
        </li>
      </ul>

      <div class="tab-content pt-3" id="templateTabContent">
        <div class="tab-pane fade show active" id="general" role="tabpanel">
          <?php include 'partials/general_info.php'; ?>
        </div>
        <div class="tab-pane fade" id="comm" role="tabpanel">
          <?php include 'partials/communication.php'; ?>
        </div>
        <div class="tab-pane fade" id="device" role="tabpanel">
          <?php include 'partials/device_info.php'; ?>
        </div>
        <div class="tab-pane fade" id="metrics" role="tabpanel">
          <?php include 'partials/metrics.php'; ?>
        </div>
        <div class="tab-pane fade" id="layout" role="tabpanel">
          <?php include 'partials/dashboard.php'; ?>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-body">
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
              <?= isset($template) && $template->is_active == 't' ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active"><?php echo lang('is_active') ?> </label>
          </div>
          <div class="text-end">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-save"></i> <?php echo lang('save') ?>
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>


<script type="text/javascript">
  $(document).ready(function() {
    $('#templateTab a').on('click', function(e) {
      e.preventDefault();
      $(this).tab('show');
    });
  });
</script>