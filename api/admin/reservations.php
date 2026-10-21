<?php
// api/admin/reservations.php?status=pending|approved|rejected|cancelled|all   (custodian only)
require_once __DIR__ . '/../../db.php';
require_method('GET');

try {
    require_admin($pdo);
    $status = (string)($_GET['status'] ?? 'pending');
    if (!in_array($status, ['pending', 'approved', 'rejected', 'cancelled', 'all'], true)) fail('Invalid status filter.');
    $dir = $status === 'pending' ? 'ASC' : 'DESC';

    $stmt = $pdo->prepare("
        SELECT r.id, r.ref_code, r.student_name, r.student_id, r.course_code, r.reservation_date, r.time_shift,
               r.status, r.created_at, r.admin_note, r.reviewed_at,
               l.name AS lab_name, l.code AS lab_code, a.full_name AS reviewed_by,
               GROUP_CONCAT(e.name ORDER BY e.name SEPARATOR ', ') AS equipment
        FROM reservations r
        JOIN laboratories l ON l.id = r.lab_id
        LEFT JOIN admins a ON a.id = r.reviewed_by
        LEFT JOIN reservation_equipment re ON re.reservation_id = r.id
        LEFT JOIN equipment e ON e.id = re.equipment_id
        WHERE (? = 'all' OR r.status = ?)
        GROUP BY r.id, l.name, l.code, a.full_name
        ORDER BY r.reservation_date $dir, FIELD(r.time_shift, ?, ?, ?)
        LIMIT 300
    ");
    $stmt->execute(array_merge([$status, $status], array_keys(SHIFTS)));

    $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];
    foreach ($pdo->query("SELECT status, COUNT(*) c FROM reservations GROUP BY status")->fetchAll() as $c) $counts[$c['status']] = (int)$c['c'];

    json_out(['success' => true, 'data' => $stmt->fetchAll(), 'counts' => $counts]);
} catch (Throwable $e) {
    error_log('admin/reservations: ' . $e->getMessage());
    fail('Could not load reservations.', 500);
}
