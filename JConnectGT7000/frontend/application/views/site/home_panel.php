<?php
$tree = isset($site_tree) ? $site_tree : array();
$pins = isset($site_pins) ? $site_pins : array();
$rows = isset($site_rows) ? $site_rows : array();
$mapSites = isset($map_sites) ? $map_sites : array();
$mapMains = isset($map_mains) ? $map_mains : array();
$mapRegions = isset($map_regions) ? $map_regions : array();
$mapBounds = isset($map_bounds) ? $map_bounds : array(5.55, 20.55, 97.25, 105.75);
$mapBgUrl = isset($map_bg_url) ? $map_bg_url : '';
$mapTilesUrl = isset($map_tiles_url) ? $map_tiles_url : '';
$summary = isset($site_summary) ? $site_summary : array('total' => 0, 'online' => 0, 'offline' => 0, 'alarm' => 0, 'online_minutes' => 15, 'sites' => array());
$siteCount = 0;
foreach ($rows as $row) {
  if (($row['type'] ?? '') === 'Site') {
    $siteCount++;
  }
}
?>
<div class="app-content<?php echo !empty($panel_hidden) ? ' d-none' : ''; ?>" id="home-site-panel" data-site-home="1">
  <div class="container-fluid py-3" id="site-home">
    <div class="row g-3 site-home-layout">
      <div class="col-lg-3 site-home-tree-col">
        <div class="card site-home-tree-card" data-site-home-tree>
          <div class="card-header site-home-tree-header">
            <div class="d-flex align-items-center justify-content-between gap-2">
              <div>
                <h6 class="mb-0">Site tree</h6>
                <div class="site-home-tree-subtitle text-body-secondary">
                  <?php echo (int) $siteCount; ?> site<?php echo $siteCount === 1 ? '' : 's'; ?> in scope
                </div>
              </div>
              <div class="btn-group btn-group-sm" role="group" aria-label="Tree expand controls">
                <button type="button" class="btn btn-outline-secondary" data-site-home-expand title="Expand all">
                  <i class="bi bi-arrows-expand" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-site-home-collapse title="Collapse all">
                  <i class="bi bi-arrows-collapse" aria-hidden="true"></i>
                </button>
              </div>
            </div>
            <div class="mt-2">
              <label class="visually-hidden" for="site-home-tree-search">Search sites</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="search" class="form-control" id="site-home-tree-search" data-site-home-search placeholder="Search name or type…" autocomplete="off">
              </div>
            </div>
          </div>
          <div class="card-body site-home-tree-body p-0">
            <div id="site-tree" class="site-home-tree site-home-tree-scroll">
              <?php if ($tree) { site_render_home_tree($tree); } else { echo '<p class="mb-0 px-3 py-3 text-body-secondary">No sites in your scope.</p>'; } ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col site-home-main-col">
        <div class="card site-map-widget-card">
          <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
            <h6 class="mb-0">Site map</h6>
            <div class="d-flex align-items-center flex-wrap justify-content-end gap-2">
              <label class="small text-body-secondary mb-0" for="site-home-map-main">Main</label>
              <select id="site-home-map-main" class="form-select form-select-sm site-home-map-filter" data-site-home-map-main>
                <option value="">All</option>
                <?php foreach ($mapMains as $main) { ?>
                  <option value="<?php echo (int) $main['id']; ?>"><?php echo html_escape($main['name']); ?></option>
                <?php } ?>
              </select>
              <label class="small text-body-secondary mb-0" for="site-home-map-region">Region</label>
              <select id="site-home-map-region" class="form-select form-select-sm site-home-map-filter" data-site-home-map-region>
                <option value="">All</option>
                <?php foreach ($mapRegions as $region) { ?>
                  <option value="<?php echo (int) $region['id']; ?>" data-main-id="<?php echo (int) $region['main_id']; ?>"><?php echo html_escape($region['name']); ?></option>
                <?php } ?>
              </select>
              <label class="small text-body-secondary mb-0" for="site-home-map-filter">Filter</label>
              <select id="site-home-map-filter" class="form-select form-select-sm site-home-map-filter" data-site-home-map-filter>
                <option value="">All</option>
                <option value="online">Online</option>
                <option value="offline">Offline</option>
                <option value="alarm">Alarm</option>
              </select>
            </div>
          </div>
          <div class="card-body p-2 site-map-widget-body">
            <div id="site-home-map" class="site-home-map border rounded"
              data-sites="<?php echo html_escape(json_encode($mapSites)); ?>"
              data-bounds="<?php echo html_escape(json_encode($mapBounds)); ?>"
              data-bg-url="<?php echo html_escape($mapBgUrl); ?>"
              data-local-tiles="<?php echo html_escape($mapTilesUrl); ?>"
              data-leaflet-icons="<?php echo html_escape(iaBase() . 'assets/plugins/leaflet/images/'); ?>"></div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 site-home-summary-col">
        <div class="card site-home-summary-card">
          <div class="card-header py-2">
            <h6 class="mb-0">Summary</h6>
            <div class="text-body-secondary small">Offline = no device data within <?php echo (int) $summary['online_minutes']; ?> min</div>
          </div>
          <div class="card-body site-home-summary-body">
            <?php
              $offlineSites = array();
              $alarmSites = array();
              foreach ($summary['sites'] as $site) {
                if (empty($site['online'])) {
                  $offlineSites[] = $site;
                }
                if ((int) $site['alarms'] > 0) {
                  $alarmSites[] = $site;
                }
              }
            ?>
            <?php
              $summaryScope = array();
              foreach ($summary['sites'] as $site) {
                $summaryScope[] = array(
                  'online' => !empty($site['online']),
                  'alarms' => (int) $site['alarms'],
                  'main_id' => (int) (isset($site['main_id']) ? $site['main_id'] : 0),
                  'region_id' => (int) (isset($site['region_id']) ? $site['region_id'] : 0),
                );
              }
            ?>
            <div class="site-home-summary-stats" data-site-summary="<?php echo html_escape(json_encode($summaryScope)); ?>">
              <button type="button" class="site-home-summary-stat is-active" data-summary-filter="" aria-pressed="true">
                <div class="site-home-summary-stat-value" data-summary-count="sites"><?php echo (int) $summary['total']; ?></div>
                <div class="site-home-summary-stat-label">Sites</div>
              </button>
              <button type="button" class="site-home-summary-stat is-alarm" data-summary-filter="alarm" aria-pressed="false">
                <div class="site-home-summary-stat-value" data-summary-count="alarm"><?php echo count($alarmSites); ?></div>
                <div class="site-home-summary-stat-label">Alarm</div>
              </button>
              <button type="button" class="site-home-summary-stat is-online" data-summary-filter="online" aria-pressed="false">
                <div class="site-home-summary-stat-value" data-summary-count="online"><?php echo (int) $summary['online']; ?></div>
                <div class="site-home-summary-stat-label">Online</div>
              </button>
              <button type="button" class="site-home-summary-stat is-offline" data-summary-filter="offline" aria-pressed="false">
                <div class="site-home-summary-stat-value" data-summary-count="offline"><?php echo count($offlineSites); ?></div>
                <div class="site-home-summary-stat-label">Offline</div>
              </button>
            </div>
            <ul class="nav nav-tabs nav-fill site-home-summary-tabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="site-summary-alarm-tab" data-bs-toggle="tab" data-bs-target="#site-summary-alarm" type="button" role="tab" aria-controls="site-summary-alarm" aria-selected="true">Alarm</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="site-summary-offline-tab" data-bs-toggle="tab" data-bs-target="#site-summary-offline" type="button" role="tab" aria-controls="site-summary-offline" aria-selected="false">Offline</button>
              </li>
            </ul>
            <div class="tab-content site-home-summary-list">
              <div class="tab-pane fade" id="site-summary-offline" role="tabpanel" aria-labelledby="site-summary-offline-tab">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>Site</th>
                      <th>Path</th>
                      <th class="text-end"><span class="visually-hidden">View</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($offlineSites as $site) { ?>
                      <tr data-summary-list="offline" data-main-id="<?php echo (int) (isset($site['main_id']) ? $site['main_id'] : 0); ?>" data-region-id="<?php echo (int) (isset($site['region_id']) ? $site['region_id'] : 0); ?>">
                        <td><?php echo html_escape($site['name']); ?></td>
                        <td class="text-body-secondary"><?php echo $site['path'] !== '' ? html_escape($site['path']) : '—'; ?></td>
                        <td class="text-end">
                          <a class="btn btn-outline-info btn-sm" href="<?php echo base_url('site/view/' . (int) $site['id']); ?>" title="View" aria-label="View <?php echo html_escape($site['name']); ?>"><i class="bi bi-eye" aria-hidden="true"></i></a>
                        </td>
                      </tr>
                    <?php } ?>
                    <tr data-summary-empty="offline"<?php echo $offlineSites ? ' class="d-none"' : ''; ?>><td colspan="3" class="text-body-secondary">No offline sites.</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="tab-pane fade show active" id="site-summary-alarm" role="tabpanel" aria-labelledby="site-summary-alarm-tab">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>Site</th>
                      <th>Path</th>
                      <th>Alarm</th>
                      <th class="text-end"><span class="visually-hidden">View</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($alarmSites as $site) { ?>
                      <tr data-summary-list="alarm" data-main-id="<?php echo (int) (isset($site['main_id']) ? $site['main_id'] : 0); ?>" data-region-id="<?php echo (int) (isset($site['region_id']) ? $site['region_id'] : 0); ?>">
                        <td><?php echo html_escape($site['name']); ?></td>
                        <td class="text-body-secondary"><?php echo $site['path'] !== '' ? html_escape($site['path']) : '—'; ?></td>
                        <td class="text-nowrap"><span class="badge <?php echo site_alarm_badge_class($site['severity']); ?>"><?php echo (int) $site['alarms']; ?></span></td>
                        <td class="text-end">
                          <a class="btn btn-outline-info btn-sm" href="<?php echo base_url('site/view/' . (int) $site['id']); ?>" title="View" aria-label="View <?php echo html_escape($site['name']); ?>"><i class="bi bi-eye" aria-hidden="true"></i></a>
                        </td>
                      </tr>
                    <?php } ?>
                    <tr data-summary-empty="alarm"<?php echo $alarmSites ? ' class="d-none"' : ''; ?>><td colspan="4" class="text-body-secondary">No sites with alarms.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<link rel="stylesheet" href="<?php echo iaBase(); ?>assets/plugins/leaflet/leaflet.css">
<script src="<?php echo iaBase(); ?>assets/plugins/leaflet/leaflet.js"></script>
<script src="<?php echo iaBase(); ?>assets/js/site-home-map.js?v=<?php echo (int) @filemtime(FCPATH . 'assets/js/site-home-map.js'); ?>"></script>
<script src="<?php echo iaBase(); ?>assets/js/site-home-tree.js"></script>
