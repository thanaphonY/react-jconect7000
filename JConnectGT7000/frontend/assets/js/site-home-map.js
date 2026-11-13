(function () {
  var mapEl = document.getElementById('site-home-map');
  var panel = document.getElementById('home-site-panel');
  if (!mapEl || typeof L === 'undefined') return;

  var leafletMap = null;
  var pinBounds = [];
  var markers = [];
  var latLngBounds = null;
  var filterEl = document.querySelector('[data-site-home-map-filter]');
  var mainEl = document.querySelector('[data-site-home-map-main]');
  var regionEl = document.querySelector('[data-site-home-map-region]');

  function parseBounds(raw) {
    if (!Array.isArray(raw) || raw.length < 4) {
      return [13.65, 13.85, 100.42, 100.62];
    }
    return raw.map(parseFloat);
  }

  function configureIcons() {
    var base = mapEl.getAttribute('data-leaflet-icons') || '';
    if (!base) return;
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
      iconRetinaUrl: base + 'marker-icon-2x.png',
      iconUrl: base + 'marker-icon.png',
      shadowUrl: base + 'marker-shadow.png'
    });
  }

  function isPanelVisible() {
    return panel && !panel.classList.contains('d-none') && mapEl.clientWidth > 80 && mapEl.clientHeight > 80;
  }

  function initMap() {
    if (leafletMap) return leafletMap;
    if (!isPanelVisible()) return null;

    configureIcons();

    var mapBounds = parseBounds([]);
    try {
      mapBounds = parseBounds(JSON.parse(mapEl.getAttribute('data-bounds') || '[]'));
    } catch (err) {
      mapBounds = [13.65, 13.85, 100.42, 100.62];
    }

    var sites = [];
    try {
      sites = JSON.parse(mapEl.getAttribute('data-sites') || '[]');
    } catch (err2) {
      sites = [];
    }

    var bgUrl = mapEl.getAttribute('data-bg-url') || '';
    var localTiles = mapEl.getAttribute('data-local-tiles') || '';
    var south = mapBounds[0];
    var north = mapBounds[1];
    var west = mapBounds[2];
    var east = mapBounds[3];
    latLngBounds = L.latLngBounds([south, west], [north, east]);

    leafletMap = L.map(mapEl, {
      scrollWheelZoom: true
    });

    if (bgUrl) {
      L.imageOverlay(bgUrl, latLngBounds).addTo(leafletMap);
    } else if (localTiles) {
      L.tileLayer(localTiles + '{z}/{x}/{y}.png?v=osmde1', {
        minZoom: 5,
        maxZoom: 13,
        attribution: '&copy; OpenStreetMap (offline cache)'
      }).addTo(leafletMap);
    } else {
      L.tileLayer('https://tile.openstreetmap.de/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(leafletMap);
    }

    markers = [];
    sites.forEach(function (site) {
      if (!Number.isFinite(site.lat) || !Number.isFinite(site.lng)) return;
      var color = site.color || '#198754';
      var marker = L.circleMarker([site.lat, site.lng], {
        radius: 9,
        color: '#fff',
        weight: 2,
        fillColor: color,
        fillOpacity: 0.95
      }).bindTooltip(site.name || 'Site', { direction: 'top' });

      if (site.url) {
        marker.on('click', function () {
          window.location.href = site.url;
        });
      }
      markers.push({
        marker: marker,
        site: site,
        latlng: [site.lat, site.lng]
      });
    });
    applyFilter();

    mapEl.dataset.mapReady = '1';
    return leafletMap;
  }

  function siteMatches(site, value) {
    var mainId = mainEl && mainEl.value ? parseInt(mainEl.value, 10) : 0;
    var regionId = regionEl && regionEl.value ? parseInt(regionEl.value, 10) : 0;
    if (mainId && parseInt(site.main_id, 10) !== mainId) return false;
    if (regionId && parseInt(site.region_id, 10) !== regionId) return false;
    if (value === 'online') return !!site.online;
    if (value === 'offline') return !site.online;
    if (value === 'alarm') return (parseInt(site.alarms, 10) || 0) > 0;
    return true;
  }

  function syncRegions() {
    if (!regionEl) return;
    var mainId = mainEl && mainEl.value ? mainEl.value : '';
    var current = regionEl.value;
    var keep = current === '';
    Array.prototype.forEach.call(regionEl.options, function (opt) {
      if (!opt.value) return;
      var show = !mainId || opt.getAttribute('data-main-id') === mainId;
      opt.hidden = !show;
      opt.disabled = !show;
      if (show && opt.value === current) keep = true;
    });
    if (!keep) regionEl.value = '';
  }

  function selectedScope() {
    return {
      mainId: mainEl && mainEl.value ? parseInt(mainEl.value, 10) : 0,
      regionId: regionEl && regionEl.value ? parseInt(regionEl.value, 10) : 0
    };
  }

  function inScope(mainId, regionId, scope) {
    if (scope.mainId && parseInt(mainId, 10) !== scope.mainId) return false;
    if (scope.regionId && parseInt(regionId, 10) !== scope.regionId) return false;
    return true;
  }

  function updateSummary() {
    var stats = document.querySelector('.site-home-summary-stats');
    if (!stats) return;
    var sites = [];
    try {
      sites = JSON.parse(stats.getAttribute('data-site-summary') || '[]');
    } catch (err) {
      sites = [];
    }
    var scope = selectedScope();
    var counts = { sites: 0, alarm: 0, online: 0, offline: 0 };
    sites.forEach(function (site) {
      if (!inScope(site.main_id, site.region_id, scope)) return;
      counts.sites += 1;
      if (site.online) counts.online += 1;
      else counts.offline += 1;
      if ((parseInt(site.alarms, 10) || 0) > 0) counts.alarm += 1;
    });
    Object.keys(counts).forEach(function (key) {
      var el = stats.querySelector('[data-summary-count="' + key + '"]');
      if (el) el.textContent = String(counts[key]);
    });
    ['offline', 'alarm'].forEach(function (list) {
      var visible = 0;
      document.querySelectorAll('[data-summary-list="' + list + '"]').forEach(function (row) {
        var show = inScope(row.getAttribute('data-main-id'), row.getAttribute('data-region-id'), scope);
        row.classList.toggle('d-none', !show);
        if (show) visible += 1;
      });
      var empty = document.querySelector('[data-summary-empty="' + list + '"]');
      if (empty) empty.classList.toggle('d-none', visible > 0);
    });
  }

  function applyFilter() {
    if (!leafletMap) return;
    var value = filterEl ? filterEl.value : '';
    pinBounds = [];
    markers.forEach(function (item) {
      var show = siteMatches(item.site, value);
      if (show) {
        if (!leafletMap.hasLayer(item.marker)) item.marker.addTo(leafletMap);
        pinBounds.push(item.latlng);
      } else if (leafletMap.hasLayer(item.marker)) {
        leafletMap.removeLayer(item.marker);
      }
    });
    fitPins();
  }

  function fitPins() {
    if (!leafletMap) return;
    if (pinBounds.length) {
      leafletMap.fitBounds(pinBounds, { padding: [40, 40], maxZoom: 13 });
    } else if (latLngBounds) {
      leafletMap.fitBounds(latLngBounds);
    }
  }

  function refreshMap() {
    if (!isPanelVisible()) {
      window.setTimeout(refreshMap, 100);
      return;
    }

    var map = initMap();
    if (!map) return;

    map.invalidateSize(true);
    fitPins();
    window.setTimeout(function () {
      map.invalidateSize(true);
      fitPins();
    }, 200);
  }

  window.siteHomeMapRefresh = refreshMap;

  if (isPanelVisible()) {
    refreshMap();
  }

  window.addEventListener('home-panel-change', function (ev) {
    if (ev.detail && ev.detail.panel === 'site') {
      window.setTimeout(refreshMap, 30);
    }
  });

  function markActiveFilter() {
    var value = filterEl ? filterEl.value : '';
    document.querySelectorAll('[data-summary-filter]').forEach(function (el) {
      var active = el.getAttribute('data-summary-filter') === value;
      el.classList.toggle('is-active', active);
      el.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function markActiveTree() {
    var mainId = mainEl && mainEl.value ? mainEl.value : '';
    var regionId = regionEl && regionEl.value ? regionEl.value : '';
    document.querySelectorAll('#site-tree .site-home-tree-item').forEach(function (item) {
      var type = item.getAttribute('data-node-type');
      var id = item.getAttribute('data-node-id') || '';
      var active = (type === 'Main' && mainId !== '' && id === mainId) || (type === 'Region' && regionId !== '' && id === regionId);
      var row = item.querySelector(':scope > .site-home-tree-branch > .site-home-tree-row, :scope > .site-home-tree-row');
      if (row) row.classList.toggle('is-filter-active', active);
    });
  }

  function onScopeChange() {
    syncRegions();
    markActiveFilter();
    markActiveTree();
    updateSummary();
    applyFilter();
  }

  var tree = document.getElementById('site-tree');
  if (tree) {
    tree.addEventListener('click', function (ev) {
      var row = ev.target.closest('.site-home-tree-row');
      if (!row || !tree.contains(row)) return;
      var item = row.closest('.site-home-tree-item');
      if (!item) return;
      var type = item.getAttribute('data-node-type');
      var id = item.getAttribute('data-node-id') || '';
      if (!id || (type !== 'Main' && type !== 'Region')) return;
      if (type === 'Main') {
        if (mainEl) mainEl.value = id;
        if (regionEl) regionEl.value = '';
      } else if (regionEl) {
        var mainItem = item.parentElement ? item.parentElement.closest('.site-home-tree-item[data-node-type="Main"]') : null;
        if (mainEl && mainItem) mainEl.value = mainItem.getAttribute('data-node-id') || '';
        syncRegions();
        regionEl.value = id;
      }
      onScopeChange();
    });
  }

  [filterEl, mainEl, regionEl].forEach(function (el) {
    if (el) el.addEventListener('change', onScopeChange);
  });

  document.querySelectorAll('[data-summary-filter]').forEach(function (el) {
    el.addEventListener('click', function () {
      if (!filterEl) return;
      filterEl.value = el.getAttribute('data-summary-filter') || '';
      onScopeChange();
    });
  });

  window.addEventListener('resize', function () {
    if (leafletMap && isPanelVisible()) {
      leafletMap.invalidateSize(true);
    }
  });
})();
