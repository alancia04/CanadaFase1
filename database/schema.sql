CREATE DATABASE IF NOT EXISTS canadafase1
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE canadafase1;

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';


--  users (anagrafica completa di tutti gli utenti del sistema)

DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    first_name      VARCHAR(80)  NOT NULL,
    last_name       VARCHAR(80)  NOT NULL,
    phone           VARCHAR(30)  NULL,
    birth_date      DATE         NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_email (email),
    KEY idx_users_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  gruppi

DROP TABLE IF EXISTS `groups`;
CREATE TABLE `groups` (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(50)  NOT NULL,
    description  VARCHAR(255) NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_groups_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--  services 

DROP TABLE IF EXISTS services;
CREATE TABLE services (
    username    VARCHAR(50)  NOT NULL,
    description VARCHAR(255) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--  user-gruppi

DROP TABLE IF EXISTS users_has_groups;
CREATE TABLE users_has_groups (
    users_id    INT UNSIGNED NOT NULL,
    groups_id   INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (users_id, groups_id),
    KEY idx_uhg_group (groups_id),
    CONSTRAINT fk_uhg_user  FOREIGN KEY (users_id)  REFERENCES users(id)   ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_uhg_group FOREIGN KEY (groups_id) REFERENCES `groups`(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--  servizi-gruppi

DROP TABLE IF EXISTS services_has_groups;
CREATE TABLE services_has_groups (
    services_username VARCHAR(50)  NOT NULL,
    groups_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (services_username, groups_id),
    KEY idx_shg_group (groups_id),
    CONSTRAINT fk_shg_service FOREIGN KEY (services_username) REFERENCES services(username) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_shg_group   FOREIGN KEY (groups_id)         REFERENCES `groups`(id)        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- members 

DROP TABLE IF EXISTS members;
CREATE TABLE members (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    member_code     VARCHAR(20)  NOT NULL,
    member_type     ENUM('studente','docente','personale') NOT NULL DEFAULT 'studente',
    matricola       VARCHAR(20)  NULL,
    enrollment_date DATE         NOT NULL DEFAULT (CURRENT_DATE),
    notes           TEXT         NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_members_user (user_id),
    UNIQUE KEY uniq_members_code (member_code),
    UNIQUE KEY uniq_members_matricola (matricola),
    KEY idx_members_type (member_type),
    CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- istruttori 

DROP TABLE IF EXISTS instructors;
CREATE TABLE instructors (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    bio         TEXT         NULL,
    specialties VARCHAR(255) NULL,
    hire_date   DATE         NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_instructors_user (user_id),
    CONSTRAINT fk_instructors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- categoria corsi

DROP TABLE IF EXISTS course_categories;
CREATE TABLE course_categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80)  NOT NULL,
    slug        VARCHAR(80)  NOT NULL,
    description VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_cat_name (name),
    UNIQUE KEY uniq_cat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- corsi

DROP TABLE IF EXISTS courses;
CREATE TABLE courses (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id      INT UNSIGNED NOT NULL,
    instructor_id    INT UNSIGNED NULL,
    title            VARCHAR(120) NOT NULL,
    slug             VARCHAR(140) NOT NULL,
    description      TEXT         NULL,
    level            ENUM('base','intermedio','avanzato') NOT NULL DEFAULT 'base',
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    is_published     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_courses_slug (slug),
    KEY idx_courses_cat (category_id),
    KEY idx_courses_instr (instructor_id),
    KEY idx_courses_pub (is_published),
    CONSTRAINT fk_courses_cat   FOREIGN KEY (category_id)   REFERENCES course_categories(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_courses_instr FOREIGN KEY (instructor_id) REFERENCES instructors(id)       ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--  rooms 

DROP TABLE IF EXISTS rooms;
CREATE TABLE rooms (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name     VARCHAR(80)  NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    location VARCHAR(120) NULL,
    notes    VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_rooms_name (name),
    CONSTRAINT chk_rooms_cap CHECK (capacity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--  course session 

DROP TABLE IF EXISTS course_sessions;
CREATE TABLE course_sessions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id  INT UNSIGNED NOT NULL,
    room_id    INT UNSIGNED NOT NULL,
    starts_at  DATETIME     NOT NULL,
    ends_at    DATETIME     NOT NULL,
    capacity   SMALLINT UNSIGNED NOT NULL,
    status     ENUM('scheduled','cancelled','completed') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sessions_start (starts_at),
    KEY idx_sessions_course_start (course_id, starts_at),
    KEY idx_sessions_room_start (room_id, starts_at),
    CONSTRAINT fk_sessions_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_sessions_room   FOREIGN KEY (room_id)   REFERENCES rooms(id)   ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_sessions_cap   CHECK (capacity > 0),
    CONSTRAINT chk_sessions_time  CHECK (ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--  bookings (prenotazione utente per sessione)

DROP TABLE IF EXISTS bookings;
CREATE TABLE bookings (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    status       ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    booked_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP    NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_session_user (session_id, user_id),
    KEY idx_bookings_user (user_id),
    KEY idx_bookings_session_status (session_id, status),
    CONSTRAINT fk_bookings_session FOREIGN KEY (session_id) REFERENCES course_sessions(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_user    FOREIGN KEY (user_id)    REFERENCES users(id)           ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- attendance 

DROP TABLE IF EXISTS attendance;
CREATE TABLE attendance (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT UNSIGNED NOT NULL,
    present    TINYINT(1)   NOT NULL DEFAULT 0,
    marked_by  INT UNSIGNED NULL,
    marked_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes      VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_attendance_booking (booking_id),
    CONSTRAINT fk_att_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_att_marker  FOREIGN KEY (marked_by)  REFERENCES users(id)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- membership types 

DROP TABLE IF EXISTS membership_types;
CREATE TABLE membership_types (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(80)  NOT NULL,
    description   VARCHAR(255) NULL,
    duration_days SMALLINT UNSIGNED NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_mtypes_name (name),
    CONSTRAINT chk_mtypes_duration CHECK (duration_days > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- tessera

DROP TABLE IF EXISTS memberships;
CREATE TABLE memberships (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id  INT UNSIGNED NOT NULL,
    type_id    INT UNSIGNED NOT NULL,
    start_date DATE         NOT NULL,
    end_date   DATE         NOT NULL,
    status     ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_memberships_member_status (member_id, status),
    KEY idx_memberships_end (end_date),
    CONSTRAINT fk_memberships_member FOREIGN KEY (member_id) REFERENCES members(id)          ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_memberships_type   FOREIGN KEY (type_id)   REFERENCES membership_types(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_memberships_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- certificati medici

DROP TABLE IF EXISTS medical_certificates;
CREATE TABLE medical_certificates (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id     INT UNSIGNED NOT NULL,
    issued_at     DATE         NOT NULL,
    expires_at    DATE         NOT NULL,
    file_path     VARCHAR(255) NULL,
    notes         VARCHAR(255) NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    verified_by   INT UNSIGNED NULL,
    verified_at   DATETIME     NULL,
    reject_reason VARCHAR(255) NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_certs_member_exp (member_id, expires_at),
    KEY idx_certs_exp (expires_at),
    KEY idx_certs_status (status),
    CONSTRAINT fk_certs_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_certs_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_certs_dates CHECK (expires_at >= issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- annunci

DROP TABLE IF EXISTS announcements;
CREATE TABLE announcements (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_id    INT UNSIGNED NOT NULL,
    title        VARCHAR(160) NOT NULL,
    body         TEXT         NOT NULL,
    is_published TINYINT(1)   NOT NULL DEFAULT 1,
    published_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ann_pub (is_published, published_at),
    CONSTRAINT fk_ann_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- preferences

DROP TABLE IF EXISTS user_preferences;
CREATE TABLE user_preferences (
    user_id             INT UNSIGNED NOT NULL,
    locale              ENUM('it','en') NOT NULL DEFAULT 'it',
    theme               ENUM('light','dark','auto') NOT NULL DEFAULT 'auto',
    email_notifications TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                                            
-- recensioni del centro

DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NULL,
    author_name  VARCHAR(120) NOT NULL,
    rating       TINYINT UNSIGNED NOT NULL,
    body         TEXT         NOT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rev_created (created_at),
    CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_rev_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
