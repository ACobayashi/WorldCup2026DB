<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Administrator scaffold and truthful capability status. */
declare(strict_types=1);
?>
<p class="wc-notice">已使用团队管理员账号登录。当前工作台提供数据概览，编辑与导入功能尚未开放。</p>
<div class="wc-grid">
  <?php foreach (['teams' => '球队资料', 'players' => '参赛阵容', 'venues' => '比赛场馆', 'stages' => '比赛阶段', 'groups' => '小组资料', 'group_teams' => '分组名单', 'matches' => '赛程与赛果', 'supports' => '支持记录'] as $key => $label): ?>
    <article class="wc-card"><p class="wc-eyebrow"><?= e(strtoupper($key)) ?></p><h2><?= e($label) ?></h2><strong class="wc-card-score"><?= e($data['counts'][$key]) ?></strong><p class="wc-muted">管理功能待开放</p><button class="glass-button" disabled>暂不可编辑</button></article>
  <?php endforeach; ?>
</div>
