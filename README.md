# 2026 世界杯课程设计

互联网数据库开发小组作业，包含团队主页和世界杯两个模块。

## Structure

```text
DBDev/
|-- README.md
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

当前仅为目录骨架，业务代码和安装 SQL 尚未生成。空目录用 `.gitkeep` 保留。
