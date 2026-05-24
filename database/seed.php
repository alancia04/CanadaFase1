<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
echo "Canada Gym, seed demo\n";

$adminExists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email='admin@canada.univaq.it'")->fetchColumn();
$groupsCount = (int)$pdo->query("SELECT COUNT(*) FROM `groups`")->fetchColumn();
if ($adminExists === 0 || $groupsCount < 4) {
    fwrite(STDERR, "ERRORE: seed.sql non risulta importato (admin mancante o meno di 4 gruppi).\n");
    fwrite(STDERR, "Esegui prima: mysql -u root -p canada_gym_traditional < canada-gym-traditional/database/seed.sql\n");
    exit(1);
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
foreach (['attendance', 'bookings', 'course_sessions', 'medical_certificates',
          'memberships', 'courses', 'course_categories', 'rooms',
          'instructors', 'members', 'announcements', 'user_preferences'] as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
    echo "  truncated $t\n";
}
$pdo->exec("DELETE FROM users WHERE email <> 'admin@canada.univaq.it'");
$pdo->exec("DELETE FROM users_has_groups WHERE users_id NOT IN (SELECT id FROM users)");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// password comune per tutti i demo
$pwHash = password_hash('Demo123!', PASSWORD_DEFAULT);

$createUser = function ($email, $first, $last, $phone = null) use ($pdo, $pwHash) {
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, phone, is_active)
        VALUES (:e, :h, :f, :l, :p, 1)
    ");
    $stmt->execute([':e' => strtolower($email), ':h' => $pwHash, ':f' => $first, ':l' => $last, ':p' => $phone]);
    return (int)$pdo->lastInsertId();
};

$assignGroup = function (int $uid, string $groupName) use ($pdo) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users_has_groups (users_id, groups_id)
        SELECT :u, id FROM `groups` WHERE name = :g LIMIT 1
    ");
    $stmt->execute([':u' => $uid, ':g' => $groupName]);
};

// utenti 
echo "→ utenti...\n";
$staffId   = $createUser('staff@canada.univaq.it',    'Giulia',  'Conti',  '0862432111');  $assignGroup($staffId, 'Staff');

$stud1Id   = $createUser('alessia.romano@studenti.univaq.it',   'Alessia', 'Romano',     null); $assignGroup($stud1Id, 'Studente');
$stud2Id   = $createUser('francesco.greco@studenti.univaq.it',  'Francesco','Greco',     null); $assignGroup($stud2Id, 'Studente');
$stud3Id   = $createUser('martina.ferri@studenti.univaq.it',    'Martina',  'Ferri',     null); $assignGroup($stud3Id, 'Studente');
$stud4Id   = $createUser('luca.moretti@studenti.univaq.it',     'Luca',     'Moretti',   null); $assignGroup($stud4Id, 'Studente');
$stud5Id   = $createUser('elena.barbieri@studenti.univaq.it',   'Elena',    'Barbieri',  null); $assignGroup($stud5Id, 'Studente');

$docId     = $createUser('paolo.deluca@univaq.it',    'Paolo',   'De Luca','0862432144'); $assignGroup($docId, 'Studente');
$persId    = $createUser('anna.mancini@univaq.it',    'Anna',    'Mancini',null);          $assignGroup($persId, 'Studente');

