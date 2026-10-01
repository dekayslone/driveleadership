const STORAGE_KEYS = {
  auth: 'driveLeadershipAdminAuth',
  applicants: 'driveLeadershipApplicants',
  messages: 'driveLeadershipMessages',
  settings: 'driveLeadershipAdminSettings'
};

const fallbackApplicants = [];
const fallbackMessages = [];

const fallbackSettings = {
  adminName: 'Admin User',
  email: 'thedrive009@gmail.com',
  approvedEmails: true,
  rejectedEmails: true,
  weeklySummary: false
};

function readStorage(key, fallback) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch (error) {
    return fallback;
  }
}

function writeStorage(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;'
  }[character]));
}

function ensureLocalData() {
  writeStorage(STORAGE_KEYS.applicants, []);
  writeStorage(STORAGE_KEYS.messages, []);
  if (!localStorage.getItem(STORAGE_KEYS.settings)) writeStorage(STORAGE_KEYS.settings, fallbackSettings);
}

function getApplicants() {
  const items = readStorage(STORAGE_KEYS.applicants, fallbackApplicants);
  return Array.isArray(items) ? items : fallbackApplicants;
}

function getMessages() {
  const items = readStorage(STORAGE_KEYS.messages, fallbackMessages);
  return Array.isArray(items) ? items : fallbackMessages;
}

function getSettings() {
  const settings = readStorage(STORAGE_KEYS.settings, fallbackSettings);
  return settings && typeof settings === 'object' ? { ...fallbackSettings, ...settings } : fallbackSettings;
}

function getAuth() {
  const auth = readStorage(STORAGE_KEYS.auth, null);
  return auth && typeof auth === 'object' ? auth : null;
}

function setAuth(user) {
  writeStorage(STORAGE_KEYS.auth, user);
}

async function getCsrfToken() {
  const response = await fetch('../backend/api/admin-csrf.php', { credentials: 'include' });
  const payload = await response.json();
  if (!response.ok || !payload.csrf_token) throw new Error('Security token unavailable.');
  return payload.csrf_token;
}

async function apiRequest(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options
  });

  const payload = response.headers.get('content-type')?.includes('application/json')
    ? await response.json()
    : null;

  return { response, payload };
}

async function logout() {
  try {
    const csrfToken = await getCsrfToken();
    await apiRequest('../backend/api/admin-logout.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ _csrf: csrfToken })
    });
  } catch (error) {
    // Redirect even when the server is unavailable so local auth state is cleared.
  }
  localStorage.removeItem(STORAGE_KEYS.auth);
  window.location.href = 'login.html';
}

function showMessage(el, text, type = 'error') {
  if (!el) return;
  el.textContent = text;
  el.classList.remove('success', 'error');
  el.classList.add(type === 'success' ? 'success' : 'error');
}

function loginPageInit() {
  const form = document.getElementById('admin-login-form');
  const button = document.getElementById('login-button');
  const message = document.getElementById('login-message');
  const passwordInput = document.getElementById('login-password');
  const toggle = document.querySelector('.password-toggle');

  if (!form || !button || !message || !passwordInput || !toggle) return;

  if (getAuth()) {
    apiRequest('../backend/api/admin-status.php', { method: 'GET' }).then(({ response }) => {
      if (response.ok) {
        window.location.href = 'index.html';
      } else {
        localStorage.removeItem(STORAGE_KEYS.auth);
      }
    }).catch(() => {
      localStorage.removeItem(STORAGE_KEYS.auth);
    });
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const email = String(formData.get('email') || '').trim().toLowerCase();
    const password = String(formData.get('password') || '').trim();
    const validEmail = /^.+@.+\..+$/.test(email);
    const validPassword = password.length > 0;

    button.disabled = true;
    button.querySelector('.btn-label').textContent = 'Signing you in...';
    showMessage(message, '', 'error');

    setTimeout(async () => {
      if (!validEmail || !validPassword) {
        showMessage(message, 'Invalid email or password.', 'error');
        button.disabled = false;
        button.querySelector('.btn-label').textContent = 'Sign in';
        return;
      }

      try {
        const csrfToken = await getCsrfToken();
        const { response, payload } = await apiRequest('../backend/api/admin-login.php', {
          method: 'POST',
          body: JSON.stringify({ email, password, _csrf: csrfToken })
        });

        if (!response.ok) {
          showMessage(message, payload?.message || 'Invalid email or password.', 'error');
          button.disabled = false;
          button.querySelector('.btn-label').textContent = 'Sign in';
          return;
        }

        const user = payload?.user || { email, name: 'Admin User', role: 'Super Admin' };
        setAuth(user);
        showMessage(message, 'Login successful. Redirecting...', 'success');
        button.querySelector('.btn-label').textContent = 'Redirecting...';

        setTimeout(() => {
          window.location.href = 'index.html';
        }, 700);
      } catch (error) {
        showMessage(message, 'The authentication service is unavailable. Start the PHP server and try again.', 'error');
        button.disabled = false;
        button.querySelector('.btn-label').textContent = 'Sign in';
      }
    }, 700);
  });

  toggle.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    toggle.textContent = isPassword ? 'Hide' : 'Show';
    toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
  });
}

