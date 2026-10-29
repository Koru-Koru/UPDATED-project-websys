/* Loaded before the page paints so a saved dark theme does not flash white. */
(() => {
    'use strict';
    const key = 'phbestcoffee-theme';
    const root = document.documentElement;
    let theme = 'light';
    try { if (localStorage.getItem(key) === 'dark') theme = 'dark'; } catch (_) {}

    function apply(value) {
        theme = value === 'dark' ? 'dark' : 'light';
        if (root.dataset.coffeeTheme !== theme) root.dataset.coffeeTheme = theme;
        root.dataset.bsTheme = theme;
        root.style.colorScheme = theme;
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            button.setAttribute('aria-pressed', String(theme === 'dark'));
            button.title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
            const icon = button.querySelector('[data-theme-icon]');
            if (icon) icon.textContent = theme === 'dark' ? '☀' : '☾';
            const label = button.querySelector('[data-theme-label]');
            if (label) label.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
        });
    }
    apply(theme);
    document.addEventListener('DOMContentLoaded', () => {
        apply(theme);
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            button.hidden = false;
            button.addEventListener('click', () => {
                apply(theme === 'dark' ? 'light' : 'dark');
                try { localStorage.setItem(key, theme); } catch (_) {}
            });
        });
    });
    window.addEventListener('pageshow', () => {
        try { apply(localStorage.getItem(key)); } catch (_) { apply(theme); }
    });
    window.addEventListener('storage', event => {
        if (event.key === key || event.key === null) apply(event.newValue);
    });
})();
