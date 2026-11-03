-- FOURWARD coursework — guestbook module, author 严苑毓.
-- Import into the chosen logical database. No DROP/TRUNCATE or fixed passwords.
-- Team/common tables plus the single-edition 2026 World Cup module.
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

-- World Cup foundation | Author: 韩羽宸 | No synthetic fixtures or support votes.
CREATE TABLE IF NOT EXISTS wc_teams (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '内部球队编号',
  code VARCHAR(8) NOT NULL COMMENT '球队唯一代码',
  name_zh VARCHAR(80) NOT NULL COMMENT '中文名称',
  name_en VARCHAR(120) NOT NULL COMMENT '英文名称',
  crest_path VARCHAR(255) NULL COMMENT '本地队徽资源路径',
  source_url VARCHAR(1000) NULL COMMENT '资料来源链接',
  source_retrieved_at DATETIME NULL COMMENT '资料采集时间 UTC',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wc_team_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='2026世界杯球队';

CREATE TABLE IF NOT EXISTS wc_players (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '内部球员编号',
  team_id BIGINT UNSIGNED NOT NULL COMMENT '本届赛事所属球队',
  name VARCHAR(120) NOT NULL COMMENT '球员姓名',
  shirt_number TINYINT UNSIGNED NULL COMMENT '本届球衣号码，未知为空',
  position VARCHAR(40) NULL COMMENT '场上位置',
  birth_date DATE NULL COMMENT '出生日期',
  source_url VARCHAR(1000) NULL COMMENT '名单来源',
  source_retrieved_at DATETIME NULL COMMENT '采集时间 UTC',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wc_player_shirt (team_id, shirt_number),
  CONSTRAINT fk_wc_player_team FOREIGN KEY (team_id) REFERENCES wc_teams(id),
  CONSTRAINT ck_wc_player_shirt CHECK (shirt_number IS NULL OR shirt_number BETWEEN 1 AND 99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='本届世界杯参赛阵容，不记录俱乐部生涯';

CREATE TABLE IF NOT EXISTS wc_venues (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL COMMENT '本届赛事使用的场馆名称',
  city VARCHAR(80) NOT NULL,
  country VARCHAR(80) NOT NULL,
  timezone VARCHAR(64) NOT NULL COMMENT 'IANA时区标识',
  source_url VARCHAR(1000) NULL,
  source_retrieved_at DATETIME NULL COMMENT 'UTC',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wc_venue_location (country, city, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='世界杯比赛场馆';

CREATE TABLE IF NOT EXISTS wc_stages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL COMMENT '稳定阶段代码',
  name VARCHAR(80) NOT NULL COMMENT '展示名称',
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_group_stage TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_wc_stage_code (code),
  CONSTRAINT ck_wc_stage_group CHECK (is_group_stage IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='比赛阶段字典';

CREATE TABLE IF NOT EXISTS wc_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(8) NOT NULL COMMENT '本届小组代码',
  name VARCHAR(40) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_wc_group_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='本届世界杯小组';

CREATE TABLE IF NOT EXISTS wc_group_teams (
  group_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (group_id, team_id),
  UNIQUE KEY uq_wc_team_group (team_id),
  CONSTRAINT fk_wc_group_entry FOREIGN KEY (group_id) REFERENCES wc_groups(id),
  CONSTRAINT fk_wc_group_team FOREIGN KEY (team_id) REFERENCES wc_teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='本届分组归属，每支球队仅属于一个小组';

CREATE TABLE IF NOT EXISTS wc_matches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  match_no SMALLINT UNSIGNED NOT NULL COMMENT '本届赛事官方比赛编号',
  stage_id BIGINT UNSIGNED NOT NULL,
  group_id BIGINT UNSIGNED NULL COMMENT '淘汰赛为空',
  home_team_id BIGINT UNSIGNED NULL COMMENT '对阵未确认时为空',
  away_team_id BIGINT UNSIGNED NULL COMMENT '对阵未确认时为空',
  home_slot VARCHAR(80) NULL COMMENT '待定名额说明，例如某场胜者',
  away_slot VARCHAR(80) NULL,
  venue_id BIGINT UNSIGNED NULL,
  kickoff_at DATETIME NOT NULL COMMENT '开球时间 UTC',
  status ENUM('scheduled','live','finished','postponed','cancelled') NOT NULL DEFAULT 'scheduled',
  home_score_90 SMALLINT UNSIGNED NULL COMMENT '常规时间比分，未知为空',
  away_score_90 SMALLINT UNSIGNED NULL,
  home_score_et SMALLINT UNSIGNED NULL COMMENT '加时结束总比分，非加时增量；无加时为空',
  away_score_et SMALLINT UNSIGNED NULL,
  home_penalty_score SMALLINT UNSIGNED NULL COMMENT '点球大战比分，无点球为空',
  away_penalty_score SMALLINT UNSIGNED NULL,
  winner_team_id BIGINT UNSIGNED NULL COMMENT '平局或结果未知时为空',
  support_closed_at DATETIME NULL COMMENT '支持关闭时间 UTC，关闭后不因改期自动重开',
  source_provider VARCHAR(40) NULL,
  source_id VARCHAR(100) NULL,
  source_url VARCHAR(1000) NULL,
  source_retrieved_at DATETIME NULL COMMENT 'UTC',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wc_match_no (match_no),
  UNIQUE KEY uq_wc_match_source (source_provider, source_id),
  KEY idx_wc_match_status_time (status, kickoff_at, id),
  KEY idx_wc_match_time (kickoff_at, id),
  KEY idx_wc_match_home_time (home_team_id, kickoff_at),
  KEY idx_wc_match_away_time (away_team_id, kickoff_at),
  CONSTRAINT fk_wc_match_stage FOREIGN KEY (stage_id) REFERENCES wc_stages(id),
  CONSTRAINT fk_wc_match_group FOREIGN KEY (group_id) REFERENCES wc_groups(id),
  CONSTRAINT fk_wc_match_home FOREIGN KEY (home_team_id) REFERENCES wc_teams(id),
  CONSTRAINT fk_wc_match_away FOREIGN KEY (away_team_id) REFERENCES wc_teams(id),
  CONSTRAINT fk_wc_match_venue FOREIGN KEY (venue_id) REFERENCES wc_venues(id),
  CONSTRAINT fk_wc_match_winner FOREIGN KEY (winner_team_id) REFERENCES wc_teams(id),
  CONSTRAINT ck_wc_match_number CHECK (match_no > 0),
  CONSTRAINT ck_wc_match_opponents CHECK (home_team_id IS NULL OR away_team_id IS NULL OR home_team_id <> away_team_id),
  CONSTRAINT ck_wc_match_winner CHECK (winner_team_id IS NULL OR (home_team_id IS NOT NULL AND winner_team_id = home_team_id) OR (away_team_id IS NOT NULL AND winner_team_id = away_team_id)),
  CONSTRAINT ck_wc_score_90_pair CHECK ((home_score_90 IS NULL) = (away_score_90 IS NULL)),
  CONSTRAINT ck_wc_score_et_pair CHECK ((home_score_et IS NULL) = (away_score_et IS NULL)),
  CONSTRAINT ck_wc_penalty_pair CHECK ((home_penalty_score IS NULL) = (away_penalty_score IS NULL)),
  CONSTRAINT ck_wc_score_et CHECK (home_score_et IS NULL OR (home_score_90 IS NOT NULL AND home_score_et >= home_score_90 AND away_score_et >= away_score_90)),
  CONSTRAINT ck_wc_match_source CHECK ((source_provider IS NULL) = (source_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='2026世界杯赛程与赛果';

CREATE TABLE IF NOT EXISTS wc_supports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL COMMENT '复用全站账号',
  match_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL COMMENT '必须为比赛双方之一，由写入服务校验',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wc_user_match (user_id, match_id),
  KEY idx_wc_support_match_team (match_id, team_id),
  CONSTRAINT fk_wc_support_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_wc_support_match FOREIGN KEY (match_id) REFERENCES wc_matches(id),
  CONSTRAINT fk_wc_support_team FOREIGN KEY (team_id) REFERENCES wc_teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='本站真实支持记录，不预置票数或百分比';
