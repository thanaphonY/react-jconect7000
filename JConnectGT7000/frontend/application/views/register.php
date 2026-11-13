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
  <body class="hold-transition signup-page">
    <div class="signup-box">
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
          <div class="msg"><h3><?php echo lang('sign_up');?></h3></div>
          <form action="<?php echo base_url() . 'user/registration'; ?>" method="post" id="register-form">

			        <div class="input-group">
              			<span class="input-group-addon">
                			<i class="material-icons">person</i>
              			</span>
              			<div class="form-line">
                			<input type="text" name="name" class="form-control" required  placeholder="Name">
              			</div>
            		</div>

			        <div class="input-group">
              			<span class="input-group-addon">
                			<i class="material-icons">email</i>
              			</span>
              			<div class="form-line">
                			<input type="text" name="email" class="form-control" required  placeholder="Email">
              			</div>
            		</div>


            <div class="input-group">
              <span class="input-group-addon">
                <i class="material-icons">lock</i>
              </span>
              <div class="form-line">
                <input type="password" class="form-control" name="password" id="password" placeholder="Password" data-validation="required">
              </div>
            </div>

            <div class="input-group">
              <span class="input-group-addon">
                <i class="material-icons">lock</i>
              </span>
              <div class="form-line">
                <input type="password" name="password_confirmation" class="form-control" placeholder="Retype password" data-validation="confirmation">
              </div>
            </div>

            <div class="input-group">
              <span class="input-group-addon">
                <i class="material-icons">shuffle</i>
              </span>
              <div class="form-line">
                <?php $type = json_decode(getSetting('user_type'));?>
                <select name="user_type" id="" class="form-control">
                  <option value=""><?php echo lang('select_type') ?></option>
                  <?php
foreach ($type as $key => $value) {
    if ($value != 'admin') {
        echo '<option value="' . $value . '">' . ucfirst($value) . '</option>';
    }
}
?>
                </select>
              </div>
            </div>

            <?php getCustomFields('user');?>

            <div class="row">
              <div class="col-xs-12">
                  <!--  <input type="hidden" name="user_type" value="<?php //echo getSetting('user_type');?>"> -->
                  <input type="hidden" name="call_from" value="reg_page">
                  <button type="submit" name="submit" style="width: 100%" class="btn btn-primary btn-lg bg-grey waves-effect"><?php echo lang('register'); ?></button>
                </div>
              </div>
          </form>
          <br>
          <a href="<?php echo base_url('login'); ?>" class="text-center"><?php echo lang('already_hav_an_account?_Login'); ?></a>
        </div>
      </div>
      <!-- /.form-box -->
    </div>
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
  </body>
<script>
$(document).ready(function(){
  <?php if ($this->input->get('invited') && $this->input->get('invited') != '') {?>
    $burl = '<?php echo base_url() ?>';
    $.ajax({
      url: $burl+'user/chekInvitation',
      method:'post',
      data:{
        code: '<?php echo $this->input->get('invited'); ?>'
      },
      dataType: 'json'
    }).done(function(data){
      console.log(data);
      if(data.result == 'success') {
        $('[name="email"]').val(data.email);
        $('form').attr('action', $burl + 'user/registerInvited/' + data.ia_users_id);
      } else{
        window.location.href= $burl + 'user/login';
      }
    });
  <?php }?>

  $('#register-form').validate({

    rules: {
        password: {
            required: true,
        },
        password_confirmation: {
            required: true,
            equalTo: "#password"
        }
    }
  });
});
</script>
</html>
