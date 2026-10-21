'use strict';

const $ = (id) => document.getElementById(id);

function h(tag, props = {}, ...children) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(props)) {
    if (k === 'class') node.className = v;
    else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
    else node.setAttribute(k, v);
  }
  node.append(...children);
  return node;
}

async function api(path, { method = 'GET', body, query } = {}) {
  if (location.protocol === 'file:') throw new Error('Open this page through http://localhost/ (XAMPP/WAMP), not by double-clicking the file.');
  const res = await fetch('api/' + path + (query ? '?' + new URLSearchParams(query) : ''), {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : undefined,
    body: body ? JSON.stringify(body) : undefined,
  });
  let data;
  try { data = await res.json(); } catch { throw new Error('The server did not return valid data. Open setup_check.php on the server to diagnose.'); }
  if (!res.ok || !data.success) { const e = new Error(data.message || 'Request failed.'); e.status = res.status; throw e; }
  return data;
}

function msg(el, text) { el.textContent = text || ''; el.hidden = !text; }
const views = ['loginView', 'setupView', 'dashView'];
function show(id) {
  views.forEach((v) => { $(v).hidden = v !== id; });
  const inDash = id === 'dashView';
  $('logoutBtn').hidden = !inDash;
  if (!inDash) $('adminWho').hidden = true;
}

const state = { status: 'pending', rejectId: null };
const STATUS_LABEL = { pending: 'Pending', approved: 'Approved', rejected: 'Rejected', cancelled: 'Cancelled' };

/* ---------- session ---------- */
async function init() {
  try {
    const { data } = await api('admin/me.php');
    if (data.logged_in) enterDash(data.admin);
    else show(data.needs_setup ? 'setupView' : 'loginView');
  } catch (err) {
    show('loginView');
    msg($('globalMsg'), err.message);
  }
}

function enterDash(admin) {
  show('dashView');
  $('adminWho').textContent = `Signed in as ${admin.full_name}`;
  $('adminWho').hidden = false;
  msg($('globalMsg'), '');
  loadList();
}

$('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  msg($('loginMsg'), '');
  try {
    const { data } = await api('admin/login.php', { method: 'POST', body: { username: $('loginUser').value, password: $('loginPass').value } });
    $('loginPass').value = '';
    enterDash(data);
  } catch (err) { msg($('loginMsg'), err.message); }
});

$('setupForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  msg($('setupMsg'), '');
  try {
    await api('admin/setup.php', { method: 'POST', body: {
      setup_key: $('setupKey').value, full_name: $('setupName').value.trim(),
      username: $('setupUser').value.trim().toLowerCase(), password: $('setupPass').value } });
    $('setupForm').reset();
    show('loginView');
    msg($('loginMsg'), 'Account created. Please log in.');
  } catch (err) { msg($('setupMsg'), err.message); }
});

$('logoutBtn').addEventListener('click', async () => {
  try { await api('admin/logout.php', { method: 'POST', body: {} }); } catch { /* ignore */ }
  show('loginView');
});

/* ---------- list ---------- */
document.querySelectorAll('#adminTabs .filter-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#adminTabs .filter-btn').forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    state.status = btn.dataset.status;
    loadList();
  });
});

const tbody = $('adminTableBody');
const messageRow = (t) => h('tr', {}, h('td', { colspan: '6', class: 'empty-cell' }, t));

async function loadList() {
  try {
    const res = await api('admin/reservations.php', { query: { status: state.status } });
    for (const [k, v] of Object.entries(res.counts)) { const el = $('cnt-' + k); if (el) el.textContent = v ? `(${v})` : ''; }
    render(res.data);
  } catch (err) {
    if (err.status === 401) { show('loginView'); msg($('loginMsg'), 'Your session expired. Please log in again.'); return; }
    tbody.replaceChildren(messageRow(err.message));
  }
}

function render(rows) {
  if (!rows.length) { tbody.replaceChildren(messageRow('Nothing here.')); return; }
  tbody.replaceChildren(...rows.map((r) => {
    const actions = [];
    if (r.status === 'pending') actions.push(h('button', { class: 'btn-action approve', type: 'button', onclick: () => review(r.id, 'approve') }, 'Approve'));
    if (r.status === 'pending' || r.status === 'approved') actions.push(h('button', { class: 'btn-action cancel', type: 'button', onclick: () => openReject(r) }, r.status === 'approved' ? 'Revoke' : 'Reject'));
    const reviewed = r.reviewed_by ? `by ${r.reviewed_by}` : '';
    return h('tr', {},
      h('td', {}, h('strong', {}, r.ref_code), h('small', { class: 'row-sub' }, `Requested ${r.created_at}`)),
      h('td', {}, r.student_name, h('small', { class: 'row-sub' }, `ID: ${r.student_id}`), h('small', { class: 'row-sub' }, r.course_code)),
      h('td', {}, `${r.lab_name} (${r.lab_code})`, r.equipment ? h('small', { class: 'row-sub' }, `+ ${r.equipment}`) : ''),
      h('td', {}, r.reservation_date, h('small', { class: 'row-sub' }, r.time_shift)),
      h('td', {}, h('span', { class: `status-badge status-${r.status}` }, STATUS_LABEL[r.status] || r.status),
        reviewed ? h('small', { class: 'row-sub' }, reviewed) : '',
        r.admin_note ? h('small', { class: 'row-sub' }, `Note: ${r.admin_note}`) : ''),
      h('td', {}, ...actions));
  }));
}

async function review(id, action, note = '') {
  try {
    await api('admin/review.php', { method: 'POST', body: { id, action, note } });
    msg($('globalMsg'), '');
    loadList();
    return true;
  } catch (err) {
    if (err.status === 401) { show('loginView'); msg($('loginMsg'), 'Your session expired. Please log in again.'); return false; }
    msg($('globalMsg'), err.message);
    loadList();
    return false;
  }
}

/* ---------- reject modal ---------- */
function openReject(r) {
  state.rejectId = r.id;
  $('rejectTitle').textContent = `${r.status === 'approved' ? 'Revoke' : 'Reject'} ${r.ref_code}`;
  $('rejectNote').value = '';
  msg($('rejectMsg'), '');
  $('rejectModal').classList.add('open');
  $('rejectNote').focus();
}
const closeReject = () => $('rejectModal').classList.remove('open');
$('closeReject').addEventListener('click', closeReject);
$('rejectModal').addEventListener('click', (e) => { if (e.target === $('rejectModal')) closeReject(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeReject(); });
$('confirmReject').addEventListener('click', async () => {
  const note = $('rejectNote').value.trim();
  if (note.length < 3) { msg($('rejectMsg'), 'Please give a short reason.'); return; }
  if (await review(state.rejectId, 'reject', note)) closeReject();
});

/* ---------- add custodian ---------- */
$('newAdminForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  msg($('newAdminMsg'), '');
  try {
    await api('admin/create_admin.php', { method: 'POST', body: {
      full_name: $('newName').value.trim(), username: $('newUser').value.trim().toLowerCase(), password: $('newPass').value } });
    $('newAdminForm').reset();
    msg($('newAdminMsg'), 'Custodian account created.');
  } catch (err) { msg($('newAdminMsg'), err.message); }
});

document.addEventListener('DOMContentLoaded', init);
