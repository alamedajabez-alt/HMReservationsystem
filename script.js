'use strict';

/* ---------- Helpers ---------- */
const $ = (id) => document.getElementById(id);
const pad = (n) => String(n).padStart(2, '0');
const toISODate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const MAX_DAYS_AHEAD = 60;
const EMPTY_AVAIL = { booked_shifts: [], equipment_busy: {} };

// Safe DOM builder: text is always inserted as text nodes (no innerHTML => no XSS)
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

async function api(endpoint, { method = 'GET', body, query } = {}) {
  if (location.protocol === 'file:') throw new Error('Open this site through http://localhost/ (XAMPP/WAMP), not by double-clicking the HTML file.');
  const url = 'api/' + endpoint + (query ? '?' + new URLSearchParams(query) : '');
  const res = await fetch(url, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : undefined,
    body: body ? JSON.stringify(body) : undefined,
  });
  let data;
  try { data = await res.json(); } catch { throw new Error('The server did not return valid data. Open setup_check.php on the server to diagnose.'); }
  if (!res.ok || !data.success) throw new Error(data.message || 'Request failed.');
  return data;
}

/* ---------- State & elements ---------- */
const state = {
  labs: [], equipment: [], filter: 'all',
  currentLab: null, availability: EMPTY_AVAIL, availToken: 0, studentId: '',
};

const labGrid = $('labGrid');
const bookingModal = $('bookingModal');
const passModal = $('passModal');
const bookingForm = $('bookingForm');
const shiftSelect = $('resTime');
const dateInput = $('resDate');
const formError = $('formError');
const slotHint = $('slotHint');
const submitBtn = $('submitBtn');
const tbody = $('reservationsTableBody');

/* ---------- Laboratories ---------- */
async function loadLabs() {
  try {
    state.labs = (await api('get_labs.php')).data;
    renderLabs();
  } catch (err) {
    labGrid.replaceChildren(h('p', { class: 'empty-state' }, 'Could not load laboratories. ', err.message));
  }
}

function renderLabs() {
  const list = state.filter === 'all' ? state.labs : state.labs.filter((l) => l.category === state.filter);
  if (!list.length) {
    labGrid.replaceChildren(h('p', { class: 'empty-state' }, 'No workstations available in this category.'));
    return;
  }
  labGrid.replaceChildren(...list.map((lab) =>
    h('div', { class: 'card' },
      h('div', {},
        h('div', { class: 'card-header' },
          h('span', { class: 'badge' }, 'Available'),
          h('span', { class: 'row-sub' }, `Capacity: ${lab.capacity}`)),
        h('h3', {}, lab.name),
        h('p', {}, lab.description || '')),
      h('button', { class: 'btn-book', type: 'button', onclick: () => openBookingModal(lab) }, 'Book Station'))
  ));
}

document.querySelectorAll('.filter-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    state.filter = btn.dataset.filter;
    renderLabs();
  });
});

/* ---------- Equipment ---------- */
async function loadEquipment() {
  try {
    state.equipment = (await api('get_equipment.php')).data;
    $('equipmentChecklist').replaceChildren(...state.equipment.map((eq) =>
      h('label', { class: 'checkbox-label', 'data-eq': eq.id },
        h('input', { type: 'checkbox', name: 'equipment', value: eq.id }),
        h('span', {}, eq.name))
    ));
  } catch {
    $('equipmentChecklist').replaceChildren(h('span', { class: 'row-sub' }, 'Equipment list unavailable.'));
  }
}

/* ---------- Booking modal & live availability ---------- */
function openBookingModal(lab) {
  state.currentLab = lab;
  state.availability = EMPTY_AVAIL;
  $('selectedLabId').value = lab.id;
  $('selectedLabTitle').textContent = lab.name;
  formError.hidden = true;

  const now = new Date(), max = new Date();
  max.setDate(max.getDate() + MAX_DAYS_AHEAD);
  dateInput.min = toISODate(now);
  dateInput.max = toISODate(max);

  if (state.studentId) $('studentId').value = state.studentId;
  refreshSlotOptions();
  bookingModal.classList.add('open');
  $('studentName').focus();
}

