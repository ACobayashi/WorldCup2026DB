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
echo "World Cup pages verified: $checks checks passed. No database writes.\n";
