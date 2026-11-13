<!DOCTYPE html>
<html>
  <head>
      <meta charset="UTF-8">
      <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
      <title>Sign In | Igniter Admin</title>
      <!-- Favicon-->
      <link rel="icon" href="<?php echo iaBase(); ?>assets/images/favicon.ico" type="image/x-icon">

      <!-- Google Fonts -->
      <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&subset=latin,cyrillic-ext" rel="stylesheet" type="text/css">
      <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" type="text/css">

      <!-- Bootstrap Core Css -->
      <link href="<?php echo iaBase(); ?>assets/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">

      <!-- Waves Effect Css -->
      <link href="<?php echo iaBase(); ?>assets/plugins/node-waves/waves.css" rel="stylesheet" />

      <!-- Animation Css -->
      <link href="<?php echo iaBase(); ?>assets/plugins/animate-css/animate.min.css" rel="stylesheet" />

      <!-- Custom Css -->
      <link href="<?php echo iaBase(); ?>assets/css/style.css" rel="stylesheet">
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
      <!-- /.login-logo -->
      <div class="card">
        <div class="body">
          <p class="login-box-msg"><?php echo lang('forget_pass_msg'); ?></p>
          <form action="<?php echo base_url() . 'user/forgetpassword' ?>" method="post" id="forgot-form">

            <div class="input-group">
              <span class="input-group-addon">
                <i class="material-icons">email</i>
              </span>
              <div class="form-line">
                <input type="email" name="email" class="form-control" placeholder="<?php echo lang('email'); ?>" data-validation="email" required />
              </div>
            </div>

            <div class="row">
              <!-- /.col -->
              <div class="col-xs-12">
                <button type="submit" style="width: 100%" class="btn btn-primary btn-lg bg-grey waves-effect"><?php echo lang('get_new_password'); ?></button>
              </div>
              <div class="text-center">
                <span class="glyph-icon-back glyphicon glyphicon-circle-arrow-left" style="cursor:pointer" onclick="window.history.back()" title="Back"></span>
              </div>
              <!-- /.col -->
            </div>
          </form>
          <div class="social-auth-links text-center">
          </div>
          <!-- /.social-auth-links -->
        </div>
      </div>
      <!-- /.login-box-body -->
    </div>
  <!-- /.login-box -->
  <!-- Jquery Core Js -->
  <script src="<?php echo iaBase(); ?>assets/plugins/jquery/jquery.min.js"></script>

  <!-- Bootstrap Core Js -->
  <script src="<?php echo iaBase(); ?>assets/plugins/bootstrap/js/bootstrap.js"></script>

  <!-- Waves Effect Plugin Js -->
  <script src="<?php echo iaBase(); ?>assets/plugins/node-waves/waves.js"></script>

  <!-- Validation Plugin Js -->
  <script src="<?php echo iaBase(); ?>assets/plugins/jquery-validation/jquery.validate.js"></script>

  <!-- Custom Js -->
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
      $('#forgot-form').validate();
    });
  </script>
  </body>
</html>
