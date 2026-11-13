
<div class="form-group mb-3">
  <label><?php echo lang('default_dashboard_layout') ?> </label>
  <textarea class="form-control" name="default_dashboard_layout" rows="5"><?= isset($device) ? $device->default_dashboard_layout : '' ?></textarea>
</div>
