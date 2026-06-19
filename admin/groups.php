<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_service(SVC_MANAGE_GROUPS);

$pdo = db();
$gid = (int)($_GET['id'] ?? 0);
$q   = trim($_GET['q'] ?? '');

// lista gruppi
$groups = $pdo->query("
    SELECT g.id, g.name, g.description, COUNT(DISTINCT uhg.users_id) AS user_count
    FROM `groups` g
    LEFT JOIN users_has_groups uhg ON uhg.groups_id = g.id
    GROUP BY g.id
    ORDER BY g.name
")->fetchAll();

$rowsHtml = '';
foreach ($groups as $g) {
    $isActive = ($gid === (int)$g['id']);
    $rowsHtml .= '<tr' . ($isActive ? ' class="row-active"' : '') . '>';
    $rowsHtml .= '<td>' . (int)$g['id'] . '</td>';
    $rowsHtml .= '<td><a href="/admin/groups.php?id=' . (int)$g['id'] . '"><strong>' . e($g['name']) . '</strong></a></td>';
    $rowsHtml .= '<td>' . e($g['description'] ?? '') . '</td>';
    $rowsHtml .= '<td>' . (int)$g['user_count'] . '</td>';
    $rowsHtml .= '<td><a class="btn btn-quiet" href="/admin/groups.php?id=' . (int)$g['id'] . '">vedi utenti</a></td>';
    $rowsHtml .= '</tr>';
}

$detailHtml = '';
$selectedName = '';
if ($gid > 0) {
    $stmt = $pdo->prepare("SELECT name FROM `groups` WHERE id=:id");
    $stmt->execute([':id' => $gid]);
    $selectedName = (string)$stmt->fetchColumn();

    if ($selectedName !== '') {
        $sql = "
            SELECT u.id, u.email, u.first_name, u.last_name, u.is_active
            FROM users u
            JOIN users_has_groups uhg ON uhg.users_id = u.id
            WHERE uhg.groups_id = :gid
        ";
        $params = [':gid' => $gid];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= " AND (u.email LIKE :q1 OR u.first_name LIKE :q2 OR u.last_name LIKE :q3 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :q4)";
            $params[':q1'] = $like; $params[':q2'] = $like; $params[':q3'] = $like; $params[':q4'] = $like;
        }
        $sql .= " ORDER BY u.last_name, u.first_name LIMIT 300";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $usersOfGroup = $stmt->fetchAll();

        $userRows = '';
        foreach ($usersOfGroup as $u) {
            $userRows .= '<tr>';
            $userRows .= '<td>' . (int)$u['id'] . '</td>';
            $userRows .= '<td>' . e($u['first_name'] . ' ' . $u['last_name']) . '</td>';
            $userRows .= '<td><small>' . e($u['email']) . '</small></td>';
            $userRows .= '<td>' . ($u['is_active'] ? 'sì' : 'no') . '</td>';
            $userRows .= '</tr>';
        }
        if (!$usersOfGroup) {
            $userRows = '<tr><td colspan="4" class="muted">Nessun utente in questo gruppo' . ($q !== '' ? ' che corrisponde a "' . e($q) . '".' : '.') . '</td></tr>';
        }

        $detailHtml = '<article class="panel">'
                    . '<h2>Utenti del gruppo: ' . e($selectedName) . '</h2>'
                    . '<form method="get" action="/admin/groups.php" class="search-bar">'
                    . '<input type="hidden" name="id" value="' . $gid . '">'
                    . '<input type="text" name="q" value="' . e($q) . '" placeholder="Cerca per nome, cognome o email" aria-label="Cerca utente nel gruppo">'
                    . '<button class="btn" type="submit">Cerca</button>'
                    . '<a class="btn btn-outline" href="/admin/groups.php?id=' . $gid . '">Reset</a>'
                    . '</form>'
                    . '<p class="muted small">Risultati: <strong>' . count($usersOfGroup) . '</strong></p>'
                    . '<table class="list">'
                    . '<thead><tr><th>id</th><th>nome</th><th>email</th><th>attivo</th></tr></thead>'
                    . '<tbody>' . $userRows . '</tbody>'
                    . '</table>'
                    . '</article>';
    }
}

chdir(PROJECT_ROOT);
require_once 'canada-gym-traditional/includes/template.inc.php';

$body = new Template('skins/canada/dtml/admin-groups');
$body->setContent('subnav', subnav_anagrafica('/admin/groups.php'));
$body->setContent('rows', $rowsHtml);
$body->setContent('detail', $detailHtml);

$main = new Template('skins/canada/dtml/main');
$main->setContent('topbar', topbar_html());
$main->setContent('title', 'Gruppi | Backoffice');
$main->setContent('nav', barra_utente());
$main->setContent('flash', flash());
$main->setContent('body', $body->get());
$main->setContent("html_lang", current_lang());
$main->setContent("brand_uni", t("brand.university"));
$main->setContent("brand_center", t("brand.center"));
$main->setContent("footer", footer_html());
$main->close();
