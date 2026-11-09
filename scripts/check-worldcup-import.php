<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Import rollback, referential checks and repeatability. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
require __DIR__.'/../app/common/bootstrap.php';
use Fourward\worldcup\services\DataImporter;
$db=database(); $checks=0;
function importCheck(bool $ok,string $message):void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function importSnapshot(PDO $db): array {
  $result=[];
  foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $rows=$db->query('SELECT * FROM `'.$table.'`')->fetchAll();
    $encoded=array_map(fn($row)=>json_encode($row,JSON_THROW_ON_ERROR),$rows); sort($encoded);
    $result[$table]=hash('sha256',implode("\n",$encoded));
  }
  return $result;
}
$data=json_decode(file_get_contents(PROJECT_ROOT.'/data/worldcup/pilot.json'),true,512,JSON_THROW_ON_ERROR);
$baseline=importSnapshot($db);
$result=(new DataImporter($db))->import($data,hash('sha256',random_bytes(32)),true);
importCheck($result['status']==='validated_rolled_back','Dry run was not rolled back');
importCheck(importSnapshot($db)===$baseline,'Dry run changed database rows');
foreach (['foreign-player','invalid-percentage','duplicate-event','source-collision'] as $case) {
  $bad=$data;
  if ($case==='foreign-player') $bad['players'][0]['team_id']=array_values(array_filter($data['teams'],fn($team)=>$team['id']!==$bad['players'][0]['team_id']))[0]['id'];
  if ($case==='invalid-percentage') $bad['player_stats'][0]['pass_completion_pct']=101;
  if ($case==='duplicate-event') $bad['events'][]=$bad['events'][0];
  if ($case==='source-collision') $bad['player_mappings'][]=['provider'=>'fifa','source_key'=>explode(':',$bad['players'][0]['id'],3)[2],'player_id'=>$bad['players'][1]['id']];
  $failed=false;
  try { (new DataImporter($db))->import($bad,hash('sha256',random_bytes(32)),true); } catch (Throwable $error) { $failed=true; }
  importCheck($failed,'Bad data was accepted: '.$case);
  importCheck(importSnapshot($db)===$baseline,'Failed transaction leaked records: '.$case);
}
echo "World Cup import checks passed: $checks (all test writes rolled back).\n";
