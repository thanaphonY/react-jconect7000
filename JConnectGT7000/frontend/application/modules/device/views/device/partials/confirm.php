      <!-- Step 5: Confirm -->

            <h5> <?php echo lang('confirm_device_conifg') ?> </h5>

            <div class="mb-4">
              <h6><?php echo lang('device_template') ?> </h6>
              <p>
                <strong><?php echo lang('name') ?> : </strong> <span id="confirm-template-name"></span><br>
                <strong>Category :</strong> <span id="confirm-template-category"></span><br>
                <strong><?php echo lang('protocol') ?> :</strong> <span id="confirm-template-protocol"></span><br>
                <strong><?php echo lang('brand') ?>  :</strong> <span id="confirm-template-brand"></span><br>
                <strong> <?php echo lang('model') ?> :</strong> <span id="confirm-template-model"></span>
              </p>
            </div>

            <div class="mb-4">
              <h6><?php echo lang('device_info') ?> </h6>
              <p>
                <strong><?php echo lang('name') ?> :</strong> <span id="confirm-name"></span><br>
                <strong>Device Role :</strong> <span id="confirm-device-role"></span><br>
                <span id="confirm-parent-gateway-row" style="display:none"><strong>Parent Gateway :</strong> <span id="confirm-parent-gateway"></span><br></span>
                <strong><?php echo lang('serial_number') ?> :</strong> <span id="confirm-serial"></span><br>
                <strong> <?php echo lang('location') ?>  : </strong> <span id="confirm-location"></span><br>
                <strong><?php echo lang('description') ?> : </strong> <span id="confirm-description"></span><br>
                <strong><?php echo lang('status') ?> </strong> <span id="confirm-status"></span>
              </p>
            </div>

            <div class="mb-4 d-none">
              <h6><?php echo lang('communication_config') ?> </h6>
              <pre id="confirm-comm" class="bg-light p-3 border rounded small"></pre>
            </div>

            <div class="mb-4 d-none">
              <h6><?php echo lang('metrics') ?> </h6>
              <pre id="confirm-metrics" class="bg-light p-3 border rounded small"></pre>
            </div>
    

            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-secondary" onclick="goToStep(4)"><?php echo lang('back') ?> </button>
              <button type="submit" class="btn btn-success"><?php echo lang('save_device') ?> </button>
            </div>
