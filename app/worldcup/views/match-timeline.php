<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Match event chart and source records. */
declare(strict_types=1);
$timelineEvents = $match['events'];
usort($timelineEvents, static fn($a, $b) => [$a['minute'], $a['added_minute'], $a['id']] <=> [$b['minute'], $b['added_minute'], $b['id']]);
$timelineNames = [$match['home_name'] ?? '主队', $match['away_name'] ?? '客队'];
$eventChart = array_map(static fn($event) => [
  'type'=>$event['event_type'], 'minute'=>(int)$event['minute'], 'added'=>(int)$event['added_minute'],
  'label'=>$event['minute_label'], 'name'=>$event['name'], 'side'=>$event['team_id'] === $match['home_team_id'] ? 0 : 1,
], $timelineEvents);
?>
<section class="wc-panel wc-event-panel" id="timeline">
  <div class="wc-section-heading"><div><p class="wc-eyebrow">MATCH TIMELINE</p><h2>比赛事件</h2></div><span class="wc-muted"><?= count($timelineEvents) ?> 条记录</span></div>
  <?php if (!$timelineEvents): ?>
    <p class="wc-muted">暂无事件记录。</p>
  <?php else: ?>
    <?= $chartMount('event-timeline', ['names'=>$timelineNames, 'extraTime'=>$match['home_score_et'] !== null, 'events'=>$eventChart]) ?>
    <ol class="wc-timeline" data-wc-event-list aria-label="比赛事件记录">
      <?php foreach ($timelineEvents as $index=>$event): $side = $eventChart[$index]['side']; ?>
        <li class="wc-event wc-event-<?= e($event['event_type']) ?>" data-wc-event-index="<?= e($index) ?>">
          <time><?= e($event['minute_label']) ?></time><span class="wc-event-icon" aria-hidden="true"><?= e($eventIcons[$event['event_type']] ?? '·') ?></span>
          <div><b><?= e($eventLabels[$event['event_type']] ?? $event['event_type']) ?></b><a href="<?= e($url('player', ['id'=>$event['player_id'],'match_id'=>$match['id']])) ?>#single-performance"><?= e($event['name']) ?></a><small><?= e($timelineNames[$side]) ?><?= !$event['appeared'] ? ' · 替补席' : '' ?></small></div>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
  <p class="wc-muted wc-fineprint">保留来源的分钟和补时标记。乌龙球记于实施球员名下，进球计入对手。</p>
</section>
