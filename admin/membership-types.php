<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_MEMBERSHIPS);
$pdo = db();
$action = $_GET['action'] ?? 'list';
$errors = [];
$form = ['name' => '', 'description' => '', 'duration_days' => '365'];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name']          = trim($_POST['name'] ?? '');
    $form['description']   = trim($_POST['description'] ?? '');
    $form['duration_days'] = (int)($_POST['duration_days'] ?? 0);
    if ($form['name'] === '')           $errors[] = 'Nome obbligatorio.';
    if ($form['duration_days'] <= 0)    $errors[] = 'Durata > 0 obbligatoria.';
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("INSERT INTO membership_types (name, description, duration_days) VALUES (:n, :d, :du)");
            $stmt->execute([':n' => $form['name'], ':d' => $form['description'] ?: null, ':du' => $form['duration_days']]);
            flash_set('Tipo tessera creato.');
            redirect('/admin/membership-types.php');
        } catch (PDOException $ex) {
            $errors[] = $ex->getCode() === '23000' ? 'Nome già esistente.' : 'Errore database.';
        }
    }
}
if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE membership_types SET is_active = 1 - is_active WHERE id=:id");
        $stmt->execute([':id' => $id]);
        flash_set('Tipo aggiornato.');
    }
    redirect('/admin/membership-types.php');
}

$rows = $pdo->query("
    SELECT mt.*, COUNT(m.id) AS active_count
    FROM membership_types mt
    LEFT JOIN memberships m ON m.type_id = mt.id AND m.status='active' AND m.end_date >= CURDATE()
    GROUP BY mt.id
    ORDER BY mt.is_active DESC, mt.name
")->fetchAll();

$rowsHtml = '';
foreach ($rows as $r) {
    $tag = $r['is_active'] ? '<span class="tag" style="background:#e8f3e1;color:#355d22">attivo</span>' : '<span class="tag">archiviato</span>';
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td>' . e($r['name']) . '</td>';
    $rowsHtml .= '<td>' . e($r['description'] ?? '') . '</td>';
    $rowsHtml .= '<td>' . (int)$r['duration_days'] . ' gg</td>';
    $rowsHtml .= '<td>' . (int)$r['active_count'] . '</td>';
    $rowsHtml .= '<td>' . $tag . '</td>';
    $rowsHtml .= '<td>';
    $rowsHtml .= '<form method="post" action="/admin/membership-types.php?action=toggle" style="display:inline">';
    $rowsHtml .= '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
    $rowsHtml .= '<button type="submit" class="btn btn-quiet">' . ($r['is_active'] ? 'archivia' : 'attiva') . '</button></form>';
    $rowsHtml .= '</td></tr>';
}

$errBlock = '';
if ($errors) { $errBlock = '<div class="flash flash-error"><ul>'; foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>'; $errBlock .= '</ul></div>'; }

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-membership-types');
$body->setContent('rows', $rowsHtml);
$body->setContent('errors', $errBlock);
$body->setContent('name_v', e($form['name']));
$body->setContent('desc_v', e($form['description']));
$body->setContent('duration_v', e((string)$form['duration_days']));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Tipi tessera | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
