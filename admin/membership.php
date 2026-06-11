<?php
// memberships: scheletro

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

//query, controlli sui permessi

chdir(PROJECT_ROOT);
require_once '../includes/template.inc.php';

$main = new Template('skins/canada/dtml/main');
$main->setContent('title', 'Memberships | Canada');
$main->setContent('body',  '<h1>Memberships</h1><p>TODO</p>');
//topbar, nav, footer 
$main->close();