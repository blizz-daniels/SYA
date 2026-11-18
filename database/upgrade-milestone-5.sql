-- Milestone 5 additive upgrade from Milestone 4. Back up first.
-- Preserve existing configuration, accounts, applications, auditions and setup lock.
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
