<!-- Step 2: Communication Configuration for Edit Device -->

<h5><?php echo lang('communication_configuration') ?> </h5>
<div id="endpoint-communication-notice" class="alert alert-info" style="display:none">
  Gateway Endpoint does not require a direct communication configuration. Data is received through the selected Parent Gateway.
</div>
<div id="direct-communication-fields">
  <div id="comm-fields-container"></div>
</div>
<input type="hidden" name="communication_config" id="comm_config_json" />

<div class="d-flex justify-content-between mt-3">
  <button type="button" class="btn btn-secondary" onclick="goToStep(1)"><?php echo lang('back') ?></button>
  <button type="button" class="btn btn-primary" onclick="goToStep(3)"><?php echo lang('next') ?></button>
</div>
