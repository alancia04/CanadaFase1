<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

chdir(PROJECT_ROOT);
require_once '../includes/template.inc.php';

$main = new Template('skins/canada/dtml/main');
$main->setContent('title', 'User groups | Canada');
$main->setContent('body',  '<h1>User groups</h1><p>TODO</p>');
$main->close();