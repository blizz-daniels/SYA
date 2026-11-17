CREATE TABLE IF NOT EXISTS audition_sessions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(150) NOT NULL,
 mode ENUM('physical','virtual') NOT NULL,
 location VARCHAR(500) NOT NULL DEFAULT '',
 meeting_url VARCHAR(1000) NOT NULL DEFAULT '',
 public_instructions TEXT NOT NULL,
 private_note TEXT NOT NULL,
 resource_key VARCHAR(60) CHARACTER SET ascii NOT NULL,
 display_zone VARCHAR(40) NOT NULL DEFAULT 'Africa/Lagos',
 starts_at DATETIME NOT NULL,
 ends_at DATETIME NOT NULL,
 state ENUM('active','cancelled') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_session_resource (resource_key,state,starts_at,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audition_slots (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 session_id BIGINT UNSIGNED NOT NULL,
 starts_at DATETIME NOT NULL,
 ends_at DATETIME NOT NULL,
 FOREIGN KEY(session_id) REFERENCES audition_sessions(id),
 UNIQUE KEY uq_session_slot (session_id,starts_at,ends_at),
 INDEX idx_slot_times (starts_at,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audition_callbacks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 state ENUM('requested','arranged','completed','closed') NOT NULL DEFAULT 'requested',
 private_note TEXT NOT NULL,
 requested_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(application_id) REFERENCES recruitment_applications(id),
 FOREIGN KEY(requested_by) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_callback_app (application_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audition_appointments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 slot_id BIGINT UNSIGNED NOT NULL,
 active_slot_id BIGINT UNSIGNED NULL,
 callback_id BIGINT UNSIGNED NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 round ENUM('initial','callback') NOT NULL DEFAULT 'initial',
 state ENUM('invited','confirmed','declined','cancelled') NOT NULL DEFAULT 'invited',
 attendance ENUM('pending','present','absent') NOT NULL DEFAULT 'pending',
 public_snapshot LONGTEXT NOT NULL,
 private_note TEXT NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii NULL,
 token_expires BIGINT UNSIGNED NULL,
 token_used_at BIGINT UNSIGNED NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(application_id) REFERENCES recruitment_applications(id),
 FOREIGN KEY(slot_id) REFERENCES audition_slots(id),
 FOREIGN KEY(active_slot_id) REFERENCES audition_slots(id),
 FOREIGN KEY(callback_id) REFERENCES audition_callbacks(id),
 UNIQUE KEY uq_active_slot (active_slot_id),
 UNIQUE KEY uq_invite_hash (token_hash),
 INDEX idx_appointment_app (application_id,state,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audition_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NULL,
 appointment_id BIGINT UNSIGNED NULL,
 callback_id BIGINT UNSIGNED NULL,
 actor_id BIGINT UNSIGNED NULL,
 actor_name VARCHAR(60) NOT NULL,
 action VARCHAR(60) NOT NULL,
 private_note TEXT NOT NULL,
 snapshot_json LONGTEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(application_id) REFERENCES recruitment_applications(id),
 FOREIGN KEY(appointment_id) REFERENCES audition_appointments(id),
 FOREIGN KEY(callback_id) REFERENCES audition_callbacks(id),
 FOREIGN KEY(actor_id) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_audition_history (application_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audition_notifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 appointment_id BIGINT UNSIGNED NOT NULL,
 appointment_version INT UNSIGNED NOT NULL,
 kind ENUM('invite','change','cancel') NOT NULL,
 state ENUM('pending','sending','accepted','failed','superseded') NOT NULL DEFAULT 'pending',
 lease_until BIGINT UNSIGNED NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 delivery_log_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(appointment_id) REFERENCES audition_appointments(id),
 FOREIGN KEY(delivery_log_id) REFERENCES recruitment_delivery_log(id) ON DELETE SET NULL,
 UNIQUE KEY uq_notice_version (appointment_id,appointment_version,kind),
 INDEX idx_notice_queue (state,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS evaluation_rubrics (
 role_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 draft_schema LONGTEXT NOT NULL,
 published_version INT UNSIGNED NOT NULL DEFAULT 0,
 FOREIGN KEY(role_id) REFERENCES recruitment_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS evaluation_rubric_versions (
 role_id BIGINT UNSIGNED NOT NULL,
 version INT UNSIGNED NOT NULL,
 schema_json LONGTEXT NOT NULL,
 published_by BIGINT UNSIGNED NULL,
 published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(role_id,version),
 FOREIGN KEY(role_id) REFERENCES recruitment_roles(id),
 FOREIGN KEY(published_by) REFERENCES administrators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS evaluations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 role_id BIGINT UNSIGNED NOT NULL,
 rubric_version INT UNSIGNED NOT NULL,
 reviewer_id BIGINT UNSIGNED NULL,
 reviewer_name VARCHAR(48) NOT NULL,
 revision INT UNSIGNED NOT NULL,
 supersedes_id BIGINT UNSIGNED NULL,
 scores_json LONGTEXT NOT NULL,
 weighted_score DECIMAL(5,2) NOT NULL,
 recommendation ENUM('advance','callback','hold','do_not_advance') NOT NULL,
 private_feedback TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(application_id) REFERENCES recruitment_applications(id),
 FOREIGN KEY(role_id,rubric_version) REFERENCES evaluation_rubric_versions(role_id,version),
 FOREIGN KEY(reviewer_id) REFERENCES administrators(id) ON DELETE SET NULL,
 FOREIGN KEY(supersedes_id) REFERENCES evaluations(id),
 UNIQUE KEY uq_review_revision (application_id,reviewer_id,revision),
 INDEX idx_evaluation_app (application_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE recruitment_delivery_log MODIFY purpose ENUM('receipt','tracking','test','audition') NOT NULL;
