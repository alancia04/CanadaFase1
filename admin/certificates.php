<?php
// gestione certificati medici lato staff/admin.
// flow di approvazione: l'utente carica -> status='pending' -> qui lo si approva o rifiuta.
// se è lo staff a caricare per conto dell'iscritto, va direttamente in 'approved'.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_CERTIFICATES);
$pdo = db();
$me = current_user();
$myId = (int)$me['id'];

$action = $_GET['action'] ?? 'list';
$filter = $_GET['filter'] ?? 'pending';   // pending|all|approved|rejected
$errors = [];

// upload diretto dello staff: salta lo status pending, è già verificato
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId  = (int)($_POST['member_id'] ?? 0);
    $issuedAt  = trim($_POST['issued_at'] ?? '');
    $expiresAt = trim($_POST['expires_at'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if ($memberId <= 0)    $errors[] = 'Seleziona un iscritto.';
    if ($issuedAt === '')  $errors[] = 'Data emissione obbligatoria.';
    if ($expiresAt === '') $errors[] = 'Data scadenza obbligatoria.';
    if ($issuedAt && $expiresAt && $expiresAt < $issuedAt) $errors[] = 'La scadenza deve essere posteriore all\'emissione.';

    $filePath = null;
    if (!empty($_FILES['file']['name'])) {
        $err  = $_FILES['file']['error'];
        $size = $_FILES['file']['size'];
        $name = $_FILES['file']['name'];
        $tmp  = $_FILES['file']['tmp_name'];

        if ($err !== UPLOAD_ERR_OK) $errors[] = 'Errore upload (codice ' . $err . ').';
        elseif ($size > 5_000_000) $errors[] = 'File troppo grande (max 5 MB).';
        else {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') $errors[] = 'Solo file PDF accettati.';
            elseif (!is_uploaded_file($tmp)) $errors[] = 'File non valido.';
            else {
                if (!is_dir(CERTIFICATES_DIR)) @mkdir(CERTIFICATES_DIR, 0775, true);
                $newName = sprintf('cert-%d-%s.pdf', $memberId, bin2hex(random_bytes(8)));
                $dest = CERTIFICATES_DIR . '/' . $newName;
                if (!move_uploaded_file($tmp, $dest)) {
                    $errors[] = 'Impossibile salvare il file.';
                } else {
                    $filePath = 'assets/uploads/certificates/' . $newName;
                }
            }
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO medical_certificates
              (member_id, issued_at, expires_at, file_path, notes, status, verified_by, verified_at)
            VALUES (:m, :i, :e, :f, :n, 'approved', :vb, NOW())
        ");
        $stmt->execute([
            ':m' => $memberId, ':i' => $issuedAt, ':e' => $expiresAt,
            ':f' => $filePath, ':n' => $notes ?: null, ':vb' => $myId,
        ]);
        flash_set('Certificato registrato e approvato.');
        redirect('/admin/certificates.php?filter=' . $filter);
    }
}

// approvazione
if ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE medical_certificates
                SET status = 'approved',
                    verified_at = NOW(),
                    verified_by = :uid
            WHERE id = :id
        ");
        $stmt->execute([':vb' => $myId, ':id' => $id]);
        flash_set($stmt->rowCount() > 0 ? 'Certificato approvato.' : 'Operazione non eseguita (non era in attesa).');
    }
    redirect('/admin/certificates.php?filter=' . $filter);
}

// rifiuto con motivazione
if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') $reason = 'Non specificata';
    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE medical_certificates
               SET status='rejected', verified_by=:vb, verified_at=NOW(), reject_reason=:r
             WHERE id=:id AND status='pending'
        ");
        $stmt->execute([':vb' => $myId, ':id' => $id, ':r' => $reason]);
        flash_set($stmt->rowCount() > 0 ? 'Certificato rifiutato.' : 'Operazione non eseguita.');
    }
    redirect('/admin/certificates.php?filter=' . $filter);
}

// la cancellazione è azione "distruttiva": riservata a chi ha delete_certificates
// (in seed.sql: solo gruppo Admin). Lo staff modera (approve/reject) ma non elimina.
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!user_has_service($myId, SVC_DELETE_CERTIFICATES)) {
        flash_set('Non hai il permesso per eliminare i certificati. Contatta un amministratore.');
        redirect('/admin/certificates.php?filter=' . $filter);
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT file_path FROM medical_certificates WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $fp = $stmt->fetchColumn();
        if ($fp && file_exists(PROJECT_ROOT . '/' . $fp)) {
            @unlink(PROJECT_ROOT . '/' . $fp);
        }
        $stmt = $pdo->prepare("DELETE FROM medical_certificates WHERE id=:id");
        $stmt->execute([':id' => $id]);
        flash_set('Certificato eliminato.');
    }
    redirect('/admin/certificates.php?filter=' . $filter);
}

// flag passato al rendering: il bottone elimina compare solo se l'utente ha il servizio
$canDelete = user_has_service($myId, SVC_DELETE_CERTIFICATES);

// query in base al filtro
$where = '';
$params = [];
if ($filter === 'pending')      { $where = "WHERE mc.status='pending'"; }
elseif ($filter === 'approved') { $where = "WHERE mc.status='approved'"; }
elseif ($filter === 'rejected') { $where = "WHERE mc.status='rejected'"; }

