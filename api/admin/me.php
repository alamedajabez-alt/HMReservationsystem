<?php
// api/admin/me.php — session status + whether first-time setup is needed
require_once __DIR__ . '/../../db.php';
require_method('GET');
try {
    $needs = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() === 0;
    $admin = current_admin($pdo);
    json_out(['success' => true, 'data' => ['logged_in' => (bool)$admin, 'needs_setup' => $needs && !$admin, 'admin' => $admin]]);
} catch (Throwable $e) {
    error_log('admin/me: ' . $e->getMessage());
    fail('Server error. Re-import schema.sql and open setup_check.php to diagnose.', 500);
}