function resetPasswordInit() {
  const requestForm = document.getElementById('reset-request-form');
  const resetForm = document.getElementById('reset-password-form');
  const token = new URLSearchParams(window.location.search).get('token');

  if (token && requestForm && resetForm) {
    requestForm.hidden = true;
    resetForm.hidden = false;
  }

  requestForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = document.getElementById('reset-request-message');
    const email = String(new FormData(requestForm).get('email') || '').trim();
    try {
      const csrfToken = await getCsrfToken();
      const { response, payload } = await apiRequest('../backend/api/admin-password-reset-request.php', {
        method: 'POST', body: JSON.stringify({ email, _csrf: csrfToken })
      });
      showMessage(message, payload?.message || 'Check your email for reset instructions.', response.ok ? 'success' : 'error');
    } catch (error) {
      showMessage(message, error.message, 'error');
    }
  });

  resetForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = document.getElementById('reset-password-message');
    const data = new FormData(resetForm);
    const password = String(data.get('password') || '');
    const confirmation = String(data.get('confirm_password') || '');
    if (password.length < 12 || password !== confirmation) {
      showMessage(message, 'Passwords must match and be at least 12 characters.', 'error');
      return;
    }
    try {
      const csrfToken = await getCsrfToken();
      const { response, payload } = await apiRequest('../backend/api/admin-password-reset.php', {
        method: 'POST', body: JSON.stringify({ token, password, _csrf: csrfToken })
      });
      showMessage(message, payload?.message || 'Password updated.', response.ok ? 'success' : 'error');
      if (response.ok) setTimeout(() => { window.location.href = 'login.html'; }, 900);
    } catch (error) {
      showMessage(message, error.message, 'error');
    }
  });
}

function renderOverview() {
  const applicants = getApplicants();
  const messages = getMessages();
  const stats = {
    total: applicants.length,
    pending: applicants.filter((item) => item.status === 'Pending').length,
    approved: applicants.filter((item) => item.status === 'Approved').length,
    rejected: applicants.filter((item) => item.status === 'Rejected').length
  };

  const cards = [
    { value: stats.total },
    { value: stats.pending },
    { value: stats.approved },
    { value: stats.rejected }
  ];

  document.querySelectorAll('[data-metric]').forEach((card, index) => {
    const item = cards[index];
    if (!item) return;
    const valueEl = card.querySelector('.stat-value');
    const changeEl = card.querySelector('.trend-up, .trend-down, .live-label');
    if (valueEl) valueEl.textContent = String(item.value);
    if (changeEl) {
      changeEl.className = 'live-label';
      changeEl.textContent = 'Live database';
    }
  });

  document.querySelector('[data-sidebar-count="overview"]')?.replaceChildren(document.createTextNode(String(stats.total + messages.length)));
  document.querySelector('[data-sidebar-count="attendees"]')?.replaceChildren(document.createTextNode(String(stats.total)));
  document.querySelector('[data-sidebar-count="volunteers"]')?.replaceChildren(document.createTextNode('0'));
  document.querySelector('[data-sidebar-count="messages"]')?.replaceChildren(document.createTextNode(String(messages.length)));
  document.querySelector('[data-message-stat="total"]')?.replaceChildren(document.createTextNode(String(messages.length)));
  document.querySelector('[data-message-stat="unread"]')?.replaceChildren(document.createTextNode(String(messages.filter((item) => item.status === 'Unread').length)));
  document.querySelector('[data-message-stat="read"]')?.replaceChildren(document.createTextNode(String(messages.filter((item) => item.status !== 'Unread').length)));
  renderRecentOverview(applicants, messages);
}

