<?php
// announcements: scheletro

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

// Da fare: query, controlli sui permessi, dati per il template

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$main = new Template('skins/canada/dtml/main');
$main->setContent('title', 'Announcements | Canada');
$main->setContent('body',  '<h1>Announcements</h1>');
// Da fare: topbar, nav, footer
$main->close();