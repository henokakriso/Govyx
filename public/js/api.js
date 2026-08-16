'use strict';

const API = {
    async request(method, path, body, options = {}) {
        const headers = { 'Accept': 'application/json' };
        if (!options.formData) {
            headers['Content-Type'] = 'application/json';
        }
        headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]')?.content || '';

        let payload;
        if (options.formData) {
            payload = options.formData;
        } else if (body !== undefined) {
            payload = JSON.stringify(body);
        }

        const res = await fetch(path, { method, headers, body: payload, credentials: 'same-origin' });
        let data = null;
        try { data = await res.json(); } catch { data = null; }

        if (res.status === 401) {
            window.location.href = '/login';
            throw new Error('Unauthorized');
        }
        if (res.status === 419) {
            showToast('Session expired. Please reload.', 'error');
            location.reload();
            throw new Error('CSRF');
        }
        if (!res.ok) {
            const msg = data?.message || 'Request failed (' + res.status + ')';
            const err = new Error(msg);
            err.details = data?.details;
            err.status = res.status;
            throw err;
        }
        return data;
    },

    get(path) { return this.request('GET', path); },
    post(path, body, options) { return this.request('POST', path, body, options); },
    put(path, body) { return this.request('PUT', path, body); },
    del(path) { return this.request('DELETE', path); },
};

function showToast(message, type = 'info', duration = 3200) {
    const container = document.getElementById('toast-container');
    if (!container) { alert(message); return; }
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = message;
    container.appendChild(el);
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transition = 'opacity .3s';
        setTimeout(() => el.remove(), 320);
    }, duration);
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString();
}

function pillStatus(s) { return `<span class="pill pill-${esc(s)}">${esc(s.replace(/_/g, ' '))}</span>`; }
function pillPriority(p) { return `<span class="pill pill-${esc(p)}">${esc(p)}</span>`; }
function printBtn(title) { return `<button class="btn btn-sm btn-ghost" data-print="${esc(title)}">🖨 Print</button>`; }

function modal(title, bodyHtml, footHtml = '') {
    const root = document.getElementById('modal-root');
    root.innerHTML = `
        <div class="modal-backdrop" id="modal-backdrop">
            <div class="modal">
                <div class="modal-head">
                    <h2>${esc(title)}</h2>
                    <button class="modal-close" data-close aria-label="Close">×</button>
                </div>
                <div class="modal-body">${bodyHtml}</div>
                ${footHtml ? `<div class="modal-foot">${footHtml}</div>` : ''}
            </div>
        </div>`;
    root.querySelector('[data-close]').onclick = () => { root.innerHTML = ''; };
    root.querySelector('#modal-backdrop').onclick = (e) => {
        if (e.target.id === 'modal-backdrop') root.innerHTML = '';
    };
    return root.querySelector('.modal');
}

function fieldForm(name, label, type = 'text', value = '', required = false) {
    return `
        <div class="field">
            <label for="f-${name}">${esc(label)}</label>
            <input type="${type}" id="f-${name}" name="${name}" value="${esc(value)}" ${required ? 'required' : ''}>
        </div>`;
}

/* ---------- Document printing ---------- */

/** Emits a printable document in a hidden iframe; user prints via the browser dialog (CSP-safe). */
function printDoc(title, bodyHtml) {
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;top:0;left:-10000px;width:0;height:0;border:0;visibility:hidden;';
    frame.onload = () => {
        setTimeout(() => {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                showToast('Print failed: ' + e.message, 'error');
            }
        }, 250);
    };
    const meta = `
        <div class="print-meta">
            <div>
                <div class="doc-title">${esc(title)}</div>
                <div class="doc-sub">GOVYX — AI Governance Brain · Project ARWE</div>
            </div>
            <div class="doc-sub">Printed ${esc(new Date().toLocaleString())}</div>
        </div>`;
    frame.srcdoc = `<!DOCTYPE html>
<html><head><meta charset="utf-8">
<title>${esc(title)} — GOVYX</title>
<link rel="stylesheet" href="/css/govyx.css">
<link rel="stylesheet" href="/css/print.css">
</head><body>${meta}${bodyHtml}</body></html>`;
    document.body.appendChild(frame);
}

// Binds every [data-print] element to a delegated handler.
function bindPrintButtons(root) {
    (root || document).querySelectorAll('[data-print]').forEach((el) => {
        el.onclick = () => {
            const title = el.dataset.print || App.viewTitle || 'GOVYX Document';
            const source = document.getElementById('view');
            printDoc(title, source ? source.innerHTML : '<p>Nothing to print.</p>');
        };
    });
}