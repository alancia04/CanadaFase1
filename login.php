<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// se sei già loggato: admin/staff vanno al backoffice
if (is_logged_in()) {
    $u = current_user();
    if (user_has_service((int)$u['id'], SVC_VIEW_DASHBOARD)) redirect('/admin/index.php');
    redirect('/dashboard.php');
}

$error = '';
$email = '';
$next  = $_GET['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pwd   = $_POST['password'] ?? '';

    if ($email === '' || $pwd === '') {
        $error = t('auth.empty_fields');
    } elseif (!login($email, $pwd)) {
        $error = t('auth.bad_creds');
    } else {
        $u = current_user();
        $dest = $_POST['next'] ?: ($_GET['next'] ?? '');
        if ($dest && str_starts_with($dest, '/')) {
            redirect($dest);
        }
        if (user_has_service((int)$u['id'], SVC_VIEW_DASHBOARD)) {
            redirect('/admin/index.php');
        }
        redirect('/dashboard.php');
    }
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/auth-login');
$body->setContent('error', $error ? '<div class="flash flash-error">' . e($error) . '</div>' : '');
$body->setContent('email', e($email));
$body->setContent('next',  e($next));

$body->setContent('h_login',       t('auth.login_title'));
$body->setContent('l_email',       t('auth.email'));
$body->setContent('l_password',    t('auth.password'));
$body->setContent('btn_submit',    t('auth.login_btn'));
$body->setContent('no_account',    t('auth.no_account'));
$body->setContent('link_register', t('auth.register_here'));
$body->setContent('with_email',    t('auth.with_email'));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', t('auth.login_title') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body',  $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
