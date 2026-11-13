<!-- Step 4: Metrics for Add Device -->

<h5><?php echo lang('device_metrics') ?></h5>
<p class="text-muted small">Metrics are copied from the selected template and are read-only while adding a device. Edit them after the device is created. For RTSP cameras, snapshot triggers can be set here.</p>
<div id="metrics-container"></div>
<input type="hidden" name="metrics" id="metrics_json">
<input type="hidden" name="snapshot_triggers_json" id="snapshot_triggers_json" value="[]">
<div class="d-flex justify-content-between mt-3">
  <button type="button" class="btn btn-secondary" onclick="goToStep(3)"><?php echo lang('back') ?></button>
  <button type="button" class="btn btn-primary" onclick="goToStep(5)"><?php echo lang('next') ?></button>
</div>
