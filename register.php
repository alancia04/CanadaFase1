<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) redirect('/');

// regole di accettazione email per Univaq
function is_univaq_email(string $email): bool {
    // accetta @univaq.it e qualsiasi sottodominio 
    return (bool)preg_match('/@([a-z0-9-]+\.)*univaq\.it$/i', $email);
}

$errors = [];
$form = [
    'email'       => '',
    'first_name'  => '',
    'last_name'   => '',
    'phone'       => '',
    'birth_date'  => '',
    'member_type' => 'studente',
    'matricola'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($form) as $k) {
        $form[$k] = trim($_POST[$k] ?? '');
    }
    $pwd     = $_POST['password']      ?? '';
    $pwdConf = $_POST['password_conf'] ?? '';

    // validation
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida.';
    } elseif (!is_univaq_email($form['email'])) {
        $errors[] = 'Servono email Univaq (es. nome.cognome@studenti.univaq.it o @univaq.it). La palestra Canada è riservata agli affiliati Univaq.';
    }
    if ($form['first_name'] === '' || $form['last_name'] === '') {
        $errors[] = 'Nome e cognome obbligatori.';
    }
    if (!in_array($form['member_type'], ['studente','docente','personale'], true)) {
        $errors[] = 'Affiliazione non valida.';
    }
    if ($form['member_type'] === 'studente' && $form['matricola'] === '') {
        $errors[] = 'Per gli studenti la matricola è obbligatoria.';
    }
    if ($form['matricola'] !== '' && !preg_match('/^[A-Za-z0-9]{4,20}$/', $form['matricola'])) {
        $errors[] = 'Matricola: solo lettere e numeri (4-20 caratteri).';
    }
    if (strlen($pwd) < 8) {
        $errors[] = 'Password lunga almeno 8 caratteri.';
    }
    if ($pwd !== $pwdConf) {
        $errors[] = 'Le due password non coincidono.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // 1) crea l'account
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, phone, birth_date, is_active)
                VALUES (:email, :hash, :fn, :ln, :ph, :bd, 1)
            ");
            $stmt->execute([
                ':email' => strtolower($form['email']),
                ':hash'  => password_hash($pwd, PASSWORD_DEFAULT),
                ':fn'    => $form['first_name'],
                ':ln'    => $form['last_name'],
                ':ph'    => $form['phone'] ?: null,
                ':bd'    => $form['birth_date'] ?: null,
            ]);
            $newId = (int)$pdo->lastInsertId();

            // 2) genera member_code progressivo
            $year = date('Y');
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(CAST(SUBSTRING(member_code, 8) AS UNSIGNED)), 0) + 1 AS next_num
                FROM members WHERE member_code LIKE :prefix
            ");
            $stmt->execute([':prefix' => "M-$year-%"]);
            $next = (int)$stmt->fetchColumn();
            $memberCode = sprintf('M-%s-%04d', $year, $next);

            // 3) crea l'anagrafica palestra 
            $stmt = $pdo->prepare("
                INSERT INTO members (user_id, member_code, member_type, matricola, enrollment_date)
                VALUES (:uid, :code, :mt, :mat, CURDATE())
            ");
            $stmt->execute([
                ':uid'  => $newId,
                ':code' => $memberCode,
                ':mt'   => $form['member_type'],
                ':mat'  => $form['matricola'] ?: null,
            ]);

            // 4) assegna al gruppo "Studente" 
            $stmt = $pdo->prepare("
                INSERT INTO users_has_groups (users_id, groups_id)
                SELECT :uid, id FROM `groups` WHERE name = 'Studente' LIMIT 1
            ");
            $stmt->execute([':uid' => $newId]);

            $pdo->commit();

            flash_set('Registrazione completata. Codice tessera: ' . $memberCode . '. Per prenotare i corsi ti serve un certificato medico valido.');
            redirect('/login.php');
        } catch (PDOException $e) {
            if (db()->inTransaction()) db()->rollBack();
            if ($e->getCode() === '23000') {
                $msg = $e->getMessage();
                if (str_contains($msg, 'uniq_users_email'))            $errors[] = 'Email già registrata.';
                elseif (str_contains($msg, 'uniq_members_matricola'))  $errors[] = 'Matricola già registrata.';
                else                                                    $errors[] = 'Dato duplicato (email o matricola).';
            } else {
                $errors[] = 'Errore durante la registrazione.';
            }
        }
    }
}

