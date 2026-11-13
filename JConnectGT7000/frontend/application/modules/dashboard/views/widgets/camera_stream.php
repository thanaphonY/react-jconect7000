<?php
$cfg = $widget->config ?? (object)[];
$deviceId = htmlspecialchars($cfg->device_id ?? ($widget->device_id ?? ''));
$label = htmlspecialchars($cfg->label ?? ($widget->label ?? 'Camera'));
$wid = htmlspecialchars($widget_id ?? uniqid('cam_'));
$elementId = "camera-stream-{$wid}";
$borderClass = widget_border_class($cfg);
$bgClass = widget_bg_class($cfg, '');
$styleAttr = widget_style_attr($cfg, 'width:100%;height:100%;display:flex;flex-direction:column;background:#111;overflow:hidden;');
?>
<div id="<?= $elementId ?>"
     class="widget-camera-stream <?= $borderClass ?> <?= $bgClass ?>"
     style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>"
     data-device-id="<?= $deviceId ?>"
     data-widget-id="<?= $wid ?>">
  <div class="d-flex justify-content-between align-items-center px-2 py-1" style="background:#1a1a1a;color:#eee;font-size:12px;">
    <span class="camera-label text-truncate"><?= $label ?></span>
    <span class="camera-status text-muted">idle</span>
  </div>
  <div class="flex-grow-1 position-relative" style="min-height:0;background:#000;">
    <video class="camera-video" muted autoplay playsinline controls
           style="width:100%;height:100%;object-fit:contain;background:#000;"></video>
    <div class="camera-overlay position-absolute top-50 start-50 translate-middle text-white-50 small d-none">
      Connecting…
    </div>
  </div>
</div>

<script>
(function () {
  const root = document.getElementById('<?= $elementId ?>');
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
      s.src = '<?= base_url('assets/plugins/hls.js/hls.min.js') ?>';
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
          maxLiveSyncPlaybackRate: 1.5,
          xhrSetup: function (xhr) {
            xhr.withCredentials = false;
          }
        });
        hls.loadSource(src);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, function () {
          hideOverlay();
          setStatus('playing');
          video.play().catch(function () {});
        });
        hls.on(Hls.Events.ERROR, function (_e, data) {
          console.warn('[camera_stream] hls error', data);
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
      if (stopped) return;
      start();
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
      // Give ffmpeg a moment to write first segments if stream just started
      return new Promise(function (resolve) {
        setTimeout(resolve, res.state === 'running' ? 500 : 2000);
      }).then(function () {
        return playPlaylist(res.playlistUrl);
      });
    }).catch(function (e) {
      setStatus('error');
      showOverlay('Request failed');
      console.error('[camera_stream]', e);
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

<style>
.widget-camera-stream .camera-video {
  display: block;
}
</style>
