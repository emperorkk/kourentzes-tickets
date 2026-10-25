/* Only an appearance preference is persisted. Never store ticket keys, content or sessions here. */
'use strict';
(() => {
    const roots = [...document.querySelectorAll('[data-kt-appearance]')];
    if (!roots.length) return;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const preferenceKey = 'ktickets.appearance';
    let choice = 'auto';
    const read = key => { try { return localStorage.getItem(key); } catch { return null; } };
    if (['auto', 'light', 'dark'].includes(read(preferenceKey))) choice = read(preferenceKey);
    function apply() {
        for (const root of roots) {
            let config;
            try { config = JSON.parse(root.dataset.ktAppearance); } catch { continue; }
            let mode = choice === 'auto' ? config.mode : choice;
            if (mode === 'site') {
                const stored = config.key ? read(config.key) : null;
                mode = ['light', 'dark', 'system', 'auto'].includes(stored) ? stored : config.site;
            }
            if (!['light', 'dark'].includes(mode)) mode = media.matches ? 'dark' : 'light';
            root.dataset.ktTheme = mode;
        }
        for (const select of document.querySelectorAll('[data-kt-theme-control]')) select.value = choice;
    }
    function mount() {
        for (const select of document.querySelectorAll('[data-kt-theme-control]')) {
            select.addEventListener('change', () => {
                choice = ['auto', 'light', 'dark'].includes(select.value) ? select.value : 'auto';
                try { localStorage.setItem(preferenceKey, choice); } catch { /* Appearance works without storage. */ }
                apply();
            });
        }
        apply();
    }
    apply();
    media.addEventListener('change', apply);
    window.addEventListener('storage', event => {
        if (event.key === preferenceKey || event.key === null) choice = ['auto', 'light', 'dark'].includes(read(preferenceKey)) ? read(preferenceKey) : 'auto';
        apply();
    });
    window.addEventListener('ktickets:site-theme-change', apply);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true }); else mount();
})();
