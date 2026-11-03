# 球员照片接入

由韩羽宸维护。当前本地 1,248 名球员都有照片：1,238 张为懂球帝提供的现成头肩头像，另外 10 名保留 FIFA 全身照。头像没有从全身照裁切，也没有生成虚构照片。榜单、球队名单、比赛阵容和个人面板共用同一个本地图片映射。

## 复现

需要 Python 3.10+、Pillow（`python -m pip install Pillow`）及网络。在仓库根目录执行：

```powershell
python scripts/worldcup_photos.py
php scripts/check-worldcup-photos.php
python -m unittest discover -s tests -p test_worldcup_photos.py
```

默认使用已提交的 `data/worldcup/player-photos/catalog.json` 下载清单，断点续传、有限并发、失败重试，校验 MIME、实际解码、尺寸、SHA-256 和文件名；已有效缓存不重复下载。`availability.json` 记录下载结果。浏览器只读取本站文件，不依赖第三方热链；本机缺少图片或加载失败时显示号码，不阻塞数据页面。图片约 12.2 MiB，缓存目录 `public/assets/images/player-photos/` 被 Git 忽略，换机器需要重新执行下载。

## 身份核查与来源

FIFA 球员 ID 通过已有 `wc_source_mappings` 与数据库关联，不新增数据库列或表。FIFA 原图取自本届赛季 `285023` 阵容的 `PlayerPicture`。原接口中萨卡和 Mohamed KONE 的图片 URL 指向其他球员，未采用；两人现已通过懂球帝名单核对接上正确大头照。少量旧 FIFA 图片 ID 仅在完整姓名各词精确一致时采用。

现成头像来自懂球帝本届赛季 `26123` 的比赛阵容 `logo`，原地址在 `headshots.json` 中。核对国家队、唯一球衣号、比赛时间和比分，同一懂球帝球员 ID、姓名和头像必须在至少两场已核对比赛中一致，保持两个来源的球员 ID 一一对应。不通过模糊姓名或跨球队号码合并。72 场小组赛原始响应缓存于被忽略的 `tmp/player-headshot-sources/`，已提交清单保留来源 URL、原响应 SHA-256 和参与核验的比赛。更新源数据可执行 `python scripts/worldcup_headshots.py --capture`；普通新机器无需重新抓阵容。

头像和 FIFA 原照片的权利属于各自权利人，不受统计 CSV 的 CC BY 或代码许可覆盖。这里只缓存供本地课程展示，未认定可以公开再分发；照片二进制不提交到仓库。署名说明见缓存目录 `CREDITS.md`。
