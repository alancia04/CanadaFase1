<?php
// configurazione globale. da modificare con i parametri del proprio ambiente.

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'canadafase1');
define('DB_USER', 'root');
define('DB_PASS', '');

// radice progetto (serve a chdir + require_once)
define('PROJECT_ROOT', dirname(__DIR__));

// se il sito non sta in root web cambiare qui
define('BASE_URL', '/');

// skin attiva (il template engine del docente la legge da $config)
$config = [
    'skin' => 'canada',
    'languages' => [],
];

// upload cert.medici
define('UPLOADS_DIR', PROJECT_ROOT . '/assets/uploads');
define('CERTIFICATES_DIR', UPLOADS_DIR . '/certificates');

ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

date_default_timezone_set('Europe/Rome');

