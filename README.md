# 2026 世界杯课程设计

互联网数据库开发小组作业，包含团队主页和世界杯两个模块。

在线展示：[NKDEV · FOURWARD](https://nkdev-fourward.snappysky32.chatgpt.site)。代码协作：[GitHub 仓库](https://github.com/ACobayashi/WorldCup2026DB)。在线站点是发布快照，不会自动同步 GitHub 提交。

## 本地预览

```sh
python3 -m http.server 5173 --bind 127.0.0.1 --directory public
```

打开 http://127.0.0.1:5173 。运行 `node scripts/check-prototype.mjs` 检查初版。

当前实现独立场景转场、暗色四人档案、人物刹停入场和反向面部背景动效，以及浏览器本地留言。使用导航、滚轮或滑动切换场景，成员卡片支持方向键。四位人物使用独立透明素材，存放于 `public/assets/images/members/`。人物形象、分工及个人留言是占位草案，四位成员的 GitHub 主页已接入；世界杯仅保留入口。尚未接入 PHP MVC、数据库或后台，留言不会发送给本人。

## Structure

```text
DBDev/
|-- README.md
|-- AGENTS.md                  # 简短贡献指南
|-- app/
|   |-- common/                # 公共功能
|   |-- team/                  # 团队主页
|   |   |-- controllers/
|   |   |-- models/
|   |   `-- views/
|   `-- worldcup/              # 世界杯项目
|       |-- controllers/
|       |-- models/
|       `-- views/
|-- config/                    # 配置
|-- public/                    # 网站入口
|   |-- index.html             # 团队主页交互初版
|   `-- assets/
|       |-- css/
|       |-- js/
|       `-- images/
|-- data/
|   |-- install.sql            # 数据库安装 SQL，实现后生成
|   |-- team/                  # 六项正式团队材料
|   `-- personal/              # 个人作业汇总
|       |-- README.md
|       |-- member-a/
|       |-- member-b/
|       |-- member-c/
|       `-- member-d/
|-- scripts/                   # 部署等辅助脚本
`-- storage/
    `-- uploads/               # 上传文件
```

PHP MVC 与安装 SQL 尚未生成。空目录用 `.gitkeep` 保留。
