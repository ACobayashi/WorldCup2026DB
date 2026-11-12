<?php
// 2410933_马禹翔
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | DQD six-axis ability radar shared by profile and comparison. */
declare(strict_types=1);
$abilityAxes=\Fourward\worldcup\models\PlayerAbility::AXES;$abilityCount=count($abilityAxes);
$abilityPoint=static function(int $index,float $value,float $radius=122)use($abilityCount):array{$angle=-M_PI/2+2*M_PI*$index/$abilityCount;return [230+cos($angle)*$radius*$value/100,205+sin($angle)*$radius*$value/100];};
$abilityPolygon=static function(array $values)use($abilityPoint):string{$out=[];foreach(array_values($values) as $index=>$value){$xy=$abilityPoint($index,(float)$value);$out[]=round($xy[0],2).','.round($xy[1],2);}return implode(' ',$out);};
$available=array_values(array_filter($radarPlayers,fn($player)=>is_array($player['ability']??null)));
?>
<?php if(!$available): ?><div class="wc-radar-empty"><h3>暂无懂球帝能力数据</h3><p>不从比赛统计推算或补全评分。</p></div><?php else: ?>
<div class="wc-ability-visual">
<?= $chartMount('radar', [
  'axes'=>array_map(null, array_keys($abilityAxes), array_values($abilityAxes)),
  'players'=>array_map(static fn($player, $index) => [
    'name'=>$player['name'], 'side'=>$index,
    'values'=>$player['ability'] ? array_intersect_key($player['ability'], $abilityAxes) : null,
  ], $radarPlayers, array_keys($radarPlayers)),
]) ?>
<svg class="wc-radar wc-ability-radar" data-wc-chart-fallback viewBox="0 0 460 410" role="img" aria-labelledby="<?= e($radarId) ?>-title <?= e($radarId) ?>-desc">
  <title id="<?= e($radarId) ?>-title">球员综合能力雷达图</title><desc id="<?= e($radarId) ?>-desc">速度、射门、传球、盘带、防守和力量六项懂球帝来源评分。</desc>
  <?php foreach([20,40,60,80,100] as $level): ?><polygon class="wc-radar-grid" points="<?= e($abilityPolygon(array_fill(0,$abilityCount,$level))) ?>"/><?php endforeach; ?>
  <?php foreach(array_values($abilityAxes) as $index=>$label):$end=$abilityPoint($index,100);$text=$abilityPoint($index,100,158); ?><line class="wc-radar-spoke" x1="230" y1="205" x2="<?= e(round($end[0],2)) ?>" y2="<?= e(round($end[1],2)) ?>"/><text x="<?= e(round($text[0],2)) ?>" y="<?= e(round($text[1],2)) ?>" text-anchor="middle" dominant-baseline="middle"><?= e($label) ?></text><?php endforeach; ?>
  <?php foreach($radarPlayers as $series=>$player):if(!is_array($player['ability']??null))continue;$values=[];foreach($abilityAxes as $key=>$label)$values[]=$player['ability'][$key]; ?>
    <polygon class="wc-ability-series wc-ability-series-<?= $series===0?'a':'b' ?>" points="<?= e($abilityPolygon($values)) ?>"/>
    <?php foreach($values as $index=>$value):$xy=$abilityPoint($index,$value); ?><circle class="wc-ability-dot wc-ability-dot-<?= $series===0?'a':'b' ?>" cx="<?= e(round($xy[0],2)) ?>" cy="<?= e(round($xy[1],2)) ?>" r="4"><title><?= e($player['name'].' · '.array_values($abilityAxes)[$index].' '.$value) ?></title></circle><?php endforeach; ?>
  <?php endforeach; ?>
</svg>
<div class="wc-ability-legend" aria-label="雷达图颜色说明"><?php foreach($radarPlayers as $series=>$player): ?><span class="<?= $series===0?'is-a':'is-b' ?>"><i aria-hidden="true"></i><b><?= $series===0?'蓝色':'橙色' ?>：<?= e($player['name']) ?></b><small><?= $player['ability']?'综合能力 '.$player['ability']['overall']:'暂无能力值' ?></small></span><?php endforeach; ?></div>
</div>
<?php endif; ?>
