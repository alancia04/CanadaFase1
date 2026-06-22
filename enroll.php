<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
$u = current_user();
$uid = (int)$u['id'];
$pdo = db();

$stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $uid]);
$memberId = (int)$stmt->fetchColumn();

if ($memberId <= 0) {
    flash_set(current_lang() === 'en'
        ? 'You are not registered. Staff must add your member profile.'
        : 'Non risulti iscritto. Lo staff deve aggiungere la tua anagrafica palestra.');
    redirect('/dashboard.php');
}

$stmt = $pdo->prepare("
    SELECT id, end_date FROM memberships
    WHERE member_id = :m AND status = 'active' AND end_date >= CURDATE()
    LIMIT 1
");
$stmt->execute([':m' => $memberId]);
$existing = $stmt->fetch();

$stmt = $pdo->query("SELECT id, name, duration_days FROM membership_types WHERE is_active=1 ORDER BY id LIMIT 1");
$type = $stmt->fetch();

$en = current_lang() === 'en';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing && $type) {
    $stmt = $pdo->prepare("
        INSERT INTO memberships (member_id, type_id, start_date, end_date, status)
        VALUES (:m, :t, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :d DAY), 'active')
    ");
    $stmt->execute([':m' => $memberId, ':t' => $type['id'], ':d' => $type['duration_days']]);
    flash_set($en
        ? 'Card ' . $type['name'] . ' activated. Validity ' . $type['duration_days'] . ' days.'
        : 'Tessera ' . $type['name'] . ' attivata. Validità ' . $type['duration_days'] . ' giorni.');
    redirect('/my-membership.php');
}

$content = '<article class="panel"><h2>' . ($en ? 'Annual sign-up' : 'Iscrizione annuale') . '</h2>';
if ($existing) {
    $content .= '<p>' . ($en
        ? 'You already have an active card until <strong>' . e(format_date_it($existing['end_date'])) . '</strong>.'
        : 'Hai già una tessera attiva fino al <strong>' . e(format_date_it($existing['end_date'])) . '</strong>.') . '</p>';
    $content .= '<p><a class="btn" href="/my-membership.php">' . ($en ? 'See details' : 'Vedi i dettagli') . '</a></p>';
} elseif (!$type) {
    $content .= '<p class="muted">' . ($en
        ? 'No active card type at the moment. Try again later or contact staff.'
        : 'Al momento non c\'è un tipo di tessera attivo. Riprova più avanti o contatta lo staff.') . '</p>';
} else {
    if ($en) {
        $content .= '<p>Canada Sports Centre sign-up is <strong>free</strong> for Univaq affiliates. The card <strong>' . e($type['name']) . '</strong> is valid for <strong>' . (int)$type['duration_days'] . ' days</strong> from activation.</p>';
        $content .= '<form method="post" style="margin-top:14px">';
        $content .= '<button class="btn" type="submit">Activate card now</button> ';
        $content .= '<a class="btn btn-outline" href="/dashboard.php">Cancel</a>';
        $content .= '</form>';
        $content .= '<p class="small muted" style="margin-top:14px">After activation, remember to upload your medical certificate so you can book sessions.</p>';
    } else {
        $content .= '<p>L\'iscrizione alla palestra Canada è <strong>gratuita</strong> per gli affiliati Univaq. La tessera <strong>' . e($type['name']) . '</strong> ha validità di <strong>' . (int)$type['duration_days'] . ' giorni</strong> dalla data di attivazione.</p>';
        $content .= '<form method="post" style="margin-top:14px">';
        $content .= '<button class="btn" type="submit">Attiva la tessera adesso</button> ';
        $content .= '<a class="btn btn-outline" href="/dashboard.php">Annulla</a>';
        $content .= '</form>';
        $content .= '<p class="small muted" style="margin-top:14px">Dopo l\'attivazione, ricorda di caricare il certificato medico per poter prenotare le sessioni.</p>';
    }
}
$content .= '</article>';

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/enroll');
$body->setContent('content', $content);
$body->setContent('bc_area', $en ? 'My area' : 'Area personale');
$body->setContent('bc_curr', $en ? 'Annual sign-up' : 'Iscrizione A.A.');

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', ($en ? 'Annual sign-up' : 'Iscrizione A.A.') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
