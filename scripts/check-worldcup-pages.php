<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Real-snapshot MVC page acceptance, no persistent writes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../app/common/bootstrap.php';
use Fourward\worldcup\controllers\ApiController;
use Fourward\worldcup\controllers\PageController;
$db=database();$api=new ApiController($db);$checks=0;startSession();$_SERVER['REQUEST_METHOD']='GET';
function pageCheck(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$label);$checks++;}
function renderWorldcup(PDO $db,array $input):string {ob_start();(new PageController($db))->handle($input);return ob_get_clean();}
$groupMatches=$api->dispatch('matches','GET',['phase'=>'group','page_size'=>100]);$knockoutMatches=$api->dispatch('matches','GET',['phase'=>'knockout','page_size'=>100]);
pageCheck($groupMatches['meta']['total']===72&&$knockoutMatches['meta']['total']===32,'Group and knockout match filters partition all fixtures');
$spain=(int)$db->query("SELECT id FROM wc_teams WHERE name_zh='西班牙'")->fetchColumn();$spainMatches=$api->dispatch('matches','GET',['q'=>'西班牙','page_size'=>100]);
pageCheck($spainMatches['items']!==[]&&count(array_filter($spainMatches['items'],fn($m)=>(int)$m['home_team_id']!==$spain&&(int)$m['away_team_id']!==$spain))===0,'Team-name match search');
$matchFilters=renderWorldcup($db,['view'=>'matches','q'=>'西班牙','phase'=>'knockout']);pageCheck(str_contains($matchFilters,'name="q"')&&str_contains($matchFilters,'value="西班牙"')&&str_contains($matchFilters,'value="knockout" selected'),'Match search and phase controls rendered');
foreach([1,19,47,74,92,104] as $no){
  $q=$db->prepare('SELECT id FROM wc_matches WHERE match_no=?');$q->execute([$no]);$id=(int)$q->fetchColumn();
  $m=$api->dispatch('match','GET',['id'=>$id]);
  pageCheck(count($m['statistics'])===2,'Two team statistics '.$no);
  pageCheck(count(array_filter($m['lineups'],fn($p)=>$p['is_starter']===1))===22,'Starting XI '.$no);
  $html=renderWorldcup($db,['view'=>'matches','id'=>$id]);
  pageCheck(str_contains($html,'id="lineups"')&&str_contains($html,'id="statistics"')&&str_contains($html,'id="timeline"'),'Match page sections '.$no);
  pageCheck(str_contains($html,'wc-goalkeeper-line')&&str_contains($html,'扑救'),'Match page goalkeeper statistics '.$no);
  pageCheck(str_contains($html,'flags/'.$m['home_code'].'.svg')&&str_contains($html,'flags/'.$m['away_code'].'.svg'),'Local flags '.$no);
  if($no===74)pageCheck(str_contains($html,'点球 3 : 4'),'Shootout separate from match score');
  if($no===104)pageCheck(str_contains($html,'90 分钟 0 : 0')&&str_contains($html,'加时结束'),'Extra-time score semantics');
  if($no===92){$henderson=array_values(array_filter($m['lineups'],fn($p)=>$p['name_en']==='Jordan HENDERSON'))[0];pageCheck(str_contains($html,$henderson['name'])&&!str_contains($html,'Jordan HENDERSON')&&str_contains($html,'替补席'),'Unused substitute card in Chinese');}
}
$own=(int)$db->query("SELECT match_id FROM wc_match_events WHERE event_type='own_goal' LIMIT 1")->fetchColumn();
pageCheck(str_contains(renderWorldcup($db,['view'=>'matches','id'=>$own]),'乌龙球'),'Own-goal label');
foreach($db->query('SELECT code FROM wc_teams') as $t)pageCheck(is_file(PROJECT_ROOT.'/public/assets/images/flags/'.$t['code'].'.svg'),'Flag exists '.$t['code']);
pageCheck(is_file(PROJECT_ROOT.'/public/assets/images/flags/LICENSE'),'Flag license retained');
foreach($db->query('SELECT id,code FROM wc_teams') as $row){
  $t=$api->dispatch('team','GET',['id'=>$row['id']]);
  pageCheck(count($t['squad'])===26,'Full unpaginated roster '.$row['code']);
  pageCheck(count($t['matches'])>=3,'Tournament journey '.$row['code']);
  pageCheck($t['summary']['players']===26 && $t['summary']['played']===count($t['matches']),'Team summary '.$row['code']);
  $html=renderWorldcup($db,['view'=>'teams','id'=>$row['id']]);
  pageCheck(substr_count($html,'class="wc-roster-card"')===26,'All 26 players rendered '.$row['code']);
  pageCheck(!str_contains($html,'出生日期')&&!str_contains($html,'身高'),'Only requested player fields');
}
$argentina=(int)$db->query("SELECT id FROM wc_teams WHERE code='ARG'")->fetchColumn();
$teamComparison=$api->dispatch('team_compare','GET',['team_a'=>$spain,'team_b'=>$argentina]);
pageCheck(count($teamComparison['teams'])===2&&count($teamComparison['options'])===48,'Two-team comparison and complete searchable options');
pageCheck($teamComparison['teams'][0]['expected_goals']>0&&$teamComparison['teams'][0]['expected_goals_against']>0,'Verified xG and xGA aggregates');
pageCheck($teamComparison['teams'][0]['highest_starting_xi_value_eur']>0,'Verified DQD starting-XI market value');
pageCheck($teamComparison['teams'][0]['played']>=3&&$teamComparison['teams'][0]['shots']>0&&$teamComparison['teams'][0]['passes_completed']>0,'Tournament team aggregates');
$teamCompareHtml=renderWorldcup($db,['view'=>'team_compare','team_a'=>$spain,'team_b'=>$argentina]);
pageCheck(substr_count($teamCompareHtml,'data-compare-search')===2&&str_contains($teamCompareHtml,'data-compare-unit="球队"'),'Independent team search controls rendered');
pageCheck(str_contains($teamCompareHtml,'两队关键指标柱状图')&&str_contains($teamCompareHtml,'预期进球（xG）')&&str_contains($teamCompareHtml,'最高首发阵容身价'),'Team comparison chart and metrics rendered');
try{$api->dispatch('team_compare','GET',['team_a'=>$spain,'team_b'=>$spain]);pageCheck(false,'Same-team comparison rejected');}catch(Fourward\common\HttpError $error){pageCheck($error->status===422,'Same-team comparison rejected');}
$html=renderWorldcup($db,['view'=>'groups']);
pageCheck(substr_count($html,'wc-standing-panel')===12,'All group tables rendered');
pageCheck(substr_count($html,'<tr class="is-qualified">')===32,'Exactly 32 qualification rows');
pageCheck(str_contains($html,'FIFA 2026 竞赛规程'),'Official rules linked');
$html=renderWorldcup($db,['view'=>'groups','panel'=>'bracket']);
pageCheck(substr_count($html,'class="wc-bracket-match"')===32,'31 knockout matches plus bronze card');
pageCheck(substr_count($html,'<path d=')===30,'Visible bracket connectors');
pageCheck(str_contains($html,'★ 冠军 · 西班牙'),'Champion comes from actual winner');
$html=renderWorldcup($db,['view'=>'groups','panel'=>'thirds']);
pageCheck(substr_count($html,'<tr class="is-qualified">')===8,'Eight third-place qualifiers rendered');
pageCheck(str_contains($html,'厄瓜多尔')&&str_contains($html,'加纳'),'Unresolved non-boundary comparison preserved');
pageCheck($api->dispatch('overview','GET',[])['capabilities']['standings'],'Standings capability enabled');
pageCheck(count($api->dispatch('bracket','GET',[])['nodes'])===31,'Bracket API available');
echo "World Cup pages verified: $checks checks passed. No database writes.\n";
