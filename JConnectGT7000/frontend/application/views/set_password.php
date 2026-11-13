<!DOCTYPE html>
<html>
  <head>
      <meta charset="UTF-8">
      <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
      <title><?php echo lang('set_new_password'); ?> | <?php echo (getSetting('website')) ? getSetting('website') : 'JCONNECT'; ?></title>
      <link rel="icon" href="<?php echo iaBase(); ?>assets/images/favicon.ico" type="image/x-icon">
      <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&subset=latin,cyrillic-ext" rel="stylesheet" type="text/css">
      <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" type="text/css">
      <link href="<?php echo iaBase(); ?>assets/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">
      <link href="<?php echo iaBase(); ?>assets/plugins/node-waves/waves.css" rel="stylesheet" />
      <link href="<?php echo iaBase(); ?>assets/plugins/animate-css/animate.min.css" rel="stylesheet" />
      <link href="<?php echo iaBase(); ?>assets/css/style.css" rel="stylesheet">
      <style>
        .logo b, .logo strong, .fp-box .logo h2, .fp-box .logo h3 {
          color: #555;
        }
        .set-password-title {
          color: #333;
          margin: 0 0 20px;
          text-align: center;
        }
      </style>
  </head>
  <body class="hold-transition fp-page">
  <?php
if ($email == 'allredyUsed') {?>
      <div class="container text-center">
        <h2><?php echo lang('you_have_already_reset_your_password'); ?></h2>
         <p><?php echo lang('please_login'); ?> <a href="<?php echo base_url() . 'user/login'; ?>"><?php echo lang('here'); ?></a> </p>
      </div>
    <?php } else {?>
    <div class="fp-box">
      <div class="logo ia-front-logo">
                    <?php
$logo = getSetting('logo');
if ($logo != '') {
    ?>
        <img src="<?php echo iaBase() . 'assets/images/' . $logo; ?>" id="logo">
<?php }?>
        <h3>
          <strong><?php echo getSetting('website'); ?></strong>
        </h3>
      </div>
      <div class="card">
        <div class="body">
          <div class="msg">
            <h2 class="set-password-title"><?php echo lang('set_new_password'); ?></h2>
          </div>
          <form class="createaccount" action="<?php echo base_url() . 'user/reset_password' ?>" method="post">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="input-group">
              <span class="input-group-addon">
                <i class="material-icons">lock</i>
              </span>
              <div class="form-line">
                <input type="password" id="password1" name="password_confirmation" class="form-control" placeholder="<?php echo lang('NewPassword'); ?>" required />
              </div>
            </div>
            <div class="input-group">
              <span class="input-group-addon">
                <i class="material-icons">lock</i>
              </span>
              <div class="form-line">
                <input type="password" id="password2" name="password" class="form-control" placeholder="<?php echo lang('ConfirmPassword'); ?>" required />
              </div>
            </div>
            <div class="row">
              <div class="col-xs-12">
                <button type="submit" name="sub" value="Set password" style="width: 100%" class="btn btn-primary btn-lg bg-grey waves-effect"><?php echo lang('set_password'); ?></button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
      <?php }?>
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
      var password1 = document.getElementById("password1");
      var password2 = document.getElementById("password2");
      if (password1 && password2) {
        password1.onchange = validatePassword;
        password2.onchange = validatePassword;
      }
      function validatePassword(){
        if (password1.value != password2.value) {
          password2.setCustomValidity("<?php echo lang('passwords_dont_match'); ?>");
        } else {
          password2.setCustomValidity('');
        }
      }
    });
  </script>
  </body>
</html>
