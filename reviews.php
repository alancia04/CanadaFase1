<?php
// pagina recensioni pubbliche del Centro sportivo Canada
// chiunque puo leggere; solo chi e loggato puo inserire

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$en  = current_lang() === 'en';
$me  = $_SESSION['user'] ?? null;
$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$me || empty($me['id'])) {
        flash_set($en ? 'Sign in or register to leave a review.' : 'Accedi o registrati per lasciare una recensione.');
        header('Location: /login.php?next=/reviews.php');
        exit;
    }
    $rating = (int)($_POST['rating'] ?? 0);
    $body   = trim((string)($_POST['body'] ?? ''));
    if ($rating < 1 || $rating > 5) {
        $err = $en ? 'Pick a rating between 1 and 5 stars.' : 'Scegli un voto tra 1 e 5 stelle.';
    } elseif (mb_strlen($body) < 10) {
        $err = $en ? 'Write at least 10 characters.' : 'Scrivi almeno 10 caratteri.';
    } elseif (mb_strlen($body) > 1500) {
        $err = $en ? 'Maximum 1500 characters.' : 'Massimo 1500 caratteri.';
    } else {
        $author = trim(($me['name'] ?? '') . ' ' . ($me['surname'] ?? ''));
        if ($author === '') $author = 'Utente Univaq';
        $stm = $pdo->prepare('INSERT INTO reviews (user_id, author_name, rating, body) VALUES (?, ?, ?, ?)');
        $stm->execute([(int)$me['id'], $author, $rating, $body]);
        header('Location: /reviews.php?ok=1');
        exit;
    }
}

if (isset($_GET['ok'])) {
    $ok = $en ? 'Thanks! Your review has been posted.' : 'Grazie! La tua recensione e stata pubblicata.';
}

$rows = $pdo->query('SELECT id, author_name, rating, body, created_at FROM reviews ORDER BY created_at DESC LIMIT 100')->fetchAll();

$avg = 0; $cnt = count($rows);
if ($cnt > 0) {
    $sum = 0;
    foreach ($rows as $r) $sum += (int)$r['rating'];
    $avg = round($sum / $cnt, 1);
}

function star_row(int $rating): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= $i <= $rating ? '<span class="star on">&#9733;</span>' : '<span class="star off">&#9734;</span>';
    }
    return $out;
}

$listHtml = '';
if (!$rows) {
    $listHtml = '<p class="muted">' . ($en ? 'No reviews yet, be the first!' : 'Ancora nessuna recensione, sii il primo!') . '</p>';
} else {
    foreach ($rows as $r) {
        $listHtml .= '<article class="review-item">';
        $listHtml .= '<div class="review-head">';
        $listHtml .= '<strong>' . e($r['author_name']) . '</strong>';
        $listHtml .= '<span class="stars">' . star_row((int)$r['rating']) . '</span>';
        $listHtml .= '</div>';
        $listHtml .= '<p class="muted small">' . e(format_datetime_it($r['created_at'])) . '</p>';
        $listHtml .= '<p>' . nl2br(e($r['body'])) . '</p>';
        $listHtml .= '</article>';
    }
}

// form
$formHtml = '';
$isLogged = !empty($me) && !empty($me['id']);
if (!$isLogged) {
    $formHtml  = '<div class="review-gate">';
    $formHtml .= '<p>' . ($en
        ? 'Only registered Univaq users can leave a review. Sign in with your Univaq email, or create a free account in a minute.'
        : 'Solo gli utenti Univaq registrati possono lasciare una recensione. Accedi con la tua email Univaq, oppure crea un account gratuito in un minuto.')
        . '</p>';
    $formHtml .= '<p class="review-gate-cta">';
    $formHtml .= '<a class="btn btn-primary" href="/register.php?next=/reviews.php">' . ($en ? 'Register' : 'Registrati') . '</a> ';
    $formHtml .= '<a class="btn btn-secondary" href="/login.php?next=/reviews.php">' . ($en ? 'Sign in' : 'Accedi') . '</a>';
    $formHtml .= '</p>';
    $formHtml .= '</div>';
} else {
    $name = trim(($me['name'] ?? '') . ' ' . ($me['surname'] ?? ''));
    $formHtml .= '<form method="post" class="review-form">';
    $formHtml .= '<p class="muted small">' . ($en ? 'You are posting as' : 'Stai pubblicando come') . ' <strong>' . e($name !== '' ? $name : 'Utente Univaq') . '</strong>.</p>';
    $formHtml .= '<fieldset class="rating-fieldset">';
    $formHtml .= '<legend>' . ($en ? 'Your rating' : 'Il tuo voto') . '</legend>';
    $formHtml .= '<div class="rating-stars">';
    for ($i = 5; $i >= 1; $i--) {
        $formHtml .= '<input type="radio" id="rate-' . $i . '" name="rating" value="' . $i . '" required>';
        $formHtml .= '<label for="rate-' . $i . '" title="' . $i . ' / 5">&#9733;</label>';
    }
    $formHtml .= '</div>';
    $formHtml .= '</fieldset>';
    $formHtml .= '<label for="body">' . ($en ? 'Tell us about your experience' : 'Raccontaci la tua esperienza') . '</label>';
    $formHtml .= '<textarea id="body" name="body" rows="4" minlength="10" maxlength="1500" required placeholder="' . ($en ? 'What did you like? What could improve?' : 'Cosa ti e piaciuto? Cosa si puo migliorare?') . '"></textarea>';
    $formHtml .= '<button type="submit" class="btn btn-primary">' . ($en ? 'Post review' : 'Pubblica recensione') . '</button>';
    $formHtml .= '</form>';
}

// alert
$alertHtml = '';
if ($err !== '') $alertHtml = '<div class="alert alert-error">' . e($err) . '</div>';
if ($ok  !== '') $alertHtml = '<div class="alert alert-success">' . e($ok) . '</div>';

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/reviews-list');
$body->setContent('h_title',   $en ? 'Reviews' : 'Recensioni');
$body->setContent('intro',     $en ? 'What people say about the Canada sports centre.' : 'Cosa pensano del Centro sportivo Canada.');
$body->setContent('avg',       number_format((float)$avg, 1, ',', '.'));
$body->setContent('avg_stars', star_row((int)round($avg)));
$body->setContent('count',     (string)$cnt);
$body->setContent('l_avg',     $en ? 'average rating' : 'voto medio');
$body->setContent('l_count',   $cnt === 1 ? ($en ? 'review' : 'recensione') : ($en ? 'reviews' : 'recensioni'));
$body->setContent('h_post',    $en ? 'Leave your review' : 'Lascia la tua recensione');
$body->setContent('h_recent',  $en ? 'Most recent' : 'Piu recenti');
$body->setContent('form',      $formHtml);
$body->setContent('list',      $listHtml);
$body->setContent('alert',     $alertHtml);

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', ($en ? 'Reviews' : 'Recensioni') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent('html_lang', current_lang());
$main->setContent('brand_uni', t('brand.university'));
$main->setContent('brand_center', t('brand.center'));
$main->setContent('footer', footer_html());
$main->close();