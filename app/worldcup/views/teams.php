<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | National team core squad and tournament journey. */
declare(strict_types=1);
?>
<?php if(isset($data['team'])): $team=$data['team']; ?>
<div class="wc-breadcrumb"><a href="<?= e($url('teams')) ?>">参赛球队</a><span>/</span><span><?= e($team['name_zh']) ?></span></div>
<section class="wc-team-hero"><div class="wc-team-identity"><?= $flag($team['code'],$team['name_zh']) ?><div><p class="wc-eyebrow"><?= e($team['code']) ?> / FIFA WORLD CUP 2026</p><h2><?= e($team['name_zh']) ?></h2><p><?= e($team['name_en']) ?> · <?= e($team['group_name']??'分组待确认') ?></p></div></div><a class="glass-button" href="<?= e($url('matches',['team_id'=>$team['id']])) ?>">全部球队赛程 ↗</a></section>
<div class="wc-team-summary"><?php foreach(['players'=>'名单球员','played'=>'已赛场次','goals_for'=>'进球','goals_against'=>'失球'] as $key=>$label): ?><div><strong><?= e($team['summary'][$key]) ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div>
<nav class="wc-section-nav" aria-label="国家队分区"><a href="#team-squad">球队名单</a><a href="#team-fixtures">比赛记录</a></nav>
<div class="wc-team-layout"><section class="wc-panel" id="team-squad"><div class="wc-section-heading"><div><p class="wc-eyebrow">NATIONAL TEAM SQUAD</p><h2>本届球队名单</h2></div><span class="wc-muted"><?= count($team['squad']) ?> 人</span></div>
<?php if(!$team['squad']): ?><p class="wc-muted">暂无名单数据。</p><?php else: ?>
<?php foreach($positions as $position=>$label): $players=array_values(array_filter($team['squad'],fn($p)=>$p['position']===$position));if(!$players)continue; ?>
<div class="wc-position-heading"><h3><?= e($label) ?></h3><span><?= count($players) ?> 人 · <?= e($position) ?></span></div>
<div class="wc-roster-grid"><?php foreach($players as $p): ?><a class="wc-roster-card" href="<?= e($url('player',['id'=>$p['id']])) ?>" aria-label="<?= e('查看 '.$p['name'].' 的球员数据') ?>"><div class="wc-roster-name"><?= $portrait($p,'medium') ?><h4><?= e($p['name']) ?><small class="wc-roster-shirt">#<?= e($p['shirt_number']??'—') ?></small></h4></div><div class="wc-roster-numbers"><span><b><?= e($p['appearances']) ?></b>出场</span><span><b><?= e($number($p['goals'])) ?></b>进球</span><span><b><?= e($number($p['tournament_assists'])) ?></b>助攻</span><span><b><?= e($number($p['shots'])) ?></b>射门</span></div><span class="wc-player-card-link">个人数据与雷达 ↗</span></a><?php endforeach; ?></div>
<?php endforeach; ?><p class="wc-muted wc-fineprint">出场按逐场名单统计；— 表示没有对应技术统计。数据范围为本届赛事，进球不含乌龙球或点球大战。</p>
<?php endif; ?></section>
<aside class="wc-panel" id="team-fixtures"><div class="wc-section-heading"><div><p class="wc-eyebrow">TOURNAMENT JOURNEY</p><h2>比赛记录</h2></div><span class="wc-muted"><?= count($team['matches']) ?> 场</span></div>
<?php if(!$team['matches']): ?><p class="wc-muted">暂无球队赛程。</p><?php else: ?><div class="wc-team-fixtures"><?php foreach($team['matches'] as $m):$home=$m['home_team_id']===$team['id'];$opponent=$home?'away':'home'; ?>
<a class="wc-team-fixture" href="<?= e($url('matches',['id'=>$m['id']])) ?>"><div><span><?= e($m['stage_name']) ?></span><time><?= e($beijingTime($m['kickoff_at'])) ?></time></div><div><span class="wc-opponent"><?= $flag($m[$opponent.'_code'],$m[$opponent.'_name']??'') ?><?= e($m[$opponent.'_name']??'待定') ?></span><strong><?= $m['home_score']===null?'— : —':e($m[$home?'home_score':'away_score'].' : '.$m[$home?'away_score':'home_score']) ?></strong></div><small><?= e($home?'主队':'客队') ?> · <?= e($statusLabels[$m['status']]) ?><?= $m['home_penalty_score']!==null ? ' · 点球 '.e($m[$home?'home_penalty_score':'away_penalty_score'].' : '.$m[$home?'away_penalty_score':'home_penalty_score']) : ($m['home_score_et']!==null?' · 含加时':'') ?> ↗</small></a>
<?php endforeach; ?></div><?php endif; ?></aside></div>
<?php else: ?>
<form class="wc-filters" method="get"><input type="hidden" name="view" value="teams"><label>搜索国家队<input name="q" maxlength="80" placeholder="中文名、英文名或球队代码" value="<?= e($input['q']??'') ?>"></label><button class="glass-button" type="submit">搜索</button><a class="wc-back" href="<?= e($url('teams')) ?>">全部球队</a></form>
<?php if(!$data['items']): ?><section class="wc-empty"><p class="wc-eyebrow">NATIONAL TEAMS</p><h2>暂无符合条件的球队</h2><p>试试其他球队名称或代码。</p></section><?php else: ?><div class="wc-grid wc-national-grid"><?php foreach($data['items'] as $t): ?><a class="wc-card wc-national-card" href="<?= e($url('teams',['id'=>$t['id']])) ?>"><div class="wc-national-card-top"><?= $flag($t['code'],$t['name_zh']) ?><span><?= e($t['group_name']??'分组待确认') ?></span></div><h2><?= e($t['name_zh']) ?></h2><p><?= e($t['name_en']) ?></p><div class="wc-national-card-bottom"><span><?= e($t['code']) ?></span><span>球队名单与赛程 ↗</span></div></a><?php endforeach; ?></div><?php endif; ?>
<?php endif; ?>
