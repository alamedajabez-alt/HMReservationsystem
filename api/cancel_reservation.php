<?php
// api/cancel_reservation.php
require_once '../db.php';

$input = json_decode(file_get_contents('php://input'), true);
$reservation_id = $input['id'] ?? null;

if (!$reservation_id) {
    echo json_encode(['success' => false, 'message' => 'Reservation ID required.']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$reservation_id]);

    echo json_encode(['success' => true, 'message' => 'Reservation cancelled successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}