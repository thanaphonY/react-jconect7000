<!-- 01 -->
<section class="content">
	<div class="container-fluid">

	



		
	</div>
	<audio id="myAudio">
		<source src="<?php echo base_url(); ?>assets/media/alarm-104598.mp3" type="audio/mpeg">
	</audio>
</section>

<script type="text/javascript">
	function clickSound() {
		var um = document.getElementById("soundalarm").checked;

		if (um === false) {
			var x = document.querySelector("audio").play();
		}

		if (x !== undefined) {
			x.then(function() {
				document.getElementById("soundalarm").checked = false;
			}).catch(function(error) {
				document.getElementById("soundalarm").checked = true;
			});
		}
	}

	$(document).ready(function() {

		function device_status() {
			$('.dstatus').each(function() {
				var $this = $(this);
				var iunit = $(this).attr('data-unit');
				var prefix = $(this).attr('data-prefix');
				var itype = $(this).attr('data-type');
				var ititle = $(this).attr('data-title');
				var dviceId = $(this).attr('data-device');
				var dviceKey = $(this).attr('data-key');

				$.ajax({
					url: $('body').attr('data-base-url') + 'dashboard/getdatawg',
					method: 'post',
					data: {
						device: $(this).attr('data-device'),
						key: $(this).attr('data-key')
					}
				}).done(function(data) {
					//console.debug(data);
					var Value = data[0]['val_data'];
					var iValue = Value;
					var alarm_active = data[0]['alarm_active'];
					var keyid = data[0]['val_key'];
					var device = data[0]['val_token'];
					var eunit = data[0]['unit'];
					if (data[0] !== undefined) {
						if (iunit !== undefined) {
							iValue += ' ';
							iValue += iunit;
						} else {
							iValue += ' ';
							iValue = eunit;
						}

						if (prefix !== undefined) {
							iValue = prefix + ' ' + iValue;
						}

						if (dviceKey.indexOf("DI") !== -1 || dviceKey.indexOf("UI") !== -1 || dviceKey.indexOf("Connection") !== -1) {
							if (Value.indexOf('Running') != -1 || Value.indexOf('On') != -1 || Value.indexOf('1') != -1 || Value == "active" || Value == "Normal" || Value == "Normal Temperature" || Value == "Normal Battery" || Value == "Close" || Value == "Connect") {
								$("svg text").filter(".status_" + dviceId + keyid).html(iValue);
								$("svg text").filter(".status_" + dviceId + keyid).prev().addClass('success');
								$("svg text").filter(".status_" + dviceId + keyid).prev().removeClass('notrun');
							} else if (Value.indexOf('Standby') != -1 || Value.indexOf('Off') != -1 || Value.indexOf('0') != -1 || Value == "inactive" || Value == "Alarm" || Value == "Hight Temperature" || Value == "Low Battery" || Value == "Open" || Value == "Trouble" || Value == "Leak" || Value == "Emergency" || Value == "Disconnect") {
								$("svg text").filter(".status_" + dviceId + keyid).html(iValue);
								$("svg text").filter(".status_" + dviceId + keyid).prev().addClass('notrun');
								$("svg text").filter(".status_" + dviceId + keyid).prev().removeClass('success');

							}
							//$this.html(iValue);
						} else {

							//$this.html(iValue);
						}
					}
				})
			});
		}
		device_status();
		setInterval(device_status, 5000);

		$ddItrem = $('.ddata');
		//console.log($ddItrem.length);

		function updateData2() {
			$('.ddata').each(function() {
				var $this = $(this);
				var iunit = $(this).attr('data-unit');
				var prefix = $(this).attr('data-prefix');
				var itype = $(this).attr('data-type');
				var ititle = $(this).attr('data-title');
				var dviceId = $(this).attr('data-device');
				var dviceKey = $(this).attr('data-key');
				$.ajax({
					url: $('body').attr('data-base-url') + 'dashboard/getdatawg',
					method: 'post',
					data: {
						device: $(this).attr('data-device'),
						key: $(this).attr('data-key')
					}
				}).done(function(data) {

					if (data[0] !== undefined) {
						var iValue = data[0]['val_data'];
						var alarm_active = data[0]['alarm_active'];
						var keyid = data[0]['val_key'];
						var device = data[0]['val_token'];
						var eunit = data[0]['unit'];
						if (iunit !== undefined) {
							iValue += ' ';
							iValue += iunit;
						} else {
							iValue += ' ';
							iValue += eunit;
						}

						if (prefix !== undefined) {
							iValue = prefix + ' ' + iValue;
						}

						if (dviceKey == 'Status') {
							if (iValue.indexOf('Running') != -1 || iValue.indexOf('On') != -1) {
								$('#icon_' + dviceId).addClass('text-success');
								$('#icon_' + dviceId).addClass('rotate');
								$('#icon_' + dviceId).parent().parent().parent().addClass('success');
								$('#icon_' + dviceId).parent().parent().parent().removeClass('notrun');
								$('#subicon_' + dviceId).addClass('text-success');
								$('#subicon_' + dviceId).addClass('fa-check-circle');
								$('#subicon_' + dviceId).removeClass('fa-stop-circle');
								$('#runicon_' + dviceId + keyid).addClass('text-success');
								$('#runicon_' + dviceId + keyid).addClass('fa-check-circle');
								$('#runicon_' + dviceId + keyid).removeClass('fa-stop-circle');

								$("svg text").filter(".data_" + dviceId + keyid).html(iValue);
								$("svg text").filter(".data_" + dviceId + keyid).prev().addClass('success');
								$("svg text").filter(".data_" + dviceId + keyid).prev().removeClass('notrun');

							} else if (iValue.indexOf('Standby') != -1 || iValue.indexOf('Off') != -1) {
								$('#icon_' + dviceId).removeClass('text-success');
								$('#icon_' + dviceId).removeClass('rotate');
								$('#icon_' + dviceId).parent().parent().parent().addClass('notrun');
								$('#icon_' + dviceId).parent().parent().parent().removeClass('success');
								$('#subicon_' + dviceId).removeClass('text-success');
								$('#subicon_' + dviceId).removeClass('fa-check-circle');
								$('#subicon_' + dviceId).addClass('fa-stop-circle');

								$('#runicon_' + dviceId + keyid).removeClass('text-success');
								$('#runicon_' + dviceId + keyid).removeClass('fa-check-circle');
								$('#runicon_' + dviceId + keyid).addClass('fa-stop-circle');

								$("svg text").filter(".data_" + dviceId + keyid).html(iValue);
								$("svg text").filter(".data_" + dviceId + keyid).prev().addClass('notrun');
								$("svg text").filter(".data_" + dviceId + keyid).prev().removeClass('success');
							}
							$this.html(iValue);
						} else if (itype == 'wlable') {
							var resTag = '';
							if (ititle !== undefined) {
								resTag = ititle;
							}
							$this.html(resTag);
							if (iValue == '0') {
								resTag += 'Normal';
								$this.html(resTag);
							} else if (iValue == '1') {
								resTag += 'Normal';
							} else if (iValue == '2') {
								resTag += 'Fault';
								$this.html(resTag);
							} else if (iValue == '3') {
								resTag += 'Disconnect';
								$this.html(resTag);
							} else {

							}

						} else if (itype == 'svg_fill') {

						} else {

							$this.html(iValue);
						}
					}
				})
			});
		}
		updateData2();

		var ws;
		var wsUri = "ws:";
		var loc = window.location;
		console.log(loc);
		if (loc.protocol === "https:") {
			wsUri = "wss:";
		}
		var part = loc.pathname.split("/");
		wsUri += "//" + loc.host + "/node/ws/realtime";

		function wsConnect() {
			//console.log("connect", wsUri);
			ws = new WebSocket(wsUri);
			ws.onmessage = function(msg) {
				msgResponse(msg.data);
			}
			ws.onopen = function() {
				console.log("Connected");
			}
			ws.onclose = function() {
				console.log("Disconnected");
				setTimeout(wsConnect, 3000);
			}
		}
		wsConnect();

		function msgResponse(msg) {
			var obj = JSON.parse(msg)
			if (obj.key == "Status") {
				if (obj.value == "0" || obj.value == "Standby" || obj.value == "Off" || obj.value == "inactive" || obj.value == "Alarm" || obj.value == "Trouble" || obj.value == "Low Battery" || obj.value == "Hight Temperature" || obj.value == "Open" || obj.value == "Fault" || obj.value == "Leak" || obj.value == "Emergency" || obj.value == "Disconnect") {
					$('#icon_' + obj.token + obj.key).removeClass('text-success');
					$('#icon_' + obj.token + obj.key).removeClass('rotate');
					$('#icon_' + obj.token + obj.key).parent().parent().parent().addClass('notrun');
					$('#icon_' + obj.token + obj.key).parent().parent().parent().removeClass('success');

					$('#icon_' + obj.token).removeClass('text-success');
					$('#icon_' + obj.token).removeClass('rotate');
					$('#icon_' + obj.token).parent().parent().parent().addClass('notrun');
					$('#icon_' + obj.token).parent().parent().parent().removeClass('success');

					$('#runicon_' + obj.token + obj.key).removeClass('text-success');
					$('#runicon_' + obj.token + obj.key).removeClass('fa-check-circle');
					$('#runicon_' + obj.token + obj.key).addClass('fa-stop-circle');
					$("label").filter(".data_" + obj.token + obj.key).html(obj.value);
					//svg
					var x, i, iValue;
					var cName = "data_" + obj.token + obj.key;
					x = document.getElementsByClassName(cName);
					for (i = 0; i < x.length; i++) {
						if (x[i].classList.contains(cName)) { // Use classList.contains() instead of className.indexOf()
							var prefix = x[i].getAttribute("data-prefix");
							var iValue = "";
							if (prefix !== null) {
								iValue = prefix + ' ' + obj.value + ' ' + obj.unit;
							} else {
								iValue = obj.value + ' ' + obj.unit;
							}
							x[i].textContent = iValue;
						}
					}
					$("svg text").filter(".data_" + obj.token + obj.key).html(iValue);
					$("svg text").filter(".data_" + obj.token + obj.key).prev().addClass('notrun');
					$("svg text").filter(".data_" + obj.token + obj.key).prev().removeClass('success');
				} else if (obj.value == "1" || obj.value == "Running" || obj.value == "On" || obj.value == "active" || obj.value == "Normal" || obj.value == "Normal Temperature" || obj.value == "Normal Battery" || obj.value == "Close" || obj.value == "Connect") {
					$('#icon_' + obj.token + obj.key).addClass('text-success');
					$('#icon_' + obj.token + obj.key).addClass('rotate');
					$('#icon_' + obj.token + obj.key).parent().parent().parent().addClass('success');
					$('#icon_' + obj.token + obj.key).parent().parent().parent().removeClass('notrun');

					$('#icon_' + obj.token).addClass('text-success');
					$('#icon_' + obj.token).addClass('rotate');
					$('#icon_' + obj.token).parent().parent().parent().addClass('success');
					$('#icon_' + obj.token).parent().parent().parent().removeClass('notrun');

					$('#runicon_' + obj.token + obj.key).addClass('text-success');
					$('#runicon_' + obj.token + obj.key).addClass('fa-check-circle');
					$('#runicon_' + obj.token + obj.key).removeClass('fa-stop-circle');
					$("label").filter(".data_" + obj.token + obj.key).html(obj.value);

					var x, i, iValue;
					var cName = "data_" + obj.token + obj.key;
					x = document.getElementsByClassName(cName);
					for (i = 0; i < x.length; i++) {
						if (x[i].classList.contains(cName)) { // Use classList.contains() instead of className.indexOf()
							var prefix = x[i].getAttribute("data-prefix");
							var iValue = "";
							if (prefix !== null) {
								iValue = prefix + ' ' + obj.value + ' ' + obj.unit;
							} else {
								iValue = obj.value + ' ' + obj.unit;
							}
							x[i].textContent = iValue;
						}
					}
					$("svg text").filter(".data_" + obj.token + obj.key).html(iValue);
					$("svg text").filter(".data_" + obj.token + obj.key).prev().addClass('success');
					$("svg text").filter(".data_" + obj.token + obj.key).prev().removeClass('notrun');
				} else {
					$("label").filter(".data_" + obj.token + obj.key).html(obj.value);
				}
			} else {
				$("label").filter(".data_" + obj.token + obj.key).html(obj.value + ' ' + obj.unit);
				$("label").filter(".time_" + obj.token + obj.key).html(obj.datetime);
				$("div").filter(".data_" + obj.token + obj.key).text(obj.value + ' ' + obj.unit);
				$("div").filter(".time_" + obj.token + obj.key).text(obj.datetime);

				var x, i, iValue;
				var cName = "data_" + obj.token + obj.key;
				x = document.getElementsByClassName(cName);
				for (i = 0; i < x.length; i++) {
					if (x[i].classList.contains(cName)) { // Use classList.contains() instead of className.indexOf()
						var prefix = x[i].getAttribute("data-prefix");
						var iValue = "";
						if (prefix !== null) {
							iValue = prefix + ' ' + obj.value + ' ' + obj.unit;
						} else {
							iValue = obj.value + ' ' + obj.unit;
						}
						x[i].textContent = iValue;
					}
				}
			}

			//up();
		}

		var wsAlarm;
		var wsUriAlarm = "ws:";
		var locAlarm = window.location;
		if (locAlarm.protocol === "https:") {
			wsUriAlarm = "wss:";
		}
		var partAlarm = locAlarm.pathname.split("/");
		wsUriAlarm += "//" + locAlarm.host + "/node/ws/activealarm";

		function wsConnectAlarm() {
			wsAlarm = new WebSocket(wsUriAlarm);
			wsAlarm.onmessage = function(msg) {
				msgResponseAlarm(msg.data);
			}
			wsAlarm.onopen = function() {}
			wsAlarm.onclose = function() {
				setTimeout(wsConnectAlarm, 3000);
			}
		}
		wsConnectAlarm();

		function msgResponseAlarm(msg) {
			const data = (function(raw) {
				try {
					return JSON.parse(raw);
				} catch (err) {
					return false;
				}
			})(msg);

			if (data) {
				$('div').filter('.update-danger').removeClass("update-danger");

				$('span').filter('.text-danger').removeClass("text-danger");
				$('span').filter('.fa-times-circle').removeClass("fa-times-circle");
				$('span').filter('.update-danger').removeClass("update-danger");
				$('svg rect').filter('.update-danger').removeClass("update-danger");

				$('#card_alarm tbody').empty();
				for (let i = 0; i < data.length; i++) {
					var card_alarm = 'alarmcard_' + data[i].device_token;
					if ($('#' + card_alarm).length > 0) {
						$('#' + card_alarm).addClass('update-danger');
					}

					var label_alarm = 'alarmlabel_' + data[i].device_token;
					if ($('#' + label_alarm).length > 0) {
						$('#' + label_alarm).addClass('update-danger');
					}
					var table_alarm = 'alarmtable_' + data[i].device_token;
					if ($('#' + table_alarm).length > 0) {
						$('#' + table_alarm).addClass('update-danger');
					}
					$('svg rect').filter('.' + label_alarm).addClass("update-danger");
					var datat = data[i].datetime;
					var dtarr = datat.split("T");
					var dateComponent = dtarr[0];
					var timearr = dtarr[1];
					var timearrb = timearr.split(":");
					var timesecarr = timearrb[2];
					var seco = timesecarr.split(".");
					var sec = seco[0];
					var timeComponent = timearrb[0] + ":" + timearrb[1] + ":" + sec;
					var datet = dateComponent + ' ' + timeComponent;
					$('#card_alarm tbody').append('<tr><td class="text-center">' + datet + '</td><td>' + data[i].title + '</td><td>' + data[i].msg + '</td></tr>');
					var alarmData = JSON.parse(data[i].alarmData);
					var source = alarmData.source;
					var label_alarm2 = 'alarmlabel_' + source.device + source.key;
					if ($('#' + label_alarm2).length > 0) {
						$('#' + label_alarm2).addClass('update-danger');
					}
					$('svg rect').filter('.' + label_alarm2).addClass("update-danger");

					$('#subicon_' + source.device + '_' + source.key).addClass('text-danger');
					$('#subicon_' + source.device + '_' + source.key).addClass('fa-times-circle');
					$('#subicon_' + source.device + '_' + source.key).addClass('update-danger');

				}
				if (data.length >= 1) {
					clickSound();
				}
			}
		}


		//Full Screen
		$('.content').on("click", ".expand", function() {
			var targetz = $(this).attr('data-src');
			$('#' + targetz).toggleClass('fullscreen');
			//console(targetz);
		});

		//nightMode
		$('.content').on("click", ".night", function() {
			var targetz = $(this).attr('data-src');
			$('#' + targetz).toggleClass('nightMode');
			//console(targetz);
		});




	});
