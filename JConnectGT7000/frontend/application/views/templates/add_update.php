<form action="<?= base_url("templates/addEdit"); ?>" method="post" enctype="multipart/form-data" id="form" class="p-4">
	<?php if (isset($data->id)) : ?>
		<input type="hidden" name="id" value="<?= htmlspecialchars($data->id); ?>">
	<?php endif; ?>

	<div class="mb-3">
		<label for="template_name" class="form-label"><?= lang('template_name'); ?> *</label>
		<input type="text" class="form-control" id="template_name" name="template_name" required value="<?= isset($data->template_name) ? htmlspecialchars($data->template_name) : ""; ?>">
	</div>

	<div class="mb-4">
		<label class="form-label"><?= lang('template_Variables'); ?></label>
		<div class="row g-3">
			<?php
			$variables = [
				'{var_user_type}' => 'user_type',
				'{var_user_name}' => 'user_name',
				'{var_sender_name}' => 'sender_name',
				'{var_website_name}' => 'website_name',
				'{var_user_email}' => 'user_email',
				'{var_inviation_link}' => 'invitation_link',
				'{var_otp}' => 'otp',
				'{var_otp_expire_minutes}' => 'otp_expire_minutes',
			];
			foreach ($variables as $var => $label) : ?>
				<div class="col-md-6 d-flex align-items-start">
					<code class="me-2"><?= $var; ?></code>
					<strong>: <?= lang($label); ?></strong>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="mb-3">
		<label for="html" class="form-label"><?= lang('html'); ?> *</label>
		<textarea class="form-control ckeditor" id="html" name="html" rows="10" required><?= isset($data->html) ? htmlspecialchars($data->html) : ""; ?></textarea>
	</div>

	<div class="text-end">
		<button type="submit" name="save" class="btn btn-primary"><?= lang('update'); ?>Save</button>
	</div>
</form>


<script>
	$(document).ready(function() {
    CKEDITOR.replace('html', {
    allowedContent: true,
    language: 'en'
  });

		$('#nameModal_Templates')
			.find('div.col-md-offset-4')
			.removeClass('col-md-offset-4')
			.removeClass('col-md-4')
			.addClass('modal-dialog')
			.addClass('modal-lg');
	});
</script>