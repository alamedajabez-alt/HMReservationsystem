# HM Lab Reserve

Hospitality Management laboratory reservations with **custodian approval** (HTML/CSS/JS + PHP + MySQL/MariaDB).

## Setup (XAMPP/WAMP)
1. Copy the whole folder to `htdocs/` (keep the `api/` folder structure!).
2. Start Apache **and MySQL**. phpMyAdmin → **Import** → `schema.sql`.
3. Browse to `http://localhost/hm-lab-reserve/setup_check.php` and fix every ❌ (it tells you exactly what's wrong).
4. Edit `config.php` → set your own `setup_key`. Open `admin.html` → create the first custodian account.
5. Students use `index.html`; the custodian approves requests in `admin.html`.
   Always open through `http://localhost/...` — never by double-clicking the HTML files.

## Flow
student submits → **pending** (slot is held, nobody else can book it) → custodian **approves** (QR entry pass unlocks)
or **rejects** with a reason (slot reopens). Students can cancel pending/approved bookings.

## Double-booking prevention
UI greys out taken shifts + API transaction checks + DB `UNIQUE` indexes (`uq_active_slot`, `uq_student_slot`).

## Production checklist
- Create a limited DB user (`optional_db_user.sql`) and set it in `config.php`; don't run as `root`.
- Delete `setup_check.php`; serve over HTTPS.
