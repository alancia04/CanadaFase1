<?php

if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('CANADAGYM');
    session_start();
}

if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    $_SESSION['user'] = [
        'username' => '',
        'name'     => '',
        'surname'  => '',
        'email'    => '',
    ];
}

// lingua default italiana 
if (!isset($_SESSION['lang']) || !in_array($_SESSION['lang'], ['it','en'], true)) {
    $_SESSION['lang'] = 'it';
}
