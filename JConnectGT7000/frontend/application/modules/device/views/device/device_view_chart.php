<?php
$deviceMetrics = [];
if (!empty($device->metrics)) {
    $decoded = json_decode($device->metrics, true);
    if (is_array($decoded)) {
        $deviceMetrics = $decoded;
    }
}

$enabledMetrics = array_values(array_filter($deviceMetrics, function ($m) {
    if (!is_array($m) || empty($m['key'])) {
        return false;
    }
    $enable = $m['enable'] ?? true;
    return $enable === true || $enable === 'true' || $enable === 1 || $enable === '1';
}));

$latestMetrics = isset($latest_metrics) && is_array($latest_metrics) ? $latest_metrics : [];

$deviceProtocol = strtolower((string) ($protocol ?? ''));
if ($deviceProtocol === '' && !empty($template->protocol)) {
    $deviceProtocol = strtolower((string) $template->protocol);
}
if ($deviceProtocol === '' && !empty($comm['protocol'])) {
    $deviceProtocol = strtolower((string) $comm['protocol']);
}
$isRtspCamera = ($deviceProtocol === 'rtsp');
$streamElementId = 'device-live-stream-' . (int) $device->id;

/**
 * Resolve a displayable latest value from device_data typed columns.
 */
$formatMetricLatest = function ($latest, $datatype) {
    if (!is_array($latest)) {
        return ['has' => false, 'text' => '—'];
    }

    $dt = strtolower(trim((string) $datatype));
    $value = $latest['value'] ?? null;
    $valueText = $latest['value_text'] ?? null;
    $valueBool = $latest['value_bool'] ?? null;
    $valueJson = $latest['value_json'] ?? null;

    $boolTruth = function ($v) {
        if (is_bool($v)) return $v;
        if ($v === 1 || $v === '1' || $v === 't' || $v === 'true' || $v === 'TRUE') return true;
        if ($v === 0 || $v === '0' || $v === 'f' || $v === 'false' || $v === 'FALSE') return false;
        return null;
    };

    if ($dt === 'bool' || $dt === 'boolean') {
        $b = $boolTruth($valueBool);
        if ($b === null) $b = $boolTruth($value);
        if ($b === null && $valueText !== null && $valueText !== '') $b = $boolTruth($valueText);
        if ($b === null) return ['has' => false, 'text' => '—'];
        return ['has' => true, 'text' => $b ? 'True' : 'False'];
    }

    if ($dt === 'string') {
        if ($valueText !== null && $valueText !== '') {
            return ['has' => true, 'text' => (string) $valueText];
        }
        if ($value !== null && $value !== '') {
            return ['has' => true, 'text' => (string) $value];
        }
        return ['has' => false, 'text' => '—'];
    }

    if ($dt === 'json') {
        if ($valueJson !== null && $valueJson !== '') {
            $text = is_string($valueJson) ? $valueJson : json_encode($valueJson, JSON_UNESCAPED_UNICODE);
            return ['has' => true, 'text' => (string) $text];
        }
        if ($valueText !== null && $valueText !== '') {
            return ['has' => true, 'text' => (string) $valueText];
        }
        return ['has' => false, 'text' => '—'];
    }

    // numeric / default — treat 0 as valid
    if ($value !== null && !(is_string($value) && trim($value) === '')) {
        if ($dt === 'int' || $dt === 'int16' || $dt === 'uint16' || $dt === 'int32' || $dt === 'uint32') {
            return ['has' => true, 'text' => (string) intval($value)];
        }
        return ['has' => true, 'text' => number_format((float) $value, 2, '.', '')];
    }

    if ($valueText !== null && $valueText !== '') {
        return ['has' => true, 'text' => (string) $valueText];
    }

    $b = $boolTruth($valueBool);
    if ($b !== null) {
        return ['has' => true, 'text' => $b ? 'True' : 'False'];
    }

    return ['has' => false, 'text' => '—'];
};
?>

