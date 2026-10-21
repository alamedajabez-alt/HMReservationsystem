// DOM Elements
const labGrid = document.getElementById('labGrid');
const filterBtns = document.querySelectorAll('.filter-btn');
const bookingModal = document.getElementById('bookingModal');
const closeModalBtn = document.getElementById('closeModal');
const selectedLabTitle = document.getElementById('selectedLabTitle');
const selectedLabIdInput = document.getElementById('selectedLabId');
const bookingForm = document.getElementById('bookingForm');
const reservationsTableBody = document.getElementById('reservationsTableBody');

const passModal = document.getElementById('passModal');
const closePassModal = document.getElementById('closePassModal');
const closePassBtn = document.getElementById('closePassBtn');

// Pass elements
const passLabName = document.getElementById('passLabName');
const passRefCode = document.getElementById('passRefCode');
const passStudentName = document.getElementById('passStudentName');
const passStudentId = document.getElementById('passStudentId');
const passCourseCode = document.getElementById('passCourseCode');
const passDateTime = document.getElementById('passDateTime');

// 1. Fetch Laboratories from PHP Backend
async function fetchLaboratories(category = 'all') {
  try {
    const response = await fetch('api/get_labs.php');
    const result = await response.json();
    
    if (result.success) {
      renderLabs(result.data, category);
    } else {
      console.error('Failed to load labs:', result.message);
    }
  } catch (error) {
    console.error('Network error fetching labs:', error);
  }
}

// Render Lab Cards
function renderLabs(labs, filter) {
  labGrid.innerHTML = '';
  
  const filtered = filter === 'all' 
    ? labs 
    : labs.filter(lab => lab.category === filter);

  if (filtered.length === 0) {
    labGrid.innerHTML = `<p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No workstations available in this category.</p>`;
    return;
  }

  filtered.forEach(lab => {
    const card = document.createElement('div');
    card.className = 'card';
    card.innerHTML = `
      <div>
        <div class="card-header">
          <span class="badge">Available</span>
          <span style="font-size: 0.75rem; color: var(--text-muted);">Capacity: ${lab.capacity}</span>
        </div>
        <h3>${lab.name}</h3>
        <p>${lab.description}</p>
      </div>
      <button class="btn-book" onclick="openBookingModal(${lab.id}, '${lab.name}')">Book Station</button>
    `;
    labGrid.appendChild(card);
  });
}

// Filter Button Click Handler
filterBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    filterBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    fetchLaboratories(btn.dataset.filter);
  });
});

// Open Booking Modal
function openBookingModal(labId, labName) {
  selectedLabIdInput.value = labId;
  selectedLabTitle.textContent = labName;
  bookingModal.classList.add('open');
}

// Close Modals
closeModalBtn.addEventListener('click', () => bookingModal.classList.remove('open'));
closePassModal.addEventListener('click', () => passModal.classList.remove('open'));
closePassBtn.addEventListener('click', () => passModal.classList.remove('open'));

bookingModal.addEventListener('click', (e) => {
  if (e.target === bookingModal) bookingModal.classList.remove('open');
});
passModal.addEventListener('click', (e) => {
  if (e.target === passModal) passModal.classList.remove('open');
});

// 2. Handle Form Submission to PHP Backend
bookingForm.addEventListener('submit', async (e) => {
  e.preventDefault();

  const lab_id = selectedLabIdInput.value;
  const student_name = document.getElementById('studentName').value;
  const student_id = document.getElementById('studentId').value;
  const course_code = document.getElementById('courseCode').value;
  const reservation_date = document.getElementById('resDate').value;
  const time_shift = document.getElementById('resTime').value;

  // Gather checked equipment IDs
  const equipment_ids = [];
  document.querySelectorAll('input[name="equipment"]:checked').forEach(cb => {
    equipment_ids.push(parseInt(cb.value));
  });

  const payload = {
    lab_id,
    student_name,
    student_id,
    course_code,
    reservation_date,
    time_shift,
    equipment_ids
  };

  try {
    const response = await fetch('api/create_reservation.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (result.success) {
      bookingModal.classList.remove('open');
      bookingForm.reset();

      // Populate Digital Pass Modal
      passLabName.textContent = selectedLabTitle.textContent;
      passRefCode.textContent = result.data.ref_code;
      passStudentName.textContent = student_name;
      passStudentId.textContent = student_id;
      passCourseCode.textContent = course_code;
      passDateTime.textContent = `${reservation_date} | ${time_shift}`;

      passModal.classList.add('open');
      fetchReservations(); // Refresh table
    } else {
      alert(`Reservation Error: ${result.message}`);
    }
  } catch (error) {
    console.error('Submission error:', error);
    alert('Failed to connect to the server.');
  }
});

// 3. Fetch Active Reservations for Table
async function fetchReservations() {
  try {
    const response = await fetch('api/get_reservations.php');
    const result = await response.json();

    if (result.success) {
      renderReservations(result.data);
    }
  } catch (error) {
    console.error('Error fetching reservations:', error);
  }
}

function renderReservations(reservations) {
  reservationsTableBody.innerHTML = '';

  if (reservations.length === 0) {
    reservationsTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No active reservations found.</td></tr>`;
    return;
  }

  reservations.forEach(res => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><strong>${res.ref_code}</strong></td>
      <td>${res.student_name}</td>
      <td>${res.lab_name}</td>
      <td>${res.reservation_date}<br><small style="color:var(--text-muted);">${res.time_shift}</small></td>
      <td><span class="status-badge">${res.status}</span></td>
      <td>
        <button class="btn-action" onclick="viewPass('${res.lab_name}', '${res.ref_code}', '${res.student_name}', '${res.student_id}', '${res.course_code}', '${res.reservation_date} | ${res.time_shift}')">View Pass</button>
        <button class="btn-action cancel" onclick="cancelReservation(${res.id})">Cancel</button>
      </td>
    `;
    reservationsTableBody.appendChild(tr);
  });
}

// View Pass from Table
function viewPass(labName, refCode, studentName, studentId, courseCode, dateTime) {
  passLabName.textContent = labName;
  passRefCode.textContent = refCode;
  passStudentName.textContent = studentName;
  passStudentId.textContent = studentId;
  passCourseCode.textContent = courseCode;
  passDateTime.textContent = dateTime;
  passModal.classList.add('open');
}

// 4. Cancel Reservation
async function cancelReservation(id) {
  if (!confirm('Are you sure you want to cancel this reservation?')) return;

  try {
    const response = await fetch('api/cancel_reservation.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });

    const result = await response.json();
    if (result.success) {
      fetchReservations();
    } else {
      alert(`Error: ${result.message}`);
    }
  } catch (error) {
    console.error('Cancel error:', error);
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  fetchLaboratories();
  fetchReservations();
});