function renderRecentOverview(applicants, messages) {
  const table = document.getElementById('recent-table');
  if (!table) return;
  const rows = [
    ...applicants.map((item) => ({ name: item.name, email: item.email, type: 'Attendee', date: item.date, status: item.status, id: item.id })),
    ...messages.map((item) => ({ name: item.sender, email: item.email, type: 'Message', date: item.date, status: item.status, id: item.id }))
  ].sort((left, right) => Date.parse(right.date) - Date.parse(left.date)).slice(0, 5);
  table.querySelector('tbody').innerHTML = rows.length ? rows.map((row) => `
    <tr>
      <td>${escapeHtml(row.name)}</td><td>${escapeHtml(row.email)}</td><td>${escapeHtml(row.type)}</td><td>${escapeHtml(row.date)}</td>
      <td><span class="status-badge status-${String(row.status).toLowerCase().replace(/\s+/g, '-')} ">${escapeHtml(row.status)}</span></td>
      <td><button type="button" class="action-link">View</button></td>
    </tr>
  `).join('') : '<tr><td colspan="6" class="empty-state">No applications or messages yet.</td></tr>';
}

function renderApplicantsTable() {
  const attendeeTable = document.getElementById('attendee-table');
  const volunteerTable = document.getElementById('volunteer-table');
  const applicants = getApplicants();

  if (attendeeTable) {
    const tbody = attendeeTable.querySelector('tbody');
    tbody.innerHTML = applicants
      .filter((item) => item.type === 'Attendee')
      .map((row) => `
        <tr>
          <td><input type="checkbox" data-row-select="${row.id}" aria-label="Select ${escapeHtml(row.name)}"></td>
          <td>${escapeHtml(row.name)}</td>
          <td>${escapeHtml(row.email)}</td>
          <td>${escapeHtml(row.phone || '-')}</td>
          <td>${escapeHtml(row.date)}</td>
          <td><span class="status-badge status-${String(row.status).toLowerCase().replace(/\s+/g, '-')} ">${escapeHtml(row.status)}</span></td>
          <td><button type="button" class="action-link view-applicant-trigger" data-id="${row.id}">View</button></td>
        </tr>
      `)
      .join('');
  }

  if (volunteerTable) {
    const tbody = volunteerTable.querySelector('tbody');
    tbody.innerHTML = applicants
      .filter((item) => item.type === 'Volunteer')
      .map((row) => `
        <tr>
          <td><input type="checkbox" data-row-select="${row.id}" aria-label="Select ${escapeHtml(row.name)}"></td>
          <td>${escapeHtml(row.name)}</td>
          <td>${escapeHtml(row.email)}</td>
          <td>${escapeHtml(row.phone || '-')}</td>
          <td>${escapeHtml(row.date)}</td>
          <td><span class="status-badge status-${String(row.status).toLowerCase().replace(/\s+/g, '-')} ">${escapeHtml(row.status)}</span></td>
          <td><button type="button" class="action-link view-applicant-trigger" data-id="${row.id}">View</button></td>
        </tr>
      `)
      .join('');
  }
}

