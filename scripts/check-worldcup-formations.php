<?php
/** FOURWARD coursework | Author: 韩羽宸 | Verify all actual pitch starters, rendering and stale-data refusal. */
declare(strict_types=1);
require __DIR__.'/../app/common/bootstrap.php';
use Fourward\worldcup\controllers\MatchController;
use Fourward\worldcup\controllers\PageController;
$db=database();$checks=0;$controller=new MatchController($db);
function formationCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
$matches=$db->query('SELECT id FROM wc_matches ORDER BY match_no')->fetchAll();formationCheck(count($matches)===104,'Full edition');
foreach($matches as $row){
  $match=$controller->show(['id'=>$row['id']]);$f=$match['formation'];formationCheck($f!==null,'Actual formation present');
  foreach(['home','away'] as $side){
    $players=$f['sides'][$side]['starters'];formationCheck(count($players)===11,'Eleven starters');
    $expected=array_column(array_filter($match['lineups'],fn($p)=>$p['team_id']===$match[$side.'_team_id']&&$p['is_starter']===1),'player_id');
    $actual=array_column($players,'player_id');sort($expected);sort($actual);formationCheck($actual===$expected,'Same players as DB squad');
    foreach($players as $p){formationCheck($p['x']>0&&$p['x']<100&&$p['y']>0&&$p['y']<100,'Positions inside pitch');foreach($p['markers'] as $e)formationCheck($e['player_id']===$p['player_id'],'Only own actual events');}
  }
}
startSession();$_SERVER['REQUEST_METHOD']='GET';
foreach([$matches[0]['id'],$matches[103]['id']] as $id){
  ob_start();(new PageController($db))->handle(['view'=>'matches','id'=>$id]);$html=ob_get_clean();
  formationCheck(substr_count($html,'<a class="wc-pitch-player')===22,'Twenty-two linked pitch players');
  formationCheck(str_contains($html,'worldcup-formation.css'),'Pitch stylesheet');
  formationCheck(!str_contains($html,'评分'),'No fabricated player ratings');
}
$id=$matches[0]['id'];$db->beginTransaction();
try{
  $stmt=$db->prepare('UPDATE wc_match_appearances SET is_starter=0 WHERE match_id=:id AND is_starter=1 LIMIT 1');$stmt->execute(['id'=>$id]);
  formationCheck($controller->show(['id'=>$id])['formation']===null,'Changed DB lineup refuses stale source formation');
}finally{$db->rollBack();}
echo "World Cup formations verified: $checks checks passed across 104 matches. Fixture change rolled back.\n";
