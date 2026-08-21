(function () {
    'use strict';

    var STORAGE_KEY = 'sv-site-theme';
    var EXPLICIT_KEY = 'sv-site-theme-explicit';
    var LEGACY_ERP_KEY = 'rd-dashboard-theme';

    function osPrefersDark() {
        return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    }

    function migrateLegacyTheme() {
        var legacy = localStorage.getItem(LEGACY_ERP_KEY);
        if (!legacy) return;
        if (!localStorage.getItem(STORAGE_KEY)) {
            localStorage.setItem(STORAGE_KEY, legacy === 'dark' ? 'dark' : 'light');
            localStorage.setItem(EXPLICIT_KEY, '1');
        }
        localStorage.removeItem(LEGACY_ERP_KEY);
    }

    function migrateSystemMode() {
        if (localStorage.getItem(STORAGE_KEY) === 'system') {
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem(EXPLICIT_KEY);
        }
    }

    function hasExplicitChoice() {
        return localStorage.getItem(EXPLICIT_KEY) === '1';
    }

    function getSavedMode() {
        migrateLegacyTheme();
        migrateSystemMode();
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'light' || saved === 'dark') {
            return saved;
        }
        return null;
    }

    function resolveTheme(mode) {
        if (mode === 'dark' || mode === 'light') {
            return mode;
        }
        return osPrefersDark() ? 'dark' : 'light';
    }

    function themeChoiceFromBtn(btn) {
        return btn.getAttribute('data-site-theme') || btn.getAttribute('data-sv-theme');
    }

    function syncThemeButtons(resolved) {
        document.querySelectorAll('.site-theme-btn, .rd-dash-theme-btn').forEach(function (btn) {
            var choice = themeChoiceFromBtn(btn);
            if (choice !== 'light' && choice !== 'dark') return;
            var active = choice === resolved;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function applyTheme(mode) {
        var explicitChoice = mode === 'light' || mode === 'dark';
        var saved = explicitChoice ? mode : getSavedMode();
        var resolved = resolveTheme(saved);
        var root = document.documentElement;
        var isDark = resolved === 'dark';

        root.classList.toggle('site-theme-dark', isDark);
        root.classList.toggle('rd-dash-theme-dark', isDark);
        root.style.colorScheme = isDark ? 'dark' : 'light';
        root.dataset.theme = resolved;

        if (explicitChoice) {
            localStorage.setItem(STORAGE_KEY, mode);
            localStorage.setItem(EXPLICIT_KEY, '1');
            root.dataset.themeMode = mode;
        } else if (saved) {
            root.dataset.themeMode = saved;
        } else {
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem(EXPLICIT_KEY);
            root.dataset.themeMode = 'auto';
        }

        syncThemeButtons(resolved);

        document.dispatchEvent(new CustomEvent('erp-theme-change', { detail: resolved }));
        if (typeof window.dashUpdateChartsTheme === 'function') {
            window.dashUpdateChartsTheme(isDark);
        }
    }

    window.SVSiteTheme = {
        apply: applyTheme,
        resolve: resolveTheme,
        getSaved: getSavedMode,
        hasExplicitChoice: hasExplicitChoice
    };

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (!hasExplicitChoice()) {
                applyTheme();
            }
        });
    }

    function bindControls() {
        document.querySelectorAll('.site-theme-btn, .rd-dash-theme-btn').forEach(function (btn) {
            if (btn.dataset.themeBound === '1') return;
            var choice = themeChoiceFromBtn(btn);
            if (choice !== 'light' && choice !== 'dark') return;
            btn.dataset.themeBound = '1';
            btn.addEventListener('click', function () {
                applyTheme(choice);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindControls);
    } else {
        bindControls();
    }

    applyTheme();
})();
