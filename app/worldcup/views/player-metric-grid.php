<?php
/** FOURWARD coursework | Author: 韩羽宸 | Grouped metric tiles for tournament and individual match scope. */
declare(strict_types=1);
$metricGroups=['进攻'=>['goals','assists','shots','take_ons','ball_progressions'],
  '传球'=>['passes_attempted','passes_completed','crosses_attempted','crosses_completed','line_breaks_attempted','line_breaks_completed','switches_of_play','step_ins'],
  '防守与对抗'=>['interceptions','tackles_attempted','tackles_won','clearances','blocks','possession_regains','duels_won_aerial','duels_won_physical','pressing_direct']];
if($p['position']==='GK')$metricGroups=['门将'=>array_keys(\Fourward\worldcup\models\PlayerBoard::GOALKEEPER_METRICS)]+$metricGroups;
?>
<div class="wc-grouped-player-stats"><?php foreach($metricGroups as $group=>$keys): ?><section class="wc-metric-category"><h3><?= e($group) ?></h3><dl><?php foreach($keys as $key): ?><div><dt><?= e($labels[$key]) ?></dt><dd><?= e($number($metricValues[$key]??null,$key==='goals_prevented'?' 球':'')) ?></dd><?php if(isset($metricCoverage)): ?><small><?= e($metricCoverage[$key]) ?>/<?= e($s['appearances']) ?> 场有记录</small><?php endif; ?></div><?php endforeach; ?><?php if($group==='传球'): ?><div><dt>传球成功率</dt><dd><?= e($number($metricPassPct,'%')) ?></dd><?php if(isset($metricCoverage)): ?><small>按成功传球 / 传球计算</small><?php endif; ?></div><?php endif; ?></dl></section><?php endforeach; ?></div>
