-- FOURWARD coursework | Author: 韩羽宸 | Optional source-backed player match metrics, no new table.
-- Applied idempotently by scripts/import-player-metrics.php after checking INFORMATION_SCHEMA.
ALTER TABLE wc_player_match_stats ADD COLUMN advanced_stats LONGTEXT NULL
  COMMENT '逐场进攻防守扩展指标及来源清单哈希；缺失不补零'
  CHECK (advanced_stats IS NULL OR JSON_VALID(advanced_stats));
