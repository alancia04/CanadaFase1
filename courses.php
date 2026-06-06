<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

// da fare: query, controlli sui permessi, dati per il template

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$main = new Template('skins/canada/dtml/main');
$main->setContent('title', 'Courses | Canada');
$main->setContent('body',  '<h1>Courses</h1><p></p>');

$main->close();