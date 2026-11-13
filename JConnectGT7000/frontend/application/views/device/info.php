<div class="card clearfix">
  <div class="header">
    <h5><?php echo lang('device_info'); ?> </h5>
  </div>
  <ul class="list-group list-group-flush">
    <li class="list-group-item"><?php echo lang('device_serial'); ?><span class="badge badge-primary badge-pill"><?php echo $device_serial; ?></span></li>
    <li class="list-group-item"><?php echo lang('device_name'); ?><span class="badge badge-primary badge-pill"><?php echo $device_name; ?></span></li>
    <li class="list-group-item"><?php echo lang('device_fw'); ?><span class="badge badge-primary badge-pill"><?php echo $device_fw; ?></li>
    <li class="list-group-item"><?php echo lang('device_ch1'); ?><span class="badge badge-primary badge-pill">
        <?php if ($device_ch1 == 1) {
          echo lang('device_type_switch');
        } elseif ($device_ch1 == 2) {
          echo lang('device_type_receptacle');
        } elseif ($device_ch1 == 3) {
          echo lang('device_type_remote');
        } else {
          echo lang('device_type_notuse');
        } ?>
    </li>
    <li class="list-group-item"><?php echo lang('device_ch2'); ?><span class="badge badge-primary badge-pill">
        <?php if ($device_ch2 == 1) {
          echo lang('device_type_switch');
        } elseif ($device_ch2 == 2) {
          echo lang('device_type_receptacle');
        } elseif ($device_ch2 == 3) {
          echo lang('device_type_remote');
        } else {
          echo lang('device_type_notuse');
        } ?>
    </li>
    <li class="list-group-item"><?php echo lang('device_ch3'); ?><span class="badge badge-primary badge-pill">
        <?php if ($device_ch3 == 1) {
          echo lang('device_type_switch');
        } elseif ($device_ch3 == 2) {
          echo lang('device_type_receptacle');
        } elseif ($device_ch3 == 3) {
          echo lang('device_type_remote');
        } else {
          echo lang('device_type_notuse');
        } ?>
    </li>
    <li class="list-group-item"><?php echo lang('device_last_use'); ?><span class="badge badge-primary badge-pill"><?php echo $device_last_use; ?></li>
  </ul>
</div>