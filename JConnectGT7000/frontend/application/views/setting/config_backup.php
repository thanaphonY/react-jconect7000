<section class="container py-4">
  <div class="mb-4">
    <h5><?php echo lang("settings"); ?></h5>
  </div>
  <?php $this->load->view('setting/tabs'); ?>
  <div class="pt-3">
  <h5 class="mb-3">Backup / Restore Config</h5>
  <p class="text-body-secondary">Exports settings, devices, templates, dashboards, widgets, notification channels, report templates, schedules, and LoRaWAN applications. Device readings and logs stay in the database.</p>
  <div class="row g-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6>Export</h6>
          <form method="post" action="<?php echo base_url('config_backup/export'); ?>">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="include_users" value="1" id="include_users">
              <label class="form-check-label" for="include_users">Include users and passwords</label>
            </div>
            <button class="btn btn-primary" type="submit">Download backup</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6>Restore</h6>
          <p>One failed row cancels the whole restore. Readings in <code>device_data</code> and log tables are not deleted.</p>
          <form method="post" action="<?php echo base_url('config_backup/restore'); ?>" enctype="multipart/form-data">
            <input class="form-control mb-3" type="file" name="backup" accept="application/json,.json" required>
            <button class="btn btn-warning" type="submit">Restore config</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  </div>
</section>
