<!DOCTYPE html>
<html>
  <head>
      <meta charset="UTF-8">
      <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
      <title><?php echo lang('forgot_password'); ?> | <?php echo (getSetting('website')) ? getSetting('website') : 'JCONNECT'; ?></title>
      <link rel="icon" href="<?php echo iaBase(); ?>assets/images/favicon.ico" type="image/x-icon">
      <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&subset=latin,cyrillic-ext" rel="stylesheet" type="text/css">
      <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" type="text/css">
      <link href="<?php echo iaBase(); ?>assets/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">
      <link href="<?php echo iaBase(); ?>assets/plugins/node-waves/waves.css" rel="stylesheet" />
      <link href="<?php echo iaBase(); ?>assets/plugins/animate-css/animate.min.css" rel="stylesheet" />
      <link href="<?php echo iaBase(); ?>assets/css/style.css" rel="stylesheet">
      <style>
        .otp-input {
          letter-spacing: 8px;
          font-size: 22px;
          text-align: center;
          font-weight: 600;
        }
      </style>
  </head>
  <body class="hold-transition fp-page">
    <div class="fp-box">
      <div class="logo ia-front-logo">
                    <?php
$logo = getSetting('logo');
if ($logo != '') {
    ?>
        <img src="<?php echo iaBase() . 'assets/images/' . $logo; ?>" id="logo">
<?php } else {?>
        <h2>
          <strong>  <?php echo getSetting('website'); ?> </strong>
        </h2>
<?php }?>
      </div>
      <div class="card">
        <div class="body">
          <p class="login-box-msg"><?php echo sprintf(lang('enter_otp_msg'), htmlspecialchars($masked_email, ENT_QUOTES, 'UTF-8')); ?></p>
          <form action="<?php echo base_url() . 'forgot_password/otp' ?>" method="post" id="otp-form">
            <div class="input-group">
              <span class="input-group-addon">
                <i class="material-icons">vpn_key</i>
              </span>
              <div class="form-line">
                <input type="text" name="otp" id="otp" class="form-control otp-input" placeholder="<?php echo lang('otp'); ?>" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required />
              </div>
            </div>
            <div class="row">
              <div class="col-xs-12">
                <button type="submit" style="width: 100%" class="btn btn-primary btn-lg bg-grey waves-effect"><?php echo lang('verify_otp'); ?></button>
              </div>
              <div class="text-center" style="margin-top: 15px;">
                <a href="<?php echo base_url() . 'forgot_password/resend'; ?>"><?php echo lang('resend_otp'); ?></a>
              </div>
              <div class="text-center">
                <span class="glyph-icon-back glyphicon glyphicon-circle-arrow-left" style="cursor:pointer" onclick="window.location='<?php echo base_url() . 'forgot_password'; ?>'" title="Back"></span>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  <script src="<?php echo iaBase(); ?>assets/plugins/jquery/jquery.min.js"></script>
  <script src="<?php echo iaBase(); ?>assets/plugins/bootstrap/js/bootstrap.js"></script>
  <script src="<?php echo iaBase(); ?>assets/plugins/node-waves/waves.js"></script>
  <script src="<?php echo iaBase(); ?>assets/plugins/jquery-validation/jquery.validate.js"></script>
  <script src="<?php echo iaBase(); ?>assets/js/admin.js"></script>
  <script src="<?php echo iaBase(); ?>assets/js/pages/examples/sign-in.js"></script>
  <script src="<?php echo iaBase(); ?>assets/js/custom.js"></script>
  <script src="<?php echo iaBase(); ?>assets/plugins/bootstrap-notify/bootstrap-notify.js"></script>
  <script>
    $(document).ready(function() {
      <?php
      if (isset($this->session->get_userdata()['alert_msg'])) {
          ?>
        $msg = '<?php echo addslashes($this->session->get_userdata()['alert_msg']['msg']); ?>';
        $type = '<?php echo $this->session->get_userdata()['alert_msg']['type']; ?>';
        showNotification($msg, $type);
      <?php
          $this->session->unset_userdata('alert_msg');
      }
      ?>
      $('#otp').on('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
      });
      $('#otp-form').validate({
        rules: {
          otp: { required: true, minlength: 6, maxlength: 6 }
        }
      });
    });
  </script>
  </body>
</html>
