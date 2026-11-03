# 世界杯网页功能

本文件记录实际实现，正式课程材料仍放 `data/team/`。开发分支为 `feature/worldcup-data-import`，使用现有 18 张表，不新增表。页面继续沿用 FOURWARD 深色风格，绿色表示积极结果/晋级，蓝紫色维持公共导航。

## 比赛详情

入口 `/worldcup/?view=matches&id=内部比赛ID`，从赛程卡片进入。顶部显示国旗、双方、比分、阶段、北京时间和场馆；常规、加时和点球分别展示。下方是双列首发/替补名单，以及技术统计与事件列表。缺失数据显示 —，不推算出场分钟或助攻。允许替补席球员领牌，乌龙球有独立标记。控球率保留来源值，不把争夺中比例强行分给任一球队。

模型 `MatchDetail` 读取名单、统计与事件；`MatchController` 在验证比赛存在后组装详情；视图负责转义及排版。`action=match` 增加 lineups/statistics/events 字段，原有比分和支持字段兼容。

国旗为本地 SVG，来自 lipis/flag-icons（MIT），署名见 `public/assets/images/flags/CREDITS.md` 和 LICENSE。按固定 FIFA 代码白名单选取，不直接使用数据库图片 URL。

验证命令：`php scripts/check-worldcup-pages.php`、`php scripts/check-worldcup.php`、`node scripts/check-prototype.mjs`。新增页面验收使用本机已导入的完整快照，不写入数据库。

当前浏览器自动视觉检查被工具中止：该 Windows 浏览器不支持 URL 策略检查。已停止该检查路径；接口与服务器渲染输出均进行验收，不将其表述为已完成浏览器视觉验收。
