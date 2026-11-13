<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	<title><?php echo (getSetting('website')) ? getSetting('website') : 'JCONNECT'; ?></title>
	<!-- Favicon-->
	<link rel="apple-touch-icon" sizes="128x128" href="<?php echo iaBase(); ?>assets/images/touch-icon-ipad.png">
	<link rel="icon" href="<?php echo iaBase(); ?>assets/images/Logo.png" type="image/x-icon">


	<link href="<?php echo iaBase(); ?>assets/css/ia_icon.css" rel="stylesheet" type="text/css">
    <link href="<?php echo iaBase(); ?>assets/css/ia_material_icon.css" rel="stylesheet" type="text/css">

	<!-- Bootstrap Core Css -->
	<link href="<?php echo iaBase(); ?>assets/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">

	<!-- Waves Effect Css -->
	<link href="<?php echo iaBase(); ?>assets/plugins/node-waves/waves.css" rel="stylesheet" />

	<!-- Animation Css -->
	<link href="<?php echo iaBase(); ?>assets/plugins/animate-css/animate.min.css" rel="stylesheet" />

	<!-- Custom Css -->
	<link href="<?php echo iaBase(); ?>assets/css/style.css" rel="stylesheet">
	<style>
		.logo b {
			color: #555;
		}
	</style>
</head>

<body class="hold-transition login-page">
	<div class="login-box">
		<div class="logo ia-front-logo">
			<?php
			$logo = getSetting('logo');
			if ($logo != '') {
				?>
				<img src="<?php echo iaBase() . 'assets/images/' . $logo; ?>" id="logo">
			<?php }?>
				<h3>
					<strong> <?php echo getSetting('website'); ?> </strong>
				</h3>
		</div>
		<div class="card">
			<div class="body">
				<form action="<?php echo base_url() . 'user/authUser'; ?>" method="post" id="login-form">
					<div class="msg">
						<h2><?php echo lang('login'); ?></h2>
					</div>
					<div class="input-group">
						<span class="input-group-addon">
							<i class="material-icons">person</i>
						</span>
						<div class="form-line">
							<input type="text" name="email" class="form-control" id="" placeholder="<?php echo lang('email'); ?>" required autofocus>
						</div>
					</div>
					<div class="input-group">
						<span class="input-group-addon">
							<i class="material-icons">lock</i>
						</span>
						<div class="form-line">
							<input type="password" name="password" class="form-control" id="pwd" placeholder="<?php echo lang('password'); ?>" required>
						</div>
					</div>
					<div class="form-check">
						<input type="checkbox" class="form-check-input" name="remember_me" id="remember_me">
						<label class="form-check-label" for="remember_me" value="1">Remember Me</label>
					</div>
					<div class="row">
						<div class="col-md-12">
							<button style="width: 100%" class="btn btn-primary bg-grey waves-effect" type="submit"><?php echo lang('SIGN_IN'); ?></button>
						</div>
					</div>

					<div class="row m-t-15 m-b--20">
						<?php if (getSetting('register_allowed') == 1) { ?>
							<div class="col-xs-6">
								<!--<a href="<?php echo base_url() . 'signup'; ?>"><?php echo lang('sing_up') ?>!</a> -->
							</div>
						<?php } ?>
						<div class="col-xs-6 align-right">
							<a href="<?php echo base_url() . 'forgot_password' ?>"><?php echo lang('forgot_password') ?>?</a>
						</div>
					</div>
				</form>
			</div>
		</div>
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
	<script src="<?php echo iaBase(); ?>assets/js/custom.js"></script>
	<script src="<?php echo iaBase() ?>assets/plugins/bootstrap-notify/bootstrap-notify.js"></script>
</body>

<script>
	$(document).ready(function() {
		/**
		 * $type may be success, danger, warning, info
		 */
		<?php
		if (isset($this->session->get_userdata()['alert_msg'])) {
			?>
			$msg = '<?php echo $this->session->get_userdata()['alert_msg']['msg']; ?>';
			$type = '<?php echo $this->session->get_userdata()['alert_msg']['type']; ?>';
			showNotification($msg, $type);
		<?php
			$this->session->unset_userdata('alert_msg');
		}
		?>

		$('#login-form').validate();
	});
</script>

</html>