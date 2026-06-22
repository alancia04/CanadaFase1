<?php
// utente carica/aggiorna il proprio certificato medico

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

$errors = [];
if ($member && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $issuedAt  = trim($_POST['issued_at'] ?? '');
    $expiresAt = trim($_POST['expires_at'] ?? '');
    if ($issuedAt === '')  $errors[] = 'Data emissione obbligatoria.';
    if ($expiresAt === '') $errors[] = 'Data scadenza obbligatoria.';
    if ($issuedAt && $expiresAt && $expiresAt < $issuedAt) $errors[] = 'Scadenza prima dell\'emissione.';

    $filePath = null;
    if (empty($_FILES['file']['name'])) {
        $errors[] = 'Carica il file PDF del certificato.';
    } else {
        $err = $_FILES['file']['error'];
        $size = $_FILES['file']['size'];
        $tmp = $_FILES['file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

        if ($err !== UPLOAD_ERR_OK) $errors[] = 'Errore upload.';
        elseif ($size > 5_000_000) $errors[] = 'File troppo grande (max 5 MB).';
        elseif ($ext !== 'pdf')    $errors[] = 'Solo PDF accettati.';
        elseif (!is_uploaded_file($tmp)) $errors[] = 'File non valido.';
        else {
            if (!is_dir(CERTIFICATES_DIR)) @mkdir(CERTIFICATES_DIR, 0775, true);
            $newName = sprintf('cert-%d-%s.pdf', $member['id'], bin2hex(random_bytes(8)));
            $dest = CERTIFICATES_DIR . '/' . $newName;
            if (!move_uploaded_file($tmp, $dest)) {
                $errors[] = 'Impossibile salvare il file.';
            } else {
                $filePath = 'assets/uploads/certificates/' . $newName;
            }
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO medical_certificates (member_id, issued_at, expires_at, file_path, status)
            VALUES (:m, :i, :e, :f, 'pending')
        ");
        $stmt->execute([':m' => $member['id'], ':i' => $issuedAt, ':e' => $expiresAt, ':f' => $filePath]);
        flash_set(current_lang() === 'en'
            ? 'Certificate uploaded. Pending staff approval: you will be notified soon. Bookings remain blocked until then.'
            : 'Certificato caricato. È in attesa di approvazione da parte dello staff: riceverai conferma a breve. Fino ad allora le prenotazioni restano bloccate.');
        redirect('/my-certificate.php');
    }
}

// certificato più recente, di qualunque stato
$cert = null;
if ($member) {
    $stmt = $pdo->prepare("SELECT * FROM medical_certificates WHERE member_id=:m ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':m' => $member['id']]);
    $cert = $stmt->fetch() ?: null;
}

$banner = banner_certificato($cert);

$current_info = '';
if ($cert) {
    $statusLabel = match($cert['status']) {
        'pending'  => '<span class="tag" style="background:#e6eef7;color:#1e3a5f">in attesa di approvazione</span>',
        'approved' => '<span class="tag" style="background:#e8f3e1;color:#355d22">approvato</span>',
        'rejected' => '<span class="tag" style="background:#fbe5e3;color:#6e1414">rifiutato</span>',
        default    => '<span class="tag">' . e($cert['status']) . '</span>',
    };
    $current_info = '<p>Stato: ' . $statusLabel . '</p>';
    $current_info .= '<p>Emesso il <strong>' . e(format_date_it($cert['issued_at'])) . '</strong>, scade il <strong>' . e(format_date_it($cert['expires_at'])) . '</strong>.</p>';
    if ($cert['file_path']) {
        $current_info .= '<p><a href="/' . e($cert['file_path']) . '" target="_blank" rel="noopener">apri il PDF</a></p>';
    }
    if ($cert['status'] === 'rejected' && !empty($cert['reject_reason'])) {
        $current_info .= '<p class="muted small">Motivazione del rifiuto: ' . e($cert['reject_reason']) . '</p>';
    }
}

$errBlock = '';
if ($errors) { $errBlock = '<div class="flash flash-error"><ul>'; foreach ($errors as $err) $errBlock .= '<li>' . e($err) . '</li>'; $errBlock .= '</ul></div>'; }

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$en = current_lang() === 'en';

$body = new Template('skins/canada/dtml/my-certificate');
$body->setContent('banner', $banner);
$body->setContent('current_info', $current_info);
$body->setContent('errors', $errBlock);
$body->setContent('bc_area',   $en ? 'My area' : 'Area personale');
$body->setContent('bc_curr',   $en ? 'Medical certificate' : 'Certificato medico');
$body->setContent('h_current', $en ? 'Current state' : 'Stato attuale');
$body->setContent('h_upload',  $en ? 'Upload a new certificate' : 'Carica un nuovo certificato');
$body->setContent('upload_help', $en
    ? 'PDF only, max 5 MB. Fill in the dates from the certificate. The new file replaces the previous one.'
    : 'PDF richiesto, max 5 MB. Inserisci anche le date riportate sul certificato. Il caricamento sostituirà il certificato precedente come riferimento corrente.');
$body->setContent('l_issued',   $en ? 'Issue date' : 'Data emissione');
$body->setContent('l_expires',  $en ? 'Expiry date' : 'Data scadenza');
$body->setContent('l_file',     $en ? 'PDF file' : 'File PDF');
$body->setContent('btn_upload', $en ? 'Upload' : 'Carica');

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', ($en ? 'Medical certificate' : 'Certificato medico') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();