async function loadAvailability() {
  const date = dateInput.value;
  if (!state.currentLab || !date) { state.availability = EMPTY_AVAIL; refreshSlotOptions(); return; }
  const token = ++state.availToken;               // ignore out-of-order responses
  try {
    const res = await api('get_availability.php', { query: { lab_id: state.currentLab.id, date } });
    if (token !== state.availToken) return;
    state.availability = res.data;
  } catch {
    if (token !== state.availToken) return;
    state.availability = EMPTY_AVAIL;
  }
  refreshSlotOptions();
}

function refreshSlotOptions() {
  const date = dateInput.value;
  const now = new Date();
  const isToday = date === toISODate(now);
  const hhmm = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
  let selectedBlocked = false, openCount = 0, total = 0;

  [...shiftSelect.options].forEach((opt) => {
    if (!opt.value) return;
    if (!opt.dataset.label) opt.dataset.label = opt.textContent;
    const booked = state.availability.booked_shifts.includes(opt.value);
    const started = isToday && hhmm >= opt.dataset.start;
    opt.disabled = booked || started;
    opt.textContent = opt.dataset.label + (booked ? ' — Already booked' : started ? ' — Already started' : '');
    total++;
    if (!opt.disabled) openCount++;
    if (opt.disabled && opt.selected) selectedBlocked = true;
  });
  if (selectedBlocked) shiftSelect.value = '';

  slotHint.classList.remove('warn');
  slotHint.textContent = '';
  if (date && total && openCount === 0) {
    slotHint.textContent = 'This station has no open shifts on this date. Please pick another date or station.';
    slotHint.classList.add('warn');
  } else if (date) {
    slotHint.textContent = 'Shifts marked "Already booked" are taken (pending or approved) and cannot be selected.';
  }
  refreshEquipmentOptions();
}

function refreshEquipmentOptions() {
  const busy = (state.availability.equipment_busy[shiftSelect.value] || []).map(Number);
  document.querySelectorAll('#equipmentChecklist .checkbox-label').forEach((label) => {
    const cb = label.querySelector('input');
    const inUse = busy.includes(Number(cb.value));
    cb.disabled = inUse;
    if (inUse) cb.checked = false;
    label.classList.toggle('disabled', inUse);
    label.title = inUse ? 'Already reserved for this shift' : '';
  });
}

dateInput.addEventListener('change', loadAvailability);
shiftSelect.addEventListener('change', refreshEquipmentOptions);

function closeBooking() { bookingModal.classList.remove('open'); }
function closePass() { passModal.classList.remove('open'); }
$('closeModal').addEventListener('click', closeBooking);
$('closePassModal').addEventListener('click', closePass);
$('closePassBtn').addEventListener('click', closePass);
$('printPassBtn').addEventListener('click', () => window.print());
bookingModal.addEventListener('click', (e) => { if (e.target === bookingModal) closeBooking(); });
passModal.addEventListener('click', (e) => { if (e.target === passModal) closePass(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeBooking(); closePass(); } });

/* ---------- Submit booking ---------- */
bookingForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  if (submitBtn.disabled) return;                 // blocks double-click / double-submit
  formError.hidden = true;

  const payload = {
    lab_id: Number($('selectedLabId').value),
    student_name: $('studentName').value.trim(),
    student_id: $('studentId').value.trim().toUpperCase(),
    course_code: $('courseCode').value.trim(),
    reservation_date: dateInput.value,
    time_shift: shiftSelect.value,
    equipment_ids: [...document.querySelectorAll('input[name="equipment"]:checked')].map((cb) => Number(cb.value)),
  };

  submitBtn.disabled = true;
  submitBtn.textContent = 'Checking availability…';
  try {
    const result = await api('create_reservation.php', { method: 'POST', body: payload });

    closeBooking();
    bookingForm.reset();
    showPass({
      lab: state.currentLab.name, ref: result.data.ref_code, name: payload.student_name,
      sid: payload.student_id, course: payload.course_code, when: `${payload.reservation_date} | ${payload.time_shift}`, status: 'pending',
    });
    setStudentId(payload.student_id);
    fetchReservations();
  } catch (err) {
    formError.textContent = err.message;
    formError.hidden = false;
    loadAvailability();                            // someone may have just taken the slot: refresh the options
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Reservation Request';
  }
});

