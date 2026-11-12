<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Single-match results, statistics and personal events. */
declare(strict_types=1);
$home = $selected['home_team_id'] === $p['team_id'];
$own = $home ? 'home' : 'away';
$other = $home ? 'away' : 'home';
$metricValues = $selected['metrics'];
unset($metricCoverage);
$metricPassPct = isset($metricValues['passes_completed'], $metricValues['passes_attempted']) && $metricValues['passes_attempted'] > 0
  ? $metricValues['passes_completed'] / $metricValues['passes_attempted'] * 100 : null;
?>
<div class="wc-single-match-header">
  <div><span class="wc-opponent"><?= $flag($selected['opponent_code'],$selected['opponent_name']) ?>对阵 <?= e($selected['opponent_name']) ?></span><p><?= e($selected['stage_name'].' · '.$beijingTime($selected['kickoff_at'])) ?></p></div>
  <div class="wc-single-match-result"><strong><?= e($selected[$own.'_score_et'] ?? $selected[$own.'_score_90'] ?? '—') ?> : <?= e($selected[$other.'_score_et'] ?? $selected[$other.'_score_90'] ?? '—') ?></strong><small>本队比分在前<?= $selected['home_score_et'] !== null ? ' · 含加时' : '' ?></small></div>
  <span class="wc-badge"><?= e($appearanceLabel($selected)) ?></span>
</div>
<p><a class="wc-back" href="<?= e($url('matches',['id'=>$selected['id']])) ?>">完整比赛详情 ↗</a></p>
<?php if (!$selected['appeared']): ?><p class="wc-notice"><?= $selected['appeared'] === null ? '该场名单中未记录这名球员，暂无个人统计。' : '该场未上场，暂无个人技术统计。' ?></p><?php endif; ?>
<?php require __DIR__.'/player-metric-grid.php'; ?>
<?php $events = array_values(array_filter($p['events'], static fn($event) => $event['match_id'] === $selected['id'])); ?>
<div class="wc-single-events">
  <h3>本场个人事件</h3>
  <?php if (!$events): ?><p class="wc-muted">暂无已记录的个人事件。</p><?php else: ?>
    <ul class="wc-player-events"><?php foreach ($events as $event): ?><li><time><?= e($event['minute_label']) ?></time><span><?= e($eventIcons[$event['event_type']]) ?> <?= e($eventLabels[$event['event_type']]) ?></span></li><?php endforeach; ?></ul>
  <?php endif; ?>
</div>
