<?php
$userid = "";
if (isset($user_id)) {
  $userid = $user_id;
}
?>

<?php if ($this->router->class === "dashboard") { ?>
  <div class="app-content-header">
    <!--begin::Container-->
    <div class="container-fluid">
      <!--begin::Row-->
      <div class="row">
        <div class="col-sm-6">
          <h5 class="mb-0"><?php echo lang("module"); ?></h5>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="<?= base_url() ?>"><?php echo lang('home') ?></a></li>
              <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>"><?php echo lang('dashboard') ?></a></li>
              <li class="breadcrumb-item active" aria-current="page"><?= $layout->name ?></li>
            </ol>
          </nav>
        </div>
        <div class="col text-end">
          <?php if (CheckPermission("dashboard", "all_update") || (CheckPermission("dashboard", "own_update") && $created_by == $user_id)): ?>
            <a href="<?= base_url('dashboard/edit_layout/') . $layout->id ?>" class="btn btn-primary">
              <i class="bi bi-plus-circle"></i> <?php echo lang("edit_layout"); ?>
            </a>
          <?php endif; ?>

        </div>
      </div>
      <!--end::Row-->
    </div>
    <!--end::Container-->
  </div>

<?php } else { ?>

  <div id="navbarContent" class="d-none">
    <div class="dropdown" id="dashboard_layout">
      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dashboardDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <?= htmlspecialchars($layout->name) ?>
      </button>
      <ul class="dropdown-menu" aria-labelledby="dashboardDropdown">
        <?php foreach ($all_dashboard_layouts as $layoutAll): ?>
          <li>
            <a class="dropdown-item <?= $layoutAll->id == $layout->id ? 'active' : '' ?>"
              href="<?= base_url('home/index/' . $layoutAll->id) ?>">
              <?= htmlspecialchars($layoutAll->name) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>



<?php }   ?>
<!--end::App Content Header-->
<!--begin::App Content-->
<div class="app-content"<?php echo $this->router->class === 'home' ? ' id="home-dashboard-panel"' : ''; ?>>
  <!--begin::Container-->
  <div class="wrapper container-fluid">
    <!--begin::Row-->


    <!--end::Row-->
    <div class="dashboard-container">
      <div id="map-tooltip" style="position:absolute; display:none; background:#fff; border:1px solid #ccc; padding:6px 8px; font-size:13px; z-index:9999; border-radius:5px; box-shadow:0 0 5px rgba(0,0,0,0.3)"></div>

      <div class="dashboard-layout-scroll" style="text-align: center;" id="layoutScroll">
        <div class="dashboard-layout-canvas grid-stack" id="layoutCanvas" style="display: inline-block;vertical-align: middle;line-height: 100%;  <?= $layout->background_image ? "background-image: url('" . base_url($layout->background_image) . "');" : '' ?>">
          <?php foreach ($layout_items as $item):
            $cfg = json_decode($item->config);
            $type = $item->widget_type;
            $widget = (object)[
              'device_id' => $cfg->device_id ?? '',
              'metric_key' => $cfg->metric_key ?? '',
              'unit' => $cfg->unit ?? '',
              'min' => $cfg->min ?? '',
              'max' => $cfg->max ?? '',
              'label' => $cfg->label ?? '',
              'config' => $cfg
            ];
          ?>
            <div class="grid-stack-item"
              gs-x="<?= $item->x ?>"
              gs-y="<?= $item->y ?>"
              gs-w="<?= $item->w ?>"
              gs-h="<?= $item->h ?>">
              <div class="grid-stack-item-content <?= widget_chrome_class($cfg) ?>">
                <?php $this->load->view("dashboard/widgets/{$type}", ['widget' => $widget, 'widget_id' => $item->id]); ?>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="grid-stack-item" gs-x="0" gs-y="0" gs-w="16" gs-h="6">
            <div class="grid-stack-item-content widget-chrome">
              <div class="card dashboard-alarm-card border-0">
                <audio id="alarmSound" src="/assets/media/system-fault3.mp3" preload="auto"></audio>
                <div class="card-header d-flex align-items-center" style="padding: var(--bs-card-cap-padding-y) var(--bs-card-cap-padding-x);">
                  <h6 class="mb-0"><?php echo lang("active_alarms"); ?></h6>
                  <span id="activeAlarmCount" class="badge rounded-pill bg-secondary ms-auto" title="Active alarm count">0</span>
                </div>
                <div class="card-body" style="padding:0px;">
                  <table id="alarmTable" class="table table-bordered table-hover table-sm table-striped table-responsive dashboard-table" style="width: 100%;">
                    <thead>
                      <tr>
                        <th><?php echo lang("device_name"); ?></th>
                        <th><?php echo lang("metric"); ?></th>
                        <th><?php echo lang("message"); ?></th>
                        <th><?php echo lang("severity"); ?></th>
                        <th><?php echo lang("last_triggered"); ?></th>
                        <th><?php echo lang("acknowledged"); ?></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="dashboard-tools bg-body-secondary">
          <!--
          <button class="btn btn-secondary btn-sm" onclick="setZoom(1)">100%</button>
          <button class="btn btn-secondary btn-sm" onclick="setZoom(1.5)">150%</button>
          <button class="btn btn-secondary btn-sm" onclick="fitToScreen()">Fit</button> -->

        </div>
      </div>

      <div class="container-fluid mt-4">



      </div>
    </div>

    <!--end::Container-->
  </div>
