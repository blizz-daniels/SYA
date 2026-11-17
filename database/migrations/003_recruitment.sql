CREATE TABLE IF NOT EXISTS recruitment_roles (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 kind ENUM('casting','crew') NOT NULL,
 title VARCHAR(150) NOT NULL,
 description TEXT NOT NULL,
 mandatory_requirements TEXT NOT NULL,
 preferences TEXT NOT NULL,
 location VARCHAR(200) NOT NULL,
 availability VARCHAR(300) NOT NULL,
 age_min TINYINT UNSIGNED NOT NULL DEFAULT 18,
 age_max TINYINT UNSIGNED NULL,
 deadline DATETIME NOT NULL,
 state ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
 published_version INT UNSIGNED NOT NULL DEFAULT 0,
 draft_schema LONGTEXT NOT NULL,
 closed_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_roles_public (kind, state, deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS recruitment_form_versions (
 role_id BIGINT UNSIGNED NOT NULL,
 version INT UNSIGNED NOT NULL,
 schema_json LONGTEXT NOT NULL,
 role_snapshot LONGTEXT NOT NULL,
 published_by BIGINT UNSIGNED NULL,
 published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (role_id, version),
 FOREIGN KEY (role_id) REFERENCES recruitment_roles(id),
 FOREIGN KEY (published_by) REFERENCES administrators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS recruitment_form_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 kind ENUM('casting','crew') NOT NULL,
 name VARCHAR(120) NOT NULL,
 schema_json LONGTEXT NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_template_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS recruitment_applications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 reference CHAR(36) CHARACTER SET ascii NOT NULL UNIQUE,
 role_id BIGINT UNSIGNED NOT NULL,
 form_version INT UNSIGNED NOT NULL,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(254) NOT NULL,
 email_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 age TINYINT UNSIGNED NOT NULL,
 location VARCHAR(200) NOT NULL,
 availability VARCHAR(1000) NOT NULL,
 answers_json LONGTEXT NOT NULL,
 public_status ENUM('Received','Under Review','Shortlisted','Audition Requested','Closed') NOT NULL DEFAULT 'Received',
 outcome ENUM('pending','selected','not_selected') NOT NULL DEFAULT 'pending',
 duplicate_flag TINYINT(1) NOT NULL DEFAULT 0,
 notice_version VARCHAR(60) NOT NULL,
 privacy_notice TEXT NOT NULL,
 acknowledged_at DATETIME NOT NULL,
 retention_due_at DATETIME NULL,
 received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (role_id, form_version) REFERENCES recruitment_form_versions(role_id, version),
 INDEX idx_app_role_status (role_id, public_status, id),
 INDEX idx_app_duplicate (role_id, email_hash),
 INDEX idx_app_retention (retention_due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS recruitment_attachments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 question_id VARCHAR(30) NOT NULL,
 storage_key CHAR(68) CHARACTER SET ascii NOT NULL UNIQUE,
 mime VARCHAR(40) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 FOREIGN KEY (application_id) REFERENCES recruitment_applications(id),
 INDEX idx_attachment_application (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS recruitment_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 public_status VARCHAR(30) NOT NULL,
 outcome VARCHAR(20) NOT NULL,
 private_note TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (application_id) REFERENCES recruitment_applications(id),
 FOREIGN KEY (actor_id) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_recruit_event (application_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS recruitment_tracking_challenges (
 token CHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 code_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 expires_at BIGINT UNSIGNED NOT NULL,
 used_at BIGINT UNSIGNED NULL,
 FOREIGN KEY (application_id) REFERENCES recruitment_applications(id),
 INDEX idx_challenge_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS recruitment_delivery_log (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NULL,
 purpose ENUM('receipt','tracking','test') NOT NULL,
 recipient_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 delivery_status ENUM('accepted','failed') NOT NULL,
 result_code VARCHAR(50) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (application_id) REFERENCES recruitment_applications(id),
 INDEX idx_recruit_delivery (application_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
