<?php
// scheda di un singolo corso e sessioni future con posti residui

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') redirect('/courses.php');

$pdo = db();
$stmt = $pdo->prepare("
    SELECT c.*, cat.name AS cat_name
    FROM courses c
    JOIN course_categories cat ON cat.id = c.category_id
    WHERE c.slug = :s AND c.is_published = 1
    LIMIT 1
");
$stmt->execute([':s' => $slug]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><h1>Corso non trovato</h1><p><a href="/courses.php">torna al calendario</a></p>';
    exit;
}

// sessioni della prossima settimana (e oltre)
$stmt = $pdo->prepare("
    SELECT cs.id, cs.starts_at, cs.ends_at, cs.capacity, cs.status,
           r.name AS room_name,
           (SELECT COUNT(*) FROM bookings b WHERE b.session_id = cs.id AND b.status='confirmed') AS booked,
           EXISTS(SELECT 1 FROM bookings b WHERE b.session_id = cs.id AND b.user_id = :uid AND b.status='confirmed') AS already_booked
    FROM course_sessions cs
    JOIN rooms r ON r.id = cs.room_id
    WHERE cs.course_id = :cid AND cs.starts_at >= NOW() AND cs.status='scheduled'
    ORDER BY cs.starts_at ASC
    LIMIT 30
");
$myUid = is_logged_in() ? (int)current_user()['id'] : 0;
$stmt->execute([':cid' => $course['id'], ':uid' => $myUid]);
$sessions = $stmt->fetchAll();

$en = current_lang() === 'en';

$sessionsBlock = '';
if (!$sessions) {
    $sessionsBlock = '<p class="muted">' . ($en ? 'No sessions scheduled. Dates will be updated shortly.' : 'Nessuna sessione in programma. Le date verranno aggiornate a breve.') . '</p>';
} else {
    $thWhen = $en ? 'when'  : 'quando';
    $thRoom = $en ? 'room'  : 'sala';
    $thSeat = $en ? 'seats' : 'posti';
    $sessionsBlock = '<table class="info"><thead><tr><th>' . $thWhen . '</th><th>' . $thRoom . '</th><th>' . $thSeat . '</th><th></th></tr></thead><tbody>';
    foreach ($sessions as $s) {
        $cap       = (int)$s['capacity'];
        $remaining = $cap - (int)$s['booked'];
        $full      = $en ? 'no seats left' : 'esauriti';
        if ($cap === 1) {
            // campo polivalente: o è libero oppure è occupato.
            $postiTxt = $remaining > 0
                ? '<span class="tag tag-ok">' . ($en ? 'free' : 'libero') . '</span>'
                : '<span class="muted">' . ($en ? 'taken' : 'occupato') . '</span>';
        } else {
            $postiTxt = $remaining > 0 ? '<strong>' . $remaining . '</strong> / ' . $cap : '<span class="muted">' . $full . '</span>';
        }

        $cta = '';
        if (!is_logged_in()) {
            $loginCta = $en ? 'log in to book' : 'accedi per prenotare';
            $cta = '<a href="/login.php?next=' . urlencode('/course-detail.php?slug=' . $course['slug']) . '">' . $loginCta . '</a>';
        } elseif ($s['already_booked']) {
            $cta = '<span class="tag" style="background:#e8f3e1;color:#355d22">' . ($en ? 'booked' : 'prenotato') . '</span>';
        } elseif ($remaining <= 0) {
            $cta = '<span class="muted small">' . $full . '</span>';
        } else {
            $bk = $en ? 'book' : 'prenota';
            $cta = '<form method="post" action="/book-session.php" style="display:inline">'
                 . '<input type="hidden" name="session_id" value="' . (int)$s['id'] . '">'
                 . '<button class="btn btn-quiet" type="submit">' . $bk . '</button></form>';
        }
        $sessionsBlock .= '<tr>';
        $sessionsBlock .= '<td>' . e(format_datetime_it($s['starts_at'])) . ' / ' . e(date('H:i', strtotime($s['ends_at']))) . '</td>';
        $sessionsBlock .= '<td>' . e($s['room_name']) . '</td>';
        $sessionsBlock .= '<td>' . $postiTxt . '</td>';
        $sessionsBlock .= '<td>' . $cta . '</td>';
        $sessionsBlock .= '</tr>';
    }
    $sessionsBlock .= '</tbody></table>';
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/course-detail');
$body->setContent('title', e($course['title']));
$body->setContent('category', e($course['cat_name']));
$body->setContent('level', e($course['level']));
$body->setContent('duration', (string)(int)$course['duration_minutes']);
$body->setContent('description', $course['description'] ? '<p>' . nl2br(e($course['description'])) . '</p>' : '');
$body->setContent('sessions_block', $sessionsBlock);
$body->setContent('bc_cal',     $en ? 'Activity calendar' : 'Calendario corsi');
$body->setContent('l_category', $en ? 'Category' : 'Categoria');
$body->setContent('l_level',    $en ? 'Level'    : 'Tipo');
$body->setContent('l_duration', $en ? 'Slot duration' : 'Durata slot');
$body->setContent('h_sessions', $en ? 'Upcoming sessions' : 'Sessioni in programma');
$body->setContent('booking_req',$en
    ? 'To book you must be logged in, have an active card and a valid medical certificate.'
    : 'Per prenotare devi essere loggato, avere una tessera attiva e un certificato medico valido.');

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', $course['title'] . ' | Canada Gym');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
