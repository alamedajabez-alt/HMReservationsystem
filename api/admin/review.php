<?php
// api/admin/review.php  (POST {id, action: approve|reject, note})  — custodian only
require_once __DIR__ . '/../../db.php';
require_method('POST');

try {
    $admin  = require_admin($pdo);
    $in     = read_json();
    $id     = filter_var($in['id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string)($in['action'] ?? '');
    $note   = trim((string)($in['note'] ?? ''));

    if (!$id || !in_array($action, ['approve', 'reject'], true)) fail('Invalid request.');
    if (mb_strlen($note) > 255) fail('Note is too long (max 255 characters).');
    if ($action === 'reject' && mb_strlen($note) < 3) fail('Please give a short reason for rejecting.');

    if ($action === 'approve') {
        $sql  = "UPDATE reservations SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), admin_note = ?
                 WHERE id = ? AND status = 'pending' AND reservation_date >= ?";
        $args = [$admin['id'], $note !== '' ? $note : null, $id, date('Y-m-d')];
    } else {
        $sql  = "UPDATE reservations SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), admin_note = ?
                 WHERE id = ? AND status IN ('pending','approved') AND reservation_date >= ?";
        $args = [$admin['id'], $note, $id, date('Y-m-d')];
    }
    $st = $pdo->prepare($sql);
    $st->execute($args);
    if ($st->rowCount() === 0) fail('Already processed, cancelled by the student, or the date has passed. Refresh the list.', 409);

    json_out(['success' => true, 'message' => $action === 'approve' ? 'Reservation approved.' : 'Reservation rejected. The slot is open again.']);
} catch (Throwable $e) {
    error_log('admin/review: ' . $e->getMessage());
    fail('Could not update the reservation.', 500);
}
