# 世界杯数据来源可用性核查

核查日期：2026-11-03（北京时间）。本次只调查、下载样本并进行交叉检查，没有修改数据库结构、导入业务数据或执行第三方项目代码。本文中的“可获取”表示核查时请求成功，不等同于字段全部准确、覆盖完整或长期接口承诺。

## 结论

推进顺序调整为：数据来源实测 → 字段与身份映射 → 小样本清洗验收 → 有依据地扩表 → 批量导入 → 网页功能与后台管理。

目前有证据支持建设国家队/球员资料、赛程赛果、阵容、事件时间线、射手/助攻榜、懂球帝综合能力和部分技术统计。热区坐标、逐场出场分钟及逐场助攻拆分仍需进一步核查，不预先承诺完整复刻。

优先组合：FIFA 第一方接口作为球队、比赛及球员编号的核对基准；openfootball 作为可下载的开放快照；懂球帝的公开数据作为中文名称、榜单、阵容和网页信息组织参考；详细 CSV 统计作为经过清洗后的补充。前台统一读取本地数据库，采集与页面请求分开。

## 已实测的数据

| 来源 | 实测结果 | 可支持功能 | 核查边界 |
| --- | --- | --- | --- |
| FIFA 赛季球队接口 | 2026 赛季返回 48 支球队 | 国家队列表、稳定球队编号 | 中文名称覆盖尚未核查 |
| FIFA 比赛接口 | 返回 104 场、48 队、12 组、16 个场馆；双方分数均非空 | 赛程、赛果、阶段、分组、场馆 | 状态枚举、加时/常规比分口径仍要明确映射 |
| FIFA 赛季阵容接口 | 阿尔及利亚样本返回 26 人，含本届赛季 ID、球员编号、号码、位置、生日、身高体重及部分照片地址 | 国家队阵容、球员资料 | 只核查一队；全量名单变更、图片可用性未验证 |
| FIFA 球员赛季统计 | 返回以球员编号为键的 1,259 个条目；含助攻、射门等指标 | 本届球员统计、榜单候选来源 | 属于赛季汇总，不是单场数据；指标第三个标志位和缺失语义待核查 |
| openfootball 基础比赛 JSON | 104 场，104 场都有 ft 分数 | 离线赛程与赛果备份 | ft/et/p 口径须按该格式处理，不盲填 score_90 |
| openfootball 阵容 JSON | 48 队、1,248 条名单，样本文件每队 26 人；姓名、号码、位置、生日、俱乐部字段均非空 | 国家队球员看板 | 属于该文件快照，不直接当作最终调整名单；不能与 1,259 条统计直接按姓名合并 |
| openfootball 详细比赛 JSON | 104 场均有 lineup；208 份球队首发均为 11 人；有换人、进球、部分红黄牌/点球信息 | 首发、替补、事件时间线 | bookings 字段出现在 94 场；缺字段不能直接认定为零事件，事件完整性未逐场核实 |
| Alamyy/Worldcup26 CSV | 已下载 104 场赛程、5,392 条比赛名单记录、3,432 条球队指标、3,288 条球员进攻统计、2,587 条球员事件；以上文件各自覆盖 104 个比赛编号 | 单场阵容、技术对比、球员逐场表现 | 已发现一处球队代码错误；不能原样直接入库，见质量问题 |
| 懂球帝 2026 数据入口 | competition_id=61、season_id=26123；积分、球员榜目录、球队榜目录、赛程均返回 JSON | 中文名称、榜单分类、网页数据组织 | 榜单目录只表示存在类别，不能据此认定所有类别都有完整数据 |
| 懂球帝榜单样本 | 射手榜响应 179 行，助攻榜 167 行，球队进球榜 47 行 | 射手榜、助攻榜、球队榜 | 助攻榜为完整非零榜；已映射名单中未出现者按 0 处理，不向单场分摊 |
| 懂球帝决赛样本 | 比赛详情和阵容接口成功；双方均有 11 人首发、15 人替补及站位字段，另有教练等信息 | 阵型图、中文阵容、比赛资料 | 只核查决赛样本，不宣称 104 场均有完整站位或评分 |

## 重要的数据质量问题

### 1. 第 47 场的球队代码错误

将 Alamyy 的 104 场 `matches.csv` 按 `match_number` 对齐 FIFA 记录后：

- 104 场主客队数值比分均一致。这只是所比较比分字段的一致性，不表示已经验证常规/加时/点球语义。
- 103 场双方国家代码一致。
- 第 47 场，FIFA 为葡萄牙 POR 对乌兹别克斯坦 UZB；CSV 的客队名称为 Uzbekistan，但 `away_team_id` 为 GHA，`match_id` 为 `2026-M047-POR-GHA`，来源 PDF 地址也含 GHA。

处理原则：保留原始数据；先进入异常队列，不静默入库。不能全局把 GHA 替换成 UZB；必须把更正限定到该场及其相关行，并复核原报告和各关联表。内部比赛/球队 ID 以核实后的 canonical mapping 为准。

