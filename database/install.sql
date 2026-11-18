-- FRESH EMPTY DATABASE ONLY. Existing M6 installations need no SQL for Milestone 7.
-- Do not use this in place of an upgrade. No administrator credentials are included.

-- 001_foundation.sql
CREATE TABLE IF NOT EXISTS schema_migrations (
 version VARCHAR(100) NOT NULL PRIMARY KEY,
 checksum CHAR(64) NOT NULL,
 applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(version,checksum) VALUES ('001_foundation.sql','71ac5473fe013d7db1f94a6c3d245fa8391f270ed077ac63f00a9eb5ead725b0');

-- 002_administration.sql
CREATE TABLE IF NOT EXISTS administrators (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(48) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL UNIQUE,
 private_code_hash VARCHAR(255) NOT NULL,
 role ENUM('super_admin','casting_manager','crew_coordinator','content_editor') NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 auth_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_admin_role_active (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_state (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 setup_completed_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO system_state (id, setup_completed_at) VALUES (1, NULL);

CREATE TABLE IF NOT EXISTS auth_rate_limits (
 bucket_key CHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 expires_at BIGINT UNSIGNED NOT NULL,
 INDEX idx_rate_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 actor_id BIGINT UNSIGNED NULL,
 action VARCHAR(60) NOT NULL,
 target_id BIGINT UNSIGNED NULL,
 details VARCHAR(500) NOT NULL DEFAULT '',
 ip_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_audit_created (created_at, id),
 INDEX idx_audit_actor (actor_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_settings (
 setting_key VARCHAR(60) CHARACTER SET ascii NOT NULL PRIMARY KEY,
 setting_value VARCHAR(500) NOT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_settings_actor FOREIGN KEY (updated_by) REFERENCES administrators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(version,checksum) VALUES ('002_administration.sql','7ecd9805e4ccdbb8c392249c231dd1c27f831b832233089f579db7e81158a293');

-- 003_recruitment.sql
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

INSERT IGNORE INTO schema_migrations(version,checksum) VALUES ('003_recruitment.sql','f1aefef5dc559c25e5af962e105d687f84504f371399e0cc372adb8aeaf12984');

-- 004_auditions_evaluations.sql
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

INSERT IGNORE INTO schema_migrations(version,checksum) VALUES ('004_auditions_evaluations.sql','44cee4ce44076214abf7a7ae8d75c1d1c573f48f23a1de56081af78c3eacfe68');

-- 005_content_media.sql
CREATE TABLE IF NOT EXISTS cms_categories (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 slug VARCHAR(90) CHARACTER SET ascii NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media_collections (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media_assets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 storage_key CHAR(64) CHARACTER SET ascii NOT NULL UNIQUE,
 width INT UNSIGNED NOT NULL,
 height INT UNSIGNED NOT NULL,
 bytes INT UNSIGNED NOT NULL,
 uploaded_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(uploaded_by) REFERENCES administrators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cms_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 kind ENUM('article','diary','announcement','media_post','character','film_info') NOT NULL,
 slug VARCHAR(100) CHARACTER SET ascii NOT NULL UNIQUE,
 draft_revision_id BIGINT UNSIGNED NULL,
 published_revision_id BIGINT UNSIGNED NULL,
 scheduled_revision_id BIGINT UNSIGNED NULL,
 scheduled_at DATETIME NULL,
 archived TINYINT UNSIGNED NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_cms_publish (archived,scheduled_at), INDEX idx_cms_kind (kind,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cms_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 item_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL,
 excerpt VARCHAR(500) NOT NULL,
 body MEDIUMTEXT NOT NULL,
 category_id BIGINT UNSIGNED NULL,
 collection_id BIGINT UNSIGNED NULL,
 cover_asset_id BIGINT UNSIGNED NULL,
 alt_text VARCHAR(250) NOT NULL DEFAULT '',
 caption VARCHAR(500) NOT NULL DEFAULT '',
 credit VARCHAR(200) NOT NULL DEFAULT '',
 video_url VARCHAR(1000) NOT NULL DEFAULT '',
 seo_title VARCHAR(180) NOT NULL DEFAULT '',
 seo_description VARCHAR(300) NOT NULL DEFAULT '',
 character_json TEXT NOT NULL,
 state ENUM('draft','submitted','changes_requested','approved') NOT NULL DEFAULT 'draft',
 author_id BIGINT UNSIGNED NULL,
 author_name VARCHAR(48) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(item_id) REFERENCES cms_items(id),
 FOREIGN KEY(category_id) REFERENCES cms_categories(id),
 FOREIGN KEY(collection_id) REFERENCES media_collections(id),
 FOREIGN KEY(cover_asset_id) REFERENCES media_assets(id),
 FOREIGN KEY(author_id) REFERENCES administrators(id) ON DELETE SET NULL,
 UNIQUE KEY uq_cms_revision (item_id,revision), INDEX idx_cms_review(state,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cms_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 item_id BIGINT UNSIGNED NOT NULL,
 revision_id BIGINT UNSIGNED NULL,
 actor_id BIGINT UNSIGNED NULL,
 actor_name VARCHAR(48) NOT NULL,
 action VARCHAR(50) NOT NULL,
 note VARCHAR(2000) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(item_id) REFERENCES cms_items(id),
 FOREIGN KEY(revision_id) REFERENCES cms_revisions(id),
 FOREIGN KEY(actor_id) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_cms_events(item_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO cms_categories(name,slug) VALUES ('Announcements','announcements'),('Production diaries','production-diaries'),('Film information','film-information');
INSERT IGNORE INTO media_collections(name) VALUES ('Official posters'),('Cast portraits'),('Production stills'),('Behind the scenes'),('Teasers and trailers'),('Press material');
INSERT IGNORE INTO cms_items(kind,slug) VALUES ('character','isioma'),('character','ayo');

INSERT IGNORE INTO schema_migrations(version,checksum) VALUES ('005_content_media.sql','7c013cd421d4f774bafab6177b02a6298245f5a4ae7e3d9aad4327c64c637a68');

-- 006_communications_privacy_backups.sql
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(254) NOT NULL,
 email_hash CHAR(64) CHARACTER SET ascii NOT NULL UNIQUE,
 state ENUM('pending','active','unsubscribed','removed') NOT NULL DEFAULT 'pending',
 topics_json TEXT NOT NULL,
 notice_version VARCHAR(60) NOT NULL,
 notice_text TEXT NOT NULL,
 requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 confirmed_at DATETIME NULL,
 unsubscribed_at DATETIME NULL,
 INDEX idx_subscriber_state(state,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS newsletter_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 subscriber_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(40) NOT NULL,
 topics_json TEXT NOT NULL,
 notice_version VARCHAR(60) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(subscriber_id) REFERENCES newsletter_subscribers(id), INDEX idx_sub_events(subscriber_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS newsletter_campaigns (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 subject VARCHAR(180) NOT NULL,
 body TEXT NOT NULL,
 topic VARCHAR(30) NOT NULL,
 state ENUM('draft','preparing','ready','cancelled') NOT NULL DEFAULT 'draft',
 cursor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NULL,
 approved_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES administrators(id) ON DELETE SET NULL,
 FOREIGN KEY(approved_by) REFERENCES administrators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS newsletter_deliveries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 campaign_id BIGINT UNSIGNED NOT NULL,
 subscriber_id BIGINT UNSIGNED NOT NULL,
 state ENUM('pending','sending','accepted','failed','skipped') NOT NULL DEFAULT 'pending',
 lease_until BIGINT UNSIGNED NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 delivery_log_id BIGINT UNSIGNED NULL,
 FOREIGN KEY(campaign_id) REFERENCES newsletter_campaigns(id),
 FOREIGN KEY(subscriber_id) REFERENCES newsletter_subscribers(id),
 FOREIGN KEY(delivery_log_id) REFERENCES recruitment_delivery_log(id) ON DELETE SET NULL,
 UNIQUE KEY uq_campaign_recipient(campaign_id,subscriber_id), INDEX idx_campaign_queue(campaign_id,state,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS contact_inquiries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 reference CHAR(40) CHARACTER SET ascii NOT NULL UNIQUE,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(254) NOT NULL,
 email_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 category VARCHAR(30) NOT NULL,
 subject VARCHAR(180) NOT NULL,
 message TEXT NOT NULL,
 notice_version VARCHAR(60) NOT NULL,
 notice_text TEXT NOT NULL,
 state ENUM('New','In Progress','Awaiting Reply','Resolved','Closed') NOT NULL DEFAULT 'New',
 assigned_to BIGINT UNSIGNED NULL,
 follow_up_at DATETIME NULL,
 received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 closed_at DATETIME NULL,
 retention_due_at DATETIME NULL,
 FOREIGN KEY(assigned_to) REFERENCES administrators(id) ON DELETE SET NULL,
 INDEX idx_contact_staff(assigned_to,state,id), INDEX idx_contact_retention(retention_due_at),INDEX idx_contact_email(email_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS contact_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 inquiry_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 actor_name VARCHAR(48) NOT NULL,
 kind ENUM('note','reply','status','assignment') NOT NULL,
 body TEXT NOT NULL,
 delivery_state ENUM('none','pending','sending','accepted','failed') NOT NULL DEFAULT 'none',
 lease_until BIGINT UNSIGNED NULL,
 delivery_log_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(inquiry_id) REFERENCES contact_inquiries(id),
 FOREIGN KEY(actor_id) REFERENCES administrators(id) ON DELETE SET NULL,
 FOREIGN KEY(delivery_log_id) REFERENCES recruitment_delivery_log(id) ON DELETE SET NULL,
 INDEX idx_contact_events(inquiry_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS privacy_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 reference CHAR(40) CHARACTER SET ascii NOT NULL UNIQUE,
 email VARCHAR(254) NOT NULL,
 email_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 kind ENUM('access','delete') NOT NULL,
 state ENUM('Awaiting Verification','Verified','In Progress','Resolved','Declined') NOT NULL DEFAULT 'Awaiting Verification',
 verified_at DATETIME NULL,
 due_at DATETIME NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_rights_email(email_hash), INDEX idx_rights_due(state,due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS privacy_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 actor_name VARCHAR(48) NOT NULL,
 note TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(request_id) REFERENCES privacy_requests(id),
 FOREIGN KEY(actor_id) REFERENCES administrators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS communication_tokens (
 token_hash CHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
 kind ENUM('confirm','manage','rights') NOT NULL,
 subject_id BIGINT UNSIGNED NOT NULL,
 payload_json TEXT NOT NULL,
 expires_at BIGINT UNSIGNED NOT NULL,
 used_at BIGINT UNSIGNED NULL,
 INDEX idx_comm_expiry(expires_at), INDEX idx_comm_subject(kind,subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS privacy_controls (
 scope ENUM('application','contact','subscriber','request') NOT NULL,
 subject_id BIGINT UNSIGNED NOT NULL,
 legal_hold TINYINT UNSIGNED NOT NULL DEFAULT 0,
 hold_reason VARCHAR(1000) NOT NULL DEFAULT '',
 anonymized_at DATETIME NULL,
 PRIMARY KEY(scope,subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS privacy_file_deletions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 storage_key CHAR(68) CHARACTER SET ascii NOT NULL UNIQUE,
 state ENUM('pending','removed','failed') NOT NULL DEFAULT 'pending',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS backup_runs (
 id CHAR(32) CHARACTER SET ascii NOT NULL PRIMARY KEY,
 state ENUM('active','complete','cancelled','expired','deleted') NOT NULL DEFAULT 'active',
 manifest_json LONGTEXT NOT NULL,
 salt CHAR(32) CHARACTER SET ascii NOT NULL,
 key_check CHAR(64) CHARACTER SET ascii NOT NULL,
 expires_at BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL,
 retention_due_at DATETIME NOT NULL,
 offhost_note VARCHAR(500) NOT NULL DEFAULT '',
 restore_note VARCHAR(1000) NOT NULL DEFAULT '',
 FOREIGN KEY(created_by) REFERENCES administrators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS backup_parts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 run_id CHAR(32) CHARACTER SET ascii NOT NULL,
 part_number INT UNSIGNED NOT NULL,
 storage_key CHAR(64) CHARACTER SET ascii NOT NULL UNIQUE,
 plain_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 bytes INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(run_id) REFERENCES backup_runs(id), UNIQUE KEY uq_backup_part(run_id,part_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE recruitment_delivery_log MODIFY purpose ENUM('receipt','tracking','test','audition','newsletter','confirmation','contact','rights') NOT NULL;
CREATE TABLE IF NOT EXISTS maintenance_writes (
 request_id CHAR(32) CHARACTER SET ascii NOT NULL PRIMARY KEY,
 expires_at BIGINT UNSIGNED NOT NULL,
 INDEX idx_write_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(version,checksum) VALUES ('006_communications_privacy_backups.sql','81bcf2e7e695f53cb46fade59eea868798e01707f2dc5c3870f76e7019dafaf6');
