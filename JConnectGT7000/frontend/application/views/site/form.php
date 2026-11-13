<?php
$isEdit = !empty($node);
$type = $isEdit ? $node->node_type : 'Room';
$parentValue = $isEdit ? (int) $node->parent_id : (int) $parent_id;
$parentOptions = isset($parent_options) ? $parent_options : array();
$parentIds = array_column($parentOptions, 'id');
if ($parentValue && !in_array($parentValue, $parentIds, true) && !empty($node)) {
    $parentOptions[] = array(
        'id' => $parentValue,
        'type' => '',
        'name' => '',
        'path' => '#' . $parentValue,
    );
}
$ranks = array('Main' => 0, 'Region' => 1, 'Site' => 2, 'Building' => 3, 'Room' => 4);
$mapSites = isset($map_sites) ? $map_sites : array();
$mapBounds = isset($map_bounds) ? $map_bounds : array(13.65, 13.85, 100.42, 100.62);
$mapBgUrl = isset($map_bg_url) ? $map_bg_url : '';
$mapTilesUrl = isset($map_tiles_url) ? $map_tiles_url : '';
$editLat = $isEdit && $node->latitude !== null && $node->latitude !== '' ? (float) $node->latitude : null;
$editLng = $isEdit && $node->longitude !== null && $node->longitude !== '' ? (float) $node->longitude : null;
$channelById = array();
foreach (isset($channels) ? $channels : array() as $channel) {
    $channelById[(int) $channel->id] = $channel;
}
$assignedChannels = array();
foreach (isset($channel_ids) ? $channel_ids : array() as $channelId) {
    $channelId = (int) $channelId;
    if (isset($channelById[$channelId])) {
        $assignedChannels[] = $channelById[$channelId];
    }
}
$channelCatalog = array();
foreach ($channelById as $id => $channel) {
    $channelCatalog[] = array(
        'id' => (int) $id,
        'name' => $channel->name,
        'type' => $channel->type,
    );
}
$deviceById = array();
foreach (isset($devices) ? $devices : array() as $device) {
    $deviceById[(int) $device->id] = $device;
}
$assignedDevices = array();
foreach (isset($device_ids) ? $device_ids : array() as $deviceId) {
    $deviceId = (int) $deviceId;
    if (isset($deviceById[$deviceId])) {
        $assignedDevices[] = $deviceById[$deviceId];
    }
}
$deviceCatalog = array();
foreach ($deviceById as $id => $device) {
    $deviceCatalog[] = array(
        'id' => (int) $id,
        'name' => $device->name,
        'location' => $device->location ?? '',
    );
}
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col">
        <h5><i class="bi bi-<?php echo $isEdit ? 'pencil-square' : 'plus-circle'; ?>"></i> <?php echo $isEdit ? 'Edit node' : 'Add node'; ?></h5>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo base_url(); ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo base_url('site'); ?>">Site Management</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $isEdit ? 'Edit node' : 'Add node'; ?></li>
          </ol>
        </nav>
        <?php if ($isEdit) { ?>
          <p class="text-body-secondary small mb-0 mt-1">
            <span class="badge <?php echo site_node_type_badge_class($node->node_type); ?>"><?php echo html_escape($node->node_type); ?></span>
            <?php echo html_escape($node->name); ?>
          </p>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid" id="site-node-form-page">
    <div class="card">
      <div class="card-body">
        <form method="post" action="<?php echo base_url('site/save'); ?>" id="site-node-form"
          data-type-ranks="<?php echo html_escape(json_encode($ranks)); ?>">
          <input type="hidden" name="id" value="<?php echo $isEdit ? (int) $node->id : 0; ?>">
          <div class="row g-4">
            <div class="col-lg-6">
              <h6 class="text-body-secondary text-uppercase small mb-3">Location</h6>
              <div class="mb-3">
                <label class="form-label" for="site-node-name">Name</label>
                <input class="form-control" id="site-node-name" name="name" required autofocus
                  value="<?php echo $isEdit ? html_escape($node->name) : ''; ?>">
              </div>
              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label" for="node_type">Type</label>
                  <select class="form-select" name="node_type" id="node_type">
                    <?php foreach (array('Main', 'Region', 'Site', 'Building', 'Room') as $option) {
                      if (!empty($regional) && $option === 'Main') {
                        continue;
                      } ?>
                      <option value="<?php echo $option; ?>" <?php echo $type === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                    <?php } ?>
                  </select>
                </div>
                <div class="col-sm-6">
                  <label class="form-label" for="sort_order">Sort order</label>
                  <input class="form-control" type="number" name="sort_order" id="sort_order"
                    value="<?php echo $isEdit ? (int) $node->sort_order : 0; ?>">
                </div>
              </div>
              <div class="mb-3" id="site-parent-field">
                <label class="form-label" for="parent_id">Parent</label>
                <select class="form-select site-parent-select2" name="parent_id" id="parent_id"
                  data-placeholder="Search path or name…">
                  <option value="">None (Main only)</option>
                  <?php foreach ($parentOptions as $parent) { ?>
                    <option value="<?php echo (int) $parent['id']; ?>"
                      data-node-type="<?php echo html_escape($parent['type']); ?>"
                      <?php echo $parentValue === (int) $parent['id'] ? 'selected' : ''; ?>>
                      <?php echo html_escape($parent['path']); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>
              <div class="mb-3 d-none" id="site-coordinates">
                <label class="form-label">Map position</label>
                <p class="form-text mt-0 mb-2">Click or drag the pin on the map. Uses local map tiles (offline) or your uploaded map image when configured.</p>
                <div id="site-form-map" class="site-form-map border rounded"
                  data-sites="<?php echo html_escape(json_encode($mapSites)); ?>"
                  data-bounds="<?php echo html_escape(json_encode($mapBounds)); ?>"
                  data-bg-url="<?php echo html_escape($mapBgUrl); ?>"
                  data-local-tiles="<?php echo html_escape($mapTilesUrl); ?>"
                  data-leaflet-icons="<?php echo html_escape(iaBase() . 'assets/plugins/leaflet/images/'); ?>"
                  data-marker-lat="<?php echo $editLat !== null ? html_escape($editLat) : ''; ?>"
                  data-marker-lng="<?php echo $editLng !== null ? html_escape($editLng) : ''; ?>"></div>
                <div class="row g-2 mt-2">
                  <div class="col-md-6">
                    <label class="form-label" for="latitude">Latitude</label>
                    <input class="form-control" id="latitude" name="latitude" inputmode="decimal"
                      value="<?php echo $isEdit && $node->latitude !== null ? html_escape($node->latitude) : ''; ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="longitude">Longitude</label>
                    <input class="form-control" id="longitude" name="longitude" inputmode="decimal"
                      value="<?php echo $isEdit && $node->longitude !== null ? html_escape($node->longitude) : ''; ?>">
                  </div>
                </div>
              </div>
              <div class="mb-0">
                <label class="form-label" for="dashboard_layout_id">Dashboard</label>
                <select class="form-select" name="dashboard_layout_id" id="dashboard_layout_id">
                  <option value="">None</option>
                  <?php foreach ($layouts as $layout) { ?>
                    <option value="<?php echo (int) $layout->id; ?>" <?php echo $isEdit && (int) $node->dashboard_layout_id === (int) $layout->id ? 'selected' : ''; ?>>
                      <?php echo html_escape($layout->name); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>
            </div>
            <div class="col-lg-6">
              <h6 class="text-body-secondary text-uppercase small mb-3">Assignments</h6>
              <?php if (empty($regional)) { ?>
                <div class="mb-3" id="site-channel-panel" data-channels="<?php echo html_escape(json_encode($channelCatalog)); ?>">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <label class="form-label mb-0">Notification channels</label>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#siteChannelModal">
                      <i class="bi bi-plus-lg"></i> Add
                    </button>
                  </div>
                  <ul class="list-group list-group-flush border rounded" id="site-channel-assigned">
                    <?php foreach ($assignedChannels as $channel) { ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center gap-2 site-channel-row" data-channel-id="<?php echo (int) $channel->id; ?>">
                        <span>
                          <?php echo html_escape($channel->name); ?>
                          <span class="badge text-bg-secondary ms-1"><?php echo html_escape($channel->type); ?></span>
                        </span>
                        <button type="button" class="btn btn-outline-danger btn-sm site-channel-remove" title="Remove" aria-label="Remove">
                          <i class="bi bi-x-lg"></i>
                        </button>
                      </li>
                    <?php } ?>
                    <?php if (!$assignedChannels) { ?>
                      <li class="list-group-item text-body-secondary small site-channel-empty">No channels linked. Click Add.</li>
                    <?php } ?>
                  </ul>
                  <div id="site-channel-inputs">
                    <?php foreach ($assignedChannels as $channel) { ?>
                      <input type="hidden" name="channel_ids[]" value="<?php echo (int) $channel->id; ?>" data-channel-id="<?php echo (int) $channel->id; ?>">
                    <?php } ?>
                  </div>
                </div>
              <?php } ?>
              <div class="mb-0" id="site-device-panel" data-devices="<?php echo html_escape(json_encode($deviceCatalog)); ?>">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                  <div>
                    <label class="form-label mb-0">Devices on this node</label>
                    <p class="form-text mt-0 mb-0">Original location text on each device is kept.</p>
                  </div>
                  <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#siteDeviceModal">
                    <i class="bi bi-plus-lg"></i> Add
                  </button>
                </div>
                <ul class="list-group list-group-flush border rounded site-form-scroll" id="site-device-assigned">
                  <?php foreach ($assignedDevices as $device) { ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2 site-device-row" data-device-id="<?php echo (int) $device->id; ?>">
                      <span>
                        <?php echo html_escape($device->name); ?>
                        <?php if ($device->location) { ?>
                          <span class="text-body-secondary small"> — <?php echo html_escape($device->location); ?></span>
                        <?php } ?>
                      </span>
                      <button type="button" class="btn btn-outline-danger btn-sm site-device-remove" title="Remove" aria-label="Remove">
                        <i class="bi bi-x-lg"></i>
                      </button>
                    </li>
                  <?php } ?>
                  <?php if (!$assignedDevices) { ?>
                    <li class="list-group-item text-body-secondary small site-device-empty">No devices linked. Click Add.</li>
                  <?php } ?>
                </ul>
                <div id="site-device-inputs">
                  <?php foreach ($assignedDevices as $device) { ?>
                    <input type="hidden" name="device_ids[]" value="<?php echo (int) $device->id; ?>" data-device-id="<?php echo (int) $device->id; ?>">
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2 pt-3 mt-3 border-top">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save</button>
            <a class="btn btn-outline-secondary" href="<?php echo base_url('site'); ?>">Cancel</a>
            <?php if ($isEdit && $node->node_type === 'Site') { ?>
              <a class="btn btn-outline-info ms-md-auto" href="<?php echo base_url('site/view/' . (int) $node->id); ?>"><i class="bi bi-box-arrow-up-right"></i> Open site</a>
            <?php } ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (empty($regional)) { ?>
<div class="modal fade" id="siteChannelModal" tabindex="-1" aria-labelledby="siteChannelModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable site-picker-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="siteChannelModalLabel">Add notification channel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary small mb-3">Only active channels are listed.</p>
        <div id="site-channel-modal-list" class="site-picker-modal-list"></div>
        <p class="text-body-secondary small mb-0 d-none" id="site-channel-modal-empty">No active channels available to add.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="site-channel-modal-add">Add selected</button>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<div class="modal fade" id="siteDeviceModal" tabindex="-1" aria-labelledby="siteDeviceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable site-picker-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="siteDeviceModalLabel">Add device</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary small mb-2">Only active devices you can assign are listed.</p>
        <input type="search" class="form-control form-control-sm mb-3" id="site-device-modal-search" placeholder="Search devices…" autocomplete="off">
        <div id="site-device-modal-list" class="site-picker-modal-list"></div>
        <p class="text-body-secondary small mb-0 d-none" id="site-device-modal-empty">No devices available to add.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="site-device-modal-add">Add selected</button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/leaflet/leaflet.css">
<link rel="stylesheet" href="<?php echo iaBase(); ?>assets/css/site-form.css">
<script src="<?php echo iaBase(); ?>assets/plugins/leaflet/leaflet.js"></script>
<script src="<?php echo iaBase(); ?>assets/js/site-form.js"></script>
