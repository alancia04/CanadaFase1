<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_VIEW_DASHBOARD);

$u = current_user();
$uid = (int)$u['id'];
$pdo = db();

$stats = [
    'users'         => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn(),
    'memberships'   => (int)$pdo->query("SELECT COUNT(*) FROM memberships WHERE status='active' AND end_date >= CURDATE()")->fetchColumn(),
    'courses'       => (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE is_published=1")->fetchColumn(),
    'sessions_w'    => (int)$pdo->query("SELECT COUNT(*) FROM course_sessions WHERE starts_at >= NOW() AND starts_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'bookings_w'    => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed' AND booked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'pending_certs' => (int)$pdo->query("SELECT COUNT(*) FROM medical_certificates WHERE status='pending'")->fetchColumn(),
];

$en = current_lang() === 'en';

$sections = $en ? [
    'Personnel' => [
        ['Users',  '/admin/users.php', 'manage_users'],
        ['Groups', '/admin/groups.php', 'manage_groups'],
    ],
    'Activities' => [
        ['Courses & activities', '/admin/courses.php',     'manage_courses'],
        ['Rooms',                '/admin/rooms.php',       'manage_rooms'],
        ['Sessions',             '/admin/sessions.php',    'manage_courses'],
        ['Bookings',             '/admin/bookings.php',    'manage_bookings'],
    ],
    'Sign-ups' => [
        ['Active cards',         '/admin/memberships.php',  'manage_memberships'],
        ['Medical certificates', '/admin/certificates.php', 'manage_certificates'],
    ],
    'Operations' => [
        ['Announcements', '/admin/announcements.php', 'manage_announcements'],
    ],
] : [
    'Anagrafica' => [
        ['Utenti', '/admin/users.php', 'manage_users'],
        ['Gruppi', '/admin/groups.php', 'manage_groups'],
    ],
    'Attività' => [
        ['Corsi e attività', '/admin/courses.php',     'manage_courses'],
        ['Sale',             '/admin/rooms.php',       'manage_rooms'],
        ['Sessioni',         '/admin/sessions.php',    'manage_courses'],
        ['Prenotazioni',     '/admin/bookings.php',    'manage_bookings'],
    ],
    'Iscrizioni' => [
        ['Tessere attive',    '/admin/memberships.php',  'manage_memberships'],
        ['Certificati medici','/admin/certificates.php', 'manage_certificates'],
    ],
    'Operativo' => [
        ['Annunci', '/admin/announcements.php', 'manage_announcements'],
    ],
];

$sectionsHtml = '<section class="section-grid">';
foreach ($sections as $title => $items) {
    $links = '';
    foreach ($items as [$label, $url, $svc]) {
        if (user_has_service($uid, $svc)) {
            $links .= '<li><a href="' . e($url) . '">' . e($label) . '</a></li>';
        }
    }
    if ($links !== '') {
        $sectionsHtml .= '<div class="section-card">'
                       . '<h3>' . e($title) . '</h3>'
                       . '<ul>' . $links . '</ul>'
                       . '</div>';
    }
}
$sectionsHtml .= '</section>';

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-home');
$body->setContent('user_name', e($u['name'] . ' ' . $u['surname']));
$body->setContent('user_groups', e(implode(', ', $u['groups'] ?? [])));
$body->setContent('stat_users',         (string)$stats['users']);
$body->setContent('stat_memberships',   (string)$stats['memberships']);
$body->setContent('stat_courses',       (string)$stats['courses']);
$body->setContent('stat_sessions_w',    (string)$stats['sessions_w']);
$body->setContent('stat_bookings_w',    (string)$stats['bookings_w']);
$body->setContent('stat_pending_certs', (string)$stats['pending_certs']);
$body->setContent('sections', $sectionsHtml);

$body->setContent('bc',            $en ? 'Backoffice' : 'Backoffice');
$body->setContent('h_welcome',     $en ? 'Welcome' : 'Benvenuto');
$body->setContent('l_groups',      $en ? 'Groups' : 'Gruppi');
$body->setContent('intro',         $en
    ? 'From here you manage users, calendar, bookings and announcements of the sports centre.'
    : 'Da qui gestisci utenti, calendario, prenotazioni e comunicazioni del centro sportivo.');
$body->setContent('lbl_users',         $en ? 'Active users'         : 'Utenti attivi');
$body->setContent('lbl_memberships',   $en ? 'Active cards'         : 'Tessere attive');
$body->setContent('lbl_courses',       $en ? 'Published activities' : 'Attività pubblicate');
$body->setContent('lbl_slots',         $en ? 'Slots in next 7 days' : 'Slot nei prossimi 7gg');
$body->setContent('lbl_bookings',      $en ? 'Bookings last 7 days' : 'Prenotazioni ultimi 7gg');
$body->setContent('lbl_pending_certs', $en ? 'Pending certificates' : 'Certificati in attesa');

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Backoffice | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
