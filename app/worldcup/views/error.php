<?php
/** FOURWARD coursework | Author: 韩羽宸 | Escaped public error and shared login entry. */
declare(strict_types=1);
?>
<section class="wc-empty" role="status"><p class="wc-eyebrow"><?= e(http_response_code()) ?></p><h2><?= e($errorMessage) ?></h2>
  <?php if (in_array(http_response_code(), [401, 403], true)): ?><p>请使用团队账号登录；赛事工作台需要管理员权限。</p><a class="glass-button" href="../admin.php">前往团队账号登录 ↗</a><?php endif; ?>
  <a class="wc-back" href="./">返回赛事中心</a>
</section>
