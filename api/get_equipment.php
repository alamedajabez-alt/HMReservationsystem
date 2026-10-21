<?php
// api/get_equipment.php
require_once __DIR__ . '/../db.php';
require_method('GET');

try {
    json_out(['success' => true, 'data' => $pdo->query("SELECT id, name FROM equipment ORDER BY id")->fetchAll()]);
} catch (Throwable $e) {
    error_log('get_equipment: ' . $e->getMessage());
    fail('Could not load equipment.', 500);
}
