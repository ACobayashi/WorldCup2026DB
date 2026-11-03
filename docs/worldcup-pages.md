# 世界杯网页功能

本文件记录实际实现，正式课程材料仍放 `data/team/`。开发分支为 `feature/worldcup-data-import`，使用现有 18 张表，不新增表。页面继续沿用 FOURWARD 深色风格，绿色表示积极结果/晋级，蓝紫色维持公共导航。

## 比赛详情

入口 `/worldcup/?view=matches&id=内部比赛ID`，从赛程卡片进入。顶部显示国旗、双方、比分、阶段、北京时间和场馆；常规、加时和点球分别展示。下方是双列首发/替补名单，以及技术统计与事件列表。缺失数据显示 —，不推算出场分钟或助攻。允许替补席球员领牌，乌龙球有独立标记。控球率保留来源值，不把争夺中比例强行分给任一球队。

模型 `MatchDetail` 读取名单、统计与事件；`MatchController` 在验证比赛存在后组装详情；视图负责转义及排版。`action=match` 增加 lineups/statistics/events 字段，原有比分和支持字段兼容。

国旗为本地 SVG，来自 lipis/flag-icons（MIT），署名见 `public/assets/images/flags/CREDITS.md` 和 LICENSE。按固定 FIFA 代码白名单选取，不直接使用数据库图片 URL。

验证命令：`php scripts/check-worldcup-pages.php`、`php scripts/check-worldcup.php`、`node scripts/check-prototype.mjs`。新增页面验收使用本机已导入的完整快照，不写入数据库。

当前浏览器自动视觉检查被工具中止：该 Windows 浏览器不支持 URL 策略检查。已停止该检查路径；接口与服务器渲染输出均进行验收，不将其表述为已完成浏览器视觉验收。

## 国家队页面

入口 `/worldcup/?view=teams`，支持中英文及代码搜索。球队卡片、详情页均使用本地国旗。详情展示本届分组、完整 26 人名单和该队比赛记录；名单按门将/后卫/中场/前锋分组，只有号码、姓名、位置及由逐场记录汇总的出场、进球、射门。单场缺失统计不补零，不展示生日和身体资料。详情名单不分页，避免默认 20 条漏掉最后六人；独立 `players` API 仍保留原分页合约。

球队进球包含有利于本队的乌龙球，球员进球不含乌龙球；双方数据均不计点球大战。球队赛程以本队比分在前展示，并标注主/客队。`TeamController` 在模型查询后组装完整名单、赛程和摘要，`Team` 模型负责聚合 SQL。验收逐队核对 48 队均展示 26 人及至少三场比赛。
