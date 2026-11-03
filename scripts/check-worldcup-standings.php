<?php
/** FOURWARD coursework | Author: 韩羽宸 | WC26 ranking edge cases, independent reference and graph checks. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../app/common/bootstrap.php';
use Fourward\worldcup\models\Standings;
use Fourward\worldcup\models\Knockout;
$db=database();$checks=0;
function standingCheck(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException('FAILED: '.$message);$checks++;}
$teams=[];foreach(range(1,4) as $id)$teams[]=['id'=>$id,'code'=>'T'.$id,'name_zh'=>'测试'.$id];
function game(int $a,int $b,?int $x,?int $y,string $status='finished'):array {return ['home_team_id'=>$a,'away_team_id'=>$b,'home_score_90'=>$x,'away_score_90'=>$y,'status'=>$status];}
$head=[game(1,2,1,0),game(1,3,0,1),game(1,4,1,0),game(2,3,8,0),game(2,4,8,0),game(3,4,0,1)];
$r=Standings::calculateGroup($teams,$head);
standingCheck(array_column($r,'id')===[1,2,4,3],'Head-to-head takes priority over superior overall goal difference');
$reapply=[game(1,2,1,0),game(3,1,1,0),game(2,3,3,2),game(1,4,9,0),game(2,4,1,0),game(3,4,8,0)];
standingCheck(array_column(Standings::calculateGroup($teams,$reapply),'id')===[2,3,1,4],'Reapply head-to-head to the remaining tied pair');
$draws=[game(1,2,0,0),game(1,3,0,0),game(1,4,0,0),game(2,3,0,0),game(2,4,0,0),game(3,4,0,0)];
$r=Standings::calculateGroup($teams,$draws);
standingCheck(count(array_filter($r,fn($t)=>$t['rank']===1&&$t['rank_end']===4&&$t['rank_tied']))===4,'Unknown conduct/ranking data stays an unresolved tie');
$r=Standings::calculateGroup($teams,[game(1,2,9,0,'scheduled'),game(3,4,null,null)]);
standingCheck(array_sum(array_column($r,'played'))===0&&array_sum(array_column($r,'points'))===0,'Scheduled and unknown-score fixtures are excluded');
$r=Standings::calculateGroup($teams,[]);standingCheck(array_sum(array_column($r,'played'))===0,'Empty fixture list');
$thirdFixture=[];foreach(range(1,12) as $id)$thirdFixture[]=['id'=>$id,'code'=>'X'.$id,'points'=>$id<=7?20-$id:($id<=9?4:1),'goal_difference'=>0,'goals_for'=>2];
$thirdResult=Standings::rankThirds($thirdFixture,true);
standingCheck(count(array_filter($thirdResult,fn($r)=>$r['qualified']===null))===2,'Tie crossing the eighth-place boundary remains pending');
standingCheck(count(array_filter($thirdResult,fn($r)=>$r['qualified']===true))===7,'Do not invent an eighth qualifier at a tie boundary');
$table=(new Standings($db))->table();
$reference=json_decode(file_get_contents(PROJECT_ROOT.'/data/worldcup/standings-reference.json'),true,512,JSON_THROW_ON_ERROR);
standingCheck($table['complete']&&count($table['groups'])===12,'Twelve completed groups');
$fields=['played'=>'matches_total','won'=>'matches_won','drawn'=>'matches_draw','lost'=>'matches_lost','goals_for'=>'goals_pro','goals_against'=>'goals_against','points'=>'points'];
$qualified=[];
foreach($table['groups'] as $i=>$group){
  standingCheck(count($group['teams'])===4,'Four teams in group '.$group['code']);
  foreach($group['teams'] as $j=>$team){$ref=$reference['groups'][$i]['teams'][$j];
    standingCheck($team['name_zh']===trim($ref['team_name'])&&$team['rank']===(int)$ref['rank'],'Independent rank/name '.$team['code']);
    foreach($fields as $key=>$source)standingCheck($team[$key]===(int)$ref[$source],'Independent '.$key.' '.$team['code']);
    standingCheck($team['won']+$team['drawn']+$team['lost']===$team['played'],'Result accounting '.$team['code']);
    if($team['qualified'])$qualified[]=$team['id'];
  }
}
standingCheck(count($qualified)===32,'Exactly 32 qualified teams');
$actual=[];foreach($db->query('SELECT home_team_id,away_team_id FROM wc_matches WHERE match_no BETWEEN 73 AND 88') as $m)$actual=[...$actual,$m['home_team_id'],$m['away_team_id']];sort($actual);sort($qualified);
standingCheck($actual===$qualified,'Computed qualification agrees with all actual round-of-32 participants');
standingCheck(count($table['thirds'])===12&&count(array_filter($table['thirds'],fn($r)=>$r['qualified']))===8,'Eight of twelve third-place teams qualify');
foreach($table['thirds'] as $t)if(in_array($t['code'],['ECU','GHA'],true))standingCheck($t['rank_tied']&&$t['rank']===3&&$t['rank_end']===4&&$t['qualified'],'Non-boundary third-place tie retained '.$t['code']);
$bracket=(new Knockout($db))->bracket();
standingCheck(count($bracket['nodes'])===31&&count($bracket['edges'])===30,'31 matches and 30 winner-feed edges');
standingCheck($bracket['warnings']===[],'Every parent winner is present in its next fixture');
standingCheck($bracket['bronze']['match_no']===103,'Third-place match separate from championship tree');
$nodes=[];foreach($bracket['nodes'] as $node)$nodes[$node['match_no']]=$node;
standingCheck($nodes[104]['column']===4,'Final in fifth round');
foreach($bracket['edges'] as $edge)standingCheck($nodes[$edge['from']]['column']+1===$nodes[$edge['to']]['column'],'Edges move exactly one round forward');
echo "World Cup standings and bracket verified: $checks checks passed. No database writes.\n";