<?php if ($isRtspCamera): ?>
  <div id="<?= htmlspecialchars($streamElementId) ?>"
       class="device-live-stream border rounded mb-3"
       style="width:100%;min-height:320px;display:flex;flex-direction:column;background:#111;overflow:hidden;"
       data-device-id="<?= (int) $device->id ?>">
    <div class="d-flex justify-content-between align-items-center px-2 py-1" style="background:#1a1a1a;color:#eee;font-size:12px;">
      <span class="camera-label text-truncate"><?php echo lang('live_stream') ?></span>
      <span class="camera-status text-muted">idle</span>
    </div>
    <div class="flex-grow-1 position-relative" style="min-height:320px;background:#000;">
      <video class="camera-video" muted autoplay playsinline controls
             style="width:100%;height:100%;min-height:320px;object-fit:contain;background:#000;"></video>
      <div class="camera-overlay position-absolute top-50 start-50 translate-middle text-white-50 small">
        Connecting…
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if (empty($enabledMetrics)): ?>
  <?php if (!$isRtspCamera): ?>
    <p class="text-muted mb-0"><?php echo lang('no_data_found') ?></p>
  <?php endif; ?>
<?php else: ?>
  <style>
    #device-metrics-tables .device-data-table { table-layout: fixed; width: 100%; }
    #device-metrics-tables .device-data-table th:nth-child(1),
    #device-metrics-tables .device-data-table td:nth-child(1) { width: 34%; }
    #device-metrics-tables .device-data-table th:nth-child(2),
    #device-metrics-tables .device-data-table td:nth-child(2) { width: 16%; }
    #device-metrics-tables .device-data-table th:nth-child(3),
    #device-metrics-tables .device-data-table td:nth-child(3) { width: 22%; }
    #device-metrics-tables .device-data-table th:nth-child(4),
    #device-metrics-tables .device-data-table td:nth-child(4) { width: 28%; }
    #device-metrics-tables .metric-label { line-height: 1.25; }
    #device-metrics-tables .metric-note {
      font-size: .8125rem;
      line-height: 1.35;
      color: var(--bs-secondary-color, #6c757d);
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
      overflow: hidden;
      white-space: pre-line;
      text-align: left;
    }
    #device-metrics-tables .metric-value { font-variant-numeric: tabular-nums; }
  </style>
  <div class="table-responsive" id="device-metrics-tables">
    <table class="table table-sm table-striped table-hover align-middle mb-0 device-data-table">
      <thead class="table-light">
        <tr>
          <th scope="col" class="text-start"><?php echo lang('metrics') ?></th>
          <th scope="col" class="text-end"><?php echo lang('value') ?></th>
          <th scope="col" class="text-center"><?php echo lang('last_update') ?></th>
          <th scope="col" class="text-start"><?php echo lang('description') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($enabledMetrics as $metric):
          $key = $metric['key'];
          $label = !empty($metric['label']) ? $metric['label'] : $key;
          $unit = $metric['unit'] ?? '';
          $datatype = $metric['datatype'] ?? '';
          $description = isset($metric['description']) ? trim((string) $metric['description']) : '';
          $latest = $latestMetrics[$key] ?? null;
          $formatted = $formatMetricLatest($latest, $datatype);
          $hasValue = !empty($formatted['has']);
          $latestValue = $formatted['text'];
          $latestTs = !empty($latest['timestamp']) ? $latest['timestamp'] : '';
        ?>
          <tr
            class="metric-row"
            data-device-id="<?= (int) $device->id ?>"
            data-metric-key="<?= htmlspecialchars($key) ?>"
            data-unit="<?= htmlspecialchars($unit) ?>"
            data-datatype="<?= htmlspecialchars($datatype) ?>"
          >
            <td class="text-start">
              <div class="metric-label fw-semibold"><?= htmlspecialchars($label) ?></div>
            </td>
            <td class="text-end text-nowrap">
              <span class="metric-value fw-semibold"><?= htmlspecialchars($latestValue) ?></span><?php if ($unit !== '' && $unit !== '-' && $hasValue): ?> <span class="metric-unit text-muted small"><?= htmlspecialchars($unit) ?></span><?php endif; ?>
            </td>
            <td class="text-center text-muted small text-nowrap metric-time" data-timestamp="<?= htmlspecialchars($latestTs) ?>">
              <?= $latestTs ? '' : '—' ?>
            </td>
            <td class="text-start">
              <?php if ($description !== ''): ?>
                <div class="metric-note" title="<?= htmlspecialchars($description) ?>"><?= htmlspecialchars($description) ?></div>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if ($isRtspCamera): ?>
