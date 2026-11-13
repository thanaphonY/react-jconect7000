<!-- views/dashboard/widgets/map_view.php -->
<?php
$config = $widget->config ?? (object)[];
$items = $config->items ?? [];
$bgImage = $config->background ?? 'default.png';
$elementId = "mapview-" . uniqid();
?>
<div class="map-tooltip" style="display: none;"></div>
<svg id="<?= $elementId ?>"
  class="map-view-wrapper border rounded bg-body text-body"
  xmlns="http://www.w3.org/2000/svg"
  viewBox="0 0 1000 600"
  preserveAspectRatio="xMidYMid meet"
  style="width: 100%; height: 100%;">
  <image xlink:href="<?php echo iaBase(); ?>uploads/maps/<?= htmlspecialchars($bgImage) ?>" height="100%" width="100%" x="0" y="0"></image>
  <g id="<?= $elementId ?>-labels">
    <?php
    if (!empty($items)) {
      foreach ($items as $i):
        $x = isset($i->x) ? floatval($i->x) : 0;
        $y = isset($i->y) ? floatval($i->y) : 0;
        $url = isset($i->url) ? $i->url : "";
        $deviceId = $i->device_id ?? '';
        $metricKey = $i->metric_key ?? '';
        $unit = htmlspecialchars($i->unit ?? '');
        $label = htmlspecialchars($i->label ?? '--');
        $show = $i->show ?? 'label+value';

        $uid = "label-" . md5($label . $metricKey . $deviceId);
        if ($show == "point") { ?>

          <g class="map-label"
            data-device-id="<?= $deviceId ?>"
            data-metric-key="<?= $metricKey ?>"
            data-show="<?= $show ?>"
            transform="translate(<?= $x ?>, <?= $y ?>)"
            style="cursor: pointer;"
            <?php if (empty($url)) { ?>
            onclick="window.open('/device/view/<?= $deviceId ?>', '_blank')"
            <?php } else { ?>
            onclick="window.open('<?= $url ?>', '_blank')"
            <?php
            }
            ?>>
            <circle r="5" cx="50" cy="50" fill="green" opacity="1.0" class="svgalarm_<?= $deviceId ?>_<?= $metricKey ?>" />
          </g>
        <?php
        } else {
          $text = ''; // content text
          if ($show === 'label') {
            $text = $label;
          } elseif ($show === 'value') {
            $text = '<tspan class="value" alignment-baseline="middle" text-anchor="middle">--</tspan>';
          } else {
            // label+value
            $text = $label . ': <tspan class="value" alignment-baseline="middle" text-anchor="middle">--</tspan>:<tspan class="unit" alignment-baseline="middle" text-anchor="middle">' . $unit . '</tspan>';
          }
        ?>
          <g class="map-label"
            data-device-id="<?= $deviceId ?>"
            data-metric-key="<?= $metricKey ?>"
            data-show="<?= $show ?>"
            transform="translate(<?= $x ?>, <?= $y ?>)"
            style="cursor: pointer;"
            <?php if (empty($url)) { ?>
            onclick="window.open('/device/view/<?= $deviceId ?>', '_blank')"
            <?php } else { ?>
            onclick="window.open('<?= $url ?>', '_blank')"
            <?php
            }
            ?>>
            <rect x="0" y="0" width="100" height="28" rx="6" ry="6" fill="#222" opacity="0.85" class="svgalarm_<?= $deviceId ?>_<?= $metricKey ?>"></rect>
            <svg width="100px" height="28px">
              <text x="50%" y="50%" alignment-baseline="middle" text-anchor="middle" fill="#fff" font-size="14"><?= $text ?></text>
            </svg>
          </g>

    <?php }
      endforeach;
    } ?>
  </g>
</svg>

