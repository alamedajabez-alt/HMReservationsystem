<?php
// api/get_reservations.php  (POST {student_id}) — only the caller's own upcoming bookings
require_once __DIR__ . '/../db.php';
require_method('POST');

$input      = read_json();
$student_id = normalize_student_id((string)($input['student_id'] ?? ''));
if (!valid_student_id($student_id)) fail('Please enter a valid Student / Instructor ID.');

try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.ref_code, r.student_name, r.student_id, r.course_code,
               r.reservation_date, r.time_shift, r.status, r.admin_note,
               l.name AS lab_name, l.code AS lab_code,
               GROUP_CONCAT(e.name ORDER BY e.name SEPARATOR ', ') AS equipment
        FROM reservations r
        JOIN laboratories l ON l.id = r.lab_id
        LEFT JOIN reservation_equipment re ON re.reservation_id = r.id
        LEFT JOIN equipment e ON e.id = re.equipment_id
        WHERE r.student_id = ? AND r.status IN ('pending','approved','rejected') AND r.reservation_date >= ?
        GROUP BY r.id, l.name, l.code
        ORDER BY r.reservation_date ASC, FIELD(r.time_shift, ?, ?, ?)
    ");
    $stmt->execute(array_merge([$student_id, date('Y-m-d')], array_keys(SHIFTS)));
    json_out(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('get_reservations: ' . $e->getMessage());
    fail('Could not load reservations. Re-import schema.sql if you updated the project.', 500);
}
