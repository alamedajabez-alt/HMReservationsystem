<?php
// api/create_reservation.php
require_once __DIR__ . '/../db.php';
require_method('POST');

$input = read_json();

$lab_id           = filter_var($input['lab_id'] ?? null, FILTER_VALIDATE_INT);
$student_name     = trim((string)($input['student_name'] ?? ''));
$student_id       = normalize_student_id((string)($input['student_id'] ?? ''));
$course_code      = trim((string)($input['course_code'] ?? ''));
$reservation_date = (string)($input['reservation_date'] ?? '');
$time_shift       = (string)($input['time_shift'] ?? '');
$equipment_ids    = is_array($input['equipment_ids'] ?? null) ? $input['equipment_ids'] : [];

// ---------- Validation ----------
if (!$lab_id || $student_name === '' || $student_id === '' || $course_code === '') fail('All required fields must be filled out.');
if (!preg_match("/^[\p{L}\p{M} .,'\-]{2,100}$/u", $student_name))                  fail('Please enter a valid full name.');
if (!valid_student_id($student_id))                                                 fail('Student ID may only contain letters, numbers and dashes (3-30 characters).');
if (!preg_match("/^[\p{L}\p{N} .,'&()\/\-]{2,100}$/u", $course_code))               fail('Please enter a valid course / subject code.');
if (!isset(SHIFTS[$time_shift]))                                                    fail('Invalid time shift.');
if (!valid_date($reservation_date))                                                 fail('Invalid date.');

$today = date('Y-m-d');
if ($reservation_date < $today)                                                     fail('Reservation date cannot be in the past.');
if ($reservation_date > date('Y-m-d', strtotime('+' . MAX_DAYS_AHEAD . ' days')))   fail('Reservations can only be made up to ' . MAX_DAYS_AHEAD . ' days ahead.');
if ($reservation_date === $today && date('H:i') >= SHIFTS[$time_shift][0])          fail('That shift has already started. Please choose a later shift or date.');

$equipment_ids = array_values(array_unique(array_filter(array_map('intval', $equipment_ids), fn($v) => $v > 0)));
if (count($equipment_ids) > 20) fail('Too many equipment items selected.');

try {
    $lab = $pdo->prepare("SELECT id FROM laboratories WHERE id = ? AND status = 'available'");
    $lab->execute([$lab_id]);
    if (!$lab->fetch()) throw new ApiError('That workstation does not exist or is unavailable.', 404);

    $pdo->beginTransaction();

    // 1) Per-student cap (anti-spam / hoarding)
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM reservations
                          WHERE student_id = ? AND status IN ('pending','approved') AND reservation_date >= ?");
    $cnt->execute([$student_id, $today]);
    if ((int)$cnt->fetchColumn() >= MAX_ACTIVE_PER_STUDENT) {
        throw new ApiError('You already have ' . MAX_ACTIVE_PER_STUDENT . ' upcoming reservations. Cancel one before booking another.', 429);
    }

    // 2) Equipment: lock rows (serializes concurrent bookings), verify they exist, and verify they're free for that shift
    if ($equipment_ids) {
        $in = implode(',', array_fill(0, count($equipment_ids), '?'));

        $lock = $pdo->prepare("SELECT id FROM equipment WHERE id IN ($in) ORDER BY id FOR UPDATE");
        $lock->execute($equipment_ids);
        if (count($lock->fetchAll()) !== count($equipment_ids)) throw new ApiError('One or more selected equipment items do not exist.');

        $busy = $pdo->prepare("SELECT DISTINCT e.name
                               FROM reservation_equipment re
                               JOIN reservations r ON r.id = re.reservation_id AND r.status IN ('pending','approved')
                               JOIN equipment e ON e.id = re.equipment_id
                               WHERE r.reservation_date = ? AND r.time_shift = ? AND re.equipment_id IN ($in)");
        $busy->execute(array_merge([$reservation_date, $time_shift], $equipment_ids));
        $taken = $busy->fetchAll(PDO::FETCH_COLUMN);
        if ($taken) throw new ApiError('Already reserved for that shift: ' . implode(', ', $taken) . '. Please untick it or choose another shift.', 409);
    }

    // 3) Insert. The UNIQUE indexes uq_active_slot (lab+date+shift) and uq_student_slot (student+date+shift)
    //    are the final, race-proof guard: two simultaneous requests can never both succeed.
    $insert = $pdo->prepare("INSERT INTO reservations
        (ref_code, lab_id, student_name, student_id, course_code, reservation_date, time_shift)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    $ref_code = null;
    for ($i = 0; $i < 5 && $ref_code === null; $i++) {
        $try = 'HM-RES-' . strtoupper(bin2hex(random_bytes(4)));
        try {
            $insert->execute([$try, $lab_id, $student_name, $student_id, $course_code, $reservation_date, $time_shift]);
            $ref_code = $try;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) !== 1062) throw $e;
            $m = $e->getMessage();
            if (strpos($m, 'uq_active_slot') !== false)  throw new ApiError('This workstation is already reserved for that date and shift. Please pick another shift or station.', 409);
            if (strpos($m, 'uq_student_slot') !== false) throw new ApiError('You already have a reservation on that date and shift (in another station). One booking per shift only.', 409);
            // otherwise: ref_code collision -> loop and generate a new one
        }
    }
    if ($ref_code === null) throw new RuntimeException('Could not generate reference code.');

    $reservation_id = (int)$pdo->lastInsertId();

    if ($equipment_ids) {
        $eq = $pdo->prepare("INSERT INTO reservation_equipment (reservation_id, equipment_id) VALUES (?, ?)");
        foreach ($equipment_ids as $eq_id) $eq->execute([$reservation_id, $eq_id]);
    }

    $pdo->commit();

    json_out(['success' => true, 'message' => 'Request submitted. It is now awaiting custodian approval.',
              'data' => ['id' => $reservation_id, 'ref_code' => $ref_code, 'student_id' => $student_id, 'status' => 'pending']], 201);

} catch (ApiError $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('create_reservation: ' . $e->getMessage());
    fail('Reservation failed. Please try again.', 500);
}