<script>
(function () {
  const root = document.getElementById(<?= json_encode($streamElementId) ?>);
  if (!root) return;

  const deviceId = root.dataset.deviceId;
  const video = root.querySelector('.camera-video');
  const statusEl = root.querySelector('.camera-status');
  const overlay = root.querySelector('.camera-overlay');
  let viewerId = null;
  let hls = null;
  let heartbeatTimer = null;
  let stopped = false;
  let retryTimer = null;

  function setStatus(text) {
    if (statusEl) statusEl.textContent = text;
  }

  function showOverlay(text) {
    if (!overlay) return;
    overlay.textContent = text || 'Connecting…';
    overlay.classList.remove('d-none');
  }

  function hideOverlay() {
    if (overlay) overlay.classList.add('d-none');
  }

  function api(path, body) {
    return fetch('/webhook/stream/' + deviceId + '/' + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {}),
      credentials: 'same-origin'
    }).then(function (r) {
      return r.json().catch(function () {
        return { ok: false, error: 'bad_json_' + r.status };
      });
    });
  }

  function loadHlsJs() {
    if (window.Hls) return Promise.resolve(window.Hls);
    return new Promise(function (resolve, reject) {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js';
      s.onload = function () { resolve(window.Hls); };
      s.onerror = function () { reject(new Error('hls_js_load_failed')); };
      document.head.appendChild(s);
    });
  }

  function absoluteUrl(url) {
    if (!url) return url;
    if (/^https?:\/\//i.test(url)) return url;
    return window.location.origin + url;
  }

  function destroyHls() {
    if (hls) {
      try { hls.destroy(); } catch (e) {}
      hls = null;
    }
    try {
      video.removeAttribute('src');
      video.load();
    } catch (e) {}
  }

  function playPlaylist(url) {
    const src = absoluteUrl(url);
    return loadHlsJs().then(function (Hls) {
      destroyHls();
      if (Hls && Hls.isSupported()) {
        hls = new Hls({
          enableWorker: true,
          lowLatencyMode: false,
          liveDurationInfinity: true,
          maxLiveSyncPlaybackRate: 1.5
        });
        hls.loadSource(src);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, function () {
          hideOverlay();
          setStatus('playing');
          video.play().catch(function () {});
        });
        hls.on(Hls.Events.ERROR, function (_e, data) {
          if (!data || !data.fatal) return;
          setStatus('error:' + (data.type || 'media'));
          showOverlay('Playback error — retrying…');
          if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
            try { hls.startLoad(); } catch (e) {}
            scheduleRetry(3000);
          } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
            try { hls.recoverMediaError(); } catch (e) { scheduleRetry(3000); }
          } else {
            scheduleRetry(4000);
          }
        });
      } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = src;
        video.addEventListener('loadedmetadata', function () {
          hideOverlay();
          setStatus('playing');
        }, { once: true });
        video.play().catch(function () {});
      } else {
        setStatus('unsupported');
        showOverlay('Browser cannot play HLS');
      }
    });
  }

  function scheduleRetry(ms) {
    if (stopped) return;
    clearTimeout(retryTimer);
    retryTimer = setTimeout(function () {
      if (!stopped) start();
    }, ms || 4000);
  }

  function startHeartbeat() {
    stopHeartbeat();
    heartbeatTimer = setInterval(function () {
      if (!viewerId || stopped) return;
      api('heartbeat', { viewerId: viewerId }).catch(function () {});
    }, 15000);
  }

  function stopHeartbeat() {
    if (heartbeatTimer) {
      clearInterval(heartbeatTimer);
      heartbeatTimer = null;
    }
  }

  function start() {
    if (!deviceId) {
      setStatus('no device');
      return;
    }
    stopped = false;
    showOverlay('Connecting…');
    setStatus('starting');
    api('watch', {}).then(function (res) {
      if (!res || !res.ok) {
        const err = res && res.error ? res.error : 'failed';
        setStatus(err === 'ffmpeg_not_installed' ? 'ffmpeg missing' : err);
        showOverlay(err === 'ffmpeg_not_installed'
          ? 'Install ffmpeg on server'
          : ('Error: ' + err));
        scheduleRetry(5000);
        return;
      }
      viewerId = res.viewerId;
      setStatus('live (' + (res.viewers || 1) + ')');
      startHeartbeat();
      return new Promise(function (resolve) {
        setTimeout(resolve, res.state === 'running' ? 500 : 2000);
      }).then(function () {
        return playPlaylist(res.playlistUrl);
      });
    }).catch(function (e) {
      setStatus('error');
      showOverlay('Request failed');
      console.error('[device live stream]', e);
      scheduleRetry(5000);
    });
  }

  function stop() {
    stopped = true;
    clearTimeout(retryTimer);
    stopHeartbeat();
    destroyHls();
    if (viewerId) {
      api('unwatch', { viewerId: viewerId }).catch(function () {});
      viewerId = null;
    }
    setStatus('idle');
  }

  start();
  window.addEventListener('beforeunload', stop);
})();
</script>
<?php endif; ?>

