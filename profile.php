<?php
// modifica della propria anagrafica

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
$u = current_user();
$uid = (int)$u['id'];
$pdo = db();

// dati attuali
$stmt = $pdo->prepare("SELECT email, first_name, last_name, phone, birth_date FROM users WHERE id = :uid");
$stmt->execute([':uid' => $uid]);
$row = $stmt->fetch();

$errors = [];
$form = [
    'first_name' => $row['first_name'] ?? '',
    'last_name'  => $row['last_name'] ?? '',
    'phone'      => $row['phone'] ?? '',
    'birth_date' => $row['birth_date'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['first_name'] = trim($_POST['first_name'] ?? '');
    $form['last_name']  = trim($_POST['last_name'] ?? '');
    $form['phone']      = trim($_POST['phone'] ?? '');
    $form['birth_date'] = trim($_POST['birth_date'] ?? '');

    if ($form['first_name'] === '' || $form['last_name'] === '') {
        $errors[] = t('profile.name_required');
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            UPDATE users SET first_name=:fn, last_name=:ln, phone=:ph, birth_date=:bd
            WHERE id=:uid
        ");
        $stmt->execute([
            ':fn'  => $form['first_name'],
            ':ln'  => $form['last_name'],
            ':ph'  => $form['phone'] ?: null,
            ':bd'  => $form['birth_date'] ?: null,
            ':uid' => $uid,
        ]);

        $_SESSION['user']['name']    = $form['first_name'];
        $_SESSION['user']['surname'] = $form['last_name'];

        flash_set(t('profile.updated'));
        redirect('/profile.php');
    }
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/profile');
$errBlock = '';
if ($errors) {
    $errBlock = '<div class="flash flash-error"><ul>';
    foreach ($errors as $e) $errBlock .= '<li>' . e($e) . '</li>';
    $errBlock .= '</ul></div>';
}
$body->setContent('errors',     $errBlock);
$body->setContent('email',      e($row['email']));
$body->setContent('first_name', e($form['first_name']));
$body->setContent('last_name',  e($form['last_name']));
$body->setContent('phone',      e($form['phone']));
$body->setContent('birth_date', e($form['birth_date']));

$body->setContent('bc_area',         current_lang() === 'en' ? 'My area' : 'Area personale');
$body->setContent('bc_profilo',      t('profile.title_breadcrumb'));
$body->setContent('h_profilo',       t('profile.title'));
$body->setContent('email_msg',       t('profile.email_immutable') . ' palestra@univaq.it.');
$body->setContent('legend_personal', t('profile.section_personal'));
$body->setContent('l_first_name',    t('profile.first_name'));
$body->setContent('l_last_name',     t('profile.last_name'));
$body->setContent('l_phone',         t('profile.phone'));
$body->setContent('l_birth',         t('profile.birth'));
$body->setContent('btn_save',        t('profile.save_btn'));
$body->setContent('btn_cancel',      t('profile.cancel_btn'));
$body->setContent('h_password',      t('pwd.title'));
$body->setContent('pwd_help',        current_lang() === 'en'
    ? 'Password change has its own dedicated page.'
    : 'Il cambio password ha una pagina dedicata.');
$body->setContent('btn_change_password', t('profile.change_password_btn'));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', t('profile.title') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
