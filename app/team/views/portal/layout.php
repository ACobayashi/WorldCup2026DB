<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
?>
<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · FOURWARD</title>
    <link rel="stylesheet" href="assets/css/common.css?v=1">
    <link rel="stylesheet" href="assets/css/team-portal.css?v=4">
    <link rel="stylesheet" href="assets/css/site-navigation.css?v=1">
    <?php if (($section ?? '') === 'stats'): ?>
      <script type="module" src="assets/js/workspace-charts.mjs?v=3"></script>
    <?php elseif (($section ?? '') === 'files'): ?>
      <script type="module" src="assets/js/coursework-upload.mjs?v=2"></script>
    <?php endif; ?>
  </head>
  <body class="portal-body">
    <a class="skip-link" href="#portal-main">跳到主要内容</a>
    <?= \Fourward\common\SiteNavigation::html($user, '', in_array($view, ['account', 'workspace'], true) ? 'account' : '') ?>
    <main id="portal-main" class="portal-main">
      <div class="portal-heading"><p>FOURWARD / TEAM SERVICES</p><h1><?= e($title) ?></h1></div>
      <?php require __DIR__ . '/' . $view . '.php'; ?>
    </main>
    <footer class="portal-footer"><span>FOURWARD · 四人同行</span><a href="./">返回团队主页</a></footer>
  </body>
</html>
