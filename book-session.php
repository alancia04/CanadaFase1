<?php
// POST handler: prenota una sessione
// regole: utente loggato + tessera attiva + certificato medico valido + posti disponibili + non già prenotato

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/courses.php');

$u = current_user();
$uid = (int)$u['id'];
if (!user_has_service($uid, SVC_BOOK_COURSE_SESSION)) {
    flash_set('Non hai il permesso per prenotare. Solo gli iscritti palestra possono prenotare le sessioni.');
    redirect('/courses.php');
}

$sessionId = (int)($_POST['session_id'] ?? 0);
if ($sessionId <= 0) redirect('/courses.php');

$pdo = db();

// recupero la sessione + dati corso/sala
$stmt = $pdo->prepare("
    SELECT cs.*, c.title, c.slug AS course_slug, r.capacity AS room_cap
    FROM course_sessions cs
    JOIN courses c ON c.id = cs.course_id
    JOIN rooms r ON r.id = cs.room_id
    WHERE cs.id = :id LIMIT 1
");
$stmt->execute([':id' => $sessionId]);
$cs = $stmt->fetch();

$en = current_lang() === 'en';
if (!$cs) { flash_set($en ? 'Session not found.' : 'Sessione non trovata.'); redirect('/courses.php'); }
if ($cs['status'] !== 'scheduled') { flash_set($en ? 'Session not bookable.' : 'La sessione non è prenotabile.'); redirect('/courses.php'); }
if (strtotime($cs['starts_at']) <= time()) { flash_set($en ? 'You can\'t book a session that has already started.' : 'Non puoi prenotare una sessione già iniziata.'); redirect('/courses.php'); }

$backUrl = '/course-detail.php?slug=' . urlencode($cs['course_slug']);

// utente è un member?
$stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $uid]);
$memberId = (int)$stmt->fetchColumn();
if ($memberId <= 0) { flash_set($en ? 'You must be a gym member to book. Contact staff.' : 'Devi essere iscritto alla palestra per prenotare. Contatta lo staff.'); redirect($backUrl); }

// tessera attiva?
$stmt = $pdo->prepare("
    SELECT id FROM memberships
    WHERE member_id = :mid AND status='active' AND end_date >= CURDATE()
    LIMIT 1
");
$stmt->execute([':mid' => $memberId]);
if (!$stmt->fetchColumn()) {
    flash_set($en
        ? 'Activate your annual card (free) before booking. Go to "Annual sign-up".'
        : 'Devi attivare la tessera annuale (gratis) prima di prenotare. Vai su "Iscrizione A.A.".');
    redirect($backUrl);
}

// certificato medico approvato dallo staff e non scaduto?
$stmt = $pdo->prepare("
    SELECT id, expires_at FROM medical_certificates
    WHERE member_id = :mid AND status='approved'
    ORDER BY expires_at DESC LIMIT 1
");
$stmt->execute([':mid' => $memberId]);
$cert = $stmt->fetch();
if (!$cert || $cert['expires_at'] < date('Y-m-d')) {
    flash_set($en
        ? 'A valid medical certificate approved by staff is required to book. Upload it from your area and wait for staff approval.'
        : 'Serve un certificato medico in corso di validità e approvato dallo staff per prenotare. Carica il certificato dall\'area personale e attendi la verifica.');
    redirect($backUrl);
}

// già prenotato?
$stmt = $pdo->prepare("
    SELECT id FROM bookings
    WHERE session_id = :sid AND user_id = :uid AND status='confirmed' LIMIT 1
");
$stmt->execute([':sid' => $sessionId, ':uid' => $uid]);
if ($stmt->fetchColumn()) {
    flash_set($en ? 'You are already booked for this session.' : 'Sei già prenotato a questa sessione.');
    redirect($backUrl);
}

// posti disponibili?
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings WHERE session_id = :sid AND status='confirmed'
");
$stmt->execute([':sid' => $sessionId]);
$confirmed = (int)$stmt->fetchColumn();
if ($confirmed >= (int)$cs['capacity']) {
    flash_set($en ? 'This session is full.' : 'Posti esauriti per questa sessione.');
    redirect($backUrl);
}

// inserisco
try {
    $stmt = $pdo->prepare("
        INSERT INTO bookings (session_id, user_id, status, booked_at)
        VALUES (:sid, :uid, 'confirmed', NOW())
    ");
    $stmt->execute([':sid' => $sessionId, ':uid' => $uid]);
    flash_set($en
        ? 'Booking confirmed: ' . $cs['title'] . ' on ' . format_datetime_it($cs['starts_at']) . '.'
        : 'Prenotazione confermata: ' . $cs['title'] . ' del ' . format_datetime_it($cs['starts_at']) . '.');
} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {
        flash_set($en ? 'You are already booked for this session.' : 'Sei già prenotato a questa sessione.');
    } else {
        flash_set($en ? 'Booking error, please retry.' : 'Errore durante la prenotazione, riprova.');
    }
}
redirect('/my-bookings.php');