<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_ROOMS);
$pdo = db();
$action = $_GET['action'] ?? 'list';
$errors = [];
$form = ['name' => '', 'capacity' => '20', 'location' => '', 'notes' => ''];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name']     = trim($_POST['name'] ?? '');
    $form['capacity'] = (int)($_POST['capacity'] ?? 0);
    $form['location'] = trim($_POST['location'] ?? '');
    $form['notes']    = trim($_POST['notes'] ?? '');
    if ($form['name'] === '')   $errors[] = 'Nome obbligatorio.';
    if ($form['capacity'] <= 0) $errors[] = 'Capienza > 0 obbligatoria.';
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("INSERT INTO rooms (name, capacity, location, notes) VALUES (:n, :c, :l, :nt)");
            $stmt->execute([':n' => $form['name'], ':c' => $form['capacity'], ':l' => $form['location'] ?: null, ':nt' => $form['notes'] ?: null]);
            flash_set('Sala creata.');
            redirect('/admin/rooms.php');
        } catch (PDOException $ex) {
            $errors[] = $ex->getCode() === '23000' ? 'Sala già esistente.' : 'Errore database.';
        }
    }
}
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id=:id");
            $stmt->execute([':id' => $id]);
            flash_set('Sala eliminata.');
        } catch (PDOException $ex) {
            flash_set('Impossibile eliminare: la sala ha sessioni programmate.');
        }
    }
    redirect('/admin/rooms.php');
}

$rooms = $pdo->query("SELECT id, name, capacity, location, notes FROM rooms ORDER BY name")->fetchAll();

$rowsHtml = '';
foreach ($rooms as $r) {
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td>' . e($r['name']) . '</td>';
    $rowsHtml .= '<td>' . (int)$r['capacity'] . '</td>';
    $rowsHtml .= '<td>' . e($r['location'] ?? '') . '</td>';
    $rowsHtml .= '<td>' . e($r['notes'] ?? '') . '</td>';
    $rowsHtml .= '<td>';
    $rowsHtml .= '<form method="post" action="/admin/rooms.php?action=delete" style="display:inline" onsubmit="return confirm(\'Eliminare?\')">';
    $rowsHtml .= '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
    $rowsHtml .= '<button type="submit" class="btn btn-quiet">elimina</button></form>';
    $rowsHtml .= '</td></tr>';
}

$errBlock = '';
if ($errors) {
    $errBlock = '<div class="flash flash-error"><ul>';
    foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>';
    $errBlock .= '</ul></div>';
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-rooms');
$body->setContent('subnav', subnav_attivita('/admin/rooms.php'));
$body->setContent('rows', $rowsHtml);
$body->setContent('errors', $errBlock);
$body->setContent('name_v', e($form['name']));
$body->setContent('capacity_v', e((string)$form['capacity']));
$body->setContent('location_v', e($form['location']));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Sale | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();