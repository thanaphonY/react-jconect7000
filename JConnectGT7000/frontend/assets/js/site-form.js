(function () {
  var root = document.getElementById('site-node-form-page');
  if (!root) return;

  var form = document.getElementById('site-node-form');
  var typeEl = document.getElementById('node_type');
  var parentField = document.getElementById('site-parent-field');
  var parentEl = document.getElementById('parent_id');
  var coords = document.getElementById('site-coordinates');
  var latInput = document.getElementById('latitude');
  var lngInput = document.getElementById('longitude');
  var mapEl = document.getElementById('site-form-map');
  var ranks = {};
  var leafletMap = null;
  var leafletMarker = null;

  if (form && form.getAttribute('data-type-ranks')) {
    try {
      ranks = JSON.parse(form.getAttribute('data-type-ranks'));
    } catch (e) {
      ranks = { Main: 0, Region: 1, Site: 2, Building: 3, Room: 4 };
    }
  }

  function parentRank(option) {
    var t = option.getAttribute('data-node-type') || '';
    return typeof ranks[t] === 'number' ? ranks[t] : 99;
  }

  function childRank() {
    return typeEl ? ranks[typeEl.value] : 99;
  }

  function applyParentRules() {
    if (!typeEl || !parentEl) return;
    var type = typeEl.value;
    var rank = childRank();
    var isMain = type === 'Main';

    if (parentField) {
      parentField.classList.toggle('d-none', isMain);
    }

    Array.prototype.forEach.call(parentEl.options, function (opt) {
      if (opt.value === '') {
        opt.disabled = !isMain;
        return;
      }
      var ok = !isMain && parentRank(opt) < rank;
      opt.disabled = !ok;
      if (!ok && opt.selected) {
        opt.selected = false;
      }
    });

    if (isMain) {
      parentEl.value = '';
    }

    if (window.jQuery && jQuery.fn.select2 && jQuery(parentEl).data('select2')) {
      jQuery(parentEl).trigger('change.select2');
    }
  }

  function parseCoord(input) {
    if (!input) return null;
    var v = parseFloat(String(input.value).trim());
    return Number.isFinite(v) ? v : null;
  }

  function formatCoord(n) {
    return Number(n).toFixed(6);
  }

  function parseBounds(raw) {
    if (!Array.isArray(raw) || raw.length < 4) {
      return [13.65, 13.85, 100.42, 100.62];
    }
    return raw.map(parseFloat);
  }

  function configureLeafletIcons() {
    if (typeof L === 'undefined' || !mapEl) return;
    var base = mapEl.getAttribute('data-leaflet-icons') || '';
    if (!base) return;
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
      iconRetinaUrl: base + 'marker-icon-2x.png',
      iconUrl: base + 'marker-icon.png',
      shadowUrl: base + 'marker-shadow.png'
    });
  }

  function placeMarker(lat, lng) {
    if (!leafletMap || typeof L === 'undefined') return;
    if (leafletMarker) {
      leafletMarker.setLatLng([lat, lng]);
      return;
    }
    leafletMarker = L.marker([lat, lng], { draggable: true }).addTo(leafletMap);
    leafletMarker.on('dragend', function (ev) {
      var p = ev.target.getLatLng();
      setMarkerPosition(p.lat, p.lng, false);
    });
  }

  function clearMarker() {
    if (leafletMarker && leafletMap) {
      leafletMap.removeLayer(leafletMarker);
    }
    leafletMarker = null;
  }

  function setMarkerPosition(lat, lng, updateMarker) {
    if (!latInput || !lngInput) return;
    latInput.value = formatCoord(lat);
    lngInput.value = formatCoord(lng);
    if (updateMarker !== false) {
      placeMarker(lat, lng);
    }
  }

  function initSiteMap() {
    if (!mapEl || leafletMap || typeof L === 'undefined') {
      return;
    }

    configureLeafletIcons();

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
    var latLngBounds = L.latLngBounds([south, west], [north, east]);

    leafletMap = L.map(mapEl, {
      scrollWheelZoom: true,
      maxBounds: latLngBounds.pad(0.12),
      maxBoundsViscosity: 0.85
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

    sites.forEach(function (site) {
      if (!Number.isFinite(site.lat) || !Number.isFinite(site.lng)) return;
      L.circleMarker([site.lat, site.lng], {
        radius: 6,
        color: '#6c757d',
        weight: 2,
        fillColor: '#adb5bd',
        fillOpacity: 0.9
      })
        .bindTooltip(site.name || 'Site', { direction: 'top' })
        .addTo(leafletMap);
    });

    leafletMap.on('click', function (ev) {
      setMarkerPosition(ev.latlng.lat, ev.latlng.lng, true);
    });

    leafletMap.fitBounds(latLngBounds);
    mapEl.dataset.mapReady = '1';

    var startLat = parseCoord(latInput);
    var startLng = parseCoord(lngInput);
    if (startLat === null || startLng === null) {
      var dataLat = parseFloat(mapEl.getAttribute('data-marker-lat'));
      var dataLng = parseFloat(mapEl.getAttribute('data-marker-lng'));
      if (Number.isFinite(dataLat) && Number.isFinite(dataLng)) {
        startLat = dataLat;
        startLng = dataLng;
      }
    }
    if (startLat !== null && startLng !== null) {
      setMarkerPosition(startLat, startLng, true);
      leafletMap.setView([startLat, startLng], Math.max(leafletMap.getZoom(), 13));
    }
  }

  function ensureSiteMap() {
    if (!coords || coords.classList.contains('d-none')) return;
    initSiteMap();
    window.setTimeout(function () {
      if (leafletMap) leafletMap.invalidateSize();
    }, 80);
  }

  function syncCoords() {
    if (!coords || !typeEl) return;
    var show = typeEl.value === 'Site';
    coords.classList.toggle('d-none', !show);
    if (show) {
      ensureSiteMap();
    }
  }

  function syncMarkerFromInputs() {
    var lat = parseCoord(latInput);
    var lng = parseCoord(lngInput);
    if (lat === null || lng === null) {
      clearMarker();
      return;
    }
    if (!leafletMap) {
      ensureSiteMap();
    }
    setMarkerPosition(lat, lng, true);
  }

  function initParentSelect2() {
    if (!parentEl || typeof jQuery === 'undefined' || !jQuery.fn.select2) {
      return;
    }
    if (parentEl.dataset.select2Ready === '1') {
      return;
    }
    parentEl.dataset.select2Ready = '1';
    jQuery(parentEl).select2({
      theme: 'bootstrap-5',
      width: '100%',
      placeholder: parentEl.getAttribute('data-placeholder') || 'Search…',
      allowClear: !typeEl || typeEl.value !== 'Main',
      dropdownParent: jQuery(document.body),
      matcher: function (params, data) {
        if (data.disabled) {
          return null;
        }
        if (!params.term || !params.term.trim()) {
          return data;
        }
        if (typeof data.text === 'undefined') {
          return null;
        }
        var term = params.term.toLowerCase();
        if ((data.text || '').toLowerCase().indexOf(term) > -1) {
          return data;
        }
        return null;
      }
    });
  }

  root.querySelectorAll('.site-form-filter').forEach(function (input) {
    input.addEventListener('input', function () {
      var sel = input.getAttribute('data-filter-target');
      if (!sel) return;
      var q = (input.value || '').toLowerCase().trim();
      root.querySelectorAll(sel).forEach(function (row) {
        var hay = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
        row.classList.toggle('d-none', q !== '' && hay.indexOf(q) === -1);
      });
    });
  });

  if (typeEl) {
    typeEl.addEventListener('change', function () {
      applyParentRules();
      syncCoords();
    });
  }

  if (latInput) latInput.addEventListener('change', syncMarkerFromInputs);
  if (lngInput) lngInput.addEventListener('change', syncMarkerFromInputs);

  initSiteChannelPicker();
  initSiteDevicePicker();

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function initSiteChannelPicker() {
    var panel = document.getElementById('site-channel-panel');
    if (!panel) return;

    var catalog = [];
    try {
      catalog = JSON.parse(panel.getAttribute('data-channels') || '[]');
    } catch (e) {
      catalog = [];
    }

    var assignedList = document.getElementById('site-channel-assigned');
    var inputsWrap = document.getElementById('site-channel-inputs');
    var modalList = document.getElementById('site-channel-modal-list');
    var modalEmpty = document.getElementById('site-channel-modal-empty');
    var modalAddBtn = document.getElementById('site-channel-modal-add');
    var modalEl = document.getElementById('siteChannelModal');

    function catalogById() {
      var map = {};
      catalog.forEach(function (item) {
        map[item.id] = item;
      });
      return map;
    }

    function assignedIds() {
      var ids = {};
      if (!inputsWrap) return ids;
      inputsWrap.querySelectorAll('input[name="channel_ids[]"]').forEach(function (input) {
        ids[parseInt(input.value, 10)] = true;
      });
      return ids;
    }

    function syncEmptyState() {
      if (!assignedList) return;
      var rows = assignedList.querySelectorAll('.site-channel-row');
      var empty = assignedList.querySelector('.site-channel-empty');
      if (rows.length) {
        if (empty) empty.remove();
      } else if (!empty) {
        var li = document.createElement('li');
        li.className = 'list-group-item text-body-secondary small site-channel-empty';
        li.textContent = 'No channels linked. Click Add.';
        assignedList.appendChild(li);
      }
    }

    function removeChannel(id) {
      if (assignedList) {
        var row = assignedList.querySelector('.site-channel-row[data-channel-id="' + id + '"]');
        if (row) row.remove();
      }
      if (inputsWrap) {
        var input = inputsWrap.querySelector('input[data-channel-id="' + id + '"]');
        if (input) input.remove();
      }
      syncEmptyState();
    }

    function addChannel(id) {
      var map = catalogById();
      var item = map[id];
      if (!item || assignedIds()[id]) return;

      if (assignedList) {
        var li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center gap-2 site-channel-row';
        li.setAttribute('data-channel-id', String(id));
        li.innerHTML =
          '<span>' +
          escapeHtml(item.name) +
          ' <span class="badge text-bg-secondary ms-1">' +
          escapeHtml(item.type || '') +
          '</span></span>' +
          '<button type="button" class="btn btn-outline-danger btn-sm site-channel-remove" title="Remove" aria-label="Remove">' +
          '<i class="bi bi-x-lg"></i></button>';
        assignedList.appendChild(li);
      }

      if (inputsWrap) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'channel_ids[]';
        hidden.value = String(id);
        hidden.setAttribute('data-channel-id', String(id));
        inputsWrap.appendChild(hidden);
      }
      syncEmptyState();
    }

    function renderModalList() {
      if (!modalList) return;
      modalList.innerHTML = '';
      var ids = assignedIds();
      var available = catalog.filter(function (item) {
        return !ids[item.id];
      });

      if (modalEmpty) {
        modalEmpty.classList.toggle('d-none', available.length > 0);
      }
      if (!available.length) {
        return;
      }

      available.forEach(function (item) {
        var wrap = document.createElement('div');
        wrap.className = 'form-check mb-2';
        wrap.innerHTML =
          '<input class="form-check-input" type="checkbox" value="' +
          item.id +
          '" id="site-channel-pick-' +
          item.id +
          '">' +
          '<label class="form-check-label" for="site-channel-pick-' +
          item.id +
          '">' +
          escapeHtml(item.name) +
          ' <span class="text-body-secondary">(' +
          escapeHtml(item.type || '') +
          ')</span></label>';
        modalList.appendChild(wrap);
      });
    }

    if (assignedList) {
      assignedList.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.site-channel-remove');
        if (!btn) return;
        var row = btn.closest('.site-channel-row');
        if (!row) return;
        removeChannel(parseInt(row.getAttribute('data-channel-id'), 10));
      });
    }

    if (modalEl) {
      modalEl.addEventListener('show.bs.modal', renderModalList);
    }

    if (modalAddBtn) {
      modalAddBtn.addEventListener('click', function () {
        if (!modalList) return;
        modalList.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
          addChannel(parseInt(input.value, 10));
        });
        if (modalEl && window.bootstrap) {
          bootstrap.Modal.getInstance(modalEl).hide();
        }
      });
    }
  }

  function initSiteDevicePicker() {
    var panel = document.getElementById('site-device-panel');
    if (!panel) return;

    var catalog = [];
    try {
      catalog = JSON.parse(panel.getAttribute('data-devices') || '[]');
    } catch (e) {
      catalog = [];
    }

    var assignedList = document.getElementById('site-device-assigned');
    var inputsWrap = document.getElementById('site-device-inputs');
    var modalList = document.getElementById('site-device-modal-list');
    var modalEmpty = document.getElementById('site-device-modal-empty');
    var modalAddBtn = document.getElementById('site-device-modal-add');
    var modalEl = document.getElementById('siteDeviceModal');
    var modalSearch = document.getElementById('site-device-modal-search');

    function catalogById() {
      var map = {};
      catalog.forEach(function (item) {
        map[item.id] = item;
      });
      return map;
    }

    function assignedIds() {
      var ids = {};
      if (!inputsWrap) return ids;
      inputsWrap.querySelectorAll('input[name="device_ids[]"]').forEach(function (input) {
        ids[parseInt(input.value, 10)] = true;
      });
      return ids;
    }

    function syncEmptyState() {
      if (!assignedList) return;
      var rows = assignedList.querySelectorAll('.site-device-row');
      var empty = assignedList.querySelector('.site-device-empty');
      if (rows.length) {
        if (empty) empty.remove();
      } else if (!empty) {
        var li = document.createElement('li');
        li.className = 'list-group-item text-body-secondary small site-device-empty';
        li.textContent = 'No devices linked. Click Add.';
        assignedList.appendChild(li);
      }
    }

    function removeDevice(id) {
      if (assignedList) {
        var row = assignedList.querySelector('.site-device-row[data-device-id="' + id + '"]');
        if (row) row.remove();
      }
      if (inputsWrap) {
        var input = inputsWrap.querySelector('input[data-device-id="' + id + '"]');
        if (input) input.remove();
      }
      syncEmptyState();
    }

    function deviceLabel(item) {
      var html = escapeHtml(item.name);
      if (item.location) {
        html += ' <span class="text-body-secondary small"> — ' + escapeHtml(item.location) + '</span>';
      }
      return html;
    }

    function addDevice(id) {
      var map = catalogById();
      var item = map[id];
      if (!item || assignedIds()[id]) return;

      if (assignedList) {
        var li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center gap-2 site-device-row';
        li.setAttribute('data-device-id', String(id));
        li.innerHTML =
          '<span>' +
          deviceLabel(item) +
          '</span>' +
          '<button type="button" class="btn btn-outline-danger btn-sm site-device-remove" title="Remove" aria-label="Remove">' +
          '<i class="bi bi-x-lg"></i></button>';
        assignedList.appendChild(li);
      }

      if (inputsWrap) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'device_ids[]';
        hidden.value = String(id);
        hidden.setAttribute('data-device-id', String(id));
        inputsWrap.appendChild(hidden);
      }
      syncEmptyState();
    }

    function filterModalList() {
      if (!modalList || !modalSearch) return;
      var q = (modalSearch.value || '').toLowerCase().trim();
      modalList.querySelectorAll('.form-check').forEach(function (row) {
        var hay = (row.getAttribute('data-search') || '').toLowerCase();
        row.classList.toggle('d-none', q !== '' && hay.indexOf(q) === -1);
      });
    }

    function renderModalList() {
      if (!modalList) return;
      modalList.innerHTML = '';
      var ids = assignedIds();
      var available = catalog.filter(function (item) {
        return !ids[item.id];
      });

      if (modalEmpty) {
        modalEmpty.classList.toggle('d-none', available.length > 0);
      }
      if (!available.length) {
        return;
      }

      available.forEach(function (item) {
        var wrap = document.createElement('div');
        wrap.className = 'form-check mb-2';
        wrap.setAttribute('data-search', (item.name + ' ' + (item.location || '')).toLowerCase());
        wrap.innerHTML =
          '<input class="form-check-input" type="checkbox" value="' +
          item.id +
          '" id="site-device-pick-' +
          item.id +
          '">' +
          '<label class="form-check-label" for="site-device-pick-' +
          item.id +
          '">' +
          deviceLabel(item) +
          '</label>';
        modalList.appendChild(wrap);
      });
      filterModalList();
    }

    if (assignedList) {
      assignedList.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.site-device-remove');
        if (!btn) return;
        var row = btn.closest('.site-device-row');
        if (!row) return;
        removeDevice(parseInt(row.getAttribute('data-device-id'), 10));
      });
    }

    if (modalEl) {
      modalEl.addEventListener('show.bs.modal', function () {
        if (modalSearch) modalSearch.value = '';
        renderModalList();
      });
    }

    if (modalSearch) {
      modalSearch.addEventListener('input', filterModalList);
    }

    if (modalAddBtn) {
      modalAddBtn.addEventListener('click', function () {
        if (!modalList) return;
        modalList.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
          addDevice(parseInt(input.value, 10));
        });
        if (modalEl && window.bootstrap) {
          bootstrap.Modal.getInstance(modalEl).hide();
        }
      });
    }
  }

  function boot() {
    initParentSelect2();
    applyParentRules();
    syncCoords();
  }

  if (document.readyState === 'complete') {
    boot();
  } else {
    window.addEventListener('load', boot);
  }
})();