$rows = $pdo->query("
    SELECT mc.id, mc.issued_at, mc.expires_at, mc.file_path, mc.status,
           mc.verified_at, mc.reject_reason,
           m.member_code, u.first_name, u.last_name,
           v.first_name AS v_first, v.last_name AS v_last
    FROM medical_certificates mc
    JOIN members m ON m.id = mc.member_id
    JOIN users u   ON u.id = m.user_id
    LEFT JOIN users v ON v.id = mc.verified_by
    $where
    ORDER BY
      CASE mc.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
      mc.created_at DESC
    LIMIT 200
")->fetchAll();

$members = $pdo->query("
    SELECT m.id, m.member_code, u.first_name, u.last_name FROM members m
    JOIN users u ON u.id = m.user_id ORDER BY u.last_name, u.first_name
")->fetchAll();

// conteggi per le tab di filtro
$counts = [
    'all'      => (int)$pdo->query("SELECT COUNT(*) FROM medical_certificates")->fetchColumn(),
    'pending'  => (int)$pdo->query("SELECT COUNT(*) FROM medical_certificates WHERE status='pending'")->fetchColumn(),
    'approved' => (int)$pdo->query("SELECT COUNT(*) FROM medical_certificates WHERE status='approved'")->fetchColumn(),
    'rejected' => (int)$pdo->query("SELECT COUNT(*) FROM medical_certificates WHERE status='rejected'")->fetchColumn(),
];

$today = date('Y-m-d');
$rowsHtml = '';
foreach ($rows as $r) {
    $expired = $r['expires_at'] < $today;

    // badge stato
    if ($r['status'] === 'pending') {
        $statusTag = '<span class="tag" style="background:#e6eef7;color:#1e3a5f">in attesa</span>';
    } elseif ($r['status'] === 'rejected') {
        $statusTag = '<span class="tag" style="background:#fbe5e3;color:#6e1414">rifiutato</span>';
    } elseif ($expired) {
        $statusTag = '<span class="tag" style="background:#fbe5e3;color:#6e1414">scaduto</span>';
    } else {
        $statusTag = '<span class="tag" style="background:#e8f3e1;color:#355d22">valido</span>';
    }

    // info verificatore (solo per approved/rejected)
    $verifInfo = '';
    if ($r['status'] !== 'pending' && $r['v_last']) {
        $verifInfo = '<br><small class="muted">da ' . e($r['v_first'] . ' ' . $r['v_last']);
        if ($r['verified_at']) {
            $verifInfo .= ' il ' . e(format_date_it(substr($r['verified_at'], 0, 10)));
        }
        $verifInfo .= '</small>';
        if ($r['status'] === 'rejected' && $r['reject_reason']) {
            $verifInfo .= '<br><small class="muted">motivo: ' . e($r['reject_reason']) . '</small>';
        }
    }

    $fileLink = $r['file_path']
        ? '<a href="/' . e($r['file_path']) . '" target="_blank" rel="noopener">PDF</a>'
        : '<span class="muted">no file</span>';

    $actions = '';
    if ($r['status'] === 'pending') {
        $actions .= '<form method="post" action="/admin/certificates.php?action=approve&filter=' . e($filter) . '" style="display:inline">';
        $actions .= '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
        $actions .= '<button type="submit" class="btn btn-quiet">approva</button></form> ';

        $actions .= '<form method="post" action="/admin/certificates.php?action=reject&filter=' . e($filter) . '" style="display:inline" onsubmit="var r = prompt(\'Motivo del rifiuto?\'); if (r === null) return false; this.reason.value = r; return true;">';
        $actions .= '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
        $actions .= '<input type="hidden" name="reason" value="">';
        $actions .= '<button type="submit" class="btn btn-quiet">rifiuta</button></form> ';
    }
    if ($canDelete) {
        $actions .= '<form method="post" action="/admin/certificates.php?action=delete&filter=' . e($filter) . '" style="display:inline" onsubmit="return confirm(\'Eliminare?\')">';
        $actions .= '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
        $actions .= '<button type="submit" class="btn btn-quiet">elimina</button></form>';
    }

    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td><code>' . e($r['member_code']) . '</code><br>' . e($r['first_name'] . ' ' . $r['last_name']) . '</td>';
    $rowsHtml .= '<td>' . e(format_date_it($r['issued_at'])) . '</td>';
    $rowsHtml .= '<td>' . e(format_date_it($r['expires_at'])) . '</td>';
    $rowsHtml .= '<td>' . $statusTag . $verifInfo . '</td>';
    $rowsHtml .= '<td>' . $fileLink . '</td>';
    $rowsHtml .= '<td>' . $actions . '</td>';
    $rowsHtml .= '</tr>';
}

// barra filtri (semplice strip di link)
$filters = [
    'pending'  => 'In attesa',
    'approved' => 'Approvati',
    'rejected' => 'Rifiutati',
    'all'      => 'Tutti',
];
$filterBar = '<div class="cert-filters">';
foreach ($filters as $k => $label) {
    $cls = $filter === $k ? 'cf-pill active' : 'cf-pill';
    $n   = $counts[$k] ?? 0;
    $filterBar .= '<a class="' . $cls . '" href="/admin/certificates.php?filter=' . e($k) . '">' . e($label) . ' <span>(' . $n . ')</span></a>';
}
$filterBar .= '</div>';

$memberOpts = '';
foreach ($members as $m) {
    $memberOpts .= '<option value="' . (int)$m['id'] . '">' . e($m['member_code'] . ' / ' . $m['last_name'] . ' ' . $m['first_name']) . '</option>';
}

$errBlock = '';
if ($errors) { $errBlock = '<div class="flash flash-error"><ul>'; foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>'; $errBlock .= '</ul></div>'; }

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-certificates');
$body->setContent('rows', $rowsHtml);
$body->setContent('member_opts', $memberOpts);
$body->setContent('errors', $errBlock);
$body->setContent('filter_bar', $filterBar);

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Certificati medici | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
