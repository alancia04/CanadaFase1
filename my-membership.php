<?php
// vista della tessera attiva dell'utente

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
$u = current_user();
$uid = (int)$u['id'];
$pdo = db();

$stmt = $pdo->prepare("SELECT * FROM members WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $uid]);
$member = $stmt->fetch();

$current = null;
$history = [];
if ($member) {
    $stmt = $pdo->prepare("
        SELECT m.*, mt.name AS type_name
        FROM memberships m JOIN membership_types mt ON mt.id = m.type_id
        WHERE m.member_id = :m AND m.status='active' AND m.end_date >= CURDATE()
        ORDER BY m.end_date DESC LIMIT 1
    ");
    $stmt->execute([':m' => $member['id']]);
    $current = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT m.*, mt.name AS type_name
        FROM memberships m JOIN membership_types mt ON mt.id = m.type_id
        WHERE m.member_id = :m
        ORDER BY m.start_date DESC LIMIT 10
    ");
    $stmt->execute([':m' => $member['id']]);
    $history = $stmt->fetchAll();
}

$en = current_lang() === 'en';

$current_block = '';
if (!$member) {
    $current_block = '<p class="muted">' . ($en
        ? 'You are not registered as a gym member. Contact staff.'
        : 'Non risulti iscritto alla palestra. Contatta lo staff.') . '</p>';
} elseif (!$current) {
    $msg = $en ? 'You have no active card.' : 'Non hai una tessera attiva.';
    $cta = $en ? 'Activate the annual card (free)' : 'Attiva la tessera annuale (gratis)';
    $current_block = '<p>' . $msg . '</p>' . '<p><a class="btn" href="/enroll.php">' . $cta . '</a></p>';
} else {
    $days = (int)((strtotime($current['end_date']) - time()) / 86400);
    $labelCode    = $en ? 'Card code' : 'Codice tessera';
    $labelActive  = $en ? 'Active from' : 'Attiva dal';
    $labelTo      = $en ? 'to' : 'al';
    $labelRemain  = $en ? 'days remaining' : 'giorni residui';
    $current_block = '<p><strong>' . e($current['type_name']) . '</strong></p>' . '<p>' . $labelCode . ': <code>' . e($member['member_code']) . '</code></p>' . '<p>' . $labelActive . ' <strong>' . e(format_date_it($current['start_date'])) . '</strong> ' . $labelTo . ' <strong>' . e(format_date_it($current['end_date'])) . '</strong> (' . $days . ' ' . $labelRemain . ').</p>';
}

$history_block = '';
if (!$history) {
    $history_block = '<p class="muted">' . ($en ? 'No previous cards.' : 'Nessuna tessera storica.') . '</p>';
} else {
    $thType = $en ? 'type' : 'tipo';
    $thFrom = $en ? 'from' : 'dal';
    $thTo   = $en ? 'to'   : 'al';
    $thStat = $en ? 'status' : 'stato';
    $history_block = '<table class="info"><thead><tr><th>' . $thType . '</th><th>' . $thFrom . '</th><th>' . $thTo . '</th><th>' . $thStat . '</th></tr></thead><tbody>';
    foreach ($history as $h) {
        $history_block .= '<tr>';
        $history_block .= '<td>' . e($h['type_name']) . '</td>';
        $history_block .= '<td>' . e(format_date_it($h['start_date'])) . '</td>';
        $history_block .= '<td>' . e(format_date_it($h['end_date'])) . '</td>';
        $history_block .= '<td>' . e($h['status']) . '</td>';
        $history_block .= '</tr>';
    }
    $history_block .= '</tbody></table>';
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/my-membership');
$body->setContent('current_block', $current_block);
$body->setContent('history_block', $history_block);
$body->setContent('bc_area',   $en ? 'My area' : 'Area personale');
$body->setContent('bc_curr',   $en ? 'Card' : 'Tessera');
$body->setContent('h_current', $en ? 'Active card' : 'Tessera attiva');
$body->setContent('h_history', $en ? 'Card history' : 'Storico tessere');

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', ($en ? 'Card' : 'Tessera') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();