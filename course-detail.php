<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

// da implementare

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$main = new Template('skins/canada/dtml/main');
$main->setContent('title', 'Course detail | Canada');
$main->setContent('body',  '<h1>Course detail</h1><p></p>');
$main->close();