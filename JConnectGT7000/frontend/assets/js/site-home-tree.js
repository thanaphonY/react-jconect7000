(function () {
  function initHomeTree(root) {
    if (!root || root.getAttribute('data-tree-ready') === '1') return;
    root.setAttribute('data-tree-ready', '1');

    var searchInput = root.querySelector('[data-site-home-search]');
    var expandBtn = root.querySelector('[data-site-home-expand]');
    var collapseBtn = root.querySelector('[data-site-home-collapse]');
    var items = Array.prototype.slice.call(root.querySelectorAll('.site-home-tree-item'));

    function setBranches(open) {
      root.querySelectorAll('.site-home-tree-branch').forEach(function (branch) {
        branch.open = open;
      });
    }

    function applySearch(query) {
      var q = String(query || '').trim().toLowerCase();
      items.forEach(function (item) {
        if (!q) {
          item.classList.remove('d-none', 'is-search-match');
          return;
        }
        var hay = (item.getAttribute('data-search') || '').toLowerCase();
        var match = hay.indexOf(q) !== -1;
        item.classList.toggle('d-none', !match);
        item.classList.toggle('is-search-match', match);
        if (match) {
          var parent = item.parentElement;
          while (parent && parent !== root) {
            if (parent.classList && parent.classList.contains('site-home-tree-item')) {
              parent.classList.remove('d-none');
            }
            if (parent.tagName === 'DETAILS') {
              parent.open = true;
            }
            parent = parent.parentElement;
          }
        }
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        applySearch(searchInput.value);
      });
    }
    if (expandBtn) {
      expandBtn.addEventListener('click', function () {
        setBranches(true);
      });
    }
    if (collapseBtn) {
      collapseBtn.addEventListener('click', function () {
        setBranches(false);
        if (searchInput) {
          searchInput.value = '';
          applySearch('');
        }
      });
    }
  }

  function boot() {
    document.querySelectorAll('[data-site-home-tree]').forEach(initHomeTree);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  document.addEventListener('home-panel-change', function (ev) {
    if (ev.detail && ev.detail.panel === 'site') {
      boot();
    }
  });
})();