### 2. 球员数量与 ID 体系不同

openfootball 的 1,248 条名单、FIFA 的 1,259 个统计键，以及详细 CSV README 声称的 1,277 条球员记录，属于不同来源的实体定义和快照。不能用数量相等作为正确性标准，也不能直接按名字或球衣号跨来源连接。

FIFA 球员编号可用于连接本届阵容与 FIFA 赛季统计；已检查阿尔及利亚样本 26 个编号均可在该统计响应找到。懂球帝另有自己的 person_id/team_id/match_id，CSV 还使用根据名字生成的 player_id。需保存来源编号并建立经过验证的映射，姓名变体、替补名单调整和同名情况进入人工核对队列。

### 3. 赛季统计不能冒充逐场统计

FIFA `season/.../players.json` 是赛季汇总；名单中的“国家队生涯进球”也不是本届世界杯进球。某个字段存在不代表这一粒度的数据已拿到。

必要时新增独立的球员本届汇总表，再逐步接入可靠的单场统计；不为了沿用预先画出的表，把赛季总数分摊或复制到每场。

### 4. 时间、事件与缺失值

FIFA `Date` 含 UTC 标识；openfootball 同时给日期和 `UTC-6` 等偏移；CSV 有些开球时间只有当地时刻。优先采用已核对的 UTC 开球时间，不能把无偏移的本地时间直接当 UTC。

事件分钟保留原始文本，如 `90+2`，区分常规、加时与点球阶段。缺失事件/空字段不自动转成零。原值、来源和采集时间应能追溯。

## GitHub 项目筛选

以下项目读取过仓库元信息或 README；“实测数据”见前表。未安装或执行其中的工具，不将 README 功能宣传视为运行验证。

