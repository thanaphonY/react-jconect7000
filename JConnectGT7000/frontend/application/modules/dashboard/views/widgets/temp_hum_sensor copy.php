<?php
$cfg = $widget->config ?? (object)[];
$deviceId1 = $cfg->device_id1 ?? '';
$deviceId2 = $cfg->device_id2 ?? '';
?>

<div class="widget-temp-humidity border rounded bg-body text-body shadow-sm boderalarm_<?= $widget->device_id ?>_"
  data-device-id="<?= $deviceId ?>" style="width: 100%; height: 100%;">
  <div class="d-flex flex-column align-items-center justify-content-center h-100" id="tempHumidityLayout">

    <div class="small text-center mb-2"><?= htmlspecialchars($cfg->label ?? '') ?></div>

    <div class="d-flex justify-content-center gap-3 flex-wrap">

      <!-- Temperature -->
      <div class="d-flex col rounded flex-column align-items-center cardalarm_<?= $widget->device_id ?>_Temperature">
        <div class="icon mb-1 text-success fs-5"><i class="bi bi-thermometer-half"></i></div>
        <div class="item-temp  valuebox-<?= $deviceId ?>-Temperature text-center" data-device-id="<?= $deviceId ?>" data-metric-key="Temperature">
          <div class="value fw-bold">--</div>
          <div class="unit small text-muted">°C</div>
        </div>
      </div>

      <!-- Humidity -->
      <div class="d-flex col rounded flex-column align-items-center cardalarm_<?= $widget->device_id ?>_Humidity">
        <div class="icon mb-1 text-primary fs-5"><i class="bi bi-moisture"></i></div>
        <div class=" item-humidity valuebox-<?= $deviceId ?>-Humidity text-center" data-device-id="<?= $deviceId ?>" data-metric-key="Humidity">
          <div class="value fw-bold">--</div> 
          <div class="unit small text-muted">%rH</div>
        </div>
      </div>

    </div>
  </div>
</div>


</script>
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