function renderMessagesTable() {
  const table = document.getElementById('messages-table');
  if (!table) return;
  const tbody = table.querySelector('tbody');
  tbody.innerHTML = getMessages().map((row) => `
    <tr>
      <td><input type="checkbox" data-row-select="${row.id}" aria-label="Select ${escapeHtml(row.sender)}"></td>
      <td>${escapeHtml(row.sender)}</td>
      <td>${escapeHtml(row.email)}</td>
      <td>${escapeHtml(row.subject)}</td>
      <td>${escapeHtml(row.date)}</td>
      <td><span class="${row.status === 'Unread' ? 'status-unread' : 'status-read'}">${escapeHtml(row.status)}</span></td>
      <td><button type="button" class="action-link view-message-trigger" data-id="${row.id}">View</button></td>
    </tr>
  `).join('');
}

function setStatusBadge(node, status) {
  if (!node) return;
  node.textContent = status;
  node.className = 'status-badge';
  const value = String(status).toLowerCase();
  if (value === 'approved') node.classList.add('status-approved');
  else if (value === 'rejected') node.classList.add('status-rejected');
  else if (value === 'pending') node.classList.add('status-pending');
  else node.classList.add('status-unread');
}

function bindApplicantActions() {
  document.querySelectorAll('.view-applicant-trigger').forEach((button) => {
    if (button.dataset.bound === 'true') return;
    button.dataset.bound = 'true';
    button.addEventListener('click', async () => {
      const id = Number(button.dataset.id);
      const selected = getApplicants().find((item) => item.id === id);
      if (!selected) return;

      const modal = document.getElementById('application-modal');
      if (!modal) return;

      modal.dataset.applicationId = String(id);
      document.getElementById('detail-name').textContent = selected.name;
      document.getElementById('detail-email').textContent = selected.email;
      document.getElementById('detail-phone').textContent = selected.phone || 'Not provided';
      document.getElementById('detail-date').textContent = selected.date;
      setStatusBadge(document.getElementById('detail-status'), selected.status);

      modal.classList.add('open');
    });
  });

  document.querySelectorAll('[data-action]').forEach((button) => {
    if (button.dataset.bound === 'true') return;
    button.dataset.bound = 'true';
    button.addEventListener('click', async () => {
      const action = button.dataset.action;
      const modal = document.getElementById('application-modal');
      const id = Number(modal?.dataset.applicationId);
      const applicants = getApplicants();
      const selected = applicants.find((item) => Number(item.id) === id);
      if (!selected) return;

      const nextStatus = action === 'approve' ? 'Approved' : action === 'reject' ? 'Rejected' : 'Pending';
      const updated = await updateApplicationStatus(selected.id, nextStatus);
      if (updated?.success) {
        await loadDashboardData();
        modal.classList.remove('open');
        if (updated.email_failed) {
          showMessage(document.getElementById('dashboard-message'), updated.message, 'error');
        }
      }
    });
  });
}

function bindMessageActions() {
  document.querySelectorAll('.view-message-trigger').forEach((button) => {
    if (button.dataset.bound === 'true') return;
    button.dataset.bound = 'true';
    button.addEventListener('click', () => {
      const id = Number(button.dataset.id);
      const selected = getMessages().find((item) => item.id === id);
      if (!selected) return;

      const modal = document.getElementById('message-modal');
      if (!modal) return;

      document.getElementById('message-subject').textContent = selected.subject;
      document.getElementById('message-sender').textContent = selected.sender;
      document.getElementById('message-email').textContent = selected.email;
      document.getElementById('message-date').textContent = selected.date;
      document.getElementById('message-body').textContent = `This is a real local admin record for ${selected.sender}. The item is saved in the browser and can be replaced with persisted database records once hosting is available.`;

      const statusNode = document.getElementById('message-status');
      if (statusNode) {
        statusNode.textContent = selected.status;
        statusNode.className = selected.status === 'Unread' ? 'status-unread' : 'status-read';
      }

      if (selected.status === 'Unread') {
        const messages = getMessages();
        const match = messages.find((item) => item.id === id);
        if (match) {
          match.status = 'Read';
          writeStorage(STORAGE_KEYS.messages, messages);
          renderMessagesTable();
        }
      }

      modal.classList.add('open');
    });
  });
}

const tablePages = {};

