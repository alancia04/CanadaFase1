
USE canada_gym_traditional;


-- gruppi

INSERT INTO `groups` (name, description) VALUES
    ('Admin',     'Controllo totale del sistema'),
    ('Staff',     'Gestione operativa palestra: iscritti, abbonamenti, pagamenti, certificati'),
    ('Istruttore','Gestione presenze sulle proprie sessioni'),
    ('Studente',  'Utente finale: prenota corsi, vede abbonamento e certificato');


-- servizi (permessi)

INSERT INTO services (username, description) VALUES
    ('manage_users',         'Gestione completa utenti'),
    ('manage_members',       'Anagrafica iscritti palestra (members)'),
    ('manage_instructors',   'Anagrafica istruttori'),
    ('manage_groups',        'Gestione gruppi'),
    ('manage_services',      'Gestione servizi e matrice gruppi-servizi'),
    ('manage_courses',       'CRUD corsi, categorie, sale e sessioni'),
    ('manage_rooms',         'CRUD sale'),
    ('manage_memberships',   'CRUD tessere annuali (iscrizioni)'),
    ('manage_bookings',      'CRUD prenotazioni (override admin)'),
    ('manage_attendance',    'Marcatura presenze'),
    ('manage_certificates',  'Visualizza, approva e rifiuta certificati'),
    ('delete_certificates',  'Elimina definitivamente un certificato (solo admin)'),
    ('manage_announcements', 'Pubblicazione annunci interni'),
    ('view_dashboard',       'Accesso al backoffice'),
    ('view_reports',         'Lettura statistiche e report aggregati'),
    ('view_own_bookings',    'Vista delle proprie prenotazioni'),
    ('view_own_membership',  'Vista della propria tessera annuale'),
    ('book_course_session',  'Prenotazione di una sessione'),
    ('cancel_own_booking',   'Cancellazione di una propria prenotazione');


-- matrice services_has_groups: 

INSERT INTO services_has_groups (services_username, groups_id)
SELECT s.username, g.id FROM services s, `groups` g
WHERE g.name = 'Admin'
  AND s.username IN (
    'manage_users','manage_members','manage_instructors',
    'manage_groups','manage_services',
    'manage_courses','manage_rooms','manage_memberships',
    'manage_bookings','manage_attendance',
    'manage_certificates','delete_certificates','manage_announcements',
    'view_dashboard','view_reports'
  );

-- Staff: operativo, niente users/groups/services
INSERT INTO services_has_groups (services_username, groups_id)
SELECT s.username, g.id FROM services s, `groups` g
WHERE g.name = 'Staff'
  AND s.username IN (
    'manage_members','manage_instructors',
    'manage_courses','manage_rooms','manage_memberships',
    'manage_bookings','manage_attendance',
    'manage_certificates','manage_announcements',
    'view_dashboard','view_reports'
  );

-- Istruttore: presenze sulle proprie sessioni + dashboard + vista lezioni
INSERT INTO services_has_groups (services_username, groups_id)
SELECT s.username, g.id FROM services s, `groups` g
WHERE g.name = 'Istruttore'
  AND s.username IN (
    'manage_attendance','view_dashboard','view_own_bookings'
  );

-- Studente: solo self-service
INSERT INTO services_has_groups (services_username, groups_id)
SELECT s.username, g.id FROM services s, `groups` g
WHERE g.name = 'Studente'
  AND s.username IN (
    'view_own_bookings','view_own_membership',
    'book_course_session','cancel_own_booking'
  );


INSERT INTO users (email, password_hash, first_name, last_name, is_active) VALUES
    ('admin@canada.univaq.it',
     '$2y$12$9VKcufAc1SiAAi./MfujQuHopusTKcIG8RxvUfKnb3Wwg0o4kMyCG',
     'Admin', 'Canada', 1);

INSERT INTO users_has_groups (users_id, groups_id)
SELECT u.id, g.id FROM users u, `groups` g
WHERE u.email = 'admin@canada.univaq.it' AND g.name = 'Admin';


-- tipo di tessera annuale di default 

INSERT INTO membership_types (name, description, duration_days, is_active) VALUES
    ('Tessera A.A. 2025/26',
     'Iscrizione annuale alla palestra universitaria Canada. Gratuita per gli affiliati Univaq.',
     365, 1);
