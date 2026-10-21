<?php
require_once __DIR__ . '/../../db.php';
require_method('POST');
start_admin_session();
$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();
json_out(['success' => true]);
