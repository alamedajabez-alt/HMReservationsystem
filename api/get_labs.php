<?php
// api/get_labs.php
require_once '../db.php';

try {
    $stmt = $pdo->query("SELECT * FROM laboratories WHERE status = 'available'");
    $labs = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $labs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}