<?php
// api/admin/create_admin.php — a logged-in custodian adds another individual custodian account
require_once __DIR__ . '/../../db.php';
require_method('POST');

try {
    require_admin($pdo);
    $in       = read_json();
    $username = strtolower(trim((string)($in['username'] ?? '')));
    $full     = trim((string)($in['full_name'] ?? ''));
    $pass     = (string)($in['password'] ?? '');

    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $username)) fail('Username: 3-30 characters, lowercase letters, numbers, _ or .');
    if (!preg_match("/^[\p{L}\p{M} .,'\-]{2,100}$/u", $full)) fail('Please enter a valid full name.');
    if (strlen($pass) < 10 || strlen($pass) > 72) fail('Password must be 10-72 characters.');

    try {
        $pdo->prepare("INSERT INTO admins (username, full_name, password_hash) VALUES (?, ?, ?)")
            ->execute([$username, $full, password_hash($pass, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) fail('That username is already taken.', 409);
        throw $e;
    }
    json_out(['success' => true, 'message' => 'Custodian account created.'], 201);
} catch (Throwable $e) {
    error_log('admin/create_admin: ' . $e->getMessage());
    fail('Could not create the account.', 500);
}
