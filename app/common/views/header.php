<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
$teamHref = $home ? '#team' : $prefix . './#team';
$membersHref = $home ? '#members' : $prefix . './#members';
?>
<header class="site-header app-header">
  <a class="brand" href="<?= e($teamHref) ?>" aria-label="FOURWARD 团队首页">
    <svg class="brand-mark" viewBox="0 0 40 40" aria-hidden="true"><path d="M8 3h9v34H8zM23 3h9v34h-9zM2 15h36v10H2z" fill="currentColor"/></svg>
    <span>FOURWARD<small>四人同行 / WORLD CUP 2026</small></span>
  </a>
  <nav class="main-nav" aria-label="主导航">
    <?php foreach (['team' => [$teamHref, '团队介绍'], 'members' => [$membersHref, '个人介绍'], 'worldcup' => [$prefix . 'worldcup/', '世界杯']] as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>" <?= $active === $key ? 'class="is-active" aria-current="location"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <a class="site-account <?= $active === 'account' ? 'is-active' : '' ?>" href="<?= e($prefix) ?>account.php" <?= $active === 'account' ? 'aria-current="page"' : '' ?>><?= $user ? '个人中心' : '登录 / 注册' ?><span aria-hidden="true">↗</span></a>
</header>
