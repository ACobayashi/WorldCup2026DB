<?php
/** FOURWARD coursework | Author: 严苑毓 | View: escaped management forms and messages. */
declare(strict_types=1);
$labels = ['pending' => '待审核', 'approved' => '已公开', 'rejected' => '未通过'];
$displayName = $user ? ($targets[$user['member_slug']]['name'] ?? $user['username']) : '';
$userPortrait = $user && isset($targets[$user['member_slug']]) && $user['member_slug'] !== 'team'
  ? 'assets/images/members/' . $user['member_slug'] . '.png' : null;
?>
<!doctype html>
<html lang="zh-CN">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141719">
    <title>团队工作台 · FOURWARD</title>
    <link rel="preload" as="image" href="assets/images/worldcup/stadium-night.jpg">
    <link rel="stylesheet" href="assets/css/guestbook-admin.css?v=6">
    <script type="module" src="assets/js/workspace.mjs?v=1"></script>
  </head>
  <body class="admin-body <?= !$user ? 'is-login' : '' ?>">
    <a class="skip-link" href="#workspace-main">跳到主要内容</a>
    <header class="admin-header">
      <div class="admin-header-inner">
        <a class="workspace-brand" href="./" aria-label="FOURWARD 团队首页">
          <svg class="workspace-mark" viewBox="0 0 40 40" aria-hidden="true"><path d="M8 3h9v34H8zM23 3h9v34h-9zM2 15h36v10H2z" fill="currentColor"/></svg>
          <span>FOURWARD<small>四人同行 / WORLD CUP 2026</small></span>
        </a>
        <nav class="workspace-nav" aria-label="工作台导航"><a href="./">团队主页</a><a href="./#members">成员档案</a><a class="is-active" href="admin.php" aria-current="page">工作台</a></nav>
        <div class="header-actions">
          <?php if ($user): ?>
            <span class="header-user"><?php if ($userPortrait): ?><span class="user-avatar"><img src="<?= e($userPortrait) ?>" alt=""></span><?php endif; ?><span><?= e($displayName) ?></span></span>
            <form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><button class="signout-button" type="submit">退出登录 <span aria-hidden="true">↗</span></button></form>
          <?php else: ?><a class="back-link" href="./">返回主页 <span aria-hidden="true">↗</span></a><?php endif; ?>
        </div>
      </div>
    </header>
    <main class="admin-main" id="workspace-main">
      <section class="workspace-hero" aria-labelledby="workspace-title">
        <div class="stadium-scene" aria-hidden="true">
          <img class="stadium-photo" src="assets/images/worldcup/stadium-night.jpg" alt="" width="1738" height="600">
          <div class="stadium-shade"></div><div class="stadium-beam"></div><div class="hero-grid"></div>
          <span class="hero-year">2026</span>
          <div class="pitch-art"><svg viewBox="0 0 440 290" fill="none"><rect x="10" y="10" width="420" height="270" rx="2"/><path d="M220 10v270M10 68h67v154H10M430 68h-67v154h67M10 106h25v78H10M430 106h-25v78h25"/><circle cx="220" cy="145" r="44"/><circle cx="220" cy="145" r="3"/><path d="M77 114a44 44 0 0 1 0 62M363 114a44 44 0 0 0 0 62"/><path class="tactic-line" d="M120 218 180 167 271 155 335 78"/></svg><img class="hero-football" src="assets/images/worldcup/football.svg" alt=""></div>
        </div>
        <div class="hero-topline"><span>TEAM WORKSPACE</span><span class="edition-tag">WORLD CUP EDITION <b>2026</b></span></div>
        <div class="hero-copy">
          <p class="hero-eyebrow"><span class="live-dot"></span>FOURWARD · 四人同行</p>
          <h1 id="workspace-title"><?= $user ? '我们的主场' : '欢迎回到主场' ?><span>一起上场</span></h1>
          <p class="hero-description"><?= $user ? '把热爱写下来，让每个声音找到自己的位置' : '登录团队账号，继续我们的世界杯之旅' ?></p>
          <?php if ($user): ?>
            <div class="hero-links"><a class="primary-button" href="#mottos-heading">编辑座右铭 <span aria-hidden="true">↗</span></a><?php if ($listing !== null): ?><a class="secondary-link" href="#review-heading">查看访客留言 <span aria-hidden="true">↓</span></a><?php endif; ?></div>
          <?php else: ?><a class="primary-button" href="#login-heading">进入工作台 <span aria-hidden="true">↓</span></a><?php endif; ?>
        </div>
        <div class="hero-bottomline"><span>04 MEMBERS <i></i> ONE TEAM</span><span class="hero-workspace-label">团队工作台</span><button class="motion-toggle" id="workspace-motion" type="button" aria-pressed="false" aria-label="暂停装饰动效" hidden><svg class="pause-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 3h3v10H4zM9 3h3v10H9z" fill="currentColor"/></svg><svg class="play-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="m5 3 8 5-8 5Z" fill="currentColor"/></svg><span>动效</span></button></div>
      </section>
      <?php if (!empty($errorMessage)): ?><p class="admin-feedback is-error" role="alert"><?= e(preg_replace('/。+$/u', '', $errorMessage)) ?></p><?php endif; ?>
      <?php if ($flash): ?><p class="admin-feedback is-success" role="status"><?= e(preg_replace('/。+$/u', '', $flash)) ?></p><?php endif; ?>
      <?php if (!$user): ?>
        <section class="login-panel" aria-labelledby="login-heading">
          <div class="login-heading"><span class="section-number">01</span><div><p class="section-eyebrow">TEAM ACCESS</p><h2 id="login-heading">球队集结</h2></div><img src="assets/images/worldcup/football.svg" alt="" aria-hidden="true"></div>
          <form method="post">
            <input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <div class="login-field"><label for="username">账号</label><input id="username" name="username" autocomplete="username" maxlength="48" required></div>
            <div class="login-field"><label for="password">密码</label><input id="password" name="password" type="password" autocomplete="current-password" maxlength="72" required></div>
            <button class="primary-button login-submit" type="submit">进入工作台 <span aria-hidden="true">↗</span></button>
          </form>
        </section>
      <?php else: ?>
        <section class="workspace-section" aria-labelledby="mottos-heading">
          <div class="section-heading"><span class="section-number">01</span><div><p class="section-eyebrow">OUR VOICES</p><h2 id="mottos-heading">把热爱写下来</h2></div><span class="section-caption">团队与个人座右铭</span></div>
          <div class="motto-grid">
            <?php foreach ($targets as $slug => $target): ?>
              <?php if ($user['role'] !== 'admin' && $user['member_slug'] !== $slug) continue; ?>
              <form method="post" class="motto-form <?= $slug === 'team' ? 'team-motto-form' : '' ?>" data-member="<?= e($slug) ?>">
                <input type="hidden" name="action" value="motto"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="member" value="<?= e($slug) ?>">
                <?php if ($slug === 'team'): ?><div class="team-motto-art" aria-hidden="true"><span>FOUR</span><img src="assets/images/team-group.png" alt="" width="1536" height="1024"></div><?php endif; ?>
                <div class="motto-heading">
                  <span class="motto-avatar" aria-hidden="true"><?php if ($slug === 'team'): ?>04<?php else: ?><img src="assets/images/members/<?= e($slug) ?>.png" alt=""><?php endif; ?></span>
                  <label for="motto-<?= e($slug) ?>"><?= $slug === 'team' ? '团队座右铭' : e($target['name']) ?></label>
                  <span class="motto-number" aria-hidden="true"><?= $slug === 'team' ? 'ONE TEAM' : str_pad((string) array_search($slug, array_keys($targets), true), 2, '0', STR_PAD_LEFT) . ' / 04' ?></span>
                </div>
                <textarea id="motto-<?= e($slug) ?>" name="motto" maxlength="240" rows="<?= $slug === 'team' ? '2' : '3' ?>" aria-label="<?= e($target['name']) ?>的座右铭" required><?= e($mottos[$slug]['text'] ?? '') ?></textarea>
                <div class="motto-bottom"><button class="save-button" type="submit" aria-label="保存<?= e($target['name']) ?>的座右铭">保存 <span aria-hidden="true">↗</span></button></div>
              </form>
            <?php endforeach; ?>
          </div>
        </section>
        <?php if ($listing !== null): ?>
          <section class="workspace-section review-section" aria-labelledby="review-heading">
            <div class="section-heading"><span class="section-number">02</span><div><p class="section-eyebrow">FROM THE STANDS</p><h2 id="review-heading">听见场外的声音</h2></div><span class="section-caption">访客留言</span></div>
            <div class="review-toolbar">
              <nav class="review-tabs" aria-label="审核状态"><?php foreach ($labels as $key => $label): ?><a href="admin.php?status=<?= e($key) ?>#review-heading" <?= $status === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
              <span class="review-total"><b><?= str_pad((string) $listing['total'], 2, '0', STR_PAD_LEFT) ?></b> 条留言</span>
            </div>
            <?php if (!$listing['items']): ?><div class="admin-empty"><svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M9 10h30a3 3 0 0 1 3 3v20a3 3 0 0 1-3 3H21l-10 7v-7H9a3 3 0 0 1-3-3V13a3 3 0 0 1 3-3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M15 20h18M15 27h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><p>暂无<?= e($labels[$status]) ?>留言</p></div><?php endif; ?>
            <?php foreach ($listing['items'] as $entry): ?>
              <article class="review-message">
                <div class="review-meta"><span class="visitor-avatar" aria-hidden="true"><?= e(mb_substr($entry['author'], 0, 1)) ?></span><div class="visitor-identity"><strong><?= e($entry['author']) ?></strong><span>给<?= e($targets[$entry['memberId']]['name']) ?></span></div><time datetime="<?= e($entry['createdAt']) ?>" title="<?= e($entry['createdAt']) ?>"><?= e((new DateTimeImmutable($entry['createdAt']))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('m月d日 H:i')) ?></time></div>
                <p class="visitor-message"><?= e($entry['message']) ?></p>
                <form method="post" class="review-actions">
                  <input type="hidden" name="action" value="review"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= e($entry['id']) ?>"><input type="hidden" name="filter" value="<?= e($status) ?>">
                  <?php foreach ($labels as $key => $label): ?><?php if ($key === $status) continue; ?><button class="review-button <?= $key === 'approved' ? 'is-primary' : '' ?>" name="status" value="<?= e($key) ?>" type="submit"><?= $key === 'approved' ? '通过并公开' : ($key === 'rejected' ? '不通过' : '移回待审核') ?></button><?php endforeach; ?>
                </form>
              </article>
            <?php endforeach; ?>
            <?php if ($page > 1 || $page * 20 < $listing['total']): ?><nav class="admin-pagination" aria-label="留言分页"><?php if ($page > 1): ?><a href="admin.php?status=<?= e($status) ?>&amp;page=<?= $page - 1 ?>#review-heading">← 上一页</a><?php endif; ?><span>第 <?= e($page) ?> 页</span><?php if ($page * 20 < $listing['total']): ?><a href="admin.php?status=<?= e($status) ?>&amp;page=<?= $page + 1 ?>#review-heading">下一页 →</a><?php endif; ?></nav><?php endif; ?>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </main>
    <footer class="workspace-footer"><div><span>FOURWARD</span><span>四人同行 / WORLD CUP 2026</span></div><a href="./">返回团队主页 <span aria-hidden="true">↗</span></a><small>球场摄影 <a href="https://commons.wikimedia.org/wiki/File:Campnou_3.jpg" target="_blank" rel="noopener noreferrer">Pure-football</a> · <a href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank" rel="noopener noreferrer">CC BY-SA 3.0</a></small></footer>
  </body>
</html>
