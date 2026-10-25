'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/theme.js'), 'utf8');
function mount(config, stored = {}, dark = false, unavailable = false) {
    const handlers = {}, mediaHandlers = {}, selectHandlers = {}, reads = [];
    const root = { dataset: { ktAppearance: JSON.stringify(config) } };
    const select = { value: '', addEventListener: (name, fn) => { selectHandlers[name] = fn; } };
    const media = { matches: dark, addEventListener: (name, fn) => { mediaHandlers[name] = fn; } };
    vm.runInNewContext(source, {
        document: { readyState: 'complete', querySelectorAll: s => s === '[data-kt-appearance]' ? [root] : [select] },
        window: { matchMedia: () => media, addEventListener: (name, fn) => { handlers[name] = fn; } },
        localStorage: { getItem: key => { reads.push(key); if (unavailable) throw Error('blocked'); return stored[key] || null; }, setItem: (key, value) => { if (unavailable) throw Error('blocked'); stored[key] = value; } }
    });
    return { root, select, media, stored, reads, choose: value => { select.value = value; selectHandlers.change(); }, os: value => { media.matches = value; mediaHandlers.change(); }, storage: () => handlers.storage({ key: config.key }) };
}
let m = mount({ mode: 'site', site: 'dark', key: '' });
assert.equal(m.root.dataset.ktTheme, 'dark');
m.choose('light');assert.equal(m.root.dataset.ktTheme, 'light');assert.equal(m.stored['ktickets.appearance'], 'light');
m.choose('auto');assert.equal(m.root.dataset.ktTheme, 'dark');
m = mount({ mode: 'light', site: 'dark', key: '' }, {}, true);assert.equal(m.root.dataset.ktTheme, 'light');
m = mount({ mode: 'system', site: 'dark', key: '' });assert.equal(m.root.dataset.ktTheme, 'light');m.os(true);assert.equal(m.root.dataset.ktTheme, 'dark');
m = mount({ mode: 'site', site: 'dark', key: 'site.color' }, { 'site.color': 'light' });assert.equal(m.root.dataset.ktTheme, 'light');m.stored['site.color']='dark';m.storage();assert.equal(m.root.dataset.ktTheme, 'dark');
m = mount({ mode: 'site', site: 'dark', key: '' }, {}, false, true);m.choose('light');assert.equal(m.root.dataset.ktTheme, 'light');
assert(!m.reads.some(key => /secret|ticket-key|csrf/.test(key)));
console.log('PASS appearance: central site mode, light/dark overrides, Automatic reset, live device changes, explicit site-storage synchronization and blocked-storage fallback.');
