<?php
$cfg = $widget->config ?? (object)[];
$deviceId = $cfg->device_id ?? '';
$borderClass = widget_border_class($cfg);
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'width: 100%; height: 100%;');
?>

<div class="widget-temp-humidity <?= $borderClass ?> <?= $bgClass ?> sboderalarm_<?= $widget->device_id ?>_"
  data-device-id="<?= $deviceId ?>" style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>">
  <div class="d-flex flex-column align-items-center justify-content-center h-100" id="tempHumidityLayout">

    <div class="small text-center mb-2"><?= htmlspecialchars($cfg->label ?? '') ?></div>

    <div class="d-flex justify-content-center gap-2 flex-wrap w-100 px-1">

      <!-- Temperature -->
      <div class="metric-pill temp d-flex flex-column align-items-center cardalarm_<?= $widget->device_id ?>_<?= $cfg->temp_key ?? '' ?>">
        <div class="icon mb-1 fs-5"><i class="bi bi-thermometer-half"></i></div>
        <div class="item-temp valuebox-<?= $deviceId ?>-<?= $cfg->temp_key ?? '' ?> text-center" data-device-id="<?= $deviceId ?>" data-metric-key="<?= $cfg->temp_key ?? '' ?>">
          <div class="value">-</div>
          <div class="unit">°C</div>
        </div>
      </div>

      <!-- Humidity -->
      <div class="metric-pill humidity d-flex flex-column align-items-center cardalarm_<?= $widget->device_id ?>_<?= $cfg->hum_key ?? '' ?>">
        <div class="icon mb-1 fs-5"><i class="bi bi-moisture"></i></div>
        <div class="item-humidity valuebox-<?= $deviceId ?>-<?= $cfg->hum_key ?? '' ?> text-center" data-device-id="<?= $deviceId ?>" data-metric-key="<?= $cfg->hum_key ?? '' ?>">
          <div class="value">-</div>
          <div class="unit">%rH</div>
        </div>
      </div>

    </div>
  </div>
</div>


<style>
  .widget-temp-humidity .value {
    font-size: 0.9rem;
  }

  .widget-temp-humidity .unit {
    font-size: 0.9rem;
  }

  .widget-temp-humidity .item-temp,
  .widget-temp-humidity .item-humidity {
    display: flex;
    text-align: center;
    align-items: center;
    justify-content: center;
    margin: 3px !important;
  }

 

</style>