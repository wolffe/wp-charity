// Behavioral checks for the shipped script using a minimal DOM and transport double.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor() {
        this.attrs = {}; this.dataset = {}; this.style = {}; this.children = []; this.events = {};
        this.classList = { toggle: (name, active) => { this.attrs[name] = active; } };
    }
    addEventListener(name, fn) { (this.events[name] ??= []).push(fn); }
    async emit(name, extra = {}) { await Promise.all((this.events[name] || []).map(fn => fn({ preventDefault() {}, ...extra }))); }
    setAttribute(key, value) { this.attrs[key] = value; }
    getAttribute(key) { return this.attrs[key]; }
    append(...items) { this.children.push(...items); }
    replaceChildren(...items) { this.children = items; }
    focus() { this.focused = true; }
    set textContent(value) { this.text = value; this.children = []; }
    get textContent() { return this.text; }
    set innerHTML(value) { this.html = value; this.children = []; }
    get innerHTML() { return this.html; }
}
const remote = new Element();
remote.dataset = { action: 'cm_account_donations', nonce: 'valid' };
const tabs = ['dashboard', 'donations', 'profile'].map(id => Object.assign(new Element(), { hash: `#${id}` }));
const panels = tabs.map(tab => {
    const panel = Object.assign(new Element(), { id: tab.hash.slice(1) });
    panel.querySelectorAll = () => panel.id === 'donations' ? [remote] : [];
    return panel;
});
const list = new Element(); list.querySelectorAll = () => tabs;
const root = new Element(); root.querySelector = () => list; root.querySelectorAll = () => panels;
const submit = new Element(); const message = new Element();
const form = new Element();
const password = "A&b+c=quote'\\ tail";
form.dataset.cmAjax = 'fxm_register_user_front_end';
form.values = { nonce: 'valid', new_user_password: password, 're-pwd': password, new_user_phone: '+353 1234' };
form.querySelector = selector => selector === '[type="submit"]' ? submit : message;
form.reportValidity = () => true;
form.reset = () => { form.didReset = true; };
const window = new Element();
const location = { hash: '#dashboard' };
const requests = [];
let fail = false;
const context = {
    document: {
        addEventListener: (event, fn) => { if (event === 'DOMContentLoaded') fn(); },
        querySelectorAll: selector => ({ '.cm-show-orders': [], '.cm-account': [root], 'form[data-cm-ajax]': [form] })[selector],
        createElement: () => new Element(),
    },
    window, location, URLSearchParams,
    history: { pushState: (a, b, hash) => { location.hash = hash; } },
    FormData: class extends Map { constructor(form) { super(Object.entries(form.values)); } },
    cmData: { url: '/admin-ajax.php', loading: 'Loading', error: 'Error', retry: 'Retry', previous: 'Previous', next: 'Next', page: 'Page' },
    fetch: async (url, options) => {
        requests.push(options);
        if (fail) throw new Error('Connection failed');
        const page = Number(options.body.get('page') || 1);
        return { ok: true, json: async () => ({ success: true, data: { html: '<p>Donation</p>', page, more: page === 1, message: 'Registered' } }) };
    },
};
vm.runInNewContext(fs.readFileSync('assets/js/cm-data.js', 'utf8'), context);
const settle = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    assert.equal(requests.length, 0, 'Dashboard must not load donations');
    assert.equal(panels[1].hidden, true);
    await tabs[1].emit('click'); await settle();
    assert.equal(requests.length, 1);
    assert.equal(requests[0].body.get('action'), 'cm_account_donations');
    assert.equal(requests[0].credentials, 'same-origin');
    assert.equal(panels[1].hidden, false);
    assert.equal(tabs[1].attrs['aria-selected'], 'true');
    assert.equal(location.hash, '#donations');
    await tabs[0].emit('click'); await tabs[1].emit('click'); await settle();
    assert.equal(requests.length, 1, 'Reopening loaded tab must not duplicate requests');
    const nav = remote.children.at(-1);
    await nav.children.at(-1).emit('click'); await settle();
    assert.equal(requests.at(-1).body.get('page'), '2');
    assert.equal(remote.focused, true, 'Pagination restores focus');
    fail = true;
    await remote.children.at(-1).children[0].emit('click'); await settle();
    assert.equal(remote.children[0].textContent, 'Connection failed');
    assert.equal(remote.attrs['aria-busy'], 'false');
    fail = false;
    await remote.children[1].emit('click'); await settle();
    assert.equal(remote.dataset.loaded, '1');
    await tabs[1].emit('keydown', { key: 'ArrowRight' });
    assert.equal(tabs[2].focused, true);
    assert.equal(location.hash, '#profile');
    location.hash = '#dashboard'; await window.emit('popstate');
    assert.equal(panels[0].hidden, false);
    location.hash = '#donations'; await window.emit('hashchange');
    assert.equal(panels[1].hidden, false);
    await form.emit('submit');
    assert.equal(requests.at(-1).body.get('new_user_password'), password);
    assert.equal(requests.at(-1).body.get('new_user_phone'), '+353 1234');
    assert.equal(message.textContent, 'Registered');
    assert.equal(submit.disabled, false);
    assert.equal(form.didReset, true);
    fail = true;
    await form.emit('submit');
    assert.equal(message.textContent, 'Connection failed');
    assert.equal(submit.disabled, false, 'Failed forms must be retryable');
    console.log('PASS: browser logic (lazy loading, pagination/retry, tabs/history/keyboard, form encoding and errors).');
})().catch(error => { console.error(error); process.exitCode = 1; });
