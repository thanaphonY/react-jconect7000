<?php
$devices = isset($devices) ? $devices : array();
$channels = isset($channels) ? $channels : array();
$deviceAlarms = isset($device_alarms) ? $device_alarms : array();
$nodeNames = isset($node_names) ? $node_names : array();
$alarmCount = isset($alarm_count) ? (int) $alarm_count : 0;
$alarmSeverity = isset($alarm_severity) ? (string) $alarm_severity : '';
$deviceCount = count($devices);
$channelCount = count($channels);
$hasCoords = $node->node_type === 'Site' && $node->latitude !== null && $node->longitude !== null;
$onlineCount = 0;
foreach ($devices as $device) {
  if (site_device_online($device)) {
    $onlineCount++;
  }
}
$siteHealthy = $alarmCount === 0 && ($deviceCount === 0 || $onlineCount === $deviceCount);
$statusLabel = $siteHealthy ? 'Healthy' : ($alarmCount > 0 ? 'Alarms' : 'Attention');
$statusClass = $siteHealthy ? 'site-view-status--ok' : ($alarmCount > 0 ? 'site-view-status--alarm' : 'site-view-status--warn');
$mapsUrl = $hasCoords
  ? 'https://www.openstreetmap.org/?mlat=' . rawurlencode($node->latitude) . '&mlon=' . rawurlencode($node->longitude) . '#map=15/' . rawurlencode($node->latitude) . '/' . rawurlencode($node->longitude)
  : '';
