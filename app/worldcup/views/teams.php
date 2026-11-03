<?php
/** FOURWARD coursework | Author: 韩羽宸 | Team search and squad detail view. */
declare(strict_types=1);
?>
<?php if (isset($data['team'])): $team = $data['team']; ?>
  <a class="wc-back" href="<?= e($url('teams')) ?>">← 返回球队列表</a>
  <article class="wc-panel"><span class="wc-code"><?= e($team['code']) ?></span><h2><?= e($team['name_zh']) ?></h2><p class="wc-muted"><?= e($team['name_en']) ?> · <?= e($team['group_name'] ?? '分组待确认') ?></p><a class="glass-button" href="<?= e($url('matches', ['team_id' => $team['id']])) ?>">查看球队赛程 ↗</a><h3>本届参赛阵容</h3>
    <?php if (!$data['squad']['items']): ?><p class="wc-muted">阵容资料尚未导入。</p><?php else: ?>
      <div class="wc-table-wrap"><table><thead><tr><th>号码</th><th>姓名</th><th>位置</th><th>出生日期</th></tr></thead><tbody><?php foreach ($data['squad']['items'] as $player): ?><tr><td><?= e($player['shirt_number'] ?? '—') ?></td><td><?= e($player['name']) ?></td><td><?= e($player['position'] ?? '待补充') ?></td><td><?= e($player['birth_date'] ?? '待补充') ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <p class="wc-muted">资料来源：<?= e($team['source_url'] ?? '待补充') ?></p>
  </article>
<?php else: ?>
  <form class="wc-filters" method="get"><input type="hidden" name="view" value="teams"><label>搜索球队<input name="q" maxlength="80" placeholder="球队中文名、英文名或代码" value="<?= e($input['q'] ?? '') ?>"></label><button class="glass-button" type="submit">搜索</button><a class="wc-back" href="<?= e($url('teams')) ?>">重置</a></form>
  <?php if (!$data['items']): ?><section class="wc-empty"><p class="wc-eyebrow">TEAMS</p><h2>暂无符合条件的球队</h2><p><?= $overview['counts']['teams'] ? '试试其他球队名称或代码。' : '球队资料尚未导入。' ?></p></section><?php else: ?>
    <div class="wc-grid"><?php foreach ($data['items'] as $team): ?><article class="wc-card"><span class="wc-code"><?= e($team['code']) ?></span><h2><?= e($team['name_zh']) ?></h2><p class="wc-muted"><?= e($team['name_en']) ?></p><p><?= e($team['group_name'] ?? '分组待确认') ?></p><a class="glass-button" href="<?= e($url('teams', ['id' => $team['id']])) ?>">球队与阵容 ↗</a></article><?php endforeach; ?></div>
  <?php endif; ?>
<?php endif; ?>
