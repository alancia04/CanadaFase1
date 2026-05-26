<?php
// helper procedurali sparsi, usati in più pagine.

require_once __DIR__ . '/lang.php';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

// flash messages alla buona, array in sessione, si svuotano dopo la lettura.
function flash_set(string $msg): void {
    if (!isset($_SESSION['flash'])) $_SESSION['flash'] = [];
    $_SESSION['flash'][] = $msg;
}

function flash(): string {
    if (empty($_SESSION['flash'])) return '';
    $out = '';
    foreach ($_SESSION['flash'] as $m) {
        $out .= '<div class="flash">' . e($m) . '</div>';
    }
    $_SESSION['flash'] = [];
    return $out;
}

// data MySQL 
function format_date_it(?string $sqlDate): string {
    if (!$sqlDate) return '';
    $ts = strtotime($sqlDate);
    if (!$ts) return '';
    if (current_lang() === 'en') {
        return date('M j, Y', $ts);
    }
    return date('d/m/Y', $ts);
}

// data+ora MySQL 
function format_datetime_it(?string $sqlDt): string {
    if (!$sqlDt) return '';
    $ts = strtotime($sqlDt);
    return $ts ? date('d/m/Y H:i', $ts) : '';
}

// barra utente del nav. 

function barra_utente(): string {
    $here = $_SERVER['SCRIPT_NAME'] ?? '';
    $isHere = function (string $path) use ($here): bool {
        if ($path === '/') return $here === '/index.php';
        if (str_ends_with($path, '/')) return str_starts_with($here, rtrim($path, '/') . '/');
        return $here === $path;
    };
    $link = function (string $href, string $label) use ($isHere): string {
        $cls = $isHere($href) ? 'nav-link active' : 'nav-link';
        return '<a class="' . $cls . '" href="' . e($href) . '">' . e($label) . '</a>';
    };

    if (!empty($_SESSION['user']['id'])) {
        $name    = $_SESSION['user']['name'] ?? '';
        $surname = $_SESSION['user']['surname'] ?? '';
        $label   = trim($name . ' ' . $surname);

        require_once __DIR__ . '/permissions.php';
        $isAdmin = user_has_service((int)$_SESSION['user']['id'], 'view_dashboard');

        $homeUrl    = $isAdmin ? '/admin/index.php' : '/';
        $homeActive = $isAdmin
            ? (str_starts_with($here, '/admin/') || $here === '/index.php')
            : ($here === '/index.php');
        $homeCls = $homeActive ? 'nav-link active' : 'nav-link';
        $out  = '<a class="' . $homeCls . '" href="' . e($homeUrl) . '">' . e(t('nav.home')) . '</a>';

        // per gli admin nascondiamo "area personale": loro non hanno una dashboard utente significativa. per studenti/docenti sì.
        if (!$isAdmin) {
            $out .= $link('/dashboard.php', t('nav.dashboard'));
        }

        $out .= $link('/reviews.php', current_lang() === 'en' ? 'Reviews' : 'Recensioni');
        $out .= '<span class="user-name">' . e($label !== '' ? $label : 'utente') . '</span>';
        $out .= '<a class="nav-link" href="/logout.php">' . e(t('nav.logout')) . '</a>';
        return $out;
    }

    return $link('/', t('nav.home'))
         . $link('/reviews.php', current_lang() === 'en' ? 'Reviews' : 'Recensioni')
         . $link('/login.php', t('nav.login'))
         . $link('/register.php', t('nav.register'));
}

// footer del sito. include i blocchi "Centro" e "Contatti" tradotti.
function footer_html(): string {
    $en = current_lang() === 'en';

    $h1 = $en ? 'Canada Sports Centre' : 'Centro sportivo Canada';
    $address = $en ? 'via Vetoio snc, Coppito, L\'Aquila' : 'via Vetoio snc, Coppito, L\'Aquila';
    $hours = $en
        ? 'Opening: Mon-Fri 9.00/21.30 &middot; Sat 9.00/18.00'
        : 'Apertura: lun-ven 9.00/21.30 &middot; sab 9.00/18.00';
    $service = $en
        ? 'Free service for Univaq students, faculty and staff.'
        : 'Servizio gratuito riservato a studenti, docenti e personale Univaq.';
    $h2 = $en ? 'Contacts' : 'Contatti';
    $project = $en
        ? ''
        : '';

    return '<footer class="site-footer"><div class="container">'
         . '<div>'
         . '<h4>' . e($h1) . '</h4>'
         . '<p>' . e($address) . '</p>'
         . '<p>' . $hours . '</p>'
         . '<p>' . e($service) . '</p>'
         . '</div>'
         . '<div>'
         . '<h4>' . e($h2) . '</h4>'
         . '<p>palestra@univaq.it</p>'
         . '<p>Tel. 0862 602729</p>'
         . ''
         . '</div>'
         . '</div></footer>';
}

// topbar superiore
function topbar_html(): string {
    $cur  = current_lang();
    $next = $_SERVER['REQUEST_URI'] ?? '/';

    if ($cur === 'it') {
        $toggle = '<span class="lang-toggle">'
                . '<span class="on">IT</span>'
                . '<a href="/lang.php?to=en&amp;next=' . urlencode($next) . '" aria-label="switch to English">EN</a>'
                . '</span>';
    } else {
        $toggle = '<span class="lang-toggle">'
                . '<a href="/lang.php?to=it&amp;next=' . urlencode($next) . '" aria-label="passa all\'italiano">IT</a>'
                . '<span class="on">EN</span>'
                . '</span>';
    }

    return '<div class="topbar"><div class="container">'
         . '<span>' . t('brand.topbar') . '</span>'
         . '<span class="topbar-right">'
         . '<a href="https://www.univaq.it" target="_blank" rel="noopener">univaq.it</a>'
         . $toggle
         . '</span>'
         . '</div></div>';
}