/* ---------- Digital pass ---------- */
function showPass(p) {
  const approved = p.status === 'approved';
  $('passBadgeHeader').textContent = approved ? 'OFFICIAL LABORATORY ENTRY PASS' : 'REQUEST RECEIVED — AWAITING CUSTODIAN APPROVAL';
  $('passStatus').textContent = approved ? 'Approved' : 'Pending approval';
  $('passQr').hidden = !approved;
  $('printPassBtn').hidden = !approved;
  $('passLabName').textContent = p.lab;
  $('passRefCode').textContent = p.ref;
  $('passStudentName').textContent = p.name;
  $('passStudentId').textContent = p.sid;
  $('passCourseCode').textContent = p.course;
  $('passDateTime').textContent = p.when;
  passModal.classList.add('open');
}

/* ---------- My reservations (scoped to the entered student ID) ---------- */
function setStudentId(id) {
  state.studentId = id;
  $('lookupStudentId').value = id;
  try { sessionStorage.setItem('hm_student_id', id); } catch { /* storage unavailable */ }
}

const messageRow = (text) => h('tr', {}, h('td', { colspan: '6', class: 'empty-cell' }, text));

$('lookupForm').addEventListener('submit', (e) => {
  e.preventDefault();
  setStudentId($('lookupStudentId').value.trim().toUpperCase());
  fetchReservations();
});

async function fetchReservations() {
  if (!state.studentId) { tbody.replaceChildren(messageRow('Enter your ID above to see your bookings.')); return; }
  try {
    const res = await api('get_reservations.php', { method: 'POST', body: { student_id: state.studentId } });
    renderReservations(res.data);
  } catch (err) {
    tbody.replaceChildren(messageRow(err.message));
  }
}

const STATUS_LABEL = { pending: 'Pending approval', approved: 'Approved', rejected: 'Rejected', cancelled: 'Cancelled' };

function renderReservations(rows) {
  if (!rows.length) { tbody.replaceChildren(messageRow('No upcoming reservations found for this ID.')); return; }
  tbody.replaceChildren(...rows.map((r) => {
    const actions = [];
    if (r.status === 'approved') {
      actions.push(h('button', {
        class: 'btn-action', type: 'button',
        onclick: () => showPass({ lab: r.lab_name, ref: r.ref_code, name: r.student_name, sid: r.student_id,
                                  course: r.course_code, when: `${r.reservation_date} | ${r.time_shift}`, status: r.status }),
      }, 'View Pass'));
    }
    if (r.status === 'pending' || r.status === 'approved') {
      actions.push(h('button', { class: 'btn-action cancel', type: 'button', onclick: () => cancelReservation(r.id) }, 'Cancel'));
    }
    return h('tr', {},
      h('td', {}, h('strong', {}, r.ref_code)),
      h('td', {}, r.student_name),
      h('td', {}, r.lab_name, r.equipment ? h('small', { class: 'row-sub' }, `+ ${r.equipment}`) : ''),
      h('td', {}, r.reservation_date, h('small', { class: 'row-sub' }, r.time_shift)),
      h('td', {}, h('span', { class: `status-badge status-${r.status}` }, STATUS_LABEL[r.status] || r.status),
        r.status === 'rejected' && r.admin_note ? h('small', { class: 'row-sub' }, `Reason: ${r.admin_note}`) : ''),
      h('td', {}, ...actions));
  }));
}

async function cancelReservation(id) {
  if (!confirm('Are you sure you want to cancel this reservation?')) return;
  try {
    await api('cancel_reservation.php', { method: 'POST', body: { id, student_id: state.studentId } });
    fetchReservations();
  } catch (err) {
    alert(err.message);
  }
}

/* ---------- Init ---------- */
document.addEventListener('DOMContentLoaded', () => {
  try { state.studentId = sessionStorage.getItem('hm_student_id') || ''; } catch { /* ignore */ }
  $('lookupStudentId').value = state.studentId;
  loadLabs();
  loadEquipment();
  fetchReservations();
});
