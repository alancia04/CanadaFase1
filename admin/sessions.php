<?php
// gestione sessioni di corso 
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_COURSES);
$pdo = db();
$action = $_GET['action'] ?? 'list';
$errors = [];
$form = ['course_id' => '', 'room_id' => '', 'starts_at' => '', 'ends_at' => '', 'capacity' => ''];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['course_id'] = (int)($_POST['course_id'] ?? 0);
    $form['room_id']   = (int)($_POST['room_id'] ?? 0);
    $form['starts_at'] = trim($_POST['starts_at'] ?? '');
    $form['ends_at']   = trim($_POST['ends_at'] ?? '');
    $form['capacity']  = (int)($_POST['capacity'] ?? 0);

    if ($form['course_id'] <= 0)  $errors[] = 'Corso obbligatorio.';
    if ($form['room_id'] <= 0)    $errors[] = 'Sala obbligatoria.';
    if ($form['starts_at'] === '') $errors[] = 'Data/ora inizio obbligatoria.';
    if ($form['ends_at'] === '')   $errors[] = 'Data/ora fine obbligatoria.';
    if (strtotime($form['ends_at']) <= strtotime($form['starts_at'])) $errors[] = 'La fine deve essere dopo l\'inizio.';
    if ($form['capacity'] <= 0)    $errors[] = 'Capienza > 0 obbligatoria.';

    // regole specifiche di dominio: sala pesi e campo polivalente non sono
    // configurabili a piacere dall'admin.
    if (!$errors && $form['course_id'] > 0 && $form['starts_at'] !== '' && $form['ends_at'] !== '') {
        $stmt = $pdo->prepare("SELECT slug FROM courses WHERE id=:id");
        $stmt->execute([':id' => $form['course_id']]);
        $courseSlug = (string)$stmt->fetchColumn();

        if (str_starts_with($courseSlug, 'sala-pesi')) {
            $start = strtotime($form['starts_at']);
            $end   = strtotime($form['ends_at']);
            if (date('i:s', $start) !== '00:00') {
                $errors[] = 'Sala pesi: il turno deve iniziare all\'inizio dell\'ora (es. 10:00, non 10:30).';
            }
            $startHr = (int)date('H', $start);
            if ($startHr < 9 || $startHr > 20) {
                $errors[] = 'Sala pesi: la fascia oraria valida è 09:00 - 21:00, ultimo turno 20:00-21:00.';
            }
            if ($end - $start !== 3600) {
                $errors[] = 'Sala pesi: ogni turno dura esattamente 60 minuti.';
            }
            $form['capacity'] = 7;
        } elseif (str_contains($courseSlug, 'basket') || str_contains($courseSlug, 'calcetto')) {
            // capienza forzata a 1, la prenotazione del campo è esclusiva
            $form['capacity'] = 1;
        }
    }

    // controllo sovrapposizione sala
    if (!$errors) {
        $stmt = $pdo->prepare("
            SELECT id FROM course_sessions
            WHERE room_id = :rid AND status = 'scheduled'
              AND NOT (ends_at <= :s OR starts_at >= :e)
            LIMIT 1
        ");
        $stmt->execute([':rid' => $form['room_id'], ':s' => $form['starts_at'], ':e' => $form['ends_at']]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'La sala è già occupata in quell\'intervallo.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO course_sessions (course_id, room_id, starts_at, ends_at, capacity, status)
                VALUES (:c, :r, :s, :e, :cap, 'scheduled')
            ");
            $stmt->execute([
                ':c'   => $form['course_id'],
                ':r'   => $form['room_id'],
                ':s'   => $form['starts_at'],
                ':e'   => $form['ends_at'],
                ':cap' => $form['capacity'],
            ]);
            flash_set('Sessione programmata.');
            redirect('/admin/sessions.php');
        } catch (PDOException $ex) {
            $errors[] = 'Errore database.';
        }
    }
}
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE course_sessions SET status='cancelled' WHERE id=:id");
        $stmt->execute([':id' => $id]);
        flash_set('Sessione annullata.');
    }
    redirect('/admin/sessions.php');
}

// filtro, prossime 30 sessioni di default
$rows = $pdo->query("
    SELECT cs.id, cs.starts_at, cs.ends_at, cs.capacity, cs.status,
           c.title AS course_title, cat.name AS cat_name,
           r.name AS room_name,
           (SELECT COUNT(*) FROM bookings b WHERE b.session_id = cs.id AND b.status='confirmed') AS booked
    FROM course_sessions cs
    JOIN courses c ON c.id = cs.course_id
    JOIN course_categories cat ON cat.id = c.category_id
    JOIN rooms r ON r.id = cs.room_id
    WHERE cs.starts_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY cs.starts_at ASC
    LIMIT 100
")->fetchAll();

$courses = $pdo->query("SELECT id, title FROM courses WHERE is_published=1 ORDER BY title")->fetchAll();
$rooms   = $pdo->query("SELECT id, name, capacity FROM rooms ORDER BY name")->fetchAll();

$rowsHtml = '';
foreach ($rows as $r) {
    $statusTag = match($r['status']) {
        'scheduled' => '<span class="tag" style="background:#e8f3e1;color:#355d22">programmata</span>',
        'cancelled' => '<span class="tag" style="background:#fbe5e3;color:#6e1414">annullata</span>',
        'completed' => '<span class="tag">conclusa</span>',
        default => '<span class="tag">' . e($r['status']) . '</span>'
    };
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td>' . e(format_datetime_it($r['starts_at'])) . '<br><small class="muted">→ ' . e(date('H:i', strtotime($r['ends_at']))) . '</small></td>';
    $rowsHtml .= '<td>' . e($r['course_title']) . '<br><small class="muted">' . e($r['cat_name']) . '</small></td>';
    $rowsHtml .= '<td>' . e($r['room_name']) . '</td>';
    $rowsHtml .= '<td>' . (int)$r['booked'] . ' / ' . (int)$r['capacity'] . '</td>';
    $rowsHtml .= '<td>' . $statusTag . '</td>';
    $rowsHtml .= '<td>';
    if ($r['status'] === 'scheduled') {
        $rowsHtml .= '<form method="post" action="/admin/sessions.php?action=cancel" style="display:inline" onsubmit="return confirm(\'Annullare la sessione?\')">';
        $rowsHtml .= '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
        $rowsHtml .= '<button type="submit" class="btn btn-quiet">annulla</button></form>';
    }
    $rowsHtml .= '</td></tr>';
}

$courseOpts = '';
foreach ($courses as $c) $courseOpts .= '<option value="' . (int)$c['id'] . '">' . e($c['title']) . '</option>';
$roomOpts = '';
foreach ($rooms as $r) $roomOpts .= '<option value="' . (int)$r['id'] . '" data-cap="' . (int)$r['capacity'] . '">' . e($r['name']) . ' (cap. ' . (int)$r['capacity'] . ')</option>';

$errBlock = '';
if ($errors) {
    $errBlock = '<div class="flash flash-error"><ul>';
    foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>';
    $errBlock .= '</ul></div>';
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-sessions');
$body->setContent('subnav', subnav_attivita('/admin/sessions.php'));
$body->setContent('rows', $rowsHtml);
$body->setContent('course_opts', $courseOpts);
$body->setContent('room_opts', $roomOpts);
$body->setContent('errors', $errBlock);

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Sessioni | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();