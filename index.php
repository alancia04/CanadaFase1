<?php
// homepage con accesso admin e utente

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    $u = current_user();
    if (user_has_service((int)$u['id'], SVC_VIEW_DASHBOARD)) {
        redirect('/admin/index.php');
    }
}

$en       = current_lang() === 'en';
$isLogged = is_logged_in();
$pdo      = null;

try { $pdo = db(); } catch (Throwable $e) { /* DB non configurato, mostro placeholder */ }

// blocco annunci

$annHtml = '';
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT title, body, published_at FROM announcements
            WHERE is_published = 1 ORDER BY published_at DESC LIMIT 3
        ");
        foreach ($stmt as $a) {
            $annHtml .= '<div class="ann-item">';
            $annHtml .= '<h4>' . e($a['title']) . '</h4>';
            $annHtml .= '<p class="muted small">' . e(format_datetime_it($a['published_at'])) . '</p>';
            $annHtml .= '<p class="small">' . nl2br(e(mb_strimwidth($a['body'], 0, 180, '…'))) . '</p>';
            $annHtml .= '</div>';
        }
    } catch (Throwable $e) { /* ignoro: vado di placeholder */ }
}
if ($annHtml === '') {
    $annHtml = '<p class="muted small">' . ($en ? 'No recent announcements.' : 'Nessuna comunicazione recente.') . '</p>';
} else {
    $all = $en ? 'see all &raquo;' : 'vedi tutte &raquo;';
    $annHtml .= '<p class="small" style="margin-top:8px"><a href="/announcements.php">' . $all . '</a></p>';
}

// HOME LOGGATA (studente, docente, personale)

