<?php
// preferenze utente: locale, tema, notifiche email

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
$u = current_user();
$uid = (int)$u['id'];
$pdo = db();

// preferenze attuali (default se non esistono)
$stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$prefs = $stmt->fetch();
if (!$prefs) {
    $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (:uid)");
    $stmt->execute([':uid' => $uid]);
    $prefs = ['locale' => 'it', 'theme' => 'auto', 'email_notifications' => 1];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $locale = in_array($_POST['locale'] ?? '', ['it','en'], true) ? $_POST['locale'] : 'it';
    $theme  = in_array($_POST['theme'] ?? '', ['light','dark','auto'], true) ? $_POST['theme'] : 'auto';
    $notif  = !empty($_POST['email_notifications']) ? 1 : 0;
    $stmt = $pdo->prepare("
        UPDATE user_preferences SET locale=:l, theme=:t, email_notifications=:n WHERE user_id=:uid
    ");
    $stmt->execute([':l' => $locale, ':t' => $theme, ':n' => $notif, ':uid' => $uid]);
    flash_set('Preferenze salvate.');
    redirect('/preferences.php');
}

$body_html = '<article class="panel">';
$body_html .= '<h2>Preferenze</h2>';
$body_html .= '<form method="post" class="auth-form">';
$body_html .= '<div class="form-row"><label>Lingua interfaccia</label>';
$body_html .= '<span class="radio-group">';
$body_html .= '<label><input type="radio" name="locale" value="it"' . ($prefs['locale']==='it'?' checked':'') . '> italiano</label>';
$body_html .= '<label><input type="radio" name="locale" value="en"' . ($prefs['locale']==='en'?' checked':'') . '> english</label>';
$body_html .= '</span></div>';
$body_html .= '<div class="form-row"><label>Tema</label>';
$body_html .= '<span class="radio-group">';
$body_html .= '<label><input type="radio" name="theme" value="auto"' . ($prefs['theme']==='auto'?' checked':'') . '> automatico</label>';
$body_html .= '<label><input type="radio" name="theme" value="light"' . ($prefs['theme']==='light'?' checked':'') . '> chiaro</label>';
$body_html .= '<label><input type="radio" name="theme" value="dark"' . ($prefs['theme']==='dark'?' checked':'') . '> scuro</label>';
$body_html .= '</span><small>Per ora il tema scuro non è applicato a tutto il sito; resta la preferenza salvata.</small></div>';
$body_html .= '<div class="form-row"><label><input type="checkbox" name="email_notifications" value="1"' . ($prefs['email_notifications']?' checked':'') . '> Ricevi notifiche via email</label></div>';
$body_html .= '<div class="form-row"><button class="btn" type="submit">Salva</button></div>';
$body_html .= '</form></article>';

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/preferences');
$body->setContent('content', $body_html);

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Preferenze | Canada Gym');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
