<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

$to   = $_GET['to']   ?? 'it';
$next = $_GET['next'] ?? '/';

if (in_array($to, ['it','en'], true)) {
    $_SESSION['lang'] = $to;
}

if (!is_string($next) || $next === '' || $next[0] !== '/') {
    $next = '/';
}

header('Location: ' . $next);
exit;