| 项目 | 用途与判断 | 许可证/维护观察 |
| --- | --- | --- |
| [openfootball/worldcup.json](https://github.com/openfootball/worldcup.json) | 优先采用其可下载赛事与名单快照；已检查基础、详细比赛及阵容文件 | 实际读取 LICENSE.md：CC0-1.0 |
| [stiles/world-cup](https://github.com/stiles/world-cup) | 参考 FIFA endpoint、赛季参数、数据分层和编号关联；阅读 players.py 后成功请求指定赛季的阵容 | 仓库标注 MIT；代码最后推送 2026-07-26 |
| [Alamyy/Worldcup26](https://github.com/Alamyy/Worldcup26) | 详细统计候选；已下载 5 个 CSV 并找到第 47 场异常 | 实际读取 LICENSE：CSV/元数据/文档 CC BY 4.0；不包含媒体素材授权 |
| [torqueda/world_cup_player_tracker](https://github.com/torqueda/world_cup_player_tracker) | 球员看板、身份映射、名单变更、数据质量页的结构参考；未运行或全量核查其数据 | 元信息未检出明确许可证；README 提及赛后刷新仍有收尾项 |
| [EbEmad/FIFA-Data-Wc-2026](https://github.com/EbEmad/FIFA-Data-Wc-2026) | FIFA EFI 单场统计采集方向候选；本次只阅读说明，不按 127 列宣传推定全量可用 | 未检出明确许可证；最后推送 2026-06-27 |
| [probberechts/soccerdata](https://github.com/probberechts/soccerdata) | 通用多站点采集库，作为扩展工具备选；本次没有验证其 2026 覆盖或运行环境 | README 标注 Apache-2.0；不是所有来源都能无条件访问 |
| [Sz-Jason-Chen/DongQiuDiCrawler](https://github.com/Sz-Jason-Chen/DongQiuDiCrawler) | 阅读 connector.py/main.py，参考 seasons、schedule、detail、lineup 路径；本次用自己的只读请求验证了当前 2026 部分接口 | 未检出明确许可证；最后推送 2023-08-05，部分接口仍可访问，不代表整套代码都可运行 |
| [hoosin/DongQiudi](https://github.com/hoosin/DongQiudi) | 旧 Node 接口项目，作为历史线索，不作为当前站点底座 | 未检出明确许可证；最后推送 2018-10-22 |
| [pengshiqi/Dongqiudi](https://github.com/pengshiqi/Dongqiudi) | 旧 App 采集分析，与本项目国家队/赛事数据目标不完全匹配 | 未检出明确许可证；最后推送 2018-05-18 |
| [Neisun/Dongqiudi](https://github.com/Neisun/Dongqiudi) | 旧 Vue2 移动端仿站；本项目是电脑网页端，适合观察组件划分，不直接替换现有工程 | 未检出明确许可证；最后推送 2017-12-21 |
| [StatsBomb / Hudl open-data](https://github.com/hudl/open-data) | 实际下载 competitions.json，有历届世界杯，未发现 2026 男足世界杯赛季 | 本轮不作为 2026 数据主源；不要用 2022 数据冒充 2026 |
| [football-data.org](https://www.football-data.org/coverage) | 官方覆盖页列有 Worldcup，但本机无密钥请求 2026 比赛接口返回 403 | 未验证取得账号权限后可用的字段和完整性；目前已有无需密钥的候选来源 |

检索中还出现 `leolin49/dqd-spider`，但实际仓库读取返回 404，本轮不采用。GitHub 的 updated_at 可能因非代码活动变化；表中维护时间采用 pushed_at。

## 请求与文件位置

FIFA 本届赛季标识：`idCompetition=17`、`idSeason=285023`。以下路径是本次实际请求成功的样本，不视为承诺不变的正式开发合约。

```text
https://api.fifa.com/api/v3/competitions/teams/285023?language=en-US
https://api.fifa.com/api/v3/calendar/matches?idSeason=285023&idCompetition=17&count=200&language=en-US
https://api.fifa.com/api/v3/teams/43843/squad?idCompetition=17&idSeason=285023&language=en-US
https://fdh-api.fifa.com/v1/stats/season/285023/players.json

https://raw.githubusercontent.com/openfootball/worldcup.json/master/2026/worldcup.json
https://raw.githubusercontent.com/openfootball/worldcup.json/master/2026/worldcup-full.json
https://raw.githubusercontent.com/openfootball/worldcup.json/master/2026/worldcup.squads.json

https://api.dongqiudi.com/soccer/biz/data/seasons?competition_id=61
https://api.dongqiudi.com/data/detail/match/54328033
https://api.dongqiudi.com/soccer/biz/dqd/v1/match/lineup/54328033
```

懂球帝 2026 的赛季响应返回积分、榜单和赛程链接，使用其实际返回的 URL。默认赛程只返回当前轮（本次为决赛一场）；需要按返回的 rounds 逐轮查询并去重，不能把一个成功响应当作 104 场已采集。射手榜/助攻榜及球队进球榜本次读取的是 26123 赛季，不使用仓库示例中的旧比赛代替 2026。

openfootball raw 域名一度发生 TLS 连接中断，随后通过 GitHub Contents API 成功读取同一文件内容；不是关闭证书验证。读取时的 Git blob SHA：

- `2026/worldcup.json`：`a99c2098b1cfaa5d3be08bf1ac60d68612925655`
- `2026/worldcup-full.json`：`f877301911d8d83ef69a0393a983772e520397d0`
- `2026/worldcup.squads.json`：`3c01473715a2533fc28f1b3f2a68e6fa848bea84`

原始响应、样本 CSV、仓库说明与核查中间结果暂存于被 Git 忽略的 `tmp/data-audit/`；它们不是可迁移的正式数据包。后续制作正式快照时，应记录 URL、UTC 采集时间、版本/哈希、许可证说明、原始文件及校验报告，并纳入约定的数据目录。

## 网页功能取舍

界面参考懂球帝的[网页比赛页](https://pc.dongqiudi.com/match)和[世界杯数据页](https://pc.dongqiudi.com/data?cid=61&tab=standings)，继续建设自己的 PHP MVC 网页端。

| 功能 | 依据与落地顺序 |
| --- | --- |
| 国家队、球员主页 | 已有可获取名单和个人资料，先完成一队的 ID 对齐与资料展示，再扩展全量 |
| 赛程、结果、分组 | 先核对并导入 FIFA 的核心记录，处理比分口径、UTC 和状态映射 |
| 射手、助攻、球队榜 | 助攻总榜已实现；射手与球队榜继续使用现有本地统计口径 |
| 比赛阵容、事件 | 有全部比赛的详细 JSON 和单场阵容样本；关联球员、规范事件类型后实现 |
| 技术统计、球员单场表现 | 有详细 CSV；先排查来源错误、字段完整性及单位，再按实际字段建模 |
| 阵型图 | 已验证单场站位样本，先验证更多比赛，保留无站位时的名单视图 |
| 热力图、评分 | 未确认完整可靠的数据，保留后续评估，不生成虚构数值 |
| 支持、关注、评论 | 来自本站账号和业务操作，与外部球迷行为分开，自行开发数据库和后台流程 |

已完成[最小清洗样本](../data/samples/worldcup2026/README.md)，随后按经验证的来源扩表并完成[全量核心数据入库](../data/worldcup/README.md)：48 队、1,248 名球员及全部 104 场的赛程、阵容、核心技术统计和事件。只保留核心足球字段，不含生日、身高、体重或照片。原小样本保留一项跨来源姓名差异供研究，不把该来源姓名强行绑定；正式名单使用 FIFA 本届身份。第 47 场代码冲突经 FIFA 双方球队、26 名球员及详细比赛名单核对后，按限定该场的显式规则更正。另有三处截断姓名、两处时间差异等记录，详见全量包的 corrections 和 quality-report。后续已接入网页技术统计、阵容、事件、国家队名单、积分榜和晋级图；实际页面边界与验收见 [网页功能说明](worldcup-pages.md)。
