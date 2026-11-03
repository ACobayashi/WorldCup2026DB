<?php
/** FOURWARD coursework | Author: 韩羽宸 | Connected round-of-32 to final diagram with real fixtures. */
declare(strict_types=1);
$bracket=$data['bracket'];$nodes=[];foreach($bracket['nodes'] as $n)$nodes[$n['match_no']]=$n;
?>
<section class="wc-panel wc-bracket-panel"><div class="wc-section-heading"><div><p class="wc-eyebrow">ROAD TO THE FINAL</p><h2>淘汰赛晋级图</h2></div><span class="wc-muted">32 强 → 决赛</span></div><p class="wc-muted">点击对阵进入比赛详情。绿色表示该场胜者，点球比分单列；较窄屏幕可横向滚动。</p>
<?php if($bracket['warnings']): ?><p class="wc-notice">晋级资料存在冲突，连接线暂不显示。<?= e(implode(' ',$bracket['warnings'])) ?></p><?php endif; ?>
<div class="wc-bracket-scroll" tabindex="0" role="region" aria-label="可横向滚动的世界杯淘汰赛晋级图"><div class="wc-bracket-canvas" style="height:<?= e($bracket['height']) ?>px">
<?php foreach(['三十二强','十六强','四分之一决赛','半决赛','决赛'] as $col=>$label): ?><h3 class="wc-bracket-round" style="left:<?= $col*20 ?>%"><?= e($label) ?></h3><?php endforeach; ?>
<?php if(!$bracket['warnings']): ?><svg class="wc-bracket-lines" viewBox="0 0 1000 <?= e($bracket['height']) ?>" preserveAspectRatio="none" aria-hidden="true"><?php foreach($bracket['edges'] as $edge):$a=$nodes[$edge['from']];$b=$nodes[$edge['to']];$x=$a['column']*200+168;$mid=$a['column']*200+184;$end=$b['column']*200; ?><path d="M<?= e($x) ?> <?= e($a['y']) ?>H<?= e($mid) ?>V<?= e($b['y']) ?>H<?= e($end) ?>" /><?php endforeach; ?></svg><?php endif; ?>
<?php foreach($bracket['nodes'] as $n): ?><div class="wc-bracket-node<?= $n['match_no']===104?' is-final':'' ?>" style="left:<?= e($n['column']*20) ?>%;top:<?= e($n['y']-43) ?>px"><?php $bm=$n['match'];require __DIR__.'/bracket-match.php'; ?><?php if($n['match_no']===104&&$bm&&$bm['status']==='finished'&&$bm['winner_team_id']!==null): ?><span class="wc-champion-label">★ 冠军 · <?= e($bm['winner_team_id']===$bm['home_team_id']?$bm['home_name']:$bm['away_name']) ?></span><?php endif; ?></div><?php endforeach; ?>
</div></div>
<?php if($bracket['bronze']): ?><div class="wc-bronze"><h3>三四名决赛</h3><?php $bm=$bracket['bronze'];require __DIR__.'/bracket-match.php'; ?></div><?php endif; ?>
</section>
