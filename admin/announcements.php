<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_ANNOUNCEMENTS);
$pdo = db();
$action = $_GET['action'] ?? 'list';
$errors = [];
$form = ['title' => '', 'body' => '', 'is_published' => '1'];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['title']        = trim($_POST['title'] ?? '');
    $form['body']         = trim($_POST['body'] ?? '');
    $form['is_published'] = !empty($_POST['is_published']) ? 1 : 0;

    if ($form['title'] === '') $errors[] = 'Titolo obbligatorio.';
    if ($form['body'] === '')  $errors[] = 'Testo obbligatorio.';
    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO announcements (author_id, title, body, is_published, published_at)
            VALUES (:a, :t, :b, :p, NOW())
        ");
        $stmt->execute([
            ':a' => (int)current_user()['id'], ':t' => $form['title'], ':b' => $form['body'], ':p' => $form['is_published'],
        ]);
        flash_set('Annuncio pubblicato.');
        redirect('/admin/announcements.php');
    }
}
if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE announcements SET is_published = 1 - is_published WHERE id=:id");
        $stmt->execute([':id' => $id]);
        flash_set('Stato aggiornato.');
    }
    redirect('/admin/announcements.php');
}
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id=:id");
        $stmt->execute([':id' => $id]);
        flash_set('Annuncio eliminato.');
    }
    redirect('/admin/announcements.php');
}

$rows = $pdo->query("
    SELECT a.id, a.title, a.is_published, a.published_at,
           u.first_name, u.last_name
    FROM announcements a
    JOIN users u ON u.id = a.author_id
    ORDER BY a.published_at DESC LIMIT 50
")->fetchAll();

$rowsHtml = '';
foreach ($rows as $a) {
    $tag = $a['is_published']
        ? '<span class="tag" style="background:#e8f3e1;color:#355d22">pubblicato</span>'
        : '<span class="tag">bozza</span>';
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td>' . e($a['title']) . '</td>';
    $rowsHtml .= '<td>' . e($a['first_name'] . ' ' . $a['last_name']) . '</td>';
    $rowsHtml .= '<td>' . e(format_datetime_it($a['published_at'])) . '</td>';
    $rowsHtml .= '<td>' . $tag . '</td>';
    $rowsHtml .= '<td>';
    $rowsHtml .= '<form method="post" action="/admin/announcements.php?action=toggle" style="display:inline">';
    $rowsHtml .= '<input type="hidden" name="id" value="' . (int)$a['id'] . '">';
    $rowsHtml .= '<button type="submit" class="btn btn-quiet">' . ($a['is_published'] ? 'archivia' : 'pubblica') . '</button></form> ';
    $rowsHtml .= '<form method="post" action="/admin/announcements.php?action=delete" style="display:inline" onsubmit="return confirm(\'Eliminare?\')">';
    $rowsHtml .= '<input type="hidden" name="id" value="' . (int)$a['id'] . '">';
    $rowsHtml .= '<button type="submit" class="btn btn-quiet">elimina</button></form>';
    $rowsHtml .= '</td></tr>';
}

$errBlock = '';
if ($errors) { $errBlock = '<div class="flash flash-error"><ul>'; foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>'; $errBlock .= '</ul></div>'; }

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-announcements');
$body->setContent('rows', $rowsHtml);
$body->setContent('errors', $errBlock);
$body->setContent('title_v', e($form['title']));
$body->setContent('body_v', e($form['body']));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Annunci | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
