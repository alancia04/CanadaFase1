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
$form = [
    'category_id' => '', 'title' => '', 'description' => '',
    'level' => 'base', 'duration_minutes' => '60', 'is_published' => '1',
];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['title','description','level','duration_minutes','is_published'] as $k) {
        $form[$k] = trim($_POST[$k] ?? '');
    }
    $form['category_id'] = (int)($_POST['category_id'] ?? 0);

    if ($form['title'] === '')          $errors[] = 'Titolo obbligatorio.';
    if ($form['category_id'] <= 0)      $errors[] = 'Categoria obbligatoria.';
    if (!in_array($form['level'], ['base','intermedio','avanzato'], true)) $errors[] = 'Livello non valido.';
    $dur = (int)$form['duration_minutes'];
    if ($dur < 15 || $dur > 240)        $errors[] = 'Durata fuori range (15-240 min).';

    if (!$errors) {
        try {
            // instructor_id resta NULL: nel dominio Canada le attività sono libere (no istruttore)
            $stmt = $pdo->prepare("
                INSERT INTO courses (category_id, instructor_id, title, slug, description, level, duration_minutes, is_published)
                VALUES (:cat, NULL, :t, :s, :d, :l, :dur, :pub)
            ");
            $stmt->execute([
                ':cat'  => $form['category_id'],
                ':t'    => $form['title'],
                ':s'    => slugify($form['title']),
                ':d'    => $form['description'] ?: null,
                ':l'    => $form['level'],
                ':dur'  => $dur,
                ':pub'  => $form['is_published'] ? 1 : 0,
            ]);
            flash_set('Corso creato.');
            redirect('/admin/courses.php');
        } catch (PDOException $ex) {
            $errors[] = $ex->getCode() === '23000' ? 'Slug duplicato.' : 'Errore database.';
        }
    }
}
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id=:id");
        $stmt->execute([':id' => $id]);
        flash_set('Corso eliminato.');
    }
    redirect('/admin/courses.php');
}
if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE courses SET is_published = 1 - is_published WHERE id=:id");
        $stmt->execute([':id' => $id]);
        flash_set('Stato corso aggiornato.');
    }
    redirect('/admin/courses.php');
}

$rows = $pdo->query("
    SELECT c.id, c.title, c.level, c.duration_minutes, c.is_published,
           cat.name AS category_name
    FROM courses c
    JOIN course_categories cat ON cat.id = c.category_id
    ORDER BY cat.name, c.title
")->fetchAll();

$cats = $pdo->query("SELECT id, name FROM course_categories ORDER BY name")->fetchAll();

$rowsHtml = '';
foreach ($rows as $r) {
    $pubTag = $r['is_published'] ? '<span class="tag" style="background:#e8f3e1;color:#355d22">pubblicato</span>' : '<span class="tag">bozza</span>';
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td>' . e($r['title']) . '</td>';
    $rowsHtml .= '<td>' . e($r['category_name']) . '</td>';
    $rowsHtml .= '<td>' . e($r['level']) . '</td>';
    $rowsHtml .= '<td>' . (int)$r['duration_minutes'] . ' min</td>';
    $rowsHtml .= '<td>' . $pubTag . '</td>';
    $rowsHtml .= '<td>';
    $rowsHtml .= '<form method="post" action="/admin/courses.php?action=toggle" style="display:inline">';
    $rowsHtml .= '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
    $rowsHtml .= '<button type="submit" class="btn btn-quiet">' . ($r['is_published'] ? 'archivia' : 'pubblica') . '</button></form> ';
    $rowsHtml .= '<form method="post" action="/admin/courses.php?action=delete" style="display:inline" onsubmit="return confirm(\'Eliminare il corso?\')">';
    $rowsHtml .= '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
    $rowsHtml .= '<button type="submit" class="btn btn-quiet">elimina</button></form>';
    $rowsHtml .= '</td></tr>';
}

$catOpts = '';
foreach ($cats as $c) $catOpts .= '<option value="' . (int)$c['id'] . '">' . e($c['name']) . '</option>';

$errBlock = '';
if ($errors) {
    $errBlock = '<div class="flash flash-error"><ul>';
    foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>';
    $errBlock .= '</ul></div>';
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-courses');
$body->setContent('subnav', subnav_attivita('/admin/courses.php'));
$body->setContent('rows', $rowsHtml);
$body->setContent('cat_opts', $catOpts);
$body->setContent('errors', $errBlock);
$body->setContent('title_v', e($form['title']));
$body->setContent('desc_v', e($form['description']));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Corsi | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