</div>


<script src="<?= base_url('assets/js/client-render2.js') ?>"></script>
<!-- <script src="<?= base_url('assets/js/widgets.js') ?>"></script> -->

<script type="text/javascript">
  let zoomLevel = 1;

  function setZoom(level) {
    zoomLevel = level;
    document.getElementById('layoutCanvas').style.transform = `scale(${zoomLevel})`;
    window.resizeAllGaugeWidgets?.();
  }

  function fitToScreen() {
    const scroll = document.getElementById('layoutScroll');
    const canvas = document.getElementById('layoutCanvas');

    const scaleX = scroll.clientWidth / canvas.offsetWidth;
    const scaleY = scroll.clientHeight / canvas.offsetHeight;
    zoomLevel = Math.min(scaleX, scaleY);
    canvas.style.transform = `scale(${zoomLevel})`;

    centerCanvas();
    window.resizeAllGaugeWidgets?.();
  }

  function centerCanvas() {
    const scroll = document.getElementById('layoutScroll');
    const canvas = document.getElementById('layoutCanvas');

    scroll.scrollLeft = (canvas.offsetWidth * zoomLevel - scroll.clientWidth) / 2;
    scroll.scrollTop = (canvas.offsetHeight * zoomLevel - scroll.clientHeight) / 2;
  }

  function resetView() {
    zoomLevel = 1;
    document.getElementById('layoutCanvas').style.transform = 'scale(1)';
    centerCanvas();
  }

  window.addEventListener('load', () => {
    fitToScreen();
    setTimeout(() => window.resizeAllGaugeWidgets?.(), 200);
  });



  document.addEventListener("DOMContentLoaded", function() {
    const widgets = document.querySelectorAll(".widget-label");

    widgets.forEach(widget => {
      const deviceId = widget.dataset.deviceId;
      const metricKey = widget.dataset.metricKey;

      fetch(`/api/metrics/latest?device_id=${deviceId}&metric_key=${metricKey}`)
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            widget.querySelector('.value').textContent = Number(data.value).toFixed(2) ?? '--';
            if (data.alarm) {
              widget.classList.add('widget-alarm');
            }
          }
        });
    });
  });


  document.addEventListener("DOMContentLoaded", function() {
    GridStack.init({
      cellHeight: 50,
      float: true,
      column: 16,
      disableResize: true,
      disableDrag: true,
      margin: 5
    });
  });


  $(document).ready(function() {

    function updateClock() {

      var clockapi = '/api/timeNow';
      $.ajax({
        url: clockapi,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
          var serverDate = data.date;
          var serverTime = data.time;
          $('.date-time-item').text(serverDate + ' ' + serverTime);
        },
        error: function(error) {
          console.error('Error server time:', error);
        }
      });
    }
    setInterval(updateClock, 60000);
  });
