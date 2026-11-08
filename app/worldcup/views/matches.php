<?php
/** FOURWARD coursework | Author: 韩羽宸 | Fixture list and match detail view. */
declare(strict_types=1);
?>
<?php if (isset($data['match'])): $match = $data['match']; ?>
  <?php require __DIR__.'/match-detail.php'; ?>
<?php else: ?>
  <form class="wc-filters" method="get">
    <input type="hidden" name="view" value="matches">
    <label>搜索球队<input type="search" name="q" maxlength="80" placeholder="中文名、英文名或球队代码" value="<?= e($input['q'] ?? '') ?>"></label>
    <label>赛事阶段<select name="phase"><?php foreach (['all'=>'全部比赛','group'=>'小组赛','knockout'=>'淘汰赛'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= ($input['phase'] ?? 'all') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>比赛状态<select name="status"><?php foreach (['all' => '全部状态'] + $statusLabels as $key => $label): ?><option value="<?= e($key) ?>" <?= ($input['status'] ?? 'all') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>比赛日期 / 北京时间<input type="date" name="date" value="<?= e($input['date'] ?? '') ?>"></label>
    <button class="glass-button" type="submit">筛选赛程</button><a class="wc-back" href="<?= e($url('matches')) ?>">重置</a>
  </form>
  <?php if (!$data['items']): ?>
    <section class="wc-empty"><p class="wc-eyebrow">MATCH CENTER</p><h2>暂无符合条件的比赛</h2><p><?= $overview['counts']['matches'] ? '试试其他状态或日期。' : '赛事数据尚未导入，导入后将在这里展示赛程与赛果。' ?></p></section>
  <?php else: ?>
    <div class="wc-grid">
      <?php foreach ($data['items'] as $match): ?>
          <article class="wc-card"><p class="wc-eyebrow">MATCH <?= e($match['match_no']) ?> · <?= e($statusLabels[$match['status']]) ?></p><p><?= e($beijingTime($match['kickoff_at'])) ?></p><div class="wc-fixture-teams"><span><?= $flag($match['home_code'],$match['home_name']??'') ?><?= e($match['home_name'] ?? $match['home_slot'] ?? '待定球队') ?></span><span><?= $flag($match['away_code'],$match['away_name']??'') ?><?= e($match['away_name'] ?? $match['away_slot'] ?? '待定球队') ?></span></div><strong class="wc-card-score"><?= e($score($match)) ?></strong><?php if ($match['home_penalty_score']!==null): ?><p class="wc-muted">点球 <?= e($match['home_penalty_score']) ?> : <?= e($match['away_penalty_score']) ?></p><?php endif; ?><p class="wc-muted"><?= e($match['stage_name']) ?> · <?= e($match['venue_name'] ?? '场馆待确认') ?></p><a class="glass-button" href="<?= e($url('matches', ['id' => $match['id']])) ?>">比赛详情 ↗</a></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
