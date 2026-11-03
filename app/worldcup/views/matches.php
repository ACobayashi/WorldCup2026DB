<?php
/** FOURWARD coursework | Author: 韩羽宸 | Fixture list and match detail view. */
declare(strict_types=1);
?>
<?php if (isset($data['match'])): $match = $data['match']; ?>
  <a class="wc-back" href="<?= e($url('matches')) ?>">← 返回赛程</a>
  <article class="wc-panel">
    <p class="wc-eyebrow">MATCH <?= e($match['match_no']) ?> / <?= e($statusLabels[$match['status']]) ?></p>
    <div class="wc-scoreboard"><h2><?= e($match['home_name'] ?? $match['home_slot'] ?? '待定球队') ?></h2><strong><?= e($score($match)) ?></strong><h2><?= e($match['away_name'] ?? $match['away_slot'] ?? '待定球队') ?></h2></div>
    <?php if ($match['home_penalty_score'] !== null): ?><p>点球大战 <?= e($match['home_penalty_score']) ?> : <?= e($match['away_penalty_score']) ?></p><?php endif; ?>
    <dl class="wc-facts">
      <?php foreach (['阶段' => $match['stage_name'], '小组' => $match['group_name'] ?? '不适用', '开球时间 / 北京时间' => $beijingTime($match['kickoff_at']), '场馆' => $match['venue_name'] ?? '待确认', '资料来源' => $match['source_url'] ?? '来源待补充', '资料采集时间 / 北京时间' => $beijingTime($match['source_retrieved_at'])] as $label => $value): ?>
        <div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php $total = $match['home_support'] + $match['away_support']; $homePercent = $total ? (int) round($match['home_support'] / $total * 100) : 0; ?>
    <section class="wc-support"><h3>本站用户支持</h3><p><?= $total ? e($match['home_support']) . ' 票 / ' . e($match['away_support']) . ' 票 · 共 ' . e($total) . ' 票' : '暂无支持记录' ?></p>
      <?php if ($total): ?><div class="wc-support-meter" role="img" aria-label="主队 <?= e($homePercent) ?>%，客队 <?= e(100 - $homePercent) ?>%"><span style="width:<?= e($homePercent) ?>%"></span></div><p><?= e($homePercent) ?>% / <?= e(100 - $homePercent) ?>%</p><?php endif; ?>
      <p class="wc-muted">支持功能尚未开放。支持率不代表获胜概率。</p><button class="glass-button" disabled>支持功能待开放</button>
    </section>
  </article>
<?php else: ?>
  <form class="wc-filters" method="get">
    <input type="hidden" name="view" value="matches">
    <label>比赛状态<select name="status"><?php foreach (['all' => '全部状态'] + $statusLabels as $key => $label): ?><option value="<?= e($key) ?>" <?= ($input['status'] ?? 'all') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>比赛日期 / 北京时间<input type="date" name="date" value="<?= e($input['date'] ?? '') ?>"></label>
    <button class="glass-button" type="submit">筛选赛程</button><a class="wc-back" href="<?= e($url('matches')) ?>">重置</a>
  </form>
  <?php if (!$data['items']): ?>
    <section class="wc-empty"><p class="wc-eyebrow">MATCH CENTER</p><h2>暂无符合条件的比赛</h2><p><?= $overview['counts']['matches'] ? '试试其他状态或日期。' : '赛事数据尚未导入，导入后将在这里展示赛程与赛果。' ?></p></section>
  <?php else: ?>
    <div class="wc-grid">
      <?php foreach ($data['items'] as $match): ?>
        <article class="wc-card"><p class="wc-eyebrow">MATCH <?= e($match['match_no']) ?> · <?= e($statusLabels[$match['status']]) ?></p><p><?= e($beijingTime($match['kickoff_at'])) ?></p><h2><?= e($match['home_name'] ?? $match['home_slot'] ?? '待定球队') ?> <span class="wc-muted">VS</span> <?= e($match['away_name'] ?? $match['away_slot'] ?? '待定球队') ?></h2><strong class="wc-card-score"><?= e($score($match)) ?></strong><p class="wc-muted"><?= e($match['stage_name']) ?> · <?= e($match['venue_name'] ?? '场馆待确认') ?></p><a class="glass-button" href="<?= e($url('matches', ['id' => $match['id']])) ?>">比赛详情 ↗</a></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
