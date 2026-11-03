# 世界杯核心数据清洗样本

范围：阿尔及利亚 26 名球员；第 19、44 场比赛。对手只保留球队引用，不包含对手的球员统计。第 47 场仅作为错误检测对照，不进入清洗结果。

只保留球队、球员姓名、号码、位置、比赛、阵容、进球、射门、传球和事件等核心内容。生日、身高、体重、照片均未纳入样本。

## 文件

- `raw/`：从上一部分审计响应中提取的最小源数据，尚未清洗。
- `manifest.json`：来源链接、字段选取说明和 SHA-256。`retrieved_at` 为空，因为先前审计没有精确记录 HTTP 采集时刻；`audit_cache_saved_at` 仅为本地缓存写入时间，不能当作官方更新时间。
- `sample.json`：清洗后的分层 JSON；使用带来源前缀的暂存 ID，不直接充当数据库 BIGINT 主键。
- `validation-report.json`：记录数量、待核实项与排除的错误对照。

## 离线重建

需要 Python 3.10+，仅用标准库，无需数据库、网络或第三方爬虫包。在仓库根目录执行：

```powershell
python scripts/worldcup_data.py
python -m unittest discover -s tests -p test_worldcup_data.py
```

如果本机还保存着上一步的审计缓存，可以重新封装原始样本：

```powershell
python scripts/worldcup_data.py --capture-from tmp/data-audit
```

克隆仓库后使用已提交的 `raw/` 和 manifest 即可离线重建，不依赖 `tmp/`。这不是联网采集器或数据库导入器。

## 本次结果

- 26 名球员、2 场比赛、52 条比赛名单、32 条上场球员统计、2 条球队汇总、23 条事件。
- 26 个详细 CSV 球员编号完成映射；openfootball 有 25 条通过核对，24 号球员的姓名拼写差异进入待核实队列，不按号码强行合并。
- 进球为零保留 0；源文件未提供的单场助攻和出场分钟保留为空。
- 第 47 场的 UZB/GHA 代码冲突保留在错误对照中，不自动更正或进入结果。

只有两场已核对小组赛的比分进入本样本；FIFA 状态代码保留原值，尚未映射成网站状态。样本不是全量赛事，也不代表所有来源字段已通过人工审核。数据库和网页内容均未改变。

## 数据来源与署名

- FIFA 第一方球队/比赛/本届阵容响应：具体 URL 见 manifest，仅保留核心字段，不包含媒体文件。
- [openfootball/worldcup.json](https://github.com/openfootball/worldcup.json)：CC0-1.0。名单快照的 Git blob 版本见 manifest。
- [World Cup 2026 Analytics Platform by Hesham El Alamy](https://github.com/Alamyy/Worldcup26)，基于公开的 FIFA Training Centre 赛后报告；CSV 数据及其本样本中的选取、清洗结果按 [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) 标注。原项目[许可证](https://github.com/Alamyy/Worldcup26/blob/main/LICENSE)不涵盖第三方图片、视频或商标。本样本不包含此类素材。

变更说明：选取一个国家队和两场比赛；筛选核心字段；建立来源 ID 映射；统一 UTC/北京时间、数字和百分比；保留未知值；隔离身份冲突及错误对照。未手工替换源球员姓名或改写源比赛国家代码。