function admin_breadcrumb(string $current): string {
    return '<nav class="breadcrumb"><a href="/admin/index.php">Backoffice</a> '
         . '<span class="sep">&raquo;</span> '
         . '<span class="current">' . e($current) . '</span></nav>';
}

// nav contestuale per le pagine admin: lista di pagine correlate con evidenza di quella in cui ci si trova
function subnav_admin(string $title, array $items, string $current_url): string {
    $out = '<nav class="admin-subnav">';
    $out .= '<a class="back" href="/admin/index.php">&laquo; Backoffice</a>';
    $out .= '<span class="title">' . e($title) . '</span>';
    $out .= '<span class="tabs">';
    foreach ($items as [$label, $url]) {
        $cls = ($url === $current_url) ? 'tab active' : 'tab';
        $out .= '<a href="' . e($url) . '" class="' . $cls . '">' . e($label) . '</a>';
    }
    $out .= '</span>';
    $out .= '</nav>';
    return $out;
}

function subnav_anagrafica(string $current_url): string {
    return subnav_admin('Anagrafica', [
        ['Utenti', '/admin/users.php'],
        ['Gruppi', '/admin/groups.php'],
    ], $current_url);
}

function subnav_attivita(string $current_url): string {
    return subnav_admin('Attività', [
        ['Attività',     '/admin/courses.php'],
        ['Sale',         '/admin/rooms.php'],
        ['Sessioni',     '/admin/sessions.php'],
        ['Prenotazioni', '/admin/bookings.php'],
    ], $current_url);
}

// banner stato certificato medico. 
// le stringhe passano da t() per supportare IT/EN.
function banner_certificato(?array $cert): string {
    $cta = '<a class="btn btn-quiet" href="/my-certificate.php" style="margin-left:10px">'
         . e(t('cert.upload_button_cta')) . '</a>';

    if (!$cert) {
        $tail = current_lang() === 'en'
            ? 'Upload your certificate from your area to be able to book sessions.'
            : 'Carica il certificato dall\'area personale per poter prenotare le sessioni.';
        return '<div class="status-banner status-warn">'
             . '<strong>' . e(t('cert.banner_absent')) . '</strong> ' . $tail
             . $cta
             . '</div>';
    }

    $status     = $cert['status'] ?? 'pending';
    $expiresAt  = $cert['expires_at'] ?? null;
    $reason     = $cert['reject_reason'] ?? '';

    if ($status === 'pending') {
        $tail = current_lang() === 'en'
            ? 'Staff must verify the document. Bookings are blocked until it is approved.'
            : 'Lo staff deve verificare il documento. Le prenotazioni sono bloccate finché non viene approvato.';
        return '<div class="status-banner status-pending">'
             . '<strong>' . e(t('cert.banner_pending')) . '</strong> ' . $tail
             . '</div>';
    }
    if ($status === 'rejected') {
        $why = $reason !== ''
            ? (current_lang() === 'en' ? ' Reason: ' : ' Motivazione: ') . e($reason) . '.'
            : '';
        $upload = current_lang() === 'en' ? ' Upload a new document.' : ' Carica un nuovo documento.';
        return '<div class="status-banner status-error">'
             . '<strong>' . e(t('cert.banner_rejected')) . '</strong>' . $why . $upload
             . $cta
             . '</div>';
    }

    if (!$expiresAt) {
        $msg = current_lang() === 'en'
            ? 'Incomplete certificate. Contact staff.'
            : 'Certificato medico incompleto. Contatta lo staff.';
        return '<div class="status-banner status-warn"><strong>' . e($msg) . '</strong></div>';
    }
    $today = date('Y-m-d');
    $days = (strtotime($expiresAt) - strtotime($today)) / 86400;
    if ($days < 0) {
        $tail = current_lang() === 'en'
            ? 'Bookings are blocked until you upload a new one.'
            : 'Le prenotazioni sono bloccate finché non ne carichi uno nuovo.';
        return '<div class="status-banner status-error">'
             . '<strong>' . e(t('cert.banner_expired')) . ' ' . e(format_date_it($expiresAt)) . '.</strong> '
             . $tail . $cta
             . '</div>';
    }
    if ($days <= 30) {
        $until = current_lang() === 'en' ? 'valid until' : 'validità fino al';
        $daysLabel = current_lang() === 'en' ? 'days' : 'giorni';
        return '<div class="status-banner status-warn">'
             . '<strong>' . e(t('cert.banner_expiring')) . '</strong> '
             . $until . ' ' . e(format_date_it($expiresAt)) . ' (' . (int)$days . ' ' . $daysLabel . ').'
             . $cta
             . '</div>';
    }
    $until = current_lang() === 'en' ? 'until' : 'fino al';
    return '<div class="status-banner status-ok">'
         . '<strong>' . e(t('cert.banner_valid')) . '</strong> ' . $until . ' ' . e(format_date_it($expiresAt)) . '.'
         . '</div>';
}

function slugify(string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = strtolower(preg_replace('~[^a-zA-Z0-9]+~', '-', $s));
    return trim($s, '-');
}