(function () {
  var root = document.getElementById('site-management');
  if (!root) return;

  var searchInput = root.querySelector('#site-tree-search');
  var typeFilter = root.querySelector('#site-type-filter');
  var expandBtn = root.querySelector('#site-expand-all');
  var collapseBtn = root.querySelector('#site-collapse-all');
  var matchCount = root.querySelector('#site-match-count');
  var treePanel = root.querySelector('#site-tree-panel');
  var sitesPanel = root.querySelector('#site-sites-panel');
  var viewTabs = root.querySelectorAll('[data-site-view]');

  function normalize(text) {
    return (text || '').toLowerCase().trim();
  }

  function eachBranch(fn) {
    root.querySelectorAll('.site-tree-branch').forEach(fn);
  }

  function filterTree() {
    var q = normalize(searchInput ? searchInput.value : '');
    var type = typeFilter ? typeFilter.value : '';
    var visible = 0;

    root.querySelectorAll('.site-tree-item').forEach(function (item) {
      var itemType = item.getAttribute('data-node-type') || '';
      var hay = normalize(item.getAttribute('data-search') || '');
      var typeOk = !type || itemType === type;
      var textOk = !q || hay.indexOf(q) !== -1;
      var show = typeOk && textOk;
      item.classList.toggle('d-none', !show);
      if (show) visible++;
    });

    if (q) {
      root.querySelectorAll('.site-tree-item:not(.d-none)').forEach(function (item) {
        var branch = item.querySelector(':scope > .site-tree-branch');
        if (branch) branch.open = true;
        var parent = item.parentElement;
        while (parent && parent !== root) {
          if (parent.classList.contains('site-tree-item')) {
            parent.classList.remove('d-none');
            var pb = parent.querySelector(':scope > .site-tree-branch');
            if (pb) pb.open = true;
          }
          if (parent.classList.contains('site-tree-branch')) {
            parent.open = true;
          }
          parent = parent.parentElement;
        }
      });
    }

    root.querySelectorAll('#site-sites-table tbody tr').forEach(function (row) {
      var hay = normalize(row.getAttribute('data-search') || '');
      var rowType = row.getAttribute('data-node-type') || 'Site';
      var typeOk = !type || rowType === type;
      var textOk = !q || hay.indexOf(q) !== -1;
      row.classList.toggle('d-none', !(typeOk && textOk));
    });

    if (matchCount) {
      matchCount.textContent = q || type ? visible + ' nodes' : '';
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', filterTree);
  }
  if (typeFilter) {
    typeFilter.addEventListener('change', filterTree);
  }
  if (expandBtn) {
    expandBtn.addEventListener('click', function () {
      eachBranch(function (el) { el.open = true; });
    });
  }
  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      eachBranch(function (el) { el.open = false; });
      var first = root.querySelector('.site-tree-branch');
      if (first) first.open = true;
    });
  }

  viewTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var view = tab.getAttribute('data-site-view');
      viewTabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
      if (treePanel) treePanel.classList.toggle('d-none', view !== 'tree');
      if (sitesPanel) sitesPanel.classList.toggle('d-none', view !== 'sites');
    });
  });

  root.querySelectorAll('.site-tree-actions-dropdown').forEach(function (wrap) {
    var toggle = wrap.querySelector('[data-bs-toggle="dropdown"]');
    var menu = wrap.querySelector('.dropdown-menu');
    if (!toggle || !menu) return;

    toggle.addEventListener('show.bs.dropdown', function () {
      if (menu.parentElement === document.body) return;
      wrap.__menuPlaceholder = document.createComment('site-dropdown-menu');
      menu.parentNode.insertBefore(wrap.__menuPlaceholder, menu);
      document.body.appendChild(menu);
      menu.classList.add('site-tree-dropdown-portal');
    });

    toggle.addEventListener('hidden.bs.dropdown', function () {
      if (menu.parentElement !== document.body || !wrap.__menuPlaceholder) return;
      wrap.insertBefore(menu, wrap.__menuPlaceholder);
      wrap.__menuPlaceholder.remove();
      wrap.__menuPlaceholder = null;
      menu.classList.remove('site-tree-dropdown-portal');
    });
  });
})();
