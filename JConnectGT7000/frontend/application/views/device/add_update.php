<form action="<?php echo base_url() . 'dashboard/customtimer' ?>" method="post" id="customField_form">
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
	<div class="form-group form-float">
		<div class="form-line">
			<select name="type" class="form-control" id="action" required>
				<option value=""></option>
				<option value="0" <?= isset($data->type) && $data->type == '0' ? 'selected' : ''; ?>><?php echo lang('timer_onetime'); ?></option>
				<option value="1" <?= isset($data->type) && $data->type == '1' ? 'selected' : ''; ?>><?php echo lang('timer_everday'); ?></option>
				<option value="2" <?= isset($data->type) && $data->type == '2' ? 'selected' : ''; ?>><?php echo lang('timer_daily'); ?></option>
			</select>
			<label for="" class="form-label"><?php echo lang('timer_type'); ?></label>
		</div>
	</div>
	<div id="sDate" class="form-group form-float"  <?= isset($data->type) && ($data->type == '1' || $data->type == '2') ? 'style="display: none"' : ''; ?>>
		<div class="form-line">
			<input type="text" id="date" name="date" class="form-control" value="<?= isset($data->date) ? $data->date : date('Y-m-d'); ?>" required>
			<label for="" class="form-label"><?php echo lang('timer_date'); ?></label>
		</div>
	</div>
	<div id="sDay" class="form-group form-float" <?= isset($data->type) && ($data->type == '1' || $data->type == '0') ? 'style="display: none"' : ''; ?>>
		<div class="form-line">
			<br><br>
			<div class="form-control">
				<span class="button-checkbox">
					<button type="button" class="btn" data-color="primary"><?php echo lang('day_Sun'); ?></button>
					<input type="checkbox" name="day[]" value="0" class="hidden" <?= isset($day) && !in_array("0", $day) ? '' : 'checked'; ?>/>
				</span>
				<span class="button-checkbox">
					<button type="button" class="btn" data-color="primary"><?php echo lang('day_Mon'); ?></button>
					<input type="checkbox" name="day[]" value="1" class="hidden" <?= isset($day) && !in_array("1", $day) ? '' : 'checked'; ?>/>
				</span>
				<span class="button-checkbox">
					<button type="button" class="btn" data-color="primary"><?php echo lang('day_Tue'); ?></button>
					<input type="checkbox" name="day[]" value="2" class="hidden" <?= isset($day) && !in_array("2", $day) ? '' : 'checked'; ?>/>
				</span>
				<span class="button-checkbox">
					<button type="button" class="btn" data-color="primary"><?php echo lang('day_Wed'); ?></button>
					<input type="checkbox" name="day[]" value="3" class="hidden" <?= isset($day) && !in_array("3", $day) ? '' : 'checked'; ?>/>
				</span>
				<span class="button-checkbox">
					<button type="button" class="btn" data-color="primary"><?php echo lang('day_Thu'); ?></button>
					<input type="checkbox" name="day[]" value="4" class="hidden" <?= isset($day) && !in_array("4", $day) ? '' : 'checked'; ?>/>
				</span>
				<span class="button-checkbox">
					<button type="button" class="btn" data-color="primary"><?php echo lang('day_Fri'); ?></button>
					<input type="checkbox" name="day[]" value="5" class="hidden" <?= isset($day) && !in_array("5", $day) ? '' : 'checked'; ?>/>
				</span>
				<span class="button-checkbox">
					<button type="button" class="btn" data-color="primary"><?php echo lang('day_Sat'); ?></button>
					<input type="checkbox" name="day[]" value="6" class="hidden" <?= isset($day) && !in_array("6", $day) ? '' : 'checked'; ?>/>
				</span>
			</div>
			<label for="" class="form-label"><?php echo lang('timer_day'); ?></label>
		</div>
	</div>
	<div class="form-group form-float">
		<div class="form-line">
			<input type="text" id="time" name="time" class="form-control" value="<?= isset($data->time) ? $data->time : date('H:i'); ?>" required>
			<label for="" class="form-label"><?php echo lang('timer_time'); ?></label>
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
	$('#action').on('change', function() {
		if(this.value == 0){
			$('#sDate').show();
			$('#sDay').hide();
		}else if(this.value == 1){
			$('#sDate').hide();
			$('#sDay').hide();
		}else if(this.value == 2){
			$('#sDate').hide();
			$('#sDay').show();
		}else{
			$('#sDate').show();
			$('#sDay').show();
		}
	});
	$(function() {
		$("#timerModal").find(".modal-body").removeAttr("style");
		$('#date').bootstrapMaterialDatePicker({
			time: false,
			minDate: moment(),
		});
		$('#time').bootstrapMaterialDatePicker({
			date: false,
			format: 'HH:mm'
		});
	});
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
	$(function() {
		$('.button-checkbox').each(function() {

			// Settings
			var $widget = $(this),
				$button = $widget.find('button'),
				$checkbox = $widget.find('input:checkbox'),
				color = $button.data('color'),
				settings = {
					on: {
						icon: 'glyphicon glyphicon-check'
					},
					off: {
						icon: 'glyphicon glyphicon-unchecked'
					}
				};

			// Event Handlers
			$button.on('click', function() {
				$checkbox.prop('checked', !$checkbox.is(':checked'));
				$checkbox.triggerHandler('change');
				updateDisplay();
			});
			$checkbox.on('change', function() {
				updateDisplay();
			});

			// Actions
			function updateDisplay() {
				var isChecked = $checkbox.is(':checked');

				// Set the button's state
				$button.data('state', (isChecked) ? "on" : "off");

				// Set the button's icon
				$button.find('.state-icon')
					.removeClass()
					.addClass('state-icon ' + settings[$button.data('state')].icon);

				// Update the button's color
				if (isChecked) {
					$button
						.removeClass('btn-default')
						.addClass('btn-' + color + ' active');
				} else {
					$button
						.removeClass('btn-' + color + ' active')
						.addClass('btn-default');
				}
			}

			// Initialization
			function init() {

				updateDisplay();

				// Inject the icon if applicable
				if ($button.find('.state-icon').length == 0) {
					$button.prepend('<i class="state-icon ' + settings[$button.data('state')].icon + '"></i> ');
				}
			}
			init();
		});
	});
</script>