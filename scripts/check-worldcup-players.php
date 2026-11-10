<?php
// 2410933_马禹翔
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Player panels, positional radar, coverage and rankings acceptance. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../app/common/bootstrap.php';
use Fourward\worldcup\models\PlayerBoard;
use Fourward\worldcup\controllers\ApiController;
use Fourward\worldcup\controllers\PageController;
use Fourward\common\HttpError;
$db=database();$api=new ApiController($db);$checks=0;startSession();$_SERVER['REQUEST_METHOD']='GET';
function playerCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$label);$checks++;}
function playerReject(callable $action,int $code):void {try{$action();}catch(HttpError $error){playerCheck($error->status===$code,'Expected HTTP '.$code);return;}throw new RuntimeException('Expected HTTP rejection');}
function playerHtml(PDO $db,array $input):string{ob_start();(new PageController($db))->handle($input);return ob_get_clean();}
playerCheck((int)$db->query('SELECT COUNT(*) FROM wc_player_match_stats WHERE advanced_stats IS NOT NULL')->fetchColumn()===3288,'All 3288 played rows extended');
playerCheck((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,3)='wc_'")->fetchColumn()===14,'No extra World Cup table');
$abilitySnapshot=json_decode(file_get_contents(PROJECT_ROOT.'/data/worldcup/player-abilities.json'),true,512,JSON_THROW_ON_ERROR);
$nameSnapshot=json_decode(file_get_contents(PROJECT_ROOT.'/data/worldcup/player-names.json'),true,512,JSON_THROW_ON_ERROR);
$assistSnapshot=json_decode(file_get_contents(PROJECT_ROOT.'/data/worldcup/player-assists.json'),true,512,JSON_THROW_ON_ERROR);
$goalkeeperSnapshot=json_decode(file_get_contents(PROJECT_ROOT.'/data/worldcup/goalkeeper-metrics.json'),true,512,JSON_THROW_ON_ERROR);
playerCheck(count($nameSnapshot['players']??[])===1248,'All player Chinese names mapped');
playerCheck(($assistSnapshot['roster_total']??0)===1248&&($assistSnapshot['nonzero_players']??0)>0,'Complete tournament assist ranking mapped');
playerCheck(($goalkeeperSnapshot['matches_checked']??0)===104&&count($goalkeeperSnapshot['rows']??[])===214,'All match goalkeeper appearances captured');
foreach(['扑救','阻止进球','禁区射门扑救','出击/成功'] as $field)playerCheck(in_array($field,$goalkeeperSnapshot['observed_source_fields']??[],true),'Goalkeeper source field '.$field);
playerCheck(($abilitySnapshot['requested']??0)===1248&&($abilitySnapshot['available']??0)+($abilitySnapshot['unavailable']??0)===1248,'All DQD ability endpoints checked');
playerCheck(count(array_filter($abilitySnapshot['failures']??[],fn($reason)=>$reason!=='ability unavailable'))===0,'Ability omissions are source-declared, not network failures');
$ids=[];$axisKeys=[];
foreach(['Thibaut COURTOIS','Arthur THEATE','Kevin DE BRUYNE','Romelu LUKAKU','Zeno DEBAST'] as $name){
  $q=$db->prepare('SELECT id FROM wc_players WHERE name=?');$q->execute([$name]);$id=(int)$q->fetchColumn();playerCheck($id>0,'Player exists '.$name);$ids[]=$id;
  $p=$api->dispatch('player','GET',['id'=>$id]);
  playerCheck($p['name_en']===$name&&$p['name']!==$name&&$p['player_name_zh']===$p['name'],'Chinese display name with English detail reference');
  $team=$api->dispatch('team','GET',['id'=>$p['team_id']]);$roster=array_values(array_filter($team['squad'],fn($r)=>$r['id']===$id))[0];
  playerCheck((int)$roster['appearances']===$p['summary']['appearances'],'Appearance count agrees with team');
  playerCheck(($roster['goals']===null?null:(int)$roster['goals'])===$p['summary']['totals']['goals'],'Goal total agrees with team');
  playerCheck(count($p['matches'])===count($team['matches']),'Full tournament journey');
  $html=playerHtml($db,['view'=>'player','id'=>$id]);
  playerCheck(str_contains($html,e($name))&&str_contains($html,e($p['name'])),'Player detail shows Chinese and English names');
  playerCheck(str_contains($html,'id="season-overview"')&&str_contains($html,'id="single-performance"')&&str_contains($html,'id="player-match-log"'),'Season and single-match sections');
  playerCheck(!str_contains($html,'出生日期')&&!str_contains($html,'最高速度'),'No unverified extra fields');
  playerCheck(!str_contains($html,'id="player-heatmap"'),'Unavailable heatmap module removed');
  playerCheck(!str_contains($html,'POSITIONAL DATA RADAR'),'Progression and take-ons removed from radar');
  playerCheck(str_contains($html,'wc-ability-radar')===($p['ability']!==null),'Ability radar only with verified source values');
  if($p['ability']!==null){foreach(array_keys(\Fourward\worldcup\models\PlayerAbility::AXES) as $axis)playerCheck(isset($p['ability'][$axis])&&$p['ability'][$axis]>=0&&$p['ability'][$axis]<=100,'Official ability bounded '.$axis);}
  if($p['summary']['appearances']===0)playerCheck($p['summary']['totals']['goals']===null,'No-data stays null');
  foreach($p['matches'] as $m){$selected=$api->dispatch('player','GET',['id'=>$id,'match_id'=>$m['id']]);playerCheck($selected['selected_match']['id']===$m['id'],'Select correct individual fixture');}
  $card=$api->dispatch('team','GET',['id'=>$p['team_id']]);$teamHtml=playerHtml($db,['view'=>'teams','id'=>$p['team_id']]);playerCheck(str_contains($teamHtml,'view=player&amp;id='.$id),'Clickable roster profile');
  playerCheck(!str_contains($teamHtml,e($name))&&str_contains($teamHtml,e($p['name'])),'Team page uses Chinese player names only');
}
$comparison=$api->dispatch('player_compare','GET',[]);playerCheck(count($comparison['players'])===2,'Default two-player comparison');
playerCheck($comparison['players'][0]['id']!==$comparison['players'][1]['id'],'Comparison players differ');
$comparisonHtml=playerHtml($db,['view'=>'compare','player_a'=>$comparison['players'][0]['id'],'player_b'=>$comparison['players'][1]['id']]);
playerCheck(str_contains($comparisonHtml,'wc-ability-radar')&&str_contains($comparisonHtml,'本届世界杯实际表现'),'Comparison radar and raw statistics rendered');
playerCheck(str_contains($comparisonHtml,'wc-performance-chart')&&str_contains($comparisonHtml,'蓝色：')&&str_contains($comparisonHtml,'橙色：'),'Named radar legend and performance bar chart rendered');
playerCheck(str_contains($comparisonHtml,'data-compare-search')&&str_contains($comparisonHtml,'player-compare.mjs'),'Player comparison search rendered');
playerCheck(!str_contains($comparisonHtml,'能力值直接来自懂球帝球员页')&&str_contains($comparisonHtml,'助攻'),'Requested note removed and assists rendered');
playerCheck(!str_contains($comparisonHtml,e($comparison['players'][0]['name_en']))&&!str_contains($comparisonHtml,e($comparison['players'][1]['name_en'])),'Comparison page hides English player names');
$matchId=(int)$db->query('SELECT id FROM wc_matches ORDER BY id LIMIT 1')->fetchColumn();$match=$api->dispatch('match','GET',['id'=>$matchId]);
foreach(array_merge($match['lineups'],$match['events']) as $person)playerCheck($person['name']!==$person['name_en']&&$person['name']===$person['player_name_zh'],'Match names translated to Chinese');
$matchHtml=playerHtml($db,['view'=>'matches','id'=>$matchId]);foreach($match['lineups'] as $person)playerCheck(!str_contains($matchHtml,e($person['name_en'])),'Match page hides English player name');
playerReject(fn()=>$api->dispatch('player_compare','GET',['player_a'=>$ids[0],'player_b'=>$ids[0]]),422);
foreach(PlayerBoard::RANKINGS as $metric=>$label){
  $board=$api->dispatch('player_rankings','GET',['metric'=>$metric,'page_size'=>100]);
  playerCheck($board['items']!==[],'Available leaderboard '.$metric);$last=PHP_INT_MAX;$previous=null;$rank=null;
  foreach($board['items'] as $row){playerCheck($row['totals'][$metric]<=$last,'Descending ranking '.$metric);if($previous===$row['totals'][$metric])playerCheck($rank===$row['rank'],'Shared rank for tied values');$last=$previous=$row['totals'][$metric];$rank=$row['rank'];}
}
$goals=$api->dispatch('player_rankings','GET',['metric'=>'goals']);playerCheck($goals['items'][0]['totals']['goals']===10,'Leading scorer 10 goals');
$assists=$api->dispatch('player_rankings','GET',['metric'=>'assists']);playerCheck($assists['items'][0]['totals']['assists']===7&&$assists['items'][0]['name']!==$assists['items'][0]['name_en'],'Leading assists and Chinese name');
$saves=$api->dispatch('player_rankings','GET',['metric'=>'saves','page_size'=>100]);playerCheck($saves['items'][0]['totals']['saves']>0&&count(array_filter($saves['items'],fn($p)=>$p['position']!=='GK'))===0,'Save leaderboard contains goalkeepers only');
$keeper=$api->dispatch('player','GET',['id'=>$saves['items'][0]['id']]);$keeperHtml=playerHtml($db,['view'=>'player','id'=>$keeper['id']]);playerCheck(str_contains($keeperHtml,'门将')&&str_contains($keeperHtml,'阻止进球')&&str_contains($keeperHtml,'禁区内扑救'),'Goalkeeper-specific profile metrics rendered');
$fullNames=['Florian WIRTZ'=>'弗洛里安·维尔茨','Erling HAALAND'=>'埃尔林·哈兰德'];foreach($fullNames as $english=>$chinese){$q=$db->prepare('SELECT id FROM wc_players WHERE name=?');$q->execute([$english]);$full=$api->dispatch('player','GET',['id'=>(int)$q->fetchColumn()]);playerCheck($full['name']===$chinese,'Verified full Chinese name '.$english);}
$filtered=$api->dispatch('player_rankings','GET',['metric'=>'interceptions','position'=>'DF']);playerCheck(count(array_filter($filtered['items'],fn($p)=>$p['position']!=='DF'))===0,'Position filter');
playerReject(fn()=>$api->dispatch('player','GET',[]),422);playerReject(fn()=>$api->dispatch('player','GET',['id'=>PHP_INT_MAX]),404);
playerReject(fn()=>$api->dispatch('player','GET',['id'=>$ids[0],'match_id'=>PHP_INT_MAX]),404);
playerReject(fn()=>$api->dispatch('player_rankings','GET',['metric'=>['bad']]),422);
playerReject(fn()=>$api->dispatch('player_rankings','GET',['position'=>'XX']),422);
playerReject(fn()=>$api->dispatch('player_rankings','POST',[]),405);
$fake=['id'=>1,'team_id'=>1,'name'=>'Fixture','shirt_number'=>1,'position'=>'FW','code'=>'ARG','name_zh'=>'测试','match_id'=>1,'is_starter'=>1,'appeared'=>1,'goals'=>0,'shots'=>0,'passes_attempted'=>0,'passes_completed'=>0,'advanced_stats'=>null];
$summary=PlayerBoard::aggregate([$fake])[1];playerCheck($summary['totals']['goals']===0&&$summary['totals']['interceptions']===null,'Real zero distinct from missing extension');
playerCheck($summary['pass_completion_pct']===null,'Zero attempted passes do not become fake 0 percent');
$radar=PlayerBoard::radar($summary,[$summary]);foreach($radar as $a)playerCheck($a['score']===null||is_finite($a['score']),'Zero-range cohort handled');
echo "Player boards verified: $checks checks passed. No database writes.\n";
