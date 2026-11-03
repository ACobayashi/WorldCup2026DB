-- FOURWARD coursework | Author: 韩羽宸 | Additive World Cup import schema.
CREATE TABLE IF NOT EXISTS wc_import_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dataset_name VARCHAR(100) NOT NULL,
  content_sha256 CHAR(64) NOT NULL UNIQUE,
  source_manifest LONGTEXT NOT NULL,
  counts_json LONGTEXT NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (JSON_VALID(source_manifest)), CHECK (JSON_VALID(counts_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='已成功提交的导入批次，失败整体回滚';

CREATE TABLE IF NOT EXISTS wc_source_mappings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(40) NOT NULL,
  entity_type ENUM('team','player','match','venue') NOT NULL,
  source_id VARCHAR(160) NOT NULL,
  team_id BIGINT UNSIGNED NULL,
  player_id BIGINT UNSIGNED NULL,
  match_id BIGINT UNSIGNED NULL,
  venue_id BIGINT UNSIGNED NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_wc_source_entity (provider, entity_type, source_id),
  FOREIGN KEY (team_id) REFERENCES wc_teams(id),
  FOREIGN KEY (player_id) REFERENCES wc_players(id),
  FOREIGN KEY (match_id) REFERENCES wc_matches(id),
  FOREIGN KEY (venue_id) REFERENCES wc_venues(id),
  FOREIGN KEY (batch_id) REFERENCES wc_import_batches(id),
  CHECK ((entity_type='team' AND team_id IS NOT NULL AND player_id IS NULL AND match_id IS NULL AND venue_id IS NULL)
    OR (entity_type='player' AND player_id IS NOT NULL AND team_id IS NULL AND match_id IS NULL AND venue_id IS NULL)
    OR (entity_type='match' AND match_id IS NOT NULL AND team_id IS NULL AND player_id IS NULL AND venue_id IS NULL)
    OR (entity_type='venue' AND venue_id IS NOT NULL AND team_id IS NULL AND player_id IS NULL AND match_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='经核验的外部编号到内部主键映射';

CREATE TABLE IF NOT EXISTS wc_match_appearances (
  match_id BIGINT UNSIGNED NOT NULL,
  player_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  shirt_number TINYINT UNSIGNED NOT NULL,
  position VARCHAR(40) NULL,
  is_starter TINYINT(1) NOT NULL,
  appeared TINYINT(1) NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (match_id, player_id),
  UNIQUE KEY uq_wc_match_team_shirt (match_id, team_id, shirt_number),
  FOREIGN KEY (match_id) REFERENCES wc_matches(id),
  FOREIGN KEY (player_id) REFERENCES wc_players(id),
  FOREIGN KEY (team_id) REFERENCES wc_teams(id),
  FOREIGN KEY (batch_id) REFERENCES wc_import_batches(id),
  CHECK (shirt_number BETWEEN 1 AND 99),
  CHECK (is_starter IN (0,1) AND appeared IN (0,1) AND is_starter <= appeared)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='逐场首发及替补名单，未上场不等于资料缺失';

CREATE TABLE IF NOT EXISTS wc_player_match_stats (
  match_id BIGINT UNSIGNED NOT NULL,
  player_id BIGINT UNSIGNED NOT NULL,
  goals SMALLINT UNSIGNED NULL,
  assists SMALLINT UNSIGNED NULL,
  minutes_played SMALLINT UNSIGNED NULL,
  shots SMALLINT UNSIGNED NULL,
  passes_attempted SMALLINT UNSIGNED NULL,
  passes_completed SMALLINT UNSIGNED NULL,
  pass_completion_pct DECIMAL(5,2) NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (match_id, player_id),
  FOREIGN KEY (match_id, player_id) REFERENCES wc_match_appearances(match_id, player_id),
  FOREIGN KEY (batch_id) REFERENCES wc_import_batches(id),
  CHECK (pass_completion_pct IS NULL OR pass_completion_pct BETWEEN 0 AND 100),
  CHECK (passes_completed IS NULL OR passes_attempted IS NULL OR passes_completed <= passes_attempted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='球员单场核心统计，未知保留NULL';

CREATE TABLE IF NOT EXISTS wc_team_match_stats (
  match_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  goals SMALLINT UNSIGNED NULL,
  possession_pct DECIMAL(5,2) NULL,
  shots SMALLINT UNSIGNED NULL,
  shots_on_target SMALLINT UNSIGNED NULL,
  passes_attempted SMALLINT UNSIGNED NULL,
  passes_completed SMALLINT UNSIGNED NULL,
  pass_completion_pct DECIMAL(5,2) NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (match_id, team_id),
  FOREIGN KEY (match_id) REFERENCES wc_matches(id),
  FOREIGN KEY (team_id) REFERENCES wc_teams(id),
  FOREIGN KEY (batch_id) REFERENCES wc_import_batches(id),
  CHECK (possession_pct IS NULL OR possession_pct BETWEEN 0 AND 100),
  CHECK (pass_completion_pct IS NULL OR pass_completion_pct BETWEEN 0 AND 100),
  CHECK (shots_on_target IS NULL OR shots IS NULL OR shots_on_target <= shots),
  CHECK (passes_completed IS NULL OR passes_attempted IS NULL OR passes_completed <= passes_attempted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='球队单场核心技术统计';

CREATE TABLE IF NOT EXISTS wc_match_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  player_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('goal','own_goal','yellow_card','red_card','subbed_on','subbed_off') NOT NULL,
  minute SMALLINT UNSIGNED NOT NULL,
  added_minute SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  minute_label VARCHAR(32) NOT NULL,
  source_provider VARCHAR(40) NOT NULL,
  source_id VARCHAR(160) NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_wc_event_source (source_provider, source_id),
  KEY idx_wc_event_time (match_id, minute, added_minute, id),
  FOREIGN KEY (match_id, player_id) REFERENCES wc_match_appearances(match_id, player_id),
  FOREIGN KEY (batch_id) REFERENCES wc_import_batches(id),
  CHECK (minute <= 150 AND added_minute <= 99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='球员比赛事件，补时与原分钟文本分别保存';
