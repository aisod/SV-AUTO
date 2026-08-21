(function () {
    var k = 'sv-site-theme';
    var explicitKey = 'sv-site-theme-explicit';
    var legacy = localStorage.getItem('rd-dashboard-theme');

    if (legacy && !localStorage.getItem(k)) {
        localStorage.setItem(k, legacy === 'dark' ? 'dark' : 'light');
        localStorage.setItem(explicitKey, '1');
        localStorage.removeItem('rd-dashboard-theme');
    }

    if (localStorage.getItem(k) === 'system') {
        localStorage.removeItem(k);
        localStorage.removeItem(explicitKey);
    }

    var explicit = localStorage.getItem(explicitKey) === '1';
    var saved = localStorage.getItem(k);
    var resolved;

    if (explicit && (saved === 'light' || saved === 'dark')) {
        resolved = saved;
    } else {
        resolved = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }

    var root = document.documentElement;
    var dark = resolved === 'dark';

    if (dark) {
        root.classList.add('site-theme-dark');
        root.classList.add('rd-dash-theme-dark');
    }

    root.style.colorScheme = dark ? 'dark' : 'light';
    root.dataset.theme = resolved;
    root.dataset.themeMode = explicit ? saved : 'auto';
})();
