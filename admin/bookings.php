<?php
// vista admin, tutte le prenotazioni 

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_BOOKINGS);
$pdo = db();
$action = $_GET['action'] ?? 'list';

if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE bookings SET status='cancelled', cancelled_at=NOW() WHERE id=:id");
        $stmt->execute([':id' => $id]);
        flash_set('Prenotazione cancellata.');
    }
    redirect('/admin/bookings.php');
}

$filter = $_GET['filter'] ?? 'upcoming';
$where = match ($filter) {
    'all'        => '',
    'past'       => 'AND cs.starts_at < NOW()',
    'cancelled'  => "AND b.status = 'cancelled'",
    'upcoming'   => 'AND cs.starts_at >= NOW()',
    default      => 'AND cs.starts_at >= NOW()',
};

$stmt = $pdo->query("
    SELECT b.id, b.status, b.booked_at, cs.starts_at,
           c.title AS course_title, r.name AS room_name,
           u.first_name, u.last_name, u.email
    FROM bookings b
    JOIN course_sessions cs ON cs.id = b.session_id
    JOIN courses c ON c.id = cs.course_id
    JOIN rooms r ON r.id = cs.room_id
    JOIN users u ON u.id = b.user_id
    WHERE 1=1 $where
    ORDER BY cs.starts_at DESC
    LIMIT 200
");
$rows = $stmt->fetchAll();

$rowsHtml = '';
foreach ($rows as $b) {
    $statusTag = $b['status'] === 'confirmed'
        ? '<span class="tag" style="background:#e8f3e1;color:#355d22">confermata</span>'
        : '<span class="tag" style="background:#fbe5e3;color:#6e1414">cancellata</span>';
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td>' . e(format_datetime_it($b['starts_at'])) . '</td>';
    $rowsHtml .= '<td>' . e($b['course_title']) . '<br><small class="muted">' . e($b['room_name']) . '</small></td>';
    $rowsHtml .= '<td>' . e($b['first_name'] . ' ' . $b['last_name']) . '<br><small class="muted">' . e($b['email']) . '</small></td>';
    $rowsHtml .= '<td>' . $statusTag . '</td>';
    $rowsHtml .= '<td>';
    if ($b['status'] === 'confirmed') {
        $rowsHtml .= '<form method="post" action="/admin/bookings.php?action=cancel" style="display:inline" onsubmit="return confirm(\'Cancellare?\')">';
        $rowsHtml .= '<input type="hidden" name="id" value="' . (int)$b['id'] . '">';
        $rowsHtml .= '<button type="submit" class="btn btn-quiet">cancella</button></form>';
    }
    $rowsHtml .= '</td></tr>';
}
chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-bookings');
$body->setContent('rows', $rowsHtml);
$body->setContent('filter_value', e($filter));
$body->setContent('total', (string)count($rows));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Prenotazioni | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
