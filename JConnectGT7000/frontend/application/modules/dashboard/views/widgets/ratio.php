<?php
$cfg = $widget->config ?? (object)[];

$elementId = "ratio-{$cfg->device_id1}-{$cfg->metric_key1}-{$cfg->device_id2}-{$cfg->metric_key2}";
$borderClass = widget_border_class($cfg);
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%;');

?>

<div id="<?= $elementId ?>" class="widget-ratio <?= $borderClass ?> <?= $bgClass ?>" style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>">
  <!-- Header Section -->
  <div class="text-center">
    <div class="small p-1 mb-0 "><?= htmlspecialchars($cfg->label) ?></div>
    <div class="ratio-value">
      <div class="spinner-border spinner-border-sm text-primary " role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>
    <div class="small "><?= htmlspecialchars($cfg->unit1) ?>/<?= htmlspecialchars($cfg->unit2) ?></div>
  </div>

  <hr class="my-1">

  <!-- Breakdown Section -->
  <div class="row p-1 text-center">

    <div class="col-sm-6 p-0 valuebox-<?= $cfg->device_id1 ?>-<?= $cfg->metric_key1 ?>">
      <div class="small  "><?= htmlspecialchars($cfg->label1) ?></div>
      <div class="value value1" data-device-id="<?= $cfg->device_id1 ?>" data-metric-key="<?= $cfg->metric_key1 ?>">--</div>
      <div class="small"><?= htmlspecialchars($cfg->unit1) ?></div>
    </div>
    <div class="col-sm-6 p-0 valuebox-<?= $cfg->device_id2 ?>-<?= $cfg->metric_key2 ?>">
      <div class="small  "><?= htmlspecialchars($cfg->label2) ?></div>
      <div class="value value2" data-device-id="<?= $cfg->device_id2 ?>" data-metric-key="<?= $cfg->metric_key2 ?>">--</div>
      <div class="small"><?= htmlspecialchars($cfg->unit2) ?></div>
    </div>

  </div>
</div>

<script>
  function updateAllRatioWidgets() {
    const widgets = document.querySelectorAll('.widget-ratio');

    widgets.forEach(widget => {
      const v1El = widget.querySelector('.value1');
      const v2El = widget.querySelector('.value2');
      const outEl = widget.querySelector('.ratio-value');

      if (!v1El || !v2El || !outEl) return;

      const val1 = parseFloat(v1El.textContent);
      const val2 = parseFloat(v2El.textContent);

      if (!isNaN(val1) && !isNaN(val2) && val2 !== 0) {
        const ratio = val1 / val2;
        outEl.textContent = ratio.toFixed(2);
      } else {
        /*
                outEl.textContent = `<div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>`;*/
      }
    });
  }

  // เรียกฟังก์ชันทุก ๆ 5 วินาที
  setInterval(updateAllRatioWidgets, 5000);

  // เรียกทันทีเมื่อโหลด
  document.addEventListener('DOMContentLoaded', updateAllRatioWidgets);
</script>


<style type="text/css">
  .widget-ratio {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: stretch;
    text-align: center;
    height: 100%;
  }



  .widget-ratio .value1,
  .widget-ratio .value2 {
    font-size: 0.9rem;
  }

  .widget-ratio .ratio-value {
    font-size: 1rem;
    font-weight: bold;
    line-height: 1.2;
    margin-bottom: 4px;
    /* เพิ่มระยะห่างจาก unit */
  }

  .widget-ratio .unit-combo {
    font-size: 0.9rem;
    color: #ccc;
    margin-bottom: 8px;
  }
</style>