</script>
<script>
  $(document).ready(function() {

    /*สร้าง "<a class="nav-link rounded-2" id="toggleSoundBtn" data-bs-toggle="button" href="#"> <i id="soundIcon" class="bi bi-volume-up"></i> Mute </a>"
ไปที่ elementSoundBtn*/
    const elementSoundBtn = document.getElementById("elementSoundBtn");
    elementSoundBtn.innerHTML = `<a class="nav-link rounded-2" id="toggleSoundBtn" data-bs-toggle="button" href="#"> <i id="soundIcon" class="bi bi-volume-up"></i> Mute </a>`;

    let soundEnabled = true;
    const alarmSound = document.getElementById("alarmSound");
    const toggleBtn = document.getElementById("toggleSoundBtn");
    const icon = document.getElementById("soundIcon");

    toggleBtn.addEventListener("click", () => {
      soundEnabled = !soundEnabled;
      if (soundEnabled) {
        icon.className = "bi bi-volume-up";
        toggleBtn.innerHTML = `<i id="soundIcon" class="bi bi-volume-up"></i> Mute`;
        //toggleBtn.classList.add("btn-outline-secondary");
        toggleBtn.classList.remove("border");
        toggleBtn.classList.remove("border-danger");
        toggleBtn.classList.remove("bg-danger");
      } else {
        icon.className = "bi bi-volume-mute";
        toggleBtn.innerHTML = `<i id="soundIcon" class="bi bi-volume-mute"></i> Unmute`;
        toggleBtn.classList.add("border");
        toggleBtn.classList.add("border-danger");
        toggleBtn.classList.add("bg-danger");
        //toggleBtn.classList.remove("btn-outline-secondary");
      }
    });
    let prevAlarmCount = 0;

    function getSeverityBadgeClass(severity) {
      switch ((severity || '').toLowerCase()) {
        case 'critical':
          return 'bg-danger text-white';
        case 'major':
        case 'high':
          return 'bg-orange text-white';
        case 'warning':
        case 'medium':
          return 'bg-warning text-dark';
        case 'minor':
        case 'low':
          return 'bg-info text-white';
        case 'info':
          return 'bg-secondary text-white';
        default:
          return 'bg-secondary text-white';
      }
    }

    function updateActiveAlarmCount(rows) {
      const list = Array.isArray(rows) ? rows : [];
      const count = list.length;
      const badge = document.getElementById('activeAlarmCount');
      if (!badge) return;

      badge.textContent = String(count);
      badge.classList.remove('bg-secondary', 'bg-danger', 'bg-warning', 'text-dark');
      if (count === 0) {
        badge.classList.add('bg-secondary');
      } else if (list.some(a => String(a.severity || '').toLowerCase() === 'critical')) {
        badge.classList.add('bg-danger');
      } else if (list.some(a => ['warning', 'major', 'medium'].includes(String(a.severity || '').toLowerCase()))) {
        badge.classList.add('bg-warning', 'text-dark');
      } else {
        badge.classList.add('bg-danger');
      }
    }

    // ถ้ามี element ที่มี id="alarmTable" 

    const table = $('#alarmTable').DataTable({
      ajax: {
        url: '/alarmstate/get_json',
        dataSrc: 'data'
      },
      searching: false,
      paging: false,
      responsive: true,
      columnDefs: [{
        className: "text-center",
        "targets": [3, 4]
      }],
      columns: [{
          data: 'device_name'
        }, // ใช้ชื่อแทน ID ถ้า join แล้ว
        {
          data: 'metric_label'
        }, // ใช้ label แทน key ถ้ามี
        {
          data: 'message'
        },
        {
          data: 'severity',
          render: function(data) {
            const label = data || 'Unknown';
            return `<span class="badge ${getSeverityBadgeClass(label)}">${label}</span>`;
          }
        },
        {
          data: 'last_triggered',
          render: function(data, type, row) {
            return dayjs(data).format('YYYY-MM-DD HH:mm:ss');
          }
        },
        {
          data: null,
          render: row => (row.acknowledged === "f") ?
            `<button class="btn btn-sm btn-outline-success" onclick="acknowledge('${row.device_id}', '${row.metric_key}')">Acknowledge</button>` : `Acknowledged by ${row.acknowledged_by} at ${dayjs(row.acknowledged_at).format('YYYY-MM-DD HH:mm:ss')}`
        }
      ],
      initComplete: function(settings, json) {
        if (json && Array.isArray(json.data)) {
          updateActiveAlarmCount(json.data);
        }
      }
    });

    $('#alarmTable').on('xhr.dt', function(e, settings, json) {
      if (json && Array.isArray(json.data)) {
        updateActiveAlarmCount(json.data);
      }
    });

    // ฟังก์ชันเล่นเสียง (ประกาศไว้ด้านบนร่วมกับ audio tag)
    function playAlarmSound() {
      if (soundEnabled) {
        alarmSound.currentTime = 0;
        alarmSound.play().catch(() => {
          console.error("Error playing sound. User interaction required.");
          // แสดงปุ่มให้ผู้ใช้คลิกเพื่อเปิดเสียง
          const soundBtn = document.getElementById("toggleSoundBtn");
          soundBtn.click();
        });
      }
    }

    // รีโหลดข้อมูลทุก 5 วินาที
    setInterval(() => {
      $.ajax({
        url: '/alarmstate/get_json',
        method: 'GET',
        dataType: 'json',
        success: function(res) {
          if (res.status === 'success') {
            if (res.data) {
              // นับจำนวน alarm ที่ยัง active และยังไม่ acknowledged
              const activeAlarms = res.data.filter(a => a.active == 't' && a.acknowledged == 'f');
              const newCount = activeAlarms.length;

              // ถ้ามีเพิ่มขึ้น → เล่นเสียง
              if (newCount > prevAlarmCount) {
                soundEnabled = true;
                if (!soundEnabled) {
                  const soundBtn = document.getElementById("toggleSoundBtn");
                  soundBtn.click();
                }
                playAlarmSound();
              }
              if (newCount != 0) {
                playAlarmSound();
              }

              prevAlarmCount = newCount;

              updateActiveAlarmCount(res.data);

              // อัปเดตตาราง
              table.clear().rows.add(res.data).draw(false);
            }

            updateAlarmHighlights(res.data);
          }
        }
      });
    }, 5000);

  });

  function acknowledge(device_id, metric_key) {
    if (confirm("Acknowledge this alarm?")) {
      $.post(`<?= base_url("alarmstate/acknowledge") ?>/${device_id}/${metric_key}`, function(res) {
        if (res.status === 'success') {
          $('#alarmTable').DataTable().ajax.reload();
        }
      }, 'json');
    }
  }

  function updateAlarmHighlights(alarms) {
    // เคลียร์ class ทั้งหมดก่อน
    document.querySelectorAll('[class*="cardalarm_"]').forEach(el => {
      el.classList.remove('widget-alarm');
    });

    document.querySelectorAll('[class*="boderalarm_"]').forEach(el => {
      el.classList.remove('widget-alarm-border');
    });

    document.querySelectorAll('[class*="svgalarm_"]').forEach(el => {
      el.classList.remove('svg-alarm');
    });


    // ใส่ class สำหรับ alarm ที่ active
    alarms.forEach(a => {
      const clsk = `cardalarm_${a.device_id}_${a.metric_key}`.replace(/\W/g, '_');
      document.querySelectorAll(`.${clsk}`).forEach(el => {
        el.classList.add('widget-alarm');
      });

      const clsd = `cardalarm_${a.device_id}_`.replace(/\W/g, '_');
      document.querySelectorAll(`.${clsd}`).forEach(el => {
        el.classList.add('widget-alarm');
      });

      const clskb = `boderalarm_${a.device_id}_${a.metric_key}`.replace(/\W/g, '_');
      document.querySelectorAll(`.${clskb}`).forEach(el => {
        el.classList.add('widget-alarm-boder');
      });

      const clsdd = `boderalarm_${a.device_id}_`.replace(/\W/g, '_');
      document.querySelectorAll(`.${clsdd}`).forEach(el => {
        el.classList.add('widget-alarm-boder');
      });

      const slsk = `svgalarm_${a.device_id}_${a.metric_key}`.replace(/\W/g, '_');
      document.querySelectorAll(`.${slsk}`).forEach(el => {
        el.classList.add('svg-alarm');
      });

      const slsd = `svgalarm_${a.device_id}_`.replace(/\W/g, '_');
      document.querySelectorAll(`.${slsd}`).forEach(el => {
        el.classList.add('svg-alarm');
      });
    });

    if (typeof updateZoneOverviewCardStatus === 'function') {
      updateZoneOverviewCardStatus(alarms);
    }
  }
