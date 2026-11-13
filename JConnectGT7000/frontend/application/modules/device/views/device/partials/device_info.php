    <!-- Step 2: Device Info  -->
    <h5><?php echo lang('device_info') ?> </h5>

    <?php echo $device; ?>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label><?php echo lang('brand') ?> </label>
        <input type="text" id="templateBrand" class="form-control" value="<?= isset($Brand) ? htmlspecialchars($Brand) : '' ?>" disabled readonly>
      </div>
      <div class="col-md-6 mb-3">
        <label><?php echo lang('model') ?> </label>
        <input type="text" id="templateModel" class="form-control" value="<?= isset($Model) ? htmlspecialchars($Model) : '' ?>" disabled readonly>
      </div>
    </div>

    <div class="mb-3">
      <label><?php echo lang('device_name') ?> </label>
      <input type="text" name="name" class="form-control" value="<?= isset($device->name) ? htmlspecialchars($device->name) : '' ?>" required />
    </div>
    <?php include 'device_role_fields.php'; ?>
    <div class="mb-3">
      <label><?php echo lang('serial_number') ?> </label>
      <input type="text" name="serial_number" class="form-control" value="<?= isset($device->serial_number) ? htmlspecialchars($device->serial_number) : '' ?>" />
    </div>
    <?php if (isset($device->device_information)) { ?>
      <div class="mb-3">
        <label><?php echo lang('device_profile') ?> </label>
        <input type="text" class="form-control" value="<?= isset($device->device_profile_name) ? htmlspecialchars($device->device_profile_name) : '' ?>" disabled readonly />
      </div>
    <?php } ?>
    <div class="mb-3">
      <label><?php echo lang('location') ?> </label>
      <?php include __DIR__ . '/site_node_select.php'; ?>
      <textarea name="location" class="form-control"><?= isset($device->location) ? htmlspecialchars($device->location) : '' ?></textarea>
    </div>
    <div class="mb-3">
      <label><?php echo lang('description') ?> </label>
      <textarea name="description" class="form-control"><?= isset($device->description) ? htmlspecialchars($device->description) : '' ?></textarea>
    </div>

    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (isset($device->is_active) && ($device->is_active == "t")) ? 'checked' : '' ?> />
      <label class="form-check-label" for="is_active"><?php echo lang('is_active') ?></label>
    </div>

    <div class="d-flex justify-content-between mt-3">
      <button type="button" class="btn btn-secondary" onclick="goToStep(1)"><?php echo lang('back') ?></button>
      <button type="button" class="btn btn-primary float-end" onclick="goToStep(3)"><?php echo lang('next') ?></button>
    </div>
