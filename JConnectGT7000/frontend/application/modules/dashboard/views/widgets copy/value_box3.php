<?php
$v = $widget->config ?? (object)[];
$label = $widget->label ?? '';
?>
<div class="widget-value-box3 border rounded bg-body text-body shadow-sm" style="width: 100%; height: 100%;">
  <div class="row gx-3 gy-1 align-items-center h-50" id="tempHumidityLayout">

    <div class="lable text-center  mb-1"><?= htmlspecialchars($v->label ?? '') ?></div>
    <!-- Value -->
    <div class="col item-value  ">
      <div class="fs-3 text-center rounded cardalarm_<?= $v->device_id1 ?>_<?= $v->metric_key1 ?> valuebox-<?= htmlspecialchars($v->device_id1 ?? '') ?>-<?= htmlspecialchars($v->metric_key1 ?? '') ?>" data-device-id="<?= $v->device_id1 ?? '' ?>" data-metric-key="<?= $v->metric_key1 ?? '' ?>">
        <div class="value">--</div>
        <div class="unit"> <?= $v->unit1 ?? '' ?> </div>
      </div>
      <div><?= htmlspecialchars($v->spector ?? '') ?></div>
      <div class="fs-3 text-center rounded cardalarm_<?= $v->device_id2 ?>_<?= $v->metric_key2 ?> valuebox-<?= htmlspecialchars($v->device_id2 ?? '') ?>-<?= htmlspecialchars($v->metric_key2 ?? '') ?>" data-device-id="<?= $v->device_id2 ?? '' ?>" data-metric-key="<?= $v->metric_key2 ?? '' ?>">
        <div class="value">--</div>
        <div class="unit"> <?= $v->unit2 ?? '' ?> </div>
      </div>
      <div> <?= htmlspecialchars($v->spector ?? '') ?> </div>
      <div class="fs-3 text-center rounded cardalarm_<?= $v->device_id3 ?>_<?= $v->metric_key3 ?> valuebox-<?= htmlspecialchars($v->device_id3 ?? '') ?>-<?= htmlspecialchars($v->metric_key3 ?? '') ?>" data-device-id="<?= $v->device_id3 ?? '' ?>" data-metric-key="<?= $v->metric_key3 ?? '' ?>">
        <div class="value">--</div>
        <div class="unit"><?= $v->unit3 ?? '' ?></div>
      </div>
    </div>
  </div>
</div>

<script>



</script>

<style type="text/css">
  .widget-value-box3 {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    text-align: center;
  }

  .widget-value-box3 .item-value {
    display: flex;
    flex-direction: row;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    text-align: center;
  }

  .widget-value-box3 .fs-3 {
    display: flex;

  }

  .widget-value-box3 .value {
    font-size: 0.9rem;
    font-weight: bold;
    white-space: nowrap;
    text-overflow: ellipsis;
    position: relative;
    margin-left: 2px; /* เพิ่ม spacing ถ้าติดกับตัวเลขเกินไป */
  }
  .widget-value-box3 .lable {
  font-size: 0.9rem;
}

 .widget-value-box3 .unit {
  font-size: 0.9rem;
  margin-left: 1px; /* เพิ่ม spacing ถ้าติดกับตัวเลขเกินไป */
  margin-right: 2px; /* เพิ่ม spacing ถ้าติดกับตัวเลขเกินไป */
}
</style>