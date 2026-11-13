# 2026 世界杯课程设计

互联网数据库开发小组作业，包含团队主页和世界杯两个模块。

当前完整功能使用 PHP + PDO MySQL，Web 根目录为 `public/`。代码协作：[GitHub 仓库](https://github.com/ACobayashi/WorldCup2026DB)。历史 [Pages 预览](https://acobayashi.github.io/WorldCup2026DB/) 仅作静态演示，不运行登录、上传或数据库，后续正式站点使用云服务器；本轮未执行部署或推送。

## 账号、资料与课程作业

PHP 数据库版共用一套 `users` 和 Session，新增以下入口；静态 HTML / Pages 不提供这些写入功能：

所有页面使用“团队介绍 / 个人介绍 / 世界杯 / 登录注册或个人中心”四个全局栏目。成员介绍旁固定三个作业连线框，读取该成员最新发布记录；边框信号随机间隔变化，减少动态偏好下停止。首页“课程作业与下载”进入全组目录，目录保留返回按钮。工作台、上传、图表、留言审核和退出登录统一从个人中心进入；业务权限仍在服务端检查。

| 入口 | 功能 |
| --- | --- |
| `account.php` | 注册、登录、个人中心、退出；默认登录回个人中心，下载登录后返回原文件。 |
| `workspace.php` | 团队成员编辑自己的公开资料；管理员可编辑团队介绍及所有成员资料。 |
| `workspace.php?section=files` | 上传文件或保存 HTTPS 外部下载链接，核对、发布及撤回；团队作业仅管理员维护，个人作业由本人或管理员维护。 |
| `coursework.php` | 公开作业目录，未提交显示“待提交”；只展示已发布文件，每项取最新发布版本。 |
| `download.php?id=…` | 登录后下载已发布文件，或跳转到已发布外部链接；草稿仅归属成员和管理员可核对。 |
| `workspace.php?section=stats` | ECharts 作业数量条形图、状态筛选及数据库刷新；管理员另有账号角色饼图。 |

新注册用户为 `user`，只可下载，不能通过注册页面获取 `member` 或 `admin`。团队账号仍由 CLI 创建。原留言审核及座右铭工作台保留在 `admin.php`。不另建文章系统，课程作业采用草稿/发布/撤回流程。已发布旧版本在撤回前仍可凭文件编号下载。

数据库新增 `team_profiles`（团队/个人公开资料）和 `coursework_files`（归属、作业编号、文件或外链来源、文件元数据及发布状态）；共享账号表增加 `user` 角色，现有账号保留。仍为 **20 张表**。迁移 `004` 补角色，`005` 补外链字段；外链不伪造文件大小或 SHA-256，服务端不抓取外链内容。外链目标是否可用及网盘自身权限由对方站点决定；本站登录仅控制跳转入口，不能保护已被复制的外部地址。

上传文件在 `data/team/uploads/` 和 `data/personal/<member-slug>/uploads/`，这些运行时目录不进 Git；正式提交材料仍需另外整理，不能用忽略的上传目录或网盘链接代替老师要求的仓库文件。迁移服务器时需一起备份数据库、上传文件并重新配置 `.env`；只推送 Git 不会迁移现有账号、留言、编辑资料或上传记录。

新环境先配置下文的 `.env` 和数据库；已有环境直接迁移。PHP 需要 PDO MySQL、mbstring、fileinfo 和 ZipArchive。以下为本地运行命令，不是云服务器部署：

```sh
php scripts/migrate-team-workspace.php
php -d upload_max_filesize=50M -d post_max_size=55M -S 127.0.0.1:8088 -t public public/router.php
```

应用允许真实的 PDF、ZIP、DOCX、PPTX、MP4，单文件不超过 50 MiB；服务器的 PHP 与反向代理上传限制还需同步配置。作业文件及数据库应一起备份。ECharts 6.1.0 随仓库本地加载，许可文件位于 `public/assets/vendor/echarts/`，不依赖运行时 CDN。

林子媛（2410936）的 MVC 文件对应：

| 功能 | Model：查询/写入数据 | Controller：请求、校验、权限与流程 | View：表单和结果展示 |
| --- | --- | --- | --- |
| 注册登录 | `app/team/models/UserAccount.php` | `app/team/controllers/AccountController.php` | `app/team/views/portal/account.php` |
| 资料编辑 | `app/team/models/TeamProfile.php` | `app/team/controllers/WorkspaceController.php` | `app/team/views/portal/workspace.php` |
| 作业发布下载 | `app/team/models/CourseworkFile.php` | `app/team/controllers/CourseworkController.php`、`WorkspaceController.php` | `app/team/views/portal/coursework.php`、`workspace.php` |

这些新增 PHP 文件头标注姓名和学号，不加队名或日期。公共认证继续复用原 `Auth.php`；修改原作者文件时保留原头注释并标注维护人。MVC 是把一个完整功能分成数据、流程、展示三种职责，不是“必须新增一张表”。本模块可以提供个人 MVC 代码贡献证据，不能代替课程另行要求的个人文档和其他提交材料。

例如注册流程是 `public/account.php → AccountController → UserAccount → users`，最后由 `portal/account.php` 展示表单或个人中心。Model 负责数据校验与数据库读写；Controller 负责请求、权限、CSRF 和流程；View 负责表单与页面。三个模型、三个控制器和相应视图构成个人代码贡献，不能把原作者的公共 `Auth.php` 或原留言模型算成全部由本人重写。

### 审核留言与发布作业

- 管理员登录后，个人中心点击“留言审核”，进入 `admin.php?status=pending#review-heading`，可通过、拒绝或移回待审核。成员账号只显示管理员权限提示，不授予审核权。
- 成员登录后，个人中心点击“上传与发布作业”，选择归属、编号和提交方式，上传文件或填写 HTTPS 地址，保存草稿后在下方点击“发布到前台”。
- 发布后，全组目录和该成员介绍旁的对应作业框同步提供下载入口；撤回后普通用户不能下载。已有外链若需要强制文件下载，应提供对方站点实际的下载地址，本站不把普通网页伪装成附件。

### 按课程截图核对仓库

| 要求 | 当前位置与状态 |
| --- | --- |
| 统一命名 | PHP 类名与文件名对应，模型/控制器使用 UpperCamelCase，视图及资源使用统一小写命名。 |
| PHP 注释头 | 当前 `app/`、`config/`、`public/`、`scripts/` 的 108 个 PHP 文件均有注释头。新增个人文件标注林子媛、2410936，保留他人原署名及协作注释。 |
| SQL 放在 `/data/install.sql` | 已有 20 表建表与初始资料 SQL，结构变更另有 `data/migrations/`。这不是当前运行库的完整导出备份；世界杯全量数据由仓库快照及 CLI 导入。 |
| 团队作业放在 `/data/team/` | 目录已就位，七项清单见该目录 README；正式材料仍需成员实际完成并放入。 |
| 个人作业放在 `/data/personal/` | 已从 member-a/b/c/d 占位改为四人实际 slug 目录，详见该目录 README。 |
| 页面提供作业下载 | 已有全组目录、个人介绍三个入口及登录下载；未发布的项目显示待提交。 |
| 每人至少一组 MVC | 林子媛的注册、资料、作业模块均有对应 M/C/V 文件，见上表；其他人的贡献需分别核对，不冒认归属。 |

以上核对针对截图中的目录和代码要求，不表示正式作业材料已经齐备，也不表示已安装 Yii2；当前运行的是轻量 PHP MVC/PDO。

主分支已补充成员学号及马禹翔的协作头注释，合并时保留多方署名。`TeamProfile.php` 兼容两位成员的资料表单；实际贡献应结合提交历史和完整功能核对，不能仅按第一段注释计数，也不能把协作文件全部归于一人。正式材料及全组个人要求仍需各成员分别核对。

数据库配置完成后可运行：

```sh
php scripts/check-team-workspace.php
php scripts/check-team-http.php http://127.0.0.1:8088
node scripts/check-team-navigation.mjs
node --check public/assets/js/workspace-charts.mjs
node --check public/assets/js/coursework-upload.mjs
```

第一个脚本事务回滚模型测试数据；第二个针对空闲的本机开发服务验证真实注册、上传、发布及登录下载，清理本轮账号和文件并恢复受影响的限频记录。不要在生产环境或有人同时操作的实例上运行 HTTP 验收。

本次在 macOS / PHP 8.3.36 / MariaDB 12.3.2 的本机开发库完成 81 项模型检查、44 项真实 HTTP 检查；前端额外验证四栏目导航、三个作业入口、随机信号/减少动态、文件/外链切换、PHP 注释头及目录结构。测试夹具清理，不覆盖用户已有资料或留言。ECharts 当前用于内容工作台统计，原世界杯 SVG 图表保留。

新版截图：[成员作业连线框](docs/screenshots/member-coursework-desktop.jpg)、[个人中心](docs/screenshots/team-account-center-desktop.jpg)、[外链提交表单](docs/screenshots/team-coursework-link-desktop.jpg)、[全组目录](docs/screenshots/team-coursework-catalog-clean.jpg)、[蓝色开场全页](docs/screenshots/worldcup-entrance-saturated.jpg)、[精简赛程页](docs/screenshots/worldcup-matches-clean.jpg)。截图使用当前本机资料，不代表正式作业已提交；保留用户已编辑的座右铭。此前完整 Python 回归套件通过 63 项；图片相关测试需要 Pillow，本机使用已包含 Pillow 的工作区 Python 运行，系统 `python3` 尚未安装该依赖。

### macOS / Unix 本机首次配置

已有本机 MariaDB 且管理员可通过 Unix socket 连接时，可以使用下列工具。示例 socket 必须替换为该服务的实际路径；本次已核对的服务使用 `/tmp/mariadb-practice.sock`，TCP 端口为 3307：

```sh
php scripts/setup-local-unix.php /tmp/mariadb-practice.sock
```

脚本仅初始化 `worldcup2026`，创建只匹配本机的 `worldcup_coursework` 开发账号，其授权限于该库的查询、写入和迁移，不授予全局权限或账号管理权限。数据库密码及 APP_KEY 随机生成，只写权限为 600 的 `.env`；团队网页账号随机密码位于同样受保护、Git 忽略的 `storage/local-accounts.txt`。不会重置已有账号密码；发现已有 `.env` 或同名数据库账号会停止，改用手动配置及迁移。后续按上文 PHP 服务命令启动。

MySQL Workbench 只是数据库管理客户端，不是 PHP 后端。连接本次项目库时使用 `127.0.0.1:3307`、库名 `worldcup2026`，专用数据库账号及密码从本地 `.env` 查看；不要继续使用端口 3306，也不要把网页成员密码填作数据库密码。云端部署、反向代理和 HTTPS 仍未验证。

## 完整留言模块本地运行

已经实现访客留言和团队座右铭的 PHP MVC 功能链。访客可给整个团队或某位成员留言，留言写入数据库并默认为待审核，管理员通过后才公开；团队成员登录后可维护自己的座右铭，管理员还可维护团队及所有成员的座右铭。

在仓库根目录执行以下 PowerShell 命令。需要已有 XAMPP，包含 PHP 8.2+、PDO MySQL、mbstring 和 MariaDB 的 Windows 初始化工具。此前验证使用 E:\XAMPP 的 PHP 8.2.12 / MariaDB 10.4.32；本轮在 macOS 验收，未在 Windows 复测角色迁移及上传限额的启动脚本调整。这不是生产部署命令。

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-local.ps1 -XamppRoot E:\XAMPP
```

打开 http://127.0.0.1:8088/ ，从右上角进入个人中心，再进入资料、上传或留言审核。默认本地账号为严苑毓的 `yan-yuanyu`（管理员）及另外三位成员的 slug（成员）。随机密码保存在 `storage/local-accounts.txt`，只用于本地开发，已经从 Git 排除；已存在的账号密码和编辑内容不会被初始化脚本覆盖。

脚本使用独立的 `storage/runtime/mysql` 数据目录，数据库端口 3307，Web 端口 8088，均只监听 127.0.0.1。它不会使用已有 XAMPP 的数据目录。重复启动保留账号、留言和座右铭，首次生成的 `.env` 和本地密码文件也从 Git 排除。端口冲突会报错；已有自定义 `.env` 时先按下面的手动配置方式运行。

```powershell
powershell -ExecutionPolicy Bypass -File scripts/stop-local.ps1 -XamppRoot E:\XAMPP
```

正常停止后数据仍保留。换机器时带走代码及所需 SQL/数据备份，重新初始化本地环境；不要复制、提交或公开运行目录及密码文件。

## 手动使用团队数据库

工作台支持发布团队介绍，以及成员的名称、简介、学院、分工、工作内容、标签和 GitHub 主页。管理员可编辑全部资料，普通成员只能编辑自己的资料。`workspace.php` 和原 `admin.php` 的资料表单共享 `team_profiles`；迁移兼容两种旧表结构，保留原来的账号、留言、座右铭和已编辑资料。静态预览仍使用仓库里的默认资料。

PHP 主页的“课程作业与下载”进入 `coursework.php`，汇总所有已发布作业，文件需登录后下载。保留 `downloads.html` 的源码 ZIP 入口和 `downloads.php` 的旧版私有归档功能：成员仅可下载自己的旧归档，管理员可下载已提供的全部旧归档。旧压缩包放在 `storage/course-downloads/<member-slug>/assignment-1.zip`（作业二、三对应 `assignment-2.zip`、`assignment-3.zip`）。没有文件就不显示下载链接。该目录不进 Git，也不随 GitHub Pages 发布；换服务器时需要单独交接文件。

复制 `.env.example` 为 `.env`，填写同一个逻辑数据库的 PDO MySQL DSN、账号、密码和至少 32 字符的随机 APP_KEY。依赖 PHP 8.2+、PDO MySQL、mbstring、fileinfo、ZipArchive。先创建空数据库，再在仓库根目录执行：

```sh
php scripts/migrate-team-workspace.php
php -d upload_max_filesize=50M -d post_max_size=55M -S 127.0.0.1:8088 -t public public/router.php
```

安装 SQL 使用 CREATE TABLE IF NOT EXISTS 和 INSERT IGNORE，不清空已存在的数据，也不覆盖座右铭或已编辑资料；后续结构变更需显式迁移。目前包含六张团队/公共表及十四张 `wc_` 表，共 20 张。全量世界杯基础数据通过独立 CLI 导入，不在初始化时预置；本站支持写入尚未实现。共享账号表只创建一套；之后选择团队统一框架时，保留模型和功能链，接入公共登录及路由。已有环境执行 `php scripts/migrate-team-workspace.php` 兼容资料表结构、补建新表并升级账号角色；`install.php` 也使用同一幂等迁移服务。

创建账号需通过环境变量提供 12–72 字节密码；密码只保存为哈希，不写进安装 SQL：

```powershell
$env:ACCOUNT_PASSWORD = Read-Host '输入本地开发账号密码'
php scripts/create-user.php yan-yuanyu yan-yuanyu admin
Remove-Item Env:\ACCOUNT_PASSWORD
```

成员账号将最后一个参数改为 `member`，账号绑定实际成员 slug。网站根目录应为 `public/`；Apache 可使用其中的 DirectoryIndex 设置，其他服务器请优先指定 `index.php`。此版本采用轻量 PHP MVC 类，未选用第三方框架，尚未验证云端或 Docker 部署。

## 验证

主页与场边留言截图来自本地数据库模式；公开留言按现有审核数据展示。GitHub Pages 使用静态演示模式。

![团队主页](docs/screenshots/team-homepage.jpg)

![场边留言](docs/screenshots/team-guestbook.jpg)

```sh
node scripts/check-prototype.mjs
node --check public/assets/js/app.mjs
node --check public/assets/js/guestbook.mjs
node scripts/check-worldcup-snapshot.mjs
php scripts/check-guestbook.php
php scripts/check-worldcup.php
```

数据库验收使用事务并最终回滚所有测试数据，覆盖审核可见性、分页、Unicode 长度、文本转义、参数化 SQL、CSRF、角色权限、座右铭编辑和数据库限频。在已初始化的开发数据库上执行。访客留言每个 IP 每分钟窗口最多尝试 5 次，登录每个 IP 每 15 分钟窗口最多尝试 10 次；限频表存储带密钥的摘要，不存原始 IP，也不信任访客自报的代理头。

## 仅预览静态页面

```sh
python3 scripts/serve-prototype.py
```

打开 http://127.0.0.1:5173 。预览脚本需要 Node.js，会先从仓库数据生成世界杯快照；固定 .mjs 的 JavaScript 响应类型，避免 Windows 文件关联将其错误地识别成纯文本；不执行 PHP。运行 `node scripts/check-prototype.mjs` 和 `node scripts/check-worldcup-snapshot.mjs` 检查两个模块。

使用其他静态服务器时，先执行 `node scripts/build-worldcup-snapshot.mjs`，再以 `public/` 为网站根目录。生成的 `public/assets/data/worldcup-snapshot.json` 不进 Git，不包含账号、留言或支持记录；GitHub Actions 每次发布前离线重建，不访问数据源网站。

静态预览及 GitHub Pages 保留浏览器本地演示留言，明确标注为本地演示，不能跨浏览器共享，也不运行审核后台。使用 PHP 入口时则调用真实数据库接口，服务故障会显示失败并保留输入，不自动改为本地保存。页面保留独立场景转场、四人档案、人物入场和面部背景动效，成员卡片支持方向键。成员标签根据学院及分工整理，人物形象为插画。主页的“场边留言”展示最新三条团队留言，数据库模式只展示审核通过的内容；全部留言和分页保留在留言弹窗中。

静态预览和 GitHub Pages 的 `worldcup/index.html` 现在是世界杯只读资料入口：48 队完整名单、1,248 名球员、104 场赛程与比赛详情、首发阵型、技术统计与事件、球员赛事/单场面板、球员榜、球员/球队搜索对比、小组积分、第三名比较与淘汰赛晋级图。全部读取功能分支已提交的快照，不是实时赛事 API，也不连接共享数据库。缺失字段保留为 —；排名保留无法核实的并列。球队及球员对比支持两侧即时搜索，图表旁提供原始值表格。

原比分大屏和支持度演示移动到 `worldcup/demo.html`，资料入口保留直达链接。该页的对阵、时间、比分和票数仍均为合成示例，支持选择仅保存在浏览器；不与快照统计混用。静态版没有登录、个人支持、后台编辑或写入功能；这些边界仍由 PHP 入口处理。球员图片沿用已核对的外部图片地址，无法加载时回退到本地号码标记。

只读快照页面：[桌面截图](docs/screenshots/worldcup-snapshot-desktop.png)、[手机截图](docs/screenshots/worldcup-snapshot-mobile.png)。本地浏览器检查覆盖模块跳转、筛选分页、单场切换与刷新、对比搜索、晋级连线、原演示支持持久化、320/390px 布局、Pages 子路径和快照加载失败提示。

PHP 本地环境的 `/worldcup/` 仍进入世界杯 MVC 数据库版，包含赛程列表与详情、球队搜索与阵容详情、分组名单、场馆、个人支持记录，以及管理员数据概览。它从数据库查询，无数据时显示待导入，不回退到示例或快照。普通成员可查看自己的支持记录，管理员可查看赛事工作台；统一登录入口为 `/account.php`，原 `/admin.php` 兼容保留。当前未开放世界杯后台编辑、网页数据导入和支持写入；CLI 导入已实现。写入预留接口验证登录及 CSRF 后明确返回 501，不会保存假成功。数据库页面提供独立快照和静态演示链接。入口、接口及后续模块边界见 [世界杯框架说明](docs/worldcup-foundation.md)。

后续功能以实际可获得的数据为依据，先核查来源与身份映射再扩表。2026 年 11 月 3 日的实际请求结果、候选 GitHub 项目、可实现功能和已发现的数据错误见 [数据来源核查](docs/worldcup-data-sources.md)。该核查尚未把任何赛事资料导入业务数据库。

已完成一个国家队、两场比赛的[核心数据清洗样本](data/samples/worldcup2026/README.md)，包含原始字段快照、来源清单、清洗结果和异常报告；不包含生日、身高、体重或照片。在仓库根目录执行 `python scripts/worldcup_data.py` 可离线重建，执行 `python -m unittest discover -s tests -p test_worldcup_data.py` 可运行 22 项校验，需要 Python 3.10+，无需第三方包。原始样本保留一项跨来源姓名差异待核实，未强行合并；入库使用核验后的 FIFA 球员身份。

已增加事务式 CLI 导入器和六张导入/统计表；账号与作业扩展后的完整初始化 SQL 共 20 张表。先前已验证环境的全量核心数据：48 队、1,248 名球员、12 组、16 场馆、104 场比赛，以及覆盖全部比赛的 5,392 条比赛名单、3,288 条球员单场统计、208 条球队汇总和 2,587 条事件。47 项 Python 校验、28 项全量数据库验收和 10 项导入回滚检查通过。换机器需使用已提交快照离线重建并导入 `data/worldcup/full/complete.json`，不能假定新库已有赛事数据；单场助攻和出场分钟等缺失字段保持为空。复现命令、来源署名、差异与更正规则见[数据库导入说明](data/worldcup/README.md)。

数据库页面已完成比赛详情（国旗、首发/替补、技术统计、事件）、48 队完整名单及球队赛程、小组积分榜和淘汰赛晋级图。赛程支持球队名或三字代码搜索，并可组合小组赛/淘汰赛、状态及日期筛选；积分页提供“小组积分 / 第三名比较 / 淘汰赛图”切换。排名遵循 FIFA 2026 相互战绩优先规则，无法确认的公平竞赛/排名并列保留待核实。343 项页面检查、491 项积分与晋级检查通过。页面入口、素材许可和浏览器视觉检查限制见[网页功能说明](docs/worldcup-pages.md)。

国家队球员卡片和比赛阵容姓名已可进入[个人数据面板](docs/worldcup-player-board.md)：赛事汇总、懂球帝综合能力六维图、可切换的单场方格统计、逐场记录与事件；新增球员榜及位置筛选。球员对比页支持两侧即时搜索，以蓝色/橙色六维图、原始统计表和双系列柱状图比较两名球员。门将拥有独立的扑救、阻止进球、禁区内扑救、出击等比赛数据和扑救榜。扩展 17 项已核验的通用逐场指标及 214 条门将出场统计，未因此新增数据库表；4,477 项球员检查通过。首次接入在完整核心数据之后执行 `python scripts/worldcup_player_metrics.py`、`python scripts/worldcup_goalkeeper_metrics.py` 和 `php scripts/import-player-metrics.php`，详细步骤与缺失数据边界见说明。

球队对比入口为 `/worldcup/?view=team_compare`，两侧选择器均可按中文名、英文名或三字代码搜索全部 48 队。页面同时提供蓝橙双系列柱状图和原始数据表，对比胜平负、进失球、净胜球、xG/xGA、射门、控球、传球、零封及身价。xG/xGA 直接汇总逐场比赛报告；懂球帝逐场响应中的身价实际随首发变化，因此页面明确使用“本届赛事最高首发阵容身价”，不冒充 26 人注册名单总身价。同队对比会返回 422。

球员照片已接入：本机覆盖 1,248 人，其中 1,238 人使用现成大头照。图片缓存不进 Git，换机器运行 `python scripts/worldcup_photos.py` 下载；来源、身份核对和缺图回退见[球员照片说明](docs/worldcup-player-photos.md)。

比赛详情增加[绿茵首发阵容图](docs/worldcup-formations.md)：104 场真实阵型与首发站位、球衣号、国家队配色、队长及已记录事件；点击进入球员该场表现。无需新增数据库表或列。

## 统一接入约定（目标方案，分阶段接入）

当前团队模块已经使用轻量 PHP MVC/PDO 运行，保留现有账号、接口、SQL 和功能链。下表是后续统一集成目标，不代表已安装 Yii2、完成数据库迁移或验证 Docker 部署；不为追求目录一致而重建账号或清空数据。

| 边界 | 两组统一采用 |
| --- | --- |
| 环境 | 目标为 Yii2 2.0 单应用双模块；PHP 8.3、MySQL 8.4、Composer 2。迁移时提交一份 `composer.lock`，统一补丁版本；现有 PHP 8.2+/PDO 和已验证的 XAMPP/MariaDB 切片先保留，不混用两套登录。 |
| MVC | 唯一 Web 根目录 `public/`；`app/team/`、`app/worldcup/` 各自管理 MVC，Yii2 Module 路径配置到对应目录；共享后端放 `app/common/`。控制器不直接写 SQL。 |
| 路由 | 目标路径 `/team`、`/worldcup`、`/admin/team`、`/admin/worldcup` 和公共 `/login`。现有 `api.php?action=...`、`admin.php` 暂时兼容；Pages 预览是 `/#team` 与 `/worldcup/`。相对路径跳转，不硬编码云服务器地址。 |
| 登录 | 继续复用现有 `users` 与公共 Session/权限检查；迁移 Yii2 时统一接入 Session + RBAC，不另建平行账号。现有 user/member/admin 保留，权限按 `team.*`、`worldcup.*` 分区。写操作验证 CSRF，密码保持哈希。 |
| 数据库 | 最终一个逻辑库 `worldcup2026`，业务表 `team_`/`wc_`、新公共表 `sys_`；现有 `users`、`app_rate_limits` 保留，重命名需显式迁移。统一 InnoDB、utf8mb4，目标主外键 unsigned BIGINT，现有字段按迁移升级。各自本地库使用同一套迁移和种子，不随意共用线上开发库。 |
| UI | 公共 `common.css`/`common.mjs` 维护字体、蓝紫色变量、导航、按钮和通知；模块只加载自己的 CSS/JS。前端使用原生 ES Modules + Fetch，动效用 CSS/WAAPI；内容工作台使用本地 ECharts 6.1.0，现有世界杯 SVG 图表及支持度演示保留。 |
| 接口 | 新接口目标 `/api/v1/...`、JSON `{code,message,data,errors}`、字段 snake_case；分页 `page`、`page_size`（默认 20、最多 100），列表 `data.items`、`data.meta`。现有留言 `{ok,data,error}` 和 camelCase 字段先保持兼容，统一迁移时再加适配。HTTP 状态码遵循语义；UTC 存储、ISO 8601 传输、北京时间展示。比赛状态 scheduled/live/finished/postponed/cancelled。 |
| 配置 | 一个环境变量配置入口，提供 `.env.example`，真实凭据不进 Git；账号/权限/公共布局由一处维护。业务模型不能跨模块直接写表，需要通过共享服务/接口协作。 |
| 部署 | 正式版目标一份 Docker Compose（Nginx + PHP-FPM + MySQL），统一迁移与种子；现有 `data/install.sql` 已汇总 20 张表，后续结构变更通过显式迁移完成，不覆盖已有数据。数据库与上传目录挂持久卷。Pages 只做前端预览；Docker 配置尚未实现。 |

支持率按有效支持票计算，**不是胜率**。正式版每个账号对每场比赛一条支持记录，通过唯一约束和事务更新；比分只能来自管理员/赛事数据源。模拟数据适配器为 `public/assets/js/worldcup-data.mjs`，接入时替换数据来源，不改变视图职责。当前支持演示的取消/改选不代表后端权限已实现。

## Structure

```text
DBDev/
|-- README.md
|-- AGENTS.md                  # 简短贡献指南
|-- .github/workflows/pages.yml # 静态前端自动发布
|-- app/
|   |-- common/                # 公共功能
|   |-- team/                  # 团队主页
|   |   |-- controllers/
|   |   |-- models/
|   |   `-- views/
|   `-- worldcup/              # 世界杯 MVC 只读框架
|       |-- controllers/
|       |-- models/
|       `-- views/
|-- config/                    # 配置
|-- public/                    # 网站入口
|   |-- index.html             # 静态演示页面
|   |-- index.php              # 数据库模式主页
|   |-- api.php                # 访客留言和座右铭读取
|   |-- admin.php              # 登录、审核和座右铭工作台
|   |-- account.php            # 统一注册、登录和退出
|   |-- workspace.php          # 资料编辑、上传发布、ECharts 统计
|   |-- coursework.php         # 已发布作业目录
|   |-- download.php           # 登录校验后的文件下载
|   |-- worldcup/index.php     # 世界杯数据库模式
|   |-- worldcup/api.php       # 世界杯只读 JSON 接口与写入预留边界
|   |-- worldcup/index.html    # 世界杯只读资料快照
|   |-- worldcup/demo.html     # 合成比分与本地支持演示
|   `-- assets/
|       |-- css/
|       |-- js/
|       `-- images/
|-- data/
|   |-- install.sql            # 全组 20 张表与初始资料，无虚构赛事或投票
|   |-- team/                  # 七项正式团队材料（包括录屏）
|   `-- personal/              # 个人作业汇总
|       |-- README.md
|       |-- lin-ziyuan/
|       |-- yan-yuanyu/
|       |-- ma-yuxiang/
|       `-- han-yuchen/
|-- scripts/                   # 部署等辅助脚本
`-- storage/
    `-- uploads/               # 上传文件
```

留言模块由 `app/team/models/GuestbookMessage.php`、`MemberMotto.php`、两个控制器和 `app/team/views/guestbook/admin.php` 构成。公共登录、会话、CSRF、PDO 及限频位于 `app/common/`，供后续模块复用；预留空目录仍用 `.gitkeep` 保留。
