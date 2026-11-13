<form role="form bor-rad" enctype="multipart/form-data" action="<?php echo base_url() . 'dashboard/addEditDevice' ?>" method="post">
  <div class="box-body">
    <div class="row">
  
        <div class="col-md-12">
          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <input type="text" name="serial" class="form-control input-lg" value="" required>
              <label for="" class="form-label"><?php echo lang('device_serial'); ?></label>
            </div>
          </div>
          <div class="form-group form-float">
            <div class="form-line">
              <input type="text" style="display: none">
              <input type="text" name="location" class="form-control input-lg" value="" required>
              <label for="" class="form-label"><?php echo lang('device_location'); ?></label>
            </div>
          </div>
        </div>
   
    </div>
   
      <div class="box-footer sub-btn-wdt">
        <button type="submit" name="submit" value="add" class="btn btn-primary wdt-bg "><?php echo lang('Add'); ?></button>
      </div>
  
</form>
<script>
  $.AdminBSB.input.activate();
</script>