<?php if (!empty($enabledMetrics)): ?>
<script>
(function () {
  const deviceId = <?= json_encode((int) $device->id) ?>;
  const metrics = <?= json_encode(array_map(function ($m) {
    return [
      'key' => $m['key'],
      'unit' => $m['unit'] ?? '',
      'datatype' => $m['datatype'] ?? '',
    ];
  }, $enabledMetrics), JSON_UNESCAPED_UNICODE) ?>;

  function formatDate(timestamp) {
    if (!timestamp) return '-';
    const date = new Date(timestamp);
    if (isNaN(date.getTime())) return String(timestamp);
    return date.toLocaleString('en-GB', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  }

  function formatValue(value, datatype) {
    if (value === null || value === undefined || value === '') return '-';

    if (datatype === 'boolean' || datatype === 'bool') {
      return value ? 'True' : 'False';
    }
    if (datatype === 'string') {
      return String(value);
    }
    if (datatype === 'int') {
      const n = parseInt(value, 10);
      return Number.isNaN(n) ? String(value) : String(n);
    }

    const num = Number(value);
    if (Number.isNaN(num)) return String(value);
    return num.toFixed(2);
  }

  function findRow(metricKey) {
    return document.querySelector(
      `.metric-row[data-device-id="${deviceId}"][data-metric-key="${CSS.escape(String(metricKey))}"]`
    );
  }

  function updateMetricRow(metricKey, value, timestamp, datatype, unit) {
    const row = findRow(metricKey);
    if (!row) return;

    const valueCell = row.querySelector('td.text-end');
    let valueEl = row.querySelector('.metric-value');
    let unitEl = row.querySelector('.metric-unit');
    const timeEl = row.querySelector('.metric-time');
    const resolvedDatatype = datatype || row.dataset.datatype || '';
    const resolvedUnit = (unit != null && unit !== '') ? unit : (row.dataset.unit || '');

    if (!valueEl && valueCell) {
      valueEl = document.createElement('span');
      valueEl.className = 'metric-value fw-semibold';
      valueCell.textContent = '';
      valueCell.appendChild(valueEl);
    }

    if (valueEl) {
      valueEl.textContent = formatValue(value, resolvedDatatype);
    }

    if (resolvedUnit) {
      if (!unitEl && valueCell) {
        valueCell.appendChild(document.createTextNode(' '));
        unitEl = document.createElement('span');
        unitEl.className = 'metric-unit text-muted small';
        valueCell.appendChild(unitEl);
      }
      if (unitEl) unitEl.textContent = resolvedUnit;
    } else if (unitEl) {
      unitEl.textContent = '';
    }

    if (timeEl) {
      timeEl.dataset.timestamp = timestamp || '';
      timeEl.textContent = timestamp ? formatDate(timestamp) : '—';
    }
  }

  function hydrateTimestamps() {
    document.querySelectorAll('.metric-time[data-timestamp]').forEach((el) => {
      const ts = el.getAttribute('data-timestamp');
      el.textContent = ts ? formatDate(ts) : '-';
    });
  }

  function initWebSocket() {
    if (!metrics.length) return;

    const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws';
    const socket = new WebSocket(`${protocol}://${window.location.host}/ws`);

    socket.onopen = () => {
      socket.send(JSON.stringify({
        type: 'subscribe',
        metrics: metrics.map((m) => ({
          device_id: deviceId,
          metric_key: m.key
        }))
      }));
    };

    socket.onmessage = (event) => {
      let batch = [];
      try {
        batch = JSON.parse(event.data);
      } catch (err) {
        console.warn('[Device Metrics WS] parse error:', err);
        return;
      }

      if (!Array.isArray(batch)) return;

      batch.forEach((metric) => {
        if (!metric || !metric.metric_key) return;
        if (parseInt(metric.device_id, 10) !== deviceId) return;

        updateMetricRow(
          metric.metric_key,
          metric.value,
          metric.timestamp,
          metric.datatype,
          metric.unit
        );
      });
    };

    socket.onclose = () => {
      setTimeout(initWebSocket, 2000);
    };

    socket.onerror = () => {
      try { socket.close(); } catch (e) { /* ignore */ }
    };
  }

  document.addEventListener('DOMContentLoaded', function () {
    hydrateTimestamps();
    initWebSocket();
  });
})();
</script>
<?php endif; ?>