$updatedAt = !empty($node->updated_at) ? $node->updated_at : null;
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5>
          <i class="bi <?php echo site_node_type_icon($node->node_type); ?>"></i>
          <?php echo html_escape($node->name); ?>
        </h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo base_url(); ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo base_url('site'); ?>">Site Management</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo html_escape($node->name); ?></li>
          </ol>
        </nav>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
          <span class="badge <?php echo site_node_type_badge_class($node->node_type); ?>"><?php echo html_escape($node->node_type); ?></span>
          <?php if ($parent) { ?>
            <span class="text-body-secondary small">under <?php echo html_escape($parent->name); ?></span>
          <?php } ?>
          <span class="site-view-status <?php echo $statusClass; ?>"><?php echo html_escape($statusLabel); ?></span>
        </div>
      </div>
      <div class="col-auto text-end d-flex flex-wrap gap-2 justify-content-end">
        <a class="btn btn-outline-primary" href="<?php echo base_url('site'); ?>">
          <i class="bi bi-arrow-left"></i> Back to tree
        </a>
        <a class="btn btn-primary" href="<?php echo base_url('site/form/' . (int) $node->id); ?>">
          <i class="bi bi-pencil"></i> Edit
        </a>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid" id="site-detail">
    <div class="site-view-meta mb-3">
      <?php if ($node->node_type === 'Site') { ?>
        <span class="site-view-meta__item">
          <i class="bi bi-geo-alt"></i>
          <?php if ($hasCoords) { ?>
            <a href="<?php echo html_escape($mapsUrl); ?>" target="_blank" rel="noopener"><?php echo html_escape($node->latitude . ', ' . $node->longitude); ?></a>
          <?php } else { ?>
            <span class="text-body-secondary">Coordinates not set</span>
          <?php } ?>
        </span>
      <?php } ?>
      <span class="site-view-meta__item">
        <i class="bi bi-hdd-stack"></i>
        <?php echo (int) $deviceCount; ?> device<?php echo $deviceCount === 1 ? '' : 's'; ?>
        <?php if ($deviceCount) { ?>
          <span class="text-body-secondary">(<?php echo (int) $onlineCount; ?> online)</span>
        <?php } ?>
      </span>
      <span class="site-view-meta__item">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo (int) $alarmCount; ?> open alarm<?php echo $alarmCount === 1 ? '' : 's'; ?>
        <?php if ($alarmSeverity !== '') { ?>
          <span class="text-body-secondary">(<?php echo html_escape($alarmSeverity); ?>)</span>
        <?php } ?>
      </span>
      <?php if ($updatedAt) { ?>
        <span class="site-view-meta__item text-body-secondary">
          <i class="bi bi-clock"></i>
          Updated <?php echo html_escape($updatedAt); ?>
        </span>
      <?php } ?>
    </div>

    <div class="row g-3 site-view-stats mb-3">
      <div class="col-md-4">
        <div class="site-view-stat">
          <div class="site-view-stat__label">Devices</div>
          <div class="site-view-stat__value"><?php echo (int) $deviceCount; ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="site-view-stat <?php echo $alarmCount ? 'site-view-stat--alarm' : ''; ?>">
          <div class="site-view-stat__label">Active Alarms</div>
          <div class="site-view-stat__value"><?php echo (int) $alarmCount; ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="site-view-stat">
          <div class="site-view-stat__label">Notification Channels</div>
          <div class="site-view-stat__value"><?php echo (int) $channelCount; ?></div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-7">
        <div class="site-view-panel h-100">
          <div class="site-view-panel__head">
            <h6 class="mb-0">Devices</h6>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo base_url('device'); ?>">All devices</a>
          </div>
          <?php if (!$devices) { ?>
            <p class="text-body-secondary mb-0 px-3 pb-3">No devices under this node.</p>
          <?php } else { ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0 site-view-device-table">
                <thead>
                  <tr>
                    <th class="site-view-device-thumb-col"></th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Protocol</th>
                    <th>Status</th>
                    <th class="text-end"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($devices as $device) {
                    $deviceId = (int) $device->id;
                    $boundNodeId = (int) ($device->site_node_id ?? 0);
                    $boundName = isset($nodeNames[$boundNodeId]) ? $nodeNames[$boundNodeId] : $node->name;
                    $loc = trim((string) ($device->location ?? ''));
                    $locationLabel = $loc !== '' ? site_location_label($boundName, $loc) : $boundName;
                    $protocol = site_device_protocol($device);
                    $online = site_device_online($device);
                    $alarms = isset($deviceAlarms[$deviceId]) ? (int) $deviceAlarms[$deviceId] : 0;
                    $imageSrc = '';
                    if (!empty($device->image)) {
                      $imageSrc = base_url('uploads/devices/' . $device->image);
                    } elseif (!empty($device->template_image)) {
                      $imageSrc = base_url('uploads/device_templates/' . $device->template_image);
                    }
                    ?>
                    <tr>
                      <td class="site-view-device-thumb-col">
                        <a class="site-view-device-thumb" href="<?php echo base_url('device/view/' . $deviceId); ?>">
                          <?php if ($imageSrc !== '') { ?>
                            <img src="<?php echo html_escape($imageSrc); ?>" alt="<?php echo html_escape($device->name); ?>" loading="lazy">
                          <?php } else { ?>
                            <i class="bi bi-hdd-rack"></i>
                          <?php } ?>
                        </a>
                      </td>
                      <td class="fw-semibold">
                        <a href="<?php echo base_url('device/view/' . $deviceId); ?>"><?php echo html_escape($device->name); ?></a>
                        <?php if ($alarms > 0) { ?>
                          <span class="badge text-bg-danger ms-1"><?php echo $alarms; ?></span>
                        <?php } ?>
                      </td>
                      <td class="text-body-secondary small"><?php echo html_escape($locationLabel); ?></td>
                      <td><span class="site-view-protocol"><?php echo html_escape($protocol); ?></span></td>
                      <td>
                        <?php if ($online) { ?>
                          <span class="site-view-pill site-view-pill--online">Online</span>
                        <?php } else { ?>
                          <span class="site-view-pill site-view-pill--offline">Offline</span>
                        <?php } ?>
                      </td>
                      <td class="text-end">
                        <a class="btn btn-outline-info btn-sm" href="<?php echo base_url('device/view/' . $deviceId); ?>" title="Open">
                          <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } ?>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="site-view-panel mb-3">
          <div class="site-view-panel__head">
            <h6 class="mb-0">Notification channels</h6>
          </div>
          <?php if (!$channels) { ?>
            <p class="text-body-secondary mb-0 px-3 pb-3">No channels assigned to this node.</p>
          <?php } else { ?>
            <ul class="site-view-channel-list">
              <?php foreach ($channels as $channel) { ?>
                <li>
                  <i class="bi <?php echo site_channel_type_icon($channel->type ?? ''); ?>"></i>
                  <span class="fw-semibold"><?php echo html_escape($channel->name); ?></span>
                  <span class="site-view-protocol"><?php echo html_escape($channel->type ?? ''); ?></span>
                  <?php if (!site_channel_is_active($channel)) { ?>
                    <span class="badge text-bg-secondary">inactive</span>
                  <?php } ?>
                </li>
              <?php } ?>
            </ul>
          <?php } ?>
        </div>

        <?php if ($node->node_type === 'Site') { ?>
          <div class="site-view-panel site-view-map-panel">
            <div class="site-view-panel__head">
              <h6 class="mb-0">Location</h6>
              <?php if ($hasCoords) { ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo html_escape($mapsUrl); ?>" target="_blank" rel="noopener">Open map</a>
              <?php } ?>
            </div>
            <?php if ($hasCoords) { ?>
              <div class="site-view-map-frame">
                <iframe
                  title="Site map"
                  loading="lazy"
                  referrerpolicy="no-referrer-when-downgrade"
                  src="https://www.openstreetmap.org/export/embed.html?bbox=<?php
                    echo rawurlencode(($node->longitude - 0.04) . ',' . ($node->latitude - 0.03) . ',' . ($node->longitude + 0.04) . ',' . ($node->latitude + 0.03));
                  ?>&amp;layer=mapnik&amp;marker=<?php echo rawurlencode($node->latitude . ',' . $node->longitude); ?>"
                ></iframe>
              </div>
              <p class="site-view-map-caption mb-0">
                <i class="bi bi-geo-alt-fill"></i>
                <?php echo html_escape($node->latitude . ', ' . $node->longitude); ?>
              </p>
            <?php } else { ?>
              <p class="text-body-secondary mb-0 px-3 pb-3">Set coordinates in Edit to show the map.</p>
            <?php } ?>
          </div>
        <?php } ?>
      </div>
    </div>

    <?php if (!empty($layout)) { ?>
      <div class="site-view-panel site-view-dashboard mb-3">
        <div class="site-view-panel__head">
          <h6 class="mb-0">Site dashboard</h6>
          <span class="text-body-secondary small"><?php echo html_escape($layout->name ?? ''); ?></span>
        </div>
        <div class="site-view-dashboard__body">
          <?php
          $this->load->view('dashboard/dashboard/view', array(
            'layout' => $layout,
            'layout_items' => $layout_items,
            'user_id' => $user_id,
            'created_by' => $created_by,
          ));
          ?>
        </div>
      </div>
    <?php } ?>
  </div>
</div>
