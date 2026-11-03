<?php
/** FOURWARD coursework | Author: 韩羽宸 | Computed standings and real knockout progression. */
declare(strict_types=1);
$standings=$data['standings'];$panel=$data['panel'];
?>
<nav class="wc-data-tabs" aria-label="积分与晋级分类"><?php foreach(['standings'=>'小组积分','thirds'=>'第三名比较','bracket'=>'淘汰赛图'] as $key=>$label): ?><a href="<?= e($url('groups',['panel'=>$key])) ?>" <?= $panel===$key?'aria-current="page"':'' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
<?php if(!$standings['groups']): ?><section class="wc-empty"><h2>分组资料尚未导入</h2></section>
<?php elseif($panel==='bracket'):require __DIR__.'/knockout.php';
elseif($panel==='thirds'): ?>
<section class="wc-panel"><div class="wc-section-heading"><div><p class="wc-eyebrow">BEST THIRD-PLACED TEAMS</p><h2>最佳第三名比较</h2></div><span class="wc-qualified-key">前八名晋级</span></div>
<?php $tableRows=$standings['thirds'];$tableLabel='各组第三名比较';$isThird=true;require __DIR__.'/standings-table.php'; ?>
<p class="wc-muted">按积分、净胜球、进球数比较。等号表示现有资料无法继续区分的并列名次；若并列跨越晋级线，则保留待确认。本届快照中厄瓜多尔与加纳的相对名次待核实，两队均在前八范围内。</p></section>
<?php else: ?>
<div class="wc-standings-heading"><p><?= $standings['complete']?'小组赛已结束 · 12 个小组':'小组赛进行中 · 暂定排名' ?></p><span class="wc-qualified-key">绿色标记已晋级32强</span></div>
<div class="wc-standings-grid"><?php foreach($standings['groups'] as $g): ?><section class="wc-standing-panel"><div class="wc-group-heading"><h2><b><?= e($g['code']) ?></b><?= e($g['name']) ?></h2><a href="<?= e($url('matches',['group_id'=>$g['id']])) ?>">小组赛程 ↗</a></div>
<?php $tableRows=$g['teams'];$tableLabel=$g['name'].'积分榜';$isThird=false;require __DIR__.'/standings-table.php'; ?>
</section><?php endforeach; ?></div>
<?php endif; ?>
<?php if($standings['groups']): ?><details class="wc-panel wc-provenance"><summary>排名与晋级规则</summary><p>胜 3 分、平 1 分、负 0 分。每组前两名和成绩最好的八个小组第三名晋级。</p><p><?= e($standings['tie_note']) ?></p><p><a href="<?= e($standings['rules_url']) ?>" target="_blank" rel="noopener noreferrer">FIFA 2026 竞赛规程 · 第 12–13 条 ↗</a></p></details><?php endif; ?>
