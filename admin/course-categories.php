<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_COURSES);
$pdo = db();
$action = $_GET['action'] ?? 'list';
$errors = [];
$form = ['name' => '', 'description' => ''];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name']        = trim($_POST['name'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    if ($form['name'] === '') $errors[] = 'Nome obbligatorio.';
    if (!$errors) {
        try {
            $stmt = $pdo->prepare("INSERT INTO course_categories (name, slug, description) VALUES (:n, :s, :d)");
            $stmt->execute([':n' => $form['name'], ':s' => slugify($form['name']), ':d' => $form['description'] ?: null]);
            flash_set('Categoria creata.');
            redirect('/admin/course-categories.php');
        } catch (PDOException $ex) {
            $errors[] = $ex->getCode() === '23000' ? 'Categoria già esistente.' : 'Errore database.';
        }
    }
}
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM course_categories WHERE id=:id");
            $stmt->execute([':id' => $id]);
            flash_set('Categoria eliminata.');
        } catch (PDOException $ex) {
            flash_set('Impossibile eliminare: ci sono corsi in questa categoria.');
        }
    }
    redirect('/admin/course-categories.php');
}

$rows = $pdo->query("
    SELECT c.id, c.name, c.slug, c.description, COUNT(co.id) AS course_count
    FROM course_categories c
    LEFT JOIN courses co ON co.category_id = c.id
    GROUP BY c.id ORDER BY c.name
")->fetchAll();

$rowsHtml = '';
foreach ($rows as $r) {
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td>' . e($r['name']) . '</td>';
    $rowsHtml .= '<td><code>' . e($r['slug']) . '</code></td>';
    $rowsHtml .= '<td>' . e($r['description'] ?? '') . '</td>';
    $rowsHtml .= '<td>' . (int)$r['course_count'] . '</td>';
    $rowsHtml .= '<td>';
    $rowsHtml .= '<form method="post" action="/admin/course-categories.php?action=delete" style="display:inline" onsubmit="return confirm(\'Eliminare?\')">';
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

$body = new Template('skins/canada/dtml/admin-categories');
$body->setContent('subnav', subnav_attivita('/admin/course-categories.php'));
$body->setContent('rows', $rowsHtml);
$body->setContent('errors', $errBlock);
$body->setContent('name_v', e($form['name']));
$body->setContent('desc_v', e($form['description']));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Categorie corsi | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();