<script>
  document.addEventListener("DOMContentLoaded", function() {
    const labels = document.querySelectorAll("#<?= $elementId ?> .map-label");

    labels.forEach(el => {
      const deviceId = el.dataset.deviceId;
      const metricKey = el.dataset.metricKey;

      if (deviceId && metricKey) {
        fetch(`/device/metrics_latest?device_id=${deviceId}&metric_key=${metricKey}`)
          .then(res => res.json())
          .then(data => {
            if (data.status === "success") {
              const val = data.value ?? "--";

              const textEl = el.querySelector("text");

              if (textEl) {
                const show = el.dataset.show || 'label+value';
                const label = textEl.textContent.split(':')[0]; // crude
                const unit = textEl.textContent.split(':')[2] ?? "33";
                let display = "";

                if (show === "value") {
                  display = `${val} ${unit}`;
                } else if (show === "label") {
                  display = label;
                } else if (show === "label+value") {
                  display = `${label}: ${val}`;
                } else if (show === "value+unit") {
                  display = `${val} ${unit}`;
                } else {
                  display = `${label}: ${val} ${unit}`;
                }

                textEl.textContent = display;
                textEl.setAttribute('alignment-baseline', 'middle');
                textEl.setAttribute('text-anchor', 'middle');
              }
            }
          });
      }
    });
  });
</script>

<style>
  .alarm-blink {
    fill: #dc3545 !important;
    animation: blinkAlarm 1s infinite;
  }

  @keyframes blinkAlarm {
    0% {
      opacity: 0.9;
    }

    50% {
      opacity: 0.3;
    }

    100% {
      opacity: 0.9;
    }
  }

  #tooltip {
    position: absolute;
    background: rgba(0, 0, 0, 0.75);
    color: #fff;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 12px;
    display: none;
    pointer-events: none;
    z-index: 100;
  }
</style>


<script>
  /*
  const mapElement = document.getElementById('<?= $elementId ?>');
  const deviceId = '<?= $widget->device_id ?>';
  const metricKey = '<?= $widget->metric_key ?>';
  const unit = '<?= $widget->unit ?>';
  const min = <?= $widget->min ?>;
  const max = <?= $widget->max ?>;
  const label = '<?= $widget->label ?>';

  // ฟังก์ชันสำหรับสร้าง marker
  function createMarker(x, y, value) {
    const marker = document.createElement('div');
    marker.className = 'marker';
    marker.style.position = 'absolute';
    marker.style.left = `${x}px`;
    marker.style.top = `${y}px`;
    marker.innerHTML = `<span>${value} ${unit}</span>`;
    return marker;
  }

  // ฟังก์ชันสำหรับอัปเดต markers
  function updateMarkers(data) {
    mapElement.innerHTML = ''; // ลบ markers เก่า
    data.forEach(item => {
      const x = item.x; // ค่าพิกัด X ของ marker
      const y = item.y; // ค่าพิกัด Y ของ marker
      const value = item.value; // ค่าที่จะแสดงใน marker
      const marker = createMarker(x, y, value);
      mapElement.appendChild(marker);
    });
  }

  // ฟังก์ชันสำหรับดึงข้อมูลจาก API
  async function fetchData() {
    try {
      const response = await fetch(`<?= base_url("api/metrics/device/$deviceId") ?>`);
      if (!response.ok) throw new Error('Network response was not ok');
      const data = await response.json();
      updateMarkers(data);
    } catch (error) {
      console.error('Error fetching data:', error);
    }
  }

  // เรียกใช้ฟังก์ชัน fetchData ทุก ๆ 5 วินาที
  setInterval(fetchData, 5000);
  // เรียกใช้ฟังก์ชัน fetchData ทันทีเมื่อโหลดหน้า
  fetchData();
  */
</script>

<style>
  .marker {
    background-color: rgba(255, 255, 255, 0.8);
    border-radius: 5px;
    padding: 5px;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
    font-size: 12px;
    color: #333;
    text-align: center;
    transition: transform 0.2s;
  }

  .marker:hover {
    transform: scale(1.1);
    z-index: 10;
  }

  .marker span {
    font-weight: bold;
  }

  .marker span::after {
    content: '<?= $widget->unit ?>';
    font-size: 10px;
    color: #666;
  }

  .marker span::before {
    content: '<?= $widget->label ?>: ';
    font-size: 10px;
    color: #666;
  }

  .marker span::before,
  .marker span::after {
    display: block;
  }

  .marker span::after {
    margin-top: 5px;
  }

  .marker span::before {
    margin-bottom: 5px;
  }

  /**.aSVG
   */
  .map-label rect {
    fill: green;
    opacity: 0.7;

  }
</style>