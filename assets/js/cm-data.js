/* Account forms, accessible tabs and bounded private report requests. */
document.addEventListener('DOMContentLoaded', () => {
    const request = async body => {
        const response = await fetch(cmData.url, { method: 'POST', body, credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data?.message || cmData.error);
        return result.data;
    };

    const button = (label, action) => {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = 'button';
        element.textContent = label;
        element.addEventListener('click', action);
        return element;
    };

    const load = async (container, page = 1, focus = false) => {
        if (container.dataset.busy === '1') return;
        container.dataset.busy = '1';
        container.setAttribute('aria-busy', 'true');
        container.setAttribute('aria-live', 'polite');
        container.textContent = cmData.loading;
        try {
            const data = await request(new URLSearchParams({ action: container.dataset.action, nonce: container.dataset.nonce, campaign_id: container.dataset.campaign || '0', page: String(page) }));
            container.innerHTML = data.html; // Escaped, authenticated HTML from this plugin's endpoint.
            const nav = document.createElement('nav');
            nav.setAttribute('aria-label', cmData.page);
            if (page > 1) nav.append(button(cmData.previous, () => load(container, page - 1, true)));
            const label = document.createElement('span');
            label.textContent = ` ${cmData.page} ${page} `;
            nav.append(label);
            if (data.more) nav.append(button(cmData.next, () => load(container, page + 1, true)));
            container.append(nav);
            container.dataset.loaded = '1';
        } catch (error) {
            const message = document.createElement('p');
            message.textContent = error.message;
            container.replaceChildren(message, button(cmData.retry, () => load(container, page, true)));
        } finally {
            container.dataset.busy = '0';
            container.setAttribute('aria-busy', 'false');
            if (focus) { container.tabIndex = -1; container.focus(); }
        }
    };

    document.querySelectorAll('.cm-show-orders').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const container = document.getElementById(trigger.getAttribute('aria-controls'));
            container.hidden = !container.hidden;
            trigger.setAttribute('aria-expanded', String(!container.hidden));
            if (!container.hidden && !container.dataset.loaded) load(container);
        });
    });

    document.querySelectorAll('.cm-account').forEach(root => {
        const list = root.querySelector('.whiskey-tabs');
        if (!list) return;
        const tabs = [...list.querySelectorAll('a[href^="#"]')];
        const panels = [...root.querySelectorAll('.whiskey-tab-content')];
        list.setAttribute('role', 'tablist');
        const activate = (hash, updateHistory = false) => {
            const selected = tabs.find(tab => tab.hash === hash) || tabs[0];
            tabs.forEach(tab => {
                const active = tab === selected;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
            });
            panels.forEach(panel => {
                const active = `#${panel.id}` === selected.hash;
                panel.hidden = !active;
                panel.style.display = active ? 'block' : 'none';
                if (active) panel.querySelectorAll('.cm-remote-list').forEach(container => {
                    if (!container.dataset.loaded) load(container);
                });
            });
            if (updateHistory && location.hash !== selected.hash) history.pushState(null, '', selected.hash);
        };
        tabs.forEach((tab, index) => {
            tab.id = `cm-tab-${tab.hash.slice(1)}`;
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', tab.hash.slice(1));
            const panel = panels.find(item => `#${item.id}` === tab.hash);
            if (panel) { panel.setAttribute('role', 'tabpanel'); panel.setAttribute('aria-labelledby', tab.id); }
            tab.addEventListener('click', event => { event.preventDefault(); activate(tab.hash, true); });
            tab.addEventListener('keydown', event => {
                const keys = { ArrowRight: (index + 1) % tabs.length, ArrowLeft: (index + tabs.length - 1) % tabs.length, Home: 0, End: tabs.length - 1 };
                if (!(event.key in keys)) return;
                event.preventDefault();
                const next = tabs[keys[event.key]];
                next.focus();
                activate(next.hash, true);
            });
        });
        window.addEventListener('popstate', () => activate(location.hash));
        window.addEventListener('hashchange', () => activate(location.hash));
        activate(location.hash);
    });

    document.querySelectorAll('form[data-cm-ajax]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const submit = form.querySelector('[type="submit"]');
            const message = form.querySelector('.register-message');
            submit.disabled = true;
            message.style.display = 'block';
            message.setAttribute('role', 'status');
            message.textContent = cmData.loading;
            try {
                const body = new FormData(form);
                body.set('action', form.dataset.cmAjax);
                const result = await request(body);
                message.textContent = result.message;
                message.className = 'register-message cm-success';
                form.reset();
            } catch (error) {
                message.textContent = error.message;
                message.className = 'register-message cm-error';
            } finally {
                submit.disabled = false;
            }
        });
    });
});
