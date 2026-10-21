<?php
// api/get_reservations.php
require_once '../db.php';

try {
    $stmt = $pdo->query("
        SELECT 
            r.id, r.ref_code, r.student_name, r.student_id, r.course_code, 
            r.reservation_date, r.time_shift, r.status, r.created_at,
            l.name AS lab_name, l.code AS lab_code, l.category
        FROM reservations r
        JOIN laboratories l ON r.lab_id = l.id
        WHERE r.status = 'confirmed'
        ORDER BY r.reservation_date ASC, r.time_shift ASC
    ");
    $reservations = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $reservations]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}