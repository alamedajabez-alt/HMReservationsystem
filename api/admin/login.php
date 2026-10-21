<?php
// api/admin/login.php — individual custodian login with lockout after 5 failed attempts
require_once __DIR__ . '/../../db.php';
require_method('POST');
$in = read_json();
$username = strtolower(trim((string)($in['username'] ?? '')));
$password = (string)($in['password'] ?? '');
if ($username === '' || $password === '' || strlen($password) > 200) fail('Enter your username and password.');

try {
    $st = $pdo->prepare("SELECT id, username, full_name, password_hash, failed_attempts,
                                (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked
                         FROM admins WHERE username = ? AND is_active = 1");
    $st->execute([$username]);
    $row = $st->fetch();

    if ($row && (int)$row['is_locked'] === 1) fail('Too many failed attempts. Try again in 15 minutes.', 429);

    // Always run password_verify so response time doesn't reveal whether the username exists
    $hash = $row ? $row['password_hash'] : password_hash('dummy-password', PASSWORD_DEFAULT);
    $ok   = password_verify($password, $hash) && $row;

    if (!$ok) {
        if ($row) {
            $n = (int)$row['failed_attempts'] + 1;
            $pdo->prepare("UPDATE admins SET failed_attempts = ?, locked_until = IF(? >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL) WHERE id = ?")
                ->execute([$n >= 5 ? 0 : $n, $n, $row['id']]);
        }
        usleep(400000);
        fail('Invalid username or password.', 401);
    }

    $pdo->prepare("UPDATE admins SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?")->execute([$row['id']]);
    start_admin_session();
    session_regenerate_id(true);                  // prevents session fixation
    $_SESSION['admin_id'] = (int)$row['id'];

    json_out(['success' => true, 'data' => ['id' => (int)$row['id'], 'username' => $row['username'], 'full_name' => $row['full_name']]]);
} catch (Throwable $e) {
    error_log('admin/login: ' . $e->getMessage());
    fail('Server error. Open setup_check.php to diagnose.', 500);
}
