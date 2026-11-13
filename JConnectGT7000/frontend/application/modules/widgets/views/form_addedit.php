<?php $isEdit = isset($widget); ?>
<div class="container mt-4">
  <h4><?= $isEdit ? 'Edit' : 'Add' ?> Widget</h4>
  <form method="post">
    <div class="mb-3">
      <label>Name</label>
      <input type="text" name="name" required class="form-control" value="<?= $isEdit ? htmlspecialchars($widget->name) : '' ?>">
    </div>
    <div class="mb-3">
      <label>Widget Type</label>
      <select name="widget_type" class="form-control" required>
        <option value="value_box" <?= $isEdit && $widget->widget_type == 'value_box' ? 'selected' : '' ?>>Value Box</option>
        <option value="gauge" <?= $isEdit && $widget->widget_type == 'gauge' ? 'selected' : '' ?>>Gauge</option>
        <option value="level_bar" <?= $isEdit && $widget->widget_type == 'level_bar' ? 'selected' : '' ?>>Level Bar</option>
        <option value="navigator" <?= $isEdit && $widget->widget_type == 'navigator' ? 'selected' : '' ?>>Navigator</option>
        <option value="line_chart" <?= $isEdit && $widget->widget_type == 'line_chart' ? 'selected' : '' ?>>Line Chart</option>
        <option value="bar_chart" <?= $isEdit && $widget->widget_type == 'bar_chart' ? 'selected' : '' ?>>Bar Chart</option>
        <option value="table" <?= $isEdit && $widget->widget_type == 'table' ? 'selected' : '' ?>>Table</option>
        <option value="status_grid" <?= $isEdit && $widget->widget_type == 'status_grid' ? 'selected' : '' ?>>Status Grid</option>
        <option value="alarm_summary" <?= $isEdit && $widget->widget_type == 'alarm_summary' ? 'selected' : '' ?>>Alarm Summary</option>
        <option value="ups_panel" <?= $isEdit && $widget->widget_type == 'ups_panel' ? 'selected' : '' ?>>UPS Panel</option>
        <option value="power_meter" <?= $isEdit && $widget->widget_type == 'power_meter' ? 'selected' : '' ?>>Power Meter</option>
        <option value="heatmap" <?= $isEdit && $widget->widget_type == 'heatmap' ? 'selected' : '' ?>>Temperature Heatmap</option>
        <option value="cooling_panel" <?= $isEdit && $widget->widget_type == 'cooling_panel' ? 'selected' : '' ?>>Cooling Panel</option>
        <option value="zone_overview" <?= $isEdit && $widget->widget_type == 'zone_overview' ? 'selected' : '' ?>>Zone Overview Card</option>
        <option value="map_view" <?= $isEdit && $widget->widget_type == 'map_view' ? 'selected' : '' ?>>Map view</option>
        <option value="svg_widget" <?= $isEdit && $widget->widget_type == 'svg_widget' ? 'selected' : '' ?>>SVG Widget</option>
        <option value="ratio" <?= $isEdit && $widget->widget_type == 'map_view' ? 'selected' : '' ?>>Ratio</option>
        <option value="temp_hum_sensor" <?= $isEdit && $widget->widget_type == 'temp_hum_sensor' ? 'selected' : '' ?>>Temperature and Humidity</option>
        <option value="value_box2" <?= $isEdit && $widget->widget_type == 'value_box2' ? 'selected' : '' ?>>Value Box 2 Value</option>
        <option value="value_box3" <?= $isEdit && $widget->widget_type == 'value_box3' ? 'selected' : '' ?>>Value Box 3 Value</option>
        <option value="status_box" <?= $isEdit && $widget->widget_type == 'status_box' ? 'selected' : '' ?>>Status Box</option>
        <option value="status_box2" <?= $isEdit && $widget->widget_type == 'status_box2' ? 'selected' : '' ?>>Status Box 2</option>
        <option value="status_box3" <?= $isEdit && $widget->widget_type == 'status_box3' ? 'selected' : '' ?>>Status Box 3</option>
        <option value="point" <?= $isEdit && $widget->widget_type == 'point' ? 'selected' : '' ?>>Point</option>
        <option value="camera_stream" <?= $isEdit && $widget->widget_type == 'camera_stream' ? 'selected' : '' ?>>Camera Stream (RTSP/HLS)</option>
      </select>
    </div>
    <div class="mb-3">
      <label>Widget Config (JSON)</label>
      <textarea name="config" rows="6" class="form-control"><?= $isEdit ? $widget->config : '{}' ?></textarea>
    </div>
    <button type="submit" class="btn btn-success"><?= $isEdit ? 'Update' : 'Create' ?></button>
    <a href="<?= base_url('widgets') ?>" class="btn btn-secondary">Cancel</a>
  </form>
</div>
