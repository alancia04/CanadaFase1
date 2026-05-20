<?php

require_once __DIR__ . '/session.php';

if (!isset($_SESSION['lang']) || !in_array($_SESSION['lang'], ['it','en'], true)) {
    $_SESSION['lang'] = 'it';
}

function current_lang(): string {
    return $_SESSION['lang'] ?? 'it';
}

// carica le stringhe del file una sola volta per request
function _lang_strings(): array {
    static $cache = null;
    if ($cache === null) {
        $file = __DIR__ . '/../lang/' . current_lang() . '.php';
        $cache = file_exists($file) ? require $file : [];
    }
    return $cache;
}

// shortcut per le stringhe ricorrenti. chiave mancante -> $fallback o la chiave stessa.
function t(string $key, ?string $fallback = null): string {
    $s = _lang_strings();
    return $s[$key] ?? ($fallback ?? $key);
}

function t_raw(string $key, ?string $fallback = null): string {
    return t($key, $fallback);
}

function other_lang(): string {
    return current_lang() === 'it' ? 'en' : 'it';
}
