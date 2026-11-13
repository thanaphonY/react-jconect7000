<?php
$page = $this->router->class;
$tabClass = function ($name) use ($page) {
    return $page === $name ? 'nav-link active' : 'nav-link';
};
?>
<ul class="nav nav-tabs" id="settingsTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <?php if ($page === 'setting') { ?>
      <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-setting" type="button" role="tab">
        <i class="fa fa-cogs"></i> <?php echo lang("general"); ?>
      </button>
    <?php } else { ?>
      <a class="nav-link" href="<?php echo base_url('setting'); ?>#general-setting"><i class="fa fa-cogs"></i> <?php echo lang("general"); ?></a>
    <?php } ?>
  </li>
  <li class="nav-item" role="presentation">
    <?php if ($page === 'setting') { ?>
      <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email-setting" type="button" role="tab">
        <i class="fa fa-envelope"></i> <?php echo lang("email"); ?>
      </button>
    <?php } else { ?>
      <a class="nav-link" href="<?php echo base_url('setting'); ?>#email-setting"><i class="fa fa-envelope"></i> <?php echo lang("email"); ?></a>
    <?php } ?>
  </li>
  <li class="nav-item" role="presentation">
    <?php if ($page === 'setting') { ?>
      <button class="nav-link" id="permission-tab" data-bs-toggle="tab" data-bs-target="#permission-setting" type="button" role="tab">
        <i class="fa fa-indent"></i> <?php echo lang("permission"); ?>
      </button>
    <?php } else { ?>
      <a class="nav-link" href="<?php echo base_url('setting'); ?>#permission-setting"><i class="fa fa-indent"></i> <?php echo lang("permission"); ?></a>
    <?php } ?>
  </li>
  <li class="nav-item" role="presentation">
    <?php if ($page === 'setting') { ?>
      <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates-setting" type="button" role="tab">
        <i class="fa fa-puzzle-piece"></i> <?php echo lang('templates'); ?>
      </button>
    <?php } else { ?>
      <a class="nav-link" href="<?php echo base_url('setting'); ?>#templates-setting"><i class="fa fa-puzzle-piece"></i> <?php echo lang('templates'); ?></a>
    <?php } ?>
  </li>
  <li class="nav-item" role="presentation">
    <a class="<?php echo $tabClass('config_backup'); ?>" href="<?php echo base_url('config_backup'); ?>">Backup / Restore</a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="<?php echo $tabClass('retention'); ?>" href="<?php echo base_url('retention'); ?>">Data retention</a>
  </li>
</ul>
