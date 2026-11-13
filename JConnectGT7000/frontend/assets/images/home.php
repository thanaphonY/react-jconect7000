<!-- 01 -->
<section class="content">
	<div class="container-fluid">

		<div class="row">

			<!-- Mapview -->
			<div class="col-xl-9 col-lg-9 col-md-9 col-sm-12 col-xs-12">
				<div class="card" id="main3d">
					<div class="pull-right" id="tools">
						<i class="material-icons expand pointer" data-src="main3d">fullscreen</i>
					</div>
					<div class="body ">
						<div class="area">
							<center>

								<div style=" position:relative">
									<svg class="mapview" id="svg_ss" baseprofile="tiny" fill="#a3a3a3" width="1280" height="720" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" version="1.2" viewbox="0 0 1280 720" xmlns="http://www.w3.org/2000/svg">
										<image xlink:href="<?php echo iaBase(); ?>assets/images/3disoview_1688993990.png" height="100%" width="100%" x="0" y="0"></image>
										<!-- T&H 1 -->
										<svg class="draggable resizable" x="536.0968627929688" y="218.58901977539062" width="90" height="30" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCTEVN001Temperature"></rect>
											<text x="50%" y="50%" class="ddata data_TCCTEVN001Temperature" id="label_TCCTEVN001_Temperature" data-device="TCCTEVN001" data-key="Temperature"></text>
										</svg>
										<svg class="draggable resizable" x="536.0968627929688" y="246.06695556640625" width="90" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCTEVN001Humidity"></rect>
											<text x="50%" y="50%" class="ddata data_TCCTEVN001Humidity" id="label_TCCTEVN001_Humidity" data-device="TCCTEVN001" data-key="Humidity"></text>
										</svg>
										<!-- T&H 2 -->
										<svg class="draggable resizable" x="627.4090222141065" y="180.02978515625" width="90" height="30" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCTEVN002Temperature"></rect>
											<text x="50%" y="50%" class="ddata data_TCCTEVN002Temperature" id="label_TCCTEVN002_Temperature" data-device="TCCTEVN002" data-key="Temperature"></text>
										</svg>
										<svg class="draggable resizable" x="627.4425659179688" y="207.23626708984375" width="90" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCTEVN002Humidity"></rect>
											<text x="50%" y="50%" class="ddata data_TCCTEVN002Humidity" id="label_TCCTEVN002_Humidity" data-device="TCCTEVN002" data-key="Humidity"></text>
										</svg>
										<!-- Door -->
										<svg class="draggable resizable" x="517.1569709777832" y="474.2322692871094" width="114.76669311523438" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCT004"></rect>
											<text x="50%" y="50%" class="ddata data_TCCT004Door_Acces_Control" id="label_TCCT004_Door_Acces_Control" data-prefix="Door" data-device="TCCT004" data-key="Door_Acces_Control"></text>
										</svg>
										<!-- T&H 3 -->
										<svg class="draggable resizable" x="719.0873059055127" y="142.58441162109375" width="90" height="30" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCTEVN003Temperature"></rect>
											<text x="50%" y="50%" class="ddata data_TCCTEVN003Temperature" id="label_TCCTEVN003_Temperature" data-device="TCCTEVN003" data-key="Temperature"></text>
										</svg>
										<svg class="draggable resizable" x="719.0872802734375" y="169.55941772460938" width="90" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCTEVN003Humidity"></rect>
											<text x="50%" y="50%" class="ddata data_TCCTEVN003Humidity" id="label_TCCTEVN003_Humidity" data-device="TCCTEVN003" data-key="Humidity"></text>
										</svg>
										<!-- FCU1 -->
										<svg class="draggable resizable" x="452.18231201171875" y="320.776123046875" width="130" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="notrun"></rect>
											<text x="50%" y="50%" class="ddata data_TCCT006Status" data-prefix="FCU1" id="label_TCCT006_Status" data-device="TCCT006" data-key="Status">FCU1</text>
										</svg>
										<!-- FCU2 -->
										<svg class="draggable resizable" x="777.7225341796875" y="108.28863525390625" width="130" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="notrun"></rect>
											<text x="50%" y="50%" class="ddata data_TCCT007Status" data-prefix="FCU2" id="label_TCCT007_Status" data-device="TCCT007" data-key="Status">FCU2</text>
										</svg>
										<!-- Gen -->
										<svg class="draggable resizable" x="301.7010383605957" y="127.45916748046875" width="120" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCT002Status"></rect>
											<text x="50%" y="50%" class="ddata data_TCCT002Status" data-prefix="GEN." id="label_TCCT002_Status" data-device="TCCT002" data-key="Status"></text>
										</svg>
										<!-- ASCU -->
										<svg class="draggable resizable" x="397.05560302734375" y="282.71051025390625" width="120" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCT005"></rect>
											<text x="50%" y="50%" class="" id="" data-prefix="ACU" data-device="TCCT005" data-key="Status">Air Control</text>
										</svg>
										<!-- ESDB -->
										<svg class="draggable resizable" x="445.22294998168945" y="176.67063903808594" width="120" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCTDM001"></rect>
											<text x="50%" y="50%" class="" id="" data-prefix="ESDB" data-device="TCCTDM001" data-key="Status">ESDB</text>
										</svg>
										<!-- ATS -->
										<svg class="draggable resizable" x="380.22294998168945" y="211.67063903808594" width="120" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCT001"></rect>
											<text x="50%" y="50%" class="" id="" data-prefix="ATS" data-device="TCCT001" data-key="Status">ATS</text>
										</svg>
										<!-- PDU Group -->
										<svg class="draggable resizable" x="505.8162727355957" y="124.88005065917969" width="120" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCTDM002"></rect>
											<text x="50%" y="50%" class="" id="" data-prefix="PDU GROUP" data-device="TCCTDM002" data-key="Status">PDU Group</text>
										</svg>

										<!-- WLD Group -->
										<svg class="draggable resizable" x="332.52373123168945" y="247.48866271972656" width="120" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCT003"></rect>
											<text x="50%" y="50%" class="" id="" data-prefix="" data-device="TCCT003" data-key="Status">Water Leak</text>
										</svg>

										<!-- Fire -->
										<svg class="draggable resizable" x="461.0025520324707" y="396.0926284790039" width="120" height="30" preserveAspectRatio="xMidYMid meet" style="">
											<rect fill="green" x="0" y="0" width="100%" height="100%" class="alarmlabel_TCCT008"></rect>
											<text x="50%" y="50%" class="" id="" data-prefix="Door" data-device="TCCT008" data-key="">Fire Suppression</text>
										</svg>

										
									</svg>

								</div>
							</center>
						</div>
					</div>
				</div>
			</div>

			<div class="col-xl-3 col-lg-3 col-md-3 col-sm-6 col-xs-12">
				<div class="card">
					<div class="header">
						<h2>
							Power System
						</h2>
					</div>
					<div class="body cardtag">

						<div class="row update-nag-row">
							<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 update-nag-item update-nag-item">
								<!-- ATS -->
								<a href="<?php echo iaBase(); ?>dashboard/view/ats">
									<div class="update-nag nag-herf">
										<div class="update-split" id="alarmcard_TCCT001"><i class="fa fa-bolt fa-2x" aria-hidden="true" style="margin-top: 3px;"></i></div>
										<div class="update-text">ATS</div>
									</div>
								</a>
								<!-- ATS -->
								<a href="<?php echo iaBase(); ?>dashboard/view/ats">
									<div class="update-nag nag-herf">
										<div class="update-split" id="alarmcard_TCCTDMATS"><i class="fa fa-bolt fa-2x" aria-hidden="true" style="margin-top: 3px;"></i></div>
										<div class="update-text">ATS Digital Power Meter</div>
									</div>
								</a>
								<!-- ESDB -->
								<a href="<?php echo iaBase(); ?>dashboard/view/esdbdm">
									<div class="update-nag nag-herf">
										<div class="update-split" id="alarmcard_TCCTDM001"><i class="fa fa-bolt fa-2x" aria-hidden="true" style="margin-top: 3px;"></i></div>
										<div class="update-text">ESDB Digital Power Meter</div>
									</div>
								</a>
								<!-- PDU -->
								<a href="<?php echo iaBase(); ?>dashboard/view/pduindm">
									<div class="update-nag nag-herf">
										<div class="update-split" id="alarmcard_PDUIN01"><i class="fa fa-bolt fa-2x" aria-hidden="true" style="margin-top: 3px;"></i></div>
										<div class="update-text">PDU Incoming Digital Power Meter</div>
									</div>
								</a>
								<a href="<?php echo iaBase(); ?>dashboard/view/pduoutdm">
									<div class="update-nag nag-herf">
										<div class="update-split" id="alarmcard_TCCTDM002"><i class="fa fa-bolt fa-2x" aria-hidden="true" style="margin-top: 3px;"></i></div>
										<div class="update-text">PDU Outgoing Digital Power Meter</div>
									</div>
								</a>


								<!-- Generator -->
								<a href="<?php echo iaBase(); ?>dashboard/view/gen">
									<div class="update-nag nag-herf">
										<div class="update-split" id="alarmcard_TCCT002"><i class="fa fa-bolt fa-2x" aria-hidden="true" style="margin-top: 3px;"></i></div>
										<div class="update-text">Generator</div>
									</div>
								</a>

							</div>
						</div>
					</div>
				</div>
			</div>


			<div class="col-xl-3 col-lg-3 col-md-3 col-sm-6 col-xs-12">
				<div class="card">
					<div class="header">
						<h2>
							Security System
						</h2>
					</div>

					<div class="body cardtag">

						<div class="row update-nag-row">
							<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 update-nag-item update-nag-item">
								<!-- DOOR1 -->
								<a href="<?php echo iaBase(); ?>dashboard/view/evm">
									<div class="update-nag nag-herf">
										<div class="update-split" id="alarmcard_TCCT004"><i class="fa fa-arrow-circle-right fa-2x" aria-hidden="true" style="margin-top: 3px;"></i></div>
										<div class="update-text">Door <label class="ddata data_TCCT004Door_Acces_Control" data-device="TCCT004" data-key="Door_Acces_Control" data-type="wtemp"></label></div>
									</div>
								</a>
							</div>
						</div>

					</div>
				</div>
			</div>
		</div>

		<div class="row">
			<!-- ATS -->
			<div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-xs-12">
				<div class="card">
					<div class="header">
						<h2>
							ATS
						</h2>
					</div>

					<div class="body messagebox_body">
						<div class="row">
							<!-- ATS -->
							<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-xs-12 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCTDMATS" style="padding-left: 0px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/ats">
										<table class="table table-bordered" style="margin-bottom: 0px;text-align: center;">
											<thead>
												<tr>
													<th>Name</th>
													<th>Voltage</th>
													<th>Current</th>
													<th>Power</th>
												</tr>
											</thead>
											<tbody>
												<tr>
													<td>Phase R</td>
													<td><span id="subicon_TCCTDMATS_VoltageL1_N"><label class="ddata data_TCCTDMATSVoltageL1_N" data-device="TCCTDMATS" data-key="VoltageL1_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDMATS_Current_L1"><label class="ddata data_TCCTDMATSCurrent_L1" data-device="TCCTDMATS" data-key="Current_L1" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDMATS_Active_Power_L1"><label class="ddata data_TCCTDMATSActive_Power_L1" data-device="TCCTDMATS" data-key="Active_Power_L1" data-type="wtemp"></label></span></td>
												</tr>
												<tr>
													<td>Phase S</td>
													<td><span id="subicon_TCCTDMATS_VoltageL2_N"><label class="ddata data_TCCTDMATSVoltageL2_N" data-device="TCCTDMATS" data-key="VoltageL2_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDMATS_Current_L2"><label class="ddata data_TCCTDMATSCurrent_L2" data-device="TCCTDMATS" data-key="Current_L2" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDMATS_Active_Power_L2"><label class="ddata data_TCCTDMATSActive_Power_L2" data-device="TCCTDMATS" data-key="Active_Power_L2" data-type="wtemp"></label></span></td>
												</tr>
												<tr>
													<td>Phase T</td>
													<td><span id="subicon_TCCTDMATS_VoltageL3_N"><label class="ddata data_TCCTDMATSVoltageL3_N" data-device="TCCTDMATS" data-key="VoltageL3_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDMATS_Current_L3"><label class="ddata data_TCCTDMATSCurrent_L3" data-device="TCCTDMATS" data-key="Current_L3" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDMATS_Active_Power_L3"><label class="ddata data_TCCTDMATSActive_Power_L3" data-device="TCCTDMATS" data-key="Active_Power_L3" data-type="wtemp"></label></span></td>
												</tr>
											</tbody>
										</table>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- ESDB -->
			<div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-xs-12">
				<div class="card">
					<div class="header">
						<h2>
							ESDB
						</h2>
					</div>

					<div class="body messagebox_body">
						<div class="row">
							<!-- ESDB -->
							<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-xs-12 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCTDM001" style="padding-left: 0px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/esdbdm">
										<table class="table table-bordered" style="margin-bottom: 0px;text-align: center;">
											<thead>
												<tr>
													<th>Name</th>
													<th>Voltage</th>
													<th>Current</th>
													<th>Power</th>
												</tr>
											</thead>
											<tbody>
												<tr>
													<td>Phase R</td>
													<td><span id="subicon_TCCTDM001_VoltageL1_N"><label class="ddata data_TCCTDM001VoltageL1_N" data-device="TCCTDM001" data-key="VoltageL1_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM001_Current_L1"><label class="ddata data_TCCTDM001Current_L1" data-device="TCCTDM001" data-key="Current_L1" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM001_Active_Power_L1"><label class="ddata data_TCCTDM001Active_Power_L1" data-device="TCCTDM001" data-key="Active_Power_L1" data-type="wtemp"></label></span></td>
												</tr>
												<tr>
													<td>Phase S</td>
													<td><span id="subicon_TCCTDM001_VoltageL2_N"><label class="ddata data_TCCTDM001VoltageL2_N" data-device="TCCTDM001" data-key="VoltageL2_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM001_Current_L2"><label class="ddata data_TCCTDM001Current_L2" data-device="TCCTDM001" data-key="Current_L2" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM001_Active_Power_L2"><label class="ddata data_TCCTDM001Active_Power_L2" data-device="TCCTDM001" data-key="Active_Power_L2" data-type="wtemp"></label></span></td>
												</tr>
												<tr>
													<td>Phase T</td>
													<td><span id="subicon_TCCTDM001_VoltageL3_N"><label class="ddata data_TCCTDM001VoltageL3_N" data-device="TCCTDM001" data-key="VoltageL3_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM001_Current_L3"><label class="ddata data_TCCTDM001Current_L3" data-device="TCCTDM001" data-key="Current_L3" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM001_Active_Power_L3"><label class="ddata data_TCCTDM001Active_Power_L3" data-device="TCCTDM001" data-key="Active_Power_L3" data-type="wtemp"></label></span></td>
												</tr>
											</tbody>
										</table>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- PDU Out -->
			<div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-xs-12">
				<div class="card">
					<div class="header">
						<h2>
							PDU Incoming
						</h2>
					</div>

					<div class="body messagebox_body">
						<div class="row">
							<!-- PDU Out -->
							<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-xs-12 nomargin">
								<div class="success messagebox margin5" id="alarmtable_PDUIN01" style="padding-left: 0px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/pduindm">
										<table class="table table-bordered" style="margin-bottom: 0px;text-align: center;">
											<thead>
												<tr>
													<th>Name</th>
													<th>Voltage</th>
													<th>Current</th>
													<th>Power</th>
												</tr>
											</thead>
											<tbody>
												<tr>
													<td>Phase R</td>
													<td><span id="subicon_PDUIN01_VoltageL1_N"><label class="ddata data_PDUIN01VoltageL1_N" data-device="PDUIN01" data-key="VoltageL1_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_PDUIN01_Current_L1"><label class="ddata data_PDUIN01Current_L1" data-device="PDUIN01" data-key="Current_L1" data-type="wtemp"></label></span></td>
													<td><span id="subicon_PDUIN01_Active_Power_L1"><label class="ddata data_PDUIN01Active_Power_L1" data-device="PDUIN01" data-key="Active_Power_L1" data-type="wtemp"></label></span></td>
												</tr>
												<tr>
													<td>Phase S</td>
													<td><span id="subicon_PDUIN01_VoltageL2_N"><label class="ddata data_PDUIN01VoltageL2_N" data-device="PDUIN01" data-key="VoltageL2_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_PDUIN01_Current_L2"><label class="ddata data_PDUIN01Current_L2" data-device="PDUIN01" data-key="Current_L2" data-type="wtemp"></label></span></td>
													<td><span id="subicon_PDUIN01_Active_Power_L2"><label class="ddata data_PDUIN01Active_Power_L2" data-device="PDUIN01" data-key="Active_Power_L2" data-type="wtemp"></label></span></td>
												</tr>
												<tr>
													<td>Phase T</td>
													<td><span id="subicon_PDUIN01_VoltageL3_N"><label class="ddata data_PDUIN01VoltageL3_N" data-device="PDUIN01" data-key="VoltageL3_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_PDUIN01_Current_L3"><label class="ddata data_PDUIN01Current_L3" data-device="PDUIN01" data-key="Current_L3" data-type="wtemp"></label></span></td>
													<td><span id="subicon_PDUIN01_Active_Power_L3"><label class="ddata data_PDUIN01Active_Power_L3" data-device="PDUIN01" data-key="Active_Power_L3" data-type="wtemp"></label></span></td>
												</tr>
											</tbody>
										</table>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- PDU Out -->
			<div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-xs-12">
				<div class="card">
					<div class="header">
						<h2>
							PDU Outgoing
						</h2>
					</div>

					<div class="body messagebox_body">
						<div class="row">
							<!-- PDU Out -->
							<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-xs-12 nomargin">
								<div class="success messagebox margin5" id="alarmtable_TCCTDM002" style="padding-left: 0px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/pduoutdm">
										<table class="table table-bordered" style="margin-bottom: 0px;text-align: center;">
											<thead>
												<tr>
													<th>Name</th>
													<th>Voltage</th>
													<th>Current</th>
													<th>Power</th>
												</tr>
											</thead>
											<tbody>
												<tr>
													<td>Phase R</td>
													<td><span id="subicon_TCCTDM002_VoltageL1_N"><label class="ddata data_TCCTDM002VoltageL1_N" data-device="TCCTDM002" data-key="VoltageL1_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM002_Current_L1"><label class="ddata data_TCCTDM002Current_L1" data-device="TCCTDM002" data-key="Current_L1" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM002_Active_Power_L1"><label class="ddata data_TCCTDM002Active_Power_L1" data-device="TCCTDM002" data-key="Active_Power_L1" data-type="wtemp"></label></span></td>
												</tr>
												<tr>
													<td>Phase S</td>
													<td><span id="subicon_TCCTDM002_VoltageL2_N"><label class="ddata data_TCCTDM002VoltageL2_N" data-device="TCCTDM002" data-key="VoltageL2_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM002_Current_L2"><label class="ddata data_TCCTDM002Current_L2" data-device="TCCTDM002" data-key="Current_L2" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM002_Active_Power_L2"><label class="ddata data_TCCTDM002Active_Power_L2" data-device="TCCTDM002" data-key="Active_Power_L2" data-type="wtemp"></label></span></td>
												</tr>
												<tr>
													<td>Phase T</td>
													<td><span id="subicon_TCCTDM002_VoltageL3_N"><label class="ddata data_TCCTDM002VoltageL3_N" data-device="TCCTDM002" data-key="VoltageL3_N" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM002_Current_L3"><label class="ddata data_TCCTDM002Current_L3" data-device="TCCTDM002" data-key="Current_L3" data-type="wtemp"></label></span></td>
													<td><span id="subicon_TCCTDM002_Active_Power_L3"><label class="ddata data_TCCTDM002Active_Power_L3" data-device="TCCTDM002" data-key="Active_Power_L3" data-type="wtemp"></label></span></td>
												</tr>
											</tbody>
										</table>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="row">
			<!-- T&H -->
			<div class="col-xl-8 col-lg-8 col-md-8 col-sm-12 col-xs-12">
				<div class="card">
					<div class="header d-flex align-items-center">
						<h2>
							Temperature & Humidity
						</h2>
					</div>
					<div class="body messagebox_body">
						<div class="row">
							<!-- Temperature 1 -->
							<div class="col-xl-4 col-lg-4 col-md-4 col-sm-4 col-xs-12 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCTEVN001" style="padding-left: 20px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/evm">
										<div class="row title" id="messagebox-icon">
											<span id="icon_TCCTEVN001" class="fa fa-2x fa-thermometer-empty text-success"></span>
											<div class="h5" id="messagebox-header">Sensor1</div>
										</div>

										<div class="row">
											<div class="col-md-12 nomargin">
												<div class="row marginl5">
													<span id="subicon_TCCTEVN001_Temperature" class="fa fa-check-circle text-success"></span>
													Temperature <label class="ddata data_TCCTEVN001Temperature" data-device="TCCTEVN001" data-key="Temperature" data-type="wtemp"></label>°C
												</div>
												<div class="row marginl5">
													<span id="subicon_TCCTEVN001_Humidity" class="fa fa-check-circle text-success"></span>
													Humidity <label class="ddata data_TCCTEVN001Humidity" data-device="TCCTEVN001" data-key="Humidity" data-type="wtemp"></label>%rH
												</div>
											</div>
										</div>
									</a>
								</div>
							</div>
							<!-- Temperature 2 -->
							<div class="col-xl-4 col-lg-4 col-md-4 col-sm-4 col-xs-12 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCTEVN002" style="padding-left: 20px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/evm">
										<div class="row title" id="messagebox-icon">
											<span id="icon_TCCTEVN002" class="fa fa-2x fa-thermometer-empty text-success"></span>
											<div class="h5" id="messagebox-header">Sensor2</div>
										</div>

										<div class="row">
											<div class="col-md-12 nomargin">
												<div class="row marginl5">
													<span id="subicon_TCCTEVN002_Temperature" class="fa fa-check-circle text-success"></span>
													Temperature <label class="ddata data_TCCTEVN002Temperature" data-device="TCCTEVN002" data-key="Temperature" data-type="wtemp"></label>°C
												</div>
												<div class="row marginl5">
													<span id="subicon_TCCTEVN002_Humidity" class="fa fa-check-circle text-success"></span>
													Humidity <label class="ddata data_TCCTEVN002Humidity" data-device="TCCTEVN002" data-key="Humidity" data-type="wtemp"></label>%rH
												</div>
											</div>
										</div>
									</a>
								</div>
							</div>
							<!-- Temperature 3 -->
							<div class="col-xl-4 col-lg-4 col-md-4 col-sm-4 col-xs-12 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCTEVN003" style="padding-left: 20px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/evm">
										<div class="row title" id="messagebox-icon">
											<span id="icon_TCCTEVN003" class="fa fa-2x fa-thermometer-empty text-success"></span>
											<div class="h5" id="messagebox-header">Sensor3</div>
										</div>

										<div class="row">
											<div class="col-md-12 nomargin">
												<div class="row marginl5">
													<span id="subicon_TCCTEVN003_Temperature" class="fa fa-check-circle text-success"></span>
													Temperature <label class="ddata data_TCCTEVN003Temperature" data-device="TCCTEVN003" data-key="Temperature" data-type="wtemp"></label>°C
												</div>
												<div class="row marginl5">
													<span id="subicon_TCCTEVN003_Humidity" class="fa fa-check-circle text-success"></span>
													Humidity <label class="ddata data_TCCTEVN003Humidity" data-device="TCCTEVN003" data-key="Humidity" data-type="wtemp"></label>%rH
												</div>
											</div>
										</div>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- FWater Leak Detector -->
			<div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-xs-12">
				<div class="card">
					<div class="header">
						<h2>
							Water Leak Detector
						</h2>
					</div>

					<div class="body messagebox_body">
						<div class="row">
							<!-- Water Leak Detector -->
							<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-xs-12 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCT003" style="padding-left: 20px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/evm">
										<div class="row title" id="messagebox-icon">
											<span id="icon_TCCT003" class="fa fa-2x fa-crosshairs text-success"></span>
											<div class="h5" id="messagebox-header">Water Leak Detector</div>
										</div>

										<div class="row">
											<div class="col-md-12 nomargin">
												<div class="row marginl5">
													<span id="subicon_TCCT003_Water_Leak" class="fa  text-success  fa-check-circle"></span>
													Leak Alarm <label class="ddata data_TCCT003Water_Leak" data-device="TCCT003" data-key="Water_Leak" data-type="wfire"></label>
												</div>
												<div class="row marginl5">
													<span id="subicon_TCCT003_Troble_Alarm" class="fa  text-success  fa-check-circle"></span>
													Troble Alarm <label class="ddata data_TCCT003Troble_Alarm" data-device="TCCT003" data-key="Troble_Alarm" data-type="wfire"></label>
												</div>
											</div>
										</div>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-xl-8 col-lg-8 col-md-8 col-sm-12 col-xs-12">
				<div class="card">
					<div class="header d-flex align-items-center">
						<h2>
							Cooling System
						</h2>
					</div>
					<div class="body messagebox_body">
						<div class="row">
							<!-- FCU1 -->
							<div class="col-xl-3 col-lg-4 col-md-4 col-sm-4 col-xs-11 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCT006" style="padding-left: 20px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/evm">
										<div class="row title" id="messagebox-icon">
											<span id="icon_TCCT006" class="fa fa-2x fa-snowflake text-success"></span>
											<div class="h5" id="messagebox-header">FCU 1</div>
										</div>

										<div class="row">
											<div class="col-md-12 nomargin">
												<div class="row marginl5">
													<span id="runicon_TCCT006Status" class="fa fa-check-circle text-success"></span>
													Status <label class="ddata data_TCCT006Status" data-prefix="FCU1" data-device="TCCT006" data-key="Status" data-type="wair"></label>
												</div>
											</div>
										</div>
									</a>
								</div>
							</div>

							<!-- FCU2 -->
							<div class="col-xl-3 col-lg-4 col-md-4 col-sm-4 col-xs-11 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCT007" style="padding-left: 20px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/evm">
										<div class="row title" id="messagebox-icon">
											<span id="icon_TCCT007" class="fa fa-2x fa-snowflake text-success"></span>
											<div class="h5" id="messagebox-header">FCU 2</div>
										</div>

										<div class="row">
											<div class="col-md-12 nomargin">
												<div class="row marginl5">
													<span id="runicon_TCCT007Status" class="fa fa-check-circle text-success"></span>
													Status <label class="ddata data_TCCT007Status" data-prefix="FCU2" data-device="TCCT007" data-key="Status" data-type="wair"></label>
												</div>
											</div>
										</div>
									</a>
								</div>
							</div>
							<!-- Air Sequence -->
							<div class="col-xl-3 col-lg-4 col-md-4 col-sm-4 col-xs-11 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCT005" style="padding-left: 20px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/evm">
										<div class="row title" id="messagebox-icon">
											<span id="icon_TCCT005" class="fa fa-2x fa-retweet text-success"></span>
											<div class="h5" id="messagebox-header">Air Sequence</div>
										</div>

										<div class="row">
											<div class="col-md-12 nomargin">
												<div class="row marginl5">
													<span id="subicon_TCCT005_Status" class="fa fa-check-circle text-success"></span>
													High Temperature <label class="ddata data_TCCT005Status" data-device="TCCT005" data-key="Status" data-type=""></label>
												</div>
											</div>
										</div>
									</a>
								</div>
							</div>

						</div>
					</div>
				</div>
			</div>




			<!-- Fire Alarm -->
			<div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-xs-12">
				<div class="card">
					<div class="header">
						<h2>
							Fire Suppression System
						</h2>
					</div>

					<div class="body messagebox_body">
						<div class="row">
							<!-- Fire1 -->
							<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-xs-12 nomargin">
								<div class="success messagebox margin5" id="alarmcard_TCCT008" style="padding-left: 20px;">
									<a href="<?php echo iaBase(); ?>dashboard/view/evm">
										<div class="row title" id="messagebox-icon">
											<span id="icon_TCCT008" class="fa fa-2x fa-fire-extinguisher text-success"></span>
											<div class="h5" id="messagebox-header">Fire Suppression System</div>
										</div>

										<div class="row">
											<div class="col-md-12 nomargin">
												<div class="row marginl5">
													<span id="subicon_TCCT008_Fire_Alarm_1" class="fa  text-success  fa-check-circle"></span>
													Fire Alarm 1 <label class="ddata data_TCCT008Fire_Alarm_1" data-device="TCCT008" data-key="Fire_Alarm_1" data-type="wfire"></label>
												</div>
												<div class="row marginl5">
													<span id="subicon_TCCT008_Fire_Alarm_2" class="fa  text-success  fa-check-circle"></span>
													Fire Alarm 2 <label class="ddata data_TCCT008Fire_Alarm_2" data-device="TCCT008" data-key="Fire_Alarm_2" data-type="wfire"></label>
												</div>
												<div class="row marginl5">
													<span id="subicon_TCCT008_Fire_Dischard" class="fa  text-success  fa-check-circle"></span>
													Dischard <label class="ddata data_TCCT008Fire_Dischard" data-device="TCCT008" data-key="Fire_Dischard" data-type="wfire"></label>
												</div>
												<div class="row marginl5">
													<span id="subicon_TCCT008_Fire_Troble_Alarm" class="fa fa-check-circle text-success text-success"></span>
													Trouble Alarm <label class="ddata data_TCCT008Fire_Troble_Alarm" data-device="TCCT008" data-key="Fire_Troble_Alarm" data-type="wfire"></label>
												</div>
											</div>
										</div>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>




		<!-- Active ALARM -->
		<div class="row">
			<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<div class="card card_alarm">
					<div class="header">
						<h2 class="box-title">
							Alarm Active
							<div class="pull-right">
								<div class="wrapalarm">
									<input id="soundalarm" type="checkbox">
									<span class="first"></span>
									<span class="second"></span>
								</div>
							</div>
						</h2>
					</div>
					<table class="table table-bordered table-striped " id="card_alarm">
						<colgroup>
							<col class="col-md-3">
							<col class="col-md-4">
							<col class="col-md-5">
						</colgroup>
						<thead>
							<tr>
								<th scope="col" class="text-center"><?php echo lang("log_datetime"); ?></th>
								<th scope="col" class="text-center"><?php echo lang("log_devicename"); ?></th>
								<th scope="col" class="text-center"><?php echo lang("log_msg"); ?></th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div>
		</div>

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
				if (obj.value == "0" || obj.value == "Standby" || obj.value == "Off") {
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
				} else if (obj.value == "1" || obj.value == "Running" || obj.value == "On") {
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

	});
</script>
<style type="text/css">
	.area {
		max-width: 900px;
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
		right: -50px;
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


	.expand {
		color: #cecece38;
	}

	.expand:hover {
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
		0% {}

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
		0% {}

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
		0% {}

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
		max-height: 600px;
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
		0% {}

		100% {
			fill: red;
		}
	}
</style>