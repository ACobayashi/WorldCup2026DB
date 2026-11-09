<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Current user's own support history. */
declare(strict_types=1);
?>
<p class="wc-notice">支持功能尚未开放。这里仅展示当前登录账号已有的支持记录。</p>
<?php if (!$data['items']): ?><section class="wc-empty"><p class="wc-eyebrow">MY SUPPORT</p><h2>你还没有支持记录</h2><p>开放后可在比赛详情中，为未开赛的比赛选择支持球队。</p></section><?php else: ?>
  <div class="wc-grid"><?php foreach ($data['items'] as $entry): ?><article class="wc-card"><p class="wc-eyebrow">MATCH <?= e($entry['match_no']) ?></p><h2><?= e($entry['team_name']) ?></h2><p class="wc-muted"><?= e($beijingTime($entry['updated_at'])) ?></p><a class="glass-button" href="<?= e($url('matches', ['id' => $entry['match_id']])) ?>">查看比赛 ↗</a></article><?php endforeach; ?></div>
<?php endif; ?>
