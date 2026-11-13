(function () {
    var COOKIE_NAME = 'sidebarstate';
    var COOKIE_DAYS = 365;
    var DESKTOP_BREAKPOINT = 992;

    function getCookie(name) {
        var parts = ('; ' + document.cookie).split('; ' + name + '=');
        if (parts.length < 2) {
            return '';
        }
        return decodeURIComponent(parts.pop().split(';').shift() || '');
    }

    function setCookie(name, value, days) {
        var expires = new Date();
        expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = name + '=' + encodeURIComponent(value)
            + '; expires=' + expires.toUTCString()
            + '; path=/; SameSite=Lax';
    }

    function currentState() {
        return document.body.classList.contains('sidebar-collapse')
            ? 'sidebar-collapse'
            : 'sidebar-open';
    }

    function applySidebarCookie() {
        if (window.innerWidth <= DESKTOP_BREAKPOINT) {
            return;
        }
        var state = getCookie(COOKIE_NAME);
        if (state !== 'sidebar-collapse' && state !== 'sidebar-open') {
            return;
        }
        document.body.classList.toggle('sidebar-collapse', state === 'sidebar-collapse');
        document.body.classList.toggle('sidebar-open', state === 'sidebar-open');
    }

    function persistSidebarCookie() {
        if (window.innerWidth <= DESKTOP_BREAKPOINT) {
            return;
        }
        setCookie(COOKIE_NAME, currentState(), COOKIE_DAYS);
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (!target.closest('[data-lte-toggle="sidebar"]') && !target.closest('.sidebar-overlay')) {
            return;
        }
        setTimeout(persistSidebarCookie, 0);
    });

    document.addEventListener('DOMContentLoaded', applySidebarCookie);
    window.addEventListener('resize', applySidebarCookie);
})();
