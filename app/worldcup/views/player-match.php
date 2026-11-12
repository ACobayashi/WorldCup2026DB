<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Interactive match selection and server-rendered details. */
declare(strict_types=1);
$selected = $p['selected_match'];
$appearanceLabel = static function (array $match): string {
  if ($match['appeared'] === null) return '名单未记录';
  if ($match['is_starter']) return '首发出场';
  return $match['appeared'] ? '替补出场' : '未上场';
};
$chartMetrics = $p['position'] === 'GK'
  ? [['saves','扑救',' 次'], ['goals_prevented','阻止进球',' 球'], ['saves_inside_box','禁区内扑救',' 次'], ['sweeper_actions_won','成功出击',' 次'], ['passes_completed','成功传球',' 次']]
  : [['shots','射门',' 次'], ['goals','进球',' 球'], ['passes_completed','成功传球',' 次'], ['interceptions','拦截',' 次'], ['tackles_won','成功抢断',' 次'], ['ball_progressions','推进',' 次']];
$chartGames = array_map(static fn(array $match): array => [
  'id'=>$match['id'], 'opponent'=>$match['opponent_name'], 'stage'=>$match['stage_name'],
  'appearance'=>$appearanceLabel($match), 'metrics'=>$match['appeared'] ? $match['metrics'] : [],
], $p['matches']);
?>
<section class="wc-panel" id="single-performance">
  <div class="wc-section-heading"><div><p class="wc-eyebrow">INDIVIDUAL MATCH PERFORMANCE</p><h2><?= e($p['name']) ?> · 单场发挥</h2></div></div>
  <?php if (!$selected): ?>
    <p class="wc-muted">暂无球队比赛记录。</p>
  <?php else: ?>
    <?= $chartMount('player-matches', ['games'=>$chartGames, 'metrics'=>$chartMetrics]) ?>
    <form class="wc-match-picker" method="get" action="./#single-performance">
      <input type="hidden" name="view" value="player"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
      <label>选择比赛<select name="match_id">
        <?php foreach ($p['matches'] as $match): ?>
          <option value="<?= e($match['id']) ?>" <?= $selected['id'] === $match['id'] ? 'selected' : '' ?>><?= e($beijingTime($match['kickoff_at']).' · '.$match['stage_name'].' · 对 '.$match['opponent_name']) ?></option>
        <?php endforeach; ?>
      </select></label>
      <button class="glass-button" type="submit">查看单场</button>
    </form>
    <div data-wc-match-body><?php require __DIR__.'/player-match-detail.php'; ?></div>
    <?php $initialId = $selected['id']; foreach ($p['matches'] as $selected): if ($selected['id'] === $initialId) continue; ?>
      <template data-wc-match-id="<?= e($selected['id']) ?>"><?php require __DIR__.'/player-match-detail.php'; ?></template>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