</script>

<script>
  $(document).ready(function() {
    // เมื่อโหลดหน้าเว็บ ให้เพิ่ม select ลงใน navbar
    $('#navbarSupportedContent').empty();
    $('#navbarSupportedContent').append($('#dashboard_layout').clone().removeAttr('id'));

  });






  document.addEventListener('DOMContentLoaded', () => {
    if (typeof updateZoneOverviewCardStatus === 'function') {
      updateZoneOverviewCardStatus();
    }
        document.querySelectorAll('g.map-label[data-show="point"]').forEach(el => {
            const container = el.closest('.map-container');
            const tooltip = container.querySelector('.map-tooltip');

            el.addEventListener('mouseenter', async () => {
                const deviceId = el.getAttribute('data-device-id');
                const metricKey = el.getAttribute('data-metric-key');

                tooltip.innerHTML = 'Loading...';
                tooltip.style.display = 'block';

                try {
                  const res = await fetch(`/dashboard/labeltooltip?device_id=${deviceId}&metric_key=${metricKey}`);
                  const json = await res.json();

                  const ts = new Date(json.timestamp);
                  const formatted = ts.getFullYear() + "-" +
                    String(ts.getMonth() + 1).padStart(2, '0') + "-" +
                    String(ts.getDate()).padStart(2, '0') + " " +
                    String(ts.getHours()).padStart(2, '0') + ":" +
                    String(ts.getMinutes()).padStart(2, '0') + ":" +
                    String(ts.getSeconds()).padStart(2, '0');

      
                  if (json.temperature) {
                    tooltip.innerHTML = `
                      <div style="font-size: 13px;">
                        <strong>${json.device_name}</strong><br>
                        ${json.location} / ${json.description}<br>
                        <span>🌡️ Temp: <strong>${json.temperature ?? '-'} ${json.temperature_unit}</strong></span><br>
                        <span>💧 Hum: <strong>${json.humidity ?? '-'} ${json.humidity_unit}</strong></span><br>
                        <small>${formatted}</small>
                      </div>
                    `;
                  } else {
                    tooltip.innerHTML = `
                      <div style="font-size: 13px;">
                        <strong>${json.device_name}</strong><br>
                        <span>${json.label}</span>
                      </div>
                    `;
                  }
                  } catch (err) {
                    tooltip.innerHTML = 'Error loading data';
                  }
                });

              el.addEventListener('mouseleave', () => {
                tooltip.style.display = 'none';
              });
            });
        });
