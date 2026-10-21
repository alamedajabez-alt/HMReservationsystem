<?php
// setup_check.php — LOCAL diagnostic page. Open http://localhost/<folder>/setup_check.php. Delete before going live.
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Open this page from the server itself (localhost) only.'); }

$rows = [];
function row(string $label, bool $pass, string $detail = ''): bool { global $rows; $rows[] = [$pass, $label, $detail]; return $pass; }

row('PHP 7.4 or newer', version_compare(PHP_VERSION, '7.4', '>='), PHP_VERSION);
row('PDO MySQL extension', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'loaded' : 'Enable extension=pdo_mysql in php.ini and restart Apache');
row('Sessions available', function_exists('session_start'));
row('Opened through a web server (not file://)', isset($_SERVER['HTTP_HOST']), $_SERVER['HTTP_HOST'] ?? '');
row('api/ folder found next to this file', is_dir(__DIR__ . '/api') && is_file(__DIR__ . '/api/get_labs.php'), 'Expected: api/get_labs.php, api/create_reservation.php, ...');

$cfg = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : null;
row('config.php found', is_array($cfg), is_array($cfg) ? '' : 'Missing config.php');

$pdo = null;
if (is_array($cfg) && extension_loaded('pdo_mysql')) {
    try {
        $pdo = new PDO("mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4", $cfg['db_user'], $cfg['db_pass'],
                       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        row('Database connection', true, "Connected to '{$cfg['db_name']}' as '{$cfg['db_user']}'");
    } catch (PDOException $e) {
        row('Database connection', false, $e->getMessage() . '  → Import schema.sql first, and check db_user / db_pass in config.php');
    }
}
if ($pdo) {
    foreach (['laboratories', 'equipment', 'reservations', 'reservation_equipment', 'admins'] as $t) {
        try { row("Table `$t`", true, $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() . ' row(s)'); }
        catch (Throwable $e) { row("Table `$t`", false, 'Missing → re-import schema.sql'); }
    }
    try {
        $c = $pdo->query("SHOW COLUMNS FROM reservations LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        row('Schema is the new approval version', strpos($c['Type'] ?? '', 'pending') !== false, 'Old schema detected → re-import schema.sql');
    } catch (Throwable $e) { row('Schema is the new approval version', false, 'reservations table missing'); }
    try {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        row('Custodian account exists', $n > 0, $n > 0 ? "$n account(s)" : 'None yet → open admin.html and create the first one');
    } catch (Throwable $e) {}
    row('setup_key changed in config.php', ($cfg['setup_key'] ?? '') !== 'change-this-setup-key', 'Needed once, to create the first custodian');
}
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Setup check</title>
<style>body{font-family:system-ui,sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem;color:#0f172a}table{border-collapse:collapse;width:100%}td{border-bottom:1px solid #e2e8f0;padding:.6rem;vertical-align:top;font-size:.9rem}td:first-child{width:2rem}</style></head><body>
<h1>HM Lab Reserve — setup check</h1>
<table><?php foreach ($rows as [$pass, $label, $detail]): ?>
<tr><td><?= $pass ? '✅' : '❌' ?></td><td><strong><?= htmlspecialchars($label) ?></strong></td><td><?= htmlspecialchars($detail) ?></td></tr>
<?php endforeach; ?></table>
<p>Fix every ❌ from the top down, then reload this page. Delete this file when you deploy.</p></body></html>
