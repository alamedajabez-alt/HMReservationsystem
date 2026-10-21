<?php
// api/get_availability.php?lab_id=1&date=2026-11-02
// Public, anonymous data only: which shifts are taken and which equipment is in use. No personal info.
require_once __DIR__ . '/../db.php';
require_method('GET');

$lab_id = filter_var($_GET['lab_id'] ?? null, FILTER_VALIDATE_INT);
$date   = (string)($_GET['date'] ?? '');
if (!$lab_id || !valid_date($date)) fail('Invalid laboratory or date.');

try {
    $s = $pdo->prepare("SELECT time_shift FROM reservations
                        WHERE lab_id = ? AND reservation_date = ? AND status IN ('pending','approved')");
    $s->execute([$lab_id, $date]);
    $booked = $s->fetchAll(PDO::FETCH_COLUMN);

    $e = $pdo->prepare("SELECT r.time_shift, re.equipment_id
                        FROM reservation_equipment re
                        JOIN reservations r ON r.id = re.reservation_id
                        WHERE r.reservation_date = ? AND r.status IN ('pending','approved')");
    $e->execute([$date]);
    $busy = [];
    foreach ($e->fetchAll() as $row) $busy[$row['time_shift']][] = (int)$row['equipment_id'];

    json_out(['success' => true, 'data' => ['booked_shifts' => $booked, 'equipment_busy' => (object)$busy]]);
} catch (Throwable $ex) {
    error_log('get_availability: ' . $ex->getMessage());
    fail('Could not check availability.', 500);
}
