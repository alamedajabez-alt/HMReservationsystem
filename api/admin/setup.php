<?php
// api/admin/setup.php — creates the FIRST custodian only (needs setup_key from config.php)
require_once __DIR__ . '/../../db.php';
require_method('POST');
$in = read_json();

$key      = (string)($in['setup_key'] ?? '');
$username = strtolower(trim((string)($in['username'] ?? '')));
$full     = trim((string)($in['full_name'] ?? ''));
$pass     = (string)($in['password'] ?? '');

if ($cfg['setup_key'] === 'change-this-setup-key') fail('Set your own private setup_key in config.php first, then try again.', 403);
if (!hash_equals($cfg['setup_key'], $key)) { usleep(500000); fail('Invalid setup key.', 403); }
if (!preg_match('/^[a-z0-9_.]{3,30}$/', $username)) fail('Username: 3-30 characters, lowercase letters, numbers, _ or .');
if (!preg_match("/^[\p{L}\p{M} .,'\-]{2,100}$/u", $full)) fail('Please enter a valid full name.');
if (strlen($pass) < 10 || strlen($pass) > 72) fail('Password must be 10-72 characters.');

try {
    $st = $pdo->prepare("INSERT INTO admins (username, full_name, password_hash)
                         SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admins)");
    $st->execute([$username, $full, password_hash($pass, PASSWORD_DEFAULT)]);
    if ($st->rowCount() === 0) fail('Setup is already complete. Log in instead.', 409);
    json_out(['success' => true, 'message' => 'Custodian account created. You can now log in.'], 201);
} catch (Throwable $e) {
    error_log('admin/setup: ' . $e->getMessage());
    fail('Server error during setup.', 500);
}