</script>
<style type="text/css">
	.nightMode {
		background-color: #000 !important;
	}

	.area {
		max-width: 1200px;
		display: block;
		position: relative;
		margin: 0 auto;
	}

	.fullscreen .area {
		max-width: unset;

	}

	.wrapalarm {
		width: 100px;
		height: 100px;
		position: absolute;
		right: -10px;
	}

	.wrapalarm input:before,
	.wrapalarm input:after {
		content: "";
		position: absolute;
		cursor: pointer;
	}

	.wrapalarm input:before {
		height: 0;
		width: 0;
		border-right: solid 15px #34495e;
		border-top: solid 15px transparent;
		border-bottom: solid 15px transparent;
		border-left: solid 20px transparent;
		border-radius: 4px;
		left: -20px;
		top: -4px;
	}

	.wrapalarm input[type=checkbox] {
		position: static;
		left: auto;
		opacity: 100;
		margin: auto;
	}

	.wrapalarm input:after {
		width: 15px;
		height: 16px;
		border-radius: 3px;
		background: #34495e;
	}

	.wrapalarm input[type=checkbox]:checked~span {
		transform: rotate(-45deg) translateY(4px);
		left: 23px;
		height: 26px;
		background: #e74c3c;
	}

	.wrapalarm input[type=checkbox]:checked+span {
		transform: rotate(45deg) translateY(-4px);
	}

	.wrapalarm span {
		width: 5px;
		background: #34495e;
		position: absolute;
		transition: all 0.3s ease-in-out;
		border-radius: 5px;
	}

	.wrapalarm span.first {
		height: 20px;
		left: 21px;
	}

	.wrapalarm span.second {
		height: 32px;
		left: 31px;
		top: -6px;
	}


	.pull-right .material-icons {
		color: #cecece60;
	}

	.pull-right .material-icons:hover {
		color: #545454;
	}

	.pointer {
		cursor: pointer;
	}

	.fullscreen {
		z-index: 9999;
		width: 100%;
		height: 100%;
		position: fixed;
		top: 0;
		left: 0;
	}

	.fullscreen .card {
		height: 100% !important;
	}

	.pic_size {
		height: auto;
		width: auto;
	}

	.card_alarm {
		min-height: 300px;
	}


	.bt_s {
		margin-bottom: 4px;
	}


	.container {
		padding-top: 50px;
	}


	.messagebox_body {
		padding: 10px;
	}

	.messagebox {
		border: solid 1px #cecece;
		min-height: 100px;
		color: #4B515D;
		margin-left: 0px;
	}

	.marginl10 {
		margin-left: 10px !important;
		margin-right: 10px !important;
	}

	.marginl5 {
		margin-left: 5px !important;
		margin-right: 5px !important;
	}

	.messagebox .title {
		display: flex;
		padding: 5px;
	}

	.messagebox .title .h5 {
		margin-bottom: 0px;
		margin-left: 15px;
	}

	.messagebox.success {
		border: solid 1px #00C851;
		border-left: solid 4px #00C851;
	}

	.messagebox.update-danger {

		animation: messageboxblinking 1s infinite;
	}


	@keyframes messageboxblinking {
		0% {
			border: solid 1px #ffff00;
			border-left: solid 4px #ffff00;
		}

		100% {
			border: solid 1px #ff4444;
			border-left: solid 4px #ff4444;
			background-color: bisque;
		}
	}


	.messagebox.fail {
		border: solid 1px #ff4444;
		border-left: solid 4px #ff4444;
	}

	.messagebox.warning {
		border: solid 1px #ffbb33;
		border-left: solid 4px #ffbb33;
	}

	.messagebox.notrun {
		border: solid 1px #7d7d7d;
		border-left: solid 4px #7d7d7d;
	}

	.messagebox-icon {
		padding: 1em;
	}

	.messagebox-detail-link {
		margin-left: 1em;
		margin-right: 1em;
		position: relative;
		right: 0;
	}

	.messagebox-header {
		padding-left: 0.6em;
	}

	.expanded-status {
		border: solid 1px black;
	}


	.update-nag-row {
		display: contents;
	}


	.update-nag {
		width: -webkit-fill-available;
		display: inline-block;
		font-size: 13px;
		text-align: left;
		background-color: #fff;
		min-height: 40px;
		-webkit-box-shadow: 2px 2px 2px 2px rgb(0 0 0 / .2);
		box-shadow: 2px 2px 2px 2px rgb(0 0 0 / 10%);
		margin-bottom: 10px;
	}

	.update-nag:hover {
		cursor: pointer;
		-webkit-box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .4);
		box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .3);
	}

	.nag-herf {
		width: 100% !important;
	}

	.update-nag>.update-split {
		background: #5cb85c;
		width: 33px;
		float: left;
		color: #fff !important;
		height: 100%;
		text-align: center;
		margin: 4px;
	}

	.update-nag>.update-split>.glyphicon {
		position: relative;
		top: calc(50% - 9px) !important;
		/* 50% - 3/4 of icon height */
	}

	.update-nag>.update-split.update-success {
		background: #5cb85c !important;
	}

	.update-nag>.update-split.update-danger {
		animation: blinking 1s infinite;
	}

	@keyframes blinking {
		0% {
			background: #ffff00;
		}

		100% {
			background: #d9534f;
		}
	}


	.update-nag>.update-split.update-info {
		background: #5bc0de !important;
	}

	.rotate {
		width: 21px;
		animation: rotation 2s infinite linear;
	}


	@keyframes rotation {
		from {
			transform: rotate(0deg);
		}

		to {
			transform: rotate(359deg);
		}
	}

	.update-nag>.update-text {
		line-height: 19px;
		padding-top: 11px;
		padding-left: 45px;
		padding-right: 20px;
	}

	.card .body {
		font-size: 12px !important;
	}

	.card .body .update-nag-item {
		margin-bottom: 0px;
	}

	.card .body .nomargin {
		margin-bottom: 1px;
	}

	.card .cardtag {
		padding: 0px;
		padding-top: 10px;
	}


	.card .header {
		padding: 10px !important;
	}

	a {
		color: #545454 !important;
	}

	.labelmap>label {
		margin-bottom: unset;
	}

	.labela {
		position: absolute;
	}

	.labelmap {
		background-color: green;
		color: white;
		font-size: 10px;
		border: double;
		padding: 2px;
		text-align: center;
	}

	.labelmap.update-danger {
		animation: labelmapblinking 1s infinite;
	}


	@keyframes labelmapblinking {
		0% {
			background-color: #ffff00;
		}

		100% {
			background-color: #fc0303;
		}
	}

	.labelmap.crac3 {
		right: 25%;
		top: 21%;
	}

	.labelmap.crac2 {
		right: 36%;
		top: 14%;
	}

	.labelmap.crac1 {
		right: 49%;
		top: 7%;
	}

	.labelmap.crac4 {
		left: 74%;
		top: 46%;
	}

	.labelmap.ats {
		right: 5%;
		top: 70%;
	}

	.labelmap.gen {
		right: 5%;
		top: 80%;
	}

	.labelmap.mdb1 {
		right: 46%;
		top: 77%;
	}


	.labelmap.fm2001 {
		left: 29%;
		top: 43%;
	}

	.labelmap.fm2002 {
		left: 5%;
		top: 30%;
	}


	.labelmap.door1 {
		left: 57%;
		top: 49%;
	}


	.labelmap.pmon1 {
		right: 5%;
		top: 21%;
	}

	.labelmap.pmon2 {
		right: 5%;
		top: 32%;
	}

	.labelmap.ups1 {
		right: 5%;
		top: 11%;
	}

	.labelmap.temp1 {
		left: 43%;
		top: 29%;
	}

	.labelmap.hu1 {
		left: 43%;
		top: 39%;
	}

	.labela.temp1 {
		left: 33%;
		top: 40%;
	}

	.labela.temp2 {
		left: 45%;
		top: 24%;
	}

	.labela.temp3 {
		left: 57%;
		top: 8%;
	}

	.labela.esdb {
		left: 0%;
		top: 8%;
	}

	.labelmap.hu1 {
		left: 43%;
		top: 39%;
	}

	.labelmap.temp1 {
		left: 43%;
		top: 29%;
	}

	.labelmap.hu1 {
		left: 43%;
		top: 39%;
	}

	.labelmap.temp4 {
		left: 65%;
		top: 84%;
	}

	.labelmap.hu4 {
		left: 65%;
		top: 92%;
	}

	a:link {
		text-decoration: none;
	}

	a:visited {
		text-decoration: none;
	}

	a:hover {
		text-decoration: none;
	}

	a:active {
		text-decoration: none;
	}

	.messagebox_body thead>tr>th {
		text-align: center;
		font-weight: 600;
	}

	.messagebox_body tbody tr td,
	.table-bordered tbody tr th {
		padding: 3px !important;
		vertical-align: middle !important;
		vertical-align: -webkit-baseline-middle;
	}

	.messagebox_body tbody label {
		margin-bottom: unset !important;
	}

	.messagebox_body tbody tr:hover {
		background-color: rgba(0, 0, 0, .1);
	}


	.stack {
		max-height: 400px;
	}

	svg.mapview {
		width: 100%;
		height: 100%;
		max-height: 800px;
	}

	.fullscreen svg.mapview {
		max-height: 90vh !important;
	}

	svg text {
		font-family: "Verdana, sans-serif";
		text-anchor: middle;
		font-size: 14px;
		stroke-width: 0;
		fill: white;
		alignment-baseline: middle;

	}

	svg .notrun {
		fill: #7d7d7d;
	}

	svg .success {
		fill: green;
	}

	svg rect {
		fill-opacity: 0.8;
	}

	svg rect.update-danger {

		animation: svgmessageboxblinking 1s infinite;
	}

	@keyframes svgmessageboxblinking {
		0% {
			fill: yellow;
		}

		100% {
			fill: red;
		}
	}

	.fullscreen svg.mapview {
		max-height: 90vh !important;
	}

	svg text {
		font-family: "Verdana, sans-serif";
		text-anchor: middle;
		font-size: 14px;
		stroke-width: 0;
		fill: white;
		alignment-baseline: middle;

	}

	svg .notrun {
		fill: #7d7d7d;
	}

	svg .success {
		fill: green;
	}

	svg .notrun.noactiveGreen {
		fill: green;
	}

	svg .success.activeRed {
		fill: red !important;
	}

	svg .success.NormalGreen {
		fill: green;
	}

	svg .notrun.AlarmRed {
		fill: red !important;
	}

	svg rect {
		fill-opacity: 0.8;
	}
</style>