<?php
// login / logout / current_user + guard per le pagine private

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/helpers.php';

// l'utente loggato sta in $_SESSION['user'] 
// quando is_logged_in() torna true le chiavi reali sono state messe da login()
function is_logged_in(): bool {
    return !empty($_SESSION['user']['id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return $_SESSION['user'];
}

// verifica credenziali, se va bene popola la sessione e rigenera l'id
function login(string $email, string $password): bool {
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') return false;

    $stmt = db()->prepare("SELECT id, email, password_hash, first_name, last_name, is_active FROM users WHERE email = :e LIMIT 1");
    $stmt->execute([':e' => $email]);
    $row = $stmt->fetch();
    if (!$row || !$row['is_active']) return false;
    if (!password_verify($password, $row['password_hash'])) return false;

    // contro session fixation
    session_regenerate_id(true);

    // chiavi attese dal template del docente: username, name, surname, email
    // aggiungo id e groups per comodità 
    $_SESSION['user'] = [
        'id'       => (int)$row['id'],
        'username' => $row['email'],
        'email'    => $row['email'],
        'name'     => $row['first_name'],
        'surname'  => $row['last_name'],
        'groups'   => user_groups((int)$row['id']),
    ];
    return true;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_unset();
    session_destroy();
}

// se non loggato rimando al login
function require_login(): void {
    if (!is_logged_in()) {
        $next = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login.php?next=' . urlencode($next));
        exit;
    }
}

// blocca chi non ha il servizio richiesto
function require_service(string $service): void {
    require_login();
    $u = current_user();
    if (!user_has_service((int)$u['id'], $service)) {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>403</title>';
        echo '<h1>403, accesso negato</h1>';
        echo '<p>Non hai il permesso necessario per questa pagina (' . e($service) . ').</p>';
        echo '<p><a href="/">torna alla home</a></p>';
        exit;
    }
}