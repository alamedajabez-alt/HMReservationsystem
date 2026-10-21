<?php
// db.php — shared bootstrap: config, DB connection, JSON helpers, request guards, admin session helpers.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';
date_default_timezone_set($cfg['timezone']);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

class ApiError extends RuntimeException {}

function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}
function fail(string $message, int $status = 400): void {
    json_out(['success' => false, 'message' => $message], $status);
}

// Same-origin only (no wildcard CORS)
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $o = parse_url($_SERVER['HTTP_ORIGIN']);
    $originHost = ($o['host'] ?? '') . (isset($o['port']) ? ':' . $o['port'] : '');
    if (strcasecmp($originHost, $_SERVER['HTTP_HOST'] ?? '') !== 0) fail('Cross-origin requests are not allowed.', 403);
}

function require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) { header('Allow: ' . $method); fail('Method not allowed.', 405); }
}
function read_json(): array {
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) fail('Content-Type must be application/json.', 415);
    $data = json_decode(file_get_contents('php://input', false, null, 0, 65536) ?: '', true);
    if (!is_array($data)) fail('Invalid JSON input.');
    return $data;
}

// ---- Business rules ----
const SHIFTS = [
    '08:00 AM - 11:00 AM' => ['08:00', '11:00'],
    '01:00 PM - 04:00 PM' => ['13:00', '16:00'],
    '05:00 PM - 08:00 PM' => ['17:00', '20:00'],
];
const MAX_DAYS_AHEAD         = 60;
const MAX_ACTIVE_PER_STUDENT = 5;
// Pending AND approved bookings both hold the slot; rejected/cancelled ones free it (enforced by schema.sql too).

function valid_date(string $d): bool {
    $dt = DateTime::createFromFormat('!Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
function normalize_student_id(string $v): string { return strtoupper(trim($v)); }
function valid_student_id(string $v): bool { return (bool)preg_match('/^[A-Z0-9\-]{3,30}$/', $v); }

// ---- Database ----
try {
    $pdo = new PDO("mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4", $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    fail('Cannot connect to the database. On the server, open http://localhost/<your-folder>/setup_check.php to see what is wrong.', 503);
}

// ---- Custodian (admin) session helpers ----
function start_admin_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('HMADMINSESS');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 1800) {   // 30 min idle timeout
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}
function current_admin(PDO $pdo): ?array {
    start_admin_session();
    $id = $_SESSION['admin_id'] ?? null;
    if (!$id) return null;
    $st = $pdo->prepare("SELECT id, username, full_name FROM admins WHERE id = ? AND is_active = 1");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function require_admin(PDO $pdo): array {
    $a = current_admin($pdo);
    if (!$a) fail('Please log in as a custodian.', 401);
    return $a;
}
