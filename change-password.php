<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Da fare: query, controlli sui permessi, dati per il template

require_login();
$u   = current_user();
$uid = (int)$u['id'];
$pdo = db();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['new_password_conf']?? '';

    // verifico la password attuale
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id=:uid");
    $stmt->execute([':uid' => $uid]);
    $hash = (string)$stmt->fetchColumn();
    if (!password_verify($current, $hash)) {
        $errors[] = t('pwd.bad_current');
    }
    if (strlen($new) < 8) {
        $errors[] = t('pwd.min_length');
    }
    if ($new !== $confirm) {
        $errors[] = t('pwd.mismatch');
    }

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE users SET password_hash=:h WHERE id=:uid");
        $stmt->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':uid' => $uid]);
        flash_set(t('pwd.updated'));
        redirect('/profile.php');
    }
}

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/change-password');

$errBlock = '';
if ($errors) {
    $errBlock = '<div class="flash flash-error"><ul>';
    foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>';
    $errBlock .= '</ul></div>';
}

$body->setContent('errors',         $errBlock);
$body->setContent('bc_area',        t('dash.shortcuts', 'Area personale'));
$body->setContent('bc_profile',     t('profile.title_breadcrumb'));
$body->setContent('bc_pwd',         t('pwd.title'));
$body->setContent('h_title',        t('pwd.title'));
$body->setContent('l_current',      t('pwd.current'));
$body->setContent('l_new',          t('pwd.new'));
$body->setContent('l_confirm',      t('pwd.new_confirm'));
$body->setContent('btn_submit',     t('pwd.submit'));
$body->setContent('btn_cancel',     t('profile.cancel_btn'));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title',  t('pwd.title') . ' | Canada');
$main->setContent('nav',    barra_utente());
$main->setContent('flash',  flash());
$main->setContent('body',   $body->get());
$main->setContent('html_lang', current_lang());
$main->setContent('brand_uni', t('brand.university'));
$main->setContent('brand_center', t('brand.center'));
$main->setContent("footer", footer_html());
$main->close();