if ($isLogged) {
    $u   = current_user();
    $uid = (int)$u['id'];
    $name = trim($u['name'] . ' ' . $u['surname']);

    $stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $uid]);
    $memberId = (int)$stmt->fetchColumn();

    $cert = null;
    if ($memberId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM medical_certificates WHERE member_id = :m ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([':m' => $memberId]);
        $cert = $stmt->fetch() ?: null;
    }
    $certBannerHtml = $memberId > 0 ? banner_certificato($cert) : '';

    // prossime prenotazioni (max 3)
    $stmt = $pdo->prepare("
        SELECT b.id, cs.starts_at, cs.ends_at, c.title, r.name AS room_name
        FROM bookings b
        JOIN course_sessions cs ON cs.id = b.session_id
        JOIN courses c ON c.id = cs.course_id
        JOIN rooms r ON r.id = cs.room_id
        WHERE b.user_id = :uid AND b.status = 'confirmed' AND cs.starts_at >= NOW()
        ORDER BY cs.starts_at ASC LIMIT 3
    ");
    $stmt->execute([':uid' => $uid]);
    $upcoming = $stmt->fetchAll();

    $upcomingHtml = '';
    if (!$upcoming) {
        $upcomingHtml = '<p class="muted">' . ($en
            ? 'No upcoming bookings yet. Pick a slot above.'
            : 'Non hai ancora prenotazioni in arrivo. Scegli uno slot qui sopra.') . '</p>';
    } else {
        $upcomingHtml = '<div class="upcoming-grid">';
        foreach ($upcoming as $b) {
            $upcomingHtml .= '<a class="upcoming-card" href="/my-bookings.php">';
            $upcomingHtml .= '<p class="up-when">' . e(format_datetime_it($b['starts_at'])) . '</p>';
            $upcomingHtml .= '<h4>' . e($b['title']) . '</h4>';
            $upcomingHtml .= '<p class="muted small">' . e($b['room_name']) . '</p>';
            $upcomingHtml .= '</a>';
        }
        $upcomingHtml .= '</div>';
        $allBookings = $en ? 'See all my bookings &raquo;' : 'Vedi tutte le mie prenotazioni &raquo;';
        $upcomingHtml .= '<p style="margin-top:10px"><a href="/my-bookings.php">' . $allBookings . '</a></p>';
    }

    $labels = $en ? [
        // hero loggato
        'hero_kicker'    => 'Canada Sports Centre',
        'hero_title'    => 'Welcome back, ' . e($name),
        'hero_sub'      => 'Book your weight room slot, basketball or 5-a-side. Manage your card and certificate from your account.',
        'btn_book'      => 'Open the calendar',
        'btn_my_bookings'=> 'My bookings',
        // book-now
        'h_book'        => 'Book a slot',
        'book_sub'      => 'Choose the activity. Slots are 60 minutes; the court is exclusive (one person per slot).',
        'b1_title'      => 'Weight room',
        'b1_text'       => 'Self-service room with machines, free weights and cardio. 12 hourly slots, max 7 people.',
        'b1_cta'        => 'See slots',
        'b2_title'      => 'Basketball',
        'b2_text'       => 'Multipurpose court used for basketball. 1 hour per slot.',
        'b2_cta'        => 'See slots',
        'b3_title'      => 'Indoor 5-a-side',
        'b3_text'       => 'Multipurpose court used for 5-a-side football. 1 hour per slot.',
        'b3_cta'        => 'See slots',
        // upcoming
        'h_upcoming'    => 'Upcoming bookings',
        // structures
        'h_structures'  => 'The centre',
        'structures_sub'=> 'Spaces inside the Canada sports centre in Coppito.',
        's1_title'      => 'Entrance and grounds',
        's1_text'       => 'Main entrance of the centre.',
        's2_title'      => 'Outdoor area',
        's2_text'       => 'Covered porch and outdoor seating.',
        's3_title'      => 'Weight room',
        's3_text'       => 'Cable machines, free weights, cardio.',
        's4_title'      => 'Study hall',
        's4_text'       => 'Wide hall with wood beams and natural light.',
        // info bar
        'h_hours'       => 'Opening hours',
        'l_weekdays'    => 'Mon-Fri',
        'l_saturday'    => 'Saturday',
        'l_sunday'      => 'Sunday',
        'l_closed'      => 'closed',
        'h_news'        => 'Announcements',
    ] : [
        // hero loggato
        'hero_kicker'    => 'Centro sportivo Canada',
        'hero_title'    => 'Bentornato, ' . e($name),
        'hero_sub'      => 'Prenota il tuo slot in sala pesi, basket o calcetto. Gestisci tessera e certificato dall\'area personale.',
        'btn_book'      => 'Apri il calendario',
        'btn_my_bookings'=> 'Le mie prenotazioni',
        // book-now
        'h_book'        => 'Prenota uno slot',
        'book_sub'      => 'Scegli l\'attività. Gli slot sono da 60 minuti; il campo è esclusivo (una persona per slot).',
        'b1_title'      => 'Sala pesi',
        'b1_text'       => 'Sala con macchine, pesi liberi e cardio. 12 slot orari, max 7 persone.',
        'b1_cta'        => 'Vedi slot',
        'b2_title'      => 'Basket',
        'b2_text'       => 'Campo polivalente usato per il basket. 1 ora a slot.',
        'b2_cta'        => 'Vedi slot',
        'b3_title'      => 'Calcetto indoor',
        'b3_text'       => 'Campo polivalente usato per calcio a 5. 1 ora a slot.',
        'b3_cta'        => 'Vedi slot',
        // upcoming
        'h_upcoming'    => 'Prossime prenotazioni',
        // structures
        'h_structures'  => 'Il centro',
        'structures_sub'=> 'Gli spazi del Centro sportivo Canada di Coppito.',
        's1_title'      => 'Ingresso e piazzale',
        's1_text'       => 'Ingresso principale del centro.',
        's2_title'      => 'Aree esterne',
        's2_text'       => 'Portico coperto e zona di accesso.',
        's3_title'      => 'Sala pesi',
        's3_text'       => 'Macchine guidate, pesi liberi, cardio.',
        's4_title'      => 'Sala studio',
        's4_text'       => 'Ampia aula con travi a vista e luce naturale.',
        // info bar
        'h_hours'       => 'Orari di apertura',
        'l_weekdays'    => 'Lun-Ven',
        'l_saturday'    => 'Sabato',
        'l_sunday'      => 'Domenica',
        'l_closed'      => 'chiuso',
        'h_news'        => 'Comunicazioni',
    ];

    chdir(PROJECT_ROOT);
    require_once 'canada-gym-traditional/includes/template.inc.php';

    $main = new Template('skins/canada/dtml/main');
    $body = new Template('skins/canada/dtml/home-logged');

    foreach ($labels as $k => $v) {
        $body->setContent($k, $v);
    }
    $body->setContent('cert_banner',    $certBannerHtml);
    $body->setContent('upcoming_block', $upcomingHtml);
    $body->setContent('announcements',  $annHtml);

    $main->setContent('topbar', topbar_html());
    $main->setContent('title', $en ? 'Home | Canada Sports Centre' : 'Home | Centro sportivo Canada');
    $main->setContent('nav', barra_utente());
    $main->setContent('flash', flash());
    $main->setContent('body', $body->get());
    $main->setContent('html_lang', current_lang());
    $main->setContent('brand_uni', t('brand.university'));
    $main->setContent('brand_center', t('brand.center'));
    $main->setContent('footer', footer_html());
    $main->close();
    exit;
}