function tableRowsFor(tableId) {
  return Array.from(document.querySelectorAll(`#${tableId} tbody tr`));
}

function refreshTableView(tableId) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const panel = table.closest('.panel');
  const search = panel?.querySelector('[data-search]')?.value.toLowerCase() || '';
  const selects = panel?.querySelectorAll('.filter-group select') || [];
  const statusFilter = selects[0]?.value || '';
  const dateFilter = selects[1]?.value || '';
  const pageSize = 10;
  const page = tablePages[tableId] || 1;
  const matched = tableRowsFor(tableId).filter((row) => {
    const textMatch = row.textContent.toLowerCase().includes(search);
    const statusMatch = !statusFilter || statusFilter === 'All statuses' || statusFilter === 'All messages' || row.textContent.includes(statusFilter);
    let dateMatch = true;
    if (dateFilter && dateFilter !== 'All dates') {
      const days = dateFilter === 'Last 7 days' ? 7 : dateFilter === 'Last 30 days' ? 30 : 90;
      const dateText = row.querySelectorAll('td')[tableId === 'messages-table' ? 4 : 4]?.textContent;
      const parsed = dateText ? Date.parse(dateText) : NaN;
      dateMatch = Number.isNaN(parsed) || (Date.now() - parsed) <= days * 86400000;
    }
    return textMatch && statusMatch && dateMatch;
  });
  const maxPage = Math.max(1, Math.ceil(matched.length / pageSize));
  tablePages[tableId] = Math.min(page, maxPage);
  matched.forEach((row, index) => {
    row.style.display = index >= (tablePages[tableId] - 1) * pageSize && index < tablePages[tableId] * pageSize ? '' : 'none';
  });
  tableRowsFor(tableId).filter((row) => !matched.includes(row)).forEach((row) => { row.style.display = 'none'; });
  const indicator = document.querySelector(`[data-page-indicator="${tableId}"]`);
  if (indicator) indicator.textContent = `Page ${tablePages[tableId]} of ${maxPage}`;
}

function renderCharts() {
  const applicants = getApplicants();
  const statusData = ['Pending', 'Approved', 'Rejected'].map((status) => ({ label: status, value: applicants.filter((item) => item.status === status).length }));
  const sourceCounts = {};
  applicants.forEach((item) => {
    const source = item.referral_source || 'Not specified';
    sourceCounts[source] = (sourceCounts[source] || 0) + 1;
  });
  const sourceData = Object.entries(sourceCounts).map(([label, value]) => ({ label, value }));

  function draw(id, data) {
    const chart = document.getElementById(id);
    if (!chart) return;
    if (!data.length) {
      chart.innerHTML = '<p class="empty-state">No records yet.</p>';
      return;
    }
    const max = Math.max(1, ...data.map((item) => item.value));
    chart.innerHTML = data.map((item) => `
      <div class="bar-row"><span class="bar-label">${escapeHtml(item.label)}</span><div class="bar-track"><span class="bar-fill" style="width:${Math.round((item.value / max) * 100)}%"></span></div><strong>${escapeHtml(item.value)}</strong></div>
    `).join('');
  }

  draw('status-chart', statusData);
  draw('source-chart', sourceData);
}

function renderAuditLog(records = []) {
  const target = document.getElementById('audit-log');
  if (!target) return;
  if (!records.length) {
    target.innerHTML = '<p class="empty-state">No audit activity yet.</p>';
    return;
  }
  target.innerHTML = records.slice(0, 8).map((item) => `
    <div class="audit-item"><strong>${escapeHtml(item.action.replace(/_/g, ' '))}</strong><span>${escapeHtml(item.entity_type)} #${escapeHtml(item.entity_id)}</span><small>${escapeHtml(item.admin_email)} · ${escapeHtml(new Date(item.created_at).toLocaleString())}</small></div>
  `).join('');
}

