<?php
// api/cancel_reservation.php  (POST {id, student_id}) — students cancel their own pending/approved booking
require_once __DIR__ . '/../db.php';
require_method('POST');

$input          = read_json();
$reservation_id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$student_id     = normalize_student_id((string)($input['student_id'] ?? ''));
if (!$reservation_id || !valid_student_id($student_id)) fail('Reservation ID and Student ID are required.');

try {
    $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled'
                           WHERE id = ? AND student_id = ? AND status IN ('pending','approved') AND reservation_date >= ?");
    $stmt->execute([$reservation_id, $student_id, date('Y-m-d')]);
    if ($stmt->rowCount() === 0) fail('Reservation not found, already closed, or can no longer be cancelled.', 404);
    json_out(['success' => true, 'message' => 'Reservation cancelled. The slot is open again.']);
} catch (Throwable $e) {
    error_log('cancel_reservation: ' . $e->getMessage());
    fail('Could not cancel the reservation.', 500);
}
