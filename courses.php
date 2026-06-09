<?php
// elenco pubblico dei corsi raggruppati per categoria

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$en  = current_lang() === 'en';

$courses = $pdo->query("
    SELECT c.id, c.title, c.slug, c.description, c.level, c.duration_minutes,
           cat.name AS cat_name, cat.slug AS cat_slug,
           (SELECT COUNT(*) FROM course_sessions cs
             WHERE cs.course_id = c.id AND cs.status='scheduled' AND cs.starts_at >= NOW()) AS upcoming_sessions
    FROM courses c
    JOIN course_categories cat ON cat.id = c.category_id
    WHERE c.is_published = 1
    ORDER BY cat.name, c.title
")->fetchAll();

// raggruppo per categoria
$byCat = [];
foreach ($courses as $c) {
    $byCat[$c['cat_name']][] = $c;
}

$body_html = '';
if (!$courses) {
    $h = $en ? 'No published activities' : 'Nessun corso pubblicato';
    $p = $en ? 'The calendar is being published. Check back soon.' : 'Il calendario è in fase di pubblicazione. Torna presto.';
    $body_html = '<article class="panel"><h2>' . $h . '</h2><p class="muted">' . $p . '</p></article>';
} else {
    $thActivity = $en ? 'activity' : 'attività';
    $thType     = $en ? 'level'    : 'tipo';
    $thDuration = $en ? 'duration' : 'durata';
    $thSlots    = $en ? 'upcoming slots' : 'slot in programma';
    $programmed = $en ? ' upcoming' : ' in programma';
    $none       = $en ? 'none' : 'nessuna';
    $detail     = $en ? 'details &raquo;' : 'scheda &raquo;';

    foreach ($byCat as $catName => $list) {
        $body_html .= '<article class="panel">';
        $body_html .= '<h2>' . e($catName) . '</h2>';
        $body_html .= '<table class="info"><thead><tr><th>' . $thActivity . '</th><th>' . $thType . '</th><th>' . $thDuration . '</th><th>' . $thSlots . '</th><th></th></tr></thead><tbody>';
        foreach ($list as $c) {
            $sessTag = (int)$c['upcoming_sessions'] > 0
                ? '<strong>' . (int)$c['upcoming_sessions'] . '</strong>' . $programmed
                : '<span class="muted">' . $none . '</span>';
            $body_html .= '<tr>';
            $body_html .= '<td><strong>' . e($c['title']) . '</strong></td>';
            $body_html .= '<td>' . e($c['level']) . '</td>';
            $body_html .= '<td>' . (int)$c['duration_minutes'] . ' min</td>';
            $body_html .= '<td>' . $sessTag . '</td>';
            $body_html .= '<td><a href="/course-detail.php?slug=' . e($c['slug']) . '">' . $detail . '</a></td>';
            $body_html .= '</tr>';
        }
        $body_html .= '</tbody></table>';
        $body_html .= '</article>';
    }
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/course-list');
$body->setContent('courses_block', $body_html);
$body->setContent('h_calendar', $en ? 'Activity calendar 2025/26' : 'Calendario corsi A.A. 2025/26');
$body->setContent('intro', $en
    ? 'List of currently published activities, grouped by category. To book a session you must be a member with an active card and a valid medical certificate.'
    : 'Elenco dei corsi attualmente pubblicati, raggruppati per categoria. Per prenotare una sessione devi essere iscritto, avere una tessera attiva e un certificato medico in corso di validità.');
$body->setContent('note', $en
    ? 'Booking closes when room capacity is reached.'
    : 'Le iscrizioni alle sessioni si chiudono al raggiungimento della capienza della sala.');

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', ($en ? 'Activity calendar' : 'Calendario corsi') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent('html_lang', current_lang());
$main->setContent('brand_uni', t('brand.university'));
$main->setContent('brand_center', t('brand.center'));
$main->setContent('footer', footer_html());
$main->close();
