# 世界杯数据库导入数据

课程开发参考，由韩羽宸维护。仅导入核心足球字段，生日、身高、体重、照片不在采集范围内。正式课程材料仍位于 `data/team/` 与 `data/personal/`。

## 小样本入库

在仓库根目录执行（本机 PHP 可用 `D:\WebDev\php\php.exe`）：

```powershell
php scripts/install.php
python scripts/prepare-worldcup-pilot.py
php scripts/import-worldcup.php data/worldcup/pilot.json --dry-run
php scripts/import-worldcup.php data/worldcup/pilot.json
php scripts/check-worldcup-import.php
```

`install.php` 以 CREATE TABLE IF NOT EXISTS 增补六张表，既有八张世界杯表和四张团队/公共表不清空、不重建，共 18 张。等价增量 SQL 为 `data/migrations/002-worldcup-import.sql`，不能用初始化脚本代替未来的 ALTER 迁移。

导入器仅限 CLI，使用数据库命名锁和事务；相同内容哈希再次导入返回 `already_imported`。`--dry-run` 执行约束验证后回滚。成功批次、清单和计数保存在 `wc_import_batches`；球队、球员、比赛、场馆的外部编号保存在带外键的 `wc_source_mappings`。同一来源编号改指另一个实体会被拒绝，不按球衣号码静默合并身份。

业务关联包括：比赛阶段/小组、双方分组、球员所属球队、逐场名单、统计与事件的名单引用。任一错误使整批回滚。支持票、账号和留言不属于导入内容。

新增四张业务表为 `wc_match_appearances`、`wc_player_match_stats`、`wc_team_match_stats`、`wc_match_events`，保存首发/替补、球员单场统计、球队单场统计和事件。单场助攻、出场分钟等未知值仍为 NULL，真实零值为 0；补时分钟单列。

`pilot.json` 是已验收样本的导入适配结果：三支球队引用、阿尔及利亚 26 名球员、两场小组赛。原始快照、许可证与待核实姓名差异见 `../samples/worldcup2026/README.md`。比赛状态适配只针对本样本已核实的完整小组赛赛果，不将 FIFA 的数值状态直接当网站枚举。

本地 PHP 页面 `/worldcup/` 查询已入库的球队、球员和赛程；静态 Pages 演示独立。详细统计展示将在后续页面功能中接入。

## 全量基础数据

```powershell
python scripts/worldcup_full.py
python -m unittest discover -s tests -p 'test_worldcup*.py'
php scripts/import-worldcup.php data/worldcup/full/core.json --dry-run
php scripts/import-worldcup.php data/worldcup/full/core.json
```

`full/manifest.json` 与 `full/raw/` 支持离线复现：48 队、1,248 名球员、12 组、16 场馆、7 阶段、104 场。FIFA 本届名单的 48 次请求单独记录 UTC 采集时间；早期审计缓存的精确采集时间未知，保留 NULL 并另记缓存写入时间。球员保留 FIFA 名称与编号，中文球队名为维护的中文译名，不声称来自 FIFA 中文接口。城市时区映射写在清洗器中，不从无偏移当地时间猜测 UTC。

比赛通过开球 UTC 和双方球队连接详细快照；基础快照第 79、92 场存在一小时差异，按明确比赛编号和双方球队核对，以 FIFA/详细快照一致的 UTC 为准。第 79 场两快照半场比分冲突，半场比分不纳入本次表结构。所有差异保留在 `core.json` 的 report 中。

比分按 openfootball 明确的 `ft`（90 分钟）、`et`（加时结束总比分）、`p`（点球）拆开，并与 FIFA 总比分、点球和胜者交叉检查。FIFA 原始状态 0 不能直接翻译成网站状态：本次还验证 ResultType 与完整赛果记录一致后才标为 finished。不会把最终比分全塞入 90 分钟字段。本站支持票始终由本站用户产生，不从外部数据构造。

许可：openfootball 快照为 CC0-1.0；Hesham El Alamy 的 Worldcup26 CSV 及其清洗结果按 CC BY 4.0 署名（https://github.com/Alamyy/Worldcup26 ，https://creativecommons.org/licenses/by/4.0/）。FIFA 响应按来源链接保留溯源；不包含图片、视频或商标素材。各文件在 manifest 中记录选取说明、原始响应哈希与选取后文件哈希。
