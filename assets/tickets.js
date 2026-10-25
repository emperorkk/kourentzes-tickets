/* Local-only UI. Secrets are never stored in localStorage or sent to external services. */
'use strict';
for (const root of document.querySelectorAll('[data-kt-config]')) {
    const c = JSON.parse(root.dataset.ktConfig), s = c.strings;
    const $ = selector => root.querySelector(selector);
    const result = $('.kt-result');
    let ticket = null, csrf = '', page = 1, before = 0, olderCursor = null, busy = false;
    const node = (tag, text, className) => { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; if (className) el.className = className; return el; };
    const notice = (text, focus = false) => { result.replaceChildren(node('p', text)); if (focus) result.focus(); };
    const formData = form => Object.fromEntries(new FormData(form).entries());
    const random = bytes => {
        if (!window.isSecureContext || !window.crypto?.getRandomValues) throw new Error(s.error);
        return btoa(String.fromCharCode(...crypto.getRandomValues(new Uint8Array(bytes)))).replaceAll('+', '-').replaceAll('/', '_').replaceAll('=', '');
    };
    async function api(path, method = 'GET', data) {
        const url = new URL(c.api);
        // rest_url() may use ?rest_route= on sites without rewrite rules.
        if (url.searchParams.has('rest_route')) {
            const [route, query] = path.split('?');
            url.searchParams.set('rest_route', url.searchParams.get('rest_route').replace(/\/$/, '') + '/' + route);
            for (const [key, value] of new URLSearchParams(query || '')) url.searchParams.set(key, value);
        } else {
            const [route, query] = path.split('?');
            url.pathname = url.pathname.replace(/\/$/, '') + '/' + route;
            url.search = query || '';
        }
        const headers = { 'Content-Type': 'application/json' };
        if (c.mode === 'admin') headers['X-WP-Nonce'] = c.nonce;
        if (csrf) headers['X-KT-CSRF'] = csrf;
        const response = await fetch(url, { method, credentials: 'same-origin', cache: 'no-store', referrerPolicy: 'no-referrer', headers, body: data === undefined ? undefined : JSON.stringify(data) });
        const value = await response.json();
        if (!response.ok) throw new Error(value.message || s.error);
        return value;
    }
    const work = action => async event => {
        event?.preventDefault(); if (busy) return; busy = true; root.setAttribute('aria-busy', 'true');
        try { await action(event); } catch (error) { notice(error.message || s.error, true); }
        finally { busy = false; root.removeAttribute('aria-busy'); }
    };
    function download(name, text, type) {
        const url = URL.createObjectURL(new Blob([text], { type })); const a = node('a'); a.href = url; a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
    function accessLink(receipt, secret) {
        const url = new URL(receipt.portal_url); url.hash = new URLSearchParams({ ticket: receipt.public_id, key: secret }).toString();
        result.replaceChildren(node('p', s.saved), node('p', receipt.public_id));
        if (receipt.moderation_state === 'quarantine') result.append(node('p', s.pending));
        const link = node('a', url.href); link.href = url.href; link.rel = 'noreferrer'; result.append(link);
        const copy = node('button', s.copy); copy.type = 'button'; copy.onclick = async () => { try { await navigator.clipboard.writeText(url.href); copy.textContent = s.copied; } catch { link.focus(); } };
        const save = node('button', s.download); save.type = 'button'; save.onclick = () => download('ticket-' + receipt.public_id + '.txt', s.saved + '\n\n' + url.href + '\n', 'text/plain;charset=utf-8');
        result.append(node('p'), copy, save); result.focus();
    }
    function messages(items, container, prepend = false) {
        const fragment = document.createDocumentFragment();
        for (const m of items) {
            const article = node('article', undefined, m.visibility === 'internal' ? 'kt-message kt-internal' : (m.author_type === 'staff' ? 'kt-message kt-message-staff' : 'kt-message'));
            const time = node('time', new Date(m.created_at.replace(' ', 'T') + 'Z').toLocaleString()); time.dateTime = m.created_at.replace(' ', 'T') + 'Z';
            article.append(node('strong', m.visibility === 'internal' ? s.internal : (m.author_type === 'staff' ? s.staff : s.guest)), time, node('pre', m.body)); fragment.append(article);
        }
        if (prepend) container.prepend(fragment); else container.replaceChildren(fragment);
    }
    if (c.mode === 'form') {
        const form = $('.kt-create'); if (!form) continue;
        let token = '', pending = null;
        api('form', 'POST', { project: c.project, privacy: c.privacy }).then(data => { token = data.token; form.querySelector('button[type=submit]').disabled = false; }).catch(error => notice(error.message));
        form.addEventListener('submit', work(async () => {
            if (!pending) pending = { secret: random(32), idempotency: random(24), expires: Date.now() + 900000 };
            if (Date.now() > pending.expires) { pending = null; throw new Error(s.expired); }
            notice(s.working);
            const data = { ...formData(form), acknowledge: form.elements.acknowledge.checked, project: c.project, form_token: token, secret: pending.secret, idempotency: pending.idempotency };
            const receipt = await api(c.privacy ? 'privacy/tickets' : 'tickets', 'POST', data);
            accessLink(receipt, pending.secret); pending = null; form.reset(); form.hidden = true; $('.kt-form-heading').hidden = true;
        }));
        // Pending secret exists in memory only and disappears on reload/navigation or after 15 minutes.
        window.addEventListener('pagehide', () => { pending = null; });
    }
    if (c.mode === 'portal') {
        const params = new URLSearchParams(location.hash.slice(1));
        let reference = params.get('ticket'), key = params.get('key');
        const openForm = $('.kt-open-link');
        history.replaceState(null, '', location.pathname + location.search);
        async function load(older = false) {
            const data = await api('tickets/' + reference + (older && olderCursor ? '?before=' + olderCursor : ''));
            ticket = data;
            if (!older) {
                notice(data.status === 'closed' ? s.closed : (data.moderation_state === 'quarantine' ? s.pending : s.status + ': ' + (c.labels[data.status] || data.status)));
                const summary = node('div'); const badge = node('span', c.labels[data.status] || data.status, 'kt-status'); badge.dataset.status = data.status; summary.append(badge, node('h2', data.subject), node('p', data.purge_at ? s.retention + ': ' + new Date(data.purge_at.replace(' ', 'T') + 'Z').toLocaleString() : s.noPurge));
                $('.kt-conversation').replaceChildren(summary, node('div', undefined, 'kt-messages'));
            }
            messages(data.messages, $('.kt-messages'), older); olderCursor = data.next_before;
            $('.kt-older').hidden = !olderCursor; $('.kt-reply').hidden = data.status === 'closed'; $('.kt-refresh').hidden = false; $('.kt-logout').hidden = false;
        }
        async function open() {
            try {
                const data = await api('access/exchange', 'POST', { ticket: reference, key }); csrf = data.csrf;
                openForm.hidden = true; await load();
            } finally { key = ''; }
        }
        if (!reference || !/^[a-f0-9]{32}$/.test(reference) || !key) { notice(s.needKey); }
        else { work(open)(); }
        params.delete('key');
        openForm.addEventListener('submit', work(async () => {
            const value = openForm.elements.private_link.value; openForm.reset();
            let url;
            try { url = new URL(value, location.href); } catch { throw new Error(s.invalidLink); }
            const fragment = new URLSearchParams(url.hash.slice(1));
            reference = fragment.get('ticket'); key = fragment.get('key');
            if (url.origin !== new URL(c.api).origin || !/^[a-f0-9]{32}$/.test(reference || '') || !/^[A-Za-z0-9_-]{43}$/.test(key || '')) { key = ''; throw new Error(s.invalidLink); }
            await open();
        }));
        $('.kt-reply').addEventListener('submit', work(async () => { await api('tickets/' + reference + '/messages', 'POST', { body: $('.kt-reply').elements.body.value, revision: ticket.revision }); $('.kt-reply').reset(); await load(); }));
        $('.kt-privacy-request').addEventListener('click', work(async () => { const form = $('.kt-reply'); if (!form.reportValidity()) return; await api('tickets/' + reference + '/privacy-request', 'POST', { body: form.elements.body.value, revision: ticket.revision }); form.reset(); await load(); }));
        $('.kt-refresh').addEventListener('click', work(() => load()));
        $('.kt-older').addEventListener('click', work(() => load(true)));
        $('.kt-logout').addEventListener('click', work(async () => { await api('access/logout', 'POST', { ticket: reference }); csrf = ''; openForm.hidden = false; $('.kt-conversation').replaceChildren(); $('.kt-reply').hidden = true; $('.kt-refresh').hidden = true; $('.kt-logout').hidden = true; $('.kt-older').hidden = true; notice(s.needKey); }));
    }
    if (c.mode === 'admin') {
        let filters = {}, exportBefore = 0;
        const composeTabs = [...root.querySelectorAll('[data-kt-compose]')];
        for (const tab of composeTabs) {
            tab.addEventListener('click', () => {
                const internal = tab.dataset.ktCompose === 'internal';
                $('.kt-admin-reply').hidden = internal; $('.kt-admin-note').hidden = !internal;
                for (const item of composeTabs) { item.setAttribute('aria-selected', String(item === tab)); item.tabIndex = item === tab ? 0 : -1; }
            });
            tab.addEventListener('keydown', event => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault(); const next = event.key === 'Home' ? composeTabs[0] : event.key === 'End' ? composeTabs.at(-1) : composeTabs[(composeTabs.indexOf(tab) + 1) % composeTabs.length]; next.click(); next.focus();
            });
        }

        async function list() {
            const rows = await api('admin/tickets?' + new URLSearchParams({ ...filters, page }));
            const inbox = node('div', undefined, 'kt-inbox');
            for (const t of rows) {
                const card = node('button', undefined, 'kt-inbox-ticket'); card.type = 'button';
                const info = node('span'), tags = node('span', undefined, 'kt-inbox-tags');
                const title = node('span', t.subject, 'kt-inbox-title');
                info.append(title, node('span', (c.labels[t.category] || t.category) + ' · ' + t.public_id + ' · ' + new Date(t.updated_at.replace(' ', 'T') + 'Z').toLocaleDateString(), 'kt-inbox-meta'));
                const status = node('span', c.labels[t.status] || t.status, 'kt-status'); status.dataset.status = t.status; tags.append(status);
                if (t.moderation_state === 'quarantine') tags.append(node('span', s.pending, 'kt-status'));
                if (Number(t.abuse_flag)) tags.append(node('span', s.flagged, 'kt-status'));
                card.append(info, tags); card.onclick = work(() => detail(t.id));
                if (ticket && Number(ticket.id) === Number(t.id)) card.setAttribute('aria-current', 'true');
                inbox.append(card);
            }
            if (!rows.length) { const empty = node('div', undefined, 'kt-empty'); empty.append(node('h2', s.emptyTitle), node('p', s.emptyBody)); inbox.append(empty); }
            $('.kt-admin-list').replaceChildren(inbox); $('.kt-prev').disabled = page <= 1; $('.kt-next').disabled = rows.length < 20;
        }
        async function detail(id, older = false) {
            if (!older) { before = 0; exportBefore = 0; }
            const t = await api('admin/tickets/' + id + (older && olderCursor ? '?before=' + olderCursor : '')); ticket = t;
            const ordered = [...t.messages].reverse();
            if (older) { exportBefore = olderCursor; messages(ordered, $('.kt-conversation'), true); } else { messages(ordered, $('.kt-conversation')); }
            olderCursor = t.messages.length === 20 ? Number(t.messages[t.messages.length - 1].id) : null; $('.kt-older').hidden = !olderCursor;
            $('.kt-admin-detail').hidden = false; $('.kt-subject').textContent = t.subject;
            for (const card of root.querySelectorAll('.kt-inbox-ticket')) card.removeAttribute('aria-current');
            const form = $('.kt-admin-update');
            for (const name of ['status', 'category', 'assigned_to', 'moderation_state']) if (form.elements[name]) form.elements[name].value = t[name] || (name === 'assigned_to' ? '0' : '');
            form.elements.abuse_flag.checked = Boolean(Number(t.abuse_flag)); form.elements.moderation_state.disabled = t.category === 'privacy';
            if ($('.kt-admin-privacy')) {
                const p = t.privacy || {};
                for (const name of ['request_type', 'verification_status', 'target_due_at', 'extension_reason', 'outcome', 'hold_reason', 'hold_review_at']) $('.kt-admin-privacy').elements[name].value = p[name] || (name === 'request_type' ? 'other' : name === 'verification_status' ? 'unverified' : '');
                $('.kt-received').textContent = p.received_at ? s.received + ': ' + new Date(p.received_at.replace(' ', 'T') + 'Z').toLocaleString() : '';
            }
            $('.kt-audit').textContent = JSON.stringify({ revision: t.revision, abuse_reasons: JSON.parse(t.abuse_reason_codes || '[]'), environment: JSON.parse(t.environment || '{}'), closed_at: t.closed_at, purge_at: t.purge_at, events: t.events }, null, 2);
            if (!older) $('.kt-subject').focus();
        }
        const update = async (path, method, data) => { const id = ticket.id; await api('admin/tickets/' + id + path, method, { ...data, revision: Number(ticket.revision) }); notice(s.success); await detail(id); await list(); };
        $('.kt-filters').addEventListener('submit', work(async () => { filters = formData($('.kt-filters')); page = 1; await list(); }));
        $('.kt-prev').onclick = work(async () => { page = Math.max(1, page - 1); await list(); }); $('.kt-next').onclick = work(async () => { page++; await list(); });
        $('.kt-older').onclick = work(() => detail(ticket.id, true));
        for (const [selector, visibility] of [['.kt-admin-reply', 'public'], ['.kt-admin-note', 'internal']]) {
            const form = $(selector); form.addEventListener('submit', work(async () => { await update('/messages', 'POST', { ...formData(form), visibility }); form.reset(); }));
        }
        $('.kt-admin-update').addEventListener('submit', work(async () => { const form = $('.kt-admin-update'), d = formData(form); d.abuse_flag = form.elements.abuse_flag.checked; await update('', 'PATCH', d); }));
        $('.kt-admin-privacy')?.addEventListener('submit', work(async () => { await update('', 'PATCH', { privacy: formData($('.kt-admin-privacy')) }); }));
        $('.kt-rotate')?.addEventListener('click', work(async () => { if (!confirm(s.rotateConfirm)) return; const receipt = await api('admin/tickets/' + ticket.id + '/rotate-access', 'POST', { revision: Number(ticket.revision) }); accessLink(receipt, receipt.key); await detail(ticket.id); }));
        $('.kt-export')?.addEventListener('click', work(async () => { const d = await api('admin/tickets/' + ticket.id + '/export', 'POST', { revision: Number(ticket.revision), before: exportBefore }); download('ticket-' + ticket.public_id + '-page.json', JSON.stringify(d, null, 2), 'application/json'); }));
        $('.kt-delete')?.addEventListener('click', work(async () => { if (!confirm(s.deleteConfirm)) return; await api('admin/tickets/' + ticket.id, 'DELETE', { revision: Number(ticket.revision), confirm: true }); ticket = null; $('.kt-admin-detail').hidden = true; await list(); notice(s.success); }));
        work(() => list())();
    }
}
