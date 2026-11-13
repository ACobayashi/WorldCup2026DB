<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
?>
<a class="glass-button portal-back" href="./#members">← 返回个人介绍</a>
<a class="glass-button portal-back" href="downloads.php">源码与旧版归档 ↗</a>
<?php foreach ($profiles as $owner => $profile): ?>
  <section class="portal-section" id="<?= e($owner) ?>"><h2><?= $owner === 'team' ? '团队作业' : e($profile['display_name']) . '的个人作业' ?></h2><div class="portal-grid">
    <?php foreach ($labels[$owner === 'team' ? 'team' : 'personal'] as $number => $label): $file = $catalog[$owner][$number] ?? null; ?>
      <article class="portal-panel portal-file"><p class="portal-eyebrow"><?= $owner === 'team' ? 'TEAM' : 'PERSONAL' ?> / <?= e($number) ?></p><h3><?= e($label) ?></h3>
        <?php if ($file): ?><p><?= e($file['title']) ?></p><small><?= $file['source_type'] === 'link' ? '外部链接' : e($file['original_name']) . ' · ' . e(number_format($file['file_size'] / 1024, 1)) . ' KiB' ?></small><a class="glass-button" href="download.php?id=<?= e($file['id']) ?>"><?= $user ? ($file['source_type'] === 'link' ? '打开下载链接' : '下载作业') : '登录后下载' ?></a>
        <?php else: ?><p class="portal-muted">待提交</p><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div></section>
<?php endforeach; ?>
