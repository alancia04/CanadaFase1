<?php

require_once __DIR__ . '/db.php';

// 19 servizi (allineati al seed.sql)
const SVC_MANAGE_USERS         = 'manage_users';
const SVC_MANAGE_MEMBERS       = 'manage_members';
const SVC_MANAGE_INSTRUCTORS   = 'manage_instructors';
const SVC_MANAGE_GROUPS        = 'manage_groups';
const SVC_MANAGE_SERVICES      = 'manage_services';
const SVC_MANAGE_COURSES       = 'manage_courses';
const SVC_MANAGE_ROOMS         = 'manage_rooms';
const SVC_MANAGE_MEMBERSHIPS   = 'manage_memberships';
const SVC_MANAGE_BOOKINGS      = 'manage_bookings';
const SVC_MANAGE_ATTENDANCE    = 'manage_attendance';
const SVC_MANAGE_CERTIFICATES  = 'manage_certificates';
const SVC_DELETE_CERTIFICATES  = 'delete_certificates';
const SVC_MANAGE_ANNOUNCEMENTS = 'manage_announcements';
const SVC_VIEW_DASHBOARD       = 'view_dashboard';
const SVC_VIEW_REPORTS         = 'view_reports';
const SVC_VIEW_OWN_BOOKINGS    = 'view_own_bookings';
const SVC_VIEW_OWN_MEMBERSHIP  = 'view_own_membership';
const SVC_BOOK_COURSE_SESSION  = 'book_course_session';
const SVC_CANCEL_OWN_BOOKING   = 'cancel_own_booking';

// fa il JOIN users -> users_has_groups -> services_has_groups -> services
function user_has_service(int $userId, string $service): bool {
    if ($userId <= 0 || $service === '') return false;

    $sql = "
        SELECT 1
        FROM users_has_groups uhg
        JOIN services_has_groups shg ON shg.groups_id = uhg.groups_id
        WHERE uhg.users_id = :uid AND shg.services_username = :svc
        LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([':uid' => $userId, ':svc' => $service]);
    return (bool)$stmt->fetchColumn();
}

// nomi dei gruppi dell'utente 
function user_groups(int $userId): array {
    $sql = "
        SELECT g.name
        FROM `groups` g
        JOIN users_has_groups uhg ON uhg.groups_id = g.id
        WHERE uhg.users_id = :uid
        ORDER BY g.name
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    return array_column($stmt->fetchAll(), 'name');
}
