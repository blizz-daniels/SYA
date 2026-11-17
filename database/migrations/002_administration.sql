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
