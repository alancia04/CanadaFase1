<?php
//elenco delle proprie prenotazioni:future(cancellabili) + storico

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
$u = current_user();
$uid = (int)$u['id'];
$pdo = db();
$en = current_lang() === 'en';

//future
$stmt = $pdo->prepare("
    SELECT b.id, b.status, cs.starts_at, cs.ends_at, c.title, r.name AS room_name, c.slug AS course_slug
    FROM bookings b
    JOIN course_sessions cs ON cs.id = b.session_id
    JOIN courses c ON c.id = cs.course_id
    JOIN rooms r ON r.id = cs.room_id
    WHERE b.user_id = :uid AND b.status = 'confirmed' AND cs.starts_at >= NOW()
    ORDER BY cs.starts_at ASC
");
$stmt->execute([':uid' => $uid]);
$future = $stmt->fetchAll();

//storico (ultimi 30)
$stmt = $pdo->prepare("
    SELECT b.id, b.status, cs.starts_at, cs.ends_at, c.title, r.name AS room_name,
           a.present
    FROM bookings b
    JOIN course_sessions cs ON cs.id = b.session_id
    JOIN courses c ON c.id = cs.course_id
    JOIN rooms r ON r.id = cs.room_id
    LEFT JOIN attendance a ON a.booking_id = b.id
    WHERE b.user_id = :uid AND cs.starts_at < NOW()
    ORDER BY cs.starts_at DESC LIMIT 30
");
$stmt->execute([':uid' => $uid]);
$past = $stmt->fetchAll();

$futureBlock = '';
if (!$future) {
    $futureBlock = '<p class="muted">'
        . ($en ? 'No upcoming bookings. <a href="/courses.php">Browse the calendar</a>.'
              : 'Nessuna prenotazione in arrivo. <a href="/courses.php">Sfoglia il calendario</a>.')
        . '</p>';
} else {
    $thDate = $en ? 'date'     : 'data';
    $thCors = $en ? 'activity' : 'corso';
    $thRoom = $en ? 'room'     : 'sala';
    $cancelBtn = $en ? 'cancel' : 'cancella';
    $futureBlock = '<table class="info"><thead><tr><th>' . $thDate . '</th><th>' . $thCors . '</th><th>' . $thRoom . '</th><th></th></tr></thead><tbody>';
    foreach ($future as $b) {
        $confirmTitle  = $en ? 'Cancel booking?' : 'Cancellare la prenotazione?';
        $confirmText   = $en
            ? 'You are about to cancel your booking for ' . htmlspecialchars($b['title']) . ' on ' . format_datetime_it($b['starts_at']) . '.'
            : 'Stai per cancellare la prenotazione per ' . htmlspecialchars($b['title']) . ' del ' . format_datetime_it($b['starts_at']) . '.';
        $confirmAction = $en ? 'Confirm cancellation' : 'Conferma cancellazione';
        $confirmCancel = $en ? 'Back' : 'Indietro';
        $futureBlock .= '<tr>';
        $futureBlock .= '<td>' . e(format_datetime_it($b['starts_at'])) . '</td>';
        $futureBlock .= '<td>' . e($b['title']) . '</td>';
        $futureBlock .= '<td>' . e($b['room_name']) . '</td>';
        $futureBlock .= '<td>';
        $futureBlock .= '<form method="post" action="/cancel-booking.php" style="display:inline"'
                     . ' data-confirm-modal'
                     . ' data-confirm-title="' . $confirmTitle . '"'
                     . ' data-confirm-text="' . $confirmText . '"'
                     . ' data-confirm-action="' . $confirmAction . '"'
                     . ' data-confirm-cancel="' . $confirmCancel . '">';
        $futureBlock .= '<input type="hidden" name="id" value="' . (int)$b['id'] . '">';
        $futureBlock .= '<button type="submit" class="btn btn-quiet">' . $cancelBtn . '</button></form>';
        $futureBlock .= '</td></tr>';
    }
    $futureBlock .= '</tbody></table>';
}

$pastBlock = '';
if (!$past) {
    $pastBlock = '<p class="muted">' . ($en ? 'No history yet.' : 'Nessuno storico per ora.') . '</p>';
} else {
    $thDate = $en ? 'date'     : 'data';
    $thCors = $en ? 'activity' : 'corso';
    $thStat = $en ? 'status'   : 'stato';
    $thPres = $en ? 'attended' : 'presenza';
    $pastBlock = '<table class="info"><thead><tr><th>' . $thDate . '</th><th>' . $thCors . '</th><th>' . $thStat . '</th><th>' . $thPres . '</th></tr></thead><tbody>';
    foreach ($past as $b) {
        $statusTag = $b['status'] === 'confirmed'
            ? ($en ? 'confirmed' : 'confermata')
            : ($en ? 'cancelled' : 'cancellata');
        $presenceTag = '<span class="muted">-</span>';
        if ($b['status'] === 'confirmed' && $b['present'] !== null) {
            $presenceTag = $b['present']
                ? '<span class="tag" style="background:#e8f3e1;color:#355d22">' . ($en ? 'present' : 'presente') . '</span>'
                : '<span class="tag" style="background:#fbe5e3;color:#6e1414">' . ($en ? 'absent'  : 'assente')  . '</span>';
        }
        $pastBlock .= '<tr>';
        $pastBlock .= '<td>' . e(format_datetime_it($b['starts_at'])) . '</td>';
        $pastBlock .= '<td>' . e($b['title']) . '</td>';
        $pastBlock .= '<td>' . $statusTag . '</td>';
        $pastBlock .= '<td>' . $presenceTag . '</td>';
        $pastBlock .= '</tr>';
    }
    $pastBlock .= '</tbody></table>';
}

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/my-bookings');
$body->setContent('future_block', $futureBlock);
$body->setContent('past_block', $pastBlock);
$body->setContent('bc_area',    $en ? 'My area' : 'Area personale');
$body->setContent('bc_curr',    $en ? 'My bookings' : 'Le mie prenotazioni');
$body->setContent('h_upcoming', $en ? 'Upcoming' : 'In arrivo');
$body->setContent('h_history',  $en ? 'History (last 30)' : 'Storico (ultime 30)');

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', ($en ? 'My bookings' : 'Le mie prenotazioni') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
