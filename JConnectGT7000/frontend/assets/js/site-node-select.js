(function () {
  function dropdownParent() {
    return jQuery(document.body);
  }

  window.initSiteNodeSelect2 = function (selectId) {
    var el = document.getElementById(selectId || 'site_node_id_select');
    if (!el || el.dataset.select2Ready === '1') {
      return;
    }
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
      return;
    }
    el.dataset.select2Ready = '1';
    var $el = jQuery(el);
    $el.select2({
      theme: 'bootstrap-5',
      width: '100%',
      placeholder: el.getAttribute('data-placeholder') || 'Search location…',
      allowClear: true,
      dropdownParent: dropdownParent(),
      matcher: function (params, data) {
        if (!params.term || !params.term.trim()) {
          return data;
        }
        if (typeof data.text === 'undefined') {
          return null;
        }
        var term = params.term.toLowerCase();
        var text = (data.text || '').toLowerCase();
        if (text.indexOf(term) > -1) {
          return data;
        }
        return null;
      }
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    window.initSiteNodeSelect2();
  });
})();
