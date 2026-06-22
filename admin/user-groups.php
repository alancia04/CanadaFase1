<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_USERS);

$pdo = db();
$action = $_GET['action'] ?? 'list';
$errors = [];

if ($action === 'assign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $gid = (int)($_POST['group_id'] ?? 0);
    if ($uid > 0 && $gid > 0) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO users_has_groups (users_id, groups_id) VALUES (:u, :g)");
            $stmt->execute([':u' => $uid, ':g' => $gid]);
            flash_set('Assegnazione creata.');
        } catch (PDOException $ex) {
            $errors[] = 'Errore: utente o gruppo non valido.';
        }
    } else {
        $errors[] = 'Utente e gruppo obbligatori.';
    }
    if (!$errors) redirect('/admin/user-groups.php');
}

if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $gid = (int)($_POST['group_id'] ?? 0);
    if ($uid > 0 && $gid > 0) {
        $stmt = $pdo->prepare("DELETE FROM users_has_groups WHERE users_id = :u AND groups_id = :g");
        $stmt->execute([':u' => $uid, ':g' => $gid]);
        flash_set('Assegnazione rimossa.');
    }
    redirect('/admin/user-groups.php');
}

$rows = $pdo->query("
    SELECT uhg.users_id, uhg.groups_id, u.email, u.first_name, u.last_name, g.name AS group_name
    FROM users_has_groups uhg
    JOIN users u    ON u.id = uhg.users_id
    JOIN `groups` g ON g.id = uhg.groups_id
    ORDER BY u.last_name, u.first_name, g.name
")->fetchAll();

$users  = $pdo->query("SELECT id, email, first_name, last_name FROM users ORDER BY last_name, first_name")->fetchAll();
$groups = $pdo->query("SELECT id, name FROM `groups` ORDER BY name")->fetchAll();

$rowsHtml = '';
foreach ($rows as $r) {
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td>' . e($r['email']) . '</td>';
    $rowsHtml .= '<td>' . e($r['first_name'] . ' ' . $r['last_name']) . '</td>';
    $rowsHtml .= '<td><strong>' . e($r['group_name']) . '</strong></td>';
    $rowsHtml .= '<td>';
    $rowsHtml .= '<form method="post" action="/admin/user-groups.php?action=remove" style="display:inline" onsubmit="return confirm(\'Rimuovere?\')">';
    $rowsHtml .= '<input type="hidden" name="user_id" value="'  . (int)$r['users_id']  . '">';
    $rowsHtml .= '<input type="hidden" name="group_id" value="' . (int)$r['groups_id'] . '">';
    $rowsHtml .= '<button type="submit" class="btn btn-outline">rimuovi</button></form>';
    $rowsHtml .= '</td></tr>';
}

$userOpts = '';
foreach ($users as $u) {
    $userOpts .= '<option value="' . (int)$u['id'] . '">' . e($u['last_name'] . ' ' . $u['first_name'] . ' (' . $u['email'] . ')') . '</option>';
}
$groupOpts = '';
foreach ($groups as $g) {
    $groupOpts .= '<option value="' . (int)$g['id'] . '">' . e($g['name']) . '</option>';
}

$errBlock = '';
if ($errors) {
    $errBlock = '<div class="flash flash-error"><ul>';
    foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>';
    $errBlock .= '</ul></div>';
}

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-user-groups');
$body->setContent('subnav', subnav_anagrafica('/admin/user-groups.php'));
$body->setContent('rows', $rowsHtml);
$body->setContent('user_opts', $userOpts);
$body->setContent('group_opts', $groupOpts);
$body->setContent('errors', $errBlock);

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Utenti↔Gruppi | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
