'use strict';

const App = {
    user: null,
    view: 'dashboard',
    nav: [],

    init: async function () {
        await this.loadUser();
        this.buildNav();
        document.getElementById('logout-link').addEventListener('click', () => this.logout());
        document.getElementById('menu-toggle').addEventListener('click', () =>
            document.getElementById('sidebar').classList.toggle('open'));
        document.getElementById('run-rankor-btn').addEventListener('click', () => this.runRankor());
        document.getElementById('notification-bell').addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleNotifications();
        });
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('notification-panel');
            if (!panel.classList.contains('hidden') && !panel.contains(e.target) && e.target.id !== 'notification-bell') {
                panel.classList.add('hidden');
            }
        });
        document.getElementById('notif-read-all').addEventListener('click', async () => {
            await API.put('/api/v1/notifications/read-all');
            this.loadNotifications();
        });

        window.addEventListener('hashchange', () => this.route());
        this.route();
        this.loadNotifications();
        setInterval(() => this.loadNotifications(), 60000);
    },

    loadUser: async function () {
        const data = await API.get('/api/v1/auth/me');
        this.user = data.user;
        const initials = this.user.full_name.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase();
        document.getElementById('avatar').textContent = initials;
        document.getElementById('user-name').textContent = this.user.full_name;
        document.getElementById('user-role').textContent = this.user.role_name + ' · ' + this.user.organization_name;
    },

    hasPermission: function (perm) {
        return this.user.role_code === 'super_admin' || !!this.user.permissions?.includes(perm);
    },

    buildNav: async function () {
        const perms = await this.fetchPermissions();
        this.user.permissions = perms;
        const items = {
            dashboard: { label: 'Dashboard', icon: '◧' },
            tasks:     { label: 'Tasks', icon: '☑' },
            kpis:      { label: 'KPIs', icon: '◎' },
            projects:  { label: 'Projects', icon: '⬡' },
            performance: { label: 'Performance', icon: '▲' },
            alerts:    { label: 'Risk Alerts', icon: '⚠' },
            rankor:    { label: 'Rankor', icon: '◈' },
            reports:   { label: 'Reports', icon: '▤' },
            audit:     { label: 'Audit Log', icon: '◍' },
            users:     { label: 'Users', icon: '👤' },
            roles:     { label: 'Roles & Permissions', icon: '🗝' },
            settings:  { label: 'System Settings', icon: '⚙' },
            organizations: { label: 'Organizations', icon: '🏛' },
            departments:   { label: 'Departments', icon: '▦' },
            officials: { label: 'Officials', icon: '🪪' },
        };
        const links = document.getElementById('nav-links');
        links.innerHTML = '';
        for (const [key, spec] of Object.entries(items)) {
            if (!this.canView(key)) continue;
            const a = document.createElement('a');
            a.className = 'nav-link';
            a.dataset.view = key;
            a.innerHTML = `${spec.icon} <span>${spec.label}</span>`;
            a.onclick = () => { location.hash = '#/' + key; };
            links.appendChild(a);
        }
    },

    fetchPermissions: async function () {
        const r = await fetch('/api/v1/auth/me', { credentials: 'same-origin' });
        const d = await r.json();
        const perms = await fetch('/api/v1/users', { credentials: 'same-origin' }).then(r2 => r2.status === 403 ? null : r2.json()).catch(() => null);
        return perms ? (perms.user?.permissions || []) : [];
    },

    canView: function (key) {
        switch (key) {
            case 'dashboard': return true;
            case 'tasks':     return this.userHas('VIEW_TASK', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager', 'official', 'auditor', 'analyst', 'viewer']);
            case 'kpis':      return this.userHas('VIEW_KPI', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager', 'official', 'auditor', 'analyst', 'viewer']);
            case 'projects':  return this.userHas('VIEW_PROJECT', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager', 'official', 'auditor', 'analyst', 'viewer']);
            case 'performance': return this.userHas('VIEW_PERFORMANCE', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager', 'official', 'analyst', 'auditor']);
            case 'alerts':    return this.userHas('VIEW_RISK', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager', 'official', 'analyst', 'auditor', 'viewer']);
            case 'rankor':    return this.userHas('VIEW_RANKOR', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager', 'official', 'analyst', 'auditor', 'viewer']);
            case 'reports':   return this.userHas('VIEW_REPORT', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager', 'official', 'analyst', 'auditor', 'viewer']);
            case 'audit':     return this.userHas('VIEW_AUDIT', ['super_admin', 'gov_admin', 'auditor']);
            case 'users':     return this.userHas('MANAGE_USERS', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin']);
            case 'roles':     return this.userHas('MANAGE_ROLES', ['super_admin']);
            case 'settings':  return this.userHas('MANAGE_SETTINGS', ['super_admin']);
            case 'organizations': return this.userHas('MANAGE_ORGANIZATIONS', ['super_admin', 'gov_admin']);
            case 'departments': return this.userHas('MANAGE_DEPARTMENTS', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin']);
            case 'officials': return this.userHas('MANAGE_USERS', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin']);
        }
        return false;
    },

    userHas(perm, roles) {
        return roles.includes(this.user.role_code);
    },

    route: async function () {
        const hash = location.hash.replace(/^#\//, '') || 'dashboard';
        const parts = hash.split('/');
        const view = parts[0];
        if (!this.canView(view)) { this.view = 'dashboard'; this.showNotFound(); return; }
        this.view = view;
        document.querySelectorAll('.nav-link').forEach(l => l.classList.toggle('active', l.dataset.view === view));
        const titles = { dashboard: 'Executive Dashboard', tasks: 'Task Management', kpis: 'KPI Management', projects: 'Projects', performance: 'Performance & Rankings', alerts: 'Risk Alerts', rankor: 'Rankor Intelligence', reports: 'Reports', audit: 'Audit Log', users: 'User Management', roles: 'Roles & Permissions', settings: 'System Settings', organizations: 'Organizations', departments: 'Departments', officials: 'Officials' };
        document.getElementById('page-title').textContent = titles[view] || 'GOVYX';
        const viewEl = document.getElementById('view');
        viewEl.innerHTML = '<div class="empty-state"><div class="big">⏳</div>Loading…</div>';
        try {
            if (view === 'tasks' && parts[1]) {
                await Views.taskDetail(viewEl, parts[1]);
            } else {
                await Views[view](viewEl);
            }
        } catch (e) {
            viewEl.innerHTML = `<div class="card"><h3>Error</h3><p class="muted">${esc(e.message)}</p></div>`;
        }
    },

    showNotFound: function () {
        const viewEl = document.getElementById('view');
        viewEl.innerHTML = '<div class="card"><h3>Access Denied</h3><p class="muted">You do not have permission to view this module.</p></div>';
    },

    logout: async function () {
        try { await API.post('/api/v1/auth/logout'); } catch {}
        window.location.href = '/login';
    },

    runRankor: async function () {
        showToast('Rankor analysis started…');
        try {
            const data = await API.post('/api/v1/rankor/run');
            showToast(`Analysis complete. ${data.alerts_created} new alert(s) created.`, 'success');
            if (this.view === 'dashboard') location.hash = '#/dashboard';
            this.refreshView();
        } catch (e) {
            showToast(e.message, 'error');
        }
    },

    refreshView: function () { location.hash = '#/' + this.view + '?t=' + Date.now(); },

    loadNotifications: async function () {
        try {
            const data = await API.get('/api/v1/notifications');
            const count = document.getElementById('notification-count');
            count.textContent = data.unread;
            count.classList.toggle('hidden', data.unread === 0);
            const list = document.getElementById('notification-list');
            list.innerHTML = data.notifications.length
                ? data.notifications.map(n => `
                    <div class="notif-item ${n.read_at ? '' : 'unread'}">
                        <strong>${esc(n.title)}</strong>
                        <div class="muted">${esc(n.message || '')}</div>
                        <div class="notif-time">${fmtDate(n.created_at)}</div>
                    </div>`).join('')
                : '<div class="empty-state">No notifications</div>';
        } catch {}
    },

    toggleNotifications: function () {
        document.getElementById('notification-panel').classList.toggle('hidden');
        this.loadNotifications();
    },
};

// ---------------------------------------------------------------------------
// Views
// ---------------------------------------------------------------------------

const Views = {

    dashboard: async function (el) {
        const [analytics, alerts, tasks, kpi] = await Promise.all([
            API.get('/api/v1/analytics/overview').catch(() => null),
            API.get('/api/v1/alerts').catch(() => null),
            API.get('/api/v1/tasks?overdue=1').catch(() => null),
            API.get('/api/v1/kpis').catch(() => null),
        ]);
        const a = analytics?.analytics || {};
        const openAlerts = (alerts?.alerts || []).filter(x => x.status === 'open');
        const role = App.user.role_code;
        const isOfficial = ['official'].includes(role);

        el.innerHTML = `
            <div class="grid grid-4 mb">
                <div class="stat"><div class="stat-label">Open Tasks</div><div class="stat-value">${(a.task_statuses || []).find(s => s.status === 'in_progress')?.total ?? 0}</div></div>
                <div class="stat"><div class="stat-label">Completed</div><div class="stat-value">${(a.task_statuses || []).filter(s => ['completed', 'reviewed'].includes(s.status)).reduce((n, s) => n + Number(s.total), 0)}</div></div>
                <div class="stat"><div class="stat-label">Completion Rate</div><div class="stat-value">${a.completion_rate ?? 0}%</div></div>
                <div class="stat"><div class="stat-label">Avg KPI Achievement</div><div class="stat-value">${a.kpi_average ?? 0}%</div></div>
            </div>

            ${isOfficial ? `<div class="card mb">
                <h3>Your Tasks</h3>
                <div class="table-wrap"><table id="dash-my-tasks"><tbody></tbody></table></div>
            </div>` : ''}

            <div class="grid grid-2 mb">
                <div class="card">
                    <h3>⚠ Open Risk Alerts</h3>
                    ${openAlerts.length
                        ? `<div class="table-wrap"><table><thead><tr><th>Severity</th><th>Title</th><th>Status</th></tr></thead><tbody>
                            ${openAlerts.slice(0, 6).map(x => `<tr>
                                <td>${pillStatus('high-s')}</td>
                                <td>${esc(x.title)}<div class="muted" style="font-size:11px">${esc(x.organization_name)}</div></td>
                                <td>${pillStatus(x.status)}</td>
                            </tr>`).join('')}
                        </tbody></table></div>`
                        : '<p class="muted">No open risk alerts.</p>'}
                </div>
                <div class="card">
                    <h3>📊 KPI Snapshot</h3>
                    ${(kpi?.kpis || []).length
                        ? `<div class="grid grid-kpis">${kpi.kpis.slice(0, 6).map(k => `
                            <div class="card" style="box-shadow:none;border:1px solid var(--line)">
                                <div class="eyebrow">${esc(k.code)}</div>
                                <div class="kpi-value ${Number(k.achievement) < Number(k.threshold || 70) ? 'danger' : ''}" style="color:${Number(k.achievement) < Number(k.threshold || 70) ? 'var(--red)' : 'var(--green)'}">${k.achievement ?? '—'}%</div>
                                <div class="kpi-target">target ${esc(k.target)} ${esc(k.unit || '')} · actual ${esc(k.actual)}</div>
                            </div>`).join('')}</div>`
                        : '<p class="muted">No KPIs in scope.</p>'}
                </div>
            </div>
        `;

        if (isOfficial) {
            const myTasks = await API.get('/api/v1/tasks?mine=1&limit=8').catch(() => null);
            document.querySelector('#dash-my-tasks tbody').innerHTML = (myTasks?.tasks || []).length
                ? myTasks.tasks.map(t => `<tr>
                    <td><a href="#/tasks/${t.id}"><strong>${esc(t.code)}</strong></a></td>
                    <td>${esc(t.title)}</td>
                    <td>${pillStatus(t.status)}</td>
                    <td>${fmtDate(t.deadline)}</td>
                </tr>`).join('')
                : '<tr><td colspan="4" class="muted">No tasks assigned.</td></tr>';
        }
    },

    tasks: async function (el) {
        const data = await API.get('/api/v1/tasks');
        const t = data.tasks || [];
        const canCreate = App.userHas('CREATE_TASK', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager', 'official']);
        el.innerHTML = `
            <div class="toolbar">
                ${canCreate ? `<button class="btn" id="new-task-btn">+ New Task</button>` : ''}
                <select id="task-filter-status" class="btn-ghost" style="padding:7px">
                    <option value="">All statuses</option>
                    ${data.statuses.map(s => `<option value="${s}">${s.replace(/_/g, ' ')}</option>`).join('')}
                </select>
                <input id="task-search" placeholder="Search tasks…" style="padding:7px;border:1px solid var(--line);border-radius:7px">
                <div class="spacer"></div>
                ${printBtn('Task Management Report')}
                <span class="muted">${t.length} task(s)</span>
            </div>
            <div class="card"><div class="table-wrap">
                <table><thead><tr>
                    <th>Code</th><th>Title</th><th>Status</th><th>Priority</th><th>Assignee</th>
                    <th>Deadline</th><th>Progress</th><th></th>
                </tr></thead><tbody id="task-rows"></tbody></table>
            </div></div>
        `;

        document.getElementById('task-filter-status').onchange = (e) => {
            const url = e.target.value ? '/api/v1/tasks?status=' + e.target.value : '/api/v1/tasks';
            API.get(url).then(d => renderRows(d.tasks || []));
        };
        const searchEl = document.getElementById('task-search');
        searchEl.oninput = debounce(() => {
            renderRows(t.filter(x => (x.title + ' ' + x.code + ' ' + x.assignee_name).toLowerCase().includes(searchEl.value.toLowerCase())));
        }, 200);
        renderRows(t);
        bindPrintButtons(el);

        if (canCreate) {
            document.getElementById('new-task-btn').onclick = () => this.taskModal();
        }
    },

    taskModal: async function (task = null) {
        const [orgs, users] = await Promise.all([
            API.get('/api/v1/organizations').catch(() => null),
            API.get('/api/v1/users').catch(() => null),
        ]);
        const orgOptions = (orgs?.organizations || []).map(o => `<option value="${o.id}">${esc(o.name)}</option>`).join('');
        const userList = users?.users || [];
        const userOptions = userList.map(u => `<option value="${u.id}">${esc(u.full_name)} ${u.username ? '(' + esc(u.username) + ')' : ''}</option>`).join('');
        const title = task ? 'Edit Task' : 'Create Task';
        const foot = `<button class="btn-ghost btn" data-close>Cancel</button>
            <button class="btn" id="task-save">${task ? 'Save' : 'Create'}</button>`;

        const m = modal(title, `
            <div class="field"><label>Code</label><input value="${esc(task?.code || 'auto')}" disabled></div>
            <div class="field"><label for="f-title">Title *</label><input id="f-title" value="${esc(task?.title || '')}"></div>
            <div class="field"><label for="f-desc">Description</label><textarea id="f-desc" rows="3">${esc(task?.description || '')}</textarea></div>
            <div class="grid grid-2">
                <div class="field"><label for="f-org">Organization *</label><select id="f-org">${orgOptions || '<option value="">None in scope</option>'}</select></div>
                <div class="field"><label for="f-priority">Priority</label>
                    <select id="f-priority">${['low', 'medium', 'high', 'critical'].map(p => `<option ${task?.priority === p ? 'selected' : ''}>${p}</option>`).join('')}</select>
                </div>
            </div>
            ${task ? '' : `<div class="field"><label for="f-assignee">Assignee</label><select id="f-assignee"><option value="">— unassigned —</option>${userOptions}</select></div>`}
            <div class="grid grid-2">
                <div class="field"><label for="f-start">Start date</label><input type="date" id="f-start" value="${esc(task?.start_date || '')}"></div>
                <div class="field"><label for="f-deadline">Deadline</label><input type="date" id="f-deadline" value="${esc(task?.deadline || '')}"></div>
            </div>
            ${task ? `<div class="field"><label for="f-progress">Progress: <span id="progress-label">${task?.progress ?? 0}%</span></label>
                <input type="range" id="f-progress" min="0" max="100" value="${task?.progress ?? 0}" oninput="document.getElementById('progress-label').textContent=this.value+'%'"></div>` : ''}
        `, foot);

        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('task-save').onclick = async () => {
            const payload = {
                title: document.getElementById('f-title').value,
                description: document.getElementById('f-desc').value,
                priority: document.getElementById('f-priority').value,
                organization_id: document.getElementById('f-org').value,
                start_date: document.getElementById('f-start').value || null,
                deadline: document.getElementById('f-deadline').value || null,
            };
            if (task) {
                payload.progress = document.getElementById('f-progress').value;
                await API.put(`/api/v1/tasks/${task.id}`, payload);
                showToast('Task updated', 'success');
            } else {
                payload.assigned_to = document.getElementById('f-assignee').value || null;
                await API.post('/api/v1/tasks', payload);
                showToast('Task created', 'success');
            }
            document.getElementById('modal-root').innerHTML = '';
            App.refreshView();
        };
    },

    taskDetail: async function (el, id) {
        const data = await API.get('/api/v1/tasks/' + id);
        const t = data.task;
        const role = App.user.role_code;
        const isAssignee = Number(t.assigned_to) === Number(App.user.id);
        const isManager = ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager'].includes(role);

        el.innerHTML = `
            <div class="toolbar mb">
                ${printBtn('Task ' + t.code + ' — ' + t.title)}
                <div class="spacer"></div>
            </div>
            <div class="grid grid-2">
                <div class="card">
                    <div class="eyebrow">${esc(t.code)}</div>
                    <h2>${esc(t.title)}</h2>
                    <p class="muted mt">${esc(t.description || 'No description')}</p>
                    <div class="mt">
                        <span>${pillStatus(t.status)}</span> ${pillPriority(t.priority)}
                        <span class="pill pill-info">progress ${t.progress}%</span>
                    </div>
                    <div class="grid grid-2 mt">
                        <div><div class="eyebrow">Organization</div>${esc(t.organization_name)}</div>
                        <div><div class="eyebrow">Department</div>${esc(t.department_name || '—')}</div>
                        <div><div class="eyebrow">Assignee</div>${esc(t.assignee_name)}</div>
                        <div><div class="eyebrow">Created by</div>${esc(t.creator_name)}</div>
                        <div><div class="eyebrow">Start</div>${fmtDate(t.start_date)}</div>
                        <div><div class="eyebrow">Deadline</div>${fmtDate(t.deadline)}</div>
                    </div>
                    ${t.approval_by ? `<div class="mt"><div class="eyebrow">Approved by ${esc(t.approver_name)}</div>${esc(t.approval_note || '')}</div>` : ''}
                    ${this.taskActions(t, isAssignee, isManager)}
                </div>
                <div class="card">
                    <h3>🚦 Task History (audit trail)</h3>
                    <div style="max-height:380px;overflow-y:auto">
                    ${(t.transitions || []).map(tr => `<div style="display:flex;gap:10px;padding:6px 0;border-bottom:1px solid var(--line)">
                        <strong>${esc(tr.from_status || '—')}</strong> → <strong>${esc(tr.to_status)}</strong>
                        <div class="muted"><div>by ${esc(tr.actor_name)} · ${fmtDate(tr.created_at)}</div>${esc(tr.note || '')}</div>
                    </div>`).join('') || '<p class="muted">No transitions.</p>'}
                    </div>
                </div>
            </div>
            <div class="card mt">
                <h3>📎 Evidence</h3>
                <div id="evidence-list" class="mt"></div>
            </div>
        `;

        const loadEvidence = async () => {
            try {
                const ev = await API.get(`/api/v1/tasks/${t.id}/evidence`);
                document.getElementById('evidence-list').innerHTML = (ev.evidence || []).length
                    ? (ev.evidence || []).map(e => `<div style="display:flex;gap:8px;padding:6px 0;align-items:center">
                        <span>📄 ${esc(e.file_name)}</span>
                        <span class="muted" style="font-size:11px">${(e.file_size / 1024).toFixed(1)} KB · v${e.version} · ${esc(e.uploaded_by_name)}</span>
                        <span class="muted" style="font-size:10px;font-family:monospace">${esc(e.checksum?.slice(0, 12))}…</span>
                      </div>`).join('')
                    : '<p class="muted">No evidence uploaded.</p>';
            } catch { }
        };
        loadEvidence();
        bindPrintButtons(el);
    },

    taskActions: function (t, isAssignee, isManager) {
        let actions = '';
        if (isAssignee && ['created', 'assigned'].includes(t.status)) {
            actions += `<button class="btn btn-sm" data-action="in_progress">Start work</button> `;
        }
        if (isAssignee && ['in_progress', 'created', 'assigned'].includes(t.status)) {
            actions += `<button class="btn btn-sm" data-action="submitted">Submit for review</button> `;
        }
        if (isManager && t.status === 'submitted') {
            actions += `<button class="btn btn-sm btn-ghost" data-action="returned">Return</button>
                         <button class="btn btn-sm btn-danger" data-action="rejected">Reject</button>
                         <button class="btn btn-sm" data-action="completed">Approve / Complete</button> `;
        }
        if (isManager && t.status === 'in_progress') {
            actions += `<button class="btn btn-sm btn-ghost" data-action="returned">Return to created</button> `;
        }
        if (actions) {
            const me = this;
            setTimeout(() => {
                document.querySelectorAll('[data-action]').forEach(b => b.onclick = async () => {
                    const note = prompt('Note (optional):');
                    const action = b.dataset.action;
                    const payload = { status: action, note: note || null };
                    let url = `/api/v1/tasks/${t.id}/status`;
                    if (action === 'completed') {
                        payload.status = undefined;
                        delete payload.status;
                        await API.post(`/api/v1/tasks/${t.id}/approve`, { note: note || null });
                    } else {
                        await API.post(url, payload);
                    }
                    showToast('Task updated', 'success');
                    location.hash = '#/tasks/' + t.id;
                });
            }, 0);
            return `<div class="mt">${actions}</div>`;
        }
        return '';
    },

    kpis: async function (el) {
        const data = await API.get('/api/v1/kpis');
        const kpis = data.kpis || [];
        const canCreate = App.userHas('CREATE_KPI', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin']);
        el.innerHTML = `
            <div class="toolbar">
                ${canCreate ? '<button class="btn" id="new-kpi-btn">+ New KPI</button>' : ''}
                <div class="spacer"></div>
                ${printBtn('KPI Performance Report')}
                <span class="muted">${kpis.length} KPI(s)</span>
            </div>
<div class="grid grid-kpis">
                ${kpis.map(k => `
                    <div class="card">
                        <div class="eyebrow">${esc(k.code)} <span class="pill pill-info">${esc(k.period || '—')}</span></div>
                        <h3>${esc(k.name)}</h3>
                        <p class="muted" style="font-size:12px">${esc(k.department_name || k.organization_name)}</p>
                        <div class="score-ring mt">
                            <div class="number" style="color:${Number(k.achievement) < Number(k.threshold || 70) ? 'var(--red)' : 'var(--green)'}">${k.achievement ?? '—'}%</div>
                            <div>
                                <div class="kpi-target">target: ${esc(k.target)} ${esc(k.unit || '')}</div>
                                <div class="kpi-target">actual: ${esc(k.actual)}</div>
                            </div>
                        </div>
                        <div class="progress mt ${Number(k.achievement) < Number(k.threshold || 70) ? 'danger' : 'good'}"><div style="width:${Math.min(100, k.achievement || 0)}%"></div></div>
                        <div class="kpi-target mt"><b>Method:</b> ${esc(k.measurement_method || '—')}</div>
                        <div class="kpi-target"><b>Weight:</b> ${esc(k.weight)} · <b>Threshold:</b> ${esc(k.threshold)}%</div>
                        <div class="mt" style="display:flex;gap:8px;flex-wrap:wrap">
                            ${canCreate ? `<button class="btn btn-sm btn-ghost" data-measure="${k.id}" data-name="${esc(k.code)}">Record</button>
                            <button class="btn btn-sm btn-ghost" data-kpi-edit="${k.id}">Edit</button>
                            <button class="btn btn-sm btn-danger" data-kpi-archive="${k.id}">${k.status === 'archived' ? 'Delete' : 'Archive'}</button>` : ''}
                        </div>
                    </div>`).join('')}
            </div>
        `;
        bindPrintButtons(el);
        if (canCreate) {
            document.getElementById('new-kpi-btn').onclick = () => this.kpiModal();
            document.querySelectorAll('[data-measure]').forEach(b => b.onclick = () => this.kpiMeasureModal(b.dataset.measure, b.dataset.name));
            document.querySelectorAll('[data-kpi-edit]').forEach(b => b.onclick = () => this.kpiModal(b.dataset.kpiEdit));
            document.querySelectorAll('[data-kpi-archive]').forEach(b => b.onclick = async () => {
                if (!confirm('Archive or delete this KPI?')) return;
                try {
                    await API.del('/api/v1/kpis/' + b.dataset.kpiArchive);
                    showToast('KPI updated', 'success');
                    App.refreshView();
                } catch (e) { showToast(e.message, 'error'); }
            });
        }
    },

    kpiModal: async function (kpiId) {
        const [orgs, current] = await Promise.all([
            API.get('/api/v1/organizations').catch(() => null),
            kpiId ? API.get('/api/v1/kpis/' + kpiId).catch(() => null) : Promise.resolve(null),
        ]);
        const k = current?.kpi;
        const orgOptions = (orgs?.organizations || []).map(o => `<option value="${o.id}" ${k && String(k.organization_id) === String(o.id) ? 'selected' : ''}>${esc(o.name)}</option>`).join('');
        const m = modal(kpiId ? 'Edit KPI — ' + (k?.code || '') : 'Create KPI', `
            <div class="grid grid-2">
                <div class="field"><label>Code *</label><input id="k-code" ${kpiId ? 'disabled' : ''} value="${esc(k?.code || '')}" placeholder="KPI-FIN-004"></div>
                <div class="field"><label>Name *</label><input id="k-name" value="${esc(k?.name || '')}"></div>
            </div>
            <div class="field"><label>Description</label><textarea id="k-desc" rows="2">${esc(k?.description || '')}</textarea></div>
            <div class="field"><label>Organization *</label><select id="k-org">${orgOptions || '<option value="">None in scope</option>'}</select></div>
            <div class="grid grid-2">
                <div class="field"><label>Target *</label><input type="number" id="k-target" step="any" value="${esc(k?.target ?? 100)}"></div>
                <div class="field"><label>Actual</label><input type="number" id="k-actual" step="any" value="${esc(k?.actual ?? 0)}"></div>
                <div class="field"><label>Unit</label><input id="k-unit" value="${esc(k?.unit || '')}" placeholder="%"></div>
                <div class="field"><label>Period</label><input id="k-period" value="${esc(k?.period || '')}" placeholder="2026-Q3"></div>
                <div class="field"><label>Weight</label><input type="number" id="k-weight" step="0.1" value="${esc(k?.weight ?? 1.0)}"></div>
                <div class="field"><label>Threshold %</label><input type="number" id="k-threshold" value="${esc(k?.threshold ?? 70)}"></div>
            </div>
            <div class="field"><label>Measurement method</label><input id="k-method" value="${esc(k?.measurement_method || '')}" placeholder="e.g. executed / allocated * 100"></div>
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="k-save">' + (kpiId ? 'Save' : 'Create KPI') + '</button>');

        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('k-save').onclick = async () => {
            const payload = {
                name: document.getElementById('k-name').value,
                description: document.getElementById('k-desc').value,
                organization_id: document.getElementById('k-org').value,
                target: document.getElementById('k-target').value,
                actual: document.getElementById('k-actual').value,
                unit: document.getElementById('k-unit').value,
                period: document.getElementById('k-period').value,
                weight: document.getElementById('k-weight').value,
                threshold: document.getElementById('k-threshold').value,
                measurement_method: document.getElementById('k-method').value,
            };
            if (kpiId) {
                await API.put('/api/v1/kpis/' + kpiId, payload);
            } else {
                payload.code = document.getElementById('k-code').value;
                await API.post('/api/v1/kpis', payload);
            }
            document.getElementById('modal-root').innerHTML = '';
            showToast(kpiId ? 'KPI updated' : 'KPI created', 'success');
            App.refreshView();
        };
    },

    kpiModal: async function () {
        const orgs = await API.get('/api/v1/organizations').catch(() => null);
        const orgOptions = (orgs?.organizations || []).map(o => `<option value="${o.id}">${esc(o.name)}</option>`).join('');
        const m = modal('Create KPI', `
            <div class="grid grid-2">
                <div class="field"><label for="k-code">Code *</label><input id="k-code" placeholder="KPI-FIN-004"></div>
                <div class="field"><label for="k-name">Name *</label><input id="k-name"></div>
            </div>
            <div class="field"><label for="k-desc">Description</label><textarea id="k-desc" rows="2"></textarea></div>
            <div class="field"><label for="k-org">Organization *</label><select id="k-org">${orgOptions}</select></div>
            <div class="grid grid-2">
                <div class="field"><label for="k-target">Target *</label><input type="number" id="k-target" step="any" value="100"></div>
                <div class="field"><label for="k-actual">Actual</label><input type="number" id="k-actual" step="any" value="0"></div>
                <div class="field"><label for="k-unit">Unit</label><input id="k-unit" placeholder="%"></div>
                <div class="field"><label for="k-period">Period</label><input id="k-period" placeholder="2026-Q3"></div>
                <div class="field"><label for="k-weight">Weight</label><input type="number" id="k-weight" step="0.1" value="1.0"></div>
                <div class="field"><label for="k-threshold">Threshold %</label><input type="number" id="k-threshold" value="70"></div>
            </div>
            <div class="field"><label for="k-method">Measurement method</label><input id="k-method" placeholder="e.g. executed / allocated * 100"></div>
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="k-save">Create KPI</button>');

        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('k-save').onclick = async () => {
            await API.post('/api/v1/kpis', {
                code: document.getElementById('k-code').value,
                name: document.getElementById('k-name').value,
                description: document.getElementById('k-desc').value,
                organization_id: document.getElementById('k-org').value,
                target: document.getElementById('k-target').value,
                actual: document.getElementById('k-actual').value,
                unit: document.getElementById('k-unit').value,
                period: document.getElementById('k-period').value,
                weight: document.getElementById('k-weight').value,
                threshold: document.getElementById('k-threshold').value,
                measurement_method: document.getElementById('k-method').value,
            });
            document.getElementById('modal-root').innerHTML = '';
            showToast('KPI created', 'success');
            App.refreshView();
        };
    },

    kpiMeasureModal: function (id, name) {
        const m = modal('Record measurement — ' + name, `
            <div class="grid grid-2">
                <div class="field"><label>Period</label><input id="km-period" value="${new Date().toISOString().slice(0, 7)}"></div>
                <div class="field"><label>Target</label><input id="km-target" type="number" step="any" value="100"></div>
                <div class="field"><label>Actual</label><input id="km-actual" type="number" step="any"></div>
            </div>
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="km-save">Record</button>');
        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('km-save').onclick = async () => {
            await API.post(`/api/v1/kpis/${id}/measurements`, {
                period: document.getElementById('km-period').value,
                target: document.getElementById('km-target').value,
                actual: document.getElementById('km-actual').value,
            });
            document.getElementById('modal-root').innerHTML = '';
            showToast('Measurement recorded — achievement = ' + Math.round(document.getElementById('km-actual').value / document.getElementById('km-target').value * 100) + '%', 'success');
            App.refreshView();
        };
    },

    projects: async function (el) {
        const data = await API.get('/api/v1/projects');
        const projects = data.projects || [];
        const canCreate = App.userHas('CREATE_PROJECT', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin']);
        el.innerHTML = `
            <div class="toolbar">
                ${canCreate ? '<button class="btn" id="new-project-btn">+ New Project</button>' : ''}
                <div class="spacer"></div>
                ${printBtn('Projects Report')}
            </div>
            <div class="grid grid-2">
                ${projects.map(p => `
                    <div class="card">
                        <div class="eyebrow">${esc(p.code)} <span class="pill pill-${esc(p.status)}">${esc(p.status.replace(/_/g, ' '))}</span></div>
                        <h3>${esc(p.name)}</h3>
                        <p class="muted" style="font-size:12px">${esc(p.organization_name)}${p.department_name ? ' · ' + esc(p.department_name) : ''}</p>
                        <div class="mt"><div class="kpi-target">Progress</div>
                            <div class="progress ${p.progress >= 80 ? 'good' : ''}"><div style="width:${p.progress}%"></div></div>
                        </div>
                        <div class="grid grid-2 mt">
                            <div class="kpi-target">Start: ${fmtDate(p.start_date)}</div>
                            <div class="kpi-target">End: ${fmtDate(p.end_date)}</div>
                        </div>
                        ${App.userHas('EDIT_PROJECT', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin']) ? `
                        <div class="mt" style="display:flex;gap:8px">
                            <button class="btn btn-sm btn-ghost" data-proj-edit="${p.id}">Edit</button>
                            <button class="btn btn-sm btn-danger" data-proj-archive="${p.id}">${p.status === 'archived' ? 'Delete' : 'Archive'}</button>
                        </div>` : ''}
                    </div>`).join('')}
            </div>`;
        bindPrintButtons(el);
        if (canCreate) {
            document.getElementById('new-project-btn').onclick = () => this.projectModal();
        }
        document.querySelectorAll('[data-proj-edit]').forEach(b => b.onclick = () => this.projectModal(b.dataset.projEdit));
        document.querySelectorAll('[data-proj-archive]').forEach(b => b.onclick = async () => {
            if (!confirm('Archive or delete this project?')) return;
            try {
                await API.del('/api/v1/projects/' + b.dataset.projArchive);
                showToast('Project updated', 'success');
                App.refreshView();
            } catch (e) { showToast(e.message, 'error'); }
        });
    },

    projectModal: async function (projectId) {
        const [orgs, current] = await Promise.all([
            API.get('/api/v1/organizations').catch(() => null),
            projectId ? API.get('/api/v1/projects/' + projectId).catch(() => null) : Promise.resolve(null),
        ]);
        const p = current?.project;
        const orgOptions = (orgs?.organizations || []).map(o => `<option value="${o.id}" ${p && String(p.organization_id) === String(o.id) ? 'selected' : ''}>${esc(o.name)}</option>`).join('');
        const m = modal(projectId ? 'Edit Project — ' + (p?.code || '') : 'Create Project', `
            <div class="grid grid-2">
                <div class="field"><label>Code *</label><input id="p-code" ${projectId ? 'disabled' : ''} value="${esc(p?.code || '')}"></div>
                <div class="field"><label>Name *</label><input id="p-name" value="${esc(p?.name || '')}"></div>
            </div>
            <div class="field"><label>Description</label><textarea id="p-desc" rows="2">${esc(p?.description || '')}</textarea></div>
            <div class="field"><label>Organization *</label><select id="p-org">${orgOptions || '<option value="">None in scope</option>'}</select></div>
            <div class="grid grid-2">
                <div class="field"><label>Start</label><input type="date" id="p-start" value="${esc(p?.start_date || '')}"></div>
                <div class="field"><label>End</label><input type="date" id="p-end" value="${esc(p?.end_date || '')}"></div>
            </div>
            <div class="grid grid-2">
                <div class="field"><label>Status</label><select id="p-status">
                    ${['planning', 'active', 'on_hold', 'completed', 'archived'].map(s => `<option ${p?.status === s ? 'selected' : ''}>${s}</option>`).join('')}
                </select></div>
                <div class="field"><label>Progress %</label><input type="number" id="p-progress" min="0" max="100" value="${esc(p?.progress ?? 0)}"></div>
            </div>
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="p-save">' + (projectId ? 'Save' : 'Create') + '</button>');
        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('p-save').onclick = async () => {
            const payload = {
                name: document.getElementById('p-name').value,
                description: document.getElementById('p-desc').value,
                organization_id: document.getElementById('p-org').value,
                start_date: document.getElementById('p-start').value || null,
                end_date: document.getElementById('p-end').value || null,
            };
            if (projectId) {
                payload.status = document.getElementById('p-status').value;
                payload.progress = document.getElementById('p-progress').value;
                await API.put('/api/v1/projects/' + projectId, payload);
            } else {
                payload.code = document.getElementById('p-code').value;
                await API.post('/api/v1/projects', payload);
            }
            document.getElementById('modal-root').innerHTML = '';
            showToast(projectId ? 'Project updated' : 'Project created', 'success');
            App.refreshView();
        };
    },

    performance: async function (el) {
        const [records, rankings] = await Promise.all([
            API.get('/api/v1/performance'),
            API.get('/api/v1/rankings'),
        ]);
        const canCalc = App.userHas('CALCULATE_PERFORMANCE', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin']);
        el.innerHTML = `
            <div class="toolbar">
                ${canCalc ? '<button class="btn btn-rancor" id="calc-btn">⚡ Recalculate scores</button>' : ''}
                <div class="spacer"></div>
                ${printBtn('Performance & Rankings Report')}
            </div>
            <div class="card mb">
                <h3>🏆 Official Rankings</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>#</th><th>Official</th><th>Department</th><th>Organization</th><th>Score</th><th>Period</th></tr></thead>
                    <tbody>
                        ${(rankings.rankings || []).map((r, i) => `<tr>
                            <td><strong>${i + 1}</strong></td>
                            <td>${esc(r.full_name)}</td>
                            <td>${esc(r.department_name)}</td>
                            <td>${esc(r.organization_name)}</td>
                            <td><strong style="color:${r.total_score >= 80 ? 'var(--green)' : r.total_score >= 60 ? 'var(--orange)' : 'var(--red)'}">${r.total_score}</strong></td>
                            <td>${esc(r.period)}</td>
                        </tr>`).join('') || '<tr><td colspan="6" class="muted">No performance records yet. Run "Recalculate scores".</td></tr>'}
                    </tbody>
                </table></div>
            </div>
            <div class="card">
                <h3>📋 Performance Records</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>Official</th><th>Dept</th><th>Period</th><th>KPI</th><th>Timeliness</th><th>Completion</th><th>Total</th><th>Method</th></tr></thead>
                    <tbody>
                        ${(records.records || []).slice(0, 40).map(r => `<tr>
                            <td>${esc(r.official_name)}</td>
                            <td>${esc(r.department_name)}</td>
                            <td>${esc(r.period)}</td>
                            <td>${r.kpi_achievement ?? '—'}%</td>
                            <td>${r.timeliness ?? '—'}%</td>
                            <td>${r.completion ?? '—'}%</td>
                            <td><strong>${r.total_score}</strong></td>
                            <td class="muted" style="font-size:11px">${esc(r.method_version || '')}</td>
                        </tr>`).join('') || '<tr><td colspan="8" class="muted">No records.</td></tr>'}
                    </tbody>
                </table></div>
                ${(records.records || []).length ? `<p class="muted mt" style="font-size:11px">Transparent formula: 0.5 × KPI achievement + 0.3 × timeliness + 0.2 × completion. Full explanation stored per record.</p>` : ''}
            </div>
        `;
        bindPrintButtons(el);
        if (canCalc) {
            document.getElementById('calc-btn').onclick = async () => {
                showToast('Computing scores…');
                try {
                    const r = await API.post('/api/v1/performance/calculate');
                    showToast(`Scores updated — ${r.alerts_created} new alert(s).`, 'success');
                    App.refreshView();
                } catch (e) { showToast(e.message, 'error'); }
            };
        }
    },

    alerts: async function (el) {
        const data = await API.get('/api/v1/alerts');
        const alerts = data.alerts || [];
        const canReview = App.userHas('REVIEW_RISK', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'dept_manager']);
        el.innerHTML = `
            <div class="toolbar">
                <div class="spacer"></div>
                ${printBtn('Risk Alerts Report')}
            </div>
            <div class="card">
                <h3>⚠ Risk Alerts — decision support only. Human review required (Section 30).</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>ID</th><th>Title</th><th>Severity</th><th>Organization</th><th>Status</th><th>Created</th><th>Factors</th>${canReview ? '<th></th>' : ''}</tr></thead>
                    <tbody>
                        ${alerts.map(a => `<tr>
                            <td>#${a.id}</td>
                            <td>${esc(a.title)}</td>
                            <td>${pillStatus(a.severity + '-s')}</td>
                            <td>${esc(a.organization_name)}</td>
                            <td>${pillStatus(a.status)}</td>
                            <td>${fmtDate(a.created_at)}</td>
                            <td class="muted" style="font-size:11px">${esc(Array.isArray(JSON.parse(a.factors_json || '[]')) ? JSON.parse(a.factors_json || '[]').join('; ') : '')}</td>
                            ${canReview ? `<td>${a.status !== 'resolved' && a.status !== 'dismissed' ? `<button class="btn btn-sm btn-ghost" data-review="${a.id}">Review</button>` : ''}</td>` : ''}
                        </tr>`).join('') || '<tr><td colspan="7" class="muted">No alerts.</td></tr>'}
                    </tbody>
                </table></div>
            </div>`;
        bindPrintButtons(el);
        if (canReview) {
            document.querySelectorAll('[data-review]').forEach(b => b.onclick = async () => {
                const note = prompt('Review note:');
                const status = confirm('Mark as resolved?') ? 'resolved' : (confirm('Dismiss?') ? 'dismissed' : 'under_review');
                await API.put(`/api/v1/alerts/${b.dataset.review}/review`, { status, note: note || null });
                showToast('Alert updated', 'success');
                App.refreshView();
            });
        }
    },

    rankor: async function (el) {
        const data = await API.get('/api/v1/rankor');
        const rows = data.analyses || [];
        el.innerHTML = `
            <div class="toolbar">
                <button class="btn btn-rancor" id="r-run">◈ Run full analysis</button>
                <div class="spacer"></div>
                ${printBtn('Rankor Intelligence Report')}
            </div>
            <div class="card">
                <h3>Rankor Analyses — explainable intelligence (every score stores method, source, factors)</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>ID</th><th>Target</th><th>Score type</th><th>Score</th><th>Confidence</th><th>Source</th><th>Method v</th><th>Explanation</th><th>Run by</th><th>When</th></tr></thead>
                    <tbody>
                        ${rows.map(r => `<tr>
                            <td>#${r.id}</td>
                            <td>${esc(r.target_type)} #${r.target_id}</td>
                            <td>${pillStatus('info')} ${esc(r.score_type)}</td>
                            <td><strong>${r.score}</strong></td>
                            <td>${r.confidence ?? '—'}</td>
                            <td><span class="pill pill-${r.source === 'c' ? 'completed' : 'info'}">${esc(r.source)}</span></td>
                            <td>${esc(r.method_version || '—')}</td>
                            <td class="muted" style="font-size:11px;max-width:320px">${esc(r.explanation || '')}</td>
                            <td>${esc(r.triggered_by)}</td>
                            <td>${fmtDate(r.created_at)}</td>
                        </tr>`).join('') || '<tr><td colspan="10" class="muted">No analyses yet — run the analysis.</td></tr>'}
                    </tbody>
                </table></div>
            </div>`;
        bindPrintButtons(el);
        document.getElementById('r-run').onclick = async () => {
            showToast('Running Rankor…');
            try {
                const r = await API.post('/api/v1/rankor/run');
                showToast(`Done. ${r.alerts_created} alert(s), ${Object.keys(r.delay_scores).length} delay scores.`, 'success');
                App.refreshView();
            } catch (e) { showToast(e.message, 'error'); }
        };
    },

    reports: async function (el) {
        const data = await API.get('/api/v1/reports');
        const reports = data.reports || [];
        const canGen = App.userHas('GENERATE_REPORT', ['super_admin', 'gov_admin', 'regional_admin', 'woreda_admin', 'org_admin', 'analyst']);
        const types = ['executive_summary', 'kpi', 'task', 'performance', 'risk', 'audit'];
        el.innerHTML = `
            <div class="toolbar">
                ${canGen ? `
                    <select id="rep-type" class="btn-ghost" style="padding:8px">
                        ${types.map(t => `<option value="${t}">${t.replace(/_/g, ' ')}</option>`).join('')}
                    </select>
                    <button class="btn" id="gen-btn">Generate report</button>` : ''}
                <div class="spacer"></div>
                ${printBtn('Reports Register')}
            </div>
            <div class="card">
                <h3>Generated reports</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Period</th><th>Generated by</th><th>When</th></tr></thead>
                    <tbody>
                        ${reports.map(r => `<tr style="cursor:pointer" data-report="${r.id}">
                            <td>#${r.id}</td>
                            <td><strong>${esc(r.title)}</strong></td>
                            <td><span class="pill pill-info">${esc(r.type)}</span></td>
                            <td>${esc(r.period || '—')}</td>
                            <td>${esc(r.generated_by_name)}</td>
                            <td>${fmtDate(r.created_at)}</td>
                        </tr>`).join('') || '<tr><td colspan="6" class="muted">No reports generated yet.</td></tr>'}
                    </tbody>
                </table></div>
            </div>`;
        bindPrintButtons(el);
        if (canGen) {
            document.getElementById('gen-btn').onclick = async () => {
                try {
                    const r = await API.post('/api/v1/reports/generate', { type: document.getElementById('rep-type').value });
                    showToast('Report #' + r.report_id + ' generated', 'success');
                    this.reportDetail(r.report);
                } catch (e) { showToast(e.message, 'error'); }
            };
        }
        document.querySelectorAll('[data-report]').forEach(row => row.onclick = async () => {
            const r = await API.get('/api/v1/reports/' + row.dataset.report);
            this.reportDetail(r.report);
        });
    },

    reportDetail: function (report) {
        const body = report.json_data || {};
        const printSafe = (v) => esc(typeof v === 'object' ? JSON.stringify(v, null, 2) : v);
        const summary = (name, val) => `<div class="grid-2" style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--line)"><span class="muted">${esc(name)}</span><strong>${esc(val)}</strong></div>`;
        let content = '';
        content += summary('Report ID', report.id);
        content += summary('Type', report.type.replace(/_/g, ' '));
        content += summary('Period', report.period || '—');
        content += summary('Generated', body.generated_at || '—');
        content += summary('Generated by', report.generated_by_name || '—');
        content += summary('Scope', (body.scope_orgs || []).map(o => o.name).join(', ') || '—');
        if (report.type === 'executive_summary') {
            content += summary('Completion rate', (body.completion_rate ?? '—') + '%');
            content += summary('Overdue tasks', body.overdue ?? '—');
            content += summary('Avg KPI achievement', (body.avg_kpi_achievement ?? '—') + '%');
            content += summary('Open alerts', body.open_alerts ?? '—');
        }
        const payloadTable = Object.keys(body || {}).filter(k => !['generated_at', 'scope_orgs'].includes(k)).map(k => `
            <div style="margin:10px 0"><div class="eyebrow">${esc(k.replace(/_/g, ' '))}</div><div class="payload-preview">${printSafe(body[k])}</div></div>`).join('');

        const docHtml = `
            <section class="card">
                <h3>Report summary</h3>
                ${content}
            </section>
            <section class="card mt">
                <h3>Data & Rankor analysis sections</h3>
                ${payloadTable || '<p class="muted">No structured sections.</p>'}
            </section>`;

        const m = modal('Report #' + report.id + ' — ' + report.type.replace(/_/g, ' '), `
            ${content}
            <div class="eyebrow mt">Full JSON payload (observed data vs calculated metrics vs Rankor analysis)</div>
            <div class="payload-preview">${esc(JSON.stringify(body, null, 2))}</div>
        `, `<button class="btn btn-sm btn-ghost" data-print-report>🖨 Print document</button><button class="btn" data-close>Close</button>`);
        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        m.querySelector('[data-print-report]').onclick = () => {
            printDoc('Report #' + report.id + ' — ' + report.type.replace(/_/g, ' '), docHtml);
        };
    },

    audit: async function (el) {
        const data = await API.get('/api/v1/audit');
        el.innerHTML = `
            <div class="toolbar">
                <div class="spacer"></div>
                ${printBtn('Audit Log Report')}
            </div>
            <div class="card">
                <h3>🔒 Audit Log — protected records (Actions)</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
                    <tbody>
                        ${(data.logs || []).map(l => `<tr>
                            <td style="white-space:nowrap">${fmtDate(l.created_at)}</td>
                            <td>${esc(l.username)}</td>
                            <td><span class="pill pill-info">${esc(l.action)}</span></td>
                            <td>${esc(l.entity_type || '')}${l.entity_id ? ' #' + esc(l.entity_id) : ''}</td>
                            <td class="muted" style="font-size:11px;max-width:340px">${esc(l.details_json || '')}</td>
                            <td>${esc(l.ip_address || '')}</td>
                        </tr>`).join('') || '<tr><td colspan="6" class="muted">No log entries.</td></tr>'}
                    </tbody>
                </table></div>
                <p class="muted mt" style="font-size:11px">${data.total} total entries.</p>
            </div>`;
        bindPrintButtons(el);
    },

    roles: async function (el) {
        const data = await API.get('/api/v1/roles');
        const roles = data.roles || [];
        el.innerHTML = `
            <div class="toolbar">
                <button class="btn" id="new-role-btn">+ New Role</button>
                <div class="spacer"></div>
                ${printBtn('Roles & Permissions Report')}
            </div>
            <div class="card">
                <h3>RBAC — roles and permission codes (Section 26)</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>Code</th><th>Name</th><th>Description</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
                    <tbody>
                        ${roles.map(r => `<tr>
                            <td><strong>${esc(r.code)}</strong></td>
                            <td>${esc(r.name)}</td>
                            <td class="muted" style="font-size:12px">${esc(r.description || '—')}</td>
                            <td><span class="pill pill-info">${r.permission_count} granted</span></td>
                            <td>${r.user_count}</td>
                            <td style="white-space:nowrap">
                                <button class="btn btn-sm btn-ghost" data-role-edit="${r.id}">Permissions</button>
                                ${r.code !== 'SUPER_ADMIN' ? `<button class="btn btn-sm btn-danger" data-role-del="${r.id}">Delete</button>` : ''}
                            </td>
                        </tr>`).join('')}
                    </tbody>
                </table></div>
            </div>`;
        bindPrintButtons(el);
        document.getElementById('new-role-btn').onclick = () => this.roleModal();
        document.querySelectorAll('[data-role-edit]').forEach(b => b.onclick = () => this.roleModal(b.dataset.roleEdit));
        document.querySelectorAll('[data-role-del]').forEach(b => b.onclick = async () => {
            if (!confirm('Delete this role?')) return;
            try {
                await API.del('/api/v1/roles/' + b.dataset.roleDel);
                showToast('Role deleted', 'success');
                App.refreshView();
            } catch (e) { showToast(e.message, 'error'); }
        });
    },

    roleModal: async function (id) {
        const [permsData, role] = await Promise.all([
            API.get('/api/v1/roles?all_permissions=1'),
            id ? API.get('/api/v1/roles/' + id) : Promise.resolve(null),
        ]);
        const granted = role?.role?.permissions?.map(p => p.code) || [];
        const allPerms = permsData?.permissions || [];
        const checkbox = (code, label) => `
            <label style="display:flex;gap:8px;align-items:flex-start;padding:5px 0;border-bottom:1px solid var(--line)">
                <input type="checkbox" data-perm="${esc(code)}" ${granted.includes(code) ? 'checked' : ''}>
                <span><code>${esc(code)}</code><div class="muted" style="font-size:11px">${esc(label)}</div></span>
            </label>`;
        const m = modal(id ? 'Edit Role — ' + role.role.code : 'Create Role', `
            <div class="grid grid-2">
                <div class="field"><label>Code *</label><input id="r-code" ${id ? 'disabled' : ''} value="${esc(role?.role?.code || '')}"></div>
                <div class="field"><label>Name *</label><input id="r-name" value="${esc(role?.role?.name || '')}"></div>
            </div>
            <div class="field"><label>Description</label><input id="r-desc" value="${esc(role?.role?.description || '')}"></div>
            <div class="eyebrow mt">Permissions for this role</div>
            <div style="max-height:320px;overflow-y:auto;border:1px solid var(--line);border-radius:8px;padding:8px 12px">
                ${allPerms.length ? allPerms.map(p => checkbox(p.code, p.description || '')).join('') : '<p class="muted">Loading permissions…</p>'}
            </div>
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="r-save">Save</button>');
        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('r-save').onclick = async () => {
            let perms = [...document.querySelectorAll('[data-perm]:checked')].map(c => c.dataset.perm);
            if (document.querySelector('#r-code')?.value?.toUpperCase() === 'SUPER_ADMIN' || role?.role?.code === 'SUPER_ADMIN') {
                if (!perms.includes('MANAGE_ROLES')) perms.push('MANAGE_ROLES');
            }
            const payload = { name: document.getElementById('r-name').value, description: document.getElementById('r-desc').value, permissions: perms };
            if (id) {
                await API.put('/api/v1/roles/' + id, payload);
            } else {
                payload.code = document.getElementById('r-code').value;
                await API.post('/api/v1/roles', payload);
            }
            document.getElementById('modal-root').innerHTML = '';
            showToast('Role saved', 'success');
            App.refreshView();
        };
    },

    settings: async function (el) {
        const data = await API.get('/api/v1/settings');
        const rows = Object.entries(data.settings || {});
        el.innerHTML = `
            <div class="toolbar">
                <button class="btn" id="new-setting-btn">+ Add setting</button>
                <div class="spacer"></div>
                ${printBtn('System Settings')}
            </div>
            <div class="card">
                <h3>System settings — stored in the settings table (audited)</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>Key</th><th>Value</th><th>Updated</th><th></th></tr></thead>
                    <tbody>
                        ${rows.map(([k, v]) => `<tr>
                            <td><code>${esc(k)}</code></td>
                            <td style="max-width:420px;word-break:break-word">${esc(typeof v === 'object' ? JSON.stringify(v) : v)}</td>
                            <td class="muted">—</td>
                            <td><button class="btn btn-sm btn-ghost" data-setting-edit="${esc(k)}">Edit</button></td>
                        </tr>`).join('') || '<tr><td colspan="4" class="muted">No settings yet.</td></tr>'}
                    </tbody>
                </table></div>
            </div>`;
        bindPrintButtons(el);
        document.getElementById('new-setting-btn').onclick = () => this.settingModal();
        document.querySelectorAll('[data-setting-edit]').forEach(b => b.onclick = () => this.settingModal(b.dataset.settingEdit));
    },

    settingModal: function (key) {
        const m = modal(key ? 'Edit setting' : 'Add setting', `
            <div class="field"><label>Key (a-z, 0-9, _ and . only)</label><input id="s-key" ${key ? 'disabled' : ''} value="${esc(key || '')}"></div>
            <div class="field"><label>Value (JSON or plain text)</label><textarea id="s-value" rows="4"></textarea></div>
            <p class="muted" style="font-size:11px">Values are stored as JSON. Sensitive keys (auth.session_*, login_guard_*) are protected.</p>
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="s-save">Save</button>');
        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('s-save').onclick = async () => {
            const k = (key || document.getElementById('s-key').value).trim();
            if (!/^[a-z0-9_.]{1,96}$/.test(k)) { showToast('Invalid settings key.', 'error'); return; }
            const raw = document.getElementById('s-value').value;
            let value;
            try { value = JSON.parse(raw); } catch { value = raw; }
            await API.put('/api/v1/settings', { settings: { [k]: value } });
            document.getElementById('modal-root').innerHTML = '';
            showToast('Setting saved', 'success');
            App.refreshView();
        };
    },

    users: async function (el) {
        const data = await API.get('/api/v1/users');
        const users = data.users || [];
        el.innerHTML = `
            <div class="toolbar">
                <button class="btn" id="new-user-btn">+ New User</button>
                <div class="spacer"></div>
                ${printBtn('User Registry Report')}
            </div>
            <div class="card">
                <h3>User Management</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Organization</th><th>Department</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        ${users.map(u => `<tr>
                            <td><strong>${esc(u.username)}</strong></td>
                            <td>${esc(u.full_name)}</td>
                            <td>${esc(u.role_name)}</td>
                            <td>${esc(u.organization_name)}</td>
                            <td>${esc(u.department_name || '—')}</td>
                            <td>${pillStatus(u.status)}</td>
                            <td style="white-space:nowrap">
                                <button class="btn btn-sm btn-ghost" data-user-edit="${u.id}">Edit</button>
                                ${u.status !== 'disabled' && String(u.id) !== String(App.user.id) ? `<button class="btn btn-sm btn-danger" data-user-disable="${u.id}">Disable</button>` : ''}
                            </td>
                        </tr>`).join('') || '<tr><td colspan="7" class="muted">No users in scope.</td></tr>'}
                    </tbody>
                </table></div>
            </div>`;
        bindPrintButtons(el);
        document.getElementById('new-user-btn').onclick = () => this.userModal();
        document.querySelectorAll('[data-user-edit]').forEach(b => b.onclick = () => this.userModal(b.dataset.userEdit));
        document.querySelectorAll('[data-user-disable]').forEach(b => b.onclick = async () => {
            if (!confirm('Disable this user?')) return;
            try {
                await API.del('/api/v1/users/' + b.dataset.userDisable);
                showToast('User disabled', 'success');
                App.refreshView();
            } catch (e) { showToast(e.message, 'error'); }
        });
    },

    userModal: async function (userId) {
        const [orgs, roles] = await Promise.all([
            API.get('/api/v1/organizations').catch(() => null),
            API.get('/api/v1/roles').catch(() => null),
        ]);
        const current = userId ? (await API.get('/api/v1/users/' + userId).catch(() => null))?.user : null;
        const roleOptions = (roles?.roles || []).map(r =>
            `<option value="${r.id}" ${current && String(current.role_id) === String(r.id) ? 'selected' : ''}>${esc(r.name)} (${esc(r.code)})</option>`).join('');
        const orgOptions = (orgs?.organizations || []).map(o =>
            `<option value="${o.id}" ${current && String(current.organization_id) === String(o.id) ? 'selected' : ''}>${esc(o.name)}</option>`).join('');
        const title = userId ? 'Edit User — ' + (current?.username || '') : 'Create User';
        const m = modal(title, `
            <div class="grid grid-2">
                <div class="field"><label>Username *</label><input id="u-username" ${userId ? 'disabled' : ''} value="${esc(current?.username || '')}"></div>
                <div class="field"><label>${userId ? 'New password (blank = keep)' : 'Password * (min 8)'}</label><input type="password" id="u-password" ${userId ? '' : 'required'} autocomplete="new-password"></div>
                <div class="field"><label>Full name *</label><input id="u-name" value="${esc(current?.full_name || '')}"></div>
                <div class="field"><label>Email</label><input id="u-email" value="${esc(current?.email || '')}"></div>
            </div>
            <div class="grid grid-2">
                <div class="field"><label>Role *</label><select id="u-role">${roleOptions || '<option value="">No roles available</option>'}</select></div>
                <div class="field"><label>Organization *</label><select id="u-org">${orgOptions || '<option value="">None in scope</option>'}</select></div>
            </div>
            <div class="field"><label>Phone</label><input id="u-phone" value="${esc(current?.phone || '')}"></div>
            ${userId ? `<div class="field"><label>Status</label><select id="u-status">
                ${['active', 'disabled', 'locked'].map(s => `<option ${current?.status === s ? 'selected' : ''}>${s}</option>`).join('')}
            </select></div>` : ''}
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="u-save">' + (userId ? 'Save' : 'Create') + '</button>');
        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('u-save').onclick = async () => {
            const payload = {
                full_name: document.getElementById('u-name').value,
                email: document.getElementById('u-email').value || null,
                phone: document.getElementById('u-phone').value || null,
                role_id: document.getElementById('u-role').value,
            };
            const pw = document.getElementById('u-password').value;
            if (pw) payload.password = pw;
            if (userId) {
                if (document.getElementById('u-status')) payload.status = document.getElementById('u-status').value;
                await API.put('/api/v1/users/' + userId, payload);
            } else {
                payload.username = document.getElementById('u-username').value;
                payload.organization_id = document.getElementById('u-org').value;
                await API.post('/api/v1/users', payload);
            }
            document.getElementById('modal-root').innerHTML = '';
            showToast(userId ? 'User updated' : 'User created', 'success');
            App.refreshView();
        };
    },

    organizations: async function (el) {
        const data = await API.get('/api/v1/organizations');
        const orgs = data.organizations || [];
        const flatten = (nodes, depth = 0) => nodes.map(n => `
            <tr>
                <td style="padding-left:${16 + depth * 22}px">${depth ? '└ ' : '🏛 '}<strong>${esc(n.name)}</strong></td>
                <td>${esc(n.code)}</td>
                <td><span class="pill pill-info">${esc(n.type)}</span></td>
                <td>${esc(n.region || '—')}</td>
                <td>${esc(n.zone || '—')}</td>
                <td>${esc(n.woreda || '—')}</td>
                <td>${pillStatus(n.status)}</td>
                <td>${n.status !== 'archived' ? `<button class="btn btn-sm btn-danger" data-org-archive="${n.id}">Archive</button>` : ''}</td>
            </tr>${(n.children || []).length ? flatten(n.children, depth + 1) : ''}`).join('');
        el.innerHTML = `
            <div class="toolbar"><button class="btn" id="new-org-btn">+ New Organization</button><div class="spacer"></div>${printBtn('Administrative Hierarchy Report')}</div>
            <div class="card">
                <h3>Administrative Hierarchy — configurable (Federal → Region → Zone → Woreda → Kebele, Section 24)</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>Name</th><th>Code</th><th>Type</th><th>Region</th><th>Zone</th><th>Woreda</th><th>Status</th><th></th></tr></thead>
                    <tbody>${flatten(orgs) || '<tr><td colspan="8" class="muted">None.</td></tr>'}</tbody>
                </table></div>
            </div>`;
        bindPrintButtons(el);
        document.getElementById('new-org-btn').onclick = () => this.orgModal();
        document.querySelectorAll('[data-org-archive]').forEach(b => b.onclick = async () => {
            if (!confirm('Archive this organization and its subtree?')) return;
            try {
                await API.del('/api/v1/organizations/' + b.dataset.orgArchive);
                showToast('Organization archived', 'success');
                App.refreshView();
            } catch (e) { showToast(e.message, 'error'); }
        });
    },

    orgModal: function () {
        const m = modal('Create Organization', `
            <div class="grid grid-2">
                <div class="field"><label>Code *</label><input id="o-code"></div>
                <div class="field"><label>Name *</label><input id="o-name"></div>
                <div class="field"><label>Type *</label>
                    <select id="o-type">${['federal', 'region', 'zone', 'woreda', 'kebele', 'organization'].map(t => `<option>${t}</option>`).join('')}</select>
                </div>
                <div class="field"><label>Region</label><input id="o-region"></div>
                <div class="field"><label>Zone</label><input id="o-zone"></div>
                <div class="field"><label>Woreda</label><input id="o-woreda"></div>
            </div>
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="o-save">Create</button>');
        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('o-save').onclick = async () => {
            await API.post('/api/v1/organizations', {
                code: document.getElementById('o-code').value,
                name: document.getElementById('o-name').value,
                type: document.getElementById('o-type').value,
                region: document.getElementById('o-region').value || null,
                zone: document.getElementById('o-zone').value || null,
                woreda: document.getElementById('o-woreda').value || null,
            });
            document.getElementById('modal-root').innerHTML = '';
            showToast('Organization created', 'success');
            App.refreshView();
        };
    },

    departments: async function (el) {
        const data = await API.get('/api/v1/departments');
        const rows = data.departments || [];
        const orgs = (await API.get('/api/v1/organizations').catch(() => null)) || {};
        el.innerHTML = `
            <div class="toolbar"><button class="btn" id="new-dept-btn">+ New Department</button><div class="spacer"></div>${printBtn('Departments Register')}</div>
            <div class="card">
                <h3>Departments</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>Code</th><th>Name</th><th>Organization</th><th>Manager</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        ${rows.map(d => `<tr>
                            <td><strong>${esc(d.code)}</strong></td>
                            <td>${esc(d.name)}</td>
                            <td>${esc(d.organization_name)}</td>
                            <td>${esc(d.manager_name || '—')}</td>
                            <td>${pillStatus(d.status)}</td>
                            <td style="white-space:nowrap">
                                <button class="btn btn-sm btn-ghost" data-dept-edit="${d.id}">Edit</button>
                                <button class="btn btn-sm btn-danger" data-dept-del="${d.id}">${d.status === 'archived' ? 'Delete' : 'Archive'}</button>
                            </td>
                        </tr>`).join('') || '<tr><td colspan="6" class="muted">No departments in scope.</td></tr>'}
                    </tbody>
                </table></div>
            </div>`;
        bindPrintButtons(el);
        document.getElementById('new-dept-btn').onclick = () => this.deptModal();
        document.querySelectorAll('[data-dept-edit]').forEach(b => b.onclick = () => this.deptModal(b.dataset.deptEdit));
        document.querySelectorAll('[data-dept-del]').forEach(b => b.onclick = async () => {
            if (!confirm('Delete or archive this department?')) return;
            try {
                await API.del('/api/v1/departments/' + b.dataset.deptDel);
                showToast('Department updated', 'success');
                App.refreshView();
            } catch (e) { showToast(e.message, 'error'); }
        });
    },

    deptModal: async function (deptId) {
        const orgs = await API.get('/api/v1/organizations').catch(() => null);
        const current = deptId ? ((await API.get('/api/v1/departments').catch(() => null))?.departments || []).find(d => String(d.id) === String(deptId)) : null;
        const orgOptions = (orgs?.organizations || []).map(o => `<option value="${o.id}" ${current && String(current.organization_id) === String(o.id) ? 'selected' : ''}>${esc(o.name)}</option>`).join('');
        const m = modal(deptId ? 'Edit Department — ' + (current?.code || '') : 'Create Department', `
            <div class="grid grid-2">
                <div class="field"><label>Code *</label><input id="d-code" value="${esc(current?.code || '')}" ${deptId ? 'disabled' : ''}></div>
                <div class="field"><label>Name *</label><input id="d-name" value="${esc(current?.name || '')}"></div>
            </div>
            <div class="field"><label>Organization *</label><select id="d-org">${orgOptions}</select></div>
            ${deptId ? `<div class="field"><label>Status</label><select id="d-status">
                ${['active', 'archived'].map(s => `<option ${current?.status === s ? 'selected' : ''}>${s}</option>`).join('')}
            </select></div>` : ''}
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="d-save">' + (deptId ? 'Save' : 'Create') + '</button>');
        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('d-save').onclick = async () => {
            const payload = { name: document.getElementById('d-name').value, organization_id: document.getElementById('d-org').value };
            if (deptId) {
                if (document.getElementById('d-status')) payload.status = document.getElementById('d-status').value;
                await API.put('/api/v1/departments/' + deptId, payload);
            } else {
                payload.code = document.getElementById('d-code').value;
                await API.post('/api/v1/departments', payload);
            }
            document.getElementById('modal-root').innerHTML = '';
            showToast(deptId ? 'Department updated' : 'Department created', 'success');
            App.refreshView();
        };
    },

    officials: async function (el) {
        const data = await API.get('/api/v1/officials');
        const rows = data.officials || [];
        el.innerHTML = `
            <div class="toolbar">
                <button class="btn" id="new-official-btn">+ Register Official</button>
                <div class="spacer"></div>
                ${printBtn('Officials Register')}
            </div>
            <div class="card">
                <h3>Officials</h3>
                <div class="table-wrap"><table>
                    <thead><tr><th>Full name</th><th>Username</th><th>Position</th><th>Grade</th><th>Department</th><th>Organization</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        ${rows.map(o => `<tr>
                            <td><strong>${esc(o.full_name)}</strong></td>
                            <td>${esc(o.username)}</td>
                            <td>${esc(o.position || '—')}</td>
                            <td>${esc(o.grade || '—')}</td>
                            <td>${esc(o.department_name)}</td>
                            <td>${esc(o.organization_name)}</td>
                            <td>${pillStatus(o.status)}</td>
                            <td style="white-space:nowrap">
                                <button class="btn btn-sm btn-ghost" data-official-edit="${o.id}">Edit</button>
                                ${o.status !== 'inactive' ? `<button class="btn btn-sm btn-danger" data-official-del="${o.id}">Deactivate</button>` : ''}
                            </td>
                        </tr>`).join('') || '<tr><td colspan="8" class="muted">No officials registered.</td></tr>'}
                    </tbody>
                </table></div>
            </div>`;
        bindPrintButtons(el);
        document.getElementById('new-official-btn').onclick = () => this.officialModal();
        document.querySelectorAll('[data-official-edit]').forEach(b => b.onclick = () => this.officialModal(b.dataset.officialEdit));
        document.querySelectorAll('[data-official-del]').forEach(b => b.onclick = async () => {
            if (!confirm('Deactivate this official?')) return;
            try {
                await API.del('/api/v1/officials/' + b.dataset.officialDel);
                showToast('Official deactivated', 'success');
                App.refreshView();
            } catch (e) { showToast(e.message, 'error'); }
        });
    },

    officialModal: async function (officialId) {
        const [orgs, users, depts, current] = await Promise.all([
            API.get('/api/v1/organizations').catch(() => null),
            API.get('/api/v1/users').catch(() => null),
            API.get('/api/v1/departments').catch(() => null),
            officialId ? API.get('/api/v1/officials/' + officialId).catch(() => null) : Promise.resolve(null),
        ]);
        const cur = current?.official;
        const userOptions = (users?.users || []).map(u => `
            <option value="${u.id}" ${cur && String(cur.user_id) === String(u.id) ? 'selected' : ''}>${esc(u.full_name)} (${esc(u.username)})</option>`).join('');
        const orgOptions = (orgs?.organizations || []).map(o => `
            <option value="${o.id}" ${cur && String(cur.organization_id) === String(o.id) ? 'selected' : ''}>${esc(o.name)}</option>`).join('');
        const deptOptions = (depts?.departments || []).map(d => `
            <option value="${d.id}" ${cur && String(cur.department_id) === String(d.id) ? 'selected' : ''}>${esc(d.name)}</option>`).join('');
        const m = modal(officialId ? 'Edit Official' : 'Register Official', `
            ${officialId ? '' : '<div class="field"><label>User account *</label><select id="o-user">' + userOptions + '</select></div>'}
            <div class="grid grid-2">
                <div class="field"><label>Organization *</label><select id="o-org">${orgOptions || '<option value="">None in scope</option>'}</select></div>
                <div class="field"><label>Department *</label><select id="o-dept">${deptOptions || '<option value="">None in scope</option>'}</select></div>
                <div class="field"><label>Position</label><input id="o-position" value="${esc(cur?.position || '')}"></div>
                <div class="field"><label>Grade</label><input id="o-grade" value="${esc(cur?.grade || '')}"></div>
            </div>
        `, '<button class="btn-ghost btn" data-close>Cancel</button><button class="btn" id="o-save">' + (officialId ? 'Save' : 'Register') + '</button>');
        m.querySelector('[data-close]').onclick = () => { document.getElementById('modal-root').innerHTML = ''; };
        document.getElementById('o-save').onclick = async () => {
            const payload = {
                organization_id: document.getElementById('o-org').value,
                department_id: document.getElementById('o-dept').value,
                position: document.getElementById('o-position').value || null,
                grade: document.getElementById('o-grade').value || null,
            };
            if (officialId) {
                await API.put('/api/v1/officials/' + officialId, payload);
            } else {
                payload.user_id = document.getElementById('o-user').value;
                await API.post('/api/v1/officials', payload);
            }
            document.getElementById('modal-root').innerHTML = '';
            showToast(officialId ? 'Official updated' : 'Official registered', 'success');
            App.refreshView();
        };
    },
};

function debounce(fn, ms) {
    let t;
    return function (...args) { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

document.addEventListener('DOMContentLoaded', () => App.init().catch(e => showToast(e.message, 'error')));