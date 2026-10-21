<?php
// api/get_labs.php
require_once __DIR__ . '/../db.php';
require_method('GET');

try {
    $stmt = $pdo->query("SELECT id, code, name, category, description, capacity
                         FROM laboratories WHERE status = 'available' ORDER BY category, code");
    json_out(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('get_labs: ' . $e->getMessage());
    fail('Could not load laboratories.', 500);
}
