<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

//da fare le query, controlli sui permessi, dati per il template

chdir(PROJECT_ROOT);
require_once '../includes/template.inc.php';

$main = new Template('skins/canada/dtml/main');
$main->setContent('title', 'Membership types | Canada');
$main->setContent('body',  '<h1>Membership types</h1><p>TODO</p>');
//da fare topbar, nav, footer
$main->close();