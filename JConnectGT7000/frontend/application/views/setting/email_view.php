<?php
// Bootstrap 5 refactor of email_view
?>
<div class="card p-4 mb-4">
  <div class="card-header">
    <h5 class="card-title mb-0"><?php echo lang('emailServerSetting'); ?></h5>
  </div>
  <form method="post" enctype="multipart/form-data" action="<?php echo base_url() . 'setting/editSetting' ?>" class="needs-validation" novalidate>
    <div class="mb-3">
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="mail_setting" id="php_mailer" value="php_mailer" <?php if (isset($result['mail_setting']) && $result['mail_setting'] == 'php_mailer') echo 'checked'; ?>>
        <label class="form-check-label" for="php_mailer"><?php echo lang('smtp'); ?></label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="mail_setting" id="simple_mail" value="simple_mail" <?php if (isset($result['mail_setting']) && $result['mail_setting'] == 'simple_mail') echo 'checked'; ?>>
        <label class="form-check-label" for="simple_mail"><?php echo lang('server_default'); ?></label>
      </div>
    </div>

    <div class="mb-3">
      <label for="company_name" class="form-label"><?php echo lang('company_name'); ?></label>
      <input type="text" class="form-control" name="company_name" id="company_name" value="<?php echo isset($result['company_name']) ? $result['company_name'] : ''; ?>">
    </div>

    <div id="phpmailer" style="display:<?php echo (isset($result['mail_setting']) && $result['mail_setting'] == 'php_mailer') ? 'block' : 'none'; ?>;">
      <div class="mb-3">
        <label for="SMTP_EMAIL" class="form-label"><?php echo lang('smtp_email'); ?></label>
        <input type="email" class="form-control" name="SMTP_EMAIL" id="SMTP_EMAIL" value="<?php echo isset($result['SMTP_EMAIL']) ? $result['SMTP_EMAIL'] : ''; ?>">
      </div>
      <div class="mb-3">
        <label for="HOST" class="form-label"><?php echo lang('smtp_host'); ?></label>
        <input type="text" class="form-control" name="HOST" id="HOST" value="<?php echo isset($result['HOST']) ? $result['HOST'] : ''; ?>">
      </div>
      <div class="mb-3">
        <label for="PORT" class="form-label"><?php echo lang('smtp_port'); ?></label>
        <input type="text" class="form-control" name="PORT" id="PORT" value="<?php echo isset($result['PORT']) ? $result['PORT'] : ''; ?>">
      </div>
      <div class="mb-3">
        <label for="SMTP_SECURE" class="form-label"><?php echo lang('smtp_secure'); ?></label>
        <input type="text" class="form-control" name="SMTP_SECURE" id="SMTP_SECURE" value="<?php echo isset($result['SMTP_SECURE']) ? $result['SMTP_SECURE'] : ''; ?>">
      </div>
      <div class="mb-3">
        <label for="SMTP_PASSWORD" class="form-label"><?php echo lang('smtp_password'); ?></label>
        <input type="password" class="form-control showpassword" name="SMTP_PASSWORD" id="test1" value="<?php echo isset($result['SMTP_PASSWORD']) ? $result['SMTP_PASSWORD'] : ''; ?>">
      </div>
    </div>

    <div id="simplemail" style="display:<?php echo (isset($result['mail_setting']) && $result['mail_setting'] == 'simple_mail') ? 'block' : 'none'; ?>;">
      <div class="mb-3">
        <label for="EMAIL" class="form-label"><?php echo lang('email'); ?></label>
        <input type="email" class="form-control" name="EMAIL" id="EMAIL" value="<?php echo isset($result['EMAIL']) ? $result['EMAIL'] : ''; ?>">
      </div>
    </div>

    <input name="register_allowed" type="hidden" value="<?php echo (isset($result['register_allowed']) && $result['register_allowed'] == 1) ? '1' : '0'; ?>">

    <?php if (CheckPermission('setting', "all_update")) { ?>
      <div class="text-center">
        <button type="submit" class="btn btn-primary w-50"><?php echo lang('save'); ?></button>
      </div>
    <?php } ?>
  </form>
</div>
