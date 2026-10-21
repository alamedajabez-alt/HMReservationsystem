<?php
// api/create_reservation.php
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) fail('Invalid JSON input.');

$lab_id           = filter_var($input['lab_id'] ?? null, FILTER_VALIDATE_INT);
$student_name     = trim((string)($input['student_name'] ?? ''));
$student_id       = trim((string)($input['student_id'] ?? ''));
$course_code      = trim((string)($input['course_code'] ?? ''));
$reservation_date = (string)($input['reservation_date'] ?? '');
$time_shift       = (string)($input['time_shift'] ?? '');
$equipment_ids    = is_array($input['equipment_ids'] ?? null) ? $input['equipment_ids'] : [];

$shifts = ['08:00 AM - 11:00 AM', '01:00 PM - 04:00 PM', '05:00 PM - 08:00 PM'];

if (!$lab_id || $student_name === '' || $student_id === '' || $course_code === '') fail('All required fields must be filled out.');
if (mb_strlen($student_name) > 100 || mb_strlen($student_id) > 30 || mb_strlen($course_code) > 100) fail('One or more fields are too long.');
if (!in_array($time_shift, $shifts, true)) fail('Invalid time shift.');

$d = DateTime::createFromFormat('Y-m-d', $reservation_date);
if (!$d || $d->format('Y-m-d') !== $reservation_date) fail('Invalid date.');
if ($reservation_date < date('Y-m-d')) fail('Reservation date cannot be in the past.');

$equipment_ids = array_values(array_unique(array_filter(array_map('intval', $equipment_ids))));

try {
    $lab = $pdo->prepare("SELECT id FROM laboratories WHERE id = ? AND status = 'available'");
    $lab->execute([$lab_id]);
    if (!$lab->fetch()) fail('That workstation does not exist or is unavailable.', 404);

    $pdo->beginTransaction();

    // Retry on the rare ref_code collision; the UNIQUE slot index catches double-bookings
    $insert = $pdo->prepare("
        INSERT INTO reservations (ref_code, lab_id, student_name, student_id, course_code, reservation_date, time_shift)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ref_code = null;
    for ($i = 0; $i < 5 && !$ref_code; $i++) {
        $try = 'HM-RES-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
        try {
            $insert->execute([$try, $lab_id, $student_name, $student_id, $course_code, $reservation_date, $time_shift]);
            $ref_code = $try;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            if (strpos($e->getMessage(), 'uq_active_slot') !== false) {
                $pdo->rollBack();
                fail('This workstation is already reserved for the selected date and time shift.', 409);
            }
            // otherwise ref_code collision: loop and try a new code
        }
    }
    if (!$ref_code) throw new RuntimeException('Could not generate reference code.');
    $reservation_id = (int)$pdo->lastInsertId();

    if ($equipment_ids) {
        $eq = $pdo->prepare("INSERT INTO reservation_equipment (reservation_id, equipment_id) VALUES (?, ?)");
        foreach ($equipment_ids as $eq_id) $eq->execute([$reservation_id, $eq_id]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Reservation created successfully!',
        'data' => ['id' => $reservation_id, 'ref_code' => $ref_code]]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('create_reservation: ' . $e->getMessage());
    fail('Reservation failed. Please try again.', 500);
}
