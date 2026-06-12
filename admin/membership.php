<?php

// gestione tessere annuali. una sola tessera valida per iscritto, gratuita.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_MEMBERSHIPS);
$pdo = db();
$action = $_GET['action'] ?? 'list';
$q = trim($_GET['q'] ?? '');

// recupero il tipo tessera di default
$defaultType = $pdo->query("
    SELECT id, name, duration_days FROM membership_types
    WHERE is_active = 1
    ORDER BY id ASC LIMIT 1
")->fetch();

if ($action === 'activate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = (int)($_POST['member_id'] ?? 0);
    if ($memberId <= 0) {
        flash_set('Iscritto non valido.');
    } elseif (!$defaultType) {
        flash_set('Nessun tipo tessera configurato nel sistema. Contatta l\'amministratore.');
    } else {
        $stmt = $pdo->prepare("
            SELECT id FROM memberships
            WHERE member_id = :m AND status = 'active' AND end_date >= CURDATE() LIMIT 1
        ");
        $stmt->execute([':m' => $memberId]);
        if ($stmt->fetchColumn()) {
            flash_set('Questo iscritto ha già una tessera attiva.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO memberships (member_id, type_id, start_date, end_date, status)
                    VALUES (:m, :t, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :d DAY), 'active')
                ");
                $stmt->execute([
                    ':m' => $memberId,
                    ':t' => (int)$defaultType['id'],
                    ':d' => (int)$defaultType['duration_days'],
                ]);
                flash_set('Tessera attivata.');
            } catch (PDOException $ex) {
                flash_set('Errore database durante l\'attivazione.');
            }
        }
    }
    redirect('/admin/memberships.php' . ($q !== '' ? '?q=' . urlencode($q) : ''));
}

if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE memberships SET status='cancelled' WHERE id=:id");
        $stmt->execute([':id' => $id]);
        flash_set('Tessera annullata.');
    }
    redirect('/admin/memberships.php' . ($q !== '' ? '?q=' . urlencode($q) : ''));
}

// lista iscritti palestra con stato tessera. ricerca per email/nome/cognome/codice.
$where = '';
$params = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where = "WHERE mb.member_code LIKE :q1
    OR u.first_name LIKE :q2 OR u.last_name LIKE :q3 OR u.email LIKE :q4
    OR CONCAT(u.first_name, ' ', u.last_name) LIKE :q5";
    $params = [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like];
}

$stmt = $pdo->prepare("
    SELECT mb.id AS member_id, mb.member_code, u.first_name, u.last_name, u.email, (SELECT m.id FROM memberships m
    WHERE m.member_id = mb.id AND m.status='active' AND m.end_date >= CURDATE()
    ORDER BY m.end_date DESC LIMIT 1) AS active_mem_id,
    (SELECT m.end_date FROM memberships m
    WHERE m.member_id = mb.id AND m.status='active' AND m.end_date >= CURDATE()
    ORDER BY m.end_date DESC LIMIT 1) AS active_end_date
    FROM members mb
    JOIN users u ON u.id = mb.user_id
    $where
    ORDER BY u.last_name, u.first_name
    LIMIT 200
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$rowsHtml = '';
foreach ($rows as $r) {
    $hasActive = !empty($r['active_mem_id']);
    if ($hasActive) {
        $stato = '<span class="tag" style="background:#e8f3e1;color:#355d22">attiva fino al ' . e(format_date_it($r['active_end_date'])) . '</span>';
        $azione = '<form method="post" action="/admin/memberships.php?action=cancel'
                . ($q !== '' ? '&q=' . e(urlencode($q)) : '') . '" style="display:inline"'
                . ' data-confirm-modal'
                . ' data-confirm-title="Annullare la tessera?"'
                . ' data-confirm-text="Stai per annullare la tessera di ' . e($r['first_name'] . ' ' . $r['last_name']) . '. L\'iscritto non potra piu prenotare sessioni finche non riattivi."'
                . ' data-confirm-action="Conferma annullamento tessera"'
                . ' data-confirm-cancel="Annulla">'
                . '<input type="hidden" name="id" value="' . (int)$r['active_mem_id'] . '">'
                . '<button type="submit" class="btn btn-quiet">annulla tessera</button></form>';
    } else {
        $stato = '<span class="tag">nessuna tessera attiva</span>';
        $azione = '<form method="post" action="/admin/memberships.php?action=activate'
                . ($q !== '' ? '&q=' . e(urlencode($q)) : '') . '" style="display:inline">'
                . '<input type="hidden" name="member_id" value="' . (int)$r['member_id'] . '">'
                . '<button type="submit" class="btn">attiva tessera</button></form>';
    }
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td><code>' . e($r['member_code']) . '</code></td>';
    $rowsHtml .= '<td>' . e($r['first_name'] . ' ' . $r['last_name']) . '</td>';
    $rowsHtml .= '<td><small>' . e($r['email']) . '</small></td>';
    $rowsHtml .= '<td>' . $stato . '</td>';
    $rowsHtml .= '<td>' . $azione . '</td>';
    $rowsHtml .= '</tr>';
}

if (!$rows) {
    $rowsHtml = '<tr><td colspan="5" class="muted">' . ($q !== '' ? 'Nessun iscritto trovato per "' . e($q) . '".' : 'Nessun iscritto.') . '</td></tr>';
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-memberships');
$body->setContent('rows', $rowsHtml);
$body->setContent('q_value', e($q));
$body->setContent('result_count', (string)count($rows));

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Tessere | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();