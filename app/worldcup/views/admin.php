<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Administrator scaffold and truthful capability status. */
/** 维护：林子媛 | 学号：2410936 | 简化只读数据概览。 */
declare(strict_types=1);
?>
<div class="wc-grid">
  <?php foreach (['teams' => '球队资料', 'players' => '参赛阵容', 'venues' => '比赛场馆', 'stages' => '比赛阶段', 'groups' => '小组资料', 'group_teams' => '分组名单', 'matches' => '赛程与赛果', 'supports' => '支持记录'] as $key => $label): ?>
    <article class="wc-card"><p class="wc-eyebrow"><?= e(strtoupper($key)) ?></p><h2><?= e($label) ?></h2><strong class="wc-card-score"><?= e($data['counts'][$key]) ?></strong></article>
  <?php endforeach; ?>
</div>
