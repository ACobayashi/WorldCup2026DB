<?php
/** FOURWARD coursework | Author: 韩羽宸 | Match scoreboard, squads, comparison and event view. */
declare(strict_types=1);
$match=$data['match'];
$stats=[];foreach ($match['statistics'] as $row) $stats[$row['team_id']]=$row;
?>
<div class="wc-breadcrumb"><a href="<?= e($url('matches')) ?>">赛程与赛果</a><span>/</span><span>第 <?= e($match['match_no']) ?> 场</span></div>
<article class="wc-match-hero">
  <div class="wc-match-top"><span><?= e($match['stage_name']) ?><?= $match['group_name'] ? ' · '.e($match['group_name']) : '' ?></span><span class="wc-badge"><?= e($statusLabels[$match['status']]) ?></span></div>
  <div class="wc-big-score">
    <?php foreach (['home','away'] as $side): ?>
      <?php if ($side==='away'): ?><div class="wc-score-center"><strong><?= e($score($match)) ?></strong><span><?= $match['home_score_et']!==null ? '加时结束' : e($statusLabels[$match['status']]) ?></span><?php if ($match['home_penalty_score']!==null): ?><small>点球 <?= e($match['home_penalty_score']) ?> : <?= e($match['away_penalty_score']) ?></small><?php endif; ?><?php if ($match['home_score_et']!==null): ?><small>90 分钟 <?= e($match['home_score_90']) ?> : <?= e($match['away_score_90']) ?></small><?php endif; ?></div><?php endif; ?>
      <a class="wc-hero-team" href="<?= e($url('teams',['id'=>$match[$side.'_team_id']])) ?>"><?= $flag($match[$side.'_code'],$match[$side.'_name']??'') ?><h2><?= e($match[$side.'_name']??'待定球队') ?></h2><span><?= e($match[$side.'_code']??'TBD') ?></span></a>
    <?php endforeach; ?>
  </div>
  <div class="wc-match-meta"><span><?= e($beijingTime($match['kickoff_at'])) ?> · 北京时间</span><span><?= e($match['venue_name']??'场馆待确认') ?><?= $match['venue_city'] ? ' / '.e($match['venue_city']) : '' ?></span></div>
</article>
<nav class="wc-section-nav" aria-label="比赛详情分区"><a href="#lineups">双方阵容</a><a href="#statistics">技术统计</a><a href="#timeline">比赛事件</a></nav>
<div class="wc-detail-layout">
  <section class="wc-panel wc-lineup-panel" id="lineups"><div class="wc-section-heading"><div><p class="wc-eyebrow">MATCH SQUADS</p><h2>双方阵容</h2></div><span class="wc-muted">首发 / 替补</span></div>
    <div class="wc-lineups"><?php foreach (['home','away'] as $side): $team=$match[$side.'_team_id'];$squad=array_values(array_filter($match['lineups'],fn($r)=>$r['team_id']===$team)); ?>
      <div><h3 class="wc-team-heading"><?= $flag($match[$side.'_code'],$match[$side.'_name']??'') ?><a href="<?= e($url('teams',['id'=>$team])) ?>"><?= e($match[$side.'_name']??'待定球队') ?></a></h3>
        <?php if (!$squad): ?><p class="wc-muted">暂无阵容数据</p><?php else: ?>
          <?php foreach ([1=>'首发',0=>'替补'] as $starter=>$label): ?><h4 class="wc-squad-label"><?= e($label) ?><span><?= count(array_filter($squad,fn($p)=>$p['is_starter']===$starter)) ?> 人</span></h4><ul class="wc-player-list">
            <?php foreach ($squad as $p): if ($p['is_starter']!==$starter) continue; ?><li><span class="wc-shirt"><?= e($p['shirt_number']) ?></span><div><span><?= e($p['name']) ?></span><small><?= e($positions[$p['position']]??$p['position']) ?><?= !$starter ? ($p['appeared'] ? ' · 已上场' : ' · 未上场') : '' ?></small></div><?php if ($p['goals']): ?><b class="wc-goals"><?= e($p['goals']) ?> ⚽</b><?php endif; ?></li><?php endforeach; ?>
          </ul><?php endforeach; ?>
        <?php endif; ?>
      </div><?php endforeach; ?></div>
  </section>
  <aside class="wc-detail-aside">
    <section class="wc-panel" id="statistics"><div class="wc-section-heading"><div><p class="wc-eyebrow">TEAM COMPARISON</p><h2>技术统计</h2></div></div>
      <div class="wc-stat-teams"><span><?= $flag($match['home_code']) ?><?= e($match['home_name']) ?></span><span><?= e($match['away_name']) ?><?= $flag($match['away_code']) ?></span></div>
      <?php foreach (['possession_pct'=>['控球率','%'],'shots'=>['射门',''],'shots_on_target'=>['射正',''],'passes_attempted'=>['传球',''],'passes_completed'=>['成功传球',''],'pass_completion_pct'=>['传球成功率','%']] as $key=>[$label,$suffix]):
        $home=$stats[$match['home_team_id']][$key]??null;$away=$stats[$match['away_team_id']][$key]??null;
        $total=($home??0)+($away??0);$width=$total>0 && $home!==null && $away!==null ? round($home/$total*100,2) : null;
      ?><div class="wc-stat-row"><div><b><?= e($number($home,$suffix)) ?></b><span><?= e($label) ?></span><b><?= e($number($away,$suffix)) ?></b></div><div class="wc-stat-bar<?= $width===null ? ' is-unknown' : '' ?>"><?php if ($width!==null): ?><span style="width:<?= e($width) ?>%"></span><?php endif; ?></div></div><?php endforeach; ?>
      <p class="wc-muted wc-fineprint">— 表示暂无数据。控球率不含争夺中比例，双方之和可能不足 100%。</p>
    </section>
    <section class="wc-panel" id="timeline"><div class="wc-section-heading"><div><p class="wc-eyebrow">MATCH TIMELINE</p><h2>比赛事件</h2></div><span class="wc-muted"><?= count($match['events']) ?> 条</span></div>
      <?php if (!$match['events']): ?><p class="wc-muted">暂无事件记录。</p><?php else: ?><ol class="wc-timeline"><?php foreach ($match['events'] as $event): ?>
        <li class="wc-event wc-event-<?= e($event['event_type']) ?>"><time><?= e($event['minute_label']) ?></time><span class="wc-event-icon" aria-hidden="true"><?= e($eventIcons[$event['event_type']]??'·') ?></span><div><b><?= e($eventLabels[$event['event_type']]??$event['event_type']) ?></b><span><?= e($event['name']) ?></span><small><?= e($event['team_id']===$match['home_team_id'] ? $match['home_name'] : $match['away_name']) ?><?= !$event['appeared'] ? ' · 替补席' : '' ?></small></div></li>
      <?php endforeach; ?></ol><?php endif; ?><p class="wc-muted wc-fineprint">保留来源分钟标记；乌龙球记于实施球员名下，进球计入对手。</p>
    </section>
  </aside>
</div>
<details class="wc-panel wc-provenance"><summary>数据来源与本站支持</summary><p>比赛与名单基于 FIFA 本届赛事数据，统计及事件整理自 FIFA Training Centre 赛后报告。未知字段保留为空。</p><p class="wc-muted">比赛来源：<?= e($match['source_url']??'暂无') ?></p><p>本站支持 <?= e($match['home_support']) ?> : <?= e($match['away_support']) ?>。支持功能尚未开放，支持率不代表获胜概率。</p></details>
