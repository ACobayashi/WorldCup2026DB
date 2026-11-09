<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Shared accessible group/third-place table. */
declare(strict_types=1);
?>
<div class="wc-table-wrap wc-standings-wrap" tabindex="0" role="region" aria-label="<?= e($tableLabel) ?>">
<table class="wc-standings-table"><caption class="sr-only"><?= e($tableLabel) ?></caption><thead><tr><th scope="col">排名</th><th scope="col">球队</th><?php if($isThird): ?><th scope="col">组</th><?php endif; ?><th scope="col">赛</th><th scope="col">胜</th><th scope="col">平</th><th scope="col">负</th><th scope="col">进/失</th><th scope="col">净胜</th><th scope="col">积分</th></tr></thead>
<tbody><?php foreach($tableRows as $r): ?>
<tr class="<?= $r['qualified']?'is-qualified':'' ?>"><td><span class="wc-rank"><?= e($r['rank']) ?><?= $r['rank_tied']?'=':'' ?></span></td><th scope="row"><a class="wc-standing-team" href="<?= e($url('teams',['id'=>$r['id']])) ?>"><?= $flag($r['code'],$r['name_zh']) ?><span><?= e($r['name_zh']) ?><?php if($r['qualified']): ?><small><?= $isThird?'晋级32强':e($r['qualification']) ?></small><?php elseif($r['rank_tied']): ?><small>同分待核实</small><?php endif; ?></span></a></th><?php if($isThird): ?><td><?= e($r['group_code']) ?></td><?php endif; ?><td><?= e($r['played']) ?></td><td><?= e($r['won']) ?></td><td><?= e($r['drawn']) ?></td><td><?= e($r['lost']) ?></td><td><?= e($r['goals_for'].'/'.$r['goals_against']) ?></td><td><?= e(($r['goal_difference']>0?'+':'').$r['goal_difference']) ?></td><td class="wc-points"><?= e($r['points']) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
