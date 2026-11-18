-- Milestone 6 additive upgrade from Milestone 5. Back up first.
-- Preserve production config/key/storage, accounts, applications, auditions and content.
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