function csvExport(tableId) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const rows = Array.from(table.querySelectorAll('tr')).map((row) => Array.from(row.children).slice(1, -1).map((cell) => `"${cell.textContent.trim().replace(/"/g, '""')}"`).join(','));
  const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `${tableId}.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
}

async function updateStatuses(entity, ids, status) {
  try {
    const csrfToken = await getCsrfToken();
    const { response, payload } = await apiRequest('../backend/api/admin-update.php', {
      method: 'POST',
      body: JSON.stringify({ entity, ids, status, _csrf: csrfToken })
    });
    if (!response.ok) throw new Error(payload?.message || 'Update failed.');
    if (Array.isArray(payload[entity])) writeStorage(entity === 'messages' ? STORAGE_KEYS.messages : STORAGE_KEYS.applicants, payload[entity]);
  } catch (error) {
    const records = entity === 'messages' ? getMessages() : getApplicants();
    records.forEach((record) => {
      if (ids.includes(Number(record.id))) record.status = status;
    });
    writeStorage(entity === 'messages' ? STORAGE_KEYS.messages : STORAGE_KEYS.applicants, records);
  }
}

async function updateApplicationStatus(id, status) {
  try {
    const csrfToken = await getCsrfToken();
    const { response, payload } = await apiRequest('../backend/api/admin-update-application.php', {
      method: 'POST',
      body: JSON.stringify({ id, status, _csrf: csrfToken })
    });
    if (!response.ok) throw new Error(payload?.message || 'Unable to update application status.');
    return payload;
  } catch (error) {
    showMessage(document.getElementById('dashboard-message'), error.message, 'error');
    return { success: false };
  }
}

async function loadDashboardData() {
  const { response, payload } = await apiRequest('../backend/api/admin-dashboard.php', { method: 'GET' });
  if (!response.ok || !payload?.success) throw new Error(payload?.message || 'Dashboard data is unavailable.');
  if (Array.isArray(payload.applications)) writeStorage(STORAGE_KEYS.applicants, payload.applications);
  if (Array.isArray(payload.messages)) writeStorage(STORAGE_KEYS.messages, payload.messages);
  renderOverview();
  renderApplicantsTable();
  renderMessagesTable();
  renderCharts();
  renderAuditLog(payload.audit_log || []);
  bindApplicantActions();
  bindMessageActions();
  refreshTableView('attendee-table');
  refreshTableView('volunteer-table');
  refreshTableView('messages-table');
}

function dashboardControlsInit() {
  document.querySelectorAll('[data-search], .filter-group select').forEach((control) => {
    control.addEventListener('input', () => {
      const table = control.closest('.panel')?.querySelector('.data-table');
      if (table) refreshTableView(table.id);
    });
    control.addEventListener('change', () => {
      const table = control.closest('.panel')?.querySelector('.data-table');
      if (table) refreshTableView(table.id);
    });
  });

  document.querySelectorAll('.pagination-btn').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.table;
      tablePages[id] = Math.max(1, (tablePages[id] || 1) + (button.dataset.pageAction === 'next' ? 1 : -1));
      refreshTableView(id);
    });
  });

  document.querySelectorAll('[data-select-all]').forEach((master) => {
    master.addEventListener('change', () => {
      document.querySelectorAll(`#${master.dataset.selectAll} [data-row-select]`).forEach((box) => { box.checked = master.checked; });
    });
  });

  document.querySelectorAll('.bulk-apply').forEach((button) => {
    button.addEventListener('click', async () => {
      const tableId = button.dataset.bulkTable;
      const status = document.querySelector(`[data-bulk-status="${tableId}"]`)?.value;
      const ids = Array.from(document.querySelectorAll(`#${tableId} [data-row-select]:checked`)).map((box) => Number(box.dataset.rowSelect));
      if (!status || !ids.length) return;
      if (tableId === 'attendee-table') {
        const results = await Promise.all(ids.map((id) => updateApplicationStatus(id, status)));
        if (results.every((result) => result?.success)) {
          await loadDashboardData();
          if (results.some((result) => result.email_failed)) {
            showMessage(document.getElementById('dashboard-message'), 'Application status updated, but the notification email could not be sent.', 'error');
          }
        }
        return;
      }
      await updateStatuses(tableId === 'messages-table' ? 'messages' : 'applications', ids, status);
      renderOverview(); renderApplicantsTable(); renderMessagesTable(); renderCharts(); bindApplicantActions(); bindMessageActions(); refreshTableView(tableId);
    });
  });

  document.querySelectorAll('.export-btn').forEach((button) => button.addEventListener('click', () => csvExport(button.dataset.export)));
  document.querySelector('.mark-all-read')?.addEventListener('click', async () => {
    const ids = getMessages().map((item) => Number(item.id));
    await updateStatuses('messages', ids, 'Read');
    renderMessagesTable(); bindMessageActions();
  });
}