chdir(PROJECT_ROOT);
require_once PROJECT_ROOT . '/includes/template.inc.php';

$body = new Template('skins/canada/dtml/auth-register');
$errBlock = '';
if ($errors) {
    $errBlock = '<div class="flash flash-error"><ul>';
    foreach ($errors as $e) $errBlock .= '<li>' . e($e) . '</li>';
    $errBlock .= '</ul></div>';
}
$body->setContent('errors',     $errBlock);
$body->setContent('email',      e($form['email']));
$body->setContent('first_name', e($form['first_name']));
$body->setContent('last_name',  e($form['last_name']));
$body->setContent('phone',      e($form['phone']));
$body->setContent('birth_date', e($form['birth_date']));
$body->setContent('matricola',  e($form['matricola']));
$body->setContent('type_studente_checked',  $form['member_type'] === 'studente'  ? 'checked' : '');
$body->setContent('type_docente_checked',   $form['member_type'] === 'docente'   ? 'checked' : '');
$body->setContent('type_personale_checked', $form['member_type'] === 'personale' ? 'checked' : '');

$en = current_lang() === 'en';
$labels = $en ? [
    'h_register' => 'Sign up',
    'intro'=> 'Canada Sports Centre is free for those affiliated to the University of L\'Aquila (students, faculty, staff). To book activities you need a valid medical certificate, which you upload after registering.',
    'legend_account'=> 'Account',
    'l_email'=> 'Univaq email',
    'email_ph' => 'name.surname@studenti.univaq.it',
    'email_help' => 'Only emails on the univaq.it domain (subdomains such as studenti.univaq.it are allowed).',
    'l_password' => 'Password',
    'pwd_minlen' => '(min 8 characters)',
    'l_password_conf'=> 'Confirm password',
    'legend_personal'=> 'Personal info',
    'l_first_name' => 'First name',
    'l_last_name'=> 'Last name',
    'l_phone' => 'Phone',
    'l_birth' => 'Date of birth',
    'legend_affiliation'=> 'Univaq affiliation',
    'l_student'=> 'Student',
    'l_faculty'=> 'Faculty',
    'l_staff'=> 'Staff',
    'l_matricola'=> 'Student ID',
    'matricola_ph'=> 'e.g. 234567',
    'matricola_help'=> 'Required for students. Letters and digits, 4 to 20 characters.',
    'btn_register'=> 'Sign up',
    'already_have'=> 'Already have an account?',
    'link_login'=> 'Log in',
] : [
    'h_register'=> 'Registrazione',
    'intro'=> 'La palestra Canada è gratuita per chi è affiliato all\'Università degli Studi dell\'Aquila (studenti, docenti, personale). Per prenotare i corsi serve un certificato medico valido. Lo carichi dopo la registrazione.',
    'legend_account'=> 'Account',
    'l_email'=> 'Email Univaq',
    'email_ph'=> 'nome.cognome@studenti.univaq.it',
    'email_help'=> 'Accetto solo email del dominio univaq.it (anche sottodomini come studenti.univaq.it).',
    'l_password'=> 'Password',
    'pwd_minlen' => '(min 8 caratteri)',
    'l_password_conf' => 'Ripeti password',
    'legend_personal' => 'Anagrafica',
    'l_first_name' => 'Nome',
    'l_last_name'  => 'Cognome',
    'l_phone' => 'Telefono',
    'l_birth'=> 'Data di nascita',
    'legend_affiliation'=> 'Affiliazione Univaq',
    'l_student'  => 'Studente',
    'l_faculty'  => 'Docente',
    'l_staff'   => 'Personale',
    'l_matricola'   => 'Matricola',
    'matricola_ph'  => 'es. 234567',
    'matricola_help'  => 'Obbligatoria per gli studenti. Lettere e numeri, da 4 a 20 caratteri.',
    'btn_register'  => 'Registrati',
    'already_have' => 'Hai già un account?',
    'link_login' => 'Accedi',
];
foreach ($labels as $k => $v) { $body->setContent($k, $v); }

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', ($en ? 'Sign up' : 'Registrazione') . ' | Canada');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body',  $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
