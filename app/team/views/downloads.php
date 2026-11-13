<?php
/** FOURWARD coursework | Author: 严苑毓 | 学号: 2413682 | Course archive list. */
/** 统一账号入口：林子媛 | 学号：2410936 */
declare(strict_types=1);
use Fourward\team\models\CourseMaterials;
?>
<?php if (!$user): ?>
  <p>登录后可下载自己的作业。教师验收请使用团队提供的管理员账号。</p>
  <a class="materials-download" href="account.php?return=downloads.php">登录 ↗</a>
<?php else: ?>
  <ul class="materials-list">
    <?php foreach ($targets as $slug => $target): ?>
      <?php if ($slug === 'team' || ($user['role'] !== 'admin' && $user['member_slug'] !== $slug)) continue; ?>
      <li>
        <strong><?= e($target['name']) ?></strong>
        <div>
          <?php $hasFiles = false; foreach (['1', '2', '3'] as $number):
            if (CourseMaterials::path($slug, $number, $user) === null) continue;
            $hasFiles = true; ?>
            <a href="downloads.php?member=<?= e($slug) ?>&amp;assignment=<?= e($number) ?>">作业 <?= e($number) ?> <span aria-hidden="true">↓</span></a>
          <?php endforeach; if (!$hasFiles): ?><span>尚未提供文件</span><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
