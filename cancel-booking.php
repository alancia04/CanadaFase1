<?php
// POST handler: cancella una propria prenotazione

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/my-bookings.php');

$u = current_user();
$uid = (int)$u['id'];
$en = current_lang() === 'en';
if (!user_has_service($uid, SVC_CANCEL_OWN_BOOKING)) {
    flash_set($en ? 'Permission denied.' : 'Permesso negato.');
    redirect('/dashboard.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) redirect('/my-bookings.php');

$pdo = db();

// verifico ownership + che la sessione non sia ancora iniziata
$stmt = $pdo->prepare("
    SELECT b.id, cs.starts_at FROM bookings b
    JOIN course_sessions cs ON cs.id = b.session_id
    WHERE b.id = :id AND b.user_id = :uid AND b.status = 'confirmed' LIMIT 1
");
$stmt->execute([':id' => $id, ':uid' => $uid]);
$b = $stmt->fetch();

if (!$b) { flash_set($en ? 'Booking not found.' : 'Prenotazione non trovata.'); redirect('/my-bookings.php'); }
if (strtotime($b['starts_at']) <= time()) {
    flash_set($en ? 'You can\'t cancel a session that already started.' : 'Non puoi cancellare una sessione già iniziata.');
    redirect('/my-bookings.php');
}

$stmt = $pdo->prepare("UPDATE bookings SET status='cancelled', cancelled_at=NOW() WHERE id=:id");
$stmt->execute([':id' => $id]);
flash_set($en ? 'Booking cancelled.' : 'Prenotazione cancellata.');
redirect('/my-bookings.php');
