<?php
// 2410933_马禹翔
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | World Cup shared server-page layout. */
declare(strict_types=1);
require __DIR__.'/helpers.php';
?>
<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111722">
    <title><?= e($title) ?> · FOURWARD 2026</title>
    <link rel="stylesheet" href="../assets/css/common.css?v=1">
    <link rel="stylesheet" href="../assets/css/worldcup-shell.css?v=2">
    <link rel="stylesheet" href="../assets/css/worldcup-data.css?v=9">
    <link rel="stylesheet" href="../assets/css/worldcup-charts.css?v=3">
    <?php if($section==='matches'): ?><link rel="stylesheet" href="../assets/css/worldcup-formation.css?v=1"><?php endif; ?>
    <script type="module" src="../assets/js/player-portraits.mjs?v=1"></script>
    <script type="module" src="../assets/js/player-compare.mjs?v=2"></script>
    <script type="module" src="../assets/js/worldcup-charts.mjs?v=3"></script>
  </head>
  <body class="wc-app">
    <a class="skip-link" href="#main">跳到主要内容</a>
    <header class="site-header">
      <a class="brand" href="../"><span class="wc-brand-mark" aria-hidden="true">#</span><span>FOURWARD<small>WORLD CUP / 2026</small></span></a>
      <nav class="main-nav" aria-label="主导航"><a href="../#team">团队介绍</a><a href="../#members">成员档案</a><a class="is-active" href="./" aria-current="location">世界杯</a></nav>
      <a class="repo-link" href="../admin.php"><?= $user ? e($user['username']) : '团队账号登录' ?> ↗</a>
    </header>
    <main id="main" class="wc-main" tabindex="-1">
      <div class="wc-heading"><div><p class="wc-eyebrow">FOURWARD / WORLD CUP 2026</p><h1><?= e($title) ?></h1></div><a class="glass-button" href="./index.html">查看只读快照 ↗</a><a class="glass-button" href="./demo.html">查看界面演示 ↗</a></div>
      <nav class="wc-tabs" aria-label="世界杯模块">
        <?php foreach (array_diff_key($titles, ['venues' => true, 'player' => true]) as $key => $label): ?>
          <a href="<?= e($url($key)) ?>" <?= $section === $key || ($section==='player'&&$key==='teams') ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php if (isset($overview)): ?>
        <div class="wc-summary" aria-label="数据概览">
          <?php foreach (['teams' => '支球队', 'matches' => '场比赛', 'venues' => '座场馆', 'supports' => '份支持'] as $key => $label): ?>
            <div><strong><?= e($overview['counts'][$key]) ?></strong><span><?= e($label) ?></span></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php require __DIR__ . '/' . $view . '.php'; ?>
      <?php if (isset($data['meta']) || isset($data['squad']['meta'])):
        $meta = $data['meta'] ?? $data['squad']['meta'];
      ?>
        <nav class="wc-pagination" aria-label="分页">
          <span>共 <?= e($meta['total']) ?> 条 · 第 <?= e($meta['page']) ?> 页</span>
          <?php if ($meta['page'] > 1): ?><a class="glass-button" href="<?= e($url($section, array_merge($input, ['page' => $meta['page'] - 1]))) ?>">上一页</a><?php endif; ?>
          <?php if ($meta['page'] < $meta['total_pages']): ?><a class="glass-button" href="<?= e($url($section, array_merge($input, ['page' => $meta['page'] + 1]))) ?>">下一页</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </main>
    <footer class="site-footer"><span>FOURWARD / WORLD CUP 2026</span><span>比赛时间均为北京时间 · 支持率来自本站用户</span><a href="../">返回团队主页 ↗</a></footer>
  </body>
</html>
