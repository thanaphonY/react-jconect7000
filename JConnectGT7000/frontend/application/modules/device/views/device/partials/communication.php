
          <!-- Step 3: Communication Configuration -->

            <h5><?php echo lang('communication_configuration') ?> </h5>
            <div id="comm-fields-container"></div>
            <input type="hidden" name="communication_config" id="comm_config_json" />

            <div class="d-flex justify-content-between mt-3">
              <button type="button" class="btn btn-secondary" onclick="goToStep(1)"><?php echo lang('back') ?></button>
              <button type="button" class="btn btn-primary" onclick="goToStep(4)"><?php echo lang('next') ?></button>
            </div>
        