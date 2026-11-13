(function () {
  function boot() {
    var btn = document.getElementById('home-view-site-btn');
    var dash = document.getElementById('home-dashboard-panel');
    var site = document.getElementById('home-site-panel');
    if (!btn || !site) return;

    var label = btn.querySelector('.home-view-site-label');
    var layoutNav = document.getElementById('navbarSupportedContent');

    function showSite(on) {
      if (dash) {
        dash.classList.toggle('d-none', !!on);
      }
      site.classList.toggle('d-none', !on);

      if (label) {
        label.textContent = on ? 'View Home' : 'View Site';
      }
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.classList.toggle('active', !!on);
      if (layoutNav) {
        layoutNav.classList.toggle('d-none', !!on);
      }

      if (on) {
        if (window.location.hash !== '#site') {
          history.replaceState(null, '', '#site');
        }
      } else if (window.location.hash === '#site') {
        history.replaceState(null, '', window.location.pathname + window.location.search);
      }

      window.dispatchEvent(new CustomEvent('home-panel-change', {
        detail: { panel: on ? 'site' : 'dashboard' }
      }));
      if (on && typeof window.siteHomeMapRefresh === 'function') {
        window.siteHomeMapRefresh();
      }
    }

    function isSite() {
      return !site.classList.contains('d-none');
    }

    // Default: always hide site until user asks (unless #site hash).
    if (window.location.hash !== '#site') {
      site.classList.add('d-none');
      if (dash) dash.classList.remove('d-none');
      if (label) label.textContent = 'View Site';
      btn.setAttribute('aria-pressed', 'false');
      btn.classList.remove('active');
    }

    if (btn.getAttribute('data-home-toggle') === '1') {
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        showSite(!isSite());
      });
    }

    if (window.location.hash === '#site' || (!dash && site)) {
      showSite(true);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
