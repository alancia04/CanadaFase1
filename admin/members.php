<?php
// CRUD anagrafica iscritti palestra

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_MEMBERS);

$pdo = db();
$action = $_GET['action'] ?? 'list';
$errors = [];
$form = ['user_id' => '', 'member_type' => 'studente', 'matricola' => '', 'notes' => ''];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['user_id']     = (int)($_POST['user_id'] ?? 0);
    $form['member_type'] = trim($_POST['member_type'] ?? '');
    $form['matricola']   = trim($_POST['matricola'] ?? '');
    $form['notes']       = trim($_POST['notes'] ?? '');

    if ($form['user_id'] <= 0) $errors[] = 'Seleziona un utente.';
    if (!in_array($form['member_type'], ['studente','docente','personale'], true)) $errors[] = 'Tipo non valido.';
    if ($form['member_type'] === 'studente' && $form['matricola'] === '') $errors[] = 'Matricola obbligatoria per gli studenti.';
    if ($form['matricola'] !== '' && !preg_match('/^[A-Za-z0-9]{4,20}$/', $form['matricola'])) $errors[] = 'Matricola: 4-20 caratteri alfanumerici.';

    if (!$errors) {
        try {
            $year = date('Y');
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(CAST(SUBSTRING(member_code, 8) AS UNSIGNED)), 0) + 1 AS next_num
                FROM members WHERE member_code LIKE :prefix
            ");
            $stmt->execute([':prefix' => "M-$year-%"]);
            $next = (int)$stmt->fetchColumn();
            $code = sprintf('M-%s-%04d', $year, $next);

            $stmt = $pdo->prepare("
                INSERT INTO members (user_id, member_code, member_type, matricola, enrollment_date, notes)
                VALUES (:uid, :code, :mt, :mat, CURDATE(), :n)
            ");
            $stmt->execute([
                ':uid'  => $form['user_id'],
                ':code' => $code,
                ':mt'   => $form['member_type'],
                ':mat'  => $form['matricola'] ?: null,
                ':n'    => $form['notes'] ?: null,
            ]);
            flash_set('Iscritto creato. Codice: ' . $code);
            redirect('/admin/members.php');
        } catch (PDOException $ex) {
            $errors[] = $ex->getCode() === '23000' ? 'L\'utente è già iscritto, oppure matricola duplicata.' : 'Errore database.';
        }
    }
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM members WHERE id = :id");
        $stmt->execute([':id' => $id]);
        flash_set('Iscrizione palestra eliminata.');
    }
    redirect('/admin/members.php');
}

$members = $pdo->query("
    SELECT m.id, m.member_code, m.member_type, m.matricola, m.enrollment_date,
           u.email, u.first_name, u.last_name, u.id AS user_id
    FROM members m
    JOIN users u ON u.id = m.user_id
    WHERE LOWER(CONCAT(u.first_name, ' ', u.last_name)) LIKE LOWER(:q)
    ORDER BY m.id DESC
")->fetchAll();

// utenti senza member record (candidati per iscrizione)
$candidates = $pdo->query("
    SELECT u.id, u.email, u.first_name, u.last_name
    FROM users u
    LEFT JOIN members m ON m.user_id = u.id
    WHERE m.id IS NULL AND u.is_active = 1
    ORDER BY u.last_name, u.first_name
")->fetchAll();

$rows = '';
foreach ($members as $m) {
    $rows .= '<tr>';
    $rows .= '<td><code>' . e($m['member_code']) . '</code></td>';
    $rows .= '<td>' . e($m['first_name'] . ' ' . $m['last_name']) . '</td>';
    $rows .= '<td>' . e($m['email']) . '</td>';
    $rows .= '<td>' . e($m['member_type']) . '</td>';
    $rows .= '<td>' . e($m['matricola'] ?? '-') . '</td>';
    $rows .= '<td>' . e(format_date_it($m['enrollment_date'])) . '</td>';
    $rows .= '<td>';
    $rows .= '<form method="post" action="/admin/members.php?action=delete" style="display:inline" onsubmit="return confirm(\'Eliminare iscrizione?\')">';
    $rows .= '<input type="hidden" name="id" value="' . (int)$m['id'] . '">';
    $rows .= '<button type="submit" class="btn btn-quiet">elimina</button></form>';
    $rows .= '</td></tr>';
}

$userOpts = '';
foreach ($candidates as $c) {
    $userOpts .= '<option value="' . (int)$c['id'] . '">'
              . e($c['last_name'] . ' ' . $c['first_name'] . ' (' . $c['email'] . ')') . '</option>';
}

$errBlock = '';
if ($errors) {
    $errBlock = '<div class="flash flash-error"><ul>';
    foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>';
    $errBlock .= '</ul></div>';
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-members');
$body->setContent('rows', $rows);
$body->setContent('user_opts', $userOpts);
$body->setContent('errors', $errBlock);
$body->setContent('matricola_v', e($form['matricola']));
$body->setContent('notes_v', e($form['notes']));
$body->setContent('total_count', (string)count($members));
$body->setContent('candidates_count', (string)count($candidates));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Iscritti | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
