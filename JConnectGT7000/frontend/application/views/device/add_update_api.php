<form action="<?php echo base_url() . 'dashboard/customapi' ?>" method="post" id="customField_form">
	<div class="form-group form-float">
		<div class="form-line">
			<select name="ch" class="form-control" id="" required>
				<option value=""></option>
				<?php if (isset($details) && $details->ch1->type != "0") { ?>
					<option value="1" <?= isset($data->ch) && $data->ch == '1' ? 'selected' : ''; ?>><?= isset($details) ? $details->ch1->name : lang('device_ch1'); ?></option>
				<?php } ?>
				<?php if (isset($details) && $details->ch2->type != "0") { ?>
					<option value="2" <?= isset($data->ch) && $data->ch == '2' ? 'selected' : ''; ?>><?= isset($details) ? $details->ch2->name : lang('device_ch2'); ?></option>
				<?php } ?>
				<?php if (isset($details) && $details->ch3->type != "0") { ?>
					<option value="3" <?= isset($data->ch) && $data->ch == '3' ? 'selected' : ''; ?>><?= isset($details) ? $details->ch3->name : lang('device_ch3'); ?></option>
				<?php } ?>


			</select>
			<label for="" class="form-label"><?php echo lang('timer_ch'); ?></label>
		</div>
	</div>
	<div class="form-group form-float">
		<div class="form-line">
			<select name="action" class="form-control" id="" required>
				<option value=""></option>
				<option value="1" <?= isset($data->activity) && $data->activity == '1' ? 'selected' : ''; ?>><?php echo lang('timer_on'); ?></option>
				<option value="0" <?= isset($data->activity) && $data->activity == '0' ? 'selected' : ''; ?>><?php echo lang('timer_off'); ?></option>
			</select>
			<label for="" class="form-label"><?php echo lang('timer_action'); ?></label>
		</div>
	</div>
	<input type="hidden" name="device_id" value="<?= $device ?>">
	<hr>
	<div class="form-group">
		<?php if (isset($data->id)) { ?>
			<input type="hidden" name="id" value="<?php echo $data->id; ?>">
			<button class="btn btn-primary waves-effect"><?php echo lang('update'); ?></button>
		<?php } else { ?>
			<button class="btn btn-primary waves-effect"><?php echo lang('save'); ?></button>
		<?php } ?>
	</div>
</form>

<script>
	$.AdminBSB.input.activate();
	var set_options = function($obj, $val) {
		if ($('.mka').length > 0) {
			$('.mka').remove();
		}
		if ($obj.val() == 'options' || $obj.val() == 'checkbox' || $obj.val() == 'radio') {
			$html = '';
			$html += '<div class="form-group mka form-float">';
			$html += '<div class="form-line">';
			$html += '<textarea class="form-control" name="options" value="">' + $val + '</textarea>';
			$html += '<label for="" class="form-label"><?php echo lang("options"); ?></label>';
			$html += '</div">';
			$html += '<span class="help-text"><?php echo lang("enter_comma_saperated_options"); ?> </span>';
			$html += '</div>';
			$obj.parents('.form-group').after($html);
			$.AdminBSB.input.activate();
		}
	}
	$(document).ready(function() {
		set_options($('select[name="type"]'), '<?php echo isset($data->options) ? $data->options : ''; ?>');
		$('select[name="type"]').on('change', function() {
			set_options($(this), '');
		});
	});
</script>