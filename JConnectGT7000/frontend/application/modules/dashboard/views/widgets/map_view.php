<!-- views/dashboard/widgets/map_view.php -->
<?php
$config = $widget->config ?? (object)[];
$items = $config->items ?? [];
$bgImage = trim($config->background ?? '');
$hasMapImage = $bgImage !== '';
$mapImageSrc = $hasMapImage ? '/uploads/maps/' . rawurlencode($bgImage) : '';
$borderClass = widget_border_class($config, 'border rounded');
$bgClass = widget_bg_class($config, '');
$styleAttr = widget_style_attr($config);
$elementId = "mapview-" . uniqid();
$sitePins = isset($site_pins) && is_array($site_pins) ? $site_pins : array();
$siteBounds = $sitePins ? site_map_resolve_bounds($sitePins, $config) : null;
$displayHeight = isset($config->display_height) ? max(200, (int) $config->display_height) : 0;
?>
<div class="map-container h-100 w-100 position-relative"<?= $displayHeight > 0 ? ' style="height:' . $displayHeight . 'px"' : '' ?>>
<div class="map-tooltip" style="display: none;"></div>
<svg id="<?= $elementId ?>"
  class="map-view-wrapper <?= $borderClass ?> <?= $bgClass ?>"
  xmlns="http://www.w3.org/2000/svg"
  viewBox="0 0 1000 600"
  preserveAspectRatio="xMidYMid meet"
  style="width: 100%; height: 100%;<?= $styleAttr ? ' ' . htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') : '' ?>">
  <?php if ($hasMapImage): ?>
    <image href="<?= htmlspecialchars($mapImageSrc, ENT_QUOTES, 'UTF-8') ?>" height="100%" width="100%" x="0" y="0" preserveAspectRatio="xMidYMid meet"></image>
  <?php elseif (!$sitePins): ?>
    <rect width="100%" height="100%" fill="#2b3035"></rect>
    <text x="500" y="285" text-anchor="middle" fill="#adb5bd" font-size="22">No map image</text>
    <text x="500" y="320" text-anchor="middle" fill="#6c757d" font-size="14">Configure widget and upload a map image</text>
  <?php else: ?>
    <rect width="100%" height="100%" fill="#1f2428"></rect>
  <?php endif; ?>
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
            data-label="<?= $label ?>"
            data-unit="<?= $unit ?>"
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
          } elseif ($show === 'value+unit') {
            $text = '<tspan class="value" alignment-baseline="middle" text-anchor="middle">--</tspan> <tspan class="unit" alignment-baseline="middle" text-anchor="middle">' . $unit . '</tspan>';
          } else {
            // label+value
            $text = $label . ' <tspan class="value" alignment-baseline="middle" text-anchor="middle">--</tspan> <tspan class="unit" alignment-baseline="middle" text-anchor="middle">' . $unit . '</tspan>';
          }
        ?>
          <g class="map-label"
            data-device-id="<?= $deviceId ?>"
            data-metric-key="<?= $metricKey ?>"
            data-show="<?= $show ?>"
            data-label="<?= $label ?>"
            data-unit="<?= $unit ?>"
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
  <?php if ($siteBounds && $sitePins) { ?>
  <g id="<?= $elementId ?>-site-pins">
    <?php foreach ($sitePins as $pin) {
      if (!isset($pin['latitude'], $pin['longitude'])) {
        continue;
      }
      list($cx, $cy) = site_map_project($pin['latitude'], $pin['longitude'], $siteBounds);
      $color = htmlspecialchars($pin['color'] ?? '#198754', ENT_QUOTES, 'UTF-8');
      $name = htmlspecialchars($pin['name'] ?? 'Site', ENT_QUOTES, 'UTF-8');
      $siteId = (int) ($pin['id'] ?? 0);
      $severity = htmlspecialchars($pin['severity'] ?? '', ENT_QUOTES, 'UTF-8');
      ?>
      <a href="<?= base_url('site/view/' . $siteId) ?>" xlink:href="<?= base_url('site/view/' . $siteId) ?>">
        <circle class="site-pin" data-site-id="<?= $siteId ?>" data-severity="<?= $severity ?>" cx="<?= $cx ?>" cy="<?= $cy ?>" r="10" fill="<?= $color ?>" stroke="#fff" stroke-width="2"></circle>
        <text x="<?= $cx ?>" y="<?= $cy + 24 ?>" text-anchor="middle" fill="#dee2e6" font-size="16"><?= $name ?></text>
      </a>
    <?php } ?>
  </g>
  <?php } elseif ($sitePins && !$siteBounds) { ?>
  <text x="500" y="300" text-anchor="middle" fill="#adb5bd" font-size="18">No site coordinates in your scope</text>
  <?php } ?>
</svg>
</div>

<script>
  
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