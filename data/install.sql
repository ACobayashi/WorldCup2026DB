-- FOURWARD coursework — guestbook module, author 严苑毓.
-- Import into the chosen logical database. No DROP/TRUNCATE or fixed passwords.
-- These four tables are this slice only; the complete project still needs >= 11.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(48) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  member_slug VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_mottos (
  member_slug VARCHAR(24) NOT NULL PRIMARY KEY,
  motto VARCHAR(120) NOT NULL,
  is_placeholder TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_motto_editor FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_guestbook_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  recipient_slug VARCHAR(24) NOT NULL,
  author VARCHAR(24) NOT NULL,
  message VARCHAR(280) NOT NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  reviewer_id BIGINT UNSIGNED NULL,
  INDEX idx_guestbook_public (status, recipient_slug, id),
  CONSTRAINT fk_guestbook_recipient FOREIGN KEY (recipient_slug) REFERENCES team_mottos(member_slug),
  CONSTRAINT fk_guestbook_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_rate_limits (
  bucket CHAR(64) NOT NULL PRIMARY KEY,
  hits INT UNSIGNED NOT NULL DEFAULT 1,
  expires_at BIGINT UNSIGNED NOT NULL,
  INDEX idx_rate_limit_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO team_mottos (member_slug, motto, is_placeholder) VALUES
  ('team', '各有热爱，一起上场。', 1),
  ('lin-ziyuan', '一个有趣且靠谱的人。', 0),
  ('yan-yuanyu', '天行健，君子以自强不息', 0),
  ('ma-yuxiang', '丢掉幻想 准备斗争', 0),
  ('han-yuchen', '命数如织，当为磐石', 0);
