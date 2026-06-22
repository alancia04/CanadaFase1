    <?php
    // area personale

    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/session.php';
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/helpers.php';

    require_login();
    $u   = current_user();
    $uid = (int)$u['id'];
    $pdo = db();
    $en  = current_lang() === 'en';

    // scheda member
    $stmt = $pdo->prepare("SELECT * FROM members WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $uid]);
    $member = $stmt->fetch();

    // certificato medico più recente
    $cert = null;
    if ($member) {
        $stmt = $pdo->prepare("SELECT * FROM medical_certificates WHERE member_id = :mid ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([':mid' => $member['id']]);
        $cert = $stmt->fetch() ?: null;
    }

    $cert = $cert ?: ['status' => 'mancante', 'expires_at' => null];

    // tessera attiva
    $membership = null;
    if ($member) {
        $stmt = $pdo->prepare("
            SELECT m.*, mt.name AS type_name
            FROM memberships m JOIN membership_types mt ON mt.id = m.type_id
            WHERE m.member_id = :mid AND m.status = 'active' AND m.end_date >= CURDATE()
            ORDER BY m.end_date DESC LIMIT 1
        ");
        $stmt->execute([':mid' => $member['id']]);
        $membership = $stmt->fetch() ?: null;
    }

    chdir(PROJECT_ROOT);
    require_once PROJECT_ROOT . '/includes/template.inc.php';

    $body = new Template('skins/canada/dtml/dashboard');

    $name    = trim($u['name'] . ' ' . $u['surname']);
    $initials = strtoupper(mb_substr($u['name'], 0, 1) . mb_substr($u['surname'], 0, 1));

    $body->setContent('bc',           $en ? 'My account' : 'Area personale');
    $body->setContent('initials',     e($initials !== '' ? $initials : 'U'));
    $body->setContent('user_name',    e($name !== '' ? $name : 'Utente'));
    $body->setContent('user_email',   e($u['email']));
    $body->setContent('l_member_code',$en ? 'Member code' : 'Codice tessera');
    $body->setContent('l_member_type',$en ? 'Type'        : 'Tipo affiliazione');
    $body->setContent('l_matricola',  $en ? 'Student ID'  : 'Matricola');
    $body->setContent('member_code',  $member ? e($member['member_code']) : '<span class="muted">' . ($en ? 'not a gym member' : 'non iscritto') . '</span>');
    $body->setContent('member_type',  $member ? e($member['member_type']) : '-');
    $body->setContent('matricola',    $member && $member['matricola'] ? e($member['matricola']) : '-');
    $body->setContent('btn_edit_data',        $en ? 'Edit my data' : 'Modifica i miei dati');
    $body->setContent('btn_change_password',  $en ? 'Change password' : 'Modifica password');

    $body->setContent('h_tessera', $en ? 'Annual card' : 'Tessera annuale');
    $body->setContent('h_cert',    $en ? 'Medical certificate' : 'Certificato medico');

    // blocco tessera
    if (!$member) {
        $body->setContent('membership_block', '<p class="muted">' . ($en
            ? 'You are not registered as a gym member.'
            : 'Non risulti iscritto alla palestra.') . '</p>');
    } elseif (!$membership) {
        $msg = $en ? 'You have no active card.' : 'Non hai una tessera attiva.';
        $cta = $en ? 'Activate the annual card (free)' : 'Attiva la tessera annuale (gratis)';
        $body->setContent('membership_block',
            '<p>' . $msg . '</p>' . '<p><a class="btn" href="/enroll.php">' . $cta . '</a></p>');
    } else {
        $until = $en ? 'Active until' : 'Attiva fino al';
        $body->setContent('membership_block',
            '<p><strong>' . e($membership['type_name']) . '</strong></p>' . '<p class="muted">' . $until . ' <strong>' . e(format_date_it($membership['end_date'])) . '</strong></p>' . '<p><a href="/my-membership.php">' . ($en ? 'See details &raquo;' : 'Vedi i dettagli &raquo;') . '</a></p>');
    }

    // blocco certificato
    if (!$member) {
        $body->setContent('cert_block', '<p class="muted">-</p>');
    } else {
        $banner = banner_certificato($cert);
        $manage = $en ? 'Manage the certificate &raquo;' : 'Gestisci il certificato &raquo;';
        $body->setContent('cert_block', $banner . '<p><a href="/my-certificate.php">' . $manage . '</a></p>');
    }

    $main = new Template('skins/canada/dtml/main');
    $main->setContent('topbar', topbar_html());
    $main->setContent('title', ($en ? 'My account' : 'Area personale') . ' | Canada');
    $main->setContent('nav', barra_utente());
    $main->setContent('flash', flash());
    $main->setContent('body', $body->get());
    $main->setContent('html_lang', current_lang());
    $main->setContent('brand_uni', t('brand.university'));
    $main->setContent('brand_center', t('brand.center'));
    $main->setContent('footer', footer_html());
    $main->close();