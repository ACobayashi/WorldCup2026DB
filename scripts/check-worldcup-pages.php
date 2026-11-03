<?php
/** FOURWARD coursework | Author: 韩羽宸 | Real-snapshot MVC page acceptance, no persistent writes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../app/common/bootstrap.php';
use Fourward\worldcup\controllers\ApiController;
use Fourward\worldcup\controllers\PageController;
$db=database();$api=new ApiController($db);$checks=0;startSession();$_SERVER['REQUEST_METHOD']='GET';
function pageCheck(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$label);$checks++;}
function renderWorldcup(PDO $db,array $input):string {ob_start();(new PageController($db))->handle($input);return ob_get_clean();}
foreach([1,19,47,74,92,104] as $no){
  $q=$db->prepare('SELECT id FROM wc_matches WHERE match_no=?');$q->execute([$no]);$id=(int)$q->fetchColumn();
  $m=$api->dispatch('match','GET',['id'=>$id]);
  pageCheck(count($m['statistics'])===2,'Two team statistics '.$no);
  pageCheck(count(array_filter($m['lineups'],fn($p)=>$p['is_starter']===1))===22,'Starting XI '.$no);
  $html=renderWorldcup($db,['view'=>'matches','id'=>$id]);
  pageCheck(str_contains($html,'id="lineups"')&&str_contains($html,'id="statistics"')&&str_contains($html,'id="timeline"'),'Match page sections '.$no);
  pageCheck(str_contains($html,'flags/'.$m['home_code'].'.svg')&&str_contains($html,'flags/'.$m['away_code'].'.svg'),'Local flags '.$no);
  if($no===74)pageCheck(str_contains($html,'点球 3 : 4'),'Shootout separate from match score');
  if($no===104)pageCheck(str_contains($html,'90 分钟 0 : 0')&&str_contains($html,'加时结束'),'Extra-time score semantics');
  if($no===92)pageCheck(str_contains($html,'Jordan HENDERSON')&&str_contains($html,'替补席'),'Unused substitute card');
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
