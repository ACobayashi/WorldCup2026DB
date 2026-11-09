<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Escaped two-team knockout match card. */
declare(strict_types=1);
?>
<?php if($bm): ?><a class="wc-bracket-match" href="<?= e($url('matches',['id'=>$bm['id']])) ?>" aria-label="<?= e('第'.$bm['match_no'].'场 '.$bm['home_name'].' 对 '.$bm['away_name']) ?>"><small><span>M<?= e($bm['match_no']) ?></span><span><?= $bm['home_penalty_score']!==null?'点球 '.e($bm['home_penalty_score'].':'.$bm['away_penalty_score']):($bm['home_score_et']!==null?'加时':'') ?></span></small>
<?php foreach(['home','away'] as $side):$winner=$bm['status']==='finished' && $bm['winner_team_id']===$bm[$side.'_team_id'] && $bm[$side.'_team_id']!==null; ?><span class="wc-bracket-team<?= $winner?' is-winner':($bm['status']==='finished'?' is-loser':'') ?>"><?= $flag($bm[$side.'_code'],$bm[$side.'_name']??'') ?><span><?= e($bm[$side.'_name']??'待定') ?></span><b><?= e($bm[$side.'_score']??'—') ?></b></span><?php endforeach; ?></a>
<?php else: ?><div class="wc-bracket-match is-pending"><span>对阵资料待导入</span></div><?php endif; ?>