//  members 
echo "→ iscrizioni palestra (members)...\n";
$year = date('Y');
$counter = 1;
$createMember = function (int $uid, string $type, ?string $matricola) use ($pdo, $year, &$counter) {
    $code = sprintf('M-%s-%04d', $year, $counter++);
    $stmt = $pdo->prepare("
        INSERT INTO members (user_id, member_code, member_type, matricola, enrollment_date)
        VALUES (:u, :c, :t, :m, CURDATE())
    ");
    $stmt->execute([':u' => $uid, ':c' => $code, ':t' => $type, ':m' => $matricola]);
    return (int)$pdo->lastInsertId();
};

$mem1 = $createMember($stud1Id, 'studente', '270001');
$mem2 = $createMember($stud2Id, 'studente', '270002');
$mem3 = $createMember($stud3Id, 'studente', '270003');
$mem4 = $createMember($stud4Id, 'studente', '270004');
$mem5 = $createMember($stud5Id, 'studente', '270005');
$memDoc = $createMember($docId,  'docente',   null);
$memPers= $createMember($persId, 'personale', null);

//  categorie + corsi 
echo "→ categorie e attività...\n";
$insertCat = $pdo->prepare("INSERT INTO course_categories (name, slug, description) VALUES (:n, :s, :d)");
$insertCat->execute([':n' => 'Sala pesi',  ':s' => 'sala-pesi',  ':d' => 'Sala attrezzata con macchine, manubri e cardio. Accesso libero negli orari di apertura.']);
$catPesi = (int)$pdo->lastInsertId();
$insertCat->execute([':n' => 'Campo polivalente', ':s' => 'campo-polivalente', ':d' => 'Campo coperto polivalente usato per basket e calcetto indoor (calcio a 5). Una sola sala fisica: quando è prenotato per un\'attività non è disponibile per l\'altra in quello stesso orario.']);
$catPoli = (int)$pdo->lastInsertId();

$insertCourse = $pdo->prepare("
    INSERT INTO courses (category_id, instructor_id, title, slug, description, level, duration_minutes, is_published)
    VALUES (:c, NULL, :t, :s, :d, :l, :du, 1)
");
$courses = [
    [$catPesi, 'Sala pesi, accesso libero', 'sala-pesi-libero',
     "Slot da un'ora in sala pesi. Macchine guidate, pesi liberi, cardio. Niente istruttore: accesso libero per chi è iscritto.\n\nSi raccomanda di pulire l'attrezzatura dopo l'uso e di rispettare gli altri utenti.",
     'base', 60],
    [$catPoli, 'Campo basket, partita libera', 'campo-basket-libera',
     "Slot da un'ora sul campo polivalente per gioco amatoriale 3vs3 o 5vs5. Pallone fornito dal centro.\n\nIMPORTANTE: il campo è polivalente, condiviso con il calcetto indoor. Se in uno slot è prenotato per calcetto, il basket non è disponibile in quello stesso orario (e viceversa).",
     'base', 60],
    [$catPoli, 'Calcetto indoor, partita libera', 'calcetto-indoor-libera',
     "Slot da un'ora sul campo polivalente per calcio a 5 indoor amatoriale. Pallone fornito dal centro.\n\nIMPORTANTE: il campo è polivalente, condiviso con il basket. Se in uno slot è prenotato per basket, il calcetto non è disponibile in quello stesso orario (e viceversa).",
     'base', 60],
];
foreach ($courses as $c) {
    $insertCourse->execute([':c' => $c[0], ':t' => $c[1], ':s' => $c[2], ':d' => $c[3], ':l' => $c[4], ':du' => $c[5]]);
}
$courseRows = $pdo->query("SELECT id, slug, duration_minutes FROM courses ORDER BY id")->fetchAll();
$idSalaPesi  = $courseRows[0]['id'];
$idBasket    = $courseRows[1]['id'];
$idCalcetto  = $courseRows[2]['id'];

//  sale 
echo "→ sale...\n";
$pdo->prepare("INSERT INTO rooms (name, capacity, location, notes) VALUES (:n, :c, :l, :no)")
    ->execute([':n' => 'Sala pesi',   ':c' => 20, ':l' => 'piano terra ala est',  ':no' => 'Macchine guidate, pesi liberi e cardio.']);
$pdo->prepare("INSERT INTO rooms (name, capacity, location, notes) VALUES (:n, :c, :l, :no)")
    ->execute([':n' => 'Campo polivalente', ':c' => 12, ':l' => 'palazzetto coperto', ':no' => 'Campo coperto polivalente: due canestri per basket e tracce per calcetto a 5. Una sola sala fisica usata alternativamente per le due attività.']);
$rooms = $pdo->query("SELECT id, name, capacity FROM rooms ORDER BY id")->fetchAll();
$roomPesi = (int)$rooms[0]['id'];
$roomPoli = (int)$rooms[1]['id'];

//  sessioni 
echo "→ sessioni...\n";
$insertSession = $pdo->prepare("
    INSERT INTO course_sessions (course_id, room_id, starts_at, ends_at, capacity, status)
    VALUES (:c, :r, :s, :e, :cap, :st)
");
$start = strtotime('today -7 days');
$totalSessions = 0;
$poliAlt = 0; 

// 12 slot fissi della sala pesi (09:00-21:00, ogni ora, capienza 7)
$gymSlots = [];
for ($h = 9; $h <= 20; $h++) {
    $gymSlots[] = [sprintf('%02d:00', $h), sprintf('%02d:00', $h + 1)];
}

for ($d = 0; $d < 35; $d++) {
    $day = strtotime("+$d days", $start);
    $dow = (int)date('N', $day);  

    // sala pesi: tutti i giorni dal lun al sab
    if ($dow >= 1 && $dow <= 6) {
        foreach ($gymSlots as $slot) {
            $startsAt = date('Y-m-d', $day) . ' ' . $slot[0] . ':00';
            $endsAt   = date('Y-m-d', $day) . ' ' . $slot[1] . ':00';
            $insertSession->execute([
                ':c' => $idSalaPesi, ':r' => $roomPesi,
                ':s' => $startsAt,   ':e' => $endsAt,
                ':cap' => 7, ':st' => 'scheduled',
            ]);
            $totalSessions++;
        }
    }

    // campo polivalente: 2 turni serali (18-19 e 19-20), alternati basket/calcetto.
    // una persona prenota e si prende il campo per quell'ora.
    if ($dow >= 1 && $dow <= 6) {
        foreach ([['18:00','19:00'], ['19:00','20:00']] as $slot) {
            $courseId = ($poliAlt++ % 2 === 0) ? $idBasket : $idCalcetto;
            $startsAt = date('Y-m-d', $day) . ' ' . $slot[0] . ':00';
            $endsAt   = date('Y-m-d', $day) . ' ' . $slot[1] . ':00';
            $status = ($d === 0) ? 'cancelled' : 'scheduled';
            $insertSession->execute([
                ':c' => $courseId, ':r' => $roomPoli,
                ':s' => $startsAt, ':e' => $endsAt,
                ':cap' => 1, ':st' => $status,
            ]);
            $totalSessions++;
        }
    }
}

// tessere 
echo "→ tessere annuali...\n";
$typeId = (int)$pdo->query("SELECT id FROM membership_types WHERE is_active=1 LIMIT 1")->fetchColumn();
if ($typeId > 0) {
    $insertM = $pdo->prepare("
        INSERT INTO memberships (member_id, type_id, start_date, end_date, status)
        VALUES (:m, :t, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 'active')
    ");
    foreach ([$mem1, $mem2, $mem3, $mem4, $memDoc, $memPers] as $mid) {
        $insertM->execute([':m' => $mid, ':t' => $typeId]);
    }
}

//  certificati medici 
echo "→ certificati medici...\n";
$adminId = (int)$pdo->query("SELECT id FROM users WHERE email='admin@canada.univaq.it'")->fetchColumn();

// i cert pre-esistenti del seed sono già stati controllati
$insertCert = $pdo->prepare("
    INSERT INTO medical_certificates
      (member_id, issued_at, expires_at, notes, status, verified_by, verified_at)
    VALUES (:m, :i, :e, :n, 'approved', :ad, :va)
");
$va = date('Y-m-d H:i:s', strtotime('-5 days'));
$insertCert->execute([':m' => $mem1, ':i' => date('Y-m-d', strtotime('-2 months')), ':e' => date('Y-m-d', strtotime('+10 months')), ':n' => 'Certificato sportivo non agonistico', ':ad' => $adminId, ':va' => $va]);
$insertCert->execute([':m' => $mem2, ':i' => date('Y-m-d', strtotime('-6 months')), ':e' => date('Y-m-d', strtotime('+25 days')),    ':n' => 'In scadenza, banner giallo',          ':ad' => $adminId, ':va' => $va]);
$insertCert->execute([':m' => $mem3, ':i' => date('Y-m-d', strtotime('-13 months')),':e' => date('Y-m-d', strtotime('-30 days')),    ':n' => 'SCADUTO: non puo prenotare',           ':ad' => $adminId, ':va' => $va]);
$insertCert->execute([':m' => $mem4, ':i' => date('Y-m-d', strtotime('-1 month')),  ':e' => date('Y-m-d', strtotime('+11 months')),  ':n' => null,                                   ':ad' => $adminId, ':va' => $va]);
$insertCert->execute([':m' => $memDoc,':i' => date('Y-m-d', strtotime('-3 months')),':e' => date('Y-m-d', strtotime('+9 months')),   ':n' => null,                                   ':ad' => $adminId, ':va' => $va]);


//  prenotazioni 
echo "→ prenotazioni demo...\n";
$futureSessions = $pdo->query("
    SELECT id FROM course_sessions WHERE starts_at >= NOW() AND status='scheduled'
    ORDER BY starts_at ASC LIMIT 10
")->fetchAll();
$insertB = $pdo->prepare("INSERT IGNORE INTO bookings (session_id, user_id, status) VALUES (:s, :u, 'confirmed')");
foreach ([$stud1Id, $stud2Id, $stud4Id, $docId] as $i => $uid) {
    foreach (array_slice($futureSessions, $i * 2, 2) as $s) {
        $insertB->execute([':s' => $s['id'], ':u' => $uid]);
    }
}

// annunci 
echo "→ annunci...\n";
$insertA = $pdo->prepare("
    INSERT INTO announcements (author_id, title, body, is_published, published_at)
    VALUES (:a, :t, :b, 1, :p)
");
$insertA->execute([':a' => $staffId, ':t' => 'Apertura iscrizioni A.A. 2025/26',
    ':b' => "Le iscrizioni al Centro sportivo Canada per l'anno accademico 2025/26 sono aperte. Studenti, docenti e personale Univaq possono attivare la tessera annuale gratuita dall'area personale.\n\nPer prenotare gli slot in sala pesi e sul campo da basket è necessario aver caricato un certificato medico in corso di validità.",
    ':p' => date('Y-m-d H:i:s', strtotime('-7 days'))]);
$insertA->execute([':a' => $staffId, ':t' => 'Orari sala pesi: nuovi slot',
    ':b' => "Da questa settimana la sala pesi è prenotabile in slot da un'ora il lunedì, mercoledì e venerdì. Posti limitati a 7 a slot.",
    ':p' => date('Y-m-d H:i:s', strtotime('-3 days'))]);
$insertA->execute([':a' => $staffId, ':t' => 'Campo basket: partita libera mar-gio-sab',
    ':b' => "Il campo da basket è disponibile per gioco amatoriale martedì, giovedì e sabato dalle 19 alle 21. Si gioca 3vs3 o 5vs5, fino a 12 prenotazioni a sessione.\n\nIl pallone è fornito dal centro.",
    ':p' => date('Y-m-d H:i:s', strtotime('-1 day'))]);

echo "\nFatto. Dati demo creati:\n";
echo "  - 1 staff, 5 studenti, 1 docente, 1 personale (niente istruttori)\n";
echo "  - 2 categorie (Sala pesi / Campo basket), 2 corsi-attività, 2 sale, $totalSessions sessioni\n";
echo "  - 6 tessere attive, 5 certificati (1 in scadenza, 1 scaduto, 2 senza cert)\n";
echo "  - 8 prenotazioni demo, 3 annunci\n\n";

echo "Credenziali demo (password = Demo123! per tutti):\n";
echo "  Staff:      staff@canada.univaq.it\n";
echo "  Studenti:   alessia.romano@studenti.univaq.it (cert ok)\n";
echo "              francesco.greco@studenti.univaq.it (cert in scadenza)\n";
echo "              martina.ferri@studenti.univaq.it (cert scaduto)\n";
echo "              luca.moretti@studenti.univaq.it / elena.barbieri@studenti.univaq.it\n";
echo "  Docente:    paolo.deluca@univaq.it\n";
echo "  Personale:  anna.mancini@univaq.it\n";
echo "Admin:        admin@canada.univaq.it / Admin123! (dal seed.sql)\n";