</script>



<style type="text/css">
  .dashboard-container {
    overflow: hidden;
    position: relative;
    border: 0px solid #eee;
  }

  /*
  .dashboard-layout-scroll {
    width: 100%;
    height: 100%;
    overflow: auto;
    position: relative;
  }*/

  .dashboard-layout-canvas {
    width: 100%;
    height: 400px;
    position: relative;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    transform-origin: top left;
    transition: transform 0.2s ease;
  }

  .dashboard-tools {

    position: fixed;
    left: 0;
    bottom: 0;
    width: 100%;
    align-items: flex-end;
    text-align: right;
    z-index: 50;
  }

  .widget-label {
    position: absolute;
    padding: 5px 10px;
    border-radius: 4px;
    background-color: rgba(255, 255, 255, 0.85);
    border: 1px solid #bbb;
    font-weight: bold;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease-in-out;
    cursor: pointer;
  }

  .widget-label:hover {
    transform: scale(1.05);
  }

  .widget-alarm {
    background-color: #dc3545;
    color: white;
    -webkit-animation: blink 1s infinite;
    -moz-animation: blink 1s infinite;
    -o-animation: blink 1s infinite;
    animation: blink 1s infinite;

  }

  .widget-alarm-boder {
    border: 2px solid #dc3545 !important;
    /*color: white;*/
  }

  .grid-stack-item {
    position: absolute;
  }

  @keyframes blink {

    50% {
      background-color: rgb(255, 0, 0, 0);
    }

    100% {
      background-color: #e50000;
    }

  }


  .svg-alarm {
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

  #alarmTable {
    width: 100%;
    margin: 0 auto;
  }

  .dashboard-alarm-card > .card-header {
    width: 100%;
  }

  #activeAlarmCount {
    min-width: 1.75rem;
    font-size: 0.8rem;
    margin-left: auto !important;
  }

  #alarmTable .badge.bg-orange {
    background-color: var(--bs-orange, #fd7e14) !important;
    color: #fff !important;
  }

  #alarmTable th {
    text-align: center;
    vertical-align: middle;
    font-size: 0.9rem;
    height: 30px;
  }

  #alarmTable td {
    text-align: left;
    vertical-align: middle;
    font-size: 0.9rem;
    height: 30px;
  }

  div.dataTables_wrapper,
  div.dataTables_info {
    margin-top: 0px;
    padding-top: 0px !important;
    margin-bottom: 5px;
    text-align: left;
    margin-left: 5px;
  }

  .loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(131, 131, 131, 0.16);
    z-index: 100;
    justify-content: center;
    align-items: center;
    font-weight: 500;
  }

  .map-tooltip {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(0, 0, 0, 0.85);
    color: #fff;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    pointer-events: none;
    z-index: 999;
  }
</style>