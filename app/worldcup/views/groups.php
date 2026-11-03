<?php
/** FOURWARD coursework | Author: 韩羽宸 | Group membership view, without fabricated standings. */
declare(strict_types=1);
?>
<?php if (!$data['items']): ?><section class="wc-empty"><p class="wc-eyebrow">GROUPS</p><h2>分组资料尚未导入</h2><p>导入分组及球队归属后，将在这里展示。</p></section><?php else: ?>
  <div class="wc-grid"><?php foreach ($data['items'] as $group): ?><article class="wc-card"><p class="wc-eyebrow">GROUP <?= e($group['code']) ?></p><h2><?= e($group['name']) ?></h2><ul class="wc-list"><?php foreach ($group['teams'] as $team): ?><li><a href="<?= e($url('teams', ['id' => $team['id']])) ?>"><?= e($team['name_zh']) ?> ↗</a><span class="wc-muted"><?= e($team['code']) ?></span></li><?php endforeach; ?></ul><?php if (!$group['teams']): ?><p class="wc-muted">球队归属待导入。</p><?php endif; ?><a class="glass-button" href="<?= e($url('matches', ['group_id' => $group['id']])) ?>">小组赛程 ↗</a></article><?php endforeach; ?></div>
<?php endif; ?>
<p class="wc-muted">小组积分榜将在赛果计算功能完成后开放。</p>
