<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_USERS);

$action = $_GET['action'] ?? 'list';
$pdo = db();

// CANCELLA
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $id !== (int)current_user()['id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        flash_set('Utente eliminato.');
    } else {
        flash_set('Non puoi cancellare te stesso.');
    }
    redirect('/admin/users.php');
}

// CREA nuovo utente
$createErrors = [];
$createForm = ['email' => '', 'first_name' => '', 'last_name' => ''];
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($createForm) as $k) $createForm[$k] = trim($_POST[$k] ?? '');
    $pwd = $_POST['password'] ?? '';
    $groupId = (int)($_POST['group_id'] ?? 0);

    if (!filter_var($createForm['email'], FILTER_VALIDATE_EMAIL))      $createErrors[] = 'Email non valida.';
    if ($createForm['first_name'] === '' || $createForm['last_name'] === '') $createErrors[] = 'Nome e cognome obbligatori.';
    if (strlen($pwd) < 8)                                              $createErrors[] = 'Password troppo corta (min 8).';
    if ($groupId <= 0)                                                 $createErrors[] = 'Seleziona un gruppo.';

    if (!$createErrors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, is_active)
                VALUES (:e, :h, :fn, :ln, 1)
            ");
            $stmt->execute([
                ':e'  => strtolower($createForm['email']),
                ':h'  => password_hash($pwd, PASSWORD_DEFAULT),
                ':fn' => $createForm['first_name'],
                ':ln' => $createForm['last_name'],
            ]);
            $newId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO users_has_groups (users_id, groups_id) VALUES (:u, :g)");
            $stmt->execute([':u' => $newId, ':g' => $groupId]);
            $pdo->commit();
            flash_set('Utente creato.');
            redirect('/admin/users.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $createErrors[] = $ex->getCode() === '23000' ? 'Email già esistente.' : 'Errore database.';
        }
    }
}

$q = trim($_GET['q'] ?? '');
$where = '';
$qparams = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where = "WHERE u.email LIKE :q1 OR u.first_name LIKE :q2 OR u.last_name LIKE :q3 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :q4";
    $qparams = [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
}
$sql = "
    SELECT u.id, u.email, u.first_name, u.last_name, u.is_active, u.created_at, GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ', ') AS groups_list
    FROM users u
    LEFT JOIN users_has_groups uhg ON uhg.users_id = u.id
    LEFT JOIN `groups` g ON g.id = uhg.groups_id
    $where
    GROUP BY u.id
    ORDER BY u.id DESC
    LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($qparams);
$users = $stmt->fetchAll();

$groups = $pdo->query("SELECT id, name FROM `groups` ORDER BY name")->fetchAll();

$rows = '';
$me = (int)current_user()['id'];
foreach ($users as $u) {
    $rows .= '<tr>';
    $rows .= '<td>' . (int)$u['id'] . '</td>';
    $rows .= '<td>' . e($u['email']) . '</td>';
    $rows .= '<td>' . e($u['first_name'] . ' ' . $u['last_name']) . '</td>';
    $rows .= '<td>' . e($u['groups_list'] ?? '-') . '</td>';
    $rows .= '<td>' . ($u['is_active'] ? 'sì' : 'no') . '</td>';
    $rows .= '<td>';
    if ((int)$u['id'] !== $me) {
        $rows .= '<form method="post" action="/admin/users.php?action=delete" style="display:inline" onsubmit="return confirm(\'Eliminare?\')">';
        $rows .= '<input type="hidden" name="id" value="' . (int)$u['id'] . '">';
        $rows .= '<button type="submit" class="btn btn-outline">elimina</button></form>';
    } else {
        $rows .= '<small>(tu)</small>';
    }
    $rows .= '</td></tr>';
}

$groupOpts = '';
foreach ($groups as $g) {
    $groupOpts .= '<option value="' . (int)$g['id'] . '">' . e($g['name']) . '</option>';
}

$errBlock = '';
if ($createErrors) {
    $errBlock = '<div class="flash flash-error"><ul>';
    foreach ($createErrors as $err) $errBlock .= '<li>' . e($err) . '</li>';
    $errBlock .= '</ul></div>';
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-users');
$body->setContent('subnav', subnav_anagrafica('/admin/users.php'));
$body->setContent('rows', $rows);
$body->setContent('group_opts', $groupOpts);
$body->setContent('errors', $errBlock);
$body->setContent('email_v',     e($createForm['email']));
$body->setContent('firstname_v', e($createForm['first_name']));
$body->setContent('lastname_v',  e($createForm['last_name']));
$body->setContent('q_value',     e($q));
$body->setContent('result_count',(string)count($users));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Utenti | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();