function dashboardInit() {
  if (!document.body.classList.contains('admin-dashboard')) return;

  (async () => {
    try {
      const { response, payload } = await apiRequest('../backend/api/admin-status.php', { method: 'GET' });
      if (response.ok && payload?.authenticated) {
        const user = payload.user || getAuth();
        if (user) setAuth(user);
      } else {
        localStorage.removeItem(STORAGE_KEYS.auth);
        window.location.href = 'login.html';
      }
    } catch (error) {
      localStorage.removeItem(STORAGE_KEYS.auth);
      window.location.href = 'login.html';
    }
  })();

  if (!getAuth()) {
    window.location.href = 'login.html';
    return;
  }

  ensureLocalData();

  const settings = getSettings();
  const auth = getAuth();
  const avatar = document.querySelector('.avatar-mini');
  if (avatar && auth) avatar.textContent = (auth.name || 'AD').slice(0, 2).toUpperCase();

  const nameInput = document.querySelector('input[value="Admin User"]');
  const emailInput = document.querySelector('input[value="admin@thedriveleadership.org"], input[value="thedrive009@gmail.com"]');
  if (nameInput) nameInput.value = settings.adminName || auth?.name || 'Admin User';
  if (emailInput) emailInput.value = settings.email || auth?.email || 'thedrive009@gmail.com';

  document.querySelectorAll('.toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => toggle.classList.toggle('on'));
  });

  document.querySelectorAll('.nav-item').forEach((button) => {
    button.addEventListener('click', () => {
      const target = button.dataset.section;
      if (target === 'logout') {
        logout();
        return;
      }

      document.querySelectorAll('[data-panel]').forEach((panel) => {
        const shouldShow = panel.dataset.panel === target;
        panel.hidden = !shouldShow;
      });

      document.querySelectorAll('.nav-item').forEach((item) => {
        item.classList.toggle('active', item.dataset.section === target);
      });

      const title = document.querySelector('.page-title');
      if (title) title.textContent = target.charAt(0).toUpperCase() + target.slice(1);
    });
  });

  document.getElementById('admin-sign-out')?.addEventListener('click', logout);

  document.getElementById('mobile-sidebar-toggle')?.addEventListener('click', () => {
    document.querySelector('.sidebar')?.classList.toggle('open');
  });

  document.querySelectorAll('.close-btn').forEach((button) => {
    button.addEventListener('click', () => {
      button.closest('.modal')?.classList.remove('open');
    });
  });

  document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) modal.classList.remove('open');
    });
  });

  document.querySelectorAll('[data-search]').forEach((input) => {
    input.addEventListener('input', () => {
      const searchTerm = input.value.toLowerCase();
      const table = input.closest('.panel')?.querySelector('table');
      if (!table) return;
      table.querySelectorAll('tbody tr').forEach((row) => {
        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
      });
    });
  });

  renderOverview();
  renderApplicantsTable();
  renderMessagesTable();
  renderCharts();
  renderAuditLog();
  bindApplicantActions();
  bindMessageActions();
  dashboardControlsInit();

  loadDashboardData().catch(() => {
    localStorage.removeItem(STORAGE_KEYS.auth);
    window.location.href = 'login.html';
  });
}

if (document.getElementById('admin-login-form')) {
  loginPageInit();
}

if (document.getElementById('reset-request-form') || document.getElementById('reset-password-form')) {
  resetPasswordInit();
}

if (document.body.classList.contains('admin-dashboard')) {
  dashboardInit();
}
