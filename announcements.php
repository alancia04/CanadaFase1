<?php
// elenco pubblico annunci

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$rows = $pdo->query("
    SELECT a.title, a.body, a.published_at, u.first_name, u.last_name
    FROM announcements a
    JOIN users u ON u.id = a.author_id
    WHERE a.is_published = 1
    ORDER BY a.published_at DESC LIMIT 50
")->fetchAll();

$en = current_lang() === 'en';
$listHtml = '';
if (!$rows) {
    $listHtml = '<p class="muted">' . ($en ? 'No published announcements.' : 'Nessun annuncio pubblicato.') . '</p>';
} else {
    foreach ($rows as $a) {
        $listHtml .= '<article class="panel">';
        $listHtml .= '<h2>' . e($a['title']) . '</h2>';
        $listHtml .= '<p class="muted small">' . e(format_datetime_it($a['published_at'])) . ' &middot; ' . e($a['first_name'] . ' ' . $a['last_name']) . '</p>';
        $listHtml .= '<div>' . nl2br(e($a['body'])) . '</div>';
        $listHtml .= '</article>';
    }
}

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/announcements-list');
$body->setContent('list', $listHtml);
$body->setContent('h_title', $en ? 'Announcements' : 'Comunicazioni');
$body->setContent('intro',   $en
    ? 'Notices published by staff (closures, schedule changes, events).'
    : 'Avvisi pubblicati dallo staff (chiusure, modifiche orario, eventi).');

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', ($en ? 'Announcements' : 'Comunicazioni') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();