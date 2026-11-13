<?php
// 2410933_马禹翔
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | World Cup shared server-page layout. */
/** 账号及作业入口接入：林子媛 | 学号：2410936 */
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
    <script src="../assets/js/worldcup-theme.js?v=2"></script>
    <link rel="stylesheet" href="../assets/css/common.css?v=1">
    <link rel="stylesheet" href="../assets/css/worldcup-shell.css?v=2">
    <link rel="stylesheet" href="../assets/css/worldcup-data.css?v=9">
    <?php if($section==='matches'): ?><link rel="stylesheet" href="../assets/css/worldcup-formation.css?v=1"><?php endif; ?>
    <link rel="stylesheet" href="../assets/css/worldcup-experience.css?v=8">
    <link rel="stylesheet" href="../assets/css/site-navigation.css?v=1">
    <script type="module" src="../assets/js/worldcup-experience.mjs?v=7"></script>
    <script type="module" src="../assets/js/player-portraits.mjs?v=1"></script>
    <script type="module" src="../assets/js/player-compare.mjs?v=2"></script>
  </head>
  <body class="wc-app">
    <a class="skip-link" href="#main">跳到主要内容</a>
    <?= \Fourward\common\SiteNavigation::html($user, '../', 'worldcup') ?>
    <main id="main" class="wc-main" tabindex="-1">
      <div class="wc-heading"><div><p class="wc-eyebrow">FOURWARD / WORLD CUP 2026</p><h1><?= e($title) ?></h1></div><div class="wc-heading-actions"></div></div>
      <nav class="wc-tabs" aria-label="世界杯模块">
        <?php foreach (array_diff_key($titles, ['venues' => true, 'player' => true]) as $key => $label): ?>
          <?php if (($key === 'admin' && (!$user || $user['role'] !== 'admin')) || ($key === 'support' && !$user)) continue; ?>
          <a href="<?= e($url($key)) ?>" <?= $section === $key || ($section==='player'&&$key==='teams') ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
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
    <footer class="site-footer"><span>FOURWARD / WORLD CUP 2026</span><span>比赛时间均为北京时间</span><a href="../">返回团队主页 ↗</a></footer>
  </body>
</html>