// HOME ANONIMO

$labels = $en ? [
    'hero_kicker'    => 'University of L\'Aquila',
    'hero_title'    => 'Canada Sports Centre',
    'hero_sub'      => 'Free for Univaq students, faculty and staff. Weight room, indoor court, study hall. Sign up with your Univaq email and book your slots.',
    'btn_signup'    => 'Sign up free',
    'btn_calendar'  => 'See the calendar',
    'hero_badge'    => 'Free for Univaq affiliates &middot; medical certificate required',

    'h_structures'  => 'The centre',
    'structures_sub'=> 'Spaces and rooms inside the Canada sports centre in Coppito, L\'Aquila.',
    's1_title'      => 'Entrance and grounds',
    's1_text'       => 'Main entrance of the centre with public access. Open to the whole university community.',
    's1_meta'       => 'via Vetoio snc, Coppito',
    's2_title'      => 'Outdoor area',
    's2_text'       => 'Covered porch and outdoor seating. Lobby for athletes between sessions.',
    's2_meta'       => 'open during opening hours',
    's3_title'      => 'Weight room',
    's3_text'       => 'Cable machines, free weights, cardio. Self-service during opening hours, capacity 7 per slot.',
    's3_meta'       => '12 hourly slots, 09:00 - 21:00',
    's5_title'      => 'Multipurpose court',
    's5_text'       => 'Indoor multipurpose court for basketball or 5-a-side football. One activity at a time, exclusive booking.',
    's5_meta'       => '1 hour slots, one user per slot',
    's4_title'      => 'Study hall',
    's4_text'       => 'Wide hall with wood beams and natural light. Tables and chairs available for affiliates.',
    's4_meta'       => 'free access during opening hours',

    'h_how'         => 'How it works',
    'how1_t'        => 'Register',
    'how1_p'        => 'Create your account with your Univaq email and your affiliation (student, faculty, staff).',
    'how2_t'        => 'Activate your card',
    'how2_p'        => 'Annual card is free. Activate it from your personal area in one click.',
    'how3_t'        => 'Upload certificate',
    'how3_p'        => 'Upload your medical certificate (PDF). Staff approves it, then booking is unlocked.',
    'how4_t'        => 'Book your slot',
    'how4_p'        => 'Browse the calendar and book the slots you like. Weight room or court.',
    'btn_signup2'   => 'Sign up now',

    'h_hours'       => 'Opening hours',
    'l_weekdays'    => 'Mon-Fri',
    'l_saturday'    => 'Saturday',
    'l_sunday'      => 'Sunday',
    'l_closed'      => 'closed',
    'h_who'         => 'Who can sign up',
    'who_li1'       => 'students with active matriculation',
    'who_li2'       => 'faculty and researchers',
    'who_li3'       => 'technical-administrative staff',
    'who_note'      => 'Univaq identity is verified through the email at registration (univaq.it and subdomains).',
    'h_news'        => 'Announcements',
] : [
    'hero_kicker'    => 'Università degli Studi dell\'Aquila',
    'hero_title'    => 'Centro sportivo Canada',
    'hero_sub'      => 'Gratis per studenti, docenti e personale Univaq. Sala pesi, campo polivalente, sala studio. Registrati con la tua email Univaq e prenota i tuoi slot.',
    'btn_signup'    => 'Iscriviti gratis',
    'btn_calendar'  => 'Vedi il calendario',
    'hero_badge'    => 'Gratuito per gli affiliati Univaq &middot; serve certificato medico valido',

    'h_structures'  => 'Il centro',
    'structures_sub'=> 'Spazi e sale del Centro sportivo Canada di Coppito, L\'Aquila.',
    's1_title'      => 'Ingresso e piazzale',
    's1_text'       => 'Ingresso principale del centro con accesso pubblico. Aperto a tutta la comunità universitaria.',
    's1_meta'       => 'via Vetoio snc, Coppito',
    's2_title'      => 'Aree esterne',
    's2_text'       => 'Portico coperto e zona di accesso. Punto di ritrovo per gli affiliati prima delle sessioni.',
    's2_meta'       => 'disponibile negli orari di apertura',
    's3_title'      => 'Sala pesi',
    's3_text'       => 'Macchine guidate, pesi liberi, cardio. Accesso libero negli orari di apertura, capienza 7 a turno.',
    's3_meta'       => '12 slot da un\'ora, dalle 09:00 alle 21:00',
    's5_title'      => 'Campo polivalente',
    's5_text'       => 'Campo coperto polivalente per basket o calcetto a 5. Un\'attività alla volta, prenotazione esclusiva.',
    's5_meta'       => 'slot da un\'ora, una persona per slot',
    's4_title'      => 'Sala studio',
    's4_text'       => 'Ampia aula con travi a vista e luce naturale. Tavoli e sedie a disposizione degli affiliati.',
    's4_meta'       => 'accesso libero negli orari di apertura',

    'h_how'         => 'Come funziona',
    'how1_t'        => 'Registrati',
    'how1_p'        => 'Crea l\'account con la tua email Univaq e indica l\'affiliazione (studente, docente, personale).',
    'how2_t'        => 'Attiva la tessera',
    'how2_p'        => 'La tessera annuale è gratuita. Si attiva dall\'area personale con un click.',
    'how3_t'        => 'Carica il certificato',
    'how3_p'        => 'Carica il certificato medico (PDF). Lo staff lo approva e si sbloccano le prenotazioni.',
    'how4_t'        => 'Prenota lo slot',
    'how4_p'        => 'Sfoglia il calendario e prenota i turni che vuoi: sala pesi o campo.',
    'btn_signup2'   => 'Iscriviti adesso',

    'h_hours'       => 'Orari di apertura',
    'l_weekdays'    => 'Lun-Ven',
    'l_saturday'    => 'Sabato',
    'l_sunday'      => 'Domenica',
    'l_closed'      => 'chiuso',
    'h_who'         => 'Chi può iscriversi',
    'who_li1'       => 'studenti con matricola attiva',
    'who_li2'       => 'docenti e ricercatori in servizio',
    'who_li3'       => 'personale tecnico-amministrativo',
    'who_note'      => 'L\'identità Univaq è verificata tramite email alla registrazione (univaq.it e sottodomini).',
    'h_news'        => 'Comunicazioni',
];

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$main = new Template('skins/canada/dtml/main');
$body = new Template('skins/canada/dtml/home');

foreach ($labels as $k => $v) {
    $body->setContent($k, $v);
}
$body->setContent('announcements', $annHtml);

$main->setContent('topbar', topbar_html());
$main->setContent('title', $en ? 'Canada Sports Centre' : 'Centro sportivo Canada, Univaq');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent('html_lang', current_lang());
$main->setContent('brand_uni', t('brand.university'));
$main->setContent('brand_center', t('brand.center'));
$main->setContent('footer', footer_html());
$main->close();