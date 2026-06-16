<?php
// sessions: scheletro. la logica completa arriva nello step Implementa.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

// TODO: query, controlli sui permessi, dati per il template

chdir(PROJECT_ROOT);
require_once '../includes/template.inc.php';

$main = new Template('skins/canada/dtml/main');
$main->setContent('title', 'Sessions | Canada');
$main->setContent('body',  '<h1>Sessions</h1><p>TODO</p>');
// TODO: topbar, nav, footer (gli helper sono in Fetta 0)
$main->close();