<?php
/** FOURWARD coursework | Author: 韩羽宸 | Player panels, positional radar, coverage and rankings acceptance. */
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
playerCheck((int)$db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn()===18,'No extra table');
$ids=[];$axisKeys=[];
foreach(['Thibaut COURTOIS','Arthur THEATE','Kevin DE BRUYNE','Romelu LUKAKU','Zeno DEBAST'] as $name){
  $q=$db->prepare('SELECT id FROM wc_players WHERE name=?');$q->execute([$name]);$id=(int)$q->fetchColumn();playerCheck($id>0,'Player exists '.$name);$ids[]=$id;
  $p=$api->dispatch('player','GET',['id'=>$id]);
  $team=$api->dispatch('team','GET',['id'=>$p['team_id']]);$roster=array_values(array_filter($team['squad'],fn($r)=>$r['id']===$id))[0];
  playerCheck((int)$roster['appearances']===$p['summary']['appearances'],'Appearance count agrees with team');
  playerCheck(($roster['goals']===null?null:(int)$roster['goals'])===$p['summary']['totals']['goals'],'Goal total agrees with team');
  playerCheck(count($p['matches'])===count($team['matches']),'Full tournament journey');
  $html=playerHtml($db,['view'=>'player','id'=>$id]);
  playerCheck(str_contains($html,'id="season-overview"')&&str_contains($html,'id="single-performance"')&&str_contains($html,'id="player-match-log"'),'Season and single-match sections');
  playerCheck(!str_contains($html,'出生日期')&&!str_contains($html,'最高速度'),'No unverified extra fields');
  if($p['summary']['appearances']===0){playerCheck(!str_contains($html,'class="wc-radar"'),'No fabricated zero-appearance radar');playerCheck($p['summary']['totals']['goals']===null,'No-data stays null');}
  else{
    $axisKeys[$p['position']]=array_column($p['radar'],'key');playerCheck(str_contains($html,'class="wc-radar"'),'Radar rendered');
    foreach($p['radar'] as $a)playerCheck($a['score']===null||($a['score']>=0&&$a['score']<=100&&is_finite($a['score'])),'Radar is finite and bounded');
    foreach($p['matches'] as $m){$selected=$api->dispatch('player','GET',['id'=>$id,'match_id'=>$m['id']]);playerCheck($selected['selected_match']['id']===$m['id'],'Select correct individual fixture');}
  }
  $card=$api->dispatch('team','GET',['id'=>$p['team_id']]);$teamHtml=playerHtml($db,['view'=>'teams','id'=>$p['team_id']]);playerCheck(str_contains($teamHtml,'view=player&amp;id='.$id),'Clickable roster profile');
}
playerCheck(count(array_unique(array_map(fn($k)=>implode(',',$k),$axisKeys)))===4,'GK DF MF FW use different axes');
foreach(PlayerBoard::RANKINGS as $metric=>$label){
  $board=$api->dispatch('player_rankings','GET',['metric'=>$metric,'page_size'=>100]);
  playerCheck($board['items']!==[],'Available leaderboard '.$metric);$last=PHP_INT_MAX;$previous=null;$rank=null;
  foreach($board['items'] as $row){playerCheck($row['totals'][$metric]<=$last,'Descending ranking '.$metric);if($previous===$row['totals'][$metric])playerCheck($rank===$row['rank'],'Shared rank for tied values');$last=$previous=$row['totals'][$metric];$rank=$row['rank'];}
}
$goals=$api->dispatch('player_rankings','GET',['metric'=>'goals']);playerCheck($goals['items'][0]['totals']['goals']===10,'Leading scorer 10 goals');
$filtered=$api->dispatch('player_rankings','GET',['metric'=>'interceptions','position'=>'DF']);playerCheck(count(array_filter($filtered['items'],fn($p)=>$p['position']!=='DF'))===0,'Position filter');
playerReject(fn()=>$api->dispatch('player','GET',[]),422);playerReject(fn()=>$api->dispatch('player','GET',['id'=>PHP_INT_MAX]),404);
playerReject(fn()=>$api->dispatch('player','GET',['id'=>$ids[0],'match_id'=>PHP_INT_MAX]),404);
playerReject(fn()=>$api->dispatch('player_rankings','GET',['metric'=>'assists']),422);
playerReject(fn()=>$api->dispatch('player_rankings','GET',['metric'=>['bad']]),422);
playerReject(fn()=>$api->dispatch('player_rankings','GET',['position'=>'XX']),422);
playerReject(fn()=>$api->dispatch('player_rankings','POST',[]),405);
$fake=['id'=>1,'team_id'=>1,'name'=>'Fixture','shirt_number'=>1,'position'=>'FW','code'=>'ARG','name_zh'=>'测试','match_id'=>1,'is_starter'=>1,'appeared'=>1,'goals'=>0,'shots'=>0,'passes_attempted'=>0,'passes_completed'=>0,'advanced_stats'=>null];
$summary=PlayerBoard::aggregate([$fake])[1];playerCheck($summary['totals']['goals']===0&&$summary['totals']['interceptions']===null,'Real zero distinct from missing extension');
playerCheck($summary['pass_completion_pct']===null,'Zero attempted passes do not become fake 0 percent');
$radar=PlayerBoard::radar($summary,[$summary]);foreach($radar as $a)playerCheck($a['score']===null||is_finite($a['score']),'Zero-range cohort handled');
echo "Player boards verified: $checks checks passed. No database writes.\n";
