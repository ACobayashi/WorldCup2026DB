<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Searchable two-team comparison. */
declare(strict_types=1);
[$a,$b]=$data['teams'];
$money=static function(mixed $value):string {if($value===null)return '—';$value=(int)$value;return $value>=100000000?rtrim(rtrim(number_format($value/100000000,2,'.',''),'0'),'.').' 亿欧':number_format($value/10000,0).' 万欧';};
$metrics=[
  'highest_starting_xi_value_eur'=>['最高首发身价',$money],
  'played'=>['比赛场次',$number],'wins'=>['胜',$number],'draws'=>['平',$number],'losses'=>['负',$number],
  'goals_for'=>['进球',$number],'goals_against'=>['失球',$number],
  'expected_goals'=>['预期进球（xG）',$number],'expected_goals_against'=>['预期失球（xGA）',$number],
  'shots'=>['射门',$number],'shots_on_target'=>['射正',$number],
  'average_possession_pct'=>['场均控球率',static fn($v)=>$number($v,'%')],
  'passes_completed'=>['成功传球',$number],'pass_completion_pct'=>['传球成功率',static fn($v)=>$number($v,'%')],
  'clean_sheets'=>['零封场次',$number],
];
$chartKeys=['highest_starting_xi_value_eur','goals_for','expected_goals','shots','shots_on_target','average_possession_pct','passes_completed','pass_completion_pct','clean_sheets'];
?>
<section class="wc-panel wc-compare-picker"><div class="wc-section-heading"><div><p class="wc-eyebrow">TEAM COMPARISON</p><h2>选择两支球队</h2></div><span class="wc-muted">48 支参赛队均可搜索</span></div>
  <form method="get" action="./" class="wc-compare-form"><input type="hidden" name="view" value="team_compare"><?php foreach([['team_a',$a],['team_b',$b]] as [$name,$selected]): ?><label><?= $name==='team_a'?'球队 A':'球队 B' ?><input class="wc-player-search" type="search" data-compare-search data-compare-unit="球队" placeholder="输入中文名、英文名或代码搜索" autocomplete="off" aria-label="搜索<?= $name==='team_a'?'球队 A':'球队 B' ?>"><select name="<?= e($name) ?>" data-compare-select><?php foreach($data['options'] as $option): ?><option value="<?= e($option['id']) ?>" data-search="<?= e($option['name_zh'].' '.$option['name_en'].' '.$option['code'].' '.($option['group_name']??'')) ?>" <?= $option['id']===$selected['id']?'selected':'' ?>><?= e($option['name_zh'].' · '.$option['code'].' · '.($option['group_name']??'未分组')) ?></option><?php endforeach; ?></select><small class="wc-search-status" aria-live="polite"></small></label><?php endforeach; ?><button class="glass-button" type="submit">开始对比</button></form>
  <p class="wc-muted wc-fineprint">搜索支持球队中文名、英文名和三字代码；两侧可独立筛选。</p>
</section>
<div class="wc-compare-heroes wc-team-compare-heroes"><?php foreach([$a,$b] as $team): ?><article class="wc-team-compare-card"><div class="wc-team-compare-identity"><?= $flag($team['code'],$team['name_zh']) ?><div><p class="wc-eyebrow"><?= e($team['code'].' / '.($team['group_name']??'未分组')) ?></p><h2><?= e($team['name_zh']) ?></h2><p>本届 <?= e($team['played']) ?> 场 · <?= e($team['wins'].'胜 '.$team['draws'].'平 '.$team['losses'].'负') ?></p></div></div><strong class="wc-team-value"><?= e($money($team['highest_starting_xi_value_eur'])) ?><small>最高首发阵容身价</small></strong></article><?php endforeach; ?></div>
<section class="wc-panel"><div class="wc-section-heading"><div><p class="wc-eyebrow">TOURNAMENT PERFORMANCE</p><h2>本届世界杯球队表现</h2></div><span class="wc-muted">原始累计值，不按场均换算</span></div>
  <div class="wc-compare-chart-legend" aria-label="柱状图颜色说明"><span class="is-a"><i></i>蓝色：<?= e($a['name_zh']) ?></span><span class="is-b"><i></i>橙色：<?= e($b['name_zh']) ?></span></div>
  <div class="wc-performance-chart" aria-label="两队关键指标柱状图"><?php foreach($chartKeys as $key):$left=$a[$key];$right=$b[$key];$max=max((float)($left??0),(float)($right??0));$leftWidth=$max>0?100*(float)$left/$max:0;$rightWidth=$max>0?100*(float)$right/$max:0;[$label,$format]=$metrics[$key]; ?><div class="wc-performance-row"><strong><?= e($label) ?></strong><div class="wc-performance-bars"><div class="is-a"><span style="--bar:<?= e(round($leftWidth,2)) ?>%"></span><b><?= e($format($left)) ?></b></div><div class="is-b"><span style="--bar:<?= e(round($rightWidth,2)) ?>%"></span><b><?= e($format($right)) ?></b></div></div></div><?php endforeach; ?></div>
  <div class="wc-table-wrap"><table class="wc-compare-table"><thead><tr><th>指标</th><th><?= e($a['name_zh']) ?></th><th><?= e($b['name_zh']) ?></th></tr></thead><tbody><?php foreach($metrics as $key=>[$label,$format]): ?><tr><th><?= e($label) ?></th><td><?= e($format($a[$key])) ?></td><td><?= e($format($b[$key])) ?></td></tr><?php endforeach; ?><tr><th>净胜球</th><td><?= e(($a['goal_difference']>0?'+':'').$a['goal_difference']) ?></td><td><?= e(($b['goal_difference']>0?'+':'').$b['goal_difference']) ?></td></tr></tbody></table></div>
  <p class="wc-muted wc-fineprint">xG/xGA 来自逐场官方比赛报告汇总。身价来自懂球帝逐场首发阵容快照，展示该队本届赛事出现过的最高首发总身价，不等同于整队注册名单总身价；胜平负与比分不计点球大战